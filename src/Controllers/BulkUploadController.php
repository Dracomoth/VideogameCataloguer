<?php
/**
 * src/Controllers/BulkUploadController.php
 * Controller orchestrating database bulk ingestion workbench, schema inspection, validation, and batch execution.
 */

declare(strict_types=1);

namespace Vault\Controllers;

use Vault\Auth\Auth;
use Vault\Repositories\BulkUploadRepository;
use Vault\Services\Response;
use Vault\Services\View;
use Throwable;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class BulkUploadController
{
    private BulkUploadRepository $repo;

    public function __construct()
    {
        $this->repo = new BulkUploadRepository();
    }

    /**
     * Renders the Database Bulk Ingestion Workbench UI.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function index(array $request): void
    {
        Auth::requireAccess('bulk_upload', 'read');

        $canWrite = Auth::canWrite('bulk_upload');
        $catalog  = $this->repo->getSchemaCatalog();

        $viewData = [
            'pageTitle'  => 'Database Bulk Ingestion Workbench',
            'activeNav'  => 'bulk_upload',
            'catalog'    => $catalog,
            'canWrite'   => $canWrite,
        ];

        $html = View::render('bulk_upload', $viewData, 'layout');
        Response::html($html);
    }

    /**
     * API endpoint returning schema information for supported tables.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function schema(array $request): void
    {
        Auth::requireAccess('bulk_upload', 'read');

        $table = $request['params']['table'] ?? $request['query']['table'] ?? null;
        if ($table !== null && $table !== '') {
            try {
                $schema = $this->repo->getTableSchema((string)$table);
                Response::json($schema);
            } catch (Throwable $e) {
                Response::error($e->getMessage(), 400);
            }
        }

        Response::json($this->repo->getSchemaCatalog());
    }

    /**
     * Validates a batch of records against target table schema and database constraints.
     * Accepts either a JSON payload with `table` and `records`, or a multipart/form-data upload.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function validateBatch(array $request): void
    {
        Auth::requireAccess('bulk_upload', 'write');

        $body = $request['body'] ?? [];
        $table = trim((string)($body['table'] ?? ''));

        if ($table === '') {
            Response::error('Target table is required.', 400);
        }

        if (!in_array($table, BulkUploadRepository::ALLOWED_TABLES, true)) {
            Response::error("Table '{$table}' is not supported for bulk upload.", 400);
        }

        // 1. Check if records were provided directly in body JSON
        $records = $body['records'] ?? null;

        // 2. If no records array, check if a file was uploaded via multipart/form-data
        if (!is_array($records) && !empty($request['files']['file']['tmp_name'])) {
            $uploaded = $request['files']['file'];
            try {
                $records = $this->parseUploadedTextFile($uploaded['tmp_name'], (string)$uploaded['name']);
            } catch (Throwable $e) {
                Response::error('Failed to parse uploaded file: ' . $e->getMessage(), 400);
            }
        }

        if (!is_array($records) || empty($records)) {
            Response::error('No records found in payload or file to validate.', 400);
        }

        try {
            $validation = $this->repo->validateBatch($table, $records);
            Response::json($validation);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * Executes the insertion of validated records into the target database table.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function executeBatch(array $request): void
    {
        Auth::requireAccess('bulk_upload', 'write');

        $body = $request['body'] ?? [];
        $table = trim((string)($body['table'] ?? ''));
        $records = $body['records'] ?? [];

        if ($table === '') {
            Response::error('Target table is required.', 400);
        }

        if (!in_array($table, BulkUploadRepository::ALLOWED_TABLES, true)) {
            Response::error("Table '{$table}' is not supported for bulk upload.", 400);
        }

        if (!is_array($records) || empty($records)) {
            Response::error('No valid records provided for insertion.', 400);
        }

        try {
            $result = $this->repo->insertBatch($table, $records);
            Response::json($result);
        } catch (Throwable $e) {
            Response::error('Batch insertion failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Parses a delimited text file (CSV, TSV, TXT) into an array of associative record arrays.
     *
     * @param string $filePath
     * @param string $originalName
     * @return array<int, array<string, mixed>>
     */
    private function parseUploadedTextFile(string $filePath, string $originalName): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \RuntimeException('Uploaded file cannot be read.');
        }

        $content = file_get_contents($filePath);
        if ($content === false || trim($content) === '') {
            return [];
        }

        // Auto-detect delimiter from first line (tab, semicolon, or comma)
        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = ',';
        $tabCount = substr_count($firstLine, "\t");
        $semiCount = substr_count($firstLine, ';');
        $commaCount = substr_count($firstLine, ',');

        if ($tabCount > $commaCount && $tabCount > $semiCount) {
            $delimiter = "\t";
        } elseif ($semiCount > $commaCount && $semiCount > $tabCount) {
            $delimiter = ';';
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Could not open file handle.');
        }

        // Strip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = null;
        $records = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            // Skip empty rows
            if ($row === [null] || empty(array_filter($row, fn($v) => trim((string)$v) !== ''))) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map('trim', $row);
                continue;
            }

            $record = [];
            foreach ($headers as $colIndex => $headerName) {
                $record[$headerName] = isset($row[$colIndex]) ? trim((string)$row[$colIndex]) : null;
            }
            $records[] = $record;
        }

        fclose($handle);
        return $records;
    }
}
