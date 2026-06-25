<?php
declare(strict_types=1);

function e(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function asset(string $path): string
{
    return SITE_URL . 'assets/' . ltrim($path, '/');
}

/**
 * Normalize a stored image_path to a path relative to assets/, tolerating values
 * that already carry a leading 'assets/' prefix. Render sites prepend 'assets/'
 * themselves, so this guarantees exactly one prefix and prevents 'assets/assets/…'
 * 404s when seed data (which stored the prefix) and the admin uploader (which does
 * not) disagree on the convention.
 */
function assetRel(?string $path): string
{
    $p = ltrim((string)$path, '/');
    if ($p === '') {
        return '';
    }
    if (strncasecmp($p, 'assets/', 7) === 0) {
        $p = substr($p, 7);
    }
    return $p;
}

function url(string $path = ''): string
{
    return SITE_URL . ltrim($path, '/');
}

function isCurrentPage(string $page): bool
{
    $current = basename($_SERVER['PHP_SELF'], '.php');
    return $current === $page || ($page === 'index' && $current === '/');
}

function navClass(string $page): string
{
    return isCurrentPage($page) ? 'nav-link active' : 'nav-link';
}

/**
 * Runs $fn and returns its result, or $fallback on any Throwable.
 *
 * Used to invoke DB-backed repositories from page templates without
 * having to scatter try/catch — pages can just call:
 *   $rows = tryDb(fn() => MovieRepo::getNowShowing());
 * and branch on emptiness for the static-fallback path.
 *
 * @param callable $fn
 * @param mixed $fallback
 * @return mixed
 */
function tryDb(callable $fn, $fallback = [])
{
    try {
        return $fn();
    } catch (\Throwable $e) {
        error_log('[tryDb] ' . $e->getMessage());
        return $fallback;
    }
}
