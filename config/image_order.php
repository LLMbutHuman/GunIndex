<?php
declare(strict_types=1);

/** Ordered public IDs/positions only; no storage keys leave the service. */
function image_order_revision(array $rows): string
{
    return hash('sha256', json_encode(array_map(static fn(array $row): array => [(string)$row['id'], (int)$row['position']], $rows), JSON_THROW_ON_ERROR));
}

function image_order_validate(array $rows, mixed $positions, mixed $revision): array
{
    if (!is_array($positions) || count($rows)>5 || count($positions)!==count($rows)
        || !is_string($revision) || !hash_equals(image_order_revision($rows), $revision)) {
        throw new InvalidArgumentException(t('image_order.changed'));
    }
    $ordered=[];
    foreach ($rows as $index=>$row) {
        if ((int)$row['position']!==$index) throw new InvalidArgumentException(t('image_order.changed'));
        $position=$positions[(string)$row['id']]??null;
        if (!is_string($position) || !preg_match('/\A[1-5]\z/', $position)
            || (int)$position>count($rows) || isset($ordered[(int)$position-1])) {
            throw new InvalidArgumentException(t('image_order.invalid'));
        }
        $ordered[(int)$position-1]=(string)$row['id'];
    }
    ksort($ordered);
    return array_values($ordered);
}

/** Caller holds the listing lock and owns the entire listing transaction. */
function image_order_apply(PDO $pdo, int $listingId, array $positions, mixed $revision): void
{
    if (!$pdo->inTransaction()) throw new LogicException('Image ordering requires the listing transaction.');
    $query=$pdo->prepare('SELECT id,position FROM market_images WHERE listing_id=? ORDER BY position FOR UPDATE');
    $query->execute([$listingId]);
    $rows=$query->fetchAll();
    $ordered=image_order_validate($rows,$positions,$revision);
    if ($ordered===array_map(static fn(array $row): string=>(string)$row['id'],$rows)) return;
    // Signed temporary positions avoid collisions with UNIQUE(listing_id,position).
    $pdo->prepare('UPDATE market_images SET position=-position-1 WHERE listing_id=?')->execute([$listingId]);
    $update=$pdo->prepare('UPDATE market_images SET position=? WHERE listing_id=? AND id=?');
    foreach ($ordered as $position=>$id) $update->execute([$position,$listingId,$id]);
}
