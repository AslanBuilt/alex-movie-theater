<?php
declare(strict_types=1);

/**
 * QrCode — renders a ticket token as a scannable QR PNG data URI.
 *
 * Real encoding needs a vendored QR library (single-file, no Composer, per
 * project convention). Until includes/vendor/qrcodegen.php is added, this
 * degrades to a clearly-labeled placeholder so confirmation.php and the
 * order email still render correctly — swap happens automatically once the
 * vendor file exists, no caller changes needed.
 *
 * Output is always a `data:image/png;base64,...` string (no remote requests),
 * matching Mailer's "no remote images" rule so it's safe to inline in email.
 */
final class QrCode
{
    private const VENDOR_PATH = __DIR__ . '/vendor/qrcodegen.php';

    public static function isReady(): bool
    {
        return is_file(self::VENDOR_PATH) && function_exists('imagecreatetruecolor');
    }

    /** @param string $data payload to encode (the raw ticket token) */
    public static function pngDataUri(string $data, int $moduleSize = 8, int $border = 4): string
    {
        return 'data:image/png;base64,' . base64_encode(self::pngBytes($data, $moduleSize, $border));
    }

    /** Raw PNG bytes — for embedding as a CID email attachment (data: URIs are stripped by Gmail). */
    public static function pngBytes(string $data, int $moduleSize = 8, int $border = 4): string
    {
        if (self::isReady()) {
            try {
                return self::renderReal($data, $moduleSize, $border);
            } catch (\Throwable $e) {
                error_log('[QrCode] real render failed, falling back: ' . $e->getMessage());
            }
        }
        return self::renderPlaceholder($data, $moduleSize, $border);
    }

    /**
     * Rasterizes the vendored library's QR matrix into a PNG via GD. Written
     * against Kazuhiko Arase's qrcodegen.php API, vendored under the
     * QrCodeGen namespace to avoid colliding with this class (PHP class
     * names are case-insensitive, so an unqualified `QRCode` would clash
     * with `QrCode`) — getMinimumQRCode()/createImage(); if a different
     * library is vendored, this method is the only thing to adapt.
     */
    private static function renderReal(string $data, int $moduleSize, int $border): string
    {
        require_once self::VENDOR_PATH;
        $qr  = \QrCodeGen\QRCode::getMinimumQRCode($data, QR_ERROR_CORRECT_LEVEL_M);
        $img = $qr->createImage($moduleSize, $border * $moduleSize);

        ob_start();
        imagepng($img);
        $bytes = ob_get_clean();
        imagedestroy($img);
        return (string)$bytes;
    }

    /** Bordered box with the token's short form — visually obvious it's a stand-in, not a scan failure. */
    private static function renderPlaceholder(string $data, int $moduleSize, int $border): string
    {
        $size = ($moduleSize * 20) + ($border * 2 * $moduleSize);
        if (function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor($size, $size);
            $white = imagecolorallocate($img, 255, 255, 255);
            $ink   = imagecolorallocate($img, 40, 40, 40);
            imagefilledrectangle($img, 0, 0, $size, $size, $white);
            imagerectangle($img, 4, 4, $size - 5, $size - 5, $ink);
            imagerectangle($img, 8, 8, $size - 9, $size - 9, $ink);
            $short = strtoupper(substr($data, 0, 8));
            imagestring($img, 3, (int)($size / 2) - 40, (int)($size / 2) - 20, 'QR PENDING', $ink);
            imagestring($img, 2, (int)($size / 2) - 32, (int)($size / 2), $short, $ink);
            ob_start();
            imagepng($img);
            $bytes = ob_get_clean();
            imagedestroy($img);
            return (string)$bytes;
        }
        // No GD at all (unlikely on this host) — 1x1 transparent PNG so the <img> tag never breaks layout.
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }
}
