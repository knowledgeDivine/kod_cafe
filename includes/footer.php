</main>
<footer class="site-footer" id="footer">
    <div class="footer-main">
        <div class="footer-brand-block">
            <a class="brand footer-brand" href="<?= e(base_url()) ?>"><span class="brand-mark">K<span>.</span>O<span>.</span>D</span><span class="brand-sub">COFFEE / DESSERT / CULTURE</span></a>
            <p>Not just a coffee break.<br>A moment worth slowing down for.</p>
        </div>
        <div class="footer-links"><span class="eyebrow">EXPLORE</span><a href="<?= e(base_url('#menu')) ?>">Our menu</a><a href="<?= e(base_url('showroom.php')) ?>">3D showroom</a><a href="<?= e(base_url('ai-barista.php')) ?>">Meet the AI barista</a><a href="<?= e(base_url('#coffee-studio')) ?>">Coffee studio</a><a href="<?= e(base_url('#story')) ?>">Our story</a><a href="<?= e(base_url('order-history.php')) ?>">Track my orders</a><a href="<?= e(base_url('favorites.php')) ?>">Saved favourites</a><a href="<?= e(base_url('account.php#taste-profile')) ?>">Your taste profile</a><a href="<?= e(base_url('register.php')) ?>">Join the K.O.D Club</a></div>
        <div class="footer-links"><span class="eyebrow">SAY HELLO</span><a href="mailto:hello@kodcafe.com">hello@kodcafe.com</a><a href="<?= e(base_url('#contact')) ?>">Business enquiries</a><a href="<?= e(base_url('admin/login.php')) ?>">Staff access</a></div>
        <div class="footer-newsletter"><span class="eyebrow">A GOOD THING IN YOUR INBOX</span><p>New pours, sweet finds and little café notes.</p>
            <form id="newsletter-form" class="newsletter-form"><label class="sr-only" for="newsletter-email">Email address</label><input id="newsletter-email" type="email" name="email" placeholder="Your email address" required><button type="submit" aria-label="Subscribe">↗</button></form><p class="form-note" id="newsletter-feedback" aria-live="polite"></p>
        </div>
    </div>
    <div class="footer-bottom"><span>© <?= date('Y') ?> K.O.D Café. Crafted with care.</span><span>GOOD COFFEE. GOOD COMPANY. GOOD ENERGY.</span></div>
</footer>
<div class="scroll-splash" id="scroll-splash" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>
<script src="<?= e(base_url('assets/js/main.js')) ?>" defer></script>
<script src="<?= e(base_url('assets/js/order.js')) ?>" defer></script>
<script src="<?= e(base_url('assets/js/order-tracking.js')) ?>" defer></script>
<script src="<?= e(base_url('assets/js/personalization.js')) ?>" defer></script>
<script type="module" src="<?= e(base_url('assets/js/scene.js')) ?>"></script>
<?php foreach (($extraScripts ?? []) as $extraScript): ?><script type="module" src="<?= e(base_url((string) $extraScript)) ?>"></script><?php endforeach; ?>
</body>
</html>
