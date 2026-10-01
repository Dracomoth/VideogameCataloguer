<?php
/**
 * src/Services/DownloadHelper.php
 * Unified helper service for resolving and dispatching downloadable files.
 */

declare(strict_types=1);

namespace Vault\Services;

use Vault\Repositories\DownloadRepository;
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

    public function __construct(?DownloadRepository $repo = null, ?BackblazeService $backblazeService = null)
    {
        $this->repo = $repo ?? new DownloadRepository();
        $this->backblazeService = $backblazeService ?? new BackblazeService();
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
     * 3. Increments download_count and updates last_download timestamp.
     *
     * @param int $id
     * @return array{record: array<string, mixed>, url: string}
     */
    public function processDownload(int $id): array
    {
        $record = $this->repo->getById($id);
        if ($record === null) {
            throw new InvalidArgumentException("Downloadable file with ID #{$id} not found.");
        }

        $url = $this->resolveDownloadUrl($record);

        // Record metrics
        $this->repo->recordDownload($id);

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
