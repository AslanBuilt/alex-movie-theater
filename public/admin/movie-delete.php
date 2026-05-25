<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/AdminAuth.php';

$db   = Database::getInstance();
$auth = new AdminAuth($db);
$auth->requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: movies.php');
    exit;
}

if (!$auth->validateCsrf((string)($_POST['csrf_token'] ?? ''))) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Session expired. Please try again.'];
    header('Location: movies.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid movie ID.'];
    header('Location: movies.php');
    exit;
}

try {
    // Defensively remove associated showtimes before deleting the movie,
    // in case the schema does not declare ON DELETE CASCADE.
    $stmt = $db->prepare('DELETE FROM showtimes WHERE movie_id = :id');
    $stmt->execute([':id' => $id]);

    $stmt = $db->prepare('DELETE FROM movies WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Movie not found.'];
    } else {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Movie deleted.'];
    }
} catch (PDOException $e) {
    error_log('movie-delete failed: ' . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Could not delete the movie.'];
}

header('Location: movies.php');
exit;
