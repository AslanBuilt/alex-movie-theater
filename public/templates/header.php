<?php
// Ensure a session + CSRF token exist so any page can safely POST to the cart API.
// Guarded so pages that already started the session (e.g. checkout.php) don't notice.
if (session_status() === PHP_SESSION_NONE) {
    session_name('ALEX_ADMIN_SESS');
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e($_SESSION['csrf_token']) ?>">
<title><?= htmlspecialchars($pageTitle ?? 'The Alex — Alexandria, Indiana') ?></title>
<meta name="description" content="<?= htmlspecialchars($pageDescription ?? '') ?>">
<?php
$_ogTitle = $pageTitle       ?? 'The Alex — Alexandria, Indiana';
$_ogDesc  = $pageDescription ?? 'Your neighborhood two-screen movie theater in Alexandria, Indiana. Adults $5, Children $3.';
$_ogImg   = $ogImage         ?? SITE_URL . 'assets/images/hero-1.webp';
$_ogUrl   = SITE_URL . basename($_SERVER['PHP_SELF'] ?? 'index.php');
?>
<meta property="og:type"        content="website">
<meta property="og:site_name"   content="<?= htmlspecialchars(SITE_NAME) ?>">
<meta property="og:title"       content="<?= htmlspecialchars($_ogTitle) ?>">
<meta property="og:description" content="<?= htmlspecialchars($_ogDesc) ?>">
<meta property="og:url"         content="<?= htmlspecialchars($_ogUrl) ?>">
<meta property="og:image"       content="<?= htmlspecialchars($_ogImg) ?>">
<meta name="twitter:card"       content="summary_large_image">
<meta name="twitter:title"      content="<?= htmlspecialchars($_ogTitle) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($_ogDesc) ?>">
<meta name="twitter:image"      content="<?= htmlspecialchars($_ogImg) ?>">
<?php if (defined('GA_MEASUREMENT_ID') && GA_MEASUREMENT_ID !== ''): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= GA_MEASUREMENT_ID ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= GA_MEASUREMENT_ID ?>');</script>
<?php endif; ?>
<?php if (defined('FB_PIXEL_ID') && FB_PIXEL_ID !== ''): ?>
<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','<?= FB_PIXEL_ID ?>');fbq('track','PageView');</script>
<?php endif; ?>
<link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@700&family=Playfair+Display:ital,wght@0,700;1,400;1,700&family=Lato:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/main.css?v=<?= @filemtime(__DIR__ . '/../assets/css/main.css') ?>">
</head>
<body>

<header class="navbar">
  <div class="container">
    <a href="index.php" class="navbar-brand">
      <img src="assets/images/logo.webp" alt="The Alex logo" class="brand-logo" width="44" height="44">
      <span class="brand-name">The Alex</span>
    </a>
    <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
    <ul class="nav-menu">
      <li><a href="index.php" class="nav-link<?= ($currentPage ?? '') === 'index' ? ' active' : '' ?>">Now Showing</a></li>
      <li><a href="senior-movie.php" class="nav-link<?= ($currentPage ?? '') === 'senior-movie' ? ' active' : '' ?>">Senior Movie</a></li>
      <li><a href="concessions.php" class="nav-link<?= ($currentPage ?? '') === 'concessions' ? ' active' : '' ?>">Concessions</a></li>
      <li><a href="events.php" class="nav-link<?= ($currentPage ?? '') === 'events' ? ' active' : '' ?>">Events</a></li>
      <li><a href="private-screenings.php" class="nav-link<?= ($currentPage ?? '') === 'private-screenings' ? ' active' : '' ?>">Private Screenings</a></li>
      <li><a href="location.php" class="nav-link<?= in_array($currentPage ?? '', ['location','contact'], true) ? ' active' : '' ?>">Location &amp; Contact</a></li>
      <li><a href="tickets.php" class="nav-link nav-cta">Buy Tickets</a></li>
      <?php if (!empty($showCart)): ?>
      <li>
        <button class="cart-icon-btn" id="cartBtn" aria-label="View cart">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
            <line x1="3" y1="6" x2="21" y2="6"/>
            <path d="M16 10a4 4 0 01-8 0"/>
          </svg>
          <span class="cart-btn-label">Order</span>
          <span class="cart-badge" id="cartBadge" style="display:none">0</span>
        </button>
      </li>
      <?php endif; ?>
    </ul>
  </div>
</header>

<main>
