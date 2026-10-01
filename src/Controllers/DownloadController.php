<?php
/**
 * src/Controllers/DownloadController.php
 * Controller for dispatching and managing downloadable files for consoles and games.
 */

declare(strict_types=1);

namespace Vault\Controllers;

use Vault\Repositories\DownloadRepository;
use Vault\Services\DownloadHelper;
use Vault\Services\Response;
use Throwable;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class DownloadController
{
    private DownloadRepository $repo;
    private DownloadHelper $helper;

    public function __construct(?DownloadRepository $repo = null, ?DownloadHelper $helper = null)
    {
        $this->repo = $repo ?? new DownloadRepository();
        $this->helper = $helper ?? new DownloadHelper($this->repo);
    }

    /**
     * Frontend download trigger endpoint: /download/{id}
     * Automatically resolves provider (external or blackblaze), increments metrics,
     * and serves the file directly or returns JSON if requested.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function download(array $request): void
    {
        $id = (int)($request['params']['id'] ?? 0);
        if ($id <= 0) {
            Response::error('Invalid file ID provided.', 400);
        }

        try {
            $result = $this->helper->processDownload($id);
            $record = $result['record'];
            $url    = $result['url'];

            // Check if caller explicitly requested JSON response (e.g. AJAX / fetch)
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            $wantsJson = (!empty($request['query']['json']) && $request['query']['json'] === '1')
                || str_contains($accept, 'application/json');

            if ($wantsJson) {
                Response::json([
                    'id'               => $record['id'],
                    'display_name'     => $record['display_name'],
                    'storage_provider' => $record['storage_provider'],
                    'url'              => $url,
                    'download_count'   => (int)$record['download_count'] + 1,
                ]);
            }

            // Direct browser trigger: redirect to download URL
            Response::redirect($url, 302);
        } catch (Throwable $e) {
            Response::error('Download error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * API download trigger endpoint: /api/download/{id}
     * Always returns JSON with the resolved download URL and file metadata.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function apiDownload(array $request): void
    {
        $id = (int)($request['params']['id'] ?? 0);
        if ($id <= 0) {
            Response::error('Invalid file ID provided.', 400);
        }

        try {
            $result = $this->helper->processDownload($id);
            $record = $result['record'];
            $url    = $result['url'];

            Response::json([
                'id'               => $record['id'],
                'display_name'     => $record['display_name'],
                'storage_provider' => $record['storage_provider'],
                'url'              => $url,
                'download_count'   => (int)$record['download_count'] + 1,
            ]);
        } catch (Throwable $e) {
            Response::error('Download error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lists downloadable files for a specific console: /api/downloads/console/{id}
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function byConsole(array $request): void
    {
        $consoleId = (int)($request['params']['id'] ?? 0);
        if ($consoleId <= 0) {
            Response::error('Invalid console ID.', 400);
        }

        $files = $this->repo->getByConsoleId($consoleId);
        Response::json($files);
    }

    /**
     * Lists downloadable files for a specific game: /api/downloads/game/{id}
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function byGame(array $request): void
    {
        $gameId = (int)($request['params']['id'] ?? 0);
        if ($gameId <= 0) {
            Response::error('Invalid game ID.', 400);
        }

        $files = $this->repo->getByGameId($gameId);
        Response::json($files);
    }
}
