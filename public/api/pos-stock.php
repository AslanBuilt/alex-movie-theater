<?php
declare(strict_types=1);

/**
 * Read-only stock snapshot for the employee POS grid (/api/pos-stock.php).
 * Polled every 60s by pos.js so an item sold out on another terminal greys
 * out here without a full page reload. No mutation, no CSRF needed.
 */

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/PosAuth.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

PosAuth::bootstrap();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

try {
    $db = Database::getInstance();
} catch (\Throwable $e) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Unavailable.']);
    exit;
}

$auth = new PosAuth($db);
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Session expired.']);
    exit;
}

$rows = $db->query(
    'SELECT id, stock_quantity, reorder_point FROM concessions WHERE is_available = 1'
)->fetchAll();

$concessions = [];
foreach ($rows as $r) {
    $concessions[(int)$r['id']] = [
        'stock'   => (int)$r['stock_quantity'],
        'reorder' => $r['reorder_point'] !== null ? (int)$r['reorder_point'] : null,
    ];
}

echo json_encode(['ok' => true, 'concessions' => $concessions]);
