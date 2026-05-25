<?php
/**
 * Alex Movie Theatre — Database Configuration EXAMPLE
 * ----------------------------------------------------------------------------
 * Copy this file to `config/database.php` and fill in the cPanel-issued
 * MySQL credentials for the production database. Do NOT commit
 * `config/database.php` (see .gitignore).
 *
 * The site is designed to degrade gracefully when this file is missing or
 * the connection fails — the public pages fall back to their static HTML.
 */

declare(strict_types=1);

return [
    'host'     => 'localhost',
    'database' => 'your_database_name',
    'username' => 'your_database_user',
    'password' => 'your_database_password',
    'charset'  => 'utf8mb4',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];
