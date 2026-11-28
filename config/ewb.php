<?php
declare(strict_types=1);

/** Administrative classification. It never confers eligibility to trade. */
function ewb_category_inherits(array $rows, int $id): bool
{
    $seen = [];
    while ($id > 0 && isset($rows[$id]) && !isset($seen[$id])) {
        if ((int)($rows[$id]['ewb_required'] ?? 0) !== 0) return true;
        $seen[$id] = true;
        $id = (int)($rows[$id]['parent_id'] ?? 0);
    }
    return false;
}

function ewb_hold_existing_listings(PDO $pdo, array $rows, int $root): int
{
    if (!ewb_category_inherits($rows,$root)) return 0;
    if (!$pdo->inTransaction()) throw new LogicException('Category review requires a transaction.');
    $children = [];
    foreach ($rows as $id => $row) $children[(int)($row['parent_id'] ?? 0)][] = (int)$id;
    $pending = [$root];
    $subtree = [];
    while ($pending !== []) {
        $id = array_pop($pending);
        if (isset($subtree[$id])) continue;
        $subtree[$id] = true;
        array_push($pending,...($children[$id] ?? []));
    }
    $q = $pdo->prepare("UPDATE market_listings SET ewb_required=1,status=IF(status='active','review',status)
        WHERE (ewb_required=0 OR status='active') AND category_id IN (".implode(',',array_fill(0,count($subtree),'?')).')');
    $q->execute(array_keys($subtree));
    return $q->rowCount();
}
