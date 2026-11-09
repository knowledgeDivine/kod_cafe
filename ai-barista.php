<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Meet the K.O.D AI Barista — Find your perfect pour';
$pageDescription = 'Tell the K.O.D AI Barista what you love and get coffee and dessert recommendations from the current café menu.';
$activePage = 'barista';
$extraStyles = ['assets/css/barista.css'];
$extraScripts = ['assets/js/barista.js'];
$menuItems = get_menu_items(null, 50);
$baristaProducts = array_map(static function (array $item): array {
    return [
        'id' => (int) $item['id'],
        'name' => (string) $item['name'],
        'category' => (string) $item['category'],
        'description' => (string) $item['description'],
        'price' => (float) $item['price'],
        'image_url' => (string) ($item['image_url'] ?: placeholder_image((string) $item['category'])),
    ];
}, $menuItems);
require __DIR__ . '/includes/header.php';
?>
<section class="barista-hero">
    <div class="barista-hero-orb orb-one"></div><div class="barista-hero-orb orb-two"></div>
    <div class="barista-hero-copy">
        <div class="eyebrow eyebrow-light"><span class="eyebrow-line"></span> YOUR PERSONAL K.O.D GUIDE</div>
        <p class="barista-overline">A LITTLE KNOW-HOW. A LOT OF GOOD TASTE.</p>
        <h1>Meet your<br><em>AI barista.</em></h1>
        <p class="barista-hero-lead">Tell us what you are in the mood for. Rich or mellow, iced or silky, coffee or something sweet — let's find your kind of good.</p>
        <a class="button button-cream" href="#barista-studio">Find my pour <span>↘</span></a>
    </div>
    <div class="barista-hero-art" aria-hidden="true">
        <div class="barista-art-ring ring-a"></div><div class="barista-art-ring ring-b"></div>
        <div class="barista-cup-shadow"></div>
        <div class="barista-cup"><div class="barista-cup-rim"></div><div class="barista-coffee"><span></span></div><div class="barista-cup-handle"></div><div class="barista-cup-mark">K<span>.</span>O<span>.</span>D</div></div>
        <div class="barista-bean bean-a"></div><div class="barista-bean bean-b"></div><div class="barista-bean bean-c"></div>
        <span class="barista-art-note note-a">MADE AROUND<br>YOUR TASTE</span><span class="barista-art-note note-b">GOOD THINGS<br>START HERE</span>
    </div>
    <div class="barista-hero-foot"><span>01 — THE K.O.D AI EXPERIENCE</span><span>ASK / EXPLORE / DISCOVER</span></div>
</section>

<section class="barista-workspace section-pad" id="barista-studio">
    <div class="section-kicker"><span>01</span><span class="kicker-line"></span><span>LET'S FIND YOUR FAVOURITE</span></div>
    <div class="barista-heading"><div><h2 class="section-title">Your taste.<br><em>Your kind of coffee.</em></h2></div><p>Start with a few little preferences or simply talk to the barista. Recommendations are drawn from the live K.O.D menu.</p></div>

    <div class="barista-layout">
        <aside class="barista-preferences">
            <div class="barista-panel-heading"><span class="eyebrow"><span class="eyebrow-line"></span> MAKE IT YOURS</span><span class="preference-index">01 / 03</span></div>
            <h3>A few things<br><em>we should know.</em></h3>
            <div class="preference-group">
                <label for="barista-taste">WHAT ARE YOU IN THE MOOD FOR?</label>
                <select id="barista-taste"><option value="surprise me">Surprise me</option><option value="smooth and creamy">Smooth & creamy</option><option value="bold and intense">Bold & intense</option><option value="sweet and indulgent">Sweet & indulgent</option><option value="light and refreshing">Light & refreshing</option><option value="earthy and gentle">Earthy & gentle</option></select>
            </div>
            <div class="preference-group">
                <label for="barista-strength">COFFEE STRENGTH</label>
                <div class="barista-segment" role="group" aria-label="Coffee strength"><button type="button" class="is-active" data-strength="any" aria-pressed="true">Any</button><button type="button" data-strength="mild" aria-pressed="false">Mild</button><button type="button" data-strength="medium" aria-pressed="false">Medium</button><button type="button" data-strength="strong" aria-pressed="false">Strong</button></div>
                <input type="hidden" id="barista-strength" value="any">
            </div>
            <div class="preference-group">
                <label for="barista-budget">YOUR BUDGET</label>
                <div class="budget-field"><span>₦</span><input id="barista-budget" type="number" min="0" max="1000000" step="500" placeholder="No limit"></div>
            </div>
            <div class="preference-group">
                <label>THE COLOUR YOU'RE DRAWN TO</label>
                <div class="barista-shades" role="group" aria-label="Coffee colour preference"><button type="button" class="shade-dot selected" data-barista-shade="caramel" aria-label="Caramel" aria-pressed="true"></button><button type="button" class="shade-dot" data-barista-shade="mocha" aria-label="Mocha" aria-pressed="false"></button><button type="button" class="shade-dot" data-barista-shade="latte" aria-label="Latte" aria-pressed="false"></button><button type="button" class="shade-dot" data-barista-shade="espresso" aria-label="Espresso" aria-pressed="false"></button><button type="button" class="shade-dot" data-barista-shade="rose" aria-label="Rose" aria-pressed="false"></button><button type="button" class="shade-dot" data-barista-shade="matcha" aria-label="Matcha" aria-pressed="false"></button></div>
                <span class="preference-hint" id="barista-shade-label">Caramel glow</span>
            </div>
            <div class="preference-group preference-check"><label><input type="checkbox" id="barista-dessert-pair"> Include a dessert pairing</label></div>
            <button type="button" class="button button-dark preference-submit" id="barista-find-match">Find my match <span>↗</span></button>
            <p class="preference-disclaimer">Tell our team about allergies or dietary needs before ordering. Menu descriptions may not list every ingredient.</p>
        </aside>

        <section class="barista-chat-panel" aria-label="Chat with the K.O.D AI Barista">
            <div class="chat-topbar"><div class="chat-avatar"><span>K<span>.</span>O<span>.</span>D</span></div><div class="chat-identity"><strong>The K.O.D Barista</strong><span><i></i> HERE TO HELP YOU CHOOSE</span></div><button type="button" id="barista-clear" class="chat-clear" title="Start a new conversation">RESET ↺</button></div>
            <div class="chat-messages" id="barista-messages" aria-live="polite" aria-relevant="additions text">
                <article class="chat-message assistant-message"><div class="chat-message-avatar">K.</div><div class="chat-bubble"><span class="chat-sender">K.O.D BARISTA <time>JUST NOW</time></span><p>Hey there. Welcome to K.O.D. ☕<br><br>Tell me what you usually enjoy — or what kind of day you're having — and I'll help you find something lovely from our menu.</p><div class="chat-suggestions"><button type="button" data-prompt="I want something smooth, creamy and not too sweet.">Smooth & creamy</button><button type="button" data-prompt="I need a strong coffee to wake me up.">A strong pick-me-up</button><button type="button" data-prompt="Suggest a coffee and dessert pairing for me.">Coffee + dessert</button></div></div></article>
            </div>
            <div class="chat-composer-wrap"><div class="chat-composer"><label class="sr-only" for="barista-input">Message the K.O.D Barista</label><textarea id="barista-input" rows="1" maxlength="700" placeholder="Tell me what you're craving…"></textarea><button type="button" id="barista-send" aria-label="Send message"><span>↗</span></button></div><div class="chat-composer-foot"><span>YOUR LITTLE COFFEE CONCIERGE</span><span>ENTER TO SEND · SHIFT + ENTER FOR A NEW LINE</span></div><p id="barista-feedback" class="barista-feedback" role="status"></p></div>
        </section>
    </div>

    <section class="barista-recommendations" id="barista-recommendations" hidden>
        <div class="recommendation-heading"><div><span class="eyebrow"><span class="eyebrow-line"></span> PICKED FOR YOUR PALATE</span><h3>A little something<br><em>just for you.</em></h3></div><span id="barista-recommendation-count">YOUR K.O.D SHORTLIST</span></div>
        <div class="barista-product-grid" id="barista-product-grid"></div>
    </section>
</section>

<section class="barista-note"><div class="barista-note-mark">K.O.D</div><p>Good coffee is personal.<br><em>Finding it should feel easy.</em></p><a href="<?= e(base_url('showroom.php')) ?>">EXPLORE THE 3D SHOWROOM ↗</a></section>
<script type="application/json" id="barista-csrf"><?= json_encode(['csrf_token' => csrf_token()], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
