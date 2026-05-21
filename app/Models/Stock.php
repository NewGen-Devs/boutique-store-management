<?php

namespace App\Models;

use App\Core\Model;

class Stock extends Model
{
    protected $table = 'stock';

    /**
     * Get inventory with item details by branch.
     * Optionally joins with category for filtering.
     */
    public static function getByBranch($branchId)
    {
        $instance = new static();

        // Ensure all active products have a stock record mapped for this branch
        $instance->db->query("
            INSERT IGNORE INTO stock (item_id, branch_id, quantity)
            SELECT id, ?, 0 FROM items WHERE is_active = TRUE
        ", [$branchId]);

        $sql = "SELECT s.*, 
                       i.name AS item_name, i.sku, i.reorder_level, i.cost_price, i.selling_price,
                       c.name AS category_name
                FROM stock s
                JOIN items i ON s.item_id = i.id
                JOIN categories c ON i.category_id = c.id
                WHERE s.branch_id = ? AND i.is_active = TRUE
                ORDER BY i.name ASC";

        $result = $instance->db->query($sql, [$branchId]);

        $inventory = [];
        while ($row = $result->fetch_assoc()) {
            $inventory[] = $row;
        }

        return $inventory;
    }

    /**
     * Get items that are below or equal to reorder level for a specific branch
     */
    public static function getLowStock($branchId)
    {
        $instance = new static();

        $sql = "SELECT s.id, s.branch_id, s.quantity, i.id AS item_id, i.name AS item_name, i.sku, i.reorder_level,
                       (i.reorder_level - s.quantity) AS shortage
                FROM stock s
                JOIN items i ON s.item_id = i.id
                WHERE s.branch_id = ? 
                AND s.quantity <= i.reorder_level 
                AND i.is_active = TRUE
                ORDER BY (i.reorder_level - s.quantity) DESC";

        $result = $instance->db->query($sql, [$branchId]);

        $lowStock = [];
        while ($row = $result->fetch_assoc()) {
            $lowStock[] = $row;
        }

        return $lowStock;
    }

    /**
     * Finds a stock record by item and branch, or returns a new instance
     */
    public static function findByItemAndBranch($itemId, $branchId)
    {
        $instance = new static();
        $sql = "SELECT * FROM stock WHERE item_id = ? AND branch_id = ?";
        $result = $instance->db->query($sql, [$itemId, $branchId]);

        if ($row = $result->fetch_assoc()) {
            return new static($row);
        }

        return null;
    }

    /**
     * Calculate total inventory value for a branch
     */
    public static function getTotalValue($branchId)
    {
        $instance = new static();
        $sql = "SELECT SUM(s.quantity * i.cost_price) as total_value 
                FROM stock s 
                JOIN items i ON s.item_id = i.id 
                WHERE s.branch_id = ?";

        $result = $instance->db->query($sql, [$branchId]);
        $row = $result->fetch_assoc();
        return $row['total_value'] ?? 0;
    }
}
