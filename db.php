<?php
/**
 * db.php - Database Connector & Backend Data Foundation
 * 
 * Supports both standard server-rendered views and decoupled JSON API endpoints.
 */

// 1. Prevent direct script execution via URL if accessed independently
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

// 2. Database Configuration
// Reads from server environment variables if set; otherwise uses Hostinger defaults.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'xxxxxxxxxxxx');
define('DB_USER', getenv('DB_USER') ?: 'xxxxxxxxxxxx');
define('DB_PASS', getenv('DB_PASS') ?: 'xxxxxxxxxxxx'); // Put your DB password here
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a shared PDO database instance (Singleton).
 */
function get_db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'",
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log raw technical error on server side; never expose credentials to client
            error_log('[Database Error] ' . $e->getMessage());

            handle_db_connection_failure($e);
        }
    }

    return $pdo;
}

/**
 * Handles connection failures according to caller context (API JSON vs Browser HTML).
 */
function handle_db_connection_failure(PDOException $e): void {
    http_response_code(500);

    $isJsonRequest = (
        (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) ||
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/'))
    );

    if ($isJsonRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => 'Database service unavailable. Please check server logs.'
        ]);
        exit;
    }

    // Friendly fallback screen for direct browser navigation
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>Service Unavailable</title>
      <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0b0f19; color: #f1f5f9; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { background: #151d30; border: 1px solid #243049; padding: 32px; border-radius: 10px; max-width: 440px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        h1 { font-size: 20px; color: #f87171; margin-bottom: 8px; }
        p { font-size: 14px; color: #94a3b8; line-height: 1.5; margin-bottom: 20px; }
        .btn { background: #0284c7; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; }
      </style>
    </head>
    <body>
      <div class="card">
        <h1>Database Connection Offline</h1>
        <p>Could not connect to the database. The database server may be temporarily down or undergoing maintenance.</p>
        <a href="javascript:location.reload()" class="btn">Retry Connection</a>
      </div>
    </body>
    </html>';
    exit;
}

// ---------------------------------------------------------------------------
// Backend API Helpers (For decoupled frontend/backend endpoints)
// ---------------------------------------------------------------------------

/**
 * Sends a standardized JSON success response.
 */
function json_response(mixed $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Sends a standardized JSON error response.
 */
function json_error(string $message, int $statusCode = 400, array $details = []): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    $payload = ['success' => false, 'error' => $message];
    if (!empty($details)) {
        $payload['details'] = $details;
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------------------
// Global PDO Initialization (Preserves compatibility with current codebase)
// ---------------------------------------------------------------------------
$pdo = get_db();