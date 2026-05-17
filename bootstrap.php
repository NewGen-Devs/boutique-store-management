<?php

/**
 * Bootstrap File - Initializes the application
 */

// Define base path constants
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . '/app');
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', ROOT_PATH . '/config');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', ROOT_PATH . '/storage');
}
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', ROOT_PATH . '/public');
}
if (!defined('FRONTEND_PATH')) {
    define('FRONTEND_PATH', ROOT_PATH . '/frontend');
}

// Load logging helper
require_once APP_PATH . '/helpers/logging.php';

// ============================================
// LOAD ENVIRONMENT VARIABLES FIRST
// ============================================
if (!function_exists('loadEnv')) {
    function loadEnv($filePath)
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse key=value
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1)) {
                    $value = substr($value, 1, -1);
                }

                putenv($key . '=' . $value);
            }
        }

        return true;
    }
}

// Load .env file if it exists
$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile)) {
    loadEnv($envFile);
}

// Load environment configuration
require_once CONFIG_PATH . '/config.php';

// Load exception classes
require_once APP_PATH . '/Exceptions/AppException.php';
require_once APP_PATH . '/Exceptions/HttpException.php';
require_once APP_PATH . '/Exceptions/NotFoundException.php';

// Load core framework classes
require_once APP_PATH . '/core/Database.php';
require_once APP_PATH . '/core/Model.php';
require_once APP_PATH . '/core/Session.php';
require_once APP_PATH . '/core/Auth.php';
require_once APP_PATH . '/core/Controller.php';
require_once APP_PATH . '/core/Router.php';

// Load vendor autoloader
require_once ROOT_PATH . '/vendor/autoload.php';

// Set timezone
date_default_timezone_set(APP_TIMEZONE ?? 'UTC');

return [
    'app_path' => APP_PATH,
    'config_path' => CONFIG_PATH,
    'storage_path' => STORAGE_PATH,
    'root_path' => ROOT_PATH,
];
