<?php
declare(strict_types=1);

/**
 * Attach optional customer name/email to a still-pending transaction, just
 * before the buyer confirms payment. CSRF-protected; only touches pending rows.
 */

session_name('ALEX_ADMIN_SESS');
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '{"ok":false,"error":"Method not allowed"}';
    exit;
}

$body = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($body)) {
    http_response_code(400);
    echo '{"ok":false,"error":"Invalid JSON"}';
    exit;
}

$token = (string)($body['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo '{"ok":false,"error":"Invalid session token"}';
    exit;
}

$ref   = trim((string)($body['ref'] ?? ''));
$name  = trim((string)($body['name'] ?? ''));
$email = trim((string)($body['email'] ?? ''));

if ($ref === '') {
    http_response_code(400);
    echo '{"ok":false,"error":"Missing reference"}';
    exit;
}

// Basic email sanity; ignore an invalid value rather than rejecting checkout.
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = '';
}

$ok = TransactionRepo::setCustomerByRef($ref, $name, $email);
echo json_encode(['ok' => $ok]);
