<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/AdminAuth.php';

$db   = Database::getInstance();
$auth = new AdminAuth($db);
$auth->requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: showtimes.php');
    exit;
}

if (!$auth->validateCsrf((string)($_POST['csrf_token'] ?? ''))) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Session expired. Please try again.'];
    header('Location: showtimes.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid showtime ID.'];
    header('Location: showtimes.php');
    exit;
}

try {
    $stmt = $db->prepare('DELETE FROM showtimes WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Showtime not found.'];
    } else {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Showtime deleted.'];
    }
} catch (PDOException $e) {
    error_log('showtime-delete failed: ' . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Could not delete the showtime.'];
}

header('Location: showtimes.php');
exit;
