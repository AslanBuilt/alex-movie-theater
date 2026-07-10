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

/**
 * Resolve a stored poster_path to a displayable <img src>. Admin can paste an
 * absolute image URL (e.g. via the "Find Poster on Google" helper) instead of
 * uploading a file — those must be returned untouched. Prefixing an absolute
 * URL with 'assets/' produces a path that 404s, which renders as a solid black
 * box (the poster wrapper's dark placeholder background showing through).
 */
function posterUrl(?string $path): string
{
    $p = trim((string)$path);
    if ($p === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#i', $p) === 1) {
        return $p;
    }
    return asset(assetRel($p));
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

/** Normalize any ticket-age input to 'Adult' or 'Child' — anything else defaults to Adult. */
function normalizeTicketAge(?string $age): string
{
    return $age === 'Child' ? 'Child' : 'Adult';
}

/** Server-side ticket price for an age, sourced from the config constants — the single source of truth. */
function ticketPrice(?string $age): float
{
    return normalizeTicketAge($age) === 'Child' ? TICKET_PRICE_CHILD : TICKET_PRICE_ADULT;
}

/**
 * Relative "time ago" string for a MySQL DATETIME string (e.g. "just now",
 * "12 minutes ago", "3 hours ago"). Falls back to an absolute date once
 * older than a week — "52 weeks ago" is less useful than a real date at
 * that distance. Returns '—' for an unparsable value rather than throwing,
 * since this is purely a display helper used inline in admin tables.
 */
function timeAgo(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '—';
    }

    $diff = time() - $ts;
    if ($diff < 0) {
        $diff = 0;
    }

    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        $mins = (int)floor($diff / 60);
        return $mins . ' minute' . ($mins === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $hours = (int)floor($diff / 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 604800) {
        $days = (int)floor($diff / 86400);
        return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    }
    return date('M j, Y', $ts);
}

/**
 * One-line item summary for admin list views, e.g. "2 tickets + 3 items" —
 * tickets counted separately from everything else (concessions) so the
 * summary reads at a glance without opening the full line-item breakdown.
 * Takes the same item rows TransactionRepo::getItems()/getItemsForTransactions()
 * return (each with 'item_type' and 'quantity').
 *
 * @param array<int, array<string, mixed>> $items
 */
function summarizeLineItems(array $items): string
{
    $ticketQty = 0;
    $otherQty  = 0;
    foreach ($items as $li) {
        $qty = (int)($li['quantity'] ?? 0);
        if (($li['item_type'] ?? '') === 'ticket') {
            $ticketQty += $qty;
        } else {
            $otherQty += $qty;
        }
    }

    $parts = [];
    if ($ticketQty > 0) {
        $parts[] = $ticketQty . ' ticket' . ($ticketQty === 1 ? '' : 's');
    }
    if ($otherQty > 0) {
        $parts[] = $otherQty . ' item' . ($otherQty === 1 ? '' : 's');
    }
    return $parts ? implode(' + ', $parts) : 'No items';
}
