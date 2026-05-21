<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Transfer;
use App\Models\Stock;
use App\Models\StockLog;
use Exception;

class TransferController extends Controller
{
    /**
     * Get list of transfers based on user role and branch
     * GET /api/transfers
     */
    public function index()
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);

            return;
        }

        try {
            // Managers (1) see all transfers, Store Keepers (2) see their branch
            if ($this->user['role_id'] == 1) {
                $transfers = Transfer::getAllTransfers();
            } elseif ($this->user['role_id'] == 2) {
                if (!$this->user['branch_id']) {
                    $this->respondJsonError('User configuration error: Missing branch association.', 400);

                    return;
                }
                $transfers = Transfer::getBranchTransfers($this->user['branch_id']);
            } else {
                $this->respondJsonError('Forbidden: Only Managers and Store Keepers can view transfers.', 403);

                return;
            }

            $this->respondJson([
                'success' => true,
                'transfers' => $transfers
            ]);
        } catch (Exception $e) {
            $this->respondJsonError('Error fetching transfers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Submit a new transfer request
     * POST /api/transfers
     */
    public function store()
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);

            return;
        }

        // Only Manager or Store Keeper can initiate
        if ($this->user['role_id'] != 1 && $this->user['role_id'] != 2) {
            $this->respondJsonError('Forbidden', 403);

            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $itemId = $data['item_id'] ?? null;
        $toBranchId = $data['to_branch_id'] ?? null;
        $quantity = $data['quantity'] ?? 0;

        $fromBranchId = $this->user['branch_id'] ?? null;
        if ($this->user['role_id'] == 1 && !empty($data['from_branch_id'])) {
            $fromBranchId = $data['from_branch_id']; // Manager can pick custom from branch
        }

        if (!$itemId || !$toBranchId || !$fromBranchId || $quantity <= 0) {
            $this->respondJsonError('Missing required fields or invalid quantity', 400);

            return;
        }

        try {
            // Verify there is enough stock before even requesting
            $stock = Stock::findByItemAndBranch($itemId, $fromBranchId);
            if (!$stock || $stock->quantity < $quantity) {
                $this->respondJsonError("Insufficient stock in the originating branch for this transfer request.", 400);

                return;
            }

            $transfer = Transfer::createTransfer($itemId, $fromBranchId, $toBranchId, $quantity, $this->user['id']);

            $this->respondJson([
                'success' => true,
                'message' => 'Transfer requested successfully',
                'transfer' => $transfer->toArray()
            ]);
        } catch (Exception $e) {
            $this->respondJsonError('Error requesting transfer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update transfer status (approve, receive, cancel)
     * PUT /api/transfers/{id}/status
     */
    public function updateStatus($id)
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);

            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $status = $data['status'] ?? null;

        if (!$status || !in_array($status, ['in_transit', 'completed', 'cancelled'])) {
            $this->respondJsonError('Invalid or missing status payload', 400);

            return;
        }

        try {
            $transfer = Transfer::find($id);
            if (!$transfer) {
                $this->respondJsonError('Transfer not found', 404);

                return;
            }

            $this->db->beginTransaction();

            // --- APPROVAL LOGIC (Manager required to set 'in_transit') ---
            if ($status === 'in_transit') {
                if ($this->user['role_id'] != 1) {
                    throw new Exception("Only Managers can approve transfers.");
                }
                if ($transfer->status !== 'pending') {
                    throw new Exception("Only pending transfers can be approved.");
                }

                // Deduct stock from source branch
                $this->adjustStock($transfer->item_id, $transfer->from_branch_id, -$transfer->quantity);

                // Record movement log (transfer OUT)
                StockLog::recordMovement($transfer->item_id, $transfer->from_branch_id, 'transfer', -$transfer->quantity, $this->user['id'], "Transfer {$transfer->id} approved.", 'transfer', $transfer->id);

                $transfer->updateStatus('in_transit', $this->user['id']);
            }
            // --- RECEIVE LOGIC (Store Keeper at destination or Manager) ---
            elseif ($status === 'completed') {
                if ($this->user['role_id'] != 1 && $this->user['branch_id'] != $transfer->to_branch_id) {
                    throw new Exception("You can only receive transfers destined for your branch.");
                }
                if ($transfer->status !== 'in_transit') {
                    throw new Exception("Transfer must be strictly 'in_transit' to be received.");
                }

                // Add stock to destination branch
                $this->adjustStock($transfer->item_id, $transfer->to_branch_id, $transfer->quantity);

                // Record movement log (transfer IN)
                StockLog::recordMovement($transfer->item_id, $transfer->to_branch_id, 'transfer', $transfer->quantity, $this->user['id'], "Transfer {$transfer->id} received.", 'transfer', $transfer->id);

                $transfer->updateStatus('completed');
            }
            // --- CANCEL LOGIC ---
            elseif ($status === 'cancelled') {
                // Must be manager (1) OR the original initiator OR staff at source/dest branch (depends on business logic, mostly Managers)
                if ($this->user['role_id'] != 1 && $this->user['id'] != $transfer->initiated_by) {
                    throw new Exception("Unauthorized to cancel this transfer.");
                }

                if ($transfer->status === 'completed' || $transfer->status === 'cancelled') {
                    throw new Exception("Transfer is already completed or cancelled.");
                }

                // If it was already in_transit, we must refund the stock back to the origin branch!
                if ($transfer->status === 'in_transit') {
                    $this->adjustStock($transfer->item_id, $transfer->from_branch_id, $transfer->quantity);
                    StockLog::recordMovement($transfer->item_id, $transfer->from_branch_id, 'transfer', $transfer->quantity, $this->user['id'], "Transfer {$transfer->id} cancelled. Stock restored.", 'transfer', $transfer->id);
                }

                $transfer->updateStatus('cancelled');
            }

            $this->db->commit();

            $this->respondJson([
                'success' => true,
                'message' => "Transfer status updated to {$status}"
            ]);

        } catch (Exception $e) {
            $this->db->rollback();
            $this->respondJsonError($e->getMessage(), 400);
        }
    }

    /**
     * Helper to reliably bump or drop stock values natively using raw DB Update for concurrency safety
     */
    private function adjustStock($itemId, $branchId, $quantityChange)
    {
        $stock = Stock::findByItemAndBranch($itemId, $branchId);
        if ($stock) {
            if ($stock->quantity + $quantityChange < 0) {
                throw new Exception("Insufficient stock to process this operation.");
            }
            $sql = "UPDATE stock SET quantity = quantity + ?, updated_at = NOW() WHERE item_id = ? AND branch_id = ?";
            $this->db->query($sql, [$quantityChange, $itemId, $branchId]);
        } else {
            if ($quantityChange < 0) {
                throw new Exception("Insufficient stock to process this operation (No stock record exists).");
            }
            $sql = "INSERT INTO stock (item_id, branch_id, quantity, damaged_quantity, updated_at) VALUES (?, ?, ?, 0, NOW())";
            $this->db->query($sql, [$itemId, $branchId, $quantityChange]);
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
