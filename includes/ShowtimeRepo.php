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
}
