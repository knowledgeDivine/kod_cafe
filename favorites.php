<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/customer_auth.php';
require_once __DIR__ . '/includes/personalization.php';
$customer = require_customer();
$favoriteIds = kod_get_customer_favorite_ids((int)$customer['id']);
$favorites = [];
if ($favoriteIds) {
    $placeholders = implode(',', array_fill(0, count($favoriteIds), '?'));
    $stmt = db()->prepare("SELECT id, name, slug, category, description, price, image_url FROM menu_items WHERE is_available = 1 AND id IN ($placeholders) ORDER BY name ASC");
    $stmt->execute($favoriteIds);
    $favorites = $stmt->fetchAll();
}
$pageTitle = 'Your saved favourites — K.O.D Café';
$pageDescription = 'Your personal collection of saved K.O.D coffee, tea, desserts and pastries.';
require __DIR__ . '/includes/header.php';
?>
<section class="order-page section-pad favorites-page">
  <div class="section-kicker"><span>11</span><span class="kicker-line"></span><span>YOUR PERSONAL COLLECTION</span></div>
  <div class="favorites-heading"><div><span class="eyebrow">THE ONES YOU LOVE</span><h1 class="section-title">Saved for<br><em>your next moment.</em></h1><p>Keep your favourite K.O.D pours and sweet things close at hand.</p></div><a class="text-link" href="<?= e(base_url('account.php')) ?>">Back to my account ↗</a></div>
  <?php if (!$favorites): ?>
    <div class="favorites-empty"><span class="eyebrow">A LITTLE SPACE FOR YOUR FAVOURITES</span><h2>Nothing saved just yet.</h2><p>Browse the menu or explore the 3D showroom, then tap the heart on anything you would like to keep.</p><div><a class="button button-dark" href="<?= e(base_url('#menu')) ?>">Explore the menu <span>↗</span></a><a class="button button-outline" href="<?= e(base_url('showroom.php')) ?>">Visit the 3D showroom <span>↗</span></a></div></div>
  <?php else: ?>
    <div class="favorites-summary"><span><?= count($favorites) ?> SAVED <?= count($favorites) === 1 ? 'FAVOURITE' : 'FAVOURITES' ?></span><span>YOUR K.O.D SHORTLIST</span></div>
    <div class="favorites-grid">
      <?php foreach ($favorites as $index => $item): ?>
        <article class="menu-card favorite-product-card" data-category="<?= e($item['category']) ?>">
          <div class="menu-card-image"><img src="<?= e($item['image_url'] ?: placeholder_image($item['category'])) ?>" alt="<?= e($item['name']) ?>" loading="lazy"><span class="menu-card-count"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></span><button type="button" class="kod-favorite-toggle is-favorite" data-favorite-id="<?= (int)$item['id'] ?>" aria-pressed="true" aria-label="Remove <?= e($item['name']) ?> from favourites">♥</button></div>
          <div class="menu-card-info"><div><h3><?= e($item['name']) ?></h3><p><?= e($item['description']) ?></p></div><span class="menu-price"><?= e(money($item['price'])) ?></span></div>
          <button class="add-to-bag" type="button" data-product-id="<?= (int)$item['id'] ?>" data-product-name="<?= e($item['name']) ?>" data-product-price="<?= e((string)$item['price']) ?>" data-product-image="<?= e($item['image_url'] ?: placeholder_image($item['category'])) ?>">Add to bag <span>+</span></button>
          <a class="menu-3d-link" href="<?= e(base_url('showroom.php?item=' . rawurlencode((string)$item['slug']))) ?>">Explore in 3D <span>↗</span></a>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="favorites-feedback" id="favorites-feedback" aria-live="polite"></p>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
