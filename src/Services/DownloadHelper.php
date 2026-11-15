<?php
/**
 * src/Services/DownloadHelper.php
 * Unified helper service for resolving and dispatching downloadable files.
 */

declare(strict_types=1);

namespace Vault\Services;

use Vault\Repositories\DownloadRepository;
use Vault\Repositories\PlayerGameRepository;
use Vault\Auth\Auth;
use RuntimeException;
use InvalidArgumentException;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class DownloadHelper
{
    private DownloadRepository $repo;
    private BackblazeService $backblazeService;
    private PlayerGameRepository $playerGameRepo;

    public function __construct(
        ?DownloadRepository $repo = null,
        ?BackblazeService $backblazeService = null,
        ?PlayerGameRepository $playerGameRepo = null
    ) {
        $this->repo = $repo ?? new DownloadRepository();
        $this->backblazeService = $backblazeService ?? new BackblazeService();
        $this->playerGameRepo = $playerGameRepo ?? new PlayerGameRepository();
    }

    /**
     * Resolves the download URL for a given downloadable file record.
     *
     * @param array<string, mixed> $fileRecord
     * @return string
     * @throws RuntimeException
     */
    public function resolveDownloadUrl(array $fileRecord): string
    {
        $provider = (string)($fileRecord['storage_provider'] ?? 'external');
        $fileKeyOrUrl = trim((string)($fileRecord['file_key_or_url'] ?? ''));

        if ($fileKeyOrUrl === '') {
            throw new RuntimeException("File pointer URL or key is empty for file record #{$fileRecord['id']}.");
        }

        if ($provider === 'blackblaze') {
            return $this->backblazeService->getTemporaryDownloadUrl($fileKeyOrUrl);
        }

        // External provider: normalize scheme and encode spaces
        return $this->normalizeExternalUrl($fileKeyOrUrl);
    }

    /**
     * Normalizes an external URL:
     * - Trims whitespace
     * - Adds https:// if protocol scheme is omitted
     * - Safely encodes spaces and unencoded URI characters in the path
     *
     * @param string $url
     * @return string
     */
    public function normalizeExternalUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        // If local file path exists, return as-is
        $localCandidate = ltrim($url, '/\\');
        if (file_exists($localCandidate) && is_file($localCandidate)) {
            return $localCandidate;
        }

        // Prepend https:// if no scheme is specified
        if (!preg_match('~^[a-zA-Z][a-zA-Z0-9+.-]*://~', $url)) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        if ($parts && isset($parts['host'])) {
            $normalized = ($parts['scheme'] ?? 'https') . '://' . $parts['host'];
            if (isset($parts['port'])) {
                $normalized .= ':' . $parts['port'];
            }
            if (isset($parts['path'])) {
                $segments = explode('/', $parts['path']);
                $encodedSegments = array_map(function (string $seg): string {
                    return rawurlencode(rawurldecode($seg));
                }, $segments);
                $normalized .= implode('/', $encodedSegments);
            }
            if (isset($parts['query'])) {
                $normalized .= '?' . $parts['query'];
            }
            if (isset($parts['fragment'])) {
                $normalized .= '#' . $parts['fragment'];
            }
            return $normalized;
        }

        return str_replace(' ', '%20', $url);
    }

    /**
     * Processes a download request by record ID:
     * 1. Looks up the downloadable file record.
     * 2. Resolves the appropriate download URL based on storage provider.
     * 3. Increments download_count and updates last_download timestamp on the file.
     * 4. If the file is linked to a game and a player is authenticated/provided,
     *    updates player_games user statistics table according to business rules.
     *
     * @param int $id
     * @param int|null $playerId Optional explicit player ID; defaults to Auth::id() if available.
     * @return array{record: array<string, mixed>, url: string}
     */
    public function processDownload(int $id, ?int $playerId = null): array
    {
        $record = $this->repo->getById($id);
        if ($record === null) {
            // Check if $id is actually a game_id with associated downloadable files
            $gameFiles = $this->repo->getByGameId($id);
            if (!empty($gameFiles)) {
                $record = $gameFiles[0];
                $id = (int)$record['id'];
            }
        }

        if ($record === null) {
            throw new InvalidArgumentException("Downloadable file or game file with ID #{$id} not found.");
        }

        $url = $this->resolveDownloadUrl($record);

        // Record metrics on downloadable_files
        $this->repo->recordDownload($id);

        // If the downloadable file belongs to a game, record player stats in player_games
        if (!empty($record['game_id'])) {
            $effectivePlayerId = $playerId ?? Auth::id();
            if ($effectivePlayerId !== null && $effectivePlayerId > 0) {
                $this->playerGameRepo->recordGameDownload($effectivePlayerId, (int)$record['game_id']);
            }
        }

        return [
            'record' => $record,
            'url'    => $url,
        ];
    }

    /**
     * Serves or redirects the client to the resolved download URL.
     *
     * @param int $id
     * @return never
     */
    public function serveDownload(int $id): void
    {
        $result = $this->processDownload($id);
        Response::redirect($result['url'], 302);
    }

    /**
     * Streams the file binary directly to the client output buffer.
     * Prevents browser cross-origin (CORS) blocks on cloud storage providers and
     * external mirrors by streaming chunk-by-chunk through the backend.
     *
     * @param string $url
     * @param string $displayName
     * @return never
     */
    public function streamFile(string $url, string $displayName = 'game.rom'): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // 1. If it's a local file on disk
        $localCandidate = ltrim($url, '/\\');
        if (file_exists($localCandidate) && is_file($localCandidate)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . addslashes($displayName) . '"');
            header('Content-Length: ' . (string)filesize($localCandidate));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-transform, no-store, must-revalidate');
            readfile($localCandidate);
            exit;
        }

        // 2. Normalize remote URL
        $targetUrl = $this->normalizeExternalUrl($url);

        $headersSent = false;
        $bytesSent = 0;

        $executeStream = function (string $remoteUrl) use (&$headersSent, &$bytesSent, $displayName): array {
            $ch = curl_init($remoteUrl);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch, CURLOPT_AUTOREFERER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 VideogameVault/5.0');
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            curl_setopt($ch, CURLOPT_BUFFERSIZE, 128 * 1024);

            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$headersSent, &$bytesSent, $displayName) {
                if (!$headersSent) {
                    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    if ($code >= 200 && $code < 400) {
                        header('Content-Type: application/octet-stream');
                        header('Content-Disposition: attachment; filename="' . addslashes($displayName) . '"');
                        header('X-Content-Type-Options: nosniff');
                        header('Cache-Control: private, no-transform, no-store, must-revalidate');
                        $headersSent = true;
                    }
                }

                if ($headersSent) {
                    echo $chunk;
                    flush();
                    $bytesSent += strlen($chunk);
                }

                return strlen($chunk);
            });

            $res = curl_exec($ch);
            $err = curl_error($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return ['success' => $res, 'error' => $err, 'code' => $code];
        };

        $result = $executeStream($targetUrl);

        // Fallback: If HTTPS failed with connection error (code 0) and no bytes were sent, attempt HTTP fallback
        if (!$headersSent && $bytesSent === 0 && str_starts_with($targetUrl, 'https://') && $result['code'] === 0) {
            $httpUrl = 'http://' . substr($targetUrl, 8);
            $result = $executeStream($httpUrl);
        }

        // If no headers were sent or no bytes were transferred, report fatal error with appropriate status
        if (!$headersSent || $bytesSent === 0) {
            $status = ($result['code'] >= 400 && $result['code'] < 600) ? $result['code'] : 502;
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            $errMsg = !empty($result['error']) 
                ? $result['error'] 
                : ($result['code'] > 0 ? "Remote server returned HTTP status {$result['code']}" : "No data received from remote source");
            echo json_encode(['error' => "Failed to stream ROM: {$errMsg}"]);
            exit;
        }

        exit;
    }
}
