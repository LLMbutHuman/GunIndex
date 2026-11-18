<?php
declare(strict_types=1);

const CLASSIFICATION_LIMIT = 16;

function classification_text(mixed $raw, int $limit, bool $empty = false): string
{
    if (!is_string($raw) || preg_match('/[\x00-\x1f\x7f]/', $raw)) {
        throw new InvalidArgumentException(t('classification.invalid'));
    }
    $value = trim($raw);
    if (($value === '' && !$empty) || !preg_match('/\A.{0,'.$limit.'}\z/us', $value) || !catalog_text_allowed($value)) {
        throw new InvalidArgumentException(t('classification.invalid'));
    }
    return $value;
}

/** Fixed precision without a float round trip. Match DECIMAL(12,3). */
function classification_number(mixed $raw): string
{
    if (!is_string($raw) || !preg_match('/\A(-?)(0|[1-9][0-9]{0,8})(?:[.,]([0-9]{1,3}))?\z/', trim($raw), $m)) {
        throw new InvalidArgumentException(t('classification.number'));
    }
    $fraction = str_pad($m[3] ?? '', 3, '0');
    $sign = $m[2] === '0' && $fraction === '000' ? '' : $m[1];
    return $sign.$m[2].'.'.$fraction;
}

function classification_definition(array $input): array
{
    $code = $input['code'] ?? null;
    $kind = $input['kind'] ?? null;
    if (!is_string($code) || !preg_match('/\A[a-z][a-z0-9]*(?:-[a-z0-9]+)*\z/', $code) || strlen($code) > 48
        || !catalog_text_allowed(str_replace('-', ' ', $code)) || !in_array($kind, ['text','number','choice','boolean'], true)) {
        throw new InvalidArgumentException(t('classification.invalid'));
    }
    $label = classification_text($input['label'] ?? null, 80);
    $unit = classification_text($input['unit'] ?? '', 24, true);
    $choices = [];
    $raw = $input['choices'] ?? '';
    if (!is_string($raw) || strlen($raw) > 6000 || ($kind !== 'number' && $unit !== '')) {
        throw new InvalidArgumentException(t('classification.invalid'));
    }
    if ($kind === 'choice') {
        foreach (preg_split('/\r\n|\n|\r/', trim($raw)) as $line) {
            $choices[] = classification_text($line, 100);
        }
        if (count($choices) < 2 || count($choices) > 25 || count(array_unique($choices)) !== count($choices)) {
            throw new InvalidArgumentException(t('classification.choices_help'));
        }
    } elseif (trim($raw) !== '') {
        throw new InvalidArgumentException(t('classification.invalid'));
    }
    return compact('code','kind','label','unit','choices');
}

/** Category lock serializes definitions with publication and listing changes. */
function classification_admin_save(PDO $pdo, int $actor, int $category, int $id, array $input): int
{
    $active = $input['is_active'] ?? null;
    $required = $input['is_required'] ?? null;
    if (!in_array($active, ['0','1'], true) || !in_array($required, ['0','1'], true) || $category < 1 || $id < 0) {
        throw new InvalidArgumentException(t('classification.invalid'));
    }
    $definition = $id === 0 ? classification_definition($input) : null;
    $pdo->beginTransaction();
    try {
        governance_assert_actor($pdo, $actor, 'categories.manage');
        $q = $pdo->prepare('SELECT id FROM market_categories WHERE id=? FOR UPDATE');
        $q->execute([$category]);
        if (!$q->fetchColumn()) throw new DomainException(t('classification.invalid'));
        $definitions = classification_definitions($pdo, $category, false, true);
        if ($id !== 0 && !isset($definitions[$id])) throw new DomainException(t('classification.invalid'));
        $activeCount = count(array_filter($definitions, static fn($row) => (int)$row['is_active'] === 1));
        if ($active === '1' && ($id === 0 || (int)$definitions[$id]['is_active'] === 0) && $activeCount >= CLASSIFICATION_LIMIT) {
            throw new DomainException(t('classification.limit'));
        }
        if ($definition !== null) {
            if (count($definitions) >= 100) throw new DomainException(t('classification.limit'));
            foreach ($definitions as $row) {
                if ($row['code'] === $definition['code']) throw new DomainException(t('classification.duplicate'));
            }
            $q = $pdo->prepare('INSERT INTO market_category_attributes(category_id,code,label,kind,unit,choices,is_required,is_active) VALUES (?,?,?,?,?,?,?,?)');
            $q->execute([$category,$definition['code'],$definition['label'],$definition['kind'],$definition['unit'],json_encode($definition['choices'], JSON_THROW_ON_ERROR),$required,$active]);
            $id = (int)$pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE market_category_attributes SET is_required=?,is_active=? WHERE id=? AND category_id=?')->execute([$required,$active,$id,$category]);
        }
        audit_log($pdo, $actor, 'category.attribute_saved', $id, ['category_id'=>$category,'required'=>(int)$required,'active'=>(int)$active]);
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function classification_definitions(PDO $pdo, int $category, bool $activeOnly = true, bool $lock = false): array
{
    if ($category < 1) return [];
    $q = $pdo->prepare('SELECT * FROM market_category_attributes WHERE category_id=?'.($activeOnly?' AND is_active=1':'').' ORDER BY id'.($lock?' FOR SHARE':''));
    $q->execute([$category]);
    $definitions = [];
    foreach ($q->fetchAll() as $row) {
        $row['choices'] = json_decode($row['choices'], true, 8, JSON_THROW_ON_ERROR);
        $definitions[(int)$row['id']] = $row;
    }
    return $definitions;
}

function classification_value(array $definition, mixed $raw): string
{
    return match ($definition['kind']) {
        'number' => classification_number($raw),
        'text' => classification_text($raw, 160),
        'choice' => in_array($raw, $definition['choices'], true) ? $raw : throw new InvalidArgumentException(t('classification.invalid')),
        'boolean' => in_array($raw, ['0','1'], true) ? $raw : throw new InvalidArgumentException(t('classification.invalid')),
        default => throw new LogicException('Unknown attribute kind.'),
    };
}

/** Reject unknown/cross-category IDs and arrays even when a field is optional. */
function classification_values(array $definitions, mixed $input, bool $publishing): array
{
    if (!is_array($input) || count($input) > CLASSIFICATION_LIMIT) throw new InvalidArgumentException(t('classification.invalid'));
    $values = [];
    foreach ($input as $id=>$value) {
        if (!isset($definitions[$id]) || !is_string($value)) throw new InvalidArgumentException(t('classification.invalid'));
        if ($value !== '') $values[$id] = classification_value($definitions[$id],$value);
    }
    foreach ($definitions as $id=>$definition) {
        if ($publishing && (int)$definition['is_required'] === 1 && !isset($values[$id])) {
            throw new InvalidArgumentException(t('classification.required',['field'=>$definition['label']]));
        }
    }
    ksort($values, SORT_NUMERIC);
    return $values;
}

function classification_entries(PDO $pdo, int $listing, bool $activeOnly = true): array
{
    $q = $pdo->prepare('SELECT a.id,a.label,a.kind,a.unit,COALESCE(v.text_value,CAST(v.number_value AS CHAR)) AS value'
        .' FROM market_listing_attributes v JOIN market_category_attributes a ON a.id=v.attribute_id'
        .' WHERE v.listing_id=?'.($activeOnly?' AND a.is_active=1':'').' ORDER BY a.id');
    $q->execute([$listing]);
    return $q->fetchAll();
}

function classification_store(PDO $pdo, int $id, int $category, array $definitions, array $values): void
{
    if (!$pdo->inTransaction()) throw new LogicException('Attribute writes require a listing transaction.');
    $q = $pdo->prepare('INSERT INTO market_listing_attributes(listing_id,category_id,attribute_id,text_value,number_value) VALUES (?,?,?,?,?)'
        .' ON DUPLICATE KEY UPDATE text_value=VALUES(text_value),number_value=VALUES(number_value)');
    foreach ($definitions as $attribute=>$definition) {
        if (!isset($values[$attribute])) {
            $pdo->prepare('DELETE FROM market_listing_attributes WHERE listing_id=? AND attribute_id=?')->execute([$id,$attribute]);
            continue;
        }
        $value = $values[$attribute];
        $numeric = $definition['kind'] === 'number';
        $q->execute([$id,$category,$attribute,$numeric?null:$value,$numeric?$value:null]);
    }
    classification_refresh_digest($pdo, $id);
}

function classification_refresh_digest(PDO $pdo, int $id): void
{
    $entries = classification_entries($pdo, $id, false);
    $hash = $entries ? hash('sha256', json_encode($entries, JSON_THROW_ON_ERROR)) : null;
    $pdo->prepare('UPDATE market_listings SET attribute_digest=? WHERE id=?')->execute([$hash,$id]);
}

function classification_copy(PDO $pdo, int $parent, int $child): void
{
    $q = $pdo->prepare('INSERT INTO market_listing_attributes(listing_id,category_id,attribute_id,text_value,number_value)'
        .' SELECT ?,category_id,attribute_id,text_value,number_value FROM market_listing_attributes WHERE listing_id=?');
    $q->execute([$child,$parent]);
    classification_refresh_digest($pdo, $child);
}

/** Convert only recognized filter operations to prepared EXISTS predicates. */
function classification_search(array $definitions, array $filters): array
{
    $conditions = [];
    $parameters = [];
    foreach (['attributes'=>'=','attribute_min'=>'>=','attribute_max'=>'<='] as $key=>$operator) {
        $raw = $filters[$key] ?? [];
        if (!is_array($raw) || count($raw) > CLASSIFICATION_LIMIT) throw new InvalidArgumentException(t('classification.invalid'));
        foreach ($raw as $id=>$value) {
            if (!is_string($value)) throw new InvalidArgumentException(t('classification.invalid'));
            if ($value === '') continue;
            $definition = $definitions[$id] ?? null;
            if (!$definition || ($operator !== '=' && $definition['kind'] !== 'number')) throw new InvalidArgumentException(t('classification.invalid'));
            $column = $definition['kind'] === 'number' ? 'number_value' : 'text_value';
            $conditions[] = 'EXISTS(SELECT 1 FROM market_listing_attributes av JOIN market_category_attributes ad ON ad.id=av.attribute_id'
                .' WHERE av.listing_id=l.id AND av.attribute_id=? AND ad.is_active=1 AND av.'.$column.$operator.'?)';
            array_push($parameters, $id, classification_value($definition, $value));
        }
    }
    return [$conditions,$parameters];
}
