<?php
declare(strict_types=1);

/**
 * Tiny fixed-window rate limiter for the cart + checkout endpoints.
 *
 * Storage is a small JSON file per key under logs/ratelimit/ (the logs dir is
 * already writable — it holds php-errors.log). No DB, no APCu dependency.
 *
 * IMPORTANT: this sits on the cart + payment path, so it FAILS OPEN. Any
 * storage/lock error allows the request through — a limiter must never turn a
 * disk glitch into "nobody can check out."
 */
final class RateLimiter
{
    /**
     * Returns true if the request is allowed, false if the limit is exceeded.
     *
     * @param string $key    Logical bucket (e.g. "cart:1.2.3.4").
     * @param int    $max    Max requests permitted within the window.
     * @param int    $window Window length in seconds.
     */
    public static function allow(string $key, int $max, int $window): bool
    {
        try {
            $dir = ROOT_PATH . '/logs/ratelimit';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
                if (!is_dir($dir)) {
                    return true; // can't create store → fail open
                }
            }

            $file = $dir . '/' . hash('sha256', $key) . '.json';
            $now  = time();

            $fh = @fopen($file, 'c+');
            if ($fh === false) {
                return true; // can't open store → fail open
            }

            $allowed = true;
            try {
                if (!flock($fh, LOCK_EX)) {
                    return true; // can't lock → fail open
                }

                $raw  = stream_get_contents($fh);
                $data = json_decode((string)$raw, true);
                if (!is_array($data) || ($now - (int)($data['start'] ?? 0)) >= $window) {
                    $data = ['start' => $now, 'count' => 0];
                }

                $data['count'] = (int)$data['count'] + 1;
                $allowed = $data['count'] <= $max;

                rewind($fh);
                ftruncate($fh, 0);
                fwrite($fh, json_encode($data));
                fflush($fh);
                flock($fh, LOCK_UN);
            } finally {
                fclose($fh);
            }

            return $allowed;
        } catch (\Throwable $e) {
            error_log('[RateLimiter] ' . $e->getMessage());
            return true; // any failure → fail open
        }
    }

    /**
     * Best-effort client IP for keying. Honors a single proxy hop but falls
     * back to REMOTE_ADDR; never throws.
     */
    public static function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return is_string($ip) && $ip !== '' ? $ip : '0.0.0.0';
    }

    /**
     * Emit a 429 JSON response and exit. For API endpoints that already speak
     * JSON (cart, order-customer).
     */
    public static function reject429(): void
    {
        http_response_code(429);
        header('Retry-After: 60');
        echo json_encode(['ok' => false, 'error' => 'Too many requests. Please slow down and try again in a moment.']);
        exit;
    }
}
