<?php
/**
 * index.php
 * Main Front Controller & Application Dispatcher.
 */

declare(strict_types=1);

// 1. Guard constant to block direct script access in protected includes
define('APP_INIT', true);

// 2. Load configuration
$configFile = __DIR__ . '/config/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Configuration file missing. Please copy 'config/config.example.php' to 'config/config.php' and enter your environment settings.");
}
$config = require $configFile;

// 3. Configure environment error reporting
if (!empty($config['app']['debug'])) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

// 4. Native Zero-Dependency Autoloader (PSR-4 Mapping: Vault\ -> src/)
spl_autoload_register(function (string $class): void {
    $prefix = 'Vault\\';
    $baseDir = __DIR__ . '/src/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use Vault\Services\Database;
use Vault\Services\Response;
use Vault\Services\Router;

// 5. Parse Request Context
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Normalize URI path (trim duplicate slashes and strip script folder if subfolder-hosted)
$scriptDir = trim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
if ($scriptDir !== '' && str_starts_with(trim($requestUri, '/'), $scriptDir)) {
    $path = substr(trim($requestUri, '/'), strlen($scriptDir));
    $requestUri = '/' . ltrim($path, '/');
}

// 6. Initialize Router
$router = new Router();

// Baseline Health & Verification Route
$router->get('/health', function () use ($config) {
    $dbStatus = 'offline';
    try {
        Database::getConnection();
        $dbStatus = 'online';
    } catch (\Throwable $e) {
        $dbStatus = 'error: ' . $e->getMessage();
    }

    Response::json([
        'status'   => 'ok',
        'app'      => $config['app']['name'],
        'env'      => $config['app']['env'],
        'database' => $dbStatus,
        'time'     => date('Y-m-d H:i:s'),
    ]);
});

// Root Dev Landing / Verification Route
$router->get('/', function () use ($config) {
    $isJson = (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
           || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');

    $dbStatus = 'offline';
    try {
        Database::getConnection();
        $dbStatus = 'connected';
    } catch (\Throwable) {
        $dbStatus = 'disconnected';
    }

    if ($isJson) {
        Response::json([
            'status'   => 'online',
            'app'      => $config['app']['name'],
            'env'      => $config['app']['env'],
            'database' => $dbStatus,
            'time'     => date('Y-m-d H:i:s'),
        ]);
    }

    $dbBadgeColor = $dbStatus === 'connected' ? '#10b981' : '#ef4444';

    Response::html("<!DOCTYPE html>
<html lang=\"en\">
<head>
  <meta charset=\"UTF-8\">
  <title>{$config['app']['name']} - Setup</title>
</head>
<body style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0b0f19; color: #f8fafc; padding: 40px;\">
  <h2>🎮 {$config['app']['name']} Front Controller Active</h2>
  <p>Environment: <strong>{$config['app']['env']}</strong></p>
  <p>Database: <span style=\"background: {$dbBadgeColor}; color: #fff; padding: 2px 8px; border-radius: 4px; font-weight: 600;\">{$dbStatus}</span></p>
  <p>Router and service pipeline operational.</p>
</body>
</html>");
});

// 7. Dispatch the Request
$router->dispatch($requestUri, $requestMethod);