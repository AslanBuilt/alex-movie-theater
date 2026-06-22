<?php
declare(strict_types=1);

final class InventoryRepo
{
    /**
     * Log a stock change and update concessions.stock_quantity.
     * Returns false on DB failure.
     */
    public static function logChange(
        int $concessionId,
        string $changeType,
        int $qtyChange,
        int $newQty,
        string $source = 'website',
        ?string $note = null
    ): bool {
        try {
            $pdo = Database::getInstance();
            $pdo->beginTransaction();

            // Update stock on concession
            $upd = $pdo->prepare('UPDATE concessions SET stock_quantity = :qty WHERE id = :id');
            $upd->execute([':qty' => $newQty, ':id' => $concessionId]);

            // Write log entry
            $ins = $pdo->prepare(
                'INSERT INTO inventory_log (concession_id, change_type, qty_change, new_quantity, source, note)
                 VALUES (:cid, :type, :chg, :new, :src, :note)'
            );
            $ins->execute([
                ':cid'  => $concessionId,
                ':type' => $changeType,
                ':chg'  => $qtyChange,
                ':new'  => $newQty,
                ':src'  => $source,
                ':note' => $note,
            ]);

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            try { Database::getInstance()->rollBack(); } catch (\Throwable $_) {}
            error_log('[InventoryRepo::logChange] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Full inventory table for the reporting dashboard.
     * @return array<int, array<string, mixed>>
     */
    public static function getFullInventory(): array
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->query(
                'SELECT id, category, name, stock_quantity, cost, price, reorder_point, is_available
                 FROM concessions
                 ORDER BY category ASC, sort_order ASC, name ASC'
            );
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('[InventoryRepo::getFullInventory] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Items at or below their reorder point (where reorder_point is set).
     * @return array<int, array<string, mixed>>
     */
    public static function getLowStock(): array
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->query(
                'SELECT id, category, name, stock_quantity, reorder_point
                 FROM concessions
                 WHERE reorder_point IS NOT NULL AND stock_quantity <= reorder_point
                 ORDER BY stock_quantity ASC'
            );
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('[InventoryRepo::getLowStock] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Recent log entries for a specific product.
     * @return array<int, array<string, mixed>>
     */
    public static function getLogForProduct(int $concessionId, int $limit = 20): array
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(
                'SELECT * FROM inventory_log WHERE concession_id = :id ORDER BY created_at DESC LIMIT :lim'
            );
            $stmt->bindValue(':id', $concessionId, \PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('[InventoryRepo::getLogForProduct] ' . $e->getMessage());
            return [];
        }
    }
}
