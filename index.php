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
$GLOBALS['config'] = $config;

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
use Vault\Controllers\BulkUploadController;
use Vault\Controllers\CatalogServicesController;
use Vault\Controllers\CategoryController;
use Vault\Controllers\ConsoleController;
use Vault\Controllers\ConsoleTypeController;
use Vault\Controllers\DashboardController;
use Vault\Controllers\DownloadController;
use Vault\Controllers\GameController;
use Vault\Controllers\LanguageController;
use Vault\Controllers\PlayerHubController;
use Vault\Controllers\PublisherController;
use Vault\Controllers\ReportController;
use Vault\Controllers\RoleController;
use Vault\Controllers\SubcategoryController;
use Vault\Controllers\UserController;
use Vault\Services\Database;
use Vault\Services\Response;
use Vault\Services\Router;
use Vault\Services\View;

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
$router->post('/api/dashboard/update-game-status', [DashboardController::class, 'updateGameStatus']);


// Player Hub & Catalog Spotlight Experience
$router->get('/collection', [PlayerHubController::class, 'index']);
$router->get('/player-hub', [PlayerHubController::class, 'index']);
$router->get('/api/player-hub/count', [PlayerHubController::class, 'apiCount']);
$router->get('/api/player-hub/pick', [PlayerHubController::class, 'apiPick']);
$router->get('/api/player-hub/games', [PlayerHubController::class, 'apiList']);
$router->get('/api/player-hub/game/{id}', [PlayerHubController::class, 'apiGetGame']);

// Portal Routes (Games Portal, Consoles Portal, Publishers Portal)
$router->get('/games-portal', function () {
    \Vault\Auth\Auth::requireAccess('games_portal', 'read');
    if (file_exists(__DIR__ . '/src/Views/games_portal.php')) {
        Response::html(View::render('games_portal', ['navActive' => 'games_portal', 'activeNav' => 'games_portal']));
        return;
    }
    Response::redirect('/collection');
});
$router->get('/games_portal', function () {
    Response::redirect('/games-portal');
});
$router->get('/game-portal', function () {
    Response::redirect('/games-portal');
});
$router->get('/game_portal', function () {
    Response::redirect('/games-portal');
});

// Games Portal API Routes
$router->get('/api/games-portal/game/{id}', function (array $req) {
    \Vault\Auth\Auth::requireAccess('games_portal', 'read');
    $id = (int)($req['params']['id'] ?? 0);
    $repo = new \Vault\Repositories\GameRepository();
    $playerId = (int)(\Vault\Auth\Auth::id() ?? 1);
    $data = $repo->getGamePortalDetail($id, $playerId);
    if ($data === null) {
        Response::json(['error' => 'Game not found'], 404);
        return;
    }
    Response::json($data);
});
$router->get('/api/game-portal/game/{id}', function (array $req) {
    \Vault\Auth\Auth::requireAccess('games_portal', 'read');
    $id = (int)($req['params']['id'] ?? 0);
    $repo = new \Vault\Repositories\GameRepository();
    $playerId = (int)(\Vault\Auth\Auth::id() ?? 1);
    $data = $repo->getGamePortalDetail($id, $playerId);
    if ($data === null) {
        Response::json(['error' => 'Game not found'], 404);
        return;
    }
    Response::json($data);
});
$router->post('/api/games-portal/update-game-status', function (array $req) {
    \Vault\Auth\Auth::requireAccess('games_portal', 'write');
    $body = json_decode((string)file_get_contents('php://input'), true) ?? [];
    $gameId   = (int)($body['game_id'] ?? 0);
    $isPlayed = !empty($body['is_played']);
    $isWon    = !empty($body['is_won']);

    if ($gameId <= 0) {
        Response::json(['success' => false, 'error' => 'Invalid game ID.'], 400);
        return;
    }

    $playerId = (int)(\Vault\Auth\Auth::id() ?? 1);
    $playerRepo = new \Vault\Repositories\PlayerGameRepository();

    // Check if game has been downloaded first
    $existing = $playerRepo->findByPlayerAndGame($playerId, $gameId);
    $isDownloaded = !empty($existing['is_downloaded']) || (!empty($existing['download_count']) && (int)$existing['download_count'] > 0);
    if (!$isDownloaded) {
        Response::json(['success' => false, 'error' => 'Game must be downloaded before updating progress.'], 400);
        return;
    }

    $record = $playerRepo->updateGameStatus($playerId, $gameId, $isPlayed, $isWon);

    Response::json([
        'success' => true,
        'record'  => $record,
    ]);
});

$router->get('/consoles-portal', function () {
    \Vault\Auth\Auth::requireAccess('consoles_portal', 'read');
    if (file_exists(__DIR__ . '/src/Views/consoles_portal.php')) {
        Response::html(View::render('consoles_portal', ['navActive' => 'consoles_portal', 'activeNav' => 'consoles_portal']));
        return;
    }
    Response::redirect('/collection');
});
$router->get('/consoles_portal', function () {
    Response::redirect('/consoles-portal');
});
$router->get('/console-portal', function () {
    Response::redirect('/consoles-portal');
});
$router->get('/console_portal', function () {
    Response::redirect('/consoles-portal');
});

// Consoles Portal API Routes
$router->get('/api/consoles-portal/console/{id}', function (array $req) {
    \Vault\Auth\Auth::requireAccess('consoles_portal', 'read');
    $id = (int)($req['params']['id'] ?? 0);
    $repo = new \Vault\Repositories\ConsoleRepository();
    $data = $repo->getConsolePortalDetail($id);
    if ($data === null) {
        Response::json(['error' => 'Console not found'], 404);
        return;
    }
    Response::json($data);
});
$router->get('/api/console-portal/console/{id}', function (array $req) {
    \Vault\Auth\Auth::requireAccess('consoles_portal', 'read');
    $id = (int)($req['params']['id'] ?? 0);
    $repo = new \Vault\Repositories\ConsoleRepository();
    $data = $repo->getConsolePortalDetail($id);
    if ($data === null) {
        Response::json(['error' => 'Console not found'], 404);
        return;
    }
    Response::json($data);
});

$router->get('/publishers-portal', function () {
    \Vault\Auth\Auth::requireAccess('publishers_portal', 'read');
    $view = file_exists(__DIR__ . '/src/Views/publishers_portal.php')
        ? 'publishers_portal'
        : (file_exists(__DIR__ . '/src/Views/publisher_portal.php') ? 'publisher_portal' : null);

    if ($view !== null) {
        Response::html(View::render($view, ['navActive' => 'publishers_portal', 'activeNav' => 'publishers_portal']));
        return;
    }
    Response::redirect('/collection');
});
$router->get('/publishers_portal', function () {
    Response::redirect('/publishers-portal');
});
$router->get('/publisher-portal', function () {
    Response::redirect('/publishers-portal');
});
$router->get('/publisher_portal', function () {
    Response::redirect('/publishers-portal');
});

// Publishers Portal API Routes
$router->get('/api/publishers-portal/publisher/{id}', function (array $req) {
    \Vault\Auth\Auth::requireAccess('publishers_portal', 'read');
    $id = (int)($req['params']['id'] ?? 0);
    $repo = new \Vault\Repositories\PublisherRepository();
    $data = $repo->getPublisherPortalDetail($id);
    if ($data === null) {
        Response::json(['error' => 'Publisher not found'], 404);
        return;
    }
    Response::json($data);
});
$router->get('/api/publisher-portal/publisher/{id}', function (array $req) {
    \Vault\Auth\Auth::requireAccess('publishers_portal', 'read');
    $id = (int)($req['params']['id'] ?? 0);
    $repo = new \Vault\Repositories\PublisherRepository();
    $data = $repo->getPublisherPortalDetail($id);
    if ($data === null) {
        Response::json(['error' => 'Publisher not found'], 404);
        return;
    }
    Response::json($data);
});

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
$router->post('/api/publishers/autofill-description', [PublisherController::class, 'autofillDescription']);
$router->post('/publishers/autofill-description', [PublisherController::class, 'autofillDescription']);
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
$router->post('/api/consoles/autofill-specs', [ConsoleController::class, 'autofillSpecs']);
$router->post('/consoles', [ConsoleController::class, 'create']);
$router->post('/consoles/create', [ConsoleController::class, 'create']);
$router->post('/consoles/{id}/update', [ConsoleController::class, 'update']);
$router->post('/consoles/{id}/delete', [ConsoleController::class, 'delete']);
$router->delete('/consoles/{id}', [ConsoleController::class, 'delete']);

// Game Cataloguer & Asset Maintenance Routes
$router->get('/games', [GameController::class, 'index']);
$router->get('/api/games', [GameController::class, 'apiList']);
$router->get('/api/telemetry', [GameController::class, 'apiTelemetry']);
$router->post('/api/games/autofill-metadata', [GameController::class, 'autofillMetadata']);
$router->post('/games', [GameController::class, 'create']);
$router->post('/games/create', [GameController::class, 'create']);
$router->post('/games/{id}/update', [GameController::class, 'update']);
$router->post('/games/{id}/delete', [GameController::class, 'delete']);
$router->delete('/games/{id}', [GameController::class, 'delete']);

// Database Bulk Ingestion Workbench Routes
$router->get('/bulk-upload', [BulkUploadController::class, 'index']);
$router->get('/api/bulk-upload/schema', [BulkUploadController::class, 'schema']);
$router->get('/api/bulk-upload/schema/{table}', [BulkUploadController::class, 'schema']);
$router->post('/api/bulk-upload/validate', [BulkUploadController::class, 'validateBatch']);
$router->post('/api/bulk-upload/execute', [BulkUploadController::class, 'executeBatch']);

// Reports & Curated Collection Audits
$router->get('/reports', [ReportController::class, 'index']);
$router->get('/api/reports/premade', [ReportController::class, 'apiPremade']);
$router->post('/api/reports/custom', [ReportController::class, 'apiCustom']);
$router->get('/api/reports/export', [ReportController::class, 'export']);

// Catalog Services & Broken Link Maintenance Routes
$router->get('/catalog-services', [CatalogServicesController::class, 'index']);
$router->get('/catalog_services', function () {
    Response::redirect('/catalog-services');
});
$router->post('/api/catalog-services/fix-broken-link', [CatalogServicesController::class, 'fixBrokenLink']);
$router->post('/api/catalog-services/dismiss-broken-link', [CatalogServicesController::class, 'dismissBrokenLink']);
$router->post('/api/catalog-services/close-broken-link', [CatalogServicesController::class, 'dismissBrokenLink']);

// Download & Asset Dispatch Routes
$router->get('/download/{id}', [DownloadController::class, 'download']);
$router->get('/api/download/{id}', [DownloadController::class, 'apiDownload']);
$router->post('/api/download/{id}/report-broken', [DownloadController::class, 'reportBroken']);
$router->post('/api/games-portal/report-broken-link', [DownloadController::class, 'reportBroken']);
$router->get('/api/downloads/console/{id}', [DownloadController::class, 'byConsole']);
$router->get('/api/downloads/game/{id}', [DownloadController::class, 'byGame']);

// 7. Dispatch the Request
$router->dispatch($requestUri, $requestMethod);