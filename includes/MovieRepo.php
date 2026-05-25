<?php
declare(strict_types=1);

/**
 * MovieRepo — read-only access to movies + showtimes for the public site.
 *
 * Every public method catches Throwable and returns a safe empty value
 * (empty array, or null for getById) on failure, so the page templates
 * can branch on emptiness to fall back to static markup.
 */
final class MovieRepo
{
    /**
     * Returns all movies with status = 'now_showing', each augmented with
     * a 'showtimes' sub-array.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getNowShowing(): array
    {
        return self::fetchMoviesByStatus('now_showing');
    }

    /**
     * Returns all movies with status = 'coming_soon'. Showtimes are
     * included (likely empty) for shape consistency with getNowShowing().
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getComingSoon(): array
    {
        return self::fetchMoviesByStatus('coming_soon');
    }

    /**
     * Returns a single movie (any status) by ID with its showtimes, or
     * null if not found or on DB failure.
     *
     * @return array<string, mixed>|null
     */
    public static function getById(int $id): ?array
    {
        try {
            $pdo = Database::getInstance();

            $stmt = $pdo->prepare('SELECT * FROM movies WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $movie = $stmt->fetch();
            if (!$movie) {
                return null;
            }

            $movie['showtimes'] = self::fetchShowtimesForMovieIds([(int) $movie['id']])[(int) $movie['id']] ?? [];
            return $movie;
        } catch (\Throwable $e) {
            error_log('[MovieRepo::getById] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Admin listing — every movie regardless of status, with showtimes.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAll(): array
    {
        try {
            $pdo = Database::getInstance();

            $stmt = $pdo->query(
                'SELECT * FROM movies ORDER BY sort_order ASC, id ASC'
            );
            $movies = $stmt ? $stmt->fetchAll() : [];
            return self::attachShowtimes($movies);
        } catch (\Throwable $e) {
            error_log('[MovieRepo::getAll] ' . $e->getMessage());
            return [];
        }
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fetchMoviesByStatus(string $status): array
    {
        try {
            $pdo = Database::getInstance();

            $stmt = $pdo->prepare(
                'SELECT * FROM movies
                 WHERE status = :status
                 ORDER BY sort_order ASC, id ASC'
            );
            $stmt->execute([':status' => $status]);
            $movies = $stmt->fetchAll();

            return self::attachShowtimes($movies);
        } catch (\Throwable $e) {
            error_log('[MovieRepo::fetchMoviesByStatus] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Bulk-fetches showtimes for the provided movie list and grafts them
     * onto each row under the 'showtimes' key.
     *
     * @param array<int, array<string, mixed>> $movies
     * @return array<int, array<string, mixed>>
     */
    private static function attachShowtimes(array $movies): array
    {
        if (!$movies) {
            return [];
        }

        $ids = [];
        foreach ($movies as $m) {
            $ids[] = (int) $m['id'];
        }

        $showtimesByMovie = self::fetchShowtimesForMovieIds($ids);

        foreach ($movies as &$m) {
            $m['showtimes'] = $showtimesByMovie[(int) $m['id']] ?? [];
        }
        unset($m);

        return $movies;
    }

    /**
     * @param array<int, int> $movieIds
     * @return array<int, array<int, array<string, mixed>>> Keyed by movie_id.
     */
    private static function fetchShowtimesForMovieIds(array $movieIds): array
    {
        if (!$movieIds) {
            return [];
        }

        try {
            $pdo = Database::getInstance();

            $placeholders = implode(',', array_fill(0, count($movieIds), '?'));
            $sql = "SELECT * FROM showtimes
                    WHERE movie_id IN ($placeholders)
                    ORDER BY sort_order ASC, id ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($movieIds));

            $grouped = [];
            while ($row = $stmt->fetch()) {
                $mid = (int) $row['movie_id'];
                $grouped[$mid][] = $row;
            }
            return $grouped;
        } catch (\Throwable $e) {
            error_log('[MovieRepo::fetchShowtimesForMovieIds] ' . $e->getMessage());
            return [];
        }
    }
}
