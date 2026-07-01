<?php
declare(strict_types=1);

/**
 * TicketTokenRepo — issues one opaque QR check-in token per ticket unit sold
 * (any channel: website/Stripe, staff register). Tokens are pure random
 * bytes (bin2hex(random_bytes(32))) with no derivable meaning; ticket_tokens
 * rows are what /checkin.php and admin/occupancy.php read.
 */
final class TicketTokenRepo
{
    /**
     * Create tokens for every ticket line on a transaction. Idempotent — a
     * second call for the same transaction (e.g. a Stripe webhook replay,
     * which is already blocked upstream by transitionFromPending, but this
     * guard is cheap insurance) returns the existing tokens instead of
     * minting duplicates.
     *
     * @return array<int,array<string,mixed>> one row per ticket, richest
     *   shape needed by confirmation.php / OrderEmail (movie title, when, seq)
     */
    public static function generateForTransaction(int $transactionId): array
    {
        try {
            $pdo = Database::getInstance();

            $existing = self::getByTransaction($transactionId);
            if ($existing) {
                return $existing;
            }

            $items = $pdo->prepare(
                "SELECT ti.id AS transaction_item_id, ti.item_id AS showtime_id, ti.quantity,
                        s.movie_id, m.title AS movie_title, s.showtime_date, s.showtime_time, s.label
                 FROM transaction_items ti
                 JOIN showtimes s ON s.id = ti.item_id
                 LEFT JOIN movies m ON m.id = s.movie_id
                 WHERE ti.transaction_id = :id AND ti.item_type = 'ticket'"
            );
            $items->execute([':id' => $transactionId]);
            $rows = $items->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                return [];
            }

            $ins = $pdo->prepare(
                'INSERT INTO ticket_tokens
                    (transaction_id, transaction_item_id, movie_id, showtime_id, ticket_token)
                 VALUES (:txn, :item, :movie, :showtime, :token)'
            );

            // Number tickets per showtime (not per line item) so "Adult x2" and
            // "Child x1" on the same showing come out 1-3 of 3, matching how
            // getByTransaction() (and the confirmation page) count them.
            $totalByShowtime = [];
            foreach ($rows as $row) {
                $sid = (int)$row['showtime_id'];
                $totalByShowtime[$sid] = ($totalByShowtime[$sid] ?? 0) + (int)$row['quantity'];
            }
            $seqByShowtime = [];

            $created = [];
            foreach ($rows as $row) {
                $sid = (int)$row['showtime_id'];
                $qty = (int)$row['quantity'];
                for ($n = 0; $n < $qty; $n++) {
                    $token = bin2hex(random_bytes(32));
                    $ins->execute([
                        ':txn'      => $transactionId,
                        ':item'     => (int)$row['transaction_item_id'],
                        ':movie'    => (int)$row['movie_id'],
                        ':showtime' => $sid,
                        ':token'    => $token,
                    ]);
                    $seqByShowtime[$sid] = ($seqByShowtime[$sid] ?? 0) + 1;
                    $created[] = [
                        'ticket_token'   => $token,
                        'movie_title'    => (string)($row['movie_title'] ?? 'Movie'),
                        'when'           => self::formatWhen($row),
                        'seq'            => $seqByShowtime[$sid],
                        'seq_total'      => $totalByShowtime[$sid],
                        'token_status'   => 'valid',
                    ];
                }
            }
            return $created;
        } catch (\Throwable $e) {
            error_log('[TicketTokenRepo::generateForTransaction] ' . $e->getMessage());
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    public static function getByTransaction(int $transactionId): array
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(
                "SELECT tt.ticket_token, tt.token_status, tt.showtime_id, m.title AS movie_title,
                        s.showtime_date, s.showtime_time, s.label
                 FROM ticket_tokens tt
                 LEFT JOIN movies m ON m.id = tt.movie_id
                 LEFT JOIN showtimes s ON s.id = tt.showtime_id
                 WHERE tt.transaction_id = :id
                 ORDER BY tt.token_id ASC"
            );
            $stmt->execute([':id' => $transactionId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Numbered per showtime_id — same grouping generateForTransaction() uses,
            // so "Ticket X of Y" always matches between the confirmation page and email.
            $totalByShowtime = [];
            foreach ($rows as $row) {
                $sid = (int)$row['showtime_id'];
                $totalByShowtime[$sid] = ($totalByShowtime[$sid] ?? 0) + 1;
            }
            $seqByShowtime = [];
            $result = [];
            foreach ($rows as $row) {
                $sid = (int)$row['showtime_id'];
                $seqByShowtime[$sid] = ($seqByShowtime[$sid] ?? 0) + 1;
                $result[] = $row + [
                    'when'      => self::formatWhen($row),
                    'seq'       => $seqByShowtime[$sid],
                    'seq_total' => $totalByShowtime[$sid],
                ];
            }
            return $result;
        } catch (\Throwable $e) {
            error_log('[TicketTokenRepo::getByTransaction] ' . $e->getMessage());
            return [];
        }
    }

    /** Void every still-valid token on a transaction (used-tokens are left alone). */
    public static function voidForTransaction(int $transactionId): int
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(
                "UPDATE ticket_tokens SET token_status = 'voided'
                 WHERE transaction_id = :id AND token_status = 'valid'"
            );
            $stmt->execute([':id' => $transactionId]);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            error_log('[TicketTokenRepo::voidForTransaction] ' . $e->getMessage());
            return 0;
        }
    }

    private static function formatWhen(array $row): string
    {
        $when = '';
        if (!empty($row['showtime_date'])) {
            try {
                $when = (new DateTime((string)$row['showtime_date']))->format('D, M j');
            } catch (\Throwable $e) {
                $when = '';
            }
            if (!empty($row['showtime_time'])) {
                $when .= ' ' . date('g:i A', strtotime((string)$row['showtime_time']));
            }
        } elseif (!empty($row['label'])) {
            $when = (string)$row['label'];
        }
        return $when;
    }
}
