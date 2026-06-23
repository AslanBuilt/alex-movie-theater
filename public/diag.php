<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<pre>\n";

// Simulate the exact same path logic as config.php
// config.php lives at __DIR__/config/config.php, so its __DIR__ is one deeper
$config_dir  = __DIR__ . '/config';            // same as __DIR__ inside config.php
$root_path   = dirname($config_dir);           // same as ROOT_PATH in config.php
$includes    = $root_path . '/includes';
$db_config   = $root_path . '/config/database.php';

echo "config_dir:     $config_dir\n";
echo "ROOT_PATH:      $root_path\n";
echo "INCLUDES_PATH:  $includes\n";
echo "DB_CONFIG_PATH: $db_config\n\n";

echo "config/config.php exists:   " . (file_exists($config_dir . '/config.php')   ? 'YES' : 'NO') . "\n";
echo "config/database.php exists: " . (file_exists($db_config)                    ? 'YES' : 'NO') . "\n";
echo "includes/ dir exists:       " . (is_dir($includes)                           ? 'YES' : 'NO') . "\n\n";

echo "Files in includes/:\n";
if (is_dir($includes)) {
    foreach (scandir($includes) as $f) {
        if ($f[0] !== '.') echo "  $f\n";
    }
} else {
    echo "  (directory missing)\n";
}

echo "\nDB connection test:\n";
if (file_exists($db_config)) {
    $cfg = require $db_config;
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
