<?php
declare(strict_types=1);

/**
 * Database — PDO singleton with graceful failure semantics.
 *
 * Loads credentials from the file at DB_CONFIG_PATH (defined in
 * config/config.php). The connection is established lazily on the first
 * call to getInstance().
 *
 * Pages that want to render with-or-without the DB should branch on
 * Database::isAvailable() (which never throws) or wrap calls in tryDb().
 */
final class Database
{
    private static ?PDO $instance = null;

    /**
     * Returns the shared PDO instance, building it on first call.
     *
     * Throws RuntimeException (with a generic message) on any failure —
     * the original PDOException is logged via error_log but never bubbled
     * up to the page, to avoid leaking credentials or host details.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        if (!defined('DB_CONFIG_PATH') || !is_file(DB_CONFIG_PATH)) {
            error_log('[Database] Config file missing at ' . (defined('DB_CONFIG_PATH') ? DB_CONFIG_PATH : '(undefined)'));
            throw new RuntimeException('Database configuration unavailable');
        }

        /** @var array<string, mixed> $config */
        $config = require DB_CONFIG_PATH;

        if (!is_array($config) || empty($config['host']) || empty($config['database'])) {
            error_log('[Database] Config file did not return a valid config array');
            throw new RuntimeException('Database configuration invalid');
        }

        $charset = $config['charset'] ?? 'utf8mb4';
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['database'],
            $charset
        );

        $options = $config['options'] ?? [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            self::$instance = new PDO(
                $dsn,
                (string) ($config['username'] ?? ''),
                (string) ($config['password'] ?? ''),
                $options
            );
        } catch (PDOException $e) {
            error_log('[Database] Connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed');
        }

        return self::$instance;
    }

    /**
     * Returns true if a PDO connection can be established right now.
     * Catches everything — safe to call from page templates.
     */
    public static function isAvailable(): bool
    {
        if (!defined('DB_CONFIG_PATH') || !is_file(DB_CONFIG_PATH)) {
            return false;
        }

        try {
            self::getInstance();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // Singleton — no construction / cloning.
    private function __construct() {}
    private function __clone() {}
}
