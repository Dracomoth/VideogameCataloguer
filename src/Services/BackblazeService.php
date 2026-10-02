<?php
/**
 * src/Services/BackblazeService.php
 * Lightweight, zero-dependency Backblaze B2 service for temporary download authorization.
 * Compatible with standard PHP shared hosting (Hostinger) without external SDKs.
 */

declare(strict_types=1);

namespace Vault\Services;

use RuntimeException;
use InvalidArgumentException;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class BackblazeService
{
    private array $config;
    private ?string $cachedAuthToken = null;
    private ?string $cachedApiUrl = null;
    private ?string $cachedDownloadUrl = null;
    private ?string $cachedBucketId = null;

    /**
     * @param array<string, mixed>|null $config Optional custom config array. Defaults to global config.
     */
    public function __construct(?array $config = null)
    {
        if ($config !== null) {
            $this->config = $config;
        } else {
            $globalConfig = $GLOBALS['config'] ?? null;
            if (empty($globalConfig)) {
                $configFile = dirname(__DIR__, 2) . '/config/config.php';
                if (file_exists($configFile)) {
                    if (!defined('APP_INIT')) {
                        define('APP_INIT', true);
                    }
                    $loaded = require $configFile;
                    if (is_array($loaded)) {
                        $globalConfig = $loaded;
                        $GLOBALS['config'] = $loaded;
                    }
                }
            }
            $this->config = $globalConfig['blackblaze'] ?? $globalConfig['backblaze'] ?? [];
        }
    }

    /**
     * Generates a temporary authorized download URL for a private Backblaze B2 file.
     *
     * @param string $fileKeyOrUrl The file key/path (e.g. 'bios/scph1001.bin') or existing B2 URL.
     * @param int|null $duration Validity in seconds (defaults to config or 3600).
     * @return string The signed temporary download URL.
     * @throws RuntimeException If credentials are missing or API request fails.
     */
    public function getTemporaryDownloadUrl(string $fileKeyOrUrl, ?int $duration = null): string
    {
        $keyId = trim((string)($this->config['key_id'] ?? ''));
        $appKey = trim((string)($this->config['application_key'] ?? ''));
        $bucketName = trim((string)($this->config['bucket_name'] ?? ''));
        $expiry = $duration ?? (int)($this->config['download_expiry'] ?? 3600);

        if ($keyId === '' || $appKey === '') {
            throw new RuntimeException(
                "Backblaze credentials not configured. Please define 'key_id' and 'application_key' in config/config.php."
            );
        }

        // Clean file key (extract relative path if user provided a full B2 URL)
        $cleanFileKey = $this->extractCleanFileKey($fileKeyOrUrl, $bucketName);

        // If S3 endpoint and region are specifically configured, we can use S3 SigV4 Presigned URL
        $s3Endpoint = trim((string)($this->config['s3_endpoint'] ?? ''));
        if ($s3Endpoint !== '') {
            return $this->generateS3PresignedUrl($cleanFileKey, $bucketName, $keyId, $appKey, $s3Endpoint, $expiry);
        }

        // Otherwise use Native Backblaze B2 API
        return $this->generateNativeB2DownloadUrl($cleanFileKey, $bucketName, $keyId, $appKey, $expiry);
    }

    /**
     * Generates a download URL using the Native Backblaze B2 REST API.
     */
    private function generateNativeB2DownloadUrl(
        string $fileKey,
        string $bucketName,
        string $keyId,
        string $appKey,
        int $expiry
    ): string {
        $auth = $this->authorizeAccount($keyId, $appKey);

        $apiUrl      = $auth['apiUrl'];
        $downloadUrl = $auth['downloadUrl'];
        $authToken   = $auth['authorizationToken'];
        $bucketId    = trim((string)($this->config['bucket_id'] ?? ''));

        // If bucketId is not explicitly specified, check the allowed scope or look it up
        if ($bucketId === '') {
            if (!empty($auth['allowed']['bucketId'])) {
                $bucketId = (string)$auth['allowed']['bucketId'];
            } else {
                $bucketId = $this->resolveBucketId($apiUrl, $authToken, (string)($auth['accountId'] ?? ''), $bucketName);
            }
        }

        // Request download authorization token for this file prefix
        $endpoint = rtrim($apiUrl, '/') . '/b2api/v3/b2_get_download_authorization';
        $payload = [
            'bucketId'               => $bucketId,
            'fileNamePrefix'         => $fileKey,
            'validDurationInSeconds' => max(60, min($expiry, 604800)), // B2 limit: 1s to 7 days
        ];

        $response = $this->httpRequest('POST', $endpoint, $payload, [
            'Authorization: ' . $authToken,
            'Content-Type: application/json',
        ]);

        if (empty($response['authorizationToken'])) {
            $msg = $response['message'] ?? 'Failed to obtain download authorization from Backblaze B2.';
            throw new RuntimeException("Backblaze B2 Authorization Error: {$msg}");
        }

        $downloadToken = $response['authorizationToken'];

        // Encode path segments while preserving forward slashes
        $encodedKey = implode('/', array_map('rawurlencode', explode('/', $fileKey)));

        return rtrim($downloadUrl, '/') . '/file/' . rawurlencode($bucketName) . '/' . $encodedKey . '?Authorization=' . urlencode($downloadToken);
    }

    /**
     * Authorizes against Backblaze B2 and caches the 24-hour token.
     *
     * @return array{apiUrl: string, downloadUrl: string, authorizationToken: string, accountId?: string, allowed?: array}
     */
    private function authorizeAccount(string $keyId, string $appKey): array
    {
        $cacheFile = sys_get_temp_dir() . '/vault_b2_auth_' . md5($keyId) . '.json';

        // Check file cache (re-use if valid and created within last 20 hours)
        if (file_exists($cacheFile)) {
            $cached = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached['authorizationToken']) && !empty($cached['expires_at'])) {
                if ($cached['expires_at'] > time()) {
                    return $cached;
                }
            }
        }

        $serverUrl = trim((string)($this->config['server_url'] ?? 'https://api.backblazeb2.com'));
        if ($serverUrl === '') {
            $serverUrl = 'https://api.backblazeb2.com';
        }

        $authEndpoint = rtrim($serverUrl, '/') . '/b2api/v3/b2_authorize_account';
        $credentials = base64_encode("{$keyId}:{$appKey}");

        $response = $this->httpRequest('GET', $authEndpoint, null, [
            'Authorization: Basic ' . $credentials,
        ]);

        if (empty($response['authorizationToken']) || empty($response['apiUrl']) || empty($response['downloadUrl'])) {
            $msg = $response['message'] ?? 'Unknown authentication failure.';
            throw new RuntimeException("Backblaze B2 b2_authorize_account failed: {$msg}");
        }

        $authData = [
            'authorizationToken' => $response['authorizationToken'],
            'apiUrl'             => $response['apiUrl'],
            'downloadUrl'        => $response['downloadUrl'],
            'accountId'          => $response['accountId'] ?? '',
            'allowed'            => $response['allowed'] ?? [],
            'expires_at'         => time() + 72000, // 20 hours TTL
        ];

        @file_put_contents($cacheFile, json_encode($authData), LOCK_EX);

        return $authData;
    }

    /**
     * Resolves the Backblaze bucket ID by bucket name.
     */
    private function resolveBucketId(string $apiUrl, string $authToken, string $accountId, string $bucketName): string
    {
        $endpoint = rtrim($apiUrl, '/') . '/b2api/v3/b2_list_buckets';
        $payload = [
            'accountId'  => $accountId,
            'bucketName' => $bucketName,
        ];

        $response = $this->httpRequest('POST', $endpoint, $payload, [
            'Authorization: ' . $authToken,
            'Content-Type: application/json',
        ]);

        if (!empty($response['buckets'][0]['bucketId'])) {
            return (string)$response['buckets'][0]['bucketId'];
        }

        throw new RuntimeException("Could not find bucket ID for bucket name '{$bucketName}' on Backblaze B2.");
    }

    /**
     * Generates an AWS SigV4 presigned URL for Backblaze S3-compatible endpoints.
     */
    private function generateS3PresignedUrl(
        string $fileKey,
        string $bucketName,
        string $accessKey,
        string $secretKey,
        string $s3Endpoint,
        int $expiry
    ): string {
        $parsed = parse_url($s3Endpoint);
        $host = $parsed['host'] ?? $s3Endpoint;
        $scheme = $parsed['scheme'] ?? 'https';

        $region = trim((string)($this->config['region'] ?? ''));
        if ($region === '') {
            // Attempt to parse region from endpoint e.g. s3.us-west-004.backblazeb2.com
            if (preg_match('/s3\.([a-z0-9-]+)\.backblazeb2\.com/i', $host, $m)) {
                $region = $m[1];
            } else {
                $region = 'us-west-004';
            }
        }

        $now = time();
        $amzDate = gmdate('Ymd\THis\Z', $now);
        $dateStamp = gmdate('Ymd', $now);
        $service = 's3';

        // URL format: https://bucket.host/file_key or https://host/bucket/file_key
        $isVirtualHost = str_starts_with($host, $bucketName . '.');
        if ($isVirtualHost) {
            $uriPath = '/' . ltrim($fileKey, '/');
            $requestHost = $host;
        } else {
            $uriPath = '/' . rawurlencode($bucketName) . '/' . ltrim($fileKey, '/');
            $requestHost = $host;
        }

        $encodedUriPath = implode('/', array_map('rawurlencode', explode('/', ltrim($uriPath, '/'))));
        $canonicalUri = '/' . $encodedUriPath;

        $credentialScope = "{$dateStamp}/{$region}/{$service}/aws4_request";

        $queryParams = [
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => "{$accessKey}/{$credentialScope}",
            'X-Amz-Date'          => $amzDate,
            'X-Amz-Expires'       => (string)$expiry,
            'X-Amz-SignedHeaders' => 'host',
        ];

        ksort($queryParams);
        $canonicalQueryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        $canonicalHeaders = "host:{$requestHost}\n";
        $signedHeaders = "host";
        $payloadHash = 'UNSIGNED-PAYLOAD';

        $canonicalRequest = "GET\n{$canonicalUri}\n{$canonicalQueryString}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        // Derive signing key
        $kDate    = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
        $kRegion  = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        return "{$scheme}://{$requestHost}{$canonicalUri}?{$canonicalQueryString}&X-Amz-Signature={$signature}";
    }

    /**
     * Extracts clean relative file key from input string.
     */
    private function extractCleanFileKey(string $fileKeyOrUrl, string $bucketName): string
    {
        $trimmed = trim($fileKeyOrUrl);

        // If it's a full URL, parse the path
        if (preg_match('#^https?://#i', $trimmed)) {
            $path = parse_url($trimmed, PHP_URL_PATH) ?: '';
            // Match /file/{bucketName}/(path...)
            if (preg_match('#^/file/' . preg_quote($bucketName, '#') . '/(.+)$#', $path, $matches)) {
                return urldecode($matches[1]);
            }
            // Match /{bucketName}/(path...)
            if (preg_match('#^/' . preg_quote($bucketName, '#') . '/(.+)$#', $path, $matches)) {
                return urldecode($matches[1]);
            }
            return ltrim(urldecode($path), '/');
        }

        // Strip leading slash or bucket name prefix if present
        $clean = ltrim($trimmed, '/');
        if ($bucketName !== '' && str_starts_with($clean, $bucketName . '/')) {
            $clean = substr($clean, strlen($bucketName) + 1);
        }

        return $clean;
    }

    /**
     * Lightweight zero-dependency HTTP client using cURL (fallback to stream context).
     *
     * @param string $method
     * @param string $url
     * @param array<string, mixed>|null $data
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    private function httpRequest(string $method, string $url, ?array $data = null, array $headers = []): array
    {
        $jsonBody = $data !== null ? json_encode($data, JSON_UNESCAPED_SLASHES) : null;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $curlHeaders = $headers;
            if ($jsonBody !== null) {
                $curlHeaders[] = 'Content-Length: ' . strlen($jsonBody);
            }

            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $curlHeaders,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => 'VideogameVault-Hostinger-Client/1.0',
            ]);

            if ($jsonBody !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
            }

            $rawResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($rawResponse === false) {
                throw new RuntimeException("Backblaze HTTP error: {$curlError}");
            }

            $decoded = json_decode((string)$rawResponse, true);
            if (!is_array($decoded)) {
                throw new RuntimeException("Invalid response from Backblaze (HTTP {$httpCode}): {$rawResponse}");
            }

            if ($httpCode >= 400) {
                $msg = $decoded['message'] ?? "HTTP error {$httpCode}";
                throw new RuntimeException("Backblaze API error ({$httpCode}): {$msg}");
            }

            return $decoded;
        }

        // Fallback: stream context
        $streamHeaders = implode("\r\n", $headers);
        if ($jsonBody !== null) {
            $streamHeaders .= "\r\nContent-Length: " . strlen($jsonBody);
        }
        $streamHeaders .= "\r\nUser-Agent: VideogameVault-Hostinger-Client/1.0";

        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => $streamHeaders,
                'content'       => $jsonBody,
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $rawResponse = @file_get_contents($url, false, $context);
        if ($rawResponse === false) {
            $err = error_get_last();
            throw new RuntimeException("Backblaze connection failed: " . ($err['message'] ?? 'Unknown error'));
        }

        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Invalid response from Backblaze: {$rawResponse}");
        }

        return $decoded;
    }
}
