<?php
declare(strict_types=1);

/**
 * Employee POS — log out (/pos/logout.php).
 *
 * Clears the employee PIN identity from the POS session and returns to the
 * login screen. Does NOT touch the admin session; an admin who used the POS
 * stays logged into /admin.
 */

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/PosAuth.php';

PosAuth::bootstrap();

try {
    $db = Database::getInstance();
    (new PosAuth($db))->logout();
} catch (\Throwable $e) {
    // Even if the DB is down, clear the session identity defensively.
    unset($_SESSION['pos_employee_id'], $_SESSION['pos_employee_name'], $_SESSION['pos_login_time']);
}

header('Location: login.php');
exit;
