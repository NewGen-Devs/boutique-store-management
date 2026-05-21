<?php

/**
 * Seed default permissions and roles
 * Run manually: php seed.php
 */

// Get database connection
require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Database;

$db = Database::getInstance();

echo "🌱 Seeding permissions and roles...\n\n";

// Define permissions
$permissions = [
    // User Management
    ['name' => 'users.create', 'resource' => 'users', 'action' => 'create', 'description' => 'Create new users'],
    ['name' => 'users.read', 'resource' => 'users', 'action' => 'read', 'description' => 'View users'],
    ['name' => 'users.update', 'resource' => 'users', 'action' => 'update', 'description' => 'Update users'],
    ['name' => 'users.delete', 'resource' => 'users', 'action' => 'delete', 'description' => 'Delete users'],

    // Role Management
    ['name' => 'roles.create', 'resource' => 'roles', 'action' => 'create', 'description' => 'Create new roles'],
    ['name' => 'roles.read', 'resource' => 'roles', 'action' => 'read', 'description' => 'View roles'],
    ['name' => 'roles.update', 'resource' => 'roles', 'action' => 'update', 'description' => 'Update roles'],
    ['name' => 'roles.delete', 'resource' => 'roles', 'action' => 'delete', 'description' => 'Delete roles'],

    // Inventory Management
    ['name' => 'inventory.read', 'resource' => 'inventory', 'action' => 'read', 'description' => 'View inventory'],
    ['name' => 'inventory.create', 'resource' => 'inventory', 'action' => 'create', 'description' => 'Add inventory items'],
    ['name' => 'inventory.update', 'resource' => 'inventory', 'action' => 'update', 'description' => 'Update inventory'],
    ['name' => 'inventory.delete', 'resource' => 'inventory', 'action' => 'delete', 'description' => 'Delete inventory items'],

    // Sales Management
    ['name' => 'sales.read', 'resource' => 'sales', 'action' => 'read', 'description' => 'View sales'],
    ['name' => 'sales.create', 'resource' => 'sales', 'action' => 'create', 'description' => 'Create sales'],

    // Reports
    ['name' => 'reports.read', 'resource' => 'reports', 'action' => 'read', 'description' => 'View reports'],
];

// Helper: convert mysqli_result to array
function resultToArray($result)
{
    if (is_array($result))
        return $result;
    if ($result instanceof \mysqli_result) {
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }
    return [];
}

// Insert permissions
$permissionIds = [];
echo "Adding permissions...\n";
foreach ($permissions as $perm) {
    try {
        $result = $db->query("SELECT id FROM permissions WHERE name = ?", [$perm['name']]);
        $existing = resultToArray($result);

        if (empty($existing)) {
            $permId = $db->insert('permissions', [
                'name' => $perm['name'],
                'permission' => $perm['name'],
                'resource' => $perm['resource'],
                'action' => $perm['action'],
                'description' => $perm['description'] ?? null,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $permissionIds[$perm['name']] = $permId;
            echo "  ✓ {$perm['name']}\n";
        } else {
            $permissionIds[$perm['name']] = $existing[0]['id'];
            echo "  ~ {$perm['name']} (exists)\n";
        }
    } catch (\Exception $e) {
        echo "  ✗ {$perm['name']}: {$e->getMessage()}\n";
    }
}

echo "\n";

// Define roles
$roles = [
    [
        'name' => 'manager',
        'description' => 'Full system access',
        'permissions' => [
            'users.create',
            'users.read',
            'users.update',
            'users.delete',
            'roles.create',
            'roles.read',
            'roles.update',
            'roles.delete',
            'inventory.read',
            'inventory.create',
            'inventory.update',
            'inventory.delete',
            'sales.read',
            'sales.create',
            'reports.read'
        ]
    ],
    [
        'name' => 'store_keeper',
        'description' => 'Inventory management only',
        'permissions' => [
            'inventory.read',
            'inventory.create',
            'inventory.update',
            'sales.read',
            'reports.read'
        ]
    ],
    [
        'name' => 'seller',
        'description' => 'POS and sales only',
        'permissions' => [
            'inventory.read',
            'sales.read',
            'sales.create'
        ]
    ],
    [
        'name' => 'viewer',
        'description' => 'Read-only access',
        'permissions' => [
            'inventory.read',
            'sales.read',
            'reports.read'
        ]
    ]
];

// Insert roles
echo "Adding roles...\n";
$roleIds = [];
foreach ($roles as $role) {
    try {
        $result = $db->query("SELECT id FROM roles WHERE name = ?", [$role['name']]);
        $existing = resultToArray($result);

        if (empty($existing)) {
            $roleId = $db->insert('roles', [
                'name' => $role['name'],
                'description' => $role['description'],
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $roleIds[$role['name']] = $roleId;
            echo "  ✓ {$role['name']}\n";

            // Attach permissions to role
            foreach ($role['permissions'] as $permName) {
                if (isset($permissionIds[$permName])) {
                    try {
                        $db->insert('role_permissions', [
                            'role_id' => $roleId,
                            'permission_id' => $permissionIds[$permName],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    } catch (\Exception $e) {
                        // Permission might already be assigned
                    }
                }
            }
        } else {
            $roleIds[$role['name']] = $existing[0]['id'];
            echo "  ~ {$role['name']} (exists)\n";
        }
    } catch (\Exception $e) {
        echo "  ✗ {$role['name']}: {$e->getMessage()}\n";
    }
}

echo "\n✨ Seeding complete!\n";
