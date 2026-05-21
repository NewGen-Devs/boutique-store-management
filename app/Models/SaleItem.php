<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class SaleItem extends Model
{
    protected $table = 'sales_items';

    /**
     * Fetch all items belonging to a specific sale
     * Join with items to get the product name
     */
    public static function getItemsBySaleId($saleId)
    {
        $db = Database::getInstance();
        $sql = "SELECT si.*, i.name as item_name, i.sku
                FROM sales_items si
                JOIN items i ON si.item_id = i.id
                WHERE si.sales_id = ?";

        $res = $db->query($sql, [$saleId]);

        $items = [];
        while ($row = $res->fetch_assoc()) {
            $items[] = $row;
        }

        return $items;
    }
}
