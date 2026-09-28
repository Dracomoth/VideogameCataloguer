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

// 5. Parse Request Context
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Normalize URI path (trim duplicate slashes and strip script name if subfolder-hosted)
$scriptDir = trim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
if ($scriptDir !== '' && str_starts_with(trim($requestUri, '/'), $scriptDir)) {
    $path = substr(trim($requestUri, '/'), strlen($scriptDir));
    $requestUri = '/' . ltrim($path, '/');
}

// 6. Base Verification Route Dispatcher
// (We will expand this to full controller-based routing once core classes are defined)
if ($requestUri === '/' || $requestUri === '/health') {
    $isJson = (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
           || str_starts_with($requestUri, '/api/');

    $response = [
        'status'  => 'online',
        'app'     => $config['app']['name'],
        'env'     => $config['app']['env'],
        'time'    => date('Y-m-d H:i:s'),
        'route'   => $requestUri,
        'method'  => $requestMethod,
    ];

    if ($isJson || $requestUri === '/health') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => $response], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Default development landing text
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>{$config['app']['name']} - Setup</title></head>";
    echo "<body style=\"font-family: sans-serif; background: #0b0f19; color: #f8fafc; padding: 40px;\">";
    echo "<h2>🎮 {$config['app']['name']} Front Controller Active</h2>";
    echo "<p>Environment: <strong>{$config['app']['env']}</strong></p>";
    echo "<p>Native autoloader and routing pipeline operational.</p>";
    echo "</body></html>";
    exit;
}

// Default 404 handler for unrecognized routes during initial setup
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => false,
    'error'   => 'Route not found.',
    'path'    => $requestUri
], JSON_UNESCAPED_SLASHES);
exit;