<?php
declare(strict_types=1);

/** An additional platform rule, not a complete legal classification. */
function catalog_text_allowed(string $text): bool
{
    return preg_match('/(?<![\p{L}\p{N}])(?:schusswaffen?|waffen?|munition|patronen?|pistolen?|revolver|gewehre?|airsoft|schreckschuss|pfefferspray|sprengstoff)(?![\p{L}\p{N}])/iu', $text) === 0;
}

function catalog_rows(PDO $pdo): array
{
    $rows = $pdo->query('SELECT id, parent_id, name, slug, is_active, sort_order, created_at FROM market_categories ORDER BY sort_order, name, id LIMIT 1001')->fetchAll();
    if (count($rows) > 1000) throw new RuntimeException('Der Kategoriebaum überschreitet das konfigurierte Limit.');
    $byId = [];
    foreach ($rows as $row) {
        $row['id'] = (int)$row['id'];
        $row['parent_id'] = $row['parent_id'] === null ? null : (int)$row['parent_id'];
        $row['is_active'] = (int)$row['is_active'];
        $byId[$row['id']] = $row;
    }
    return $byId;
}

function catalog_row_allowed(array $rows, int $id): bool
{
    $seen = [];
    while ($id !== 0) {
        if (isset($seen[$id]) || !isset($rows[$id]) || $rows[$id]['is_active'] !== 1 || !catalog_text_allowed($rows[$id]['name'])) return false;
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
    foreach ($rows as $row) $children[(int)($row['parent_id'] ?? 0)][] = $row;
    $result = [];
    $seen = [];
    $visit = static function (int $parent, int $depth) use (&$visit, &$result, &$seen, $children, $rows, $activeOnly): void {
        foreach ($children[$parent] ?? [] as $row) {
            if (isset($seen[$row['id']])) continue;
            $seen[$row['id']] = true;
            if (!$activeOnly || catalog_row_allowed($rows, $row['id'])) {
                $row['depth'] = $depth;
                $row['available'] = catalog_row_allowed($rows, $row['id']);
                $result[] = $row;
            }
            $visit($row['id'], $depth + 1);
        }
    };
    $visit(0, 0);
    return $result;
}

function catalog_save_category(PDO $pdo, int $actorId, array $data): int
{
    settings_assert_admin($pdo, $actorId);
    $id = filter_var($data['id'] ?? '0', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $parent = filter_var($data['parent_id'] ?? '0', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $sort = filter_var($data['sort_order'] ?? '0', FILTER_VALIDATE_INT, ['options' => ['min_range' => -10000, 'max_range' => 10000]]);
    $name = $data['name'] ?? null;
    $slug = $data['slug'] ?? null;
    $active = $data['is_active'] ?? null;
    if ($id === false || $parent === false || $sort === false || !is_string($name) || !is_string($slug)
        || !preg_match('/\A.{2,100}\z/us', trim($name)) || preg_match('/[\x00-\x1F\x7F]/u', $name)
        || !preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug) || strlen($slug) > 100 || !in_array($active, ['0', '1'], true)) {
        throw new InvalidArgumentException('Bitte prüfe Name, Kennung, Oberkategorie und Sortierung.');
    }
    $name = trim($name);
    if (!catalog_text_allowed($name . ' ' . str_replace('-', ' ', $slug))) throw new InvalidArgumentException('Diese Produktgruppe ist für den allgemeinen Marktplatz nicht freigegeben.');
    $lockName = 'gunindex.categories.' . substr(hash('sha256', (string)$pdo->query('SELECT DATABASE()')->fetchColumn()), 0, 24);
    $statement = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $statement->execute([$lockName]);
    if ((int)$statement->fetchColumn() !== 1) throw new RuntimeException('Der Kategoriebaum wird gerade bearbeitet. Bitte erneut versuchen.');
    try {
        $pdo->beginTransaction();
        settings_assert_admin($pdo, $actorId);
        $rows = catalog_rows($pdo);
        if (($id !== 0 && !isset($rows[$id])) || ($parent !== 0 && !isset($rows[$parent]))) throw new InvalidArgumentException('Kategorie oder Oberkategorie ist nicht vorhanden.');
        if ($id === 0 && count($rows) >= 1000) throw new InvalidArgumentException('Das Limit von 1000 Kategorien ist erreicht.');
        $ancestor = $parent;
        $seen = [];
        while ($ancestor !== 0) {
            if (($id > 0 && $ancestor === $id) || isset($seen[$ancestor])) throw new InvalidArgumentException('Eine Kategorie darf nicht unter sich selbst oder einem ihrer Nachfahren liegen.');
            $seen[$ancestor] = true;
            $ancestor = (int)($rows[$ancestor]['parent_id'] ?? 0);
        }
        if ($id === 0) {
            $statement = $pdo->prepare('INSERT INTO market_categories(parent_id, name, slug, is_active, sort_order, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())');
            $statement->execute([$parent === 0 ? null : $parent, $name, $slug, (int)$active, $sort]);
            $id = (int)$pdo->lastInsertId();
            $action = 'category.created';
        } else {
            $statement = $pdo->prepare('UPDATE market_categories SET parent_id=?, name=?, slug=?, is_active=?, sort_order=? WHERE id=?');
            $statement->execute([$parent === 0 ? null : $parent, $name, $slug, (int)$active, $sort, $id]);
            $action = 'category.updated';
        }
        audit_log($pdo, $actorId, $action, $id, ['parent_id' => $parent ?: null, 'active' => (int)$active]);
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error instanceof PDOException && $error->getCode() === '23000') throw new InvalidArgumentException('Diese Kategoriekennung ist bereits vergeben oder die Zuordnung ist ungültig.');
        throw $error;
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
}
