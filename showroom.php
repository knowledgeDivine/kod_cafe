<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/customer_auth.php';
require_once __DIR__ . '/includes/personalization.php';
$signedInCustomer = current_customer();
$showroomFavoriteIds = $signedInCustomer ? kod_get_customer_favorite_ids((int) $signedInCustomer['id']) : [];
$showroomFavoriteIds = is_array($showroomFavoriteIds) ? array_map('intval', $showroomFavoriteIds) : [];

$pageTitle = 'The K.O.D 3D Showroom — Explore every detail';
$pageDescription = 'Rotate, zoom and explore K.O.D coffee, tea, desserts and pastries in an interactive 3D product showroom.';
$activePage = 'showroom';
$extraStyles = ['assets/css/showroom.css'];
$extraScripts = ['assets/js/showroom.js'];
$menuItems = get_menu_items(null, 50);
$products = array_map(static function (array $item) use ($showroomFavoriteIds): array {
    return [
        'id' => (int) $item['id'],
        'name' => (string) $item['name'],
        'slug' => (string) $item['slug'],
        'category' => (string) $item['category'],
        'description' => (string) $item['description'],
        'price' => (float) $item['price'],
        'image_url' => (string) ($item['image_url'] ?: placeholder_image((string) $item['category'])),
        'is_featured' => (int) ($item['is_featured'] ?? 0),
        'is_favorite' => in_array((int)$item['id'], $showroomFavoriteIds, true),
    ];
}, $menuItems);
$requestedSlug = trim((string) ($_GET['item'] ?? ''));
$selectedIndex = 0;
foreach ($products as $index => $product) {
    if ($requestedSlug !== '' && hash_equals($product['slug'], $requestedSlug)) {
        $selectedIndex = $index;
        break;
    }
}
$categories = ['all' => 'All pieces', 'coffee' => 'Coffee', 'tea' => 'Tea', 'dessert' => 'Desserts', 'pastry' => 'Pastries'];
require __DIR__ . '/includes/header.php';
?>
<section class="showroom-hero">
    <div class="showroom-hero-orbit orbit-a"></div><div class="showroom-hero-orbit orbit-b"></div>
    <div class="showroom-hero-copy">
        <div class="eyebrow eyebrow-light"><span class="eyebrow-line"></span> THE K.O.D OBJECT LIBRARY</div>
        <p class="showroom-overline">A CLOSER LOOK AT THE GOOD STUFF</p>
        <h1>Made to be<br><em>looked at.</em></h1>
        <p class="showroom-hero-lead">Explore every curve, layer and little detail. Pick a piece, turn it around and find your next favourite.</p>
        <a class="showroom-scroll-link" href="#showroom-studio">ENTER THE SHOWROOM <span>↓</span></a>
    </div>
    <div class="showroom-hero-side"><span>01 — 04</span><span class="showroom-hero-rule"></span><span>COFFEE / DESSERT / CULTURE</span></div>
</section>

<section class="showroom-page section-pad" id="showroom-studio" data-showroom-initial-index="<?= (int) $selectedIndex ?>">
    <div class="section-kicker"><span>01</span><span class="kicker-line"></span><span>THE INTERACTIVE SHOWROOM</span></div>
    <div class="showroom-heading"><div><h2 class="section-title">Take your time.<br><em>Look closer.</em></h2></div><p>Drag to rotate, scroll or use the controls to zoom. Every piece is modelled in 3D for this little K.O.D corner.</p></div>

    <div class="showroom-filters" role="group" aria-label="Filter showroom products">
        <?php foreach ($categories as $key => $label): ?>
            <button type="button" class="showroom-filter <?= $key === 'all' ? 'active' : '' ?>" data-showroom-filter="<?= e($key) ?>" aria-pressed="<?= $key === 'all' ? 'true' : 'false' ?>"><?= e($label) ?></button>
        <?php endforeach; ?>
        <span class="showroom-count"><?= count($products) ?> PIECES TO EXPLORE</span>
    </div>

    <?php if ($products): ?>
        <div class="showroom-layout">
            <div class="showroom-viewer-wrap">
                <div class="showroom-viewer-top"><span><i class="viewer-live-dot"></i> LIVE 3D VIEW</span><span id="showroom-view-label">01 / <?= str_pad((string) count($products), 2, '0', STR_PAD_LEFT) ?></span></div>
                <div class="showroom-viewer" id="showroom-viewer">
                    <div class="viewer-glow"></div><div class="viewer-floor"></div>
                    <canvas id="showroom-canvas" aria-label="Interactive 3D product. Drag to rotate and scroll to zoom."></canvas>
                    <div class="viewer-fallback" id="viewer-fallback" hidden><img id="viewer-fallback-image" src="" alt=""><span>3D preview is unavailable in this browser. Enjoy the product photograph instead.</span></div>
                    <div class="viewer-watermark">K<span>.</span>O<span>.</span>D</div>
                    <div class="viewer-corner-note">ROTATE <span>↗</span> DISCOVER</div>
                </div>
                <div class="showroom-viewer-controls">
                    <div class="viewer-drag-hint"><span class="drag-icon">↔</span><span>DRAG TO ROTATE · SCROLL TO ZOOM</span></div>
                    <div class="viewer-buttons">
                        <button type="button" id="showroom-zoom-out" aria-label="Zoom out" title="Zoom out">−</button>
                        <button type="button" id="showroom-reset" aria-label="Reset view" title="Reset view">↺</button>
                        <button type="button" id="showroom-zoom-in" aria-label="Zoom in" title="Zoom in">+</button>
                        <button type="button" id="showroom-auto-rotate" class="is-on" aria-pressed="true" title="Toggle automatic rotation">AUTO <span>ON</span></button>
                    </div>
                </div>
            </div>

            <aside class="showroom-product-panel" aria-live="polite">
                <div class="showroom-product-index"><span>THE PIECE</span><span id="showroom-product-index">01</span></div>
                <div class="showroom-product-category" id="showroom-product-category"><?= e(strtoupper($products[$selectedIndex]['category'])) ?></div>
                <h3 id="showroom-product-name"><?= e($products[$selectedIndex]['name']) ?></h3>
                <p class="showroom-product-description" id="showroom-product-description"><?= e($products[$selectedIndex]['description']) ?></p>
                <div class="showroom-product-meta"><div><span>MADE FOR</span><strong id="showroom-product-moment">YOUR LITTLE PAUSE</strong></div><div><span>THE K.O.D PRICE</span><strong id="showroom-product-price"><?= e(money($products[$selectedIndex]['price'])) ?></strong></div></div>
                <button type="button" class="button button-dark showroom-add add-to-bag" id="showroom-add" data-product-id="<?= (int) $products[$selectedIndex]['id'] ?>" data-product-name="<?= e($products[$selectedIndex]['name']) ?>" data-product-price="<?= e((string) $products[$selectedIndex]['price']) ?>" data-product-image="<?= e($products[$selectedIndex]['image_url']) ?>">Add to your bag <span>↗</span></button>
                <?php if ($signedInCustomer): ?><button type="button" class="kod-favorite-toggle showroom-favorite-toggle <?= $products[$selectedIndex]['is_favorite'] ? 'is-favorite' : '' ?>" id="showroom-favorite" data-favorite-id="<?= (int)$products[$selectedIndex]['id'] ?>" aria-pressed="<?= $products[$selectedIndex]['is_favorite'] ? 'true' : 'false' ?>"><?= $products[$selectedIndex]['is_favorite'] ? '♥ Saved to favourites' : '♡ Save to favourites' ?></button><?php else: ?><a class="showroom-favorite-login" href="<?= e(base_url('login.php')) ?>">Sign in to save this piece ↗</a><?php endif; ?>
                <p class="showroom-selection-note" id="showroom-selection-note">SELECT A DIFFERENT PIECE BELOW TO EXPLORE IT IN 3D.</p>
                <div class="showroom-panel-divider"></div>
                <div class="showroom-selected-image"><img id="showroom-product-image" src="<?= e($products[$selectedIndex]['image_url']) ?>" alt="<?= e($products[$selectedIndex]['name']) ?>" loading="lazy"><span>THE REAL-LIFE INSPIRATION</span></div>
            </aside>
        </div>

        <div class="showroom-collection-heading"><div><span class="eyebrow"><span class="eyebrow-line"></span> CHOOSE YOUR NEXT LOOK</span><h3>Explore the collection.</h3></div><span>SELECT A PIECE TO CHANGE THE 3D VIEW</span></div>
        <div class="showroom-product-grid" id="showroom-product-grid">
            <?php foreach ($products as $index => $product): ?>
                <button type="button" class="showroom-product-card <?= $index === $selectedIndex ? 'selected' : '' ?>" data-showroom-product="<?= $index ?>" data-category="<?= e($product['category']) ?>" aria-pressed="<?= $index === $selectedIndex ? 'true' : 'false' ?>">
                    <span class="showroom-card-image"><img src="<?= e($product['image_url']) ?>" alt="" loading="lazy"><span class="showroom-card-number">01 / <?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span><span class="showroom-card-view">VIEW IN 3D ↗</span></span>
                    <span class="showroom-card-info"><span><strong><?= e($product['name']) ?></strong><small><?= e(ucfirst($product['category'])) ?></small></span><b><?= e(money($product['price'])) ?></b></span>
                </button>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="showroom-empty"><span class="eyebrow">THE SHOWROOM IS GETTING READY</span><h3>Nothing on the shelves just yet.</h3><p>Add available products in the admin menu manager and they will appear here automatically.</p><a class="button button-dark" href="<?= e(base_url('admin/menu.php')) ?>">Open menu manager <span>↗</span></a></div>
    <?php endif; ?>
</section>

<section class="showroom-note-section"><div class="showroom-note-mark">K.O.D</div><p>Good things reward a closer look.</p><span>TAKE A MOMENT. FIND YOUR FAVOURITE.</span></section>
<script type="application/json" id="showroom-product-data"><?= json_encode($products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
