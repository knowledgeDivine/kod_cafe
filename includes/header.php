<?php
$pageTitle = $pageTitle ?? 'K.O.D Café — A little more than coffee';
$pageDescription = $pageDescription ?? 'Coffee, craft and beautiful moments. Discover the K.O.D café experience.';
$activePage = $activePage ?? '';
require_once __DIR__ . '/customer_auth.php';
$signedCustomer = current_customer();
$flash = get_flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#211812">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/images/kod-mark.svg')) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
    <?php foreach (($extraStyles ?? []) as $extraStyle): ?><link rel="stylesheet" href="<?= e(base_url((string) $extraStyle)) ?>"><?php endforeach; ?>
    <script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.169.0/build/three.module.js"}}</script>
</head>
<body>
<div class="page-grain" aria-hidden="true"></div>
<header class="site-header" id="site-header">
    <a class="brand" href="<?= e(base_url()) ?>" aria-label="K.O.D home">
        <span class="brand-mark">K<span>.</span>O<span>.</span>D</span>
        <span class="brand-sub">COFFEE / DESSERT / CULTURE</span>
    </a>
    <button class="nav-toggle" id="nav-toggle" aria-label="Open navigation" aria-expanded="false"><span></span><span></span></button>
    <nav class="main-nav" id="main-nav" aria-label="Main navigation">
        <a class="<?= $activePage === 'home' ? 'active' : '' ?>" href="<?= e(base_url()) ?>">Home</a>
        <a href="<?= e(base_url('#experience')) ?>">Experience</a>
        <a href="<?= e(base_url('#menu')) ?>">Our menu</a>
        <a class="<?= $activePage === 'showroom' ? 'active' : '' ?>" href="<?= e(base_url('showroom.php')) ?>">3D showroom</a>
        <a class="<?= $activePage === 'barista' ? 'active' : '' ?>" href="<?= e(base_url('ai-barista.php')) ?>">AI barista</a>
        <a href="<?= e(base_url('#coffee-studio')) ?>">Coffee studio</a>
        <a href="<?= e(base_url('#story')) ?>">Our story</a>
    </nav>
    <div class="header-actions"><a class="orders-link" href="<?= e(base_url('order-history.php')) ?>">My orders</a><?php if ($signedCustomer): ?><a class="saved-nav-link" href="<?= e(base_url('favorites.php')) ?>">Saved</a><?php endif; ?><a class="account-nav-link" href="<?= e(base_url($signedCustomer ? 'account.php' : 'login.php')) ?>"><?= $signedCustomer ? 'My account' : 'Sign in' ?></a><a class="bag-link" href="<?= e(base_url('cart.php')) ?>">Your bag <span class="bag-count" data-cart-count>0</span></a><a class="header-cta" href="<?= e(base_url('#contact')) ?>">Visit K.O.D <span>↗</span></a></div>
</header>
<?php if ($flash): ?>
    <div class="flash-message <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
<?php endif; ?>
<main>
