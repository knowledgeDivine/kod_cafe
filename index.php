<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/customer_auth.php';
require_once __DIR__ . '/includes/personalization.php';
$signedInCustomer = current_customer();
$homeFavoriteIds = $signedInCustomer ? kod_get_customer_favorite_ids((int)$signedInCustomer['id']) : [];
$pageTitle = 'K.O.D Café — A little more than coffee';
$pageDescription = 'A premium coffee and dessert experience. Explore our menu, discover your coffee shade and step into the K.O.D world.';
$activePage = 'home';
$menuItems = get_menu_items(null, 6);
require __DIR__ . '/includes/header.php';
?>
<section class="hero" id="top">
    <div class="hero-copy">
        <div class="eyebrow eyebrow-light"><span class="eyebrow-line"></span> THE ART OF A GOOD PAUSE</div>
        <h1>More than<br><em>a cup.</em><br>A whole mood.</h1>
        <p class="hero-lead">Slow mornings. Bold flavours. Little moments that stay with you. Welcome to coffee, reimagined.</p>
        <div class="hero-actions"><a class="button button-cream" href="#menu">Explore the menu <span>↗</span></a><a class="text-link text-link-light" href="#experience">Discover K.O.D <span>↓</span></a></div><a class="hero-showroom-link" href="<?= e(base_url('showroom.php')) ?>">Step inside the 3D showroom <span>↗</span></a>
        <div class="hero-index"><span>01 / 04</span><span class="index-rule"></span><span>CRAFTED FOR YOUR MOMENT</span></div>
    </div>
    <div class="hero-art" aria-label="Interactive three-dimensional K.O.D coffee cup">
        <div class="hero-orbit orbit-one"></div><div class="hero-orbit orbit-two"></div>
        <div class="hero-art-label label-top"><span>01</span> SIGNATURE BLEND</div>
        <canvas id="coffee-canvas" class="three-canvas" aria-label="A rotating three-dimensional coffee cup. Drag to rotate."></canvas>
        <div class="hero-art-caption"><span class="caption-dot"></span><span>DRAG TO EXPLORE</span><span class="caption-divider"></span><span>FRESHLY POURED</span></div>
        <div class="hero-stamp">K<br><span>O</span><br>D</div>
        <div class="hero-art-label label-bottom">THE PERFECT POUR <span>EST. 2026</span></div>
    </div>
    <a class="scroll-cue" href="#experience"><span>SCROLL TO SIP</span><i></i></a>
</section>

<section class="intro-section section-pad" id="experience">
    <div class="section-kicker"><span>01</span><span class="kicker-line"></span><span>THE K.O.D EXPERIENCE</span></div>
    <div class="intro-grid">
        <div><h2 class="section-title">A little pause.<br><em>A lot of feeling.</em></h2></div>
        <div class="intro-text"><p>We believe the best things are never rushed. From the first crackle of freshly ground beans to the last soft bite of something sweet, every detail is an invitation to slow down and enjoy the now.</p><a class="text-link" href="#story">The thought behind the cup <span>↗</span></a></div>
    </div>
    <div class="experience-strip">
        <div class="experience-card card-coffee"><div class="experience-number">01</div><div class="experience-visual coffee-beans-visual"><span></span><span></span><span></span></div><div><h3>Thoughtful coffee</h3><p>Beautifully balanced, always intentional.</p></div></div>
        <div class="experience-card card-sweet"><div class="experience-number">02</div><div class="experience-visual pastry-visual"><span></span></div><div><h3>Little sweet things</h3><p>Made for the moments in between.</p></div></div>
        <div class="experience-card card-culture"><div class="experience-number">03</div><div class="experience-visual cup-visual"><span></span></div><div><h3>Room to connect</h3><p>Good company makes everything better.</p></div></div>
    </div>
</section>

<section class="feature-section" id="feature">
    <div class="feature-image"><div class="feature-image-overlay"></div><div class="feature-image-note"><span>THE DAILY RITUAL</span><span>— 01 / 03</span></div></div>
    <div class="feature-copy"><div class="eyebrow"><span class="eyebrow-line"></span> THE SIGNATURE POUR</div><h2 class="section-title">Made with<br><em>intention.</em></h2><p>Rich espresso, silky milk and a finish that feels like your favourite song. Meet the little ritual that makes an ordinary day feel considered.</p><div class="feature-specs"><div><strong>01</strong><span>SELECTED BEANS</span></div><div><strong>02</strong><span>CAREFUL EXTRACTION</span></div><div><strong>03</strong><span>YOUR KIND OF FINISH</span></div></div><a class="button button-dark" href="#menu">Find your favourite <span>↗</span></a></div>
</section>

<section class="menu-section section-pad" id="menu">
    <div class="section-kicker"><span>02</span><span class="kicker-line"></span><span>THE GOOD STUFF</span></div>
    <div class="menu-heading"><div><h2 class="section-title">A menu worth<br><em>taking your time.</em></h2></div><p>From bold and beautifully simple to something a little indulgent. There's a new favourite waiting here.</p></div>
    <div class="menu-grid" id="menu-grid">
        <?php if ($menuItems): ?>
            <?php foreach ($menuItems as $index => $item): ?>
                <article class="menu-card" data-category="<?= e($item['category']) ?>"><div class="menu-card-image"><img src="<?= e($item['image_url'] ?: placeholder_image($item['category'])) ?>" alt="<?= e($item['name']) ?>" loading="lazy"><span class="menu-card-count">0<?= $index + 1 ?></span><?php if ((int)$item['is_featured'] === 1): ?><span class="menu-card-tag">K.O.D FAVOURITE</span><?php endif; ?><?php if ($signedInCustomer): ?><button type="button" class="kod-favorite-toggle <?= in_array((int)$item['id'], $homeFavoriteIds, true) ? 'is-favorite' : '' ?>" data-favorite-id="<?= (int)$item['id'] ?>" aria-pressed="<?= in_array((int)$item['id'], $homeFavoriteIds, true) ? 'true' : 'false' ?>" aria-label="Save <?= e($item['name']) ?> to favourites"><?= in_array((int)$item['id'], $homeFavoriteIds, true) ? '♥' : '♡' ?></button><?php else: ?><a class="kod-favorite-login" href="<?= e(base_url('login.php')) ?>" aria-label="Sign in to save <?= e($item['name']) ?>">♡</a><?php endif; ?></div><div class="menu-card-info"><div><h3><?= e($item['name']) ?></h3><p><?= e($item['description']) ?></p></div><span class="menu-price"><?= e(money($item['price'])) ?></span></div><button class="add-to-bag" type="button" data-product-id="<?= (int)$item['id'] ?>" data-product-name="<?= e($item['name']) ?>" data-product-price="<?= e((string)$item['price']) ?>" data-product-image="<?= e($item['image_url'] ?: placeholder_image($item['category'])) ?>">Add to bag <span>+</span></button><a class="menu-3d-link" href="<?= e(base_url('showroom.php?item=' . rawurlencode((string) $item['slug']))) ?>">Explore in 3D <span>↗</span></a></article>
            <?php endforeach; ?>
        <?php else: ?>
            <article class="menu-card"><div class="menu-card-image"><img src="https://images.unsplash.com/photo-1509042239860-f550ce710b93?auto=format&fit=crop&w=900&q=85" alt="Signature espresso" loading="lazy"><span class="menu-card-count">01</span><span class="menu-card-tag">K.O.D FAVOURITE</span></div><div class="menu-card-info"><div><h3>Velvet Latte</h3><p>Double espresso, velvety milk, soft caramel finish.</p></div><span class="menu-price">₦3,500</span></div></article>
            <article class="menu-card"><div class="menu-card-image"><img src="https://images.unsplash.com/photo-1517701604599-bb29b565090c?auto=format&fit=crop&w=900&q=85" alt="Iced coffee" loading="lazy"><span class="menu-card-count">02</span></div><div class="menu-card-info"><div><h3>Brown Sugar Cloud</h3><p>Cold brew, brown sugar, a cloud of cream.</p></div><span class="menu-price">₦4,000</span></div></article>
            <article class="menu-card"><div class="menu-card-image"><img src="https://images.unsplash.com/photo-1578985545062-69928b1d9587?auto=format&fit=crop&w=900&q=85" alt="Chocolate cake" loading="lazy"><span class="menu-card-count">03</span></div><div class="menu-card-info"><div><h3>Midnight Chocolate</h3><p>Deep cocoa layers with a smooth ganache.</p></div><span class="menu-price">₦4,500</span></div></article>
            <article class="menu-card"><div class="menu-card-image"><img src="https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=900&q=85" alt="Fresh pastry" loading="lazy"><span class="menu-card-count">04</span></div><div class="menu-card-info"><div><h3>Butter Croissant</h3><p>Golden, flaky, baked for the slow mornings.</p></div><span class="menu-price">₦2,800</span></div></article>
            <article class="menu-card"><div class="menu-card-image"><img src="https://images.unsplash.com/photo-1544787219-7f47acab7655?auto=format&fit=crop&w=900&q=85" alt="Tea" loading="lazy"><span class="menu-card-count">05</span></div><div class="menu-card-info"><div><h3>Golden Hour Tea</h3><p>A gentle botanical blend with citrus notes.</p></div><span class="menu-price">₦2,500</span></div></article>
            <article class="menu-card"><div class="menu-card-image"><img src="https://images.unsplash.com/photo-1486427944299-d1955d23e34d?auto=format&fit=crop&w=900&q=85" alt="Cupcake dessert" loading="lazy"><span class="menu-card-count">06</span></div><div class="menu-card-info"><div><h3>Little Cloud Cake</h3><p>Soft vanilla sponge, whipped cream, berries.</p></div><span class="menu-price">₦3,800</span></div></article>
        <?php endif; ?>
    </div>
    <div class="menu-footer"><span>GOOD THINGS ARE BEST SHARED.</span><a href="#contact" class="text-link">Ask about our menu <span>↗</span></a></div>
</section>

<section class="dessert-section" id="dessert-showcase">
    <div class="dessert-copy"><div class="eyebrow eyebrow-light"><span class="eyebrow-line"></span> THE SWEET SIDE</div><h2 class="section-title">A little<br><em>extra.</em></h2><p>Because coffee deserves a good companion. Explore the sweet side of K.O.D — one bite, one layer, one little moment at a time.</p><div class="dessert-controls"><button class="dessert-control active" data-dessert="cake">01 / CHOCOLATE CAKE</button><button class="dessert-control" data-dessert="donut">02 / GLAZED DONUT</button><button class="dessert-control" data-dessert="croissant">03 / BUTTER CROISSANT</button></div></div>
    <div class="dessert-stage"><div class="dessert-stage-ring"></div><canvas id="dessert-canvas" class="three-canvas" aria-label="Interactive three-dimensional dessert. Drag to rotate."></canvas><span class="dessert-stage-caption">A SWEET LITTLE INTERLUDE <span>↗</span></span></div>
</section>

<section class="coffee-studio section-pad" id="coffee-studio">
    <div class="section-kicker"><span>03</span><span class="kicker-line"></span><span>THE COFFEE COLOUR STUDIO</span></div>
    <div class="studio-grid"><div class="studio-intro"><div class="eyebrow"><span class="eyebrow-line"></span> YOUR CUP, YOUR SHADE</div><h2 class="section-title">Pick a colour.<br><em>Find your coffee.</em></h2><p>Every coffee has its own character. Choose the shade you’re drawn to and we’ll match it with a drink that feels just right.</p><div class="studio-note"><span class="note-star">✳</span><span>There is no wrong way to take your coffee. Only your way.</span></div></div>
        <div class="shade-studio"><div class="shade-head"><span>01 — CHOOSE YOUR SHADE</span><span id="shade-label">CARAMEL GLOW</span></div><div class="shade-options" role="group" aria-label="Choose your coffee colour"><button class="shade-option selected" data-shade="caramel" data-name="Caramel Glow" style="--swatch:#b97843" aria-label="Caramel Glow" aria-pressed="true"></button><button class="shade-option" data-shade="mocha" data-name="Deep Mocha" style="--swatch:#573729" aria-label="Deep Mocha" aria-pressed="false"></button><button class="shade-option" data-shade="latte" data-name="Oat Silk" style="--swatch:#d7b991" aria-label="Oat Silk" aria-pressed="false"></button><button class="shade-option" data-shade="espresso" data-name="Midnight Espresso" style="--swatch:#2b1a13" aria-label="Midnight Espresso" aria-pressed="false"></button><button class="shade-option" data-shade="rose" data-name="Rose Cloud" style="--swatch:#c98d86" aria-label="Rose Cloud" aria-pressed="false"></button><button class="shade-option" data-shade="matcha" data-name="Soft Matcha" style="--swatch:#9b9b70" aria-label="Soft Matcha" aria-pressed="false"></button></div>
            <div class="coffee-preview" id="coffee-preview" style="--coffee-shade:#b97843"><div class="preview-cup"><div class="preview-handle"></div><div class="preview-liquid"></div></div><div class="preview-shadow"></div><span class="preview-label">YOUR SHADE, YOUR SIGNATURE</span></div>
            <div class="recommendation" aria-live="polite"><div><span class="eyebrow">YOUR MATCH</span><h3 id="recommendation-name">Caramel Cloud Latte</h3><p id="recommendation-description">A smooth espresso with silky milk, warm caramel and a soft, sweet finish.</p></div><div class="recommendation-meta"><span id="recommendation-strength">MEDIUM / SMOOTH</span><strong id="recommendation-price">₦3,800</strong></div></div>
            <button class="button button-dark studio-action" id="save-recommendation" type="button">Save my coffee match <span>↗</span></button><p class="studio-feedback" id="studio-feedback" aria-live="polite"></p>
        </div></div>
</section>

<section class="quote-section"><div class="quote-mark">“</div><blockquote>Some moments are better<br>when you <em>let them brew.</em></blockquote><span>THE K.O.D WAY</span></section>

<section class="story-section section-pad" id="story"><div class="story-visual"><div class="story-photo"></div><div class="story-photo-stamp">K.O.D<br><span>— MADE FOR THE MOMENT —</span></div></div><div class="story-copy"><div class="section-kicker"><span>04</span><span class="kicker-line"></span><span>THE STORY</span></div><h2 class="section-title">Coffee is the<br><em>beginning.</em></h2><p>K.O.D is built around a simple thought: everyday rituals deserve a little wonder. We bring together considered coffee, comforting desserts and a welcoming space for people to pause, meet and make memories.</p><p>Whether you’re starting the day, finding your focus or catching up with someone you love, there’s a seat and a cup waiting for you.</p><a class="text-link" href="#contact">Come find your moment <span>↗</span></a></div></section>

<section class="contact-section section-pad" id="contact"><div class="contact-heading"><div class="eyebrow eyebrow-light"><span class="eyebrow-line"></span> LET'S MAKE A MOMENT</div><h2 class="section-title">Your table<br><em>is waiting.</em></h2><p>Have a question, planning a gathering or looking to work with us? Leave us a note.</p><div class="contact-details"><a href="mailto:hello@kodcafe.com">HELLO@KODCAFE.COM</a><span>BUSINESS & COLLABORATIONS WELCOME</span></div></div>
    <form class="contact-form" id="contact-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="form-row"><label>Your name<input name="name" type="text" placeholder="How should we call you?" required maxlength="100"></label><label>Email address<input name="email" type="email" placeholder="you@example.com" required maxlength="190"></label></div><div class="form-row"><label>Phone (optional)<input name="phone" type="tel" placeholder="+234 ..." maxlength="30"></label><label>What is this about?<select name="subject"><option value="General enquiry">A little question</option><option value="Business enquiry">Business enquiry</option><option value="Event or booking">Event or gathering</option><option value="Collaboration">Collaboration</option></select></label></div><label>Your message<textarea name="message" rows="4" placeholder="Tell us what's on your mind..." required maxlength="3000"></textarea></label><div class="contact-submit"><span id="contact-feedback" aria-live="polite"></span><button class="button button-cream" type="submit">Send your note <span>↗</span></button></div></form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
