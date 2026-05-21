<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Stock;
use App\Models\StockLog;

class InventoryController extends Controller
{
    /**
     * View inventory by branch (or all branches for Manager)
     * GET /api/inventory?branch_id=XX
     */
    public function index()
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);

            return;
        }

        $branchId = $_GET['branch_id'] ?? null;
        if (!$branchId) {
            $user = $this->user;
            $branchId = $user['branch_id'] ?? null;
            if (!$branchId) {
                $this->respondJsonError('Branch ID is required', 400);

                return;
            }
        }

        try {
            $inventory = Stock::getByBranch($branchId);
            $totalValue = Stock::getTotalValue($branchId);

            $this->respondJson([
                'success' => true,
                'inventory' => $inventory,
                'total_value' => $totalValue
            ]);
        } catch (\Exception $e) {
            $this->respondJsonError('Error fetching inventory: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get low stock alerts for a branch
     * GET /api/inventory/low-stock?branch_id=XX
     */
    public function lowStock()
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);

            return;
        }

        $branchId = $_GET['branch_id'] ?? null;
        if (!$branchId) {
            $user = $this->user;
            $branchId = $user['branch_id'] ?? null;
            if (!$branchId) {
                $this->respondJsonError('Branch ID is required', 400);

                return;
            }
        }

        try {
            $lowStock = Stock::getLowStock($branchId);
            $this->respondJson([
                'success' => true,
                'low_stock' => $lowStock
            ]);
        } catch (\Exception $e) {
            $this->respondJsonError('Error fetching low stock: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Fetch recent stock movement history
     * GET /api/inventory/history?branch_id=XX
     */
    public function history()
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);

            return;
        }

        $branchId = $_GET['branch_id'] ?? null;
        if (!$branchId) {
            $user = $this->user;
            $branchId = $user['branch_id'] ?? null;
            if (!$branchId) {
                $this->respondJsonError('Branch ID is required', 400);

                return;
            }
        }

        try {
            $history = StockLog::getHistory($branchId, 100);
            $this->respondJson([
                'success' => true,
                'history' => $history
            ]);
        } catch (\Exception $e) {
            $this->respondJsonError('Error fetching inventory history: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Make a stock adjustment (in/out/damage/adjustment)
     * POST /api/inventory/adjust
     */
    public function adjust()
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);

            return;
        }

        // Restrict to Manager (1) or Store Keeper (2)
        $user = $this->user;
        if ($user['role_id'] != 1 && $user['role_id'] != 2) {
            $this->respondJsonError('Forbidden: Only Managers and Store Keepers can adjust stock.', 403);

            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        $itemId = $data['item_id'] ?? null;
        $branchId = !empty($data['branch_id']) ? $data['branch_id'] : $user['branch_id'];
        $type = $data['type'] ?? null;
        $quantity = $data['quantity'] ?? 0;
        $notes = $data['notes'] ?? null;

        if (!$itemId || !$branchId || !$type || $quantity <= 0) {
            $this->respondJsonError('Missing required fields or invalid quantity', 400);

            return;
        }

        try {
            $this->db->beginTransaction();

            // Find or create stock
            $stock = Stock::findByItemAndBranch($itemId, $branchId);
            $currentQuantity = $stock ? $stock->quantity : 0;
            $currentDamaged = $stock ? $stock->damaged_quantity : 0;

            $newQuantity = $currentQuantity;
            $newDamaged = $currentDamaged;

            $quantityChange = 0; // For the history log

            if ($type === 'in' || $type === 'adjustment' && $data['adjust_direction'] === '+') {
                $newQuantity += $quantity;
                $quantityChange = $quantity;
            } elseif ($type === 'out' || ($type === 'adjustment' && $data['adjust_direction'] === '-')) {
                if ($currentQuantity < $quantity) {
                    throw new \Exception("Insufficient stock for item ID $itemId");
                }
                $newQuantity -= $quantity;
                $quantityChange = -$quantity;
            } elseif ($type === 'damage') {
                if ($currentQuantity < $quantity) {
                    throw new \Exception("Insufficient stock for item ID $itemId to mark as damaged");
                }
                $newQuantity -= $quantity;
                $newDamaged += $quantity;
                $quantityChange = -$quantity;
            } else {
                throw new \Exception("Unknown adjustment type.");
            }

            // Update or Insert Stock
            if ($stock) {
                // Cannot call update dynamically easily without custom model update method, doing manual DB update
                $sql = "UPDATE stock SET quantity = ?, damaged_quantity = ?, updated_at = NOW() WHERE item_id = ? AND branch_id = ?";
                $this->db->query($sql, [$newQuantity, $newDamaged, $itemId, $branchId]);
            } else {
                $sql = "INSERT INTO stock (item_id, branch_id, quantity, damaged_quantity, updated_at) VALUES (?, ?, ?, ?, NOW())";
                $this->db->query($sql, [$itemId, $branchId, $newQuantity, $newDamaged]);
            }

            // Record in history log
            StockLog::recordMovement($itemId, $branchId, $type, $quantityChange, $user['id'], $notes);

            $this->db->commit();

            $this->respondJson([
                'success' => true,
                'message' => 'Stock adjusted successfully',
                'new_quantity' => $newQuantity
            ]);

        } catch (\Exception $e) {
            $this->db->rollback();
            $this->respondJsonError('Error adjusting stock: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Helper: Respond with JSON
     */
    protected function respondJson($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Helper: Respond with JSON error
     */
    protected function respondJsonError($message, $statusCode = 400)
    {
        $this->respondJson([
            'success' => false,
            'message' => $message
        ], $statusCode);
    }
}
