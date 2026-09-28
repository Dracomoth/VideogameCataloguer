<?php
/**
 * config/config.example.php
 * Configuration blueprint template.
 * Copy this file to `config/config.php` and enter your real credentials.
 */

declare(strict_types=1);

// Prevent direct execution outside the application
if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

// -----------------------------------------------------------------------------
// Environment Detection
// -----------------------------------------------------------------------------
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) 
        || (php_sapi_name() === 'cli-server');

return [
    'app' => [
        'name'        => 'Videogame Vault',
        'env'         => $isLocal ? 'development' : 'production',
        'debug'       => $isLocal,
        'base_url'    => $isLocal ? 'http://localhost:8000' : 'https://vdgn-test.yourdomain.com',
        'session_key' => 'vg_vault_session',
    ],

    'database' => [
        // When running locally, point to your Hostinger Remote MySQL host/IP.
        // When running on Hostinger, this is typically 'localhost'.
        'host'    => $isLocal ? 'your_hostinger_remote_mysql_host_or_ip' : 'localhost',
        'port'    => 3306,
        'name'    => 'your_dev_database_name',
        'user'    => 'your_dev_database_user',
        'pass'    => 'your_dev_database_password',
        'charset' => 'utf8mb4',
    ],

    'paths' => [
        'root'    => dirname(__DIR__),
        'images'  => dirname(__DIR__) . '/images',
        'storage' => dirname(__DIR__) . '/storage',
    ],
];