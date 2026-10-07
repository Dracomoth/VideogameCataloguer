<?php
/**
 * src/Controllers/CatalogServicesController.php
 * Controller for Catalog Services, maintenance tasks, and broken link remediation.
 */

declare(strict_types=1);

namespace Vault\Controllers;

use Vault\Auth\Auth;
use Vault\Repositories\BrokenLinkRepository;
use Vault\Services\Response;
use Vault\Services\View;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class CatalogServicesController
{
    private BrokenLinkRepository $repo;

    public function __construct(?BrokenLinkRepository $repo = null)
    {
        $this->repo = $repo ?? new BrokenLinkRepository();
    }

    /**
     * Renders the Catalog Services Workbench view.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function index(array $request): void
    {
        Auth::requireAccess('catalog_services', 'read');

        $brokenLinks = $this->repo->getOpenBrokenLinks();

        $html = View::render('catalog_services', [
            'pageTitle'    => 'Catalog Services',
            'navActive'    => 'catalog_services',
            'activeNav'    => 'catalog_services',
            'brokenLinks'  => $brokenLinks,
            'canWrite'     => Auth::canWrite('catalog_services'),
        ]);

        Response::html($html);
    }

    /**
     * API Endpoint: Fix downloadable file path and close broken link ticket.
     * POST /api/catalog-services/fix-broken-link
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function fixBrokenLink(array $request): void
    {
        Auth::requireAccess('catalog_services', 'write');

        $body = json_decode((string)file_get_contents('php://input'), true) ?? [];
        $reportId = (int)($body['report_id'] ?? 0);
        $fileId   = (int)($body['file_id'] ?? 0);
        $newPath  = trim((string)($body['file_path'] ?? ''));

        if ($reportId <= 0 || $fileId <= 0 || $newPath === '') {
            Response::json(['success' => false, 'error' => 'Invalid parameters or empty file path.'], 400);
        }

        $success = $this->repo->fixAndCloseReport($reportId, $fileId, $newPath);

        if ($success) {
            Response::json([
                'success' => true,
                'message' => 'File path updated and broken link ticket closed successfully.'
            ]);
        } else {
            Response::json(['success' => false, 'error' => 'Failed to update file record or close ticket.'], 500);
        }
    }

    /**
     * API Endpoint: Close broken link ticket without changing the file path.
     * POST /api/catalog-services/dismiss-broken-link
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function dismissBrokenLink(array $request): void
    {
        Auth::requireAccess('catalog_services', 'write');

        $body = json_decode((string)file_get_contents('php://input'), true) ?? [];
        $reportId = (int)($body['report_id'] ?? 0);

        if ($reportId <= 0) {
            Response::json(['success' => false, 'error' => 'Invalid report ID.'], 400);
        }

        $success = $this->repo->closeReport($reportId);

        if ($success) {
            Response::json([
                'success' => true,
                'message' => 'Broken link ticket closed.'
            ]);
        } else {
            Response::json(['success' => false, 'error' => 'Failed to close ticket.'], 500);
        }
    }
}
