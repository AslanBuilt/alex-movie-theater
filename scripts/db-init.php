<?php
// One-time DB setup — creates tables + seeds data. Safe to run repeatedly (idempotent).
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

header('Content-Type: application/json');

if (!defined('DB_CONFIG_PATH') || !is_file(DB_CONFIG_PATH)) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'msg' => 'database.php missing']);
    exit;
}

$cfg = require DB_CONFIG_PATH;

function makeConn(array $cfg): mysqli {
    $c = new mysqli(
        (string)($cfg['host']     ?? 'localhost'),
        (string)($cfg['username'] ?? ''),
        (string)($cfg['password'] ?? ''),
        (string)($cfg['database'] ?? '')
    );
    if ($c->connect_error) {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'msg' => 'connect: ' . $c->connect_error]);
        exit;
    }
    $c->set_charset('utf8mb4');
    return $c;
}

function runSql(mysqli $conn, string $sql, string $label): void {
    // Split on semicolons, skip comments and blank lines
    $parts = preg_split('/;\s*\n/', $sql);
    foreach ($parts as $stmt) {
        $stmt = trim($stmt);
        // Skip blank lines and pure comment blocks
        $stripped = preg_replace('/--[^\n]*\n?/', '', $stmt);
        if (trim($stripped) === '') continue;
        if (!$conn->query($stmt)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'msg' => $label . ': ' . $conn->error . ' | stmt: ' . substr($stmt, 0, 80)]);
            exit;
        }
    }
}

// Check if already seeded (idempotent guard)
$conn = makeConn($cfg);
$r = $conn->query(
    "SELECT COUNT(*) AS cnt FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name = 'concessions'"
);
$tableExists = (int)$r->fetch_assoc()['cnt'] > 0;
if ($tableExists) {
    $r2 = $conn->query("SELECT COUNT(*) AS cnt FROM concessions");
    if ((int)$r2->fetch_assoc()['cnt'] > 0) {
        $conn->close();
        echo json_encode(['status' => 'skipped', 'msg' => 'already seeded']);
        exit;
    }
}
$conn->close();

// Run schema on a fresh connection
$conn = makeConn($cfg);
$schema = file_get_contents(__DIR__ . '/database/schema.sql');
if ($schema === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'msg' => 'schema.sql not found']);
    exit;
}
runSql($conn, $schema, 'schema');
$conn->close();

// Run seed on another fresh connection
$conn = makeConn($cfg);
$seed = file_get_contents(__DIR__ . '/database/seed.sql');
if ($seed === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'msg' => 'seed.sql not found']);
    exit;
}
runSql($conn, $seed, 'seed');
$conn->close();

echo json_encode(['status' => 'success', 'msg' => 'schema + seed complete']);
