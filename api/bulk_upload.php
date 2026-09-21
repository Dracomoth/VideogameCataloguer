<?php
/**
 * api/bulk_upload.php - Universal Delimited/Excel Bulk Ingestion Endpoint
 */
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$targetTable = $_POST['table'] ?? '';
$allowedTables = [
    'Games'         => 'Games',
    'Consoles'      => 'Consoles',
    'Publishers'    => 'Publishers',
    'Categories'    => 'Categories',
    'Subcategories' => 'Subcategories',
    'Languages'     => 'Languages'
];

if (!isset($allowedTables[$targetTable])) {
    echo json_encode(['success' => false, 'error' => 'Invalid or unauthorized target table specified.']);
    exit;
}

$table = $allowedTables[$targetTable];

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'Uploaded file exceeds the upload_max_filesize directive.',
        UPLOAD_ERR_FORM_SIZE  => 'Uploaded file exceeds the MAX_FILE_SIZE directive.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.'
    ];
    $msg = $uploadErrors[$_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'Upload failed.';
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

$tmpPath   = $_FILES['file']['tmp_name'];
$origName  = $_FILES['file']['name'];
$extension = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

try {
    // -------------------------------------------------------------
    // 1. Parse File Content into Headers and Rows
    // -------------------------------------------------------------
    $headers = [];
    $records = [];

    if (in_array($extension, ['csv', 'txt', 'tsv'], true)) {
        $handle = fopen($tmpPath, 'r');
        if (!$handle) throw new Exception("Could not open uploaded file.");

        // Detect delimiter: tab for .txt/.tsv, comma/semicolon for .csv
        $firstLine = fgets($handle);
        rewind($handle);

        $delimiter = ",";
        if ($extension === 'txt' || $extension === 'tsv' || substr_count($firstLine, "\t") > substr_count($firstLine, ",")) {
            $delimiter = "\t";
        } elseif (substr_count($firstLine, ";") > substr_count($firstLine, ",")) {
            $delimiter = ";";
        }

        // Check and strip UTF-8 BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $rawHeaders = fgetcsv($handle, 0, $delimiter);
        if ($rawHeaders) {
            $headers = array_map(function($h) {
                return trim(trim((string)$h), "\xEF\xBB\xBF \t\n\r\0\x0B");
            }, $rawHeaders);

            $rowIdx = 1;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowIdx++;
                // Skip empty lines
                if (count($row) === 1 && $row[0] === null) continue;
                $records[] = ['row_num' => $rowIdx, 'data' => $row];
            }
        }
        fclose($handle);

    } elseif (in_array($extension, ['xls', 'xlsx'], true)) {
        // Parse standard SpreadsheetML (XML) or fall back to plain text read
        $rawXml = file_get_contents($tmpPath);
        if (strpos($rawXml, 'xmlns="urn:schemas-microsoft-com:office:spreadsheet"') !== false) {
            $xml = simplexml_load_string($rawXml);
            $xml->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
            $xmlRows = $xml->xpath('//ss:Worksheet[1]//ss:Table//ss:Row');

            if (!empty($xmlRows)) {
                $firstRow = true;
                $rowIdx = 0;
                foreach ($xmlRows as $xr) {
                    $rowIdx++;
                    $cells = [];
                    foreach ($xr->xpath('./ss:Cell') as $c) {
                        $cells[] = trim((string)$c->Data);
                    }
                    if ($firstRow) {
                        $headers = $cells;
                        $firstRow = false;
                    } else {
                        $records[] = ['row_num' => $rowIdx, 'data' => $cells];
                    }
                }
            }
        } else {
            // Attempt standard CSV-fallback parse in case it was a renamed delimited file
            $handle = fopen($tmpPath, 'r');
            $rawHeaders = fgetcsv($handle, 0, ",");
            if ($rawHeaders && count($rawHeaders) > 1) {
                $headers = array_map('trim', $rawHeaders);
                $rowIdx = 1;
                while (($row = fgetcsv($handle, 0, ",")) !== false) {
                    $rowIdx++;
                    $records[] = ['row_num' => $rowIdx, 'data' => $row];
                }
            }
            fclose($handle);
        }
    }

    if (empty($headers)) {
        throw new Exception("Unable to parse file headers. Ensure the file contains headers on the first line.");
    }

    // -------------------------------------------------------------
    // 2. Fetch Schema Constraints & Foreign Keys
    // -------------------------------------------------------------
    $tableColsStmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    $dbColumns = $tableColsStmt->fetchAll(PDO::FETCH_ASSOC);

    $dbColLookup = [];
    foreach ($dbColumns as $col) {
        $dbColLookup[strtolower($col['Field'])] = $col['Field'];
    }

    // Retrieve active Foreign Keys for this table
    $fkSql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
              FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL";
    $fkStmt = $pdo->prepare($fkSql);
    $fkStmt->execute([$table]);
    $foreignKeys = $fkStmt->fetchAll(PDO::FETCH_ASSOC);

    $fkRules = [];
    foreach ($foreignKeys as $fk) {
        $fkRules[$fk['COLUMN_NAME']] = [
            'ref_table' => $fk['REFERENCED_TABLE_NAME'],
            'ref_col'   => $fk['REFERENCED_COLUMN_NAME']
        ];
    }

    // -------------------------------------------------------------
    // 3. Map File Columns to Database Columns (Ignore PK `ID`)
    // -------------------------------------------------------------
    $mappedFields = []; // file_col_idx => db_field_name
    foreach ($headers as $idx => $headerName) {
        $cleanHeader = strtolower($headerName);
        if ($cleanHeader === 'id') {
            // Explicitly ignore primary keys to preserve AUTO_INCREMENT
            continue;
        }

        if (isset($dbColLookup[$cleanHeader])) {
            $mappedFields[$idx] = $dbColLookup[$cleanHeader];
        }
    }

    if (empty($mappedFields)) {
        throw new Exception("No matching database fields found for table '{$table}'. Unrecognized headers: " . implode(', ', $headers));
    }

    // -------------------------------------------------------------
    // 4. Batch Insertion with Row-Level Error Tracking
    // -------------------------------------------------------------
    $insertedCount = 0;
    $skippedCount  = 0;
    $errors        = [];

    // Pre-build parameterized INSERT query template
    $insertFields   = array_values($mappedFields);
    $escapedFields  = array_map(function($f) { return "`" . str_replace("`", "``", $f) . "`"; }, $insertFields);
    $placeholders   = array_fill(0, count($insertFields), '?');

    $sqlInsert = "INSERT INTO `{$table}` (" . implode(', ', $escapedFields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $insertStmt = $pdo->prepare($sqlInsert);

    // Pre-cache FK lookups in memory to prevent high query volume on large uploads
    $fkCache = [];

    $pdo->beginTransaction();

    foreach ($records as$item) {
        $rowNumber =$item['row_num'];
        $rowVals   =$item['data'];
        $params    = [];$rowHasError = false;

        foreach ($mappedFields as$fileColIdx => $fieldName) {$rawVal = isset($rowVals[$fileColIdx]) ? trim((string)$rowVals[$fileColIdx]) : '';
            if ($rawVal === '' || strtolower($rawVal) === 'null') {$value = null;
            } else {
                $value =$rawVal;
            }

            // Foreign Key Check
            if (isset($fkRules[$fieldName]) &&$value !== null) {
                $refTable =$fkRules[$fieldName]['ref_table'];$refCol   = $fkRules[$fieldName]['ref_col'];
                $cacheKey = "{$refTable}_{$refCol}_{$value}";

                if (!isset($fkCache[$cacheKey])) {
                    $chk =$pdo->prepare("SELECT 1 FROM `{$refTable}` WHERE `{$refCol}` = ? LIMIT 1");
                    $chk->execute([$value]);$fkCache[$cacheKey] = (bool)$chk->fetchColumn();
                }

                if (!$fkCache[$cacheKey]) {$errors[] = "Row #{$rowNumber}: Foreign Key constraint failed on field '{$fieldName}'. Value '{$value}' does not exist in '{$refTable}'.";
                    $rowHasError = true;
                    break;
                }
            }

            $params[] =$value;
        }

        if ($rowHasError) {$skippedCount++;
            continue;
        }

        try {
            $insertStmt->execute($params);$insertedCount++;
        } catch (PDOException $e) {$skippedCount++;
            $errors[] = "Row #{$rowNumber}: Database insert error - " . $e->getMessage();
        }
    }

    $pdo->commit();

    echo json_encode([
        'success'  => true,
        'summary'  => [
            'table'     => $table,
            'total'     => count($records),
            'inserted'  => $insertedCount,
            'skipped'   => $skippedCount,
            'error_log' => $errors
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {$pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}