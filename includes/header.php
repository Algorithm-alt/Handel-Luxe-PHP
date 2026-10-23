<?php require_once __DIR__ . '/../config/app.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?> | <?= APP_NAME ?></title>
  <script>
    document.documentElement.classList.add('js');
    try {
      var savedTheme = localStorage.getItem('handel-luxe-theme');
      var initialTheme = savedTheme || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      if (initialTheme === 'dark') document.documentElement.dataset.theme = 'dark';
    } catch (e) {}
  </script>
<?php
  // Per-page meta description; falls back to a sensible site-wide default.
  $metaDescription = $metaDescription ?? 'Shop authentic perfumes, body sprays and gift sets from Handel Luxe GH. Nationwide delivery across Ghana with secure Paystack payment or pay on delivery.';

  // Canonical URL: REQUEST_URI includes the app's subdirectory, which
  // BASE_URL already carries — strip the base path to avoid doubling it.
  $requestPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
  if (strpos($requestPath, BASE_PATH) === 0) {
      $requestPath = substr($requestPath, strlen(BASE_PATH) - 1);
  }
  $canonicalUrl = rtrim(BASE_URL, '/') . htmlspecialchars($canonicalPath ?? $requestPath);
?>
  <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
<?php if (!empty($noIndex)): ?>
  <meta name="robots" content="noindex, nofollow, noarchive">
<?php endif; ?>
  <link rel="canonical" href="<?= $canonicalUrl ?>">

  <!-- Social share images -->
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= APP_NAME ?>">
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? APP_NAME) ?> | <?= APP_NAME ?>">
  <meta property="og:description" content="<?= htmlspecialchars($metaDescription) ?>">
  <meta property="og:image" content="<?= base_url('/images/logo.png') ?>">
  <meta property="og:url" content="<?= $canonicalUrl ?>">
  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle ?? APP_NAME) ?> | <?= APP_NAME ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($metaDescription) ?>">
  <meta name="twitter:image" content="<?= base_url('/images/logo.png') ?>">

  <link rel="icon" type="image/png" href="<?= base_url('/images/logo.png') ?>">
  <?php /* Version param busts browser caches when style.css changes (Apache sends no Cache-Control). Bump on every CSS edit. */ ?>
  <link rel="stylesheet" href="<?= base_url('/css/style.css') ?>?v=<?= filemtime(__DIR__ . '/../public/css/style.css') ?>">
  <?php /* JS adds its own "/" before routes, so expose the base without a
           trailing slash; otherwise every fetch/link becomes "...//api/...". */ ?>
  <script>window.APP_BASE_URL = <?= json_encode(rtrim(BASE_URL, '/')) ?>;</script>
<?php
  // Google Analytics (GA4). Set the measurement ID in config/app.php to
  // activate; nothing is loaded while it is empty (local/dev installs).
  if (defined('GA_MEASUREMENT_ID') && GA_MEASUREMENT_ID && !app_is_local()):
?>
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars(GA_MEASUREMENT_ID) ?>"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', <?= json_encode(GA_MEASUREMENT_ID) ?>);
  </script>
<?php endif; ?>
</head>
<body>
<?php require_once __DIR__ . '/loader.php'; ?>
<aside class="announcement-bar" id="announcement-bar" aria-label="Store announcements">
  <p class="announcement-msg is-active" data-announcement>
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
    <span>Nationwide delivery across Ghana &mdash; pay on delivery available</span>
  </p>
  <p class="announcement-msg" data-announcement>
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
    <span>100% authentic fragrances, sourced from authorized suppliers</span>
  </p>
  <p class="announcement-msg" data-announcement>
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
    <span>Need help choosing? WhatsApp us on 0542332244</span>
  </p>
  <button type="button" class="announcement-dismiss" id="announcement-dismiss" aria-label="Dismiss announcements">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
  </button>
</aside>
<nav class="main-nav"><div class="container nav-inner">
  <a href="<?= base_url('/') ?>" class="logo"><img src="<?= base_url('/images/logo.png') ?>" alt="<?= APP_NAME ?> logo" class="logo-img"> <?= APP_NAME ?></a>
  <button class="hamburger" aria-label="Open menu"><span></span><span></span><span></span></button>
  <div class="nav-links">
    <div class="nav-search-wrap">
      <form class="nav-search" action="<?= base_url('/products') ?>" method="get" role="search" id="nav-search-form">
        <input type="text" name="search" id="nav-search-input" placeholder="Search fragrances..." aria-label="Search fragrances" autocomplete="off" aria-expanded="false" aria-controls="search-suggest">
        <button type="submit" aria-label="Search"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></button>
      </form>
      <div class="search-suggest" id="search-suggest" role="listbox" aria-label="Search suggestions" hidden></div>
    </div>
    <a href="<?= base_url('/') ?>">Home</a>
    <a href="<?= base_url('/products') ?>">Products</a>
    <a href="<?= base_url('/about') ?>">About</a>
    <a href="<?= base_url('/contact') ?>">Contact</a>
    <a href="<?= base_url('/wishlist') ?>" class="nav-wishlist" aria-label="Wishlist"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg><span class="wishlist-count-badge" data-wishlist-count hidden>0</span></a>
    <a href="<?= base_url('/cart') ?>" class="nav-cart"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span class="cart-count" id="cart-count" data-cart-count aria-label="Items in cart">0</span></a>
    <button type="button" id="theme-toggle" class="theme-toggle" aria-label="Switch to dark mode" title="Switch to dark mode"><span aria-hidden="true">☾</span><span class="theme-toggle-label">Dark</span></button>
    <span id="auth-links"></span>
  </div>
</div></nav>
<?php if (!empty($breadcrumbs)): ?>
<nav class="breadcrumbs" aria-label="Breadcrumb"><div class="container">
  <ol>
    <?php foreach ($breadcrumbs as $crumb): ?>
      <?php if (!empty($crumb['url'])): ?>
        <li><a href="<?= base_url($crumb['url']) ?>"><?= htmlspecialchars($crumb['label']) ?></a></li>
      <?php else: ?>
        <li aria-current="page"><?= htmlspecialchars($crumb['label']) ?></li>
      <?php endif; ?>
    <?php endforeach; ?>
  </ol>
</div></nav>
<?php endif; ?>

