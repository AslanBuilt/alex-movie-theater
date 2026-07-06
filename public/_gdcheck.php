<?php
declare(strict_types=1);

/**
 * TEMPORARY token-gated diagnostic. Confirms whether the .user.ini
 * `extension=gd` line actually loaded GD. REMOVE after use.
 */

$TOKEN = 'gdcheck-3n9v7q1r';
if (!hash_equals($TOKEN, (string)($_GET['key'] ?? ''))) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');

echo "PHP version: " . PHP_VERSION . "\n";
echo "SAPI: " . php_sapi_name() . "\n";
echo "Loaded php.ini: " . (php_ini_loaded_file() ?: '(none)') . "\n";
echo "Scanned .ini files: " . (php_ini_scanned_files() ?: '(none)') . "\n";
echo "\nGD extension_loaded: " . (extension_loaded('gd') ? 'YES' : 'NO') . "\n";
echo "imagecreatetruecolor exists: " . (function_exists('imagecreatetruecolor') ? 'YES' : 'NO') . "\n";

if (extension_loaded('gd')) {
    $info = gd_info();
    echo "GD version: " . ($info['GD Version'] ?? '?') . "\n";
    echo "PNG support: " . (!empty($info['PNG Support']) ? 'YES' : 'NO') . "\n";
}
