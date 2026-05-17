<?php

/**
 * Router script for PHP built-in server
 * Usage: php -S localhost:8000 -t public public/router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Check if it's a static file request
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|json)$/', $uri)) {
    // Try to serve from frontend directory
    $frontendPath = dirname(__DIR__) . '/frontend' . $uri;

    if (file_exists($frontendPath) && is_file($frontendPath)) {
        // Determine mime type
        $ext = strtolower(pathinfo($frontendPath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
        ];

        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=3600');
        readfile($frontendPath);
        return true;
    }

    // File not found
    http_response_code(404);
    echo "File not found: $uri";
    return true;
}

// For all other requests, pass to index.php
require __DIR__ . '/index.php';
