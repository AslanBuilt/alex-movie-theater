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
                "INSERT INTO concessions (category, name, description, price, image_path, is_available, sort_order)
                 VALUES (:category, :name, :description, :price, :image_path, :is_available, :sort_order)"
            );
            $stmt->execute([
                ':category'     => $data['category']     ?? 'Other',
                ':name'         => $data['name']         ?? '',
                ':description'  => $data['description']  ?? '',
                ':price'        => (float)($data['price'] ?? 0),
                ':image_path'   => $data['image_path']   ?? '',
                ':is_available' => (int)($data['is_available'] ?? 1),
                ':sort_order'   => (int)($data['sort_order'] ?? 0),
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
                     price=:price, image_path=:image_path,
                     is_available=:is_available, sort_order=:sort_order
                 WHERE id=:id"
            );
            return $stmt->execute([
                ':category'     => $data['category']     ?? 'Other',
                ':name'         => $data['name']         ?? '',
                ':description'  => $data['description']  ?? '',
                ':price'        => (float)($data['price'] ?? 0),
                ':image_path'   => $data['image_path']   ?? '',
                ':is_available' => (int)($data['is_available'] ?? 1),
                ':sort_order'   => (int)($data['sort_order'] ?? 0),
                ':id'           => $id,
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

    public function saveOrder(array $data): bool
    {
        if ($this->db === null) return false;
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO concession_orders
                    (order_number, customer_name, customer_email, customer_phone, show_info, items_json, total_amount)
                 VALUES
                    (:order_number, :customer_name, :customer_email, :customer_phone, :show_info, :items_json, :total_amount)"
            );
            return $stmt->execute([
                ':order_number'   => $data['order_number']   ?? '',
                ':customer_name'  => $data['customer_name']  ?? '',
                ':customer_email' => $data['customer_email'] ?? '',
                ':customer_phone' => $data['customer_phone'] ?? '',
                ':show_info'      => $data['show_info']      ?? '',
                ':items_json'     => $data['items_json']     ?? '[]',
                ':total_amount'   => (float)($data['total_amount'] ?? 0),
            ]);
        } catch (Throwable $e) {
            error_log('ConcessionRepo::saveOrder: ' . $e->getMessage());
            return false;
        }
    }

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
}
