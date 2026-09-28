<?php
/**
 * router.php
 * Local development router for PHP's built-in web server.
 * Simulates Apache .htaccess rewrites for `php -S localhost:8000 router.php`.
 */

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$filePath = __DIR__ .$uri;

// 1. Emulate .htaccess 403 block on protected directories & extensions
if (preg_match('#^/(config|src|database|storage)/#i', $uri) ||
    preg_match('#\.(sql|md|log|json|lock)$#i', $uri)) {
    http_response_code(403);
    exit('Access Forbidden');
}

// 2. Serve physical static files directly (assets, images, etc.)
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false; // Tells PHP CLI server to serve file as-is
}

// 3. Fall through to Front Controller for all dynamic routes
require_once __DIR__ . '/index.php';