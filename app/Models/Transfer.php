<?php

namespace App\Models;

use App\Core\Model;
use Exception;

class Transfer extends Model
{
    protected $table = 'transfers';

    /**
     * Create a new transfer request
     */
    public static function createTransfer($itemId, $fromBranchId, $toBranchId, $quantity, $userId)
    {
        if ($quantity <= 0) {
            throw new Exception("Transfer quantity must be greater than zero.");
        }
        if ($fromBranchId == $toBranchId) {
            throw new Exception("Source and destination branches cannot be the same.");
        }

        $transfer = new static([
            'item_id' => $itemId,
            'from_branch_id' => $fromBranchId,
            'to_branch_id' => $toBranchId,
            'quantity' => $quantity,
            'status' => 'pending',
            'initiated_by' => $userId
        ]);

        $transfer->save();
        return $transfer;
    }

    /**
     * Get all transfers (for Managers)
     */
    public static function getAllTransfers()
    {
        $instance = new static();

        $sql = "SELECT t.*, 
                       i.name AS item_name, i.sku,
                       b1.name AS from_branch_name,
                       b2.name AS to_branch_name,
                       u1.first_name AS init_first, u1.last_name AS init_last,
                       u2.first_name AS appr_first, u2.last_name AS appr_last
                FROM transfers t
                JOIN items i ON t.item_id = i.id
                JOIN branches b1 ON t.from_branch_id = b1.id
                JOIN branches b2 ON t.to_branch_id = b2.id
                JOIN users u1 ON t.initiated_by = u1.id
                LEFT JOIN users u2 ON t.approved_by = u2.id
                ORDER BY t.created_at DESC";

        $result = $instance->db->query($sql);

        $transfers = [];
        while ($row = $result->fetch_assoc()) {
            $row['initiated_by_name'] = trim($row['init_first'] . ' ' . $row['init_last']);
            $row['approved_by_name'] = $row['appr_first'] ? trim($row['appr_first'] . ' ' . $row['appr_last']) : null;
            $transfers[] = $row;
        }

        return $transfers;
    }

    /**
     * Get transfers involving a specific branch (as sender or receiver)
     */
    public static function getBranchTransfers($branchId)
    {
        $instance = new static();

        $sql = "SELECT t.*, 
                       i.name AS item_name, i.sku,
                       b1.name AS from_branch_name,
                       b2.name AS to_branch_name,
                       u1.first_name AS init_first, u1.last_name AS init_last,
                       u2.first_name AS appr_first, u2.last_name AS appr_last
                FROM transfers t
                JOIN items i ON t.item_id = i.id
                JOIN branches b1 ON t.from_branch_id = b1.id
                JOIN branches b2 ON t.to_branch_id = b2.id
                JOIN users u1 ON t.initiated_by = u1.id
                LEFT JOIN users u2 ON t.approved_by = u2.id
                WHERE t.from_branch_id = ? OR t.to_branch_id = ?
                ORDER BY t.created_at DESC";

        $result = $instance->db->query($sql, [$branchId, $branchId]);

        $transfers = [];
        while ($row = $result->fetch_assoc()) {
            $row['initiated_by_name'] = trim($row['init_first'] . ' ' . $row['init_last']);
            $row['approved_by_name'] = $row['appr_first'] ? trim($row['appr_first'] . ' ' . $row['appr_last']) : null;
            $transfers[] = $row;
        }

        return $transfers;
    }

    /**
     * Update transfer status (e.g. pending -> in_transit -> completed, or cancelled)
     */
    public function updateStatus($newStatus, $approvedByUserId = null)
    {
        $allowedStatuses = ['pending', 'in_transit', 'completed', 'cancelled'];
        if (!in_array($newStatus, $allowedStatuses)) {
            throw new Exception("Invalid status: {$newStatus}");
        }

        $this->status = $newStatus;
        if ($approvedByUserId) {
            $this->approved_by = $approvedByUserId;
        }

        if ($newStatus === 'in_transit') {
            $this->transferred_at = date('Y-m-d H:i:s');
        }

        return $this->save();
    }
}
