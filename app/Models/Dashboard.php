<?php

namespace App\Models;

use App\Core\Database;

class Dashboard
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ——— Manager Dashboard ———

    /**
     * KPI widgets: today's revenue, total products, active branches, active users
     */
    public function getManagerKpis()
    {
        $today = date('Y-m-d');

        // Today's revenue
        $revResult = $this->toArray($this->db->query(
            "SELECT COALESCE(SUM(final_amount), 0) as today_revenue, COUNT(*) as today_transactions FROM sales WHERE DATE(created_at) = ?",
            [$today]
        ));

        // Active products count
        $prodResult = $this->toArray($this->db->query(
            "SELECT COUNT(*) as total_products FROM items WHERE is_active = 1"
        ));

        // Active branches
        $branchResult = $this->toArray($this->db->query(
            "SELECT COUNT(*) as active_branches FROM branches WHERE is_active = 1"
        ));

        // Active users
        $userResult = $this->toArray($this->db->query(
            "SELECT COUNT(*) as active_users FROM users WHERE is_active = 1"
        ));

        // Month revenue
        $monthResult = $this->toArray($this->db->query(
            "SELECT COALESCE(SUM(final_amount), 0) as month_revenue FROM sales WHERE DATE(created_at) BETWEEN ? AND ?",
            [date('Y-m-01'), $today]
        ));

        return [
            'today_revenue' => $revResult[0]['today_revenue'] ?? 0,
            'today_transactions' => $revResult[0]['today_transactions'] ?? 0,
            'total_products' => $prodResult[0]['total_products'] ?? 0,
            'active_branches' => $branchResult[0]['active_branches'] ?? 0,
            'active_users' => $userResult[0]['active_users'] ?? 0,
            'month_revenue' => $monthResult[0]['month_revenue'] ?? 0
        ];
    }

    /**
     * Recent transactions (last 10)
     */
    public function getRecentTransactions($limit = 10)
    {
        $query = "SELECT 
                    s.transaction_number,
                    s.final_amount,
                    s.payment_method,
                    s.created_at,
                    u.first_name as seller_first,
                    u.last_name as seller_last,
                    b.name as branch_name
                  FROM sales s
                  LEFT JOIN users u ON s.user_id = u.id
                  LEFT JOIN branches b ON s.branch_id = b.id
                  ORDER BY s.created_at DESC
                  LIMIT ?";

        return $this->toArray($this->db->query($query, [$limit]));
    }

    /**
     * Top performing sellers this month
     */
    public function getTopSellers($limit = 5)
    {
        $query = "SELECT 
                    u.first_name, u.last_name,
                    COUNT(s.id) as sale_count,
                    COALESCE(SUM(s.final_amount), 0) as total_revenue
                  FROM users u
                  JOIN sales s ON u.id = s.user_id
                  WHERE DATE(s.created_at) BETWEEN ? AND ?
                  GROUP BY u.id, u.first_name, u.last_name
                  ORDER BY total_revenue DESC
                  LIMIT ?";

        return $this->toArray($this->db->query($query, [date('Y-m-01'), date('Y-m-d'), $limit]));
    }

    /**
     * Revenue by branch (this month)
     */
    public function getRevenuByBranch()
    {
        $query = "SELECT 
                    b.name as branch_name,
                    COALESCE(SUM(s.final_amount), 0) as revenue,
                    COUNT(s.id) as transactions
                  FROM branches b
                  LEFT JOIN sales s ON b.id = s.branch_id AND DATE(s.created_at) BETWEEN ? AND ?
                  WHERE b.is_active = 1
                  GROUP BY b.id, b.name
                  ORDER BY revenue DESC";

        return $this->toArray($this->db->query($query, [date('Y-m-01'), date('Y-m-d')]));
    }

    /**
     * Low stock alerts (items at or below reorder level)
     */
    public function getLowStockAlerts($limit = 5)
    {
        $query = "SELECT 
                    i.name, i.sku,
                    COALESCE(st.quantity, 0) as current_stock,
                    i.reorder_level
                  FROM items i
                  LEFT JOIN stock st ON i.id = st.item_id
                  WHERE i.is_active = 1 AND COALESCE(st.quantity, 0) <= i.reorder_level
                  ORDER BY current_stock ASC
                  LIMIT ?";

        return $this->toArray($this->db->query($query, [$limit]));
    }

    // ——— Store Keeper Dashboard ———

    public function getStoreKeeperMetrics()
    {
        // Total inventory items
        $items = $this->toArray($this->db->query(
            "SELECT COUNT(*) as total_items FROM items WHERE is_active = 1"
        ));

        // Low stock count
        $lowStock = $this->toArray($this->db->query(
            "SELECT COUNT(*) as low_stock_count FROM items i LEFT JOIN stock st ON i.id = st.item_id WHERE i.is_active = 1 AND COALESCE(st.quantity, 0) <= i.reorder_level"
        ));

        // Inventory value
        $value = $this->toArray($this->db->query(
            "SELECT COALESCE(SUM(i.cost_price), 0) as cost_value, COALESCE(SUM(i.selling_price), 0) as retail_value FROM items i WHERE i.is_active = 1"
        ));

        // Recent stock movements (from stock_history)
        $movements = $this->toArray($this->db->query(
            "SELECT sh.*, i.name as item_name FROM stock_history sh JOIN items i ON sh.item_id = i.id ORDER BY sh.created_at DESC LIMIT 10"
        ));

        return [
            'total_items' => $items[0]['total_items'] ?? 0,
            'low_stock_count' => $lowStock[0]['low_stock_count'] ?? 0,
            'cost_value' => $value[0]['cost_value'] ?? 0,
            'retail_value' => $value[0]['retail_value'] ?? 0,
            'recent_movements' => $movements
        ];
    }

    // ——— Seller Dashboard ———

    public function getSellerMetrics($userId)
    {
        $today = date('Y-m-d');

        // Personal sales today
        $todaySales = $this->toArray($this->db->query(
            "SELECT COUNT(*) as count, COALESCE(SUM(final_amount), 0) as revenue FROM sales WHERE user_id = ? AND DATE(created_at) = ?",
            [$userId, $today]
        ));

        // Personal sales this month
        $monthSales = $this->toArray($this->db->query(
            "SELECT COUNT(*) as count, COALESCE(SUM(final_amount), 0) as revenue FROM sales WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?",
            [$userId, date('Y-m-01'), $today]
        ));

        // Top selling items by this seller
        $topItems = $this->toArray($this->db->query(
            "SELECT i.name, SUM(si.quantity) as qty_sold, SUM(si.subtotal) as revenue
             FROM sales_items si
             JOIN sales s ON si.sales_id = s.id
             JOIN items i ON si.item_id = i.id
             WHERE s.user_id = ? AND DATE(s.created_at) BETWEEN ? AND ?
             GROUP BY i.id, i.name
             ORDER BY qty_sold DESC LIMIT 5",
            [$userId, date('Y-m-01'), $today]
        ));

        return [
            'today_sales' => $todaySales[0]['count'] ?? 0,
            'today_revenue' => $todaySales[0]['revenue'] ?? 0,
            'month_sales' => $monthSales[0]['count'] ?? 0,
            'month_revenue' => $monthSales[0]['revenue'] ?? 0,
            'top_items' => $topItems
        ];
    }

    /**
     * Convert mysqli_result to array
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
