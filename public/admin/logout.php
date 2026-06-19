<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/AdminAuth.php';

$auth = new AdminAuth(Database::getInstance());
$auth->logout();

// Start a fresh session so the flash survives the redirect.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('ALEX_ADMIN_SESS');
    session_start();
}

$_SESSION['flash'] = [
    'type'    => 'info',
    'message' => 'You have been logged out.',
];

header('Location: login.php');
exit;
