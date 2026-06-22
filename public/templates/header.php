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
      <img src="assets/images/logo.jpg" alt="The Alex logo" class="brand-logo" width="44" height="44">
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
    </ul>
  </div>
</header>

<main>
