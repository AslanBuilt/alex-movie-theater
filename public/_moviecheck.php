<?php
declare(strict_types=1);

$token = '46052181731e69c85e8bbc83d4b5c50e';
if (!hash_equals($token, (string)($_GET['t'] ?? ''))) {
    http_response_code(403);
    exit;
}
header('Content-Type: text/plain');

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';

try {
    $pdo = Database::getInstance();
} catch (Throwable $e) {
    echo "DB CONNECTION FAILED: " . $e->getMessage() . "\n";
    exit;
}

echo "=== All movies in database ===\n";
$rows = $pdo->query(
    "SELECT id, title, status, is_active, sort_order, created_at
     FROM movies
     ORDER BY created_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "No movies found\n";
} else {
    foreach ($rows as $row) {
        echo implode(' | ', array_map('strval', $row)) . "\n";
    }
}

echo "\n=== Movies table columns ===\n";
$cols = $pdo->query("SHOW COLUMNS FROM movies")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo $col['Field'] . ' | ' . $col['Type'] . ' | ' . $col['Null'] . ' | ' . ($col['Default'] ?? 'NULL') . "\n";
}

echo "\n=== Poster upload directory ===\n";
$destDir = __DIR__ . '/assets/images/posters/';
echo "Path: $destDir\n";
echo "Exists: " . (is_dir($destDir) ? 'yes' : 'no') . "\n";
if (is_dir($destDir)) {
    echo "Writable: " . (is_writable($destDir) ? 'yes' : 'NO') . "\n";
} else {
    echo "Parent writable: " . (is_writable(dirname($destDir)) ? 'yes' : 'NO') . "\n";
}

echo "\n=== PHP limits relevant to the create form ===\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "output_buffering: " . var_export(ini_get('output_buffering'), true) . "\n";
echo "display_errors: " . ini_get('display_errors') . "\n";
echo "log_errors: " . ini_get('log_errors') . "\n";
echo "error_reporting: " . ini_get('error_reporting') . "\n";
