<?php
declare(strict_types=1);

/**
 * JSON endpoint for movies.php's drag-to-reorder UI.
 * Accepts {"order":[{"id":..,"sort_order":..}, ...]} and persists the new
 * sort_order for every listed movie in a single transaction.
 *
 * This is a JSON POST, not a form submit, so the CSRF token travels via the
 * X-CSRF-Token request header (same convention as public/api/cart.php)
 * instead of a hidden form field, but is validated through the exact same
 * AdminAuth::validateCsrf() every other admin POST handler uses — CSRF
 * protection is not skipped just because the payload is JSON.
 */

require_once __DIR__ . '/../../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/AdminAuth.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $db = Database::getInstance();
} catch (\Throwable $e) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Database unavailable.']);
    exit;
}

$auth = new AdminAuth($db);
$auth->requireAuth(); // redirects to login.php if not authenticated, same as every other admin page

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!$auth->validateCsrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Your session expired. Please refresh and try again.']);
    exit;
}

$raw  = (string)file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || !isset($data['order']) || !is_array($data['order'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Malformed request.']);
    exit;
}

$ids = [];
foreach ($data['order'] as $entry) {
    if (!is_array($entry) || !isset($entry['id'])) {
        continue;
    }
    $id = (int)$entry['id'];
    if ($id > 0) {
        $ids[] = $id;
    }
}
$ids = array_values(array_unique($ids));

if (count($ids) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No movies to reorder.']);
    exit;
}

try {
    $db->beginTransaction();

    // The authoritative order is each id's position in the array (1-based),
    // not whatever numeric sort_order the client sent — trusting only the
    // sequence means a tampered/duplicated payload can't produce duplicate
    // or out-of-range sort_order values in the database.
    $stmt = $db->prepare('UPDATE movies SET sort_order = :sort_order WHERE id = :id');
    foreach ($ids as $index => $movieId) {
        $stmt->execute([
            ':sort_order' => $index + 1,
            ':id'         => $movieId,
        ]);
    }

    $db->commit();
    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('movies-reorder save failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save the new order.']);
}
