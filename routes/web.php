<?php

/**
 * Application Routes
 * Define all routes for the application
 */

namespace App\Routes;

use App\Core\Router;

/**
 * Initialize router and register all routes
 */
return function (Router $router) {

    // ============================================
    // AUTH ROUTES (Public)
    // ============================================
    $router->get('/', 'AuthController@showHome', 'home');
    $router->get('/about', 'AuthController@showAbout', 'about');
    $router->get('/contact', 'AuthController@showContact', 'contact');
    $router->get('/login', 'AuthController@showLogin', 'login');
    $router->post('/login', 'AuthController@login', 'login.store');
    $router->post('/logout', 'AuthController@logout', 'logout');
    $router->get('/register', 'AuthController@showRegister', 'register');
    $router->get('/password-policy', 'AuthController@showPasswordPolicy', 'password.policy');
    $router->post('/register', 'AuthController@register', 'register.store');

    // ============================================
    // PROTECTED ROUTES (Require Authentication)
    // ============================================

    // DASHBOARD
    $router->get('/dashboard', 'DashboardController@index', 'dashboard');

    // ============================================
    // MANAGER ROUTES (Disabled - controllers not yet implemented)
    // ============================================
    /* DISABLED - coming soon
    $router->group(['prefix' => '/manager', 'middleware' => 'auth.manager'], function ($router) {
        // Coming soon...
    });
    */

    // ============================================
    // STORE KEEPER ROUTES (Disabled - controllers not yet implemented)
    // ============================================
    /* DISABLED - coming soon
    $router->group(['prefix' => '/store-keeper', 'middleware' => 'auth.store_keeper'], function ($router) {
        // Coming soon...
    });

    $router->group(['prefix' => '/seller', 'middleware' => 'auth.seller'], function ($router) {
        // Coming soon...
    });
    */

    // ============================================
    // API ROUTES - AUTHENTICATION
    // ============================================
    $router->post('/api/login', 'UserController@apiLogin', 'api.login');
    $router->post('/api/logout', 'UserController@apiLogout', 'api.logout');
    $router->get('/api/user', 'UserController@apiGetUser', 'api.user');

    // ============================================
    // API ROUTES - PASSWORD RESET
    // ============================================
    $router->post('/api/password/forgot', 'PasswordResetController@apiForgotPassword', 'api.password.forgot');
    $router->post('/api/password/reset', 'PasswordResetController@apiResetPassword', 'api.password.reset');
    $router->get('/api/password/verify-token', 'PasswordResetController@apiVerifyToken', 'api.password.verify');

    // ============================================
    // API ROUTES - USER MANAGEMENT
    // ============================================
    $router->get('/api/users', 'Api\UserManagementController@listUsers', 'api.users.list');
    $router->get('/api/users/{id}', 'Api\UserManagementController@showUser', 'api.users.show');
    $router->post('/api/users', 'Api\UserManagementController@createUser', 'api.users.create');
    $router->put('/api/users/{id}', 'Api\UserManagementController@updateUser', 'api.users.update');
    $router->delete('/api/users/{id}', 'Api\UserManagementController@deleteUser', 'api.users.delete');
    $router->post('/api/users/{id}/reset-password', 'Api\UserManagementController@resetUserPassword', 'api.users.reset-password');
    $router->post('/api/users/{id}/unlock', 'Api\UserManagementController@unlockUser', 'api.users.unlock');

    // ============================================
    // API ROUTES - ROLE MANAGEMENT
    // ============================================
    $router->get('/api/roles', 'Api\RoleManagementController@listRoles', 'api.roles.list');
    $router->get('/api/permissions', 'Api\RoleManagementController@listPermissions', 'api.permissions.list');
    $router->post('/api/roles', 'Api\RoleManagementController@createRole', 'api.roles.create');
    $router->put('/api/roles/{id}', 'Api\RoleManagementController@updateRole', 'api.roles.update');
    $router->delete('/api/roles/{id}', 'Api\RoleManagementController@deleteRole', 'api.roles.delete');

    // ============================================
    // API ROUTES - BRANCH MANAGEMENT
    // ============================================
    $router->get('/api/branches', 'Api\BranchController@listBranches', 'api.branches.list');
    $router->post('/api/branches', 'Api\BranchController@createBranch', 'api.branches.create');
    $router->delete('/api/branches/{id}', 'Api\BranchController@deleteBranch', 'api.branches.delete');

    // ============================================
    // API ROUTES - REPORTS & ANALYTICS
    // ============================================
    $router->get('/api/reports/sales/summary', 'Api\ReportController@getSalesSummary', 'api.reports.sales.summary');
    $router->get('/api/reports/sales/branch', 'Api\ReportController@getSalesByBranch', 'api.reports.sales.branch');
    $router->get('/api/reports/sales/seller', 'Api\ReportController@getSalesBySeller', 'api.reports.sales.seller');
    $router->get('/api/reports/inventory/valuation', 'Api\ReportController@getInventoryValuation', 'api.reports.inventory.valuation');
    $router->get('/api/reports/inventory/low-stock', 'Api\ReportController@getLowStockReport', 'api.reports.inventory.low_stock');

    // ============================================
    // API ROUTES - CHARTS
    // ============================================
    $router->get('/api/charts/sales-trend', 'Api\ChartController@getSalesTrend', 'api.charts.sales-trend');
    $router->get('/api/charts/revenue-by-branch', 'Api\ChartController@getRevenueByBranch', 'api.charts.revenue-by-branch');
    $router->get('/api/charts/category-performance', 'Api\ChartController@getCategoryPerformance', 'api.charts.category-performance');

    // ============================================
    // API ROUTES - EXPORTS
    // ============================================
    $router->get('/api/export/csv/sales', 'Api\ExportController@exportSalesCsv', 'api.export.csv.sales');
    $router->get('/api/export/csv/inventory', 'Api\ExportController@exportInventoryCsv', 'api.export.csv.inventory');
    $router->get('/api/export/csv/activity-logs', 'Api\ExportController@exportActivityLogsCsv', 'api.export.csv.logs');
    $router->get('/api/export/pdf/sales', 'Api\ExportController@exportSalesPdf', 'api.export.pdf.sales');
    $router->get('/api/export/pdf/receipts/{id}', 'Api\ExportController@exportReceiptPdf', 'api.export.pdf.receipt');
    $router->get('/api/export/pdf/custom', 'Api\ExportController@exportCustomReportPdf', 'api.export.pdf.custom');

    // ============================================
    // API ROUTES - DASHBOARD ANALYTICS
    // ============================================
    $router->get('/api/dashboard/manager', 'Api\DashboardController@manager', 'api.dashboard.manager');
    $router->get('/api/dashboard/storekeeper', 'Api\DashboardController@storekeeper', 'api.dashboard.storekeeper');
    $router->get('/api/dashboard/seller', 'Api\DashboardController@seller', 'api.dashboard.seller');

    // ============================================
    // API ROUTES - INVENTORY MANAGEMENT
    // ============================================
    $router->get('/api/inventory', 'Api\InventoryController@index', 'api.inventory.index');
    $router->get('/api/inventory/low-stock', 'Api\InventoryController@lowStock', 'api.inventory.low_stock');
    $router->get('/api/inventory/history', 'Api\InventoryController@history', 'api.inventory.history');
    $router->post('/api/inventory/adjust', 'Api\InventoryController@adjust', 'api.inventory.adjust');

    // ============================================
    // API ROUTES - TRANSFER MANAGEMENT
    // ============================================
    $router->get('/api/transfers', 'Api\TransferController@index', 'api.transfers.index');
    $router->post('/api/transfers', 'Api\TransferController@store', 'api.transfers.store');
    $router->put('/api/transfers/{id}/status', 'Api\TransferController@updateStatus', 'api.transfers.updateStatus');

    // ============================================
    // API ROUTES - SALES MANAGEMENT (POS)
    // ============================================
    $router->get('/api/sales', 'Api\SalesController@index', 'api.sales.index');
    $router->post('/api/sales', 'Api\SalesController@store', 'api.sales.store');
    $router->get('/api/sales/{id}', 'Api\SalesController@show', 'api.sales.show');

    // ============================================
    // API ROUTES (Optional - JSON responses) - DISABLED
    // ============================================
    /*
    $router->group(['prefix' => '/api/v1'], function ($router) {

        // Sales API
        $router->get('/sales', 'Api\SalesController@index', 'api.sales.index');
        $router->post('/sales', 'Api\SalesController@store', 'api.sales.store');

        // Branch API
        $router->get('/branches', 'Api\BranchController@index', 'api.branches.index');
    });
    */

    // ============================================
    // API ROUTES - PRODUCTS & CATEGORIES
    // NOTE: specific paths must come before {id} wildcard routes
    // ============================================

    // Categories (specific — must be registered before /api/products/{id})
    $router->get('/api/products/categories', 'ProductController@apiCategories', 'api.categories.index');
    $router->post('/api/products/categories', 'ProductController@apiCreateCategory', 'api.categories.store');
    $router->delete('/api/products/categories/{id}', 'ProductController@apiDeleteCategory', 'api.categories.destroy');

    // Products CRUD
    $router->get('/api/products', 'ProductController@index', 'api.products.index');
    $router->post('/api/products', 'ProductController@store', 'api.products.store');
    $router->get('/api/products/{id}', 'ProductController@show', 'api.products.show');
    $router->put('/api/products/{id}', 'ProductController@update', 'api.products.update');
    $router->delete('/api/products/{id}', 'ProductController@destroy', 'api.products.destroy');
};
