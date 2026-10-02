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

        // External provider: directly serve the URL
        return $fileKeyOrUrl;
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
            throw new InvalidArgumentException("Downloadable file with ID #{$id} not found.");
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
}
