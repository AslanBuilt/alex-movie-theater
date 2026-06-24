<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'The Alex — Alexandria, Indiana') ?></title>
<meta name="description" content="<?= htmlspecialchars($pageDescription ?? '') ?>">
<link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@700&family=Playfair+Display:ital,wght@0,700;1,400;1,700&family=Lato:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/main.css">
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
      <li>
        <button class="cart-icon-btn" id="cartBtn" aria-label="View cart">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
            <line x1="3" y1="6" x2="21" y2="6"/>
            <path d="M16 10a4 4 0 01-8 0"/>
          </svg>
          <span class="cart-badge" id="cartBadge" style="display:none">0</span>
        </button>
      </li>
    </ul>
  </div>
</header>

<main>
