<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/customer_auth.php';
$historyCustomer = current_customer();
$tokens = $_SESSION['kod_order_tokens'] ?? [];
$tokens = is_array($tokens) ? array_values(array_filter($tokens, static fn($token) => is_string($token) && strlen($token) === 64 && ctype_xdigit($token))) : [];
$orders = [];
try {
    $where = [];
    $params = [];
    if ($historyCustomer) { $where[] = 'customer_id = :customer_id'; $params['customer_id'] = (int)$historyCustomer['id']; }
    if ($tokens) {
        $placeholders = [];
        foreach ($tokens as $index => $token) { $key = 'token_' . $index; $placeholders[] = ':' . $key; $params[$key] = $token; }
        $where[] = 'public_token IN (' . implode(',', $placeholders) . ')';
    }
    if ($where) {
        $stmt = db()->prepare('SELECT order_number, public_token, fulfillment, subtotal, discount_amount, total, status, payment_status, created_at, estimated_ready_at FROM orders WHERE (' . implode(' OR ', $where) . ') ORDER BY created_at DESC LIMIT 20');
        $stmt->execute($params); $orders = $stmt->fetchAll();
    }
} catch (Throwable $exception) {
    error_log('Customer order history lookup failed: ' . $exception->getMessage());
}
$pageTitle = 'My recent orders — K.O.D Café';
$pageDescription = 'Return to your recent K.O.D café orders and track their progress.';
require __DIR__ . '/includes/header.php';
?>
<section class="order-page section-pad history-page">
    <div class="section-kicker"><span>08</span><span class="kicker-line"></span><span>YOUR K.O.D MOMENTS</span></div>
    <div class="order-page-heading"><div><h1 class="section-title">Your recent<br><em>orders.</em></h1><p><?= $historyCustomer ? 'Your K.O.D account keeps your order history together.' : 'Orders placed during this browser session are kept here for easy tracking. Create an account to keep future orders together.' ?></p></div><a class="text-link" href="<?= e(base_url('#menu')) ?>">← Back to the menu</a></div>
    <?php if (!$orders): ?>
        <div class="history-empty"><div class="confirmation-mark">☕</div><h2>No recent orders here yet.</h2><p>When you place an order from this browser, its private tracking link will appear on this page.</p><a class="button button-dark" href="<?= e(base_url('#menu')) ?>">Explore the menu <span>↗</span></a></div>
    <?php else: ?>
        <div class="history-list">
            <?php foreach ($orders as $order): ?>
                <article class="history-card">
                    <div class="history-card-top"><div><span class="eyebrow">ORDER REFERENCE</span><strong><?= e($order['order_number']) ?></strong><small><?= e(date('d M Y · H:i', strtotime($order['created_at']))) ?></small></div><span class="status-pill <?= e($order['status']) ?>"><?= e(str_replace('_', ' ', ucfirst($order['status']))) ?></span></div>
                    <div class="history-card-bottom"><span><?= e(ucfirst($order['fulfillment'])) ?> · <?= e(money($order['total'])) ?><?php if ((float)$order['discount_amount'] > 0): ?> · <?= e(money($order['discount_amount'])) ?> rewards saved<?php endif; ?></span><a class="text-link" href="<?= e(base_url('order-tracking.php?ref=' . $order['public_token'])) ?>">Track this order <span>↗</span></a></div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
