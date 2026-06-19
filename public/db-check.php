<?php
// Temporary diagnostic — remove after debugging
declare(strict_types=1);
header('Content-Type: application/json');

$result = [];

// Check if config.php is loadable
$configPath = __DIR__ . '/config/config.php';
$result['config_path'] = $configPath;
$result['config_exists'] = file_exists($configPath);

if ($result['config_exists']) {
    require_once $configPath;
    $result['DB_CONFIG_PATH'] = defined('DB_CONFIG_PATH') ? DB_CONFIG_PATH : 'undefined';
    $result['db_file_exists'] = defined('DB_CONFIG_PATH') && is_file(DB_CONFIG_PATH);

    if ($result['db_file_exists']) {
        $cfg = require DB_CONFIG_PATH;
        $result['db_host'] = $cfg['host'] ?? 'missing';
        $result['db_name'] = $cfg['database'] ?? 'missing';
        $result['db_user'] = $cfg['username'] ?? 'missing';
        $result['db_pass_len'] = strlen((string)($cfg['password'] ?? ''));

        // Try PDO
        try {
            $dsn = 'mysql:host=' . ($cfg['host'] ?? '') . ';dbname=' . ($cfg['database'] ?? '') . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $cfg['username'] ?? '', $cfg['password'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $result['pdo'] = 'connected';
        } catch (PDOException $e) {
            $result['pdo'] = 'FAILED: ' . $e->getMessage();
        }

        // Try mysqli
        try {
            $m = new mysqli($cfg['host'] ?? '', $cfg['username'] ?? '', $cfg['password'] ?? '', $cfg['database'] ?? '');
            $result['mysqli'] = $m->connect_error ? 'FAILED: ' . $m->connect_error : 'connected';
        } catch (\Throwable $e) {
            $result['mysqli'] = 'FAILED: ' . $e->getMessage();
        }
    }
}

echo json_encode($result, JSON_PRETTY_PRINT);
