<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/AdminAuth.php';
require_once INCLUDES_PATH . '/ConcessionRepo.php';

$db   = Database::getInstance();
$auth = new AdminAuth($db);
$auth->requireAuth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $repo = new ConcessionRepo($db);
    $repo->delete($id);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Concession item deleted.'];
}

header('Location: concessions.php');
exit;
