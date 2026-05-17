<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Chart;

class ChartController extends Controller
{
    private $chartModel;

    public function __construct()
    {
        parent::__construct();
        $this->chartModel = new Chart();
    }

    /**
     * Helper: Respond with JSON
     */
    private function respondJson($data, $statusCode = 200)
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    /**
     * Helper: Respond with JSON error
     */
    private function respondJsonError($message, $statusCode = 400)
    {
        $this->respondJson([
            'success' => false,
            'message' => $message,
            'errors' => null
        ], $statusCode);
    }

    /**
     * GET /api/charts/sales-trend
     */
    public function getSalesTrend()
    {
        // auth validation bypassed for debug

        try {
            $days = isset($_GET['days']) ? (int) $_GET['days'] : 30;
            $data = $this->chartModel->getSalesTrend($days);

            // Format for Chart.js
            $labels = [];
            $revenues = [];

            foreach ($data as $row) {
                $labels[] = date('M d', strtotime($row['date']));
                $revenues[] = (float) $row['total_revenue'];
            }

            $this->respondJson([
                'success' => true,
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Revenue',
                            'data' => $revenues,
                            'borderColor' => '#4361ee',
                            'backgroundColor' => 'rgba(67, 97, 238, 0.1)',
                            'fill' => true,
                            'tension' => 0.4
                        ]
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            $this->respondJsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/charts/revenue-by-branch
     */
    public function getRevenueByBranch()
    {
        // auth validation bypassed for debug

        try {
            $data = $this->chartModel->getBranchRevenue();

            $labels = [];
            $revenues = [];
            $backgroundColors = ['#4361ee', '#3a0ca3', '#7209b7', '#f72585', '#4cc9f0', '#00bbf9'];

            foreach ($data as $row) {
                $labels[] = $row['branch_name'];
                $revenues[] = (float) $row['total_revenue'];
            }

            $this->respondJson([
                'success' => true,
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'data' => $revenues,
                            'backgroundColor' => array_slice($backgroundColors, 0, count($labels))
                        ]
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            $this->respondJsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/charts/category-performance
     */
    public function getCategoryPerformance()
    {
        // auth validation bypassed for debug

        try {
            $data = $this->chartModel->getCategoryPerformance();

            $labels = [];
            $quantities = [];

            foreach ($data as $row) {
                $labels[] = $row['category_name'];
                $quantities[] = (int) $row['items_sold'];
            }

            $this->respondJson([
                'success' => true,
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Items Sold',
                            'data' => $quantities,
                            'backgroundColor' => '#4cc9f0',
                            'borderRadius' => 4
                        ]
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            $this->respondJsonError($e->getMessage(), 500);
        }
    }
}
