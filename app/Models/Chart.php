<?php

namespace App\Models;

use App\Core\Database;

class Chart
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get Sales Trend over time
     * Useful for Line Charts
     */
    public function getSalesTrend($days = 30)
    {
        $startDate = date('Y-m-d', strtotime("-$days days"));

        $sql = "
            SELECT 
                DATE(created_at) as date,
                SUM(final_amount) as total_revenue,
                COUNT(id) as total_transactions
            FROM sales
            WHERE created_at >= ?
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at) ASC
        ";

        return $this->toArray($this->db->query($sql, [$startDate . ' 00:00:00']));
    }

    /**
     * Get Revenue by Branch
     * Useful for Doughnut/Pie Charts
     */
    public function getBranchRevenue()
    {
        $sql = "
            SELECT 
                b.name as branch_name,
                SUM(s.final_amount) as total_revenue
            FROM sales s
            JOIN branches b ON s.branch_id = b.id
            GROUP BY b.id
            ORDER BY total_revenue DESC
        ";

        return $this->toArray($this->db->query($sql));
    }

    /**
     * Get Category Performance
     * Useful for Bar Charts
     */
    public function getCategoryPerformance()
    {
        $sql = "
            SELECT 
                c.name as category_name,
                SUM(si.quantity) as items_sold,
                SUM(si.subtotal) as category_revenue
            FROM sales_items si
            JOIN items i ON si.item_id = i.id
            JOIN categories c ON i.category_id = c.id
            GROUP BY c.id
            ORDER BY items_sold DESC
            LIMIT 10
        ";

        return $this->toArray($this->db->query($sql));
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
