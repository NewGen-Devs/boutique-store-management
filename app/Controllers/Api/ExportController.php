<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;

class ExportController extends Controller
{
    protected $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
    }

    /**
     * =============================================
     * CSV EXPORTS
     * =============================================
     */

    public function exportSalesCsv()
    {
        if (!$this->isAuthenticated() || !$this->hasPermission('reports.read')) {
            $this->respondJsonError('Unauthorized', 403);
            return;
        }

        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');

        $query = "SELECT s.transaction_number, b.name as branch, u.username as seller, 
                         s.total_amount, s.discount_amount, s.final_amount, s.payment_method, s.created_at
                  FROM sales s
                  LEFT JOIN branches b ON s.branch_id = b.id
                  LEFT JOIN users u ON s.user_id = u.id
                  WHERE DATE(s.created_at) BETWEEN ? AND ?
                  ORDER BY s.created_at DESC";

        $result = $this->db->query($query, [$startDate, $endDate]);

        $filename = "sales_report_{$startDate}_to_{$endDate}.csv";
        $headers = ['Transaction Number', 'Branch', 'Seller', 'Total', 'Discount', 'Final Amount', 'Payment Method', 'Date'];

        $this->outputCsv($filename, $headers, $result);
    }

    public function exportInventoryCsv()
    {
        if (!$this->isAuthenticated() || !$this->hasPermission('reports.read')) {
            $this->respondJsonError('Unauthorized', 403);
            return;
        }

        $query = "SELECT i.sku, i.name, c.name as category, i.cost_price, i.selling_price, 
                         COALESCE(SUM(st.quantity), 0) as total_quantity
                  FROM items i
                  LEFT JOIN categories c ON i.category_id = c.id
                  LEFT JOIN stock st ON i.id = st.item_id
                  WHERE i.is_active = 1
                  GROUP BY i.id
                  ORDER BY i.name ASC";

        $result = $this->db->query($query);

        $filename = "inventory_report_" . date('Y_m_d') . ".csv";
        $headers = ['SKU', 'Item Name', 'Category', 'Cost Price', 'Selling Price', 'Total Quantity'];

        $this->outputCsv($filename, $headers, $result);
    }

    public function exportActivityLogsCsv()
    {
        if (!$this->isAuthenticated() || !$this->hasPermission('reports.read')) {
            $this->respondJsonError('Unauthorized', 403);
            return;
        }

        $query = "SELECT a.id, u.username, a.action, a.entity_type, a.entity_id, a.ip_address, a.created_at
                  FROM audit_logs a
                  LEFT JOIN users u ON a.user_id = u.id
                  ORDER BY a.created_at DESC
                  LIMIT 5000";

        $result = $this->db->query($query);

        $filename = "activity_logs_" . date('Y_m_d') . ".csv";
        $headers = ['ID', 'User', 'Action', 'Entity Type', 'Entity ID', 'IP Address', 'Date'];

        $this->outputCsv($filename, $headers, $result);
    }

    /**
     * =============================================
     * PDF EXPORTS
     * =============================================
     */

    public function exportSalesPdf()
    {
        if (!$this->isAuthenticated() || !$this->hasPermission('reports.read')) {
            $this->respondJsonError('Unauthorized', 403);
            return;
        }

        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');

        $query = "SELECT s.transaction_number, b.name as branch, u.username as seller, 
                         s.final_amount, s.created_at
                  FROM sales s
                  LEFT JOIN branches b ON s.branch_id = b.id
                  LEFT JOIN users u ON s.user_id = u.id
                  WHERE DATE(s.created_at) BETWEEN ? AND ?
                  ORDER BY s.created_at DESC";

        $result = $this->db->query($query, [$startDate, $endDate]);

        $html = '<h2>Sales Report (' . $startDate . ' to ' . $endDate . ')</h2>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%" style="border-collapse:collapse; text-align:left;">';
        $html .= '<tr><th>Date</th><th>TXN</th><th>Branch</th><th>Seller</th><th>Amount</th></tr>';

        $total = 0;
        while ($row = $result->fetch_assoc()) {
            $amount = (float) $row['final_amount'];
            $total += $amount;
            $html .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>$%.2f</td></tr>',
                substr($row['created_at'], 0, 10),
                $row['transaction_number'],
                $row['branch'] ?? 'N/A',
                $row['seller'] ?? 'N/A',
                $amount
            );
        }
        $html .= sprintf('<tr><th colspan="4" style="text-align:right">Total</th><th>$%.2f</th></tr>', $total);
        $html .= '</table>';

        $filename = "sales_report_{$startDate}_to_{$endDate}.pdf";
        $this->outputPdf($html, $filename);
    }

    public function exportReceiptPdf($id)
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 403);
            return;
        }

        // Fetch sales data
        $query = "SELECT s.*, b.name as branch_name, u.username as seller_name 
                  FROM sales s
                  LEFT JOIN branches b ON s.branch_id = b.id
                  LEFT JOIN users u ON s.user_id = u.id
                  WHERE s.id = ?";
        $res = $this->db->query($query, [$id]);
        $sale = $res->fetch_assoc();

        if (!$sale) {
            $this->respondJsonError('Receipt not found', 404);
            return;
        }

        // Fetch items
        $iQuery = "SELECT si.quantity, si.unit_price, si.subtotal, i.name
                   FROM sales_items si
                   JOIN items i ON si.item_id = i.id
                   WHERE si.sales_id = ?";
        $itemsRes = $this->db->query($iQuery, [$id]);

        $html = '<div style="font-family: Arial, sans-serif; max-width: 400px; margin: 0 auto; text-align:center;">';
        $html .= '<h2>Boutique Store</h2>';
        $html .= '<p>' . ($sale['branch_name'] ?? 'Main Branch') . '</p>';
        $html .= '<hr/>';
        $html .= '<p style="text-align:left"><strong>TXN:</strong> ' . $sale['transaction_number'] . '<br/>';
        $html .= '<strong>Date:</strong> ' . $sale['created_at'] . '<br/>';
        $html .= '<strong>Seller:</strong> ' . ($sale['seller_name'] ?? 'System') . '</p>';
        $html .= '<table width="100%" style="font-size:12px; margin-top:20px; text-align:left;">';
        $html .= '<tr><th style="border-bottom:1px solid #ccc; padding-bottom:5px;">Item</th><th style="border-bottom:1px solid #ccc; padding-bottom:5px;">Qty</th><th style="border-bottom:1px solid #ccc; padding-bottom:5px;">Total</th></tr>';

        while ($row = $itemsRes->fetch_assoc()) {
            $html .= sprintf(
                '<tr><td style="padding:5px 0;">%s</td><td style="padding:5px 0;">%s</td><td style="padding:5px 0;">$%.2f</td></tr>',
                $row['name'],
                $row['quantity'],
                (float) $row['subtotal']
            );
        }
        $html .= '</table>';
        $html .= '<hr/>';
        $html .= '<p style="text-align:right"><strong>Subtotal:</strong> $' . number_format($sale['total_amount'], 2) . '<br/>';
        $html .= '<strong>Discount:</strong> $' . number_format($sale['discount_amount'], 2) . '<br/>';
        $html .= '<strong style="font-size:16px;">Final: $' . number_format($sale['final_amount'], 2) . '</strong></p>';
        $html .= '<p style="margin-top:30px;">Thank you for your purchase!</p>';
        $html .= '</div>';

        $this->outputPdf($html, "receipt_{$sale['transaction_number']}.pdf", 'portrait', [0, 0, 226, 600]);
    }

    public function exportCustomReportPdf()
    {
        if (!$this->isAuthenticated() || !$this->hasPermission('reports.read')) {
            $this->respondJsonError('Unauthorized', 403);
            return;
        }

        $type = $_GET['type'] ?? 'general';

        $html = "<h2>Custom Report ({$type})</h2>";
        $html .= "<p>Generated on " . date('Y-m-d H:i:s') . "</p>";
        $html .= "<p>This is a dynamically generated custom report for your store.</p>";

        $this->outputPdf($html, "custom_report_{$type}.pdf");
    }

    /**
     * =============================================
     * HELPER METHODS
     * =============================================
     */

    private function outputCsv($filename, array $headers, $result)
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);

        if ($result instanceof \mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, array_values($row));
            }
        }

        fclose($output);
        exit;
    }

    private function outputPdf($html, $filename, $orientation = 'portrait', $paperSize = 'A4')
    {
        // Require DOMPDF
        if (!class_exists('Dompdf\Dompdf')) {
            $this->respondJsonError('PDF generation library not installed. Run composer require dompdf/dompdf.', 500);
            return;
        }

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($paperSize, $orientation);
        $dompdf->render();

        $dompdf->stream($filename, ["Attachment" => true]);
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
