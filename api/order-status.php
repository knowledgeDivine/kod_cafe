<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$token = strtolower((string)($_GET['ref'] ?? ''));
if (strlen($token) !== 64 || !ctype_xdigit($token)) {
    json_response(['success' => false, 'message' => 'This tracking link is not valid.'], 400);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT order_number, fulfillment, total, status, payment_status, estimated_ready_at, created_at, updated_at FROM orders WHERE public_token = :token LIMIT 1');
    $stmt->execute(['token' => $token]);
    $order = $stmt->fetch();
    if (!$order) json_response(['success' => false, 'message' => 'We could not find this order. Check your tracking link.'], 404);

    $itemsStmt = $pdo->prepare('SELECT item_name, unit_price, quantity, line_total FROM order_items WHERE order_id = (SELECT id FROM orders WHERE public_token = :token LIMIT 1) ORDER BY id');
    $itemsStmt->execute(['token' => $token]);
    $historyStmt = $pdo->prepare('SELECT from_status, to_status, note, created_at FROM order_status_history WHERE order_id = (SELECT id FROM orders WHERE public_token = :token LIMIT 1) ORDER BY created_at ASC, id ASC');
    $historyStmt->execute(['token' => $token]);

    json_response([
        'success' => true,
        'order' => $order,
        'items' => $itemsStmt->fetchAll(),
        'history' => $historyStmt->fetchAll(),
        'checked_at' => date('c'),
    ]);
} catch (Throwable $exception) {
    error_log('Order tracking lookup failed: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'Order tracking is temporarily unavailable. Please try again shortly.'], 500);
}
