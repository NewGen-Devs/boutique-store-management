<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\User;
use App\Models\Role;

class UserManagementController extends Controller
{
    protected $user;
    protected $role;

    public function __construct()
    {
        parent::__construct();
        $this->role = new Role();
    }

    /**
     * List all users with pagination and filtering
     * GET /api/users
     */
    public function listUsers()
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);
            return;
        }

        if (!$this->hasPermission('users.read')) {
            $this->respondJsonError('Forbidden: Permission denied', 403);
            return;
        }

        $page = (int) ($_GET['page'] ?? 1);
        $limit = (int) ($_GET['limit'] ?? 10);
        $search = $_GET['search'] ?? '';
        $role = $_GET['role'] ?? '';
        $status = $_GET['status'] ?? 'active';

        $offset = ($page - 1) * $limit;

        try {
            // Build query
            $query = "SELECT u.*, r.name as role_name, b.name as branch_name 
                      FROM users u 
                      LEFT JOIN roles r ON u.role_id = r.id 
                      LEFT JOIN branches b ON u.branch_id = b.id 
                      WHERE 1=1";

            $params = [];

            // Apply filters
            if ($search) {
                $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
                $searchTerm = "%$search%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
            }

            if ($role) {
                $query .= " AND u.role_id = ?";
                $params[] = $role;
            }

            if ($status === 'active') {
                $query .= " AND u.is_active = 1";
            } elseif ($status === 'inactive') {
                $query .= " AND u.is_active = 0";
            }

            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM (" . $query . ") as count_query";
            $countResult = $this->resultToArray($this->db->query($countQuery, $params));
            $total = $countResult[0]['total'] ?? 0;

            // Add pagination
            $query .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $users = $this->db->query($query, $params);
            $usersArray = $this->resultToArray($users);

            $this->respondJson([
                'success' => true,
                'users' => $usersArray,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            $this->respondJsonError('Error fetching users: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single user details
     * GET /api/users/{id}
     */
    public function showUser($id)
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);
            return;
        }

        if (!$this->hasPermission('users.read')) {
            $this->respondJsonError('Forbidden: Permission denied', 403);
            return;
        }

        try {
            $userResult = $this->db->query(
                "SELECT u.*, r.name as role_name, b.name as branch_name 
                 FROM users u 
                 LEFT JOIN roles r ON u.role_id = r.id 
                 LEFT JOIN branches b ON u.branch_id = b.id 
                 WHERE u.id = ?",
                [$id]
            );

            $user = $this->resultToArray($userResult);

            if (empty($user)) {
                $this->respondJsonError('User not found', 404);
                return;
            }

            // Hide password hash
            unset($user[0]['password']);

            $this->respondJson([
                'success' => true,
                'user' => $user[0]
            ]);
        } catch (\Exception $e) {
            $this->respondJsonError('Error fetching user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create new user
     * POST /api/users
     */
    public function createUser()
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);
            return;
        }

        if (!$this->hasPermission('users.create')) {
            $this->respondJsonError('Forbidden: Permission denied', 403);
            return;
        }

        $data = $this->getJsonInput();

        // Validate input
        $errors = $this->validateUserInput($data);
        if (!empty($errors)) {
            $this->respondJson([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors
            ], 400);
            return;
        }

        try {
            // Check email uniqueness
            $existingResult = $this->db->query("SELECT id FROM users WHERE email = ?", [$data['email']]);
            $existingArray = $this->resultToArray($existingResult);
            if (!empty($existingArray)) {
                $this->respondJson([
                    'success' => false,
                    'message' => 'Email already exists',
                    'errors' => ['email' => 'This email is already registered']
                ], 400);
                return;
            }

            // Hash password
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);

            // Generate username from email if not provided
            $username = $data['username'] ?? explode('@', $data['email'])[0];

            // Ensure username is unique
            $counter = 1;
            $originalUsername = $username;
            while (!empty($this->resultToArray($this->db->query("SELECT id FROM users WHERE username = ?", [$username])))) {
                $username = $originalUsername . $counter++;
            }

            // Insert user
            $inserted = $this->db->insert('users', [
                'username' => $username,
                'email' => $data['email'],
                'password' => $hashedPassword,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'role_id' => $data['role_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            if (!$inserted) {
                $this->respondJsonError('Failed to create user', 500);
                return;
            }

            // Fetch created user
            $userResult = $this->db->query(
                "SELECT u.id, u.email, u.first_name, u.last_name, u.role_id, u.branch_id, u.is_active, u.created_at 
                 FROM users u WHERE u.id = ?",
                [$inserted]
            );
            $user = $this->resultToArray($userResult);

            $this->respondJson([
                'success' => true,
                'message' => 'User created successfully',
                'user' => $user[0]
            ], 201);
        } catch (\Exception $e) {
            $this->respondJsonError('Error creating user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update user
     * PUT /api/users/{id}
     */
    public function updateUser($id)
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);
            return;
        }

        if (!$this->hasPermission('users.update')) {
            $this->respondJsonError('Forbidden: Permission denied', 403);
            return;
        }

        $data = $this->getJsonInput();

        try {
            // Check user exists
            $userResult = $this->resultToArray($this->db->query("SELECT id FROM users WHERE id = ?", [$id]));
            if (empty($userResult)) {
                $this->respondJsonError('User not found', 404);
                return;
            }

            // Check email uniqueness (if changing email)
            if (isset($data['email'])) {
                $existingResult = $this->db->query(
                    "SELECT id FROM users WHERE email = ? AND id != ?",
                    [$data['email'], $id]
                );
                $existingArray = $this->resultToArray($existingResult);
                if (!empty($existingArray)) {
                    $this->respondJson([
                        'success' => false,
                        'message' => 'Email already exists',
                        'errors' => ['email' => 'This email is already registered']
                    ], 400);
                    return;
                }
            }

            // Prepare update data
            $updateData = [];
            $allowedFields = ['email', 'first_name', 'last_name', 'role_id', 'branch_id', 'is_active'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }

            // Handle password update if provided
            if (isset($data['password']) && !empty($data['password'])) {
                $passwordValidation = User::validatePasswordStrength($data['password']);
                if (!$passwordValidation['valid']) {
                    $this->respondJson([
                        'success' => false,
                        'message' => $passwordValidation['message'],
                        'errors' => ['password' => $passwordValidation['message']]
                    ], 400);
                    return;
                }
                $updateData['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
            }

            if (empty($updateData)) {
                $this->respondJson([
                    'success' => false,
                    'message' => 'No valid fields to update'
                ], 400);
                return;
            }

            $updateData['updated_at'] = date('Y-m-d H:i:s');

            // Update user
            $updated = $this->db->update('users', $updateData, 'id = ?', [$id]);

            if (!$updated) {
                $this->respondJsonError('Failed to update user', 500);
                return;
            }

            // Fetch updated user
            $updatedUserResult = $this->db->query(
                "SELECT u.id, u.email, u.first_name, u.last_name, u.role_id, u.branch_id, u.is_active, u.updated_at 
                 FROM users u WHERE u.id = ?",
                [$id]
            );
            $updatedUser = $this->resultToArray($updatedUserResult);

            $this->respondJson([
                'success' => true,
                'message' => 'User updated successfully',
                'user' => $updatedUser[0]
            ]);
        } catch (\Exception $e) {
            $this->respondJsonError('Error updating user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete user (soft delete)
     * DELETE /api/users/{id}
     */
    public function deleteUser($id)
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);
            return;
        }

        if (!$this->hasPermission('users.delete')) {
            $this->respondJsonError('Forbidden: Permission denied', 403);
            return;
        }

        try {
            $user = $this->db->query("SELECT id FROM users WHERE id = ?", [$id]);
            if (empty($user)) {
                $this->respondJsonError('User not found', 404);
                return;
            }

            // Soft delete
            $deleted = $this->db->update('users', ['is_active' => 0, 'deleted_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);

            if (!$deleted) {
                $this->respondJsonError('Failed to delete user', 500);
                return;
            }

            $this->respondJson([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            $this->respondJsonError('Error deleting user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reset user password
     * POST /api/users/{id}/reset-password
     */
    public function resetUserPassword($id)
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);
            return;
        }

        if (!$this->hasPermission('users.update')) {
            $this->respondJsonError('Forbidden: Permission denied', 403);
            return;
        }

        $data = $this->getJsonInput();

        if (!isset($data['new_password'])) {
            $this->respondJson([
                'success' => false,
                'message' => 'New password is required',
                'errors' => ['new_password' => 'Password cannot be empty']
            ], 400);
            return;
        }

        $passwordValidation = User::validatePasswordStrength($data['new_password']);
        if (!$passwordValidation['valid']) {
            $this->respondJson([
                'success' => false,
                'message' => $passwordValidation['message'],
                'errors' => ['new_password' => $passwordValidation['message']]
            ], 400);
            return;
        }

        try {
            $user = $this->db->query("SELECT id FROM users WHERE id = ?", [$id]);
            if (empty($user)) {
                $this->respondJsonError('User not found', 404);
                return;
            }

            $hashedPassword = password_hash($data['new_password'], PASSWORD_BCRYPT);

            $updated = $this->db->update(
                'users',
                ['password' => $hashedPassword, 'updated_at' => date('Y-m-d H:i:s')],
                'id = ?',
                [$id]
            );

            if (!$updated) {
                $this->respondJsonError('Failed to reset password', 500);
                return;
            }

            $this->respondJson([
                'success' => true,
                'message' => 'Password reset successfully'
            ]);
        } catch (\Exception $e) {
            $this->respondJsonError('Error resetting password: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Unlock user account
     * POST /api/users/{id}/unlock
     */
    public function unlockUser($id)
    {
        if (!$this->isAuthenticated()) {
            $this->respondJsonError('Unauthorized', 401);
            return;
        }

        if (!$this->hasPermission('users.update')) {
            $this->respondJsonError('Forbidden: Permission denied', 403);
            return;
        }

        try {
            $user = $this->db->query("SELECT id FROM users WHERE id = ?", [$id]);
            if (empty($user)) {
                $this->respondJsonError('User not found', 404);
                return;
            }

            $updated = $this->db->update(
                'users',
                ['login_attempts' => 0, 'is_locked' => 0, 'updated_at' => date('Y-m-d H:i:s')],
                'id = ?',
                [$id]
            );

            if (!$updated) {
                $this->respondJsonError('Failed to unlock user', 500);
                return;
            }

            $this->respondJson([
                'success' => true,
                'message' => 'User unlocked successfully'
            ]);
        } catch (\Exception $e) {
            $this->respondJsonError('Error unlocking user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Validate user input
     */
    private function validateUserInput($data)
    {
        $errors = [];

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required';
        }

        if (!isset($data['password'])) {
            $errors['password'] = 'Password is required';
        } else {
            $passwordValidation = User::validatePasswordStrength($data['password']);
            if (!$passwordValidation['valid']) {
                $errors['password'] = $passwordValidation['message'];
            }
        }

        if (empty($data['first_name'])) {
            $errors['first_name'] = 'First name is required';
        }

        if (empty($data['last_name'])) {
            $errors['last_name'] = 'Last name is required';
        }

        if (empty($data['role_id'])) {
            $errors['role_id'] = 'Role is required';
        }

        return $errors;
    }

    /**
     * Override hasPermission to handle API context with error handling
     */
    protected function hasPermission($permission)
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        try {
            $role = $this->getUserRole();
            if (!$role) {
                return false;
            }
            return method_exists($role, 'hasPermission') ? $role->hasPermission($permission) : false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Helper: Get JSON input
     */
    protected function getJsonInput()
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    /**
     * Helper: Respond with JSON
     */
    protected function respondJson($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Helper: Respond with JSON error
     */
    protected function respondJsonError($message, $statusCode = 400)
    {
        $this->respondJson([
            'success' => false,
            'message' => $message
        ], $statusCode);
    }
}
