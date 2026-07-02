<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
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

if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error    = '';
$username = '';

if (($_GET['reason'] ?? '') === 'expired') {
    $error = 'Your session expired. Please sign in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // No trusted reverse proxy in front of this host — REMOTE_ADDR is the
    // real client IP (see AdminAuth::isIpLockedOut()).
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

    if ($auth->isIpLockedOut($ip)) {
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
                $auth->clearFailedAttempts($ip);
                header('Location: index.php');
                exit;
            } else {
                $auth->recordFailedAttempt($ip);
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
            <span class="login-brand-name">The Alex</span>
            <span class="login-brand-sub" id="brandSub"><?= $error !== '' ? 'ADMIN' : 'SIGN IN' ?></span>
        </div>

        <!-- Chooser: pick Admin (this page) or Employee (PIN page). -->
        <div class="login-choice" id="loginChooser"<?= $error !== '' ? ' hidden' : '' ?>>
            <button type="button" class="choice-btn" id="chooseAdmin">
                <span class="choice-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9.5 12l1.8 1.8 3.5-3.6"/></svg></span>
                <span class="choice-tx"><span class="choice-tt">Admin login</span><span class="choice-ss">Username &amp; password</span></span>
                <span class="choice-arr"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 6l6 6-6 6"/></svg></span>
            </button>
            <a class="choice-btn" href="../pos/login.php">
                <span class="choice-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="3"/><circle cx="8.5" cy="9" r="1.1" fill="currentColor"/><circle cx="12" cy="9" r="1.1" fill="currentColor"/><circle cx="15.5" cy="9" r="1.1" fill="currentColor"/><circle cx="8.5" cy="13" r="1.1" fill="currentColor"/><circle cx="12" cy="13" r="1.1" fill="currentColor"/><circle cx="15.5" cy="13" r="1.1" fill="currentColor"/><path d="M8.5 17h7"/></svg></span>
                <span class="choice-tx"><span class="choice-tt">Employee</span><span class="choice-ss">Enter your register PIN</span></span>
                <span class="choice-arr"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 6l6 6-6 6"/></svg></span>
            </a>
        </div>

        <!-- Admin username/password pane -->
        <div id="adminPane"<?= $error === '' ? ' hidden' : '' ?>>
            <button type="button" class="login-back" id="backToChooser">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 6l-6 6 6 6"/></svg> Back
            </button>

            <?php if ($error !== '') : ?>
                <div class="alert alert-error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" class="admin-form" data-prevent-double="1" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username"
                           value="<?= e($username) ?>" autocomplete="username" required>
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
    </div>

    <script>
        (function () {
            var chooser = document.getElementById('loginChooser');
            var pane = document.getElementById('adminPane');
            var sub = document.getElementById('brandSub');
            document.getElementById('chooseAdmin').addEventListener('click', function () {
                chooser.hidden = true; pane.hidden = false; sub.textContent = 'ADMIN';
                var u = document.getElementById('username'); if (u) u.focus();
            });
            document.getElementById('backToChooser').addEventListener('click', function () {
                pane.hidden = true; chooser.hidden = false; sub.textContent = 'SIGN IN';
            });
        })();
    </script>
    <script src="js/admin.js" defer></script>
</body>
</html>
