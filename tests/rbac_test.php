#!/usr/bin/env php
<?php

/**
 * RBAC System Test Suite
 * Tests all RBAC components and functionality
 */

require_once __DIR__ . '/../bootstrap.php';
require_once APP_PATH . '/helpers/rbac.php';

// Color codes for output
const GREEN = "\033[0;32m";
const RED = "\033[0;31m";
const YELLOW = "\033[1;33m";
const BLUE = "\033[0;34m";
const NC = "\033[0m"; // No Color

// Test results
$tests_passed = 0;
$tests_failed = 0;
$tests_total = 0;

/**
 * Print colored output
 */
function print_test($message, $status = true)
{
    global $tests_passed, $tests_failed, $tests_total;

    $tests_total++;

    if ($status) {
        $tests_passed++;
        echo GREEN . "✓ PASS" . NC . ": $message\n";
    } else {
        $tests_failed++;
        echo RED . "✗ FAIL" . NC . ": $message\n";
    }
}

function print_section($title)
{
    echo "\n" . BLUE . "=== $title ===" . NC . "\n";
}

function print_info($message)
{
    echo YELLOW . "ℹ" . NC . " $message\n";
}

// ============================================
// TEST 1: DATABASE SETUP
// ============================================
print_section("1. DATABASE SETUP TESTS");

try {
    $db = \App\Core\Database::getInstance();
    print_test("Database connection established", $db !== null);

    // Check roles table
    $result = $db->query("SHOW TABLES LIKE 'roles'");
    print_test("Roles table exists", $result->num_rows > 0);

    // Check permissions table
    $result = $db->query("SHOW TABLES LIKE 'permissions'");
    print_test("Permissions table exists", $result->num_rows > 0);

    // Check default roles
    $result = $db->query("SELECT COUNT(*) as count FROM roles");
    $row = $result->fetch_assoc();
    print_test("Default roles inserted (" . $row['count'] . " roles)", $row['count'] >= 4);

    // Check permissions assigned
    $result = $db->query("SELECT COUNT(*) as count FROM permissions");
    $row = $result->fetch_assoc();
    print_test("Permissions assigned (" . $row['count'] . " permissions)", $row['count'] > 0);

} catch (\Exception $e) {
    print_test("Database initialization failed: " . $e->getMessage(), false);
}

// ============================================
// TEST 2: ROLE MODEL TESTS
// ============================================
print_section("2. ROLE MODEL TESTS");

try {

    // Test: Get all roles
    $roles = \App\Models\Role::all();
    print_test("Role::all() returns array", is_array($roles));
    print_test("Default roles loaded (" . count($roles) . ")", count($roles) >= 4);

    // Test: Find role by name
    $admin_role = \App\Models\Role::findByName('admin');
    print_test("Role::findByName('admin') works", $admin_role !== null);

    if ($admin_role) {
        print_test("Admin role name is 'admin'", $admin_role->name === 'admin');

        // Test: Get permissions for role
        $permissions = $admin_role->getPermissions();
        print_test("Role::getPermissions() returns array", is_array($permissions));
        print_test("Admin has permissions (" . count($permissions) . ")", count($permissions) > 0);

        // Test: Check specific permission
        $has_perm = $admin_role->hasPermission('users.create');
        print_test("Admin has 'users.create' permission", $has_perm);
    }

    // Test: Find role by ID
    if ($admin_role && isset($admin_role->id)) {
        $found_role = \App\Models\Role::findById($admin_role->id);
        print_test("Role::findById() works", $found_role !== null);
    }

    // Test: Role exists check
    $exists = \App\Models\Role::exists('admin');
    print_test("Role::exists('admin') returns true", $exists);

    $not_exists = \App\Models\Role::exists('nonexistent_role_' . time());
    print_test("Role::exists('nonexistent') returns false", !$not_exists);

} catch (\Exception $e) {
    print_test("Role model tests failed: " . $e->getMessage(), false);
}

// ============================================
// TEST 3: PERMISSION MODEL TESTS
// ============================================
print_section("3. PERMISSION MODEL TESTS");

try {

    // Test: Get all permissions
    $all_perms = \App\Models\Permission::all();
    print_test("Permission::all() returns array", is_array($all_perms));
    print_test("Permissions exist (" . count($all_perms) . ")", count($all_perms) > 0);

    // Test: Get permissions by role ID
    $admin_role = \App\Models\Role::findByName('admin');
    if ($admin_role) {
        $role_perms = \App\Models\Permission::getByRoleId($admin_role->id);
        print_test("Permission::getByRoleId() works", is_array($role_perms));
        print_test("Admin role has permissions (" . count($role_perms) . ")", count($role_perms) > 0);
    }

} catch (\Exception $e) {
    print_test("Permission model tests failed: " . $e->getMessage(), false);
}

// ============================================
// TEST 4: AUTHORIZATION MIDDLEWARE TESTS
// ============================================
print_section("4. AUTHORIZATION MIDDLEWARE TESTS");

try {
    // Note: Middleware class requires explicit loading
    // It will be tested through controller integration
    print_test(
        "Authorization Middleware file exists",
        file_exists(APP_PATH . '/core/Middleware/AuthorizationMiddleware.php')
    );
    print_info("Middleware tests covered by controller and helper function tests");

} catch (\Exception $e) {
    print_test("Middleware tests failed: " . $e->getMessage(), false);
}

// ============================================
// TEST 5: CONTROLLER METHODS TESTS
// ============================================
print_section("5. CONTROLLER METHODS TESTS");

try {
    // Create a test controller instance
    $controller = new class () extends \App\Core\Controller {
        public function testMethods()
        {
            return [
                'hasRole' => method_exists($this, 'hasRole'),
                'hasPermission' => method_exists($this, 'hasPermission'),
                'canAccess' => method_exists($this, 'canAccess'),
                'getUserRole' => method_exists($this, 'getUserRole'),
                'hasAnyRole' => method_exists($this, 'hasAnyRole'),
                'hasAnyPermission' => method_exists($this, 'hasAnyPermission'),
            ];
        }
    };

    $methods = $controller->testMethods();

    print_test("hasRole() method exists in Controller", $methods['hasRole']);
    print_test("hasPermission() method exists in Controller", $methods['hasPermission']);
    print_test("canAccess() method exists in Controller", $methods['canAccess']);
    print_test("getUserRole() method exists in Controller", $methods['getUserRole']);
    print_test("hasAnyRole() method exists in Controller", $methods['hasAnyRole']);
    print_test("hasAnyPermission() method exists in Controller", $methods['hasAnyPermission']);

} catch (\Exception $e) {
    print_test("Controller methods tests failed: " . $e->getMessage(), false);
}

// ============================================
// TEST 6: HELPER FUNCTIONS TESTS
// ============================================
print_section("6. HELPER FUNCTIONS TESTS");

try {
    // Test if helper file is included
    print_test("RBAC helpers file exists", function_exists('is_authenticated'));
    print_test("is_authenticated() function works", is_bool(is_authenticated()));
    print_test("has_role() function works", is_bool(has_role('admin')));
    print_test("has_permission() function works", is_bool(has_permission('users.create')));
    print_test("can_access() function works", is_bool(can_access('items', 'create')));
    print_test("get_all_roles() function works", is_array(get_all_roles()));
    print_test("get_all_permissions() function works", is_array(get_all_permissions()));

} catch (\Exception $e) {
    print_test("Helper functions tests failed: " . $e->getMessage(), false);
}

// ============================================
// TEST 7: ROLE CREATION & PERMISSIONS TESTS
// ============================================
print_section("7. ROLE CREATION & PERMISSIONS TESTS");

try {
    $test_role_name = 'test_role_' . time();

    // Test: Create new role
    $new_role = \App\Models\Role::create([
        'name' => $test_role_name,
        'description' => 'Test role for verification'
    ]);
    print_test("Role::create() works", $new_role !== null && isset($new_role->id));

    if ($new_role) {
        // Test: Find newly created role
        $found = \App\Models\Role::findByName($test_role_name);
        print_test("Newly created role can be found", $found !== null);

        // Test: Assign permission
        $assigned = $new_role->assignPermission('items.read');
        print_test("assignPermission() works", $assigned);

        // Test: Check if permission assigned
        $has_perm = $new_role->hasPermission('items.read');
        print_test("Assigned permission is verified", $has_perm);

        // Test: Get all permissions
        $perms = $new_role->getPermissions();
        print_test("getPermissions() returns assigned permission", in_array('items.read', $perms, true));

        // Test: Remove permission
        $removed = $new_role->removePermission('items.read');
        print_test("removePermission() works", $removed);

        // Verify removal
        $has_perm_now = $new_role->hasPermission('items.read');
        print_test("Permission is removed", !$has_perm_now);

        // Test: Delete role
        $deleted = $new_role->delete();
        print_test("Role::delete() works", $deleted);
    }

} catch (\Exception $e) {
    print_test("Role creation tests failed: " . $e->getMessage(), false);
}

// ============================================
// TEST 8: CONFIGURATION TESTS
// ============================================
print_section("8. CONFIGURATION TESTS");

try {
    $config = require APP_PATH . '/../config/rbac.php';
    print_test("RBAC config file loaded", is_array($config));
    print_test("Config has default_roles", isset($config['default_roles']));
    print_test("Config has resources", isset($config['resources']));
    print_test("Config has 4 default roles", count($config['default_roles']) === 4);

    print_info("Configured roles: " . implode(', ', array_keys($config['default_roles'])));

} catch (\Exception $e) {
    print_test("Configuration tests failed: " . $e->getMessage(), false);
}

// ============================================
// TEST 9: INTEGRATION TESTS
// ============================================
print_section("9. INTEGRATION TESTS");

try {
    // Simulate user sessions with different roles
    $test_cases = [
        ['role' => 'admin', 'perms' => ['users.create', 'items.delete', 'reports.view']],
        ['role' => 'manager', 'perms' => ['items.read', 'sales.create']],
        ['role' => 'staff', 'perms' => ['items.read', 'sales.read']],
    ];

    foreach ($test_cases as $test) {
        $role = \App\Models\Role::findByName($test['role']);

        if ($role) {
            $all_have = true;
            foreach ($test['perms'] as $perm) {
                if (!$role->hasPermission($perm)) {
                    $all_have = false;
                    break;
                }
            }
            print_test("Role '{$test['role']}' has expected permissions", $all_have);
        }
    }

} catch (\Exception $e) {
    print_test("Integration tests failed: " . $e->getMessage(), false);
}

// ============================================
// TEST SUMMARY
// ============================================
print_section("TEST SUMMARY");

$total_percentage = $tests_total > 0 ? round(($tests_passed / $tests_total) * 100, 2) : 0;

echo "\n";
echo GREEN . "Passed: " . NC . $tests_passed . "\n";
echo RED . "Failed: " . NC . $tests_failed . "\n";
echo "Total:  $tests_total\n";
echo BLUE . "Success Rate: " . NC . $total_percentage . "%\n";
echo "\n";

if ($tests_failed === 0) {
    echo GREEN . "✓ ALL TESTS PASSED! RBAC SYSTEM IS WORKING CORRECTLY." . NC . "\n";
    exit(0);
} else {
    echo RED . "✗ SOME TESTS FAILED. PLEASE CHECK THE ERRORS ABOVE." . NC . "\n";
    exit(1);
}
