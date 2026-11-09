<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/database.php';

function get_menu_items(?string $category = null, int $limit = 12): array
{
    try {
        $sql = 'SELECT id, name, slug, category, description, price, image_url, is_featured FROM menu_items WHERE is_available = 1';
        $params = [];
        if ($category !== null && $category !== '') {
            $sql .= ' AND category = :category';
            $params['category'] = $category;
        }
        $sql .= ' ORDER BY is_featured DESC, sort_order ASC, id DESC LIMIT ' . max(1, min(50, $limit));
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $exception) {
        error_log('Menu query failed: ' . $exception->getMessage());
        return [];
    }
}


function placeholder_image(string $category): string
{
    $images = [
        'coffee' => 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?auto=format&fit=crop&w=900&q=85',
        'dessert' => 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?auto=format&fit=crop&w=900&q=85',
        'pastry' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=900&q=85',
        'tea' => 'https://images.unsplash.com/photo-1544787219-7f47acab7655?auto=format&fit=crop&w=900&q=85',
    ];
    return $images[$category] ?? $images['coffee'];
}
