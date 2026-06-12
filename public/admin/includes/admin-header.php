<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/AdminAuth.php';

try {
    $db = Database::getInstance();
} catch (RuntimeException $e) {
    http_response_code(503);
    ?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Unavailable</title>
<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f5f5f5}
.box{background:#fff;padding:2rem 2.5rem;border-radius:6px;max-width:400px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.1)}
h1{font-size:1.25rem;margin-bottom:.75rem}p{color:#555;font-size:.9rem}</style>
</head><body><div class="box">
<h1>Admin panel unavailable</h1>
<p>The database has not been configured on this server yet. Please set up <code>config/database.php</code> and try again.</p>
</div></body></html><?php
    exit;
}
$auth = new AdminAuth($db);
$auth->requireAuth();

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$adminUser   = $auth->getUser();

/**
 * Return the appropriate CSS class for a sidebar nav link based on the
 * current page. The first matching slug wins.
 *
 * @param string[] $slugs
 */
function admin_nav_class(array $slugs, string $currentPage): string
{
    foreach ($slugs as $slug) {
        if ($slug === $currentPage) {
            return 'admin-nav-link is-active';
        }
    }
    return 'admin-nav-link';
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = $pageTitle ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($pageTitle) ?> — <?= e(SITE_NAME) ?> Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400;1,700&family=Lato:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-body">
<div class="admin-layout">
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-brand">
            <span class="admin-brand-name">Alex Theatre</span>
            <span class="admin-brand-sub">ADMIN</span>
        </div>

        <nav class="admin-nav" aria-label="Admin navigation">
            <a class="<?= admin_nav_class(['index'], $currentPage) ?>" href="index.php">Dashboard</a>
            <a class="<?= admin_nav_class(['movies', 'movie-edit', 'movie-delete'], $currentPage) ?>" href="movies.php">Movies</a>
            <a class="<?= admin_nav_class(['showtimes', 'showtime-edit', 'showtime-delete'], $currentPage) ?>" href="showtimes.php">Showtimes</a>
            <a class="<?= admin_nav_class(['events', 'event-edit', 'event-delete'], $currentPage) ?>" href="events.php">Events</a>
            <a class="<?= admin_nav_class(['senior-showings', 'senior-showing-edit', 'senior-showing-delete'], $currentPage) ?>" href="senior-showings.php">Senior Showings</a>
        </nav>

        <div class="admin-sidebar-footer">
            <?php if ($adminUser !== null) : ?>
                <div class="admin-user">
                    <span class="admin-user-label">Signed in as</span>
                    <span class="admin-user-name"><?= e($adminUser['username']) ?></span>
                </div>
            <?php endif; ?>
            <a class="admin-logout" href="logout.php">Log out</a>
        </div>
    </aside>

    <button type="button" class="sidebar-toggle" data-sidebar-toggle aria-label="Toggle navigation">Menu</button>

    <main class="admin-content">
        <?php if ($flash !== null && is_array($flash)) :
            $type    = $flash['type']    ?? 'info';
            $message = $flash['message'] ?? '';
            $allowed = ['success', 'error', 'info'];
            if (!in_array($type, $allowed, true)) {
                $type = 'info';
            }
        ?>
            <div class="alert alert-<?= e($type) ?>" role="alert">
                <?= e((string)$message) ?>
            </div>
        <?php endif; ?>
