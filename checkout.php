<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/customer_auth.php';
$checkoutCustomer = current_customer();
$pageTitle = 'Checkout — K.O.D Café';
$pageDescription = 'Complete your K.O.D café order.';
require __DIR__ . '/includes/header.php';
?>
<section class="order-page section-pad">
    <div class="section-kicker"><span>06</span><span class="kicker-line"></span><span>THE FINAL POUR</span></div>
    <div class="order-page-heading"><div><h1 class="section-title">Almost<br><em>yours.</em></h1><p>Tell us how to get your order ready.</p></div><a class="text-link" href="<?= e(base_url('cart.php')) ?>">← Back to your bag</a></div>
    <div class="checkout-layout">
        <form id="checkout-form" class="checkout-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="checkout-block"><span class="eyebrow">01 — YOUR DETAILS</span><div class="checkout-fields"><label>Full name<input type="text" name="customer_name" autocomplete="name" maxlength="120" required placeholder="Your name" value="<?= e($checkoutCustomer['full_name'] ?? '') ?>"></label><label>Email address<input type="email" name="customer_email" autocomplete="email" maxlength="190" required placeholder="you@example.com" value="<?= e($checkoutCustomer['email'] ?? '') ?>"></label><label>Phone number<input type="tel" name="customer_phone" autocomplete="tel" maxlength="40" required placeholder="+234 ..." value="<?= e($checkoutCustomer['phone'] ?? '') ?>"></label></div></div>
            <div class="checkout-block"><span class="eyebrow">02 — HOW WOULD YOU LIKE IT?</span><div class="fulfillment-options"><label class="fulfillment-option"><input type="radio" name="fulfillment" value="pickup" checked><span><strong>Pick up at K.O.D</strong><small>Collect your order at the café.</small></span></label><label class="fulfillment-option"><input type="radio" name="fulfillment" value="delivery"><span><strong>Deliver to me</strong><small>We'll use the address you provide.</small></span></label></div><label id="delivery-address-wrap" class="delivery-address" hidden>Delivery address<textarea name="delivery_address" rows="3" maxlength="500" placeholder="Street, area, city and any helpful directions"></textarea></label></div>
            <div class="checkout-block"><span class="eyebrow">03 — K.O.D REWARDS</span>
                <?php if ($checkoutCustomer): ?>
                    <div class="checkout-rewards" data-points-available="<?= (int)$checkoutCustomer['loyalty_points'] ?>"><div><strong><?= number_format((int)$checkoutCustomer['loyalty_points']) ?> points available</strong><small>100 points = ₦500 off your order.</small></div><label class="reward-toggle"><input type="checkbox" id="redeem-points" name="redeem_points" value="1"><span>Use my rewards</span></label></div>
                <?php else: ?>
                    <div class="checkout-rewards guest-rewards"><div><strong>Collect points with a K.O.D account.</strong><small>Join the K.O.D Club for 25 welcome points, then earn 1 point for every ₦500 spent on completed orders.</small></div><a href="<?= e(base_url('login.php')) ?>">Sign in</a><a href="<?= e(base_url('register.php')) ?>">Join free</a></div>
                <?php endif; ?>
            </div>
            <div class="checkout-block"><span class="eyebrow">04 — PAYMENT</span><div class="payment-note"><strong>Pay when your order arrives or is collected.</strong><p>This local demo accepts payment at pickup or delivery. No online card payment is collected.</p></div></div>
            <div class="checkout-block"><span class="eyebrow">05 — ANYTHING ELSE?</span><label>Order note (optional)<textarea name="customer_note" rows="3" maxlength="1000" placeholder="Milk preference, allergy note or delivery instructions"></textarea></label></div>
            <p id="checkout-feedback" class="checkout-feedback" role="status" aria-live="polite"></p>
            <button class="button button-dark checkout-submit" type="submit">Place my order <span>↗</span></button>
        </form>
        <aside class="cart-summary checkout-summary"><span class="eyebrow">MADE FRESH FOR YOU</span><h2>Your order</h2><div id="checkout-lines" class="checkout-lines"><p>Loading your selection…</p></div><div class="summary-row"><span>Subtotal</span><strong id="checkout-subtotal">₦0</strong></div><div class="summary-row checkout-discount-row" id="checkout-discount-row" hidden><span>K.O.D rewards discount</span><strong id="checkout-discount">−₦0</strong></div><div class="summary-row summary-total"><span>Total</span><strong id="checkout-total">₦0</strong></div><p class="summary-note">Your order is confirmed after you submit. K.O.D will contact you if any detail needs clarification.</p></aside>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
