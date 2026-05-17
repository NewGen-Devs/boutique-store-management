<?php

/**
 * Generic HTTP Error View
 * Used for 4xx and 5xx HTTP errors
 */

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error <?php echo $code ?? 500; ?> — Boutique Store</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .error-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 60px 40px;
            max-width: 500px;
            text-align: center;
        }
        
        .error-code {
            font-size: 80px;
            font-weight: 700;
            color: #667eea;
            line-height: 1;
            margin-bottom: 20px;
        }
        
        .error-title {
            font-size: 28px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 12px;
        }
        
        .error-message {
            font-size: 16px;
            color: #718096;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .error-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 28px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #2d3748;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        .error-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <?php
                $icon = match ($code ?? 500) {
                    404 => '🔍',
                    403 => '🔒',
                    401 => '🚫',
                    500 => '⚠️',
                    503 => '🛠️',
                    default => '❌'
                };
echo $icon;
?>
        </div>
        
        <div class="error-code"><?php echo $code ?? 500; ?></div>
        
        <h1 class="error-title">
            <?php
            echo match ($code ?? 500) {
                404 => 'Page Not Found',
                403 => 'Access Forbidden',
                401 => 'Unauthorized',
                500 => 'Internal Server Error',
                503 => 'Service Unavailable',
                default => 'Error'
            };
?>
        </h1>
        
        <p class="error-message">
            <?php echo htmlspecialchars($message ?? 'An error occurred', ENT_QUOTES, 'UTF-8'); ?>
        </p>
        
        <div class="error-actions">
            <a href="/" class="btn btn-primary">Go Home</a>
            <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
        </div>
    </div>
</body>
</html>
