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

    'blackblaze' => [
        // Backblaze B2 Application Key ID (or S3 Access Key ID)
        'key_id'          => 'your_blackblaze_key_id',
        // Backblaze B2 Application Key (or S3 Secret Access Key)
        'application_key' => 'your_blackblaze_application_key',
        // Target private bucket name
        'bucket_name'     => 'your_bucket_name',
        // Bucket ID (optional: auto-discovered via API if key has bucket scope or list access)
        'bucket_id'       => '',
        // Server URL / API Endpoint (default: 'https://api.backblazeb2.com')
        'server_url'      => 'https://api.backblazeb2.com',
        // Optional S3-compatible endpoint (e.g. 'https://s3.us-west-004.backblazeb2.com')
        's3_endpoint'     => '',
        // Region (e.g. 'us-west-004')
        'region'          => '',
        // Validity duration for temporary download URLs in seconds (default: 3600 = 1 hour)
        'download_expiry' => 3600,
    ],
];