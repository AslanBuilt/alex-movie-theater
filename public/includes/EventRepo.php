<?php
declare(strict_types=1);

/**
 * EventRepo — read access for events + senior_showings.
 *
 * Same graceful-fallback pattern as MovieRepo: every public method
 * returns a safe empty/null value on DB failure so pages can branch.
 */
final class EventRepo
{
    /**
     * Returns events with status in ('upcoming', 'tba'), ordered for
     * display on events.php.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getUpcoming(): array
    {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->query(
                "SELECT * FROM events
                 WHERE status IN ('upcoming', 'tba')
                 ORDER BY sort_order ASC, COALESCE(event_date, '9999-12-31') ASC, id ASC"
            );
            return $stmt ? $stmt->fetchAll() : [];
        } catch (\Throwable $e) {
            error_log('[EventRepo::getUpcoming] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Returns a single event by ID, or null.
     *
     * @return array<string, mixed>|null
     */
    public static function getById(int $id): ?array
    {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare('SELECT * FROM events WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            return $row === false ? null : $row;
        } catch (\Throwable $e) {
            error_log('[EventRepo::getById] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Admin listing — every event regardless of status.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAll(): array
    {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->query(
                'SELECT * FROM events ORDER BY sort_order ASC, id ASC'
            );
            return $stmt ? $stmt->fetchAll() : [];
        } catch (\Throwable $e) {
            error_log('[EventRepo::getAll] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Returns the next senior screening to display, or null when no row
     * is suitable / on DB failure.
     *
     * Prefers an 'upcoming' row with the soonest future date, then any
     * 'upcoming' without a date, then a 'tba' row.
     *
     * @return array<string, mixed>|null
     */
    public static function getNextSeniorShowing(): ?array
    {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->query(
                "SELECT * FROM senior_showings
                 WHERE status IN ('upcoming', 'tba')
                 ORDER BY
                     FIELD(status, 'upcoming', 'tba'),
                     COALESCE(showing_date, '9999-12-31') ASC,
                     id ASC
                 LIMIT 1"
            );
            if (!$stmt) {
                return null;
            }
            $row = $stmt->fetch();
            return $row === false ? null : $row;
        } catch (\Throwable $e) {
            error_log('[EventRepo::getNextSeniorShowing] ' . $e->getMessage());
            return null;
        }
    }
}
