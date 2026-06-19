<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/AdminAuth.php';

$db   = Database::getInstance();
$auth = new AdminAuth($db);
$auth->requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: senior-showings.php');
    exit;
}

if (!$auth->validateCsrf((string)($_POST['csrf_token'] ?? ''))) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Session expired. Please try again.'];
    header('Location: senior-showings.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid senior showing ID.'];
    header('Location: senior-showings.php');
    exit;
}

try {
    $stmt = $db->prepare('DELETE FROM senior_showings WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Senior showing not found.'];
    } else {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Senior showing deleted.'];
    }
} catch (PDOException $e) {
    error_log('senior-showing-delete failed: ' . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Could not delete the senior showing.'];
}

header('Location: senior-showings.php');
exit;
