<?php
$token = '0f5707731a2b4017655ccf3f85f348de';
if (!isset($_GET['t']) || $_GET['t'] !== $token) {
    http_response_code(403); exit;
}
header('Content-Type: text/plain');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/QrCode.php';

echo "GD loaded: " . (extension_loaded('gd') ? "YES" : "NO") . "\n";
echo "imagecreatetruecolor: " . (function_exists('imagecreatetruecolor') ? "YES" : "NO") . "\n";
echo "QrCode::isReady(): " . (QrCode::isReady() ? "yes — WILL GENERATE REAL QR" : "no — STILL BROKEN") . "\n";

$test_token = bin2hex(random_bytes(8));
$png_bytes  = QrCode::pngBytes($test_token);
$img        = @imagecreatefromstring($png_bytes);

if ($img) {
    echo "QR width:  " . imagesx($img) . "px\n";
    echo "QR height: " . imagesy($img) . "px\n";
    echo "Result: REAL QR CODE — demo ready\n";
    imagedestroy($img);
} else {
    echo "Result: STILL BROKEN — image decode failed\n";
}
