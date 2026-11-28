<?php
declare(strict_types=1);

function ewb_category_required(PDO $pdo, int $categoryId): bool
{
    $query = $pdo->prepare(
        'SELECT parent_id, ewb_required FROM market_categories WHERE id = ?'
    );
    $visited = [];

    while ($categoryId > 0) {
        if (isset($visited[$categoryId])) {
            throw new RuntimeException('Invalid category hierarchy.');
        }
        $visited[$categoryId] = true;

        $query->execute([$categoryId]);
        $category = $query->fetch(PDO::FETCH_ASSOC);

        if (!$category) {
            throw new RuntimeException('Category not found.');
        }

        if ((int)$category['ewb_required'] === 1) {
            return true;
        }

        $categoryId = (int)($category['parent_id'] ?? 0);
    }

    return false;
}
