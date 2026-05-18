<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Branch;

class BranchController extends Controller
{
    /**
     * Get all active branches
     */
    public function index()
    {
        try {
            $branches = Branch::all();
            
            // Convert models to array
            $data = array_map(function($branch) {
                return $branch->toArray();
            }, $branches);
            
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $data]);
            exit;
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}
