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

use Vault\Controllers\AuthController;
use Vault\Controllers\CategoryController;
use Vault\Controllers\ConsoleController;
use Vault\Controllers\ConsoleTypeController;
use Vault\Controllers\DashboardController;
use Vault\Controllers\GameController;
use Vault\Controllers\LanguageController;
use Vault\Controllers\PublisherController;
use Vault\Controllers\RoleController;
use Vault\Controllers\SubcategoryController;
use Vault\Controllers\UserController;
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

// Baseline Health Check
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

// --- Application Core Routes ---

// Authentication & Session Routes
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->post('/logout', [AuthController::class, 'logout']);

// Dashboard HTML Workbench View
$router->get('/', [DashboardController::class, 'index']);

// Dashboard JSON Telemetry API
$router->get('/api/dashboard', [DashboardController::class, 'api']);

// User Management Routes
$router->get('/users', [UserController::class, 'index']);
$router->post('/users', [UserController::class, 'store']);
$router->post('/users/{id}/update', [UserController::class, 'update']);
$router->post('/users/{id}/toggle', [UserController::class, 'toggle']);
$router->post('/users/{id}/delete', [UserController::class, 'delete']);
$router->delete('/users/{id}', [UserController::class, 'delete']);

// Role & Dual-Device Permission Matrix Routes
$router->get('/roles', [RoleController::class, 'index']);
$router->post('/roles', [RoleController::class, 'store']);
$router->get('/api/roles/{id}/matrix', [RoleController::class, 'matrix']);
$router->post('/roles/{id}/update', [RoleController::class, 'update']);
$router->post('/roles/{id}/delete', [RoleController::class, 'delete']);
$router->delete('/roles/{id}', [RoleController::class, 'delete']);

// Taxonomy: Languages & Regions Routes
$router->get('/languages', [LanguageController::class, 'index']);
$router->get('/api/languages', [LanguageController::class, 'apiList']);
$router->post('/languages', [LanguageController::class, 'create']);
$router->post('/languages/create', [LanguageController::class, 'create']);
$router->post('/languages/{id}/update', [LanguageController::class, 'update']);
$router->post('/languages/{id}/delete', [LanguageController::class, 'delete']);
$router->delete('/languages/{id}', [LanguageController::class, 'delete']);

// Taxonomy: Categories Routes
$router->get('/categories', [CategoryController::class, 'index']);
$router->get('/api/categories', [CategoryController::class, 'apiList']);
$router->post('/categories', [CategoryController::class, 'create']);
$router->post('/categories/create', [CategoryController::class, 'create']);
$router->post('/categories/{id}/update', [CategoryController::class, 'update']);
$router->post('/categories/{id}/delete', [CategoryController::class, 'delete']);
$router->delete('/categories/{id}', [CategoryController::class, 'delete']);

// Taxonomy: Publishers & Hardware Manufacturers Routes
$router->get('/publishers', [PublisherController::class, 'index']);
$router->get('/api/publishers', [PublisherController::class, 'apiList']);
$router->post('/publishers', [PublisherController::class, 'create']);
$router->post('/publishers/create', [PublisherController::class, 'create']);
$router->post('/publishers/{id}/update', [PublisherController::class, 'update']);
$router->post('/publishers/{id}/delete', [PublisherController::class, 'delete']);
$router->delete('/publishers/{id}', [PublisherController::class, 'delete']);

// Taxonomy: Subcategories Routes
$router->get('/subcategories', [SubcategoryController::class, 'index']);
$router->get('/api/subcategories', [SubcategoryController::class, 'apiList']);
$router->post('/subcategories', [SubcategoryController::class, 'create']);
$router->post('/subcategories/create', [SubcategoryController::class, 'create']);
$router->post('/subcategories/{id}/update', [SubcategoryController::class, 'update']);
$router->post('/subcategories/{id}/delete', [SubcategoryController::class, 'delete']);
$router->delete('/subcategories/{id}', [SubcategoryController::class, 'delete']);

// Taxonomy: Console Types Routes
$router->get('/console-types', [ConsoleTypeController::class, 'index']);
$router->get('/api/console-types', [ConsoleTypeController::class, 'apiList']);
$router->post('/console-types', [ConsoleTypeController::class, 'create']);
$router->post('/console-types/create', [ConsoleTypeController::class, 'create']);
$router->post('/console-types/{id}/update', [ConsoleTypeController::class, 'update']);
$router->post('/console-types/{id}/delete', [ConsoleTypeController::class, 'delete']);
$router->delete('/console-types/{id}', [ConsoleTypeController::class, 'delete']);

// Hardware & Consoles Maintenance Routes
$router->get('/consoles', [ConsoleController::class, 'index']);
$router->get('/api/consoles', [ConsoleController::class, 'apiList']);
$router->post('/consoles', [ConsoleController::class, 'create']);
$router->post('/consoles/create', [ConsoleController::class, 'create']);
$router->post('/consoles/{id}/update', [ConsoleController::class, 'update']);
$router->post('/consoles/{id}/delete', [ConsoleController::class, 'delete']);
$router->delete('/consoles/{id}', [ConsoleController::class, 'delete']);

// Game Cataloguer & Asset Maintenance Routes
$router->get('/games', [GameController::class, 'index']);
$router->get('/api/games', [GameController::class, 'apiList']);
$router->post('/games', [GameController::class, 'create']);
$router->post('/games/create', [GameController::class, 'create']);
$router->post('/games/{id}/update', [GameController::class, 'update']);
$router->post('/games/{id}/delete', [GameController::class, 'delete']);
$router->delete('/games/{id}', [GameController::class, 'delete']);

// 7. Dispatch the Request
$router->dispatch($requestUri, $requestMethod);