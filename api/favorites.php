<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require_once dirname(__DIR__) . '/includes/customer_auth.php';
header('Cache-Control: no-store');

$customer = current_customer();
if (!$customer) json_response(['success' => false, 'message' => 'Sign in to save favourite K.O.D menu items.', 'login_url' => base_url('login.php')], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
$data = request_json();
if (!csrf_check($data['csrf_token'] ?? null)) json_response(['success' => false, 'message' => 'Your session expired. Refresh and try again.'], 419);
$itemId = filter_var($data['menu_item_id'] ?? null, FILTER_VALIDATE_INT);
if (!$itemId || $itemId < 1) json_response(['success' => false, 'message' => 'Choose a valid menu item.'], 422);

try {
    $pdo = db();
    $product = $pdo->prepare('SELECT id FROM menu_items WHERE id = :id AND is_available = 1 LIMIT 1');
    $product->execute(['id' => $itemId]);
    if (!$product->fetchColumn()) json_response(['success' => false, 'message' => 'That menu item is not currently available.'], 404);

    $existing = $pdo->prepare('SELECT id FROM customer_favorites WHERE customer_id = :customer_id AND menu_item_id = :menu_item_id LIMIT 1');
    $existing->execute(['customer_id' => (int)$customer['id'], 'menu_item_id' => $itemId]);
    if ($existing->fetchColumn()) {
        $pdo->prepare('DELETE FROM customer_favorites WHERE customer_id = :customer_id AND menu_item_id = :menu_item_id')->execute(['customer_id' => (int)$customer['id'], 'menu_item_id' => $itemId]);
        $isFavorite = false;
    } else {
        $pdo->prepare('INSERT IGNORE INTO customer_favorites (customer_id, menu_item_id) VALUES (:customer_id, :menu_item_id)')->execute(['customer_id' => (int)$customer['id'], 'menu_item_id' => $itemId]);
        $isFavorite = true;
    }
    $count = $pdo->prepare('SELECT COUNT(*) FROM customer_favorites WHERE customer_id = :id');
    $count->execute(['id' => (int)$customer['id']]);
    json_response(['success' => true, 'is_favorite' => $isFavorite, 'favorite_count' => (int)$count->fetchColumn(), 'message' => $isFavorite ? 'Saved to your K.O.D favourites.' : 'Removed from your K.O.D favourites.']);
} catch (Throwable $exception) {
    error_log('KOD favourite update failed: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'We could not update your favourites. Check that the Phase 9 database migration has been imported.'], 500);
}
