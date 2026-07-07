<?php
$token = '3e439399547f39845c56c4e7f70d6517';
if (!isset($_GET['t']) || $_GET['t'] !== $token) {
    http_response_code(403); exit;
}
header('Content-Type: text/plain');

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';

$pdo = Database::getInstance();

echo "=== admin_users ===\n";
foreach ($pdo->query("SELECT id, username, is_active FROM admin_users")->fetchAll(PDO::FETCH_ASSOC) as $a) {
    echo 'id=' . $a['id'] . ' username=' . $a['username'] . ' is_active=' . $a['is_active'] . "\n";
}

echo "\n=== employees ===\n";
foreach ($pdo->query("SELECT id, name, is_active FROM employees")->fetchAll(PDO::FETCH_ASSOC) as $e) {
    echo 'id=' . $e['id'] . ' name=' . $e['name'] . ' is_active=' . $e['is_active'] . "\n";
}
