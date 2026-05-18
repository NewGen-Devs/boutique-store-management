<?php

namespace App\Models;

use App\Core\Model;

class Inventory extends Model
{
    protected $table = 'stock';

    // ---- Queries ----

    /** Get all stock for a branch (with item details) */
    public static function getByBranch($branchId = null)
    {
        $instance = new static();
        
        $sql = "SELECT s.*, i.name as item_name, i.sku, i.reorder_level,
                       i.selling_price, c.name as category_name, b.name as branch_name
                FROM stock s
                JOIN items i ON s.item_id = i.id
                LEFT JOIN categories c ON i.category_id = c.id
                LEFT JOIN branches b ON s.branch_id = b.id
                WHERE i.is_active = TRUE";
                
        $params = [];
        if ($branchId) {
            $sql .= " AND s.branch_id = ?";
            $params[] = $branchId;
        }
        
        $sql .= " ORDER BY i.name ASC, b.name ASC";
        
        $result = $instance->db->query($sql, $params);
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        return $rows;
    }

    /** Get all low stock items (quantity <= reorder_level) */
    public static function getLowStock($branchId = null)
    {
        $instance = new static();
        $sql = "SELECT s.*, i.name as item_name, i.sku, i.reorder_level,
                            b.name as branch_name
                FROM stock s
                JOIN items i ON s.item_id = i.id
                JOIN branches b ON s.branch_id = b.id
                WHERE s.quantity <= i.reorder_level AND i.is_active = TRUE";
        $params = [];
        if ($branchId) {
            $sql .= " AND s.branch_id = ?";
            $params[] = (int) $branchId;
        }
        $sql .= " ORDER BY s.quantity ASC";
        $result = $instance->db->query($sql, $params);
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        return $rows;
    }

    /** Calculate total inventory value for a branch */
    public static function getTotalValue($branchId = null)
    {
        $instance = new static();
        $sql = "SELECT SUM(s.quantity * i.selling_price) as total_value,
                       SUM(s.quantity * i.cost_price) as total_cost,
                        SUM(s.quantity) as total_units
                FROM stock s
                JOIN items i ON s.item_id = i.id
                WHERE i.is_active = TRUE";
        $params = [];
        if ($branchId) {
            $sql .= " AND s.branch_id = ?";
            $params[] = (int) $branchId;
        }
        $result = $instance->db->query($sql, $params);
        return $result->fetch_assoc();
    }

    /** Get or create stock record for an item in a branch */
    public static function getOrCreate($itemId, $branchId)
    {
        $instance = new static();
        $result = $instance->db->query(
            "SELECT * FROM stock WHERE item_id = ? AND branch_id = ?",
            [$itemId, $branchId]
        );
        $row = $result->fetch_assoc();
        if ($row) return new static($row);

        // Create a stock record with 0 quantity
        $id = $instance->db->insert('stock', [
            'item_id'   => $itemId,
            'branch_id' => $branchId,
            'quantity'  => 0,
        ]);
        $result2 = $instance->db->query("SELECT * FROM stock WHERE id = ?", [$id]);
        return new static($result2->fetch_assoc());
    }

    /** Adjust stock quantity */
    public function adjust($change)
    {
        $newQty = max(0, (int)$this->quantity + (int)$change);
        $this->db->update('stock', ['quantity' => $newQty], 'id = ?', [$this->id]);
        $this->attributes['quantity'] = $newQty;
    }

    /** Add to damaged quantity */
    public function addDamaged($amount)
    {
        $newDamaged = (int)$this->damaged_quantity + (int)$amount;
        $newQty     = max(0, (int)$this->quantity - (int)$amount);
        $this->db->update('stock', [
            'damaged_quantity' => $newDamaged,
            'quantity'         => $newQty,
        ], 'id = ?', [$this->id]);
        $this->attributes['damaged_quantity'] = $newDamaged;
        $this->attributes['quantity'] = $newQty;
    }
}
