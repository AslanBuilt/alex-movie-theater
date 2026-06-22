<?php
// Temporary diagnostic — delete after use
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<pre>\n";
echo "ROOT_PATH check:\n";
$root = dirname(__DIR__);
echo "  __DIR__ = " . __DIR__ . "\n";
echo "  ROOT_PATH = $root\n";
echo "  includes/ exists: " . (is_dir($root . '/includes') ? 'YES' : 'NO') . "\n";
echo "  config/database.php exists: " . (file_exists($root . '/config/database.php') ? 'YES' : 'NO') . "\n";

echo "\nFiles in includes/:\n";
if (is_dir($root . '/includes')) {
    foreach (scandir($root . '/includes') as $f) {
        if ($f[0] !== '.') echo "  $f\n";
    }
}

echo "\nDB connection test:\n";
if (file_exists($root . '/config/database.php')) {
    $cfg = require $root . '/config/database.php';
    try {
        $pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8mb4", $cfg['username'], $cfg['password']);
        echo "  Connected OK\n";
        echo "  concessions rows: " . $pdo->query("SELECT COUNT(*) FROM concessions")->fetchColumn() . "\n";
        echo "  sample image_path: " . ($pdo->query("SELECT image_path FROM concessions LIMIT 1")->fetchColumn() ?: 'NULL') . "\n";
    } catch (Exception $e) {
        echo "  FAILED: " . $e->getMessage() . "\n";
    }
} else {
    echo "  SKIPPED — database.php missing\n";
}
echo "</pre>";
@unlink(__FILE__);
echo "<p><em>Diagnostic deleted itself.</em></p>";
