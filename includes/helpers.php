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
 * Returns an inline Tabler (MIT-licensed) SVG icon by name.
 * Stroke icons inherit currentColor, so colour them with CSS.
 */
function icon(string $name, string $class = 'tabler-icon'): string
{
    $paths = [
        'phone' => '<path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -15 -15a2 2 0 0 1 2 -2" />',
        'map-pin' => '<path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /><path d="M17.657 16.657l-4.243 4.243a2 2 0 0 1 -2.827 0l-4.244 -4.243a8 8 0 1 1 11.314 0z" />',
        'mail' => '<path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10z" /><path d="M3 7l9 6l9 -6" />',
        'clock' => '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" /><path d="M12 7v5l3 3" />',
        'brand-facebook' => '<path d="M7 10v4h3v7h4v-7h3l1 -4h-4v-2a1 1 0 0 1 1 -1h3v-4h-3a5 5 0 0 0 -5 5v2h-3" />',
        'brand-instagram' => '<path d="M4 8a4 4 0 0 1 4 -4h8a4 4 0 0 1 4 4v8a4 4 0 0 1 -4 4h-8a4 4 0 0 1 -4 -4z" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /><path d="M16.5 7.5v.01" />',
        'car' => '<path d="M5 17a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M15 17a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M5 17h-2v-6l2 -5h9l4 5h1a2 2 0 0 1 2 2v4h-2m-4 0h-6m-6 -6h15m-6 0v-5" />',
        'route' => '<path d="M3 19a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M17 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M5 17v-4a4 4 0 0 1 4 -4h4a4 4 0 0 0 4 -4" />',
        'cash' => '<path d="M7 9m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z" /><path d="M14 14m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 9v-2a2 2 0 0 0 -2 -2h-10a2 2 0 0 0 -2 2v6a2 2 0 0 0 2 2h2" />',
        'food' => '<path d="M19 3v12h-5c-.023 -3.681 .184 -7.406 5 -12zm0 12v6h-1v-3m-10 -14v17m-3 -17v3a3 3 0 1 0 6 0v-3" />',
        'credit-card' => '<path d="M3 5m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z" /><path d="M3 10l18 0" /><path d="M7 15l.01 0" /><path d="M11 15l2 0" />',
    ];

    $body = $paths[$name] ?? '';

    return '<svg xmlns="http://www.w3.org/2000/svg" class="' . e($class) . '" width="24" height="24" '
        . 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
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
