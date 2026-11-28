<?php
declare(strict_types=1);

require_once __DIR__.'/i18n.php';
require_once __DIR__.'/ewb.php';

/** An additional platform rule, not a complete legal classification. */
function catalog_text_allowed(string $text): bool
{
    // These terms identify ink consumables; unrelated prohibited terms are still evaluated.
    $text = preg_replace('/\bPatronen?\s+für\s+(?:Tintenstrahl)?drucker\b/iu', 'Druckertinte', $text);
    if ($text === null) return false;
    return preg_match('/(?<![\p{L}\p{N}])(?:schusswaffen?|waffen?|munition|patronen?|pistolen?|revolver|gewehre?|airsoft|schreckschuss|pfefferspray|sprengstoff)(?![\p{L}\p{N}])/iu', $text) === 0;
}

function catalog_rows(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT id, parent_id, name, slug, is_active, ewb_required, sort_order, created_at
         FROM market_categories
         ORDER BY sort_order, name, id
         LIMIT 1001'
    )->fetchAll();

    if (count($rows) > 1000) {
        throw new RuntimeException(
            'Der Kategoriebaum überschreitet das konfigurierte Limit.'
        );
    }

    $byId = [];

    foreach ($rows as $row) {
        $row['id'] = (int)$row['id'];
        $row['parent_id'] = $row['parent_id'] === null
            ? null
            : (int)$row['parent_id'];
        $row['is_active'] = (int)$row['is_active'];
        $row['ewb_required'] = (int)$row['ewb_required'];

        $byId[$row['id']] = $row;
    }

    return $byId;
}

function catalog_row_allowed(array $rows, int $id): bool
{
    $seen = [];

    while ($id !== 0) {
        if (
            isset($seen[$id])
            || !isset($rows[$id])
            || $rows[$id]['is_active'] !== 1
            || (int)($rows[$id]['ewb_required'] ?? 0) !== 0
            || !catalog_text_allowed($rows[$id]['name'])
        ) {
            return false;
        }

        $seen[$id] = true;
        $id = (int)($rows[$id]['parent_id'] ?? 0);
    }

    return $seen !== [];
}

function catalog_category_allowed(PDO $pdo, int $id): bool
{
    return $id > 0 && catalog_row_allowed(catalog_rows($pdo), $id);
}

function catalog_categories(PDO $pdo, bool $activeOnly = true): array
{
    $rows = catalog_rows($pdo);
    $children = [];

    foreach ($rows as $row) {
        $children[(int)($row['parent_id'] ?? 0)][] = $row;
    }

    $result = [];
    $seen = [];

    $visit = static function (
        int $parent,
        int $depth
    ) use (
        &$visit,
        &$result,
        &$seen,
        $children,
        $rows,
        $activeOnly
    ): void {
        foreach ($children[$parent] ?? [] as $row) {
            if (isset($seen[$row['id']])) {
                continue;
            }

            $seen[$row['id']] = true;

            if (
                !$activeOnly
                || catalog_row_allowed($rows, $row['id'])
            ) {
                $row['depth'] = $depth;
                $row['available'] = catalog_row_allowed(
                    $rows,
                    $row['id']
                );
                if (!$activeOnly) {
                    $row['effective_ewb_required'] = ewb_category_inherits($rows, $row['id']);
                }

                $result[] = $row;
            }

            $visit($row['id'], $depth + 1);
        }
    };

    $visit(0, 0);

    return $result;
}

function catalog_category_write_lock(PDO $pdo): string
{
    $name = 'gunindex.categories.'.substr(
        hash(
            'sha256',
            (string)$pdo->query('SELECT DATABASE()')->fetchColumn()
        ),
        0,
        24
    );

    $statement = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $statement->execute([$name]);

    if ((int)$statement->fetchColumn() !== 1) {
        throw new DomainException(t('refine.category_busy'));
    }

    return $name;
}

function catalog_category_dependencies(PDO $pdo, int $id): array
{
    $references = [
        'children' => ['market_categories', 'parent_id'],
        'listings' => ['market_listings', 'category_id'],
        'attributes' => ['market_category_attributes', 'category_id'],
        'translations' => ['market_category_translations', 'category_id'],
        'wanted' => ['market_wanted', 'category_id'],
        'campaigns' => ['market_campaigns', 'category_id'],
        'placements' => ['market_business_inventory', 'category_id'],
        'bookings' => ['market_premium_bookings', 'category_id'],
    ];

    $dependencies = [];

    foreach ($references as $name => [$table, $column]) {
        $query = $pdo->prepare(
            "SELECT COUNT(*) FROM $table WHERE $column=?"
        );

        $query->execute([$id]);
        $count = (int)$query->fetchColumn();

        if ($count > 0) {
            $dependencies[$name] = $count;
        }
    }

    $searches = $pdo->prepare(
        "SELECT COUNT(*)
         FROM market_saved_searches
         WHERE JSON_UNQUOTE(
             JSON_EXTRACT(
                 CASE
                     WHEN JSON_VALID(criteria_json)
                     THEN criteria_json
                     ELSE '{}'
                 END,
                 '$.category_id'
             )
         )=?"
    );

    $searches->execute([(string)$id]);
    $count = (int)$searches->fetchColumn();

    if ($count > 0) {
        $dependencies['searches'] = $count;
    }

    $drafts = $pdo->prepare(
        "SELECT COUNT(*)
         FROM market_listing_drafts
         WHERE JSON_UNQUOTE(
             JSON_EXTRACT(payload, '$.category_id')
         )=?"
    );

    $drafts->execute([(string)$id]);
    $count = (int)$drafts->fetchColumn();

    if ($count > 0) {
        $dependencies['drafts'] = $count;
    }

    return $dependencies;
}

function catalog_remove_category(
    PDO $pdo,
    int $actor,
    int $id,
    bool $permanent
): void {
    governance_assert_actor(
        $pdo,
        $actor,
        'categories.manage'
    );

    $lock = catalog_category_write_lock($pdo);

    try {
        $pdo->beginTransaction();

        governance_assert_actor(
            $pdo,
            $actor,
            'categories.manage'
        );

        $query = $pdo->prepare(
            'SELECT is_active
             FROM market_categories
             WHERE id=?
             FOR UPDATE'
        );

        $query->execute([$id]);
        $active = $query->fetchColumn();

        if ($active === false) {
            throw new DomainException(
                t('admin_geography.category_missing')
            );
        }

        if (
            $permanent
            && catalog_category_dependencies($pdo, $id)
        ) {
            throw new DomainException(
                t('refine.category_blocked')
            );
        }

        if ($permanent || (int)$active === 1) {
            $pdo->prepare(
                $permanent
                    ? 'DELETE FROM market_categories WHERE id=?'
                    : 'UPDATE market_categories SET is_active=0 WHERE id=?'
            )->execute([$id]);

            audit_log(
                $pdo,
                $actor,
                $permanent
                    ? 'category.deleted'
                    : 'category.deactivated',
                $id
            );
        }

        $pdo->commit();

    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (
            $error instanceof PDOException
            && (int)($error->errorInfo[1] ?? 0) === 1451
        ) {
            throw new DomainException(
                t('refine.category_blocked')
            );
        }

        throw $error;

    } finally {
        $pdo->prepare(
            'SELECT RELEASE_LOCK(?)'
        )->execute([$lock]);
    }
}

function catalog_save_category(
    PDO $pdo,
    int $actorId,
    array $data
): int {
    function_exists('governance_assert_actor')
        ? governance_assert_actor(
            $pdo,
            $actorId,
            'categories.manage'
        )
        : settings_assert_admin($pdo, $actorId);

    $id = filter_var(
        $data['id'] ?? '0',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0]]
    );

    $parent = filter_var(
        $data['parent_id'] ?? '0',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0]]
    );

    $sort = filter_var(
        $data['sort_order'] ?? '0',
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => -10000,
                'max_range' => 10000,
            ],
        ]
    );

    $name = $data['name'] ?? null;
    $slug = $data['slug'] ?? null;
    $active = $data['is_active'] ?? null;
    $ewb = $data['ewb_required'] ?? null;

    if (
        $id === false
        || $parent === false
        || $sort === false
        || !is_string($name)
        || !is_string($slug)
        || !preg_match('/\A.{2,100}\z/us', trim($name))
        || preg_match('/[\x00-\x1F\x7F]/u', $name)
        || !preg_match(
            '/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
            $slug
        )
        || strlen($slug) > 100
        || !in_array($active, ['0', '1'], true)
        || ($ewb !== null && !in_array($ewb, ['0', '1'], true))
    ) {
        throw new InvalidArgumentException(
            t('classification.category_invalid')
        );
    }

    $name = trim($name);

    if (
        !catalog_text_allowed(
            $name . ' ' . str_replace('-', ' ', $slug)
        )
    ) {
        throw new InvalidArgumentException(
            t('classification.category_restricted')
        );
    }

    $lockName = catalog_category_write_lock($pdo);

    try {
        $pdo->beginTransaction();

        function_exists('governance_assert_actor')
            ? governance_assert_actor(
                $pdo,
                $actorId,
                'categories.manage'
            )
            : settings_assert_admin($pdo, $actorId);

        $rows = catalog_rows($pdo);
        $ewb ??= (string)($rows[$id]['ewb_required'] ?? 0);

        if (
            ($id !== 0 && !isset($rows[$id]))
            || ($parent !== 0 && !isset($rows[$parent]))
        ) {
            throw new InvalidArgumentException(
                t('classification.category_missing_parent')
            );
        }

        if ($id === 0 && count($rows) >= 1000) {
            throw new InvalidArgumentException(
                t('classification.category_limit')
            );
        }

        $ancestor = $parent;
        $seen = [];

        while ($ancestor !== 0) {
            if (
                ($id > 0 && $ancestor === $id)
                || isset($seen[$ancestor])
            ) {
                throw new InvalidArgumentException(
                    t('classification.category_cycle')
                );
            }

            $seen[$ancestor] = true;
            $ancestor = (int)(
                $rows[$ancestor]['parent_id'] ?? 0
            );
        }

        if ($id === 0) {
            $statement = $pdo->prepare(
                'INSERT INTO market_categories(
                    parent_id,
                    name,
                    slug,
                    is_active,
                    ewb_required,
                    sort_order,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
            );

            $statement->execute([
                $parent === 0 ? null : $parent,
                $name,
                $slug,
                (int)$active,
                (int)$ewb,
                $sort,
            ]);

            $id = (int)$pdo->lastInsertId();
            $action = 'category.created';

        } else {
            $statement = $pdo->prepare(
                'UPDATE market_categories
                 SET parent_id=?,
                     name=?,
                     slug=?,
                     is_active=?,
                     ewb_required=?,
                     sort_order=?
                 WHERE id=?'
            );

            $statement->execute([
                $parent === 0 ? null : $parent,
                $name,
                $slug,
                (int)$active,
                (int)$ewb,
                $sort,
                $id,
            ]);

            $action = 'category.updated';
        }

        $beforeEwb = (int)($rows[$id]['ewb_required'] ?? 0);
        $beforeParent = (int)($rows[$id]['parent_id'] ?? 0);
        $rows[$id] = ['parent_id'=>$parent ?: null,'ewb_required'=>(int)$ewb];
        $held = ($beforeEwb !== (int)$ewb || $beforeParent !== $parent) ? ewb_hold_existing_listings($pdo,$rows,$id) : 0;
        audit_log(
            $pdo,
            $actorId,
            $action,
            $id,
            [
                'parent_id' => $parent ?: null,
                'active' => (int)$active,
                'ewb_required' => (int)$ewb,
                'previous_ewb_required' => $beforeEwb,
                'effective_ewb_required' => ewb_category_inherits($rows,$id),
                'listings_held_for_review' => $held,
            ]
        );

        $pdo->commit();

        return $id;

    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (
            $error instanceof PDOException
            && $error->getCode() === '23000'
        ) {
            throw new InvalidArgumentException(
                t('classification.category_duplicate')
            );
        }

        throw $error;

    } finally {
        $release = $pdo->prepare(
            'SELECT RELEASE_LOCK(?)'
        );

        $release->execute([$lockName]);
    }
}
