<?php
declare(strict_types=1);

class ConcessionRepo
{
    private ?PDO $db;

    public function __construct(?PDO $db)
    {
        $this->db = $db;
    }

    public function getAvailable(): array
    {
        if ($this->db === null) return [];
        try {
            $stmt = $this->db->query(
                "SELECT * FROM concessions WHERE is_available = 1 ORDER BY category, sort_order, name"
            );
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            error_log('ConcessionRepo::getAvailable: ' . $e->getMessage());
            return [];
        }
    }

    public function getAll(): array
    {
        if ($this->db === null) return [];
        try {
            $stmt = $this->db->query(
                "SELECT * FROM concessions ORDER BY category, sort_order, name"
            );
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            error_log('ConcessionRepo::getAll: ' . $e->getMessage());
            return [];
        }
    }

    public function getById(int $id): ?array
    {
        if ($this->db === null) return null;
        try {
            $stmt = $this->db->prepare("SELECT * FROM concessions WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (Throwable $e) {
            error_log('ConcessionRepo::getById: ' . $e->getMessage());
            return null;
        }
    }

    public function create(array $data): int
    {
        if ($this->db === null) return 0;
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO concessions (category, name, description, price, cost, reorder_point, stock_quantity, image_path, is_available, sort_order)
                 VALUES (:category, :name, :description, :price, :cost, :reorder, :stock, :image_path, :is_available, :sort_order)"
            );
            $stmt->execute([
                ':category'    => $data['category']      ?? 'Other',
                ':name'        => $data['name']          ?? '',
                ':description' => $data['description']   ?? '',
                ':price'       => (float)($data['price'] ?? 0),
                ':cost'        => isset($data['cost']) && $data['cost'] !== '' ? (float)$data['cost'] : null,
                ':reorder'     => isset($data['reorder_point']) && $data['reorder_point'] !== '' ? (int)$data['reorder_point'] : null,
                ':stock'       => (int)($data['stock_quantity'] ?? 0),
                ':image_path'  => $data['image_path']    ?? '',
                ':is_available'=> (int)($data['is_available'] ?? 1),
                ':sort_order'  => (int)($data['sort_order'] ?? 0),
            ]);
            return (int)$this->db->lastInsertId();
        } catch (Throwable $e) {
            error_log('ConcessionRepo::create: ' . $e->getMessage());
            return 0;
        }
    }

    public function update(int $id, array $data): bool
    {
        if ($this->db === null) return false;
        try {
            $stmt = $this->db->prepare(
                "UPDATE concessions
                 SET category=:category, name=:name, description=:description,
                     price=:price, cost=:cost, reorder_point=:reorder, stock_quantity=:stock,
                     image_path=:image_path, is_available=:is_available, sort_order=:sort_order
                 WHERE id=:id"
            );
            return $stmt->execute([
                ':category'    => $data['category']     ?? 'Other',
                ':name'        => $data['name']         ?? '',
                ':description' => $data['description']  ?? '',
                ':price'       => (float)($data['price'] ?? 0),
                ':cost'        => isset($data['cost']) && $data['cost'] !== '' ? (float)$data['cost'] : null,
                ':reorder'     => isset($data['reorder_point']) && $data['reorder_point'] !== '' ? (int)$data['reorder_point'] : null,
                ':stock'       => (int)($data['stock_quantity'] ?? 0),
                ':image_path'  => $data['image_path']   ?? '',
                ':is_available'=> (int)($data['is_available'] ?? 1),
                ':sort_order'  => (int)($data['sort_order'] ?? 0),
                ':id'          => $id,
            ]);
        } catch (Throwable $e) {
            error_log('ConcessionRepo::update: ' . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        if ($this->db === null) return false;
        try {
            $stmt = $this->db->prepare("DELETE FROM concessions WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Throwable $e) {
            error_log('ConcessionRepo::delete: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * NOTE: the legacy pay-at-theatre pre-order writer (saveOrder) was removed
     * when that flow was consolidated into cart → checkout → Stripe. The two
     * methods below remain read/maintenance-only so admin can still view and
     * close out historical `concession_orders` rows.
     */
    public function getOrders(int $limit = 50): array
    {
        if ($this->db === null) return [];
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM concession_orders ORDER BY created_at DESC LIMIT ?"
            );
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            error_log('ConcessionRepo::getOrders: ' . $e->getMessage());
            return [];
        }
    }

    public function updateOrderStatus(int $id, string $status): bool
    {
        if ($this->db === null) return false;
        $allowed = ['pending', 'ready', 'picked_up', 'cancelled'];
        if (!in_array($status, $allowed, true)) return false;
        try {
            $stmt = $this->db->prepare("UPDATE concession_orders SET status=? WHERE id=?");
            return $stmt->execute([$status, $id]);
        } catch (Throwable $e) {
            error_log('ConcessionRepo::updateOrderStatus: ' . $e->getMessage());
            return false;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function getOptions(int $concessionId): array
    {
        if ($this->db === null) return [];
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM concession_options WHERE concession_id = ? ORDER BY sort_order ASC, id ASC"
            );
            $stmt->execute([$concessionId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            error_log('ConcessionRepo::getOptions: ' . $e->getMessage());
            return [];
        }
    }

    public function addOption(int $concessionId, string $label, int $sortOrder = 0): int
    {
        if ($this->db === null) return 0;
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO concession_options (concession_id, option_label, is_available, sort_order)
                 VALUES (?, ?, 1, ?)"
            );
            $stmt->execute([$concessionId, $label, $sortOrder]);
            return (int)$this->db->lastInsertId();
        } catch (Throwable $e) {
            error_log('ConcessionRepo::addOption: ' . $e->getMessage());
            return 0;
        }
    }

    public function deleteOption(int $optionId): bool
    {
        if ($this->db === null) return false;
        try {
            $stmt = $this->db->prepare("DELETE FROM concession_options WHERE id = ?");
            return $stmt->execute([$optionId]);
        } catch (Throwable $e) {
            error_log('ConcessionRepo::deleteOption: ' . $e->getMessage());
            return false;
        }
    }

    /** Decrement stock by qty atomically. Returns false if insufficient stock. */
    public function decrementStock(int $id, int $qty): bool
    {
        if ($this->db === null) return false;
        try {
            $stmt = $this->db->prepare(
                "UPDATE concessions SET stock_quantity = stock_quantity - ?
                 WHERE id = ? AND stock_quantity >= ?"
            );
            $stmt->execute([$qty, $id, $qty]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('ConcessionRepo::decrementStock: ' . $e->getMessage());
            return false;
        }
    }

    public function getStockQuantity(int $id): int
    {
        if ($this->db === null) return 0;
        try {
            $stmt = $this->db->prepare("SELECT stock_quantity FROM concessions WHERE id = ?");
            $stmt->execute([$id]);
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            error_log('ConcessionRepo::getStockQuantity: ' . $e->getMessage());
            return 0;
        }
    }
}
