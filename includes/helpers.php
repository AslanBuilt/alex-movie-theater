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
