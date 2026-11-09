<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Your bag — K.O.D Café';
$pageDescription = 'Review your K.O.D coffee and dessert selection.';
require __DIR__ . '/includes/header.php';
?>
<section class="order-page section-pad">
    <div class="section-kicker"><span>05</span><span class="kicker-line"></span><span>YOUR K.O.D SELECTION</span></div>
    <div class="order-page-heading"><div><h1 class="section-title">Good choice.<br><em>Let's make it yours.</em></h1><p>Everything in your bag, ready when you are.</p></div><a class="text-link" href="<?= e(base_url('#menu')) ?>">← Back to menu</a></div>
    <div id="cart-page-content" class="cart-layout">
        <div id="cart-lines" class="cart-lines"><div class="cart-loading">Your bag is being prepared…</div></div>
        <aside class="cart-summary"><span class="eyebrow">THE LITTLE DETAILS</span><h2>Order summary</h2><div class="summary-row"><span>Items</span><strong id="cart-item-count">0</strong></div><div class="summary-row summary-total"><span>Subtotal</span><strong id="cart-subtotal">₦0</strong></div><p class="summary-note">Delivery or pickup details are collected at checkout. Payment is made at the café or on delivery.</p><a class="button button-dark cart-checkout" id="cart-checkout" href="<?= e(base_url('checkout.php')) ?>">Continue to checkout <span>↗</span></a><a class="cart-continue" href="<?= e(base_url('#menu')) ?>">Continue exploring the menu</a></aside>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
