<?php

/**
 * User Controller - Authentication & User Management API
 * Handles login, logout, user data endpoints
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\User;

class UserController extends Controller
{
    protected $session;

    public function __construct()
    {
        $this->session = Session::getInstance();
        parent::__construct();
    }

    /**
     * ==========================================
     * API: LOGIN
     * ==========================================
     */

    /**
     * POST /api/login
     * Authenticate user with email and password
     */
    public function apiLogin()
    {
        // Only allow POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Method not allowed', 405);
        }

        // Get JSON payload
        $payload = $this->getJsonPayload();

        // Validate input
        $validation = $this->validateLoginInput($payload);
        if (!$validation['valid']) {
            $this->jsonError($validation['message'], 400);
        }

        $email = $payload['email'] ?? '';
        $password = $payload['password'] ?? '';

        try {
            // Find user by email
            $user = User::findByEmail($email);

            if (!$user) {
                // Log failed attempt
                logger()->info("Login failed: User not found - $email");
                $this->jsonError('Invalid email or password', 401);
            }

            // Check if account is locked
            if ($user->isLocked()) {
                logger()->warning("Login failed: Account locked - $email");
                $this->jsonError('Account is temporarily locked. Try again later.', 429);
            }

            // Verify password
            if (!$user->verifyPassword($password)) {
                // Increment login attempts
                $user->incrementLoginAttempts();
                logger()->info("Login failed: Invalid password - $email (attempt {$user->login_attempts})");
                $this->jsonError('Invalid email or password', 401);
            }

            // Check if user is active
            if (!$user->is_active) {
                logger()->warning("Login failed: Account inactive - $email");
                $this->jsonError('Account is inactive', 403);
            }

            // Success: Update login info and create session
            $user->updateLastLogin();

            // Regenerate session for security
            $this->session->regenerate();

            // Store user in session
            $this->session->setUser($user->toApiResponse());

            // Generate CSRF token
            $csrfToken = $this->session->generateCsrfToken();

            logger()->info("Login successful - {$user->email}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Login successful',
                'user' => $user->toApiResponse(),
                'csrf_token' => $csrfToken
            ]);
        } catch (\Exception $e) {
            logger()->error("Login error: " . $e->getMessage());
            $this->jsonError('Authentication error. Please try again.', 500);
        }
    }

    /**
     * Validate login input
     */
    private function validateLoginInput($data)
    {
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($email)) {
            return ['valid' => false, 'message' => 'Email is required'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'Invalid email format'];
        }

        if (empty($password)) {
            return ['valid' => false, 'message' => 'Password is required'];
        }

        if (strlen($password) < 8) {
            return ['valid' => false, 'message' => 'Invalid password'];
        }

        return ['valid' => true];
    }

    /**
     * ==========================================
     * API: LOGOUT
     * ==========================================
     */

    /**
     * POST /api/logout
     * Logout authenticated user
     */
    public function apiLogout()
    {
        // Only allow POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Method not allowed', 405);
        }

        try {
            if ($this->session->isAuthenticated()) {
                $email = $this->session->getUser()['email'] ?? 'unknown';
                logger()->info("User logged out - $email");
            }

            // Clear session
            $this->session->clearUser();
            $this->session->destroy();

            $this->jsonResponse([
                'success' => true,
                'message' => 'Logout successful'
            ]);
        } catch (\Exception $e) {
            logger()->error("Logout error: " . $e->getMessage());
            $this->jsonError('Logout failed', 500);
        }
    }

    /**
     * ==========================================
     * API: GET CURRENT USER
     * ==========================================
     */

    /**
     * GET /api/user
     * Get current authenticated user data
     */
    public function apiGetUser()
    {
        // Only allow GET requests
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->jsonError('Method not allowed', 405);
        }

        if (!$this->session->isAuthenticated()) {
            $this->jsonError('Unauthorized', 401);
        }

        try {
            $userData = $this->session->getUser();

            // Refresh from database to get latest data
            $user = User::findById($userData['id']);

            if (!$user) {
                $this->session->clearUser();
                $this->jsonError('User not found', 404);
            }

            $this->jsonResponse([
                'success' => true,
                'user' => $user->toApiResponse()
            ]);
        } catch (\Exception $e) {
            logger()->error("Get user error: " . $e->getMessage());
            $this->jsonError('Error retrieving user data', 500);
        }
    }

    /**
     * ==========================================
     * HELPER METHODS
     * ==========================================
     */

    /**
     * Get JSON payload from request body
     */
    private function getJsonPayload()
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }

    /**
     * Send JSON error response
     */
    protected function jsonError($message, $statusCode = 400)
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);

        echo json_encode([
            'success' => false,
            'message' => $message,
            'status_code' => $statusCode
        ]);

        exit;
    }

    /**
     * Send JSON success response
     */
    protected function jsonResponse($data, $statusCode = 200)
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);

        echo json_encode($data);
        exit;
    }
}
