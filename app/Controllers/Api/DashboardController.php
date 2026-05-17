<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Dashboard;
use Exception;

class DashboardController extends Controller
{
    private $dashboardModel;

    public function __construct()
    {
        parent::__construct();
        $this->dashboardModel = new Dashboard();
    }

    /**
     * GET /api/dashboard/manager
     * Returns all manager-level KPIs, recent transactions, top sellers, etc.
     */
    public function manager()
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);
            return;
        }

        try {
            $kpis = $this->dashboardModel->getManagerKpis();
            $recentTxns = $this->dashboardModel->getRecentTransactions(10);
            $topSellers = $this->dashboardModel->getTopSellers(5);
            $branchRevenue = $this->dashboardModel->getRevenuByBranch();
            $lowStock = $this->dashboardModel->getLowStockAlerts(5);

            $this->respondJson([
                'success' => true,
                'data' => [
                    'kpis' => $kpis,
                    'recent_transactions' => $recentTxns,
                    'top_sellers' => $topSellers,
                    'branch_revenue' => $branchRevenue,
                    'low_stock_alerts' => $lowStock
                ]
            ]);
        } catch (Exception $e) {
            $this->respondJsonError('Error loading manager dashboard: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/dashboard/storekeeper
     * Returns inventory-focused metrics for store keepers
     */
    public function storekeeper()
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);
            return;
        }

        try {
            $metrics = $this->dashboardModel->getStoreKeeperMetrics();

            $this->respondJson([
                'success' => true,
                'data' => $metrics
            ]);
        } catch (Exception $e) {
            $this->respondJsonError('Error loading store keeper dashboard: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/dashboard/seller
     * Returns personal sales metrics for the logged-in seller
     */
    public function seller()
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);
            return;
        }

        try {
            $user = $this->getCurrentUser();
            $userId = $user['id'] ?? 0;
            $metrics = $this->dashboardModel->getSellerMetrics($userId);

            $this->respondJson([
                'success' => true,
                'data' => $metrics
            ]);
        } catch (Exception $e) {
            $this->respondJsonError('Error loading seller dashboard: ' . $e->getMessage(), 500);
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
