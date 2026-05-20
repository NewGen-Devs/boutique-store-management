<?php

/**
 * Authentication Controller
 * Handles frontend authentication views
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;

class AuthController extends Controller
{
    protected $session;

    public function __construct()
    {
        $this->session = Session::getInstance();
    }

    /**
     * Show homepage
     */
    public function showHome()
    {
        // Debug: Log session state
        if (APP_DEBUG) {
            error_log("showHome() - Session authenticated: " . ($this->session->isAuthenticated() ? 'YES' : 'NO'));
            error_log("showHome() - Session data: " . json_encode($_SESSION));
        }

        // If already logged in, redirect to dashboard
        if ($this->session->isAuthenticated()) {
            header('Location: /dashboard');
            exit;
        }

        // Include homepage
        include FRONTEND_PATH . '/pages/index.html';
    }

    /**
     * Show about page
     */
    public function showAbout()
    {
        include FRONTEND_PATH . '/pages/about.html';
    }

    /**
     * Show contact page
     */
    public function showContact()
    {
        include FRONTEND_PATH . '/pages/contact.html';
    }

    /**
     * Show debug login page
     */
    public function showDebugLogin()
    {
        include ROOT_PATH . '/debug-login.html';
    }

    /**
     * Show login page
     */
    public function showLogin()
    {
        // If already logged in, redirect to dashboard
        if ($this->session->isAuthenticated()) {
            header('Location: /dashboard');
            exit;
        }

        // Include login page
        include FRONTEND_PATH . '/pages/login.html';
    }

    /**
     * Show register page
     */
    public function showRegister()
    {
        // If already logged in, redirect to dashboard
        if ($this->session->isAuthenticated()) {
            header('Location: /dashboard');
            exit;
        }

        // Include register page - for now just show login
        include FRONTEND_PATH . '/pages/login.html';
    }

    /**
     * Login (POST) - deprecated, use /api/login instead
     */
    public function login()
    {
        // Redirect to API endpoint message
        $this->jsonError('Please use POST /api/login for authentication', 400);
    }

    /**
     * Logout (POST) - deprecated, use /api/logout instead
     */
    public function logout()
    {
        // Redirect to API endpoint message
        $this->jsonError('Please use POST /api/logout for logout', 400);
    }

    /**
     * Register (POST) - deprecated
     */
    public function register()
    {
        // Redirect to API endpoint message
        $this->jsonError('Registration is not yet available', 400);
    }
    /**
     * Show password policy page
     */
    public function showPasswordPolicy()
    {
        include FRONTEND_PATH . '/pages/password-policy.html';
    }
}
