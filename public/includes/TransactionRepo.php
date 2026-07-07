<?php
declare(strict_types=1);

final class TransactionRepo
{
    /**
     * Insert a new transaction and return its ID, or 0 on failure.
     */
    public static function create(array $data): int
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(
                'INSERT INTO transactions
                    (transaction_ref, type, source_channel, total_amount, payment_status, payment_method, customer_name, customer_email)
                 VALUES
                    (:ref, :type, :channel, :total, :status, :method, :name, :email)'
            );
            $stmt->execute([
                ':ref'     => $data['transaction_ref'],
                ':type'    => $data['type'],
                ':channel' => $data['source_channel'] ?? 'website',
                ':total'   => (float)$data['total_amount'],
                ':status'  => $data['payment_status'] ?? 'pending',
                ':method'  => $data['payment_method'] ?? 'mock',
                ':name'    => $data['customer_name']  ?? null,
                ':email'   => $data['customer_email'] ?? null,
            ]);
            return (int)$pdo->lastInsertId();
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::create] ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Add a line item to an existing transaction.
     */
    public static function addItem(int $transactionId, array $item): bool
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(
                'INSERT INTO transaction_items
                    (transaction_id, item_type, item_id, item_name, quantity, unit_price, selected_option, subtotal)
                 VALUES
                    (:txn_id, :type, :item_id, :name, :qty, :price, :option, :sub)'
            );
            return $stmt->execute([
                ':txn_id' => $transactionId,
                ':type'   => $item['item_type'],
                ':item_id'=> (int)$item['item_id'],
                ':name'   => $item['item_name'],
                ':qty'    => (int)$item['quantity'],
                ':price'  => (float)$item['unit_price'],
                ':option' => $item['selected_option'] ?? null,
                ':sub'    => (float)$item['subtotal'],
            ]);
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::addItem] ' . $e->getMessage());
            return false;
        }
    }

    public static function updateStatus(int $id, string $status, string $gatewayRef = ''): bool
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(
                'UPDATE transactions SET payment_status = :status, payment_method = :method WHERE id = :id'
            );
            return $stmt->execute([
                ':status' => $status,
                ':method' => $gatewayRef !== '' ? $gatewayRef : 'mock',
                ':id'     => $id,
            ]);
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::updateStatus] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark a transaction as voided. Unlike updateStatus(), this does NOT touch
     * payment_method, so the original gateway/method record is preserved.
     */
    public static function voidTransaction(int $id): bool
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare("UPDATE transactions SET payment_status = 'voided' WHERE id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::voidTransaction] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Transition a transaction to a new status ONLY if it is currently pending.
     * Returns true if this call performed the transition (rowCount === 1), false
     * if it was already moved on — lets the webhook run side effects exactly once.
     */
    public static function transitionFromPending(int $id, string $newStatus): bool
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(
                "UPDATE transactions SET payment_status = :new WHERE id = :id AND payment_status = 'pending'"
            );
            $stmt->execute([':new' => $newStatus, ':id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::transitionFromPending] ' . $e->getMessage());
            return false;
        }
    }

    /** Set customer name/email on a still-pending transaction, found by ref. */
    public static function setCustomerByRef(string $ref, ?string $name, ?string $email): bool
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(
                "UPDATE transactions SET customer_name = :name, customer_email = :email
                 WHERE transaction_ref = :ref AND payment_status = 'pending'"
            );
            return $stmt->execute([
                ':name'  => ($name  !== null && $name  !== '') ? $name  : null,
                ':email' => ($email !== null && $email !== '') ? $email : null,
                ':ref'   => $ref,
            ]);
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::setCustomerByRef] ' . $e->getMessage());
            return false;
        }
    }

    /** Attach a Stripe PaymentIntent id to a transaction. */
    public static function setStripePaymentIntent(int $id, string $paymentIntentId): bool
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare('UPDATE transactions SET stripe_payment_intent_id = :pi WHERE id = :id');
            return $stmt->execute([':pi' => $paymentIntentId, ':id' => $id]);
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::setStripePaymentIntent] ' . $e->getMessage());
            return false;
        }
    }

    public static function getByPaymentIntent(string $paymentIntentId): ?array
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare('SELECT * FROM transactions WHERE stripe_payment_intent_id = :pi LIMIT 1');
            $stmt->execute([':pi' => $paymentIntentId]);
            $row = $stmt->fetch();
            if (!$row) return null;
            $row['items'] = self::getItems((int)$row['id']);
            return $row;
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getByPaymentIntent] ' . $e->getMessage());
            return null;
        }
    }

    public static function getByRef(string $ref): ?array
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare('SELECT * FROM transactions WHERE transaction_ref = :ref LIMIT 1');
            $stmt->execute([':ref' => $ref]);
            $row = $stmt->fetch();
            if (!$row) return null;
            $row['items'] = self::getItems((int)$row['id']);
            return $row;
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getByRef] ' . $e->getMessage());
            return null;
        }
    }

    public static function getById(int $id): ?array
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare('SELECT * FROM transactions WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if (!$row) return null;
            $row['items'] = self::getItems($id);
            return $row;
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getById] ' . $e->getMessage());
            return null;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function getItems(int $transactionId): array
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare('SELECT * FROM transaction_items WHERE transaction_id = :id ORDER BY id ASC');
            $stmt->execute([':id' => $transactionId]);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getItems] ' . $e->getMessage());
            return [];
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function getRecent(int $limit = 50): array
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(
                'SELECT * FROM transactions ORDER BY created_at DESC LIMIT :lim'
            );
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getRecent] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Sales summary for tonight, today, yesterday, this week, this month.
     * Returns keyed array of totals and counts.
     */
    public static function getSalesReport(): array
    {
        $report = [
            'tonight'   => ['total' => 0.0, 'count' => 0],
            'today'     => ['total' => 0.0, 'count' => 0],
            'yesterday' => ['total' => 0.0, 'count' => 0],
            'week'      => ['total' => 0.0, 'count' => 0],
            'month'     => ['total' => 0.0, 'count' => 0],
        ];
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->query(
                "SELECT
                    SUM(CASE WHEN DATE(created_at) = CURDATE() AND HOUR(created_at) >= 17 THEN total_amount ELSE 0 END) AS tonight_total,
                    COUNT(CASE WHEN DATE(created_at) = CURDATE() AND HOUR(created_at) >= 17 THEN 1 END) AS tonight_count,
                    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN total_amount ELSE 0 END) AS today_total,
                    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) AS today_count,
                    SUM(CASE WHEN DATE(created_at) = CURDATE() - INTERVAL 1 DAY THEN total_amount ELSE 0 END) AS yesterday_total,
                    COUNT(CASE WHEN DATE(created_at) = CURDATE() - INTERVAL 1 DAY THEN 1 END) AS yesterday_count,
                    SUM(CASE WHEN YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1) THEN total_amount ELSE 0 END) AS week_total,
                    COUNT(CASE WHEN YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1) THEN 1 END) AS week_count,
                    SUM(CASE WHEN YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) THEN total_amount ELSE 0 END) AS month_total,
                    COUNT(CASE WHEN YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) THEN 1 END) AS month_count
                 FROM transactions WHERE payment_status = 'paid'"
            );
            $row = $stmt->fetch();
            if ($row) {
                $report['tonight']   = ['total' => (float)($row['tonight_total']   ?? 0), 'count' => (int)($row['tonight_count']   ?? 0)];
                $report['today']     = ['total' => (float)($row['today_total']     ?? 0), 'count' => (int)($row['today_count']     ?? 0)];
                $report['yesterday'] = ['total' => (float)($row['yesterday_total'] ?? 0), 'count' => (int)($row['yesterday_count'] ?? 0)];
                $report['week']      = ['total' => (float)($row['week_total']      ?? 0), 'count' => (int)($row['week_count']      ?? 0)];
                $report['month']     = ['total' => (float)($row['month_total']     ?? 0), 'count' => (int)($row['month_count']     ?? 0)];
            }
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getSalesReport] ' . $e->getMessage());
        }
        return $report;
    }

    /**
     * Top-selling items by quantity. type = 'ticket' | 'concession' | 'all'.
     * @return array<int, array<string, mixed>>
     */
    public static function getTopItems(string $type = 'all', int $limit = 5): array
    {
        try {
            $pdo   = Database::getInstance();
            $where = $type !== 'all' ? "AND ti.item_type = :type" : '';
            $sql   = "SELECT ti.item_name, ti.item_type,
                             SUM(ti.quantity) AS total_qty,
                             SUM(ti.subtotal) AS total_revenue
                      FROM transaction_items ti
                      JOIN transactions t ON t.id = ti.transaction_id
                      WHERE t.payment_status = 'paid' $where
                      GROUP BY ti.item_name, ti.item_type
                      ORDER BY total_qty DESC
                      LIMIT :lim";
            $stmt = $pdo->prepare($sql);
            if ($type !== 'all') {
                $stmt->bindValue(':type', $type);
            }
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getTopItems] ' . $e->getMessage());
            return [];
        }
    }

    /** Revenue split by transaction type (ticket / concession / combo). */
    public static function getRevenueByType(): array
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->query(
                "SELECT type, SUM(total_amount) AS revenue, COUNT(*) AS cnt
                 FROM transactions WHERE payment_status = 'paid'
                 GROUP BY type"
            );
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getRevenueByType] ' . $e->getMessage());
            return [];
        }
    }

    /** Revenue per day for the current calendar week (Mon-start). Sparse — only days with sales. */
    public static function getRevenueByDayThisWeek(): array
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->query(
                "SELECT DATE(created_at) AS day, SUM(total_amount) AS revenue
                 FROM transactions
                 WHERE payment_status = 'paid' AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)
                 GROUP BY DATE(created_at)
                 ORDER BY day ASC"
            );
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getRevenueByDayThisWeek] ' . $e->getMessage());
            return [];
        }
    }

    /** Revenue per day for the current calendar month. Sparse — only days with sales. */
    public static function getRevenueByDayThisMonth(): array
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->query(
                "SELECT DATE(created_at) AS day, SUM(total_amount) AS revenue
                 FROM transactions
                 WHERE payment_status = 'paid'
                   AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())
                 GROUP BY DATE(created_at)
                 ORDER BY day ASC"
            );
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getRevenueByDayThisMonth] ' . $e->getMessage());
            return [];
        }
    }

    /** Paginated list for admin. */
    public static function getPaginated(int $page = 1, int $perPage = 25, string $status = ''): array
    {
        try {
            $pdo    = Database::getInstance();
            $offset = ($page - 1) * $perPage;
            $where  = $status !== '' ? 'WHERE payment_status = :status' : '';
            $stmt   = $pdo->prepare(
                "SELECT * FROM transactions $where ORDER BY created_at DESC LIMIT :lim OFFSET :off"
            );
            if ($status !== '') $stmt->bindValue(':status', $status);
            $stmt->bindValue(':lim', $perPage, \PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getPaginated] ' . $e->getMessage());
            return [];
        }
    }

    public static function countAll(string $status = ''): int
    {
        try {
            $pdo   = Database::getInstance();
            $where = $status !== '' ? 'WHERE payment_status = :status' : '';
            $stmt  = $pdo->prepare("SELECT COUNT(*) FROM transactions $where");
            if ($status !== '') $stmt->bindValue(':status', $status);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::countAll] ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check-in summary (total tickets / checked in / last check-in time) for a
     * batch of transaction ids, keyed by id. Transactions with no ticket_tokens
     * rows (concession-only) come back with total_tickets = 0.
     *
     * @param array<int,int> $transactionIds
     * @return array<int,array<string,mixed>>
     */
    public static function getCheckinSummaries(array $transactionIds): array
    {
        $transactionIds = array_values(array_unique(array_map('intval', $transactionIds)));
        if (!$transactionIds) return [];
        try {
            $pdo          = Database::getInstance();
            $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
            $stmt         = $pdo->prepare(
                "SELECT t.id,
                        COUNT(tk.token_id) AS total_tickets,
                        SUM(CASE WHEN tk.token_status = 'used' THEN 1 ELSE 0 END) AS checked_in,
                        MAX(tk.checked_in_at) AS last_checked_in_at
                 FROM transactions t
                 LEFT JOIN ticket_tokens tk ON tk.transaction_id = t.id
                 WHERE t.id IN ($placeholders)
                 GROUP BY t.id"
            );
            $stmt->execute($transactionIds);
            $out = [];
            foreach ($stmt->fetchAll() as $row) {
                $out[(int)$row['id']] = $row;
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getCheckinSummaries] ' . $e->getMessage());
            return [];
        }
    }

    /** Per-ticket check-in detail for one transaction (admin detail view). */
    public static function getTicketTokens(int $transactionId): array
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(
                "SELECT tt.ticket_token, tt.token_status, tt.checked_in_at, tt.checked_in_terminal, m.title AS movie_title
                 FROM ticket_tokens tt
                 LEFT JOIN movies m ON m.id = tt.movie_id
                 WHERE tt.transaction_id = :id
                 ORDER BY tt.token_id ASC"
            );
            $stmt->execute([':id' => $transactionId]);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getTicketTokens] ' . $e->getMessage());
            return [];
        }
    }
}
