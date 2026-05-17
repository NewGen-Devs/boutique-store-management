<?php

/**
 * Role Model - Handles role management and role queries
 */

namespace App\Models;

use App\Core\Model;

class Role extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id';

    /**
     * ==========================================
     * QUERY METHODS
     * ==========================================
     */

    /**
     * Get all roles
     */
    public static function all()
    {
        $instance = new static();
        $result = $instance->db->query("SELECT * FROM {$instance->table} ORDER BY name ASC");

        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = new static($row);
        }
        return $records;
    }

    /**
     * Find role by ID
     */
    public static function findById($id)
    {
        return static::find($id);
    }

    /**
     * Find role by name
     */
    public static function findByName($name)
    {
        return static::where('name', $name);
    }

    /**
     * Check if role exists
     */
    public static function exists($name)
    {
        $role = static::where('name', $name);
        return $role !== null;
    }

    /**
     * ==========================================
     * CREATE/UPDATE METHODS
     * ==========================================
     */

    /**
     * Create new role
     */
    public static function create($data)
    {
        if (empty($data['name'])) {
            throw new \Exception('Role name is required');
        }

        if (static::exists($data['name'])) {
            throw new \Exception('Role already exists');
        }

        $role = new static([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $role->save();
        return $role;
    }

    /**
     * Update role
     */
    public function update($data = [])
    {
        if (isset($data['name']) && $data['name'] !== $this->name) {
            if (static::where('name', $data['name'])) {
                throw new \Exception('Role name already exists');
            }
            $this->name = $data['name'];
        }

        if (isset($data['description'])) {
            $this->description = $data['description'];
        }

        $this->save();
        return $this;
    }

    /**
     * Delete role
     */
    public function delete()
    {
        // Check if role is assigned to users
        $userCount = $this->db->query(
            "SELECT COUNT(*) as count FROM users WHERE role_id = ?",
            [$this->id]
        )->fetch_assoc();

        if ($userCount['count'] > 0) {
            throw new \Exception('Cannot delete role that is assigned to users');
        }

        return parent::delete();
    }

    /**
     * ==========================================
     * PERMISSION METHODS
     * ==========================================
     */

    /**
     * Get all permissions for this role
     */
    public function getPermissions()
    {
        if (!isset($this->id)) {
            return [];
        }

        // Try new schema first (role_permissions table)
        $result = $this->db->query(
            "SELECT p.permission FROM permissions p 
             JOIN role_permissions rp ON p.id = rp.permission_id 
             WHERE rp.role_id = ? ORDER BY p.permission ASC",
            [$this->id]
        );

        $permissions = [];
        if (is_array($result)) {
            foreach ($result as $row) {
                $permissions[] = $row['permission'];
            }
        } elseif (is_object($result) && method_exists($result, 'fetch_assoc')) {
            while ($row = $result->fetch_assoc()) {
                $permissions[] = $row['permission'];
            }
        }

        // If no permissions found in new schema, try old schema
        if (empty($permissions)) {
            $result2 = $this->db->query(
                "SELECT permission FROM permissions 
                 WHERE role_id = ? ORDER BY permission ASC",
                [$this->id]
            );

            if (is_array($result2)) {
                foreach ($result2 as $row) {
                    $permissions[] = $row['permission'];
                }
            } elseif (is_object($result2) && method_exists($result2, 'fetch_assoc')) {
                while ($row = $result2->fetch_assoc()) {
                    $permissions[] = $row['permission'];
                }
            }
        }

        return $permissions;
    }

    /**
     * Check if role has permission
     */
    public function hasPermission($permission)
    {
        if (!isset($this->id)) {
            return false;
        }

        // Try checking with the new schema (role_permissions + separate permissions table)
        $result = $this->db->query(
            "SELECT rp.id FROM role_permissions rp 
             JOIN permissions p ON p.id = rp.permission_id 
             WHERE rp.role_id = ? AND (p.permission = ? OR CONCAT(p.resource, '.', p.action) = ?) LIMIT 1",
            [$this->id, $permission, $permission]
        );

        if (
            (is_array($result) && count($result) > 0) ||
            (is_object($result) && isset($result->num_rows) && $result->num_rows > 0)
        ) {
            return true;
        }

        // Fallback to old schema (permissions table with role_id and permission columns)
        // This handles permissions stored directly on the permissions table
        $result2 = $this->db->query(
            "SELECT id FROM permissions 
             WHERE role_id = ? AND permission = ? LIMIT 1",
            [$this->id, $permission]
        );

        if (
            (is_array($result2) && count($result2) > 0) ||
            (is_object($result2) && isset($result2->num_rows) && $result2->num_rows > 0)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Assign permission to role
     */
    public function assignPermission($permission)
    {
        if (!isset($this->id)) {
            throw new \Exception('Role must be saved before assigning permissions');
        }

        if (empty($permission)) {
            throw new \Exception('Permission name is required');
        }

        // Check if permission already exists
        if ($this->hasPermission($permission)) {
            return true; // Already assigned
        }

        // Find the permission ID by name
        $permResult = $this->db->query(
            "SELECT id FROM permissions WHERE name = ? LIMIT 1",
            [$permission]
        );

        $permId = null;
        if (is_array($permResult) && !empty($permResult)) {
            $permId = $permResult[0]['id'];
        } elseif (is_object($permResult) && $permResult->num_rows > 0) {
            $row = $permResult->fetch_assoc();
            $permId = $row['id'];
        }

        if (!$permId) {
            throw new \Exception("Permission '{$permission}' not found");
        }

        $result = $this->db->query(
            "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
            [$this->id, $permId]
        );
        return $result !== null;
    }

    /**
     * Assign multiple permissions
     */
    public function assignPermissions(array $permissions)
    {
        foreach ($permissions as $permission) {
            $this->assignPermission($permission);
        }
        return $this;
    }

    /**
     * Remove permission from role
     */
    public function removePermission($permission)
    {
        if (!isset($this->id)) {
            return false;
        }

        $result = $this->db->query(
            "DELETE rp FROM role_permissions rp 
             JOIN permissions p ON p.id = rp.permission_id 
             WHERE rp.role_id = ? AND p.name = ?",
            [$this->id, $permission]
        );
        return $result !== null;
    }

    /**
     * Sync permissions (replace all with given list)
     */
    public function syncPermissions(array $permissions)
    {
        if (!isset($this->id)) {
            throw new \Exception('Role must be saved before syncing permissions');
        }

        // Delete all existing role-permission assignments
        $this->db->query("DELETE FROM role_permissions WHERE role_id = ?", [$this->id]);

        // Assign new permissions
        foreach ($permissions as $permission) {
            $this->assignPermission($permission);
        }

        return $this;
    }
}
