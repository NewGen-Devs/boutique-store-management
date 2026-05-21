<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use Exception;

class SalesController extends Controller
{
    /**
     * Get recent sales (filtered by RBAC)
     * GET /api/sales
     */
    public function index()
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);

            return;
        }

        try {
            $roleId = (int) $this->user['role_id'];
            $branchId = $this->user['branch_id'];

            if ($roleId === 1) { // Manager sees all
                $sales = Sale::getAllSales(100);
            } elseif ($roleId === 2) { // Store Keeper sees branch
                $sales = Sale::getBranchSales($branchId, null, 100);
            } elseif ($roleId === 3) { // Seller sees own
                $sales = Sale::getBranchSales($branchId, $this->user['id'], 100);
            } else {
                $this->respondJsonError('Forbidden', 403);

                return;
            }

            $this->respondJson([
                'success' => true,
                'sales' => $sales
            ]);
        } catch (Exception $e) {
            $this->respondJsonError('Error fetching sales: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get a specific sale with line items
     * GET /api/sales/{id}
     */
    public function show($id)
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);

            return;
        }

        try {
            $sale = Sale::find($id);
            if (!$sale) {
                $this->respondJsonError('Sale not found', 404);

                return;
            }

            // Simple RBAC
            if ($this->user['role_id'] != 1 && $sale->branch_id != $this->user['branch_id']) {
                $this->respondJsonError('Forbidden to view this sale', 403);

                return;
            }

            $items = SaleItem::getItemsBySaleId($id);

            $this->respondJson([
                'success' => true,
                'sale' => $sale->toArray(),
                'items' => $items
            ]);
        } catch (Exception $e) {
            $this->respondJsonError('Error fetching sale details: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store a new sale (POS Checkout)
     * POST /api/sales
     */
    public function store()
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);

            return;
        }

        // Must be Seller(3) or Store Keeper(2) or Manager(1)
        if (!in_array($this->user['role_id'], [1, 2, 3])) {
            $this->respondJsonError('Forbidden', 403);

            return;
        }

        if (empty($this->user['branch_id'])) {
            $this->respondJsonError('Your user account is not assigned to a branch.', 400);

            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        $items = $data['items'] ?? [];
        $paymentMethod = $data['payment_method'] ?? 'cash';
        $discountAmount = floatval($data['discount_amount'] ?? 0);
        $notes = $data['notes'] ?? null;

        if (empty($items) || !is_array($items)) {
            $this->respondJsonError('No items provided for the sale', 400);

            return;
        }

        try {
            $sale = Sale::createSale(
                $this->user['id'],
                $this->user['branch_id'],
                $items,
                $discountAmount,
                $paymentMethod,
                $notes
            );

            $this->respondJson([
                'success' => true,
                'message' => 'Transaction completed successfully.',
                'sale' => $sale->toArray()
            ]);

        } catch (Exception $e) {
            $this->respondJsonError('Transaction failed: ' . $e->getMessage(), 400);
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
