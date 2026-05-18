<?php

namespace App\Models;

use App\Core\Model;

class StockLog extends Model
{
    protected $table = 'stock_history';

    // ---- Record movements ----

    /** Log any stock movement */
    public static function record($itemId, $branchId, $type, $qtyChange, $notes = null, $userId = null)
    {
        $instance = new static();
        $instance->db->insert('stock_history', [
            'item_id'         => $itemId,
            'branch_id'       => $branchId,
            'type'            => $type,        // 'in','out','damage','adjustment'
            'quantity_change' => $qtyChange,
            'notes'           => $notes,
            'user_id'         => $userId ?? 0,
        ]);
    }

    // ---- Query history ----

    /** Get history for a branch (with optional item filter) */
    public static function getHistory($branchId = null, $itemId = null, $limit = 50)
    {
        $instance = new static();
        $where = ['1=1'];
        $params = [];

        if ($branchId) { $where[] = 'sh.branch_id = ?'; $params[] = (int)$branchId; }
        if ($itemId)   { $where[] = 'sh.item_id = ?';   $params[] = (int)$itemId; }

        $sql = "SELECT sh.*, i.name as item_name, i.sku, b.name as branch_name
                FROM stock_history sh
                JOIN items i ON sh.item_id = i.id
                JOIN branches b ON sh.branch_id = b.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY sh.created_at DESC
                LIMIT ?";
        $params[] = (int)$limit;

        $result = $instance->db->query($sql, $params);
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        return $rows;
    }

    /** Get summary report: total in/out/damage per item in a branch */
    public static function getReport($branchId)
    {
        $instance = new static();
        $result = $instance->db->query(
            "SELECT i.name as item_name, i.sku,
                    SUM(CASE WHEN sh.type='in' THEN sh.quantity_change ELSE 0 END) as total_in,
                    SUM(CASE WHEN sh.type='out' THEN ABS(sh.quantity_change) ELSE 0 END) as total_out,
                    SUM(CASE WHEN sh.type='damage' THEN ABS(sh.quantity_change) ELSE 0 END) as total_damaged
             FROM stock_history sh
             JOIN items i ON sh.item_id = i.id
             WHERE sh.branch_id = ?
             GROUP BY sh.item_id
             ORDER BY i.name ASC",
            [$branchId]
        );
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        return $rows;
    }
}
