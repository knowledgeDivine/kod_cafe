<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
$token = preg_replace('/[^a-f0-9]/', '', strtolower((string)($_GET['ref'] ?? '')));
$order = null; $items = [];
if (strlen($token) === 64) {
    try {
        $stmt = db()->prepare('SELECT * FROM orders WHERE public_token = :token LIMIT 1');
        $stmt->execute(['token' => $token]);
        $order = $stmt->fetch() ?: null;
        if ($order) {
            $itemStmt = db()->prepare('SELECT item_name, unit_price, quantity, line_total FROM order_items WHERE order_id = :id ORDER BY id');
            $itemStmt->execute(['id' => $order['id']]); $items = $itemStmt->fetchAll();
        }
    } catch (Throwable $exception) { error_log('Order confirmation lookup failed: ' . $exception->getMessage()); }
}
$pageTitle = 'Order received — K.O.D Café';
require __DIR__ . '/includes/header.php';
?>
<section class="order-page section-pad confirmation-page">
<?php if ($order): ?>
    <div class="confirmation-mark">✓</div><span class="eyebrow">THANK YOU FOR CHOOSING K.O.D</span><h1 class="section-title">Your order is<br><em>in good hands.</em></h1><p class="confirmation-lead">We've received your order. Keep this reference handy.</p>
    <div class="confirmation-card"><div class="confirmation-ref"><span>ORDER REFERENCE</span><strong><?= e($order['order_number']) ?></strong></div><div class="confirmation-items"><?php foreach ($items as $item): ?><div class="summary-row"><span><?= (int)$item['quantity'] ?> × <?= e($item['item_name']) ?></span><strong><?= e(money($item['line_total'])) ?></strong></div><?php endforeach; ?></div><div class="summary-row"><span><?= $order['fulfillment'] === 'delivery' ? 'Delivery' : 'Collection' ?></span><strong><?= $order['fulfillment'] === 'delivery' ? 'Delivery requested' : 'Pick up at K.O.D' ?></strong></div><div class="summary-row"><span>Payment</span><strong>Pay on <?= $order['fulfillment'] === 'delivery' ? 'delivery' : 'pickup' ?></strong></div><div class="summary-row"><span>Subtotal</span><strong><?= e(money($order['subtotal'])) ?></strong></div><?php if ((float)$order['discount_amount'] > 0): ?><div class="summary-row"><span>K.O.D rewards discount</span><strong>−<?= e(money($order['discount_amount'])) ?></strong></div><?php endif; ?><div class="summary-row summary-total"><span>Total due</span><strong><?= e(money($order['total'])) ?></strong></div><?php if ((int)$order['loyalty_points_redeemed'] > 0): ?><p class="confirmation-lead">You used <?= number_format((int)$order['loyalty_points_redeemed']) ?> K.O.D points on this order.</p><?php endif; ?></div>
    <p class="confirmation-lead">Your order status: <strong><?= e(ucfirst($order['status'])) ?></strong>. Payment has not been collected online.</p>
    <a class="button button-dark" href="<?= e(base_url('order-tracking.php?ref=' . $order['public_token'])) ?>">Track your order <span>↗</span></a>
    <p class="confirmation-lead"><a class="text-link" href="<?= e(base_url('order-history.php')) ?>">View my recent orders</a></p>
<?php else: ?>
    <span class="eyebrow">K.O.D ORDER DESK</span><h1 class="section-title">We couldn't find<br><em>that order.</em></h1><p class="confirmation-lead">Check your confirmation link or return to the menu to start a new order.</p>
<?php endif; ?>
    <a class="button button-dark" href="<?= e(base_url()) ?>">Back to K.O.D <span>↗</span></a>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
