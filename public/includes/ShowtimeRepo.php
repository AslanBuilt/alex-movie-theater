<?php
declare(strict_types=1);

/**
 * ShowtimeRepo — CRUD for the showtimes table.
 *
 * Used by the admin panel. Read-side errors return [] / false / 0 and
 * are logged; write-side errors are also caught and logged so callers
 * can fall back without exposing internals.
 */
final class ShowtimeRepo
{
    /**
     * Returns all showtimes for a movie, ordered for display.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getByMovieId(int $movieId): array
    {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare(
                'SELECT * FROM showtimes
                 WHERE movie_id = :movie_id
                 ORDER BY sort_order ASC, id ASC'
            );
            $stmt->execute([':movie_id' => $movieId]);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('[ShowtimeRepo::getByMovieId] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Inserts a new showtime and returns the new ID, or 0 on failure.
     */
    public static function create(
        int $movieId,
        string $label,
        string $times,
        ?string $date,
        int $sortOrder
    ): int {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare(
                'INSERT INTO showtimes (movie_id, label, times, showtime_date, sort_order)
                 VALUES (:movie_id, :label, :times, :showtime_date, :sort_order)'
            );
            $stmt->execute([
                ':movie_id'      => $movieId,
                ':label'         => $label,
                ':times'         => $times,
                ':showtime_date' => $date,
                ':sort_order'    => $sortOrder,
            ]);
            return (int) $pdo->lastInsertId();
        } catch (\Throwable $e) {
            error_log('[ShowtimeRepo::create] ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Updates an existing showtime. Returns true on success.
     */
    public static function update(
        int $id,
        string $label,
        string $times,
        ?string $date,
        int $sortOrder
    ): bool {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare(
                'UPDATE showtimes
                 SET label = :label,
                     times = :times,
                     showtime_date = :showtime_date,
                     sort_order = :sort_order
                 WHERE id = :id'
            );
            return $stmt->execute([
                ':id'            => $id,
                ':label'         => $label,
                ':times'         => $times,
                ':showtime_date' => $date,
                ':sort_order'    => $sortOrder,
            ]);
        } catch (\Throwable $e) {
            error_log('[ShowtimeRepo::update] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a showtime. Returns true on success.
     */
    public static function delete(int $id): bool
    {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare('DELETE FROM showtimes WHERE id = :id');
            return $stmt->execute([':id' => $id]);
        } catch (\Throwable $e) {
            error_log('[ShowtimeRepo::delete] ' . $e->getMessage());
            return false;
        }
    }

    public static function getById(int $id): ?array
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare('SELECT * FROM showtimes WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\Throwable $e) {
            error_log('[ShowtimeRepo::getById] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Decrement tickets_sold by qty. Returns false if insufficient availability.
     */
    public static function decrementTickets(int $id, int $qty): bool
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(
                'UPDATE showtimes
                 SET tickets_sold = tickets_sold + :qty
                 WHERE id = :id
                   AND is_active = 1
                   AND (available_tickets - tickets_sold) >= :qty2'
            );
            $stmt->execute([':qty' => $qty, ':id' => $id, ':qty2' => $qty]);
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            error_log('[ShowtimeRepo::decrementTickets] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Restore tickets to a showtime (e.g. when voiding a transaction).
     * Lowers tickets_sold by qty, never below zero.
     */
    public static function restoreTickets(int $id, int $qty): bool
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(
                'UPDATE showtimes SET tickets_sold = GREATEST(0, tickets_sold - :qty) WHERE id = :id'
            );
            $stmt->execute([':qty' => $qty, ':id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            error_log('[ShowtimeRepo::restoreTickets] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a transactional showtime (date + time + capacity).
     */
    public static function createTransactional(
        int $movieId,
        string $date,
        string $time,
        int $availableTickets,
        int $sortOrder = 0,
        string $screen = 'large'
    ): int {
        try {
            $pdo  = Database::getInstance();
            $label = (new \DateTime($date))->format('D, M j') . ' ' . $time;
            $stmt  = $pdo->prepare(
                'INSERT INTO showtimes
                    (movie_id, label, times, showtime_date, showtime_time, available_tickets, tickets_sold, is_active, sort_order, screen)
                 VALUES
                    (:movie_id, :label, :times, :date, :time, :avail, 0, 1, :sort, :screen)'
            );
            $stmt->execute([
                ':movie_id' => $movieId,
                ':label'    => $label,
                ':times'    => $time,
                ':date'     => $date,
                ':time'     => $time,
                ':avail'    => $availableTickets,
                ':sort'     => $sortOrder,
                ':screen'   => $screen,
            ]);
            return (int)$pdo->lastInsertId();
        } catch (\Throwable $e) {
            error_log('[ShowtimeRepo::createTransactional] ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Update a transactional showtime.
     */
    public static function updateTransactional(
        int $id,
        string $date,
        string $time,
        int $availableTickets,
        bool $isActive,
        int $sortOrder,
        string $screen
    ): bool {
        try {
            $pdo   = Database::getInstance();
            $label = (new \DateTime($date))->format('D, M j') . ' ' . $time;
            $stmt  = $pdo->prepare(
                'UPDATE showtimes
                 SET label = :label, times = :times,
                     showtime_date = :date, showtime_time = :time,
                     available_tickets = :avail, is_active = :active,
                     sort_order = :sort, screen = :screen
                 WHERE id = :id'
            );
            return $stmt->execute([
                ':label'  => $label,
                ':times'  => $time,
                ':date'   => $date,
                ':time'   => $time,
                ':avail'  => $availableTickets,
                ':active' => $isActive ? 1 : 0,
                ':sort'   => $sortOrder,
                ':screen' => $screen,
                ':id'     => $id,
            ]);
        } catch (\Throwable $e) {
            error_log('[ShowtimeRepo::updateTransactional] ' . $e->getMessage());
            return false;
        }
    }
}
