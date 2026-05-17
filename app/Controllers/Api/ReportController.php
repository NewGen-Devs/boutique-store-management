<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Report;
use Exception;

class ReportController extends Controller
{
    private $reportModel;

    public function __construct()
    {
        parent::__construct();
        $this->reportModel = new Report();
    }

    /**
     * Retrieve top-level KPI summary (Transactions, Revenue, Profit, etc)
     * GET /api/reports/sales/summary
     */
    public function getSalesSummary()
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);
            return;
        }

        if (!$this->hasPermission('reports.read')) {
            $this->respondJsonError('Forbidden: Permission denied', 403);
            return;
        }

        try {
            $startDate = $_GET['start_date'] ?? date('Y-m-01'); // default: start of this month
            $endDate = $_GET['end_date'] ?? date('Y-m-d');     // default: today

            // Basic date validation
            if (!strtotime($startDate) || !strtotime($endDate)) {
                $this->respondJsonError('Invalid date format. Use YYYY-MM-DD.', 400);
                return;
            }

            $summary = $this->reportModel->getSalesSummary($startDate, $endDate);

            $this->respondJson([
                'success' => true,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'data' => $summary
            ]);
        } catch (Exception $e) {
            $this->respondJsonError('Failed to generate sales summary report: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Retrieve Sales sorted grouped by Branches
     * GET /api/reports/sales/branch
     */
    public function getSalesByBranch()
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);
            return;
        }

        if (!$this->hasPermission('reports.read')) {
            $this->respondJsonError('Forbidden: Permission denied', 403);
            return;
        }

        try {
            $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $_GET['end_date'] ?? date('Y-m-d');

            $branchData = $this->reportModel->getSalesByBranch($startDate, $endDate);

            $this->respondJson([
                'success' => true,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'data' => $branchData
            ]);
        } catch (Exception $e) {
            $this->respondJsonError('Failed to generate branch report: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Retrieve Performance metrics sorted by user (seller)
     * GET /api/reports/sales/seller
     */
    public function getSalesBySeller()
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);
            return;
        }

        if (!$this->hasPermission('reports.read')) {
            $this->respondJsonError('Forbidden: Permission denied', 403);
            return;
        }

        try {
            $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $_GET['end_date'] ?? date('Y-m-d');

            $sellerData = $this->reportModel->getSalesBySeller($startDate, $endDate);

            $this->respondJson([
                'success' => true,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'data' => $sellerData
            ]);
        } catch (Exception $e) {
            $this->respondJsonError('Failed to generate seller report: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Retrieve complete financial inventory valuation
     * GET /api/reports/inventory/valuation
     */
    public function getInventoryValuation()
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);
            return;
        }

        if (!$this->hasPermission('reports.read')) {
            $this->respondJsonError('Forbidden: Permission denied', 403);
            return;
        }

        try {
            $valuation = $this->reportModel->getInventoryValuation();

            $this->respondJson([
                'success' => true,
                'data' => $valuation
            ]);
        } catch (Exception $e) {
            $this->respondJsonError('Failed to generate inventory valuation: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Retrieve top 20 items approaching dangerous stock thresholds
     * GET /api/reports/inventory/low-stock
     */
    public function getLowStockReport()
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);
            return;
        }

        if (!$this->hasPermission('reports.read')) {
            $this->respondJsonError('Forbidden: Permission denied', 403);
            return;
        }

        try {
            $lowStock = $this->reportModel->getLowStockItems();

            $this->respondJson([
                'success' => true,
                'data' => $lowStock
            ]);
        } catch (Exception $e) {
            $this->respondJsonError('Failed to generate low stock report: ' . $e->getMessage(), 500);
        }
    }

    // ——— Response Helpers ———

    protected function respondJson($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function respondJsonError($message, $statusCode = 400)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}
