<?php

/**
 * Password Reset Controller
 * Handles password reset requests and token validation
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\User;

class PasswordResetController extends Controller
{
    protected $session;

    public function __construct()
    {
        $this->session = Session::getInstance();
    }

    /**
     * ==========================================
     * API: REQUEST PASSWORD RESET
     * ==========================================
     */

    /**
     * POST /api/password/forgot
     * Request password reset token
     */
    public function apiForgotPassword()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Method not allowed', 405);
        }

        $payload = $this->getJsonPayload();
        $email = $payload['email'] ?? '';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->jsonError('Valid email is required', 400);
        }

        try {
            $user = User::findByEmail($email);

            if (!$user) {
                // Don't reveal if email exists
                logger()->info("Password reset requested for non-existent email: $email");
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'If an account exists with that email, a reset link will be sent.'
                ]);
                return;
            }

            // Generate reset token
            $token = $user->generateResetToken();

            // TODO: Send email with reset link
            // Example: EmailService::sendPasswordReset($user->email, $token);

            logger()->info("Password reset token generated for: $email");

            $this->jsonResponse([
                'success' => true,
                'message' => 'If an account exists with that email, a reset link will be sent.'
            ]);
        } catch (\Exception $e) {
            logger()->error("Password reset error: " . $e->getMessage());
            $this->jsonError('Error processing request', 500);
        }
    }

    /**
     * ==========================================
     * API: RESET PASSWORD
     * ==========================================
     */

    /**
     * POST /api/password/reset
     * Reset password with valid token
     */
    public function apiResetPassword()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Method not allowed', 405);
        }

        $payload = $this->getJsonPayload();
        $token = $payload['token'] ?? '';
        $email = $payload['email'] ?? '';
        $password = $payload['password'] ?? '';
        $confirmPassword = $payload['password_confirmation'] ?? '';

        // Validate input
        $validation = $this->validateResetInput($email, $password, $confirmPassword);
        if (!$validation['valid']) {
            $this->jsonError($validation['message'], 400);
        }

        try {
            $user = User::findByEmail($email);

            if (!$user) {
                $this->jsonError('Invalid reset link', 400);
            }

            // Verify token
            if (!$user->verifyResetToken($token)) {
                logger()->warning("Invalid password reset token for: $email");
                $this->jsonError('Invalid or expired reset link', 400);
            }

            // Update password
            $user->setPassword($password);
            $user->clearResetToken();

            logger()->info("Password reset successful for: $email");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Password reset successfully. You can now login.'
            ]);
        } catch (\Exception $e) {
            logger()->error("Password reset error: " . $e->getMessage());
            $this->jsonError('Error resetting password', 500);
        }
    }

    /**
     * ==========================================
     * API: VERIFY RESET TOKEN
     * ==========================================
     */

    /**
     * GET /api/password/verify-token/{token}
     * Verify if reset token is valid
     */
    public function apiVerifyToken($token)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->jsonError('Method not allowed', 405);
        }

        try {
            if (empty($token)) {
                $this->jsonError('Token is required', 400);
            }

            $instance = new User();
            $result = $instance->db->query(
                "SELECT * FROM users WHERE reset_token = ? LIMIT 1",
                [hash('sha256', $token)]
            );

            $userData = $result->fetch_assoc();

            if (!$userData) {
                $this->jsonError('Invalid token', 400);
            }

            $user = new User($userData);

            if (!$user->verifyResetToken($token)) {
                $this->jsonError('Token expired', 400);
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'Token is valid',
                'email' => $user->email
            ]);
        } catch (\Exception $e) {
            logger()->error("Token verification error: " . $e->getMessage());
            $this->jsonError('Error verifying token', 500);
        }
    }

    /**
     * ==========================================
     * VALIDATION & HELPERS
     * ==========================================
     */

    /**
     * Validate reset input
     */
    private function validateResetInput($email, $password, $confirmPassword)
    {
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
            return ['valid' => false, 'message' => 'Password must be at least 8 characters'];
        }

        if ($password !== $confirmPassword) {
            return ['valid' => false, 'message' => 'Passwords do not match'];
        }

        return ['valid' => true];
    }

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
