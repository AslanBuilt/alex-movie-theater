<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/AdminAuth.php';

$db   = Database::getInstance();
$auth = new AdminAuth($db);

if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$RATE_WINDOW_SECONDS = 600;
$RATE_MAX_ATTEMPTS   = 5;

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $now = time();

    $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'first' => $now];
    if (!is_array($attempts)) {
        $attempts = ['count' => 0, 'first' => $now];
    }
    if (($now - (int)($attempts['first'] ?? $now)) > $RATE_WINDOW_SECONDS) {
        $attempts = ['count' => 0, 'first' => $now];
    }

    $isLockedOut = ((int)$attempts['count'] >= $RATE_MAX_ATTEMPTS)
        && (($now - (int)$attempts['first']) < $RATE_WINDOW_SECONDS);

    if ($isLockedOut) {
        $error = 'Too many failed attempts. Please wait a few minutes and try again.';
    } else {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!$auth->validateCsrf($token)) {
            $error = 'Your session expired. Please try again.';
        } else {
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            if ($username === '' || $password === '') {
                $error = 'Please enter both username and password.';
            } elseif ($auth->login($username, $password)) {
                unset($_SESSION['login_attempts']);
                header('Location: index.php');
                exit;
            } else {
                $attempts['count'] = (int)$attempts['count'] + 1;
                $_SESSION['login_attempts'] = $attempts;
                $error = 'Invalid username or password.';
            }
        }
    }
}

$csrf      = $auth->generateCsrfToken();
$pageTitle = 'Sign in';
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
<body class="admin-login">
    <div class="login-card">
        <div class="login-brand">
            <span class="login-brand-name">Alex Theatre</span>
            <span class="login-brand-sub">ADMIN</span>
        </div>

        <?php if ($error !== '') : ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="admin-form" data-prevent-double="1" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username"
                       value="<?= e($username) ?>" autocomplete="username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password"
                       autocomplete="current-password" required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Sign in</button>
            </div>
        </form>
    </div>

    <script src="js/admin.js" defer></script>
</body>
</html>
