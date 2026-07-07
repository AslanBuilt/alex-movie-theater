<?php
declare(strict_types=1);

define('ENVIRONMENT', 'production');

if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/php-errors.log');
}

define('SITE_NAME', 'The Alex');
define('SITE_URL', 'https://parityrfp.com/cs/alex-movie-theater/');
define('SITE_PHONE', '765-620-9093');
define('SITE_ADDRESS', '407 N. Harrison Street, Alexandria, IN 46001');
define('SITE_EMAIL', 'info@alexmovietheatre.com');

define('TICKETS_URL', SITE_URL . 'tickets.php');
define('FACEBOOK_URL', 'https://www.facebook.com/TheAlexandriaTheatre');
define('INSTAGRAM_URL', 'https://www.instagram.com/the.alextheatre');

define('GA_MEASUREMENT_ID', 'G-SW2FC3JLE8');  // GA4 stream "Alex Movie Theater" (stream ID 15167575038)
define('FB_PIXEL_ID',        '');  // Deferred — set to numeric pixel ID from Meta Events Manager when running Meta ads

define('ADMIN_SESSION_TTL', 28800); // 8 hours of inactivity before the admin session expires

define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('TEMPLATES_PATH', ROOT_PATH . '/templates');
define('DB_CONFIG_PATH', ROOT_PATH . '/config/database.php');

date_default_timezone_set('America/Indiana/Indianapolis');

require_once INCLUDES_PATH . '/helpers.php';
