<?php
/**
 * src/Services/Response.php
 * Unified HTTP Response & Payload Formatter.
 */

declare(strict_types=1);

namespace Vault\Services;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class Response
{
    /**
     * Sends a standardized JSON success response and exits.
     *
     * @param mixed $data
     * @param int $statusCode
     * @return never
     */
    public static function json(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo json_encode([
            'success' => true,
            'data'    => $data,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        exit;
    }

    /**
     * Sends a standardized JSON error response and exits.
     *
     * @param string $message
     * @param int $statusCode
     * @param array<string, mixed> $details
     * @return never
     */
    public static function error(string $message, int $statusCode = 400, array $details = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        $payload = [
            'success' => false,
            'error'   => $message,
        ];

        if (!empty($details)) {
            $payload['details'] = $details;
        }

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        exit;
    }

    /**
     * Sends an HTML string or renders content with an HTTP status code.
     *
     * @param string $html
     * @param int $statusCode
     * @return never
     */
    public static function html(string $html, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    /**
     * Performs an HTTP redirection.
     *
     * @param string $url
     * @param int $statusCode
     * @return never
     */
    public static function redirect(string $url, int $statusCode = 302): void
    {
        http_response_code($statusCode);
        header("Location: {$url}");
        exit;
    }
}