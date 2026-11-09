<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
$token = strtolower((string)($_GET['ref'] ?? ''));
$validToken = strlen($token) === 64 && ctype_xdigit($token);
$pageTitle = 'Track your order — K.O.D Café';
$pageDescription = 'Follow your K.O.D café order from confirmation to collection or delivery.';
require __DIR__ . '/includes/header.php';
?>
<section class="order-page section-pad tracking-page">
    <div class="section-kicker"><span>07</span><span class="kicker-line"></span><span>THE K.O.D ORDER DESK</span></div>
    <div class="order-page-heading"><div><h1 class="section-title">Good things<br>are <em>on the way.</em></h1><p>Follow your order as our team prepares it.</p></div><a class="text-link" href="<?= e(base_url('order-history.php')) ?>">← My recent orders</a></div>
    <?php if ($validToken): ?>
        <div id="order-tracker" class="tracker-shell" data-ref="<?= e($token) ?>" data-endpoint="<?= e(base_url('api/order-status.php')) ?>" data-history-url="<?= e(base_url('order-history.php')) ?>" data-menu-url="<?= e(base_url('#menu')) ?>">
            <div class="tracker-loading"><span class="tracker-spinner"></span><span>Finding your order…</span></div>
        </div>
    <?php else: ?>
        <div class="tracker-message tracker-error"><strong>Tracking link needed</strong><p>Open the private tracking link from your K.O.D order confirmation, or choose one of your recent orders.</p><a class="button button-dark" href="<?= e(base_url('order-history.php')) ?>">View my recent orders <span>↗</span></a></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
