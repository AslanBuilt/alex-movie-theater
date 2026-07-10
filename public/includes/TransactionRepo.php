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
     * Atomic per-calendar-day sequence, race-safe via MySQL upsert + LAST_INSERT_ID().
     * Works inside an existing transaction so a rolled-back sale does not consume
     * a daily order number.
     */
    public static function nextDailyOrderNumber(): int
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            "INSERT INTO daily_order_counters (order_date, next_number)
             VALUES (CURDATE(), LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE next_number = LAST_INSERT_ID(next_number + 1)"
        );
        $stmt->execute();
        return (int)$pdo->lastInsertId();
    }

    /**
     * Assign the next daily order number to an existing transaction record.
     */
    public static function assignDailyOrderNumber(int $id): int
    {
        $n    = self::nextDailyOrderNumber();
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE transactions SET daily_order_number = :n WHERE id = :id');
        $stmt->execute([':n' => $n, ':id' => $id]);
        return $n;
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

    /**
     * Revenue + order count for today / this week / this month / all-time,
     * in one query. Same CASE-WHEN-over-one-query style as getSalesReport().
     */
    public static function getSummaryStats(): array
    {
        $report = [
            'today'   => ['total' => 0.0, 'count' => 0],
            'week'    => ['total' => 0.0, 'count' => 0],
            'month'   => ['total' => 0.0, 'count' => 0],
            'allTime' => ['total' => 0.0, 'count' => 0],
        ];
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->query(
                "SELECT
                    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN total_amount ELSE 0 END) AS today_total,
                    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) AS today_count,
                    SUM(CASE WHEN YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1) THEN total_amount ELSE 0 END) AS week_total,
                    COUNT(CASE WHEN YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1) THEN 1 END) AS week_count,
                    SUM(CASE WHEN YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) THEN total_amount ELSE 0 END) AS month_total,
                    COUNT(CASE WHEN YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) THEN 1 END) AS month_count,
                    SUM(total_amount) AS all_total,
                    COUNT(*) AS all_count
                 FROM transactions WHERE payment_status = 'paid'"
            );
            $row = $stmt->fetch();
            if ($row) {
                $report['today']   = ['total' => (float)($row['today_total'] ?? 0), 'count' => (int)($row['today_count'] ?? 0)];
                $report['week']    = ['total' => (float)($row['week_total']  ?? 0), 'count' => (int)($row['week_count']  ?? 0)];
                $report['month']   = ['total' => (float)($row['month_total'] ?? 0), 'count' => (int)($row['month_count'] ?? 0)];
                $report['allTime'] = ['total' => (float)($row['all_total']   ?? 0), 'count' => (int)($row['all_count']   ?? 0)];
            }
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getSummaryStats] ' . $e->getMessage());
        }
        return $report;
    }

    /**
     * Revenue per day-of-week (Mon-Sun) for this week AND last week, zero-filled
     * so every day is present even with no sales. Two bound-date-range queries
     * (rather than YEARWEEK(...) - 1, which breaks across a year boundary —
     * e.g. week 1 minus 1 does not equal the prior ISO week) computed from
     * PHP DateTime, not user input, so no validation is needed on the bounds.
     *
     * @return array<int, array{day:string, this_week:float, last_week:float}>
     */
    public static function getRevenueByDayWithComparison(): array
    {
        $dayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $out = [];
        foreach ($dayLabels as $label) {
            $out[] = ['day' => $label, 'this_week' => 0.0, 'last_week' => 0.0];
        }
        try {
            $pdo = Database::getInstance();

            $todayDow   = (int)date('N'); // 1=Mon..7=Sun
            $thisMonday = (new DateTime('today'))->modify('-' . ($todayDow - 1) . ' days');
            $thisSunday = (clone $thisMonday)->modify('+6 days');
            $lastMonday = (clone $thisMonday)->modify('-7 days');
            $lastSunday = (clone $thisMonday)->modify('-1 days');

            $stmt = $pdo->prepare(
                "SELECT DATE(created_at) AS day, SUM(total_amount) AS revenue
                 FROM transactions
                 WHERE payment_status = 'paid' AND DATE(created_at) BETWEEN :start AND :end
                 GROUP BY DATE(created_at)"
            );

            $stmt->execute([':start' => $thisMonday->format('Y-m-d'), ':end' => $thisSunday->format('Y-m-d')]);
            $thisWeekByDate = [];
            foreach ($stmt->fetchAll() as $r) {
                $thisWeekByDate[(string)$r['day']] = (float)$r['revenue'];
            }

            $stmt->execute([':start' => $lastMonday->format('Y-m-d'), ':end' => $lastSunday->format('Y-m-d')]);
            $lastWeekByDate = [];
            foreach ($stmt->fetchAll() as $r) {
                $lastWeekByDate[(string)$r['day']] = (float)$r['revenue'];
            }

            for ($i = 0; $i < 7; $i++) {
                $dThis = (clone $thisMonday)->modify("+$i days")->format('Y-m-d');
                $dLast = (clone $lastMonday)->modify("+$i days")->format('Y-m-d');
                $out[$i]['this_week'] = round($thisWeekByDate[$dThis] ?? 0, 2);
                $out[$i]['last_week'] = round($lastWeekByDate[$dLast] ?? 0, 2);
            }
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getRevenueByDayWithComparison] ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * Revenue by transaction type (ticket/concession/combo), optionally bound
     * to a date range [$start, $end] (inclusive, 'Y-m-d'). Unlike
     * getRevenueByType(), always returns all three keys — even at $0 — so a
     * category with no sales in range can be shown greyed-out rather than
     * silently omitted.
     *
     * @return array<string, array{revenue:float, cnt:int}>
     */
    public static function getRevenueByTypeInRange(?string $start = null, ?string $end = null): array
    {
        $out = [
            'ticket'     => ['revenue' => 0.0, 'cnt' => 0],
            'concession' => ['revenue' => 0.0, 'cnt' => 0],
            'combo'      => ['revenue' => 0.0, 'cnt' => 0],
        ];
        try {
            $pdo    = Database::getInstance();
            $where  = "WHERE payment_status = 'paid'";
            $params = [];
            if ($start !== null && $end !== null) {
                $where .= ' AND created_at BETWEEN :start AND :end';
                $params[':start'] = $start . ' 00:00:00';
                $params[':end']   = $end . ' 23:59:59';
            }
            $stmt = $pdo->prepare(
                "SELECT
                    SUM(CASE WHEN type = 'ticket' THEN total_amount ELSE 0 END) AS ticket_revenue,
                    COUNT(CASE WHEN type = 'ticket' THEN 1 END) AS ticket_cnt,
                    SUM(CASE WHEN type = 'concession' THEN total_amount ELSE 0 END) AS concession_revenue,
                    COUNT(CASE WHEN type = 'concession' THEN 1 END) AS concession_cnt,
                    SUM(CASE WHEN type = 'combo' THEN total_amount ELSE 0 END) AS combo_revenue,
                    COUNT(CASE WHEN type = 'combo' THEN 1 END) AS combo_cnt
                 FROM transactions $where"
            );
            $stmt->execute($params);
            $row = $stmt->fetch();
            if ($row) {
                $out['ticket']     = ['revenue' => (float)($row['ticket_revenue']     ?? 0), 'cnt' => (int)($row['ticket_cnt']     ?? 0)];
                $out['concession'] = ['revenue' => (float)($row['concession_revenue'] ?? 0), 'cnt' => (int)($row['concession_cnt'] ?? 0)];
                $out['combo']      = ['revenue' => (float)($row['combo_revenue']      ?? 0), 'cnt' => (int)($row['combo_cnt']      ?? 0)];
            }
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getRevenueByTypeInRange] ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * Top-selling movies by tickets sold, with an Adult/Child split and the
     * movie's poster, optionally bound to a date range [$start, $end]
     * ('Y-m-d', inclusive). Extends getTopItems('ticket', ...): that method
     * groups by ti.item_name, which bakes in the showtime date/time, so the
     * same movie fragments across every showtime it played. This joins
     * ti.item_id (a showtime id for ticket rows — see checkout.php/
     * pos-checkout.php) through showtimes to movies and groups by movie, so
     * "top movies" really means top movies, not top showtimes.
     *
     * selected_option for ticket rows is 'Adult' or 'Child' (title case —
     * see helpers.php normalizeTicketAge() / checkout.php), not lowercase.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getTopMoviesWithAgeSplit(int $limit = 5, ?string $start = null, ?string $end = null): array
    {
        try {
            $pdo        = Database::getInstance();
            $dateFilter = '';
            $params     = [];
            if ($start !== null && $end !== null) {
                $dateFilter        = ' AND t.created_at BETWEEN :start AND :end';
                $params[':start']  = $start . ' 00:00:00';
                $params[':end']    = $end . ' 23:59:59';
            }
            $sql = "SELECT m.id AS movie_id, m.title AS item_name, m.poster_path,
                           SUM(ti.quantity) AS total_qty,
                           SUM(ti.subtotal) AS total_revenue,
                           SUM(CASE WHEN ti.selected_option = 'Adult' THEN ti.quantity ELSE 0 END) AS adult_count,
                           SUM(CASE WHEN ti.selected_option = 'Child' THEN ti.quantity ELSE 0 END) AS child_count
                    FROM transaction_items ti
                    JOIN transactions t ON t.id = ti.transaction_id
                    JOIN showtimes s ON s.id = ti.item_id
                    JOIN movies m ON m.id = s.movie_id
                    WHERE t.payment_status = 'paid' AND ti.item_type = 'ticket' $dateFilter
                    GROUP BY m.id, m.title, m.poster_path
                    ORDER BY total_qty DESC
                    LIMIT :lim";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getTopMoviesWithAgeSplit] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Top-selling concessions by units sold, with per-unit margin (price -
     * cost, null when cost isn't set), optionally bound to a date range
     * [$start, $end] ('Y-m-d', inclusive). Extends getTopItems('concession',
     * ...) with a join to concessions — item_id on a concession transaction
     * line is concessions.id (see checkout.php / pos-checkout.php), so the
     * join is a direct id match, not a name match.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getTopConcessionsWithMargin(int $limit = 5, ?string $start = null, ?string $end = null): array
    {
        try {
            $pdo        = Database::getInstance();
            $dateFilter = '';
            $params     = [];
            if ($start !== null && $end !== null) {
                $dateFilter       = ' AND t.created_at BETWEEN :start AND :end';
                $params[':start'] = $start . ' 00:00:00';
                $params[':end']   = $end . ' 23:59:59';
            }
            $sql = "SELECT c.id AS concession_id, c.name AS item_name, c.cost, c.price,
                           SUM(ti.quantity) AS total_qty,
                           SUM(ti.subtotal) AS total_revenue
                    FROM transaction_items ti
                    JOIN transactions t ON t.id = ti.transaction_id
                    JOIN concessions c ON c.id = ti.item_id
                    WHERE t.payment_status = 'paid' AND ti.item_type = 'concession' $dateFilter
                    GROUP BY c.id, c.name, c.cost, c.price
                    ORDER BY total_qty DESC
                    LIMIT :lim";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getTopConcessionsWithMargin] ' . $e->getMessage());
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

    /**
     * Per-ticket check-in detail for one transaction (admin detail view).
     * Includes transaction_item_id and the line item's selected_option
     * (Adult/Child) so the caller can group multiple tokens under the
     * single line item they were issued from (one ticket_tokens row per
     * physical ticket, but one transaction_items row per line — a
     * quantity-3 "Adult" line has 3 tokens sharing one transaction_item_id).
     */
    public static function getTicketTokens(int $transactionId): array
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(
                "SELECT tt.ticket_token, tt.token_status, tt.checked_in_at, tt.checked_in_terminal,
                        tt.transaction_item_id, ti.selected_option, m.title AS movie_title
                 FROM ticket_tokens tt
                 LEFT JOIN movies m ON m.id = tt.movie_id
                 LEFT JOIN transaction_items ti ON ti.id = tt.transaction_item_id
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

    /**
     * Data for the four summary cards atop admin/transactions.php: today's
     * order count (any status — a raw "how many orders came in today"
     * figure), today's revenue and this week's revenue (paid only, same
     * DATE(created_at) = CURDATE() / YEARWEEK(...,1) convention as
     * getSummaryStats()/occupancy.php), and how many transactions are
     * currently sitting in 'pending' (not date-bound — that's an actionable
     * "needs attention" count regardless of when the order was placed).
     * Single-query CASE-WHEN, same style as getSummaryStats()/getSalesReport().
     */
    public static function getDashboardSummary(): array
    {
        $out = [
            'today_orders'  => 0,
            'today_revenue' => 0.0,
            'week_revenue'  => 0.0,
            'pending_count' => 0,
        ];
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->query(
                "SELECT
                    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) AS today_orders,
                    SUM(CASE WHEN DATE(created_at) = CURDATE() AND payment_status = 'paid' THEN total_amount ELSE 0 END) AS today_revenue,
                    SUM(CASE WHEN YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) AND payment_status = 'paid' THEN total_amount ELSE 0 END) AS week_revenue,
                    COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) AS pending_count
                 FROM transactions"
            );
            $row = $stmt->fetch();
            if ($row) {
                $out['today_orders']  = (int)($row['today_orders'] ?? 0);
                $out['today_revenue'] = (float)($row['today_revenue'] ?? 0);
                $out['week_revenue']  = (float)($row['week_revenue'] ?? 0);
                $out['pending_count'] = (int)($row['pending_count'] ?? 0);
            }
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getDashboardSummary] ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * Line items for a batch of transactions, keyed by transaction_id. Batches
     * admin/transactions.php's list-page item summaries and expandable detail
     * panels into one query instead of one getItems() call per row — same
     * batching pattern as getCheckinSummaries().
     *
     * @param array<int,int> $transactionIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function getItemsForTransactions(array $transactionIds): array
    {
        $transactionIds = array_values(array_unique(array_map('intval', $transactionIds)));
        if (!$transactionIds) return [];
        try {
            $pdo          = Database::getInstance();
            $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
            $stmt         = $pdo->prepare(
                "SELECT * FROM transaction_items WHERE transaction_id IN ($placeholders) ORDER BY transaction_id ASC, id ASC"
            );
            $stmt->execute($transactionIds);
            $out = [];
            foreach ($stmt->fetchAll() as $row) {
                $out[(int)$row['transaction_id']][] = $row;
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('[TransactionRepo::getItemsForTransactions] ' . $e->getMessage());
            return [];
        }
    }
}
