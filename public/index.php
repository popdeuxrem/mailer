<?php

declare(strict_types=1);

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start session
session_start();

// Set timezone
date_default_timezone_set('UTC');

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Composer autoloader
require_once BASE_PATH . '/vendor/autoload.php';

use EmailPlatform\Core\Application;

try {
    // Initialize application
    $app = new Application(BASE_PATH);
    
    // Handle the incoming request
    $app->handleRequest();
    
} catch (Throwable $e) {
    // Handle fatal errors gracefully
    $errorMessage = $_ENV['APP_DEBUG'] ?? false ? 
        $e->getMessage() . "\n" . $e->getTraceAsString() : 
        'An error occurred. Please try again later.';
    
    http_response_code(500);
    
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0) {
        // API request - return JSON
        header('Content-Type: application/json');
        echo json_encode(['error' => $errorMessage]);
    } else {
        // Web request - return HTML
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - Email Marketing Platform</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background: #f8fafc; 
            color: #374151; 
            padding: 2rem; 
            text-align: center; 
        }
        .error-container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: white; 
            padding: 2rem; 
            border-radius: 8px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); 
        }
        .error-title { 
            color: #dc2626; 
            font-size: 1.5rem; 
            margin-bottom: 1rem; 
        }
        .error-message { 
            background: #fef2f2; 
            border: 1px solid #fecaca; 
            padding: 1rem; 
            border-radius: 4px; 
            white-space: pre-wrap; 
            text-align: left; 
            font-family: monospace; 
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1 class="error-title">Application Error</h1>
        <div class="error-message">{$errorMessage}</div>
        <p>
            <a href="/">← Return to Home</a>
        </p>
    </div>
</body>
</html>
HTML;
    }
}