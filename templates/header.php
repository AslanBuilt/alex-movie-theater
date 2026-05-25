<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? SITE_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= asset('images/favicon.svg') ?>">
    <meta name="description" content="<?= e($pageDescription ?? 'Alex Movie Theatre in Alexandria, Indiana. Two-screen independent theatre with affordable tickets. $5 adults, $3 children.') ?>">
    <meta name="keywords" content="<?= e($pageKeywords ?? 'movie theater Alexandria Indiana, movies Alexandria IN, Alex Theatre, affordable movies Indiana') ?>">
    <link rel="canonical" href="<?= e($canonical ?? SITE_URL) ?>">

    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($canonical ?? SITE_URL) ?>">
    <meta property="og:title" content="<?= e($pageTitle ?? SITE_NAME) ?>">
    <meta property="og:description" content="<?= e($pageDescription ?? 'Alex Movie Theatre — Alexandria\'s independent two-screen theatre. $5 adults, $3 children.') ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@700&family=Playfair+Display:ital,wght@0,700;1,400;1,700&family=Lato:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/main.css') ?>">

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "MovieTheater",
        "name": "Alex Movie Theatre",
        "url": "<?= SITE_URL ?>",
        "telephone": "<?= SITE_PHONE ?>",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "407 N. Harrison Street",
            "addressLocality": "Alexandria",
            "addressRegion": "IN",
            "postalCode": "46001",
            "addressCountry": "US"
        },
        "priceRange": "$"
    }
    </script>
</head>
<body>

<header class="navbar">
    <div class="container">
        <a href="<?= url() ?>" class="navbar-brand">
            <span class="brand-name">Alex Theatre</span>
            <span class="brand-sub">Alexandria, Indiana</span>
        </a>

        <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <ul class="nav-menu">
            <li><a href="<?= url() ?>" class="<?= navClass('index') ?>">Now Showing</a></li>
            <li><a href="<?= url('senior-movie.php') ?>" class="<?= navClass('senior-movie') ?>">Senior Movie</a></li>
            <li><a href="<?= url('concessions.php') ?>" class="<?= navClass('concessions') ?>">Concessions</a></li>
            <li><a href="<?= url('events.php') ?>" class="<?= navClass('events') ?>">Events</a></li>
            <li><a href="<?= url('private-screenings.php') ?>" class="<?= navClass('private-screenings') ?>">Private Screenings</a></li>
            <li><a href="<?= url('location.php') ?>" class="<?= navClass('location') ?>">Location</a></li>
            <li><a href="<?= url('contact.php') ?>" class="<?= navClass('contact') ?>">Contact</a></li>
            <li><a href="<?= e(TICKETS_URL) ?>" class="nav-link nav-cta">Buy Tickets</a></li>
        </ul>
    </div>
</header>

<main>
