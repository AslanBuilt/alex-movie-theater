<?php
// One-time DB setup — creates tables + seeds data. Safe to run repeatedly (idempotent).
// Deployed temporarily by the CI workflow, deleted from server after each run.
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

header('Content-Type: application/json');

if (!defined('DB_CONFIG_PATH') || !is_file(DB_CONFIG_PATH)) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'msg' => 'database.php missing']);
    exit;
}

$cfg  = require DB_CONFIG_PATH;
$conn = new mysqli(
    (string)($cfg['host']     ?? ''),
    (string)($cfg['username'] ?? ''),
    (string)($cfg['password'] ?? ''),
    (string)($cfg['database'] ?? '')
);

if ($conn->connect_error) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'msg' => 'connect: ' . $conn->connect_error]);
    exit;
}

// Idempotent guard — skip if concessions table already has rows
$r = $conn->query(
    "SELECT COUNT(*) AS cnt FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name = 'concessions'"
);
$tableExists = (int)$r->fetch_assoc()['cnt'] > 0;
if ($tableExists) {
    $r2 = $conn->query("SELECT COUNT(*) AS cnt FROM concessions");
    if ((int)$r2->fetch_assoc()['cnt'] > 0) {
        echo json_encode(['status' => 'skipped', 'msg' => 'already seeded']);
        $conn->close();
        exit;
    }
}

// Run schema (drops + recreates tables)
$schema = file_get_contents(__DIR__ . '/database/schema.sql');
if ($schema === false || !$conn->multi_query($schema)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'msg' => 'schema: ' . $conn->error]);
    $conn->close();
    exit;
}
while ($conn->more_results() && $conn->next_result());

// Run seed
$seed = file_get_contents(__DIR__ . '/database/seed.sql');
if ($seed === false || !$conn->multi_query($seed)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'msg' => 'seed: ' . $conn->error]);
    $conn->close();
    exit;
}
while ($conn->more_results() && $conn->next_result());

$conn->close();
echo json_encode(['status' => 'success', 'msg' => 'schema + seed complete']);
