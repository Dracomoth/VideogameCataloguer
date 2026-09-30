<?php
/**
 * src/Controllers/ReportController.php
 * Controller orchestrating curated collection audits and custom dynamic report exports.
 */

declare(strict_types=1);

namespace Vault\Controllers;

use Vault\Auth\Auth;
use Vault\Repositories\ReportRepository;
use Vault\Services\Response;
use Vault\Services\View;
use Throwable;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class ReportController
{
    private ReportRepository $repo;

    public function __construct()
    {
        $this->repo = new ReportRepository();
    }

    /**
     * Renders the Reports & Audits Page.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function index(array $request): void
    {
        Auth::requireAccess('reports', 'read');

        $stats   = $this->repo->getPremadeStats();
        $catalog = $this->repo->getTableCatalog();

        $viewData = [
            'pageTitle' => 'Reports & Collection Audits',
            'activeNav' => 'reports',
            'stats'     => $stats,
            'catalog'   => $catalog,
        ];

        $html = View::render('reports', $viewData, 'layout');
        Response::html($html);
    }

    /**
     * API endpoint returning dataset for a curated premade audit.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function apiPremade(array $request): void
    {
        Auth::requireAccess('reports', 'read');

        $reportName = trim((string)($_GET['report'] ?? ($request['report'] ?? '')));
        if ($reportName === '') {
            Response::error('Missing report identifier parameter.', 400);
        }

        try {
            $data = $this->repo->getPremadeReport($reportName);
            Response::json($data);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * API endpoint returning dataset for custom table and ordered column selection.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function apiCustom(array $request): void
    {
        Auth::requireAccess('reports', 'read');

        // Parse JSON payload or POST body
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);

        if (!is_array($payload)) {
            $payload = $_POST;
        }

        $table = trim((string)($payload['table'] ?? ''));
        $columns = $payload['columns'] ?? [];

        if (!is_array($columns)) {
            $columns = array_filter(array_map('trim', explode(',', (string)$columns)));
        }

        if ($table === '') {
            Response::error('Please specify a target table for custom report generation.', 400);
        }

        if (empty($columns)) {
            Response::error('Please select at least one column for custom report export.', 400);
        }

        try {
            $data = $this->repo->getCustomReport($table, $columns);
            Response::json($data);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * Direct streaming download fallback for server-side generation.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function export(array $request): void
    {
        Auth::requireAccess('reports', 'read');

        $type = trim((string)($_GET['type'] ?? 'premade'));
        $format = strtolower(trim((string)($_GET['format'] ?? 'csv')));

        if (!in_array($format, ['csv', 'txt', 'xlsx'], true)) {
            Response::error('Unsupported export format.', 400);
        }

        try {
            if ($type === 'premade') {
                $report = trim((string)($_GET['report'] ?? ''));
                $data = $this->repo->getPremadeReport($report);
            } else {
                $table = trim((string)($_GET['table'] ?? ''));
                $colsRaw = $_GET['columns'] ?? '';
                $columns = is_array($colsRaw) ? $colsRaw : explode(',', (string)$colsRaw);
                $data = $this->repo->getCustomReport($table, $columns);
            }

            $filename = ($data['filename'] ?? 'report_' . date('Ymd_His')) . '.' . $format;

            if ($format === 'csv') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                $out = fopen('php://output', 'w');
                // UTF-8 BOM for Excel compatibility with CSV
                fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
                fputcsv($out, $data['headers']);
                foreach ($data['rows'] as $row) {
                    fputcsv($out, $row);
                }
                fclose($out);
                exit;
            } elseif ($format === 'txt') {
                // Critical user requirement:
                // "In the specific case of .txt exports, these must not contain any other separation character, only values and tab characters."
                header('Content-Type: text/plain; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                
                // Clean header line: tabs only, no quotes, no commas
                $headerClean = array_map(function($h) {
                    return str_replace(["\t", "\r", "\n"], ' ', (string)$h);
                }, $data['headers']);
                echo implode("\t", $headerClean) . "\r\n";

                foreach ($data['rows'] as $row) {
                    $cleanedRow = array_map(function($val) {
                        if ($val === null) {
                            return '';
                        }
                        return str_replace(["\t", "\r", "\n"], ' ', (string)$val);
                    }, $row);
                    echo implode("\t", $cleanedRow) . "\r\n";
                }
                exit;
            } else {
                // For XLSX on server side without PHPZip or composer, redirect to client generator or return JSON
                Response::json($data);
            }
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
