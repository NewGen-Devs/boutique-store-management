<?php

namespace App\Models;

use App\Core\Model;
use Exception;

class StockLog extends Model
{
    protected $table = 'stock_history';

    /**
     * Record a stock movement
     */
    public static function recordMovement($itemId, $branchId, $type, $quantityChange, $userId, $notes = null, $referenceType = null, $referenceId = null)
    {
        $allowedTypes = ['in', 'out', 'damage', 'transfer', 'adjustment'];
        if (!in_array($type, $allowedTypes)) {
            throw new Exception("Invalid stock movement type: {$type}");
        }

        $log = new static([
            'item_id' => $itemId,
            'branch_id' => $branchId,
            'type' => $type,
            'quantity_change' => $quantityChange,
            'user_id' => $userId,
            'notes' => $notes,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId
        ]);

        $log->save();
        return $log;
    }

    /**
     * Fetch recent stock history for a branch
     */
    public static function getHistory($branchId, $limit = 50)
    {
        $instance = new static();

        // Fetch movements with item names, user names, etc.
        $sql = "SELECT h.*, 
                       i.name AS item_name, i.sku,
                       u.first_name, u.last_name
                FROM stock_history h
                JOIN items i ON h.item_id = i.id
                JOIN users u ON h.user_id = u.id
                WHERE h.branch_id = ?
                ORDER BY h.created_at DESC
                LIMIT ?";

        $result = $instance->db->query($sql, [$branchId, $limit]);

        $history = [];
        while ($row = $result->fetch_assoc()) {
            // Include full name for convenience
            $row['user_name'] = $row['first_name'] . ' ' . $row['last_name'];
            $history[] = $row;
        }

        return $history;
    }
}
