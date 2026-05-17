<?php

namespace App\Models;

use App\Core\Database;
use Exception;

class Report
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Generate sales summary bounded by dates
     */
    public function getSalesSummary($startDate, $endDate)
    {
        $query = "SELECT 
                    COUNT(s.id) as total_transactions,
                    COALESCE(SUM(s.total_amount), 0) as revenue,
                    COALESCE(SUM(s.final_amount), 0) as final_revenue,
                    COALESCE(SUM(s.discount_amount), 0) as total_discounts,
                    COALESCE(SUM(s.final_amount - (
                        SELECT COALESCE(SUM(i.cost_price * si.quantity), 0)
                        FROM sales_items si 
                        JOIN items i ON si.item_id = i.id 
                        WHERE si.sales_id = s.id
                    )), 0) as profit,
                    COALESCE((SELECT SUM(si2.quantity) FROM sales_items si2 
                              JOIN sales s2 ON si2.sales_id = s2.id 
                              WHERE DATE(s2.created_at) BETWEEN ? AND ?), 0) as total_items_sold
                  FROM sales s
                  WHERE DATE(s.created_at) BETWEEN ? AND ?";

        $result = $this->db->query($query, [$startDate, $endDate, $startDate, $endDate]);

        $extracted = $this->toArray($result);

        return $extracted[0] ?? [
            'total_transactions' => 0,
            'revenue' => 0,
            'final_revenue' => 0,
            'total_discounts' => 0,
            'profit' => 0,
            'total_items_sold' => 0
        ];
    }

    /**
     * Aggregate sales by branch
     */
    public function getSalesByBranch($startDate, $endDate)
    {
        $query = "SELECT 
                    b.name as branch_name, 
                    COUNT(s.id) as transactions, 
                    COALESCE(SUM(s.final_amount), 0) as revenue 
                  FROM branches b
                  LEFT JOIN sales s ON b.id = s.branch_id AND DATE(s.created_at) BETWEEN ? AND ?
                  WHERE b.is_active = 1
                  GROUP BY b.id, b.name
                  ORDER BY revenue DESC";

        return $this->toArray($this->db->query($query, [$startDate, $endDate]));
    }

    /**
     * Aggregate sales by seller (users)
     */
    public function getSalesBySeller($startDate, $endDate)
    {
        $query = "SELECT 
                    u.first_name, 
                    u.last_name, 
                    COUNT(s.id) as total_sales, 
                    COALESCE(SUM(s.final_amount), 0) as revenue
                  FROM users u
                  JOIN sales s ON u.id = s.user_id
                  WHERE DATE(s.created_at) BETWEEN ? AND ?
                  GROUP BY u.id, u.first_name, u.last_name
                  ORDER BY revenue DESC";

        return $this->toArray($this->db->query($query, [$startDate, $endDate]));
    }

    /**
     * Calculate inventory overall valuation
     */
    public function getInventoryValuation()
    {
        $query = "SELECT 
                    COUNT(*) as total_products,
                    COALESCE(SUM(i.cost_price), 0) as total_cost_value,
                    COALESCE(SUM(i.selling_price), 0) as total_retail_value,
                    COALESCE(SUM(i.selling_price) - SUM(i.cost_price), 0) as potential_profit
                  FROM items i
                  WHERE i.is_active = 1";

        $result = $this->toArray($this->db->query($query));

        return $result[0] ?? [
            'total_products' => 0,
            'total_cost_value' => 0,
            'total_retail_value' => 0,
            'potential_profit' => 0
        ];
    }

    /**
     * Retrieve items facing stock shortage
     */
    public function getLowStockItems($threshold = 10)
    {
        $query = "SELECT 
                    i.sku,
                    i.name,
                    c.name as category_name,
                    COALESCE(st.quantity, 0) as quantity,
                    i.reorder_level
                  FROM items i
                  LEFT JOIN categories c ON i.category_id = c.id
                  LEFT JOIN stock st ON i.id = st.item_id
                  WHERE i.is_active = 1 AND COALESCE(st.quantity, 0) <= i.reorder_level
                  ORDER BY quantity ASC
                  LIMIT 20";

        return $this->toArray($this->db->query($query));
    }

    /**
     * Convert mysqli_result to array safely
     */
    private function toArray($result)
    {
        if (is_array($result)) {
            return $result;
        }

        $data = [];
        if ($result instanceof \mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }

        return $data;
    }
}
