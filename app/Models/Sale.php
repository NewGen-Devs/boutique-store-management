<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use Exception;

class Sale extends Model
{
    protected $table = 'sales';

    /**
     * Create a complete sale transaction including line items and stock deduction.
     * Uses a database transaction to ensure Atomicity.
     *
     * @param int $userId The seller's ID
     * @param int $branchId The branch ID where sale occurs
     * @param array $items Array of cart items (requires item_id, quantity, unit_price)
     * @param float $globalDiscount Overall discount applied
     * @param string $paymentMethod Payment method (cash, card, etc)
     * @param string $notes Optional notes
     * @return Sale
     * @throws Exception
     */
    public static function createSale($userId, $branchId, $items, $globalDiscount = 0, $paymentMethod = 'cash', $notes = null)
    {
        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // 1. Calculate totals
            $totalAmount = 0;
            foreach ($items as $item) {
                if ($item['quantity'] <= 0) {
                    throw new Exception("Invalid quantity for item " . $item['item_id']);
                }
                $totalAmount += ($item['quantity'] * $item['unit_price']);
            }

            $finalAmount = max(0, $totalAmount - $globalDiscount);
            $transactionNumber = 'TRX-' . strtoupper(uniqid()) . '-' . rand(100, 999);

            // 2. Insert main Sales record
            $saleId = $db->insert('sales', [
                'transaction_number' => $transactionNumber,
                'branch_id' => $branchId,
                'user_id' => $userId,
                'total_amount' => $totalAmount,
                'discount_amount' => $globalDiscount,
                'final_amount' => $finalAmount,
                'payment_method' => $paymentMethod,
                'notes' => $notes,
            ]);

            // 3. Process line items & stock deduplication
            foreach ($items as $item) {
                $subtotal = $item['quantity'] * $item['unit_price'];

                // Record line item
                $db->insert('sales_items', [
                    'sales_id' => $saleId,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $subtotal,
                    'discount' => 0 // Can implement line-item discounts later if needed
                ]);

                // Verify and deduct stock
                $stock = Stock::findByItemAndBranch($item['item_id'], $branchId);
                if (!$stock || $stock->quantity < $item['quantity']) {
                    throw new Exception("Insufficient stock for item ID: " . $item['item_id']);
                }

                $db->query(
                    "UPDATE stock SET quantity = quantity - ?, updated_at = NOW() WHERE item_id = ? AND branch_id = ?",
                    [$item['quantity'], $item['item_id'], $branchId]
                );

                // Record movement in stock log
                StockLog::recordMovement(
                    $item['item_id'],
                    $branchId,
                    'out', // Sale out
                    -$item['quantity'],
                    $userId,
                    "Sale checkout: " . $transactionNumber,
                    'sale',
                    $saleId
                );
            }

            $db->commit();
            return self::find($saleId);

        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Get recent sales for a branch, optionally limited by seller
     */
    public static function getBranchSales($branchId, $sellerId = null, $limit = 50)
    {
        $db = Database::getInstance();
        $sql = "SELECT s.*, b.name as branch_name, u.first_name as seller_first, u.last_name as seller_last 
                FROM sales s 
                JOIN branches b ON s.branch_id = b.id 
                JOIN users u ON s.user_id = u.id 
                WHERE s.branch_id = ? ";
        $params = [$branchId];

        if ($sellerId) {
            $sql .= " AND s.user_id = ? ";
            $params[] = $sellerId;
        }

        $sql .= " ORDER BY s.created_at DESC LIMIT ?";
        $params[] = $limit;

        $res = $db->query($sql, $params);
        $sales = [];
        while ($row = $res->fetch_assoc()) {
            $sales[] = $row;
        }
        return $sales;
    }

    /**
     * Get all recent sales system-wide (Manager scope)
     */
    public static function getAllSales($limit = 100)
    {
        $db = Database::getInstance();
        $sql = "SELECT s.*, b.name as branch_name, u.first_name as seller_first, u.last_name as seller_last 
                FROM sales s 
                JOIN branches b ON s.branch_id = b.id 
                JOIN users u ON s.user_id = u.id 
                ORDER BY s.created_at DESC LIMIT ?";
        $res = $db->query($sql, [$limit]);
        $sales = [];
        while ($row = $res->fetch_assoc()) {
            $sales[] = $row;
        }
        return $sales;
    }
}
