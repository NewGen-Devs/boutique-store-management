<?php

/**
 * User Model - Handles user data, authentication, and security
 */

namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $hidden = ['password', 'reset_token'];

    /**
     * ==========================================
     * QUERY METHODS
     * ==========================================
     */

    /**
     * Find user by ID
     */
    public static function findById($id)
    {
        return static::find($id);
    }

    /**
     * Find user by email
     */
    public static function findByEmail($email)
    {
        return static::where('email', $email);
    }

    /**
     * Find user by username
     */
    public static function findByUsername($username)
    {
        return static::where('username', $username);
    }

    /**
     * Get all active users
     */
    public static function allActive()
    {
        $instance = new static();
        $result = $instance->db->query("SELECT * FROM {$instance->table} WHERE is_active = 1");

        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = new static($row);
        }
        return $records;
    }

    /**
     * Create new user with validation
     */
    public static function create($data)
    {
        $user = new static($data);

        // Validate email
        if (!$user->validateEmail($data['email'] ?? null)) {
            throw new \Exception('Invalid email format');
        }

        // Check if email already exists
        if (static::findByEmail($data['email'])) {
            throw new \Exception('Email already registered');
        }

        // Check if username already exists
        if (isset($data['username']) && static::findByUsername($data['username'])) {
            throw new \Exception('Username already taken');
        }

        // Hash password if provided
        if (isset($data['password'])) {
            $user->setPassword($data['password']);
        }

        $user->save();
        return $user;
    }

    /**
     * Update user with validation
     */
    public function update($data = [])
    {
        // Validate email if provided
        if (isset($data['email']) && $data['email'] !== $this->email) {
            if (!$this->validateEmail($data['email'])) {
                throw new \Exception('Invalid email format');
            }
            if (static::findByEmail($data['email'])) {
                throw new \Exception('Email already in use');
            }
        }

        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => PASSWORD_HASH_COST]);
        }

        // Update attributes
        foreach ($data as $key => $value) {
            $this->attributes[$key] = $value;
        }

        return parent::update();
    }

    /**
     * Delete user (hard delete)
     */
    public function delete()
    {
        return parent::delete();
    }

    /**
     * Soft delete user
     */
    public function softDelete()
    {
        $this->attributes['is_active'] = false;
        return $this->save();
    }

    /**
     * Restore soft deleted user
     */
    public function restore()
    {
        $this->attributes['is_active'] = true;
        return $this->save();
    }

    /**
     * ==========================================
     * PASSWORD & AUTHENTICATION
     * ==========================================
     */

    /**
     * Hash password with bcrypt
     */
    public function setPassword($password)
    {
        $validation = static::validatePasswordStrength($password);
        if (!$validation['valid']) {
            throw new \Exception($validation['message']);
        }

        $this->attributes['password'] = password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_HASH_COST]);
        return $this;
    }

    /**
     * Validate password strength
     * Requirements: min 8 chars, uppercase, lowercase, number, special char
     */
    public static function validatePasswordStrength($password)
    {
        if (strlen($password) < 8) {
            return [
                'valid' => false,
                'message' => 'Password must be at least 8 characters long'
            ];
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return [
                'valid' => false,
                'message' => 'Password must contain at least one uppercase letter'
            ];
        }

        if (!preg_match('/[a-z]/', $password)) {
            return [
                'valid' => false,
                'message' => 'Password must contain at least one lowercase letter'
            ];
        }

        if (!preg_match('/[0-9]/', $password)) {
            return [
                'valid' => false,
                'message' => 'Password must contain at least one number'
            ];
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return [
                'valid' => false,
                'message' => 'Password must contain at least one special character'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Verify password
     */
    public function verifyPassword($password)
    {
        return password_verify($password, $this->attributes['password'] ?? '');
    }

    /**
     * Check if password needs rehashing
     */
    public function passwordNeedsRehash()
    {
        return password_needs_rehash($this->attributes['password'] ?? '', PASSWORD_BCRYPT, ['cost' => PASSWORD_HASH_COST]);
    }

    /**
     * ==========================================
     * EMAIL VALIDATION
     * ==========================================
     */

    /**
     * Validate email format
     */
    public function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * ==========================================
     * LOGIN & SECURITY
     * ==========================================
     */

    /**
     * Update last login timestamp
     */
    public function updateLastLogin()
    {
        $this->attributes['last_login'] = date('Y-m-d H:i:s');
        $this->attributes['login_attempts'] = 0;
        return $this->save();
    }

    /**
     * Increment failed login attempts
     */
    public function incrementLoginAttempts()
    {
        $this->attributes['login_attempts'] = ($this->attributes['login_attempts'] ?? 0) + 1;

        if ($this->attributes['login_attempts'] >= AUTH_MAX_LOGIN_ATTEMPTS) {
            $this->attributes['locked_until'] = date('Y-m-d H:i:s', time() + AUTH_LOCKOUT_DURATION);
        }

        return $this->save();
    }

    /**
     * Reset login attempts
     */
    public function resetLoginAttempts()
    {
        $this->attributes['login_attempts'] = 0;
        $this->attributes['locked_until'] = null;
        return $this->save();
    }

    /**
     * Check if account is locked
     */
    public function isLocked()
    {
        if (!$this->attributes['locked_until'] ?? false) {
            return false;
        }

        $lockedUntil = strtotime($this->attributes['locked_until']);
        if (time() > $lockedUntil) {
            $this->resetLoginAttempts();
            return false;
        }

        return true;
    }

    /**
     * Unlock account
     */
    public function unlock()
    {
        return $this->resetLoginAttempts();
    }

    /**
     * ==========================================
     * ROLES & PERMISSIONS
     * ==========================================
     */

    /**
     * Get user's role
     */
    public function getRole()
    {
        $result = $this->db->query(
            "SELECT r.* FROM roles r WHERE r.id = ?",
            [$this->attributes['role_id'] ?? null]
        );
        return $result->fetch_assoc();
    }

    /**
     * Get user's permissions
     */
    public function getPermissions()
    {
        $result = $this->db->query(
            "SELECT permission FROM permissions WHERE role_id = ?",
            [$this->attributes['role_id'] ?? null]
        );

        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row['permission'];
        }
        return $permissions;
    }

    /**
     * Check if user has specific permission
     */
    public function hasPermission($permission)
    {
        $permissions = $this->getPermissions();
        return in_array($permission, $permissions, true);
    }

    /**
     * ==========================================
     * PASSWORD RESET
     * ==========================================
     */

    /**
     * Generate password reset token
     */
    public function generateResetToken()
    {
        $token = bin2hex(random_bytes(32));
        $this->attributes['reset_token'] = hash('sha256', $token);
        $this->attributes['reset_token_expires'] = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        $this->save();
        return $token;
    }

    /**
     * Verify password reset token
     */
    public function verifyResetToken($token)
    {
        $hashedToken = hash('sha256', $token);

        if ($this->attributes['reset_token'] !== $hashedToken) {
            return false;
        }

        $expiresAt = strtotime($this->attributes['reset_token_expires'] ?? '');
        if (time() > $expiresAt) {
            return false;
        }

        return true;
    }

    /**
     * Clear password reset token
     */
    public function clearResetToken()
    {
        $this->attributes['reset_token'] = null;
        $this->attributes['reset_token_expires'] = null;
        return $this->save();
    }

    /**
     * ==========================================
     * DATA FORMATTING
     * ==========================================
     */

    /**
     * Get user data for API response (hide sensitive fields)
     */
    public function toApiResponse()
    {
        $data = $this->attributes;

        // Remove sensitive fields
        unset($data['password']);
        unset($data['reset_token']);
        unset($data['login_attempts']);
        unset($data['locked_until']);

        return $data;
    }
}
