<?php
/**
 * api/admin.php - Direct File Scanner & Reporter
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

$action  = $_GET['action'] ?? '';
$baseDir = dirname(__DIR__);

$gameScreenQry = "SELECT `Image` AS IMG FROM `Games` WHERE `Image` IS NOT NULL";
$gameBoxArtQry = "SELECT `BoxArt` AS IMG FROM `Games` WHERE `BoxArt` IS NOT NULL";
$ConsoleImageQry = "SELECT `Image` AS IMG FROM `Consoles` WHERE `Image` IS NOT NULL";
$ConsoleLogoQry = "SELECT `Logo` AS IMG FROM `Consoles` WHERE `Logo` IS NOT NULL";

/**
 * Scans a folder path directly and returns all contained file names as an array.
 *
 * @param string $path Absolute directory path
 * @return array List of filenames or diagnostic error messages
 */
function scan_path_files(string $path): array {
    if (!file_exists($path)) {
        return ["[NOT FOUND] Directory does not exist on disk: {$path}"];
    }

    if (!is_dir($path)) {
        return ["[NOT A DIRECTORY] Path exists but is not a folder: {$path}"];
    }

    if (!is_readable($path)) {
        return ["[PERMISSION DENIED] Folder exists but cannot be read: {$path}"];
    }

    $entries = scandir($path);
    if ($entries === false) {
        return ["[READ FAILURE] scandir() could not read folder contents: {$path}"];
    }

    $files = [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $fullFile = $path . '/' . $entry;
        if (is_file($fullFile)) {
            $files[] = $entry;
        }
    }

    return $files;
}

try {
    if ($action === 'scan_orphans') {
        $targetPaths = [
            'images/games'    => $baseDir . '/images/games',
            'images/consoles' => $baseDir . '/images/consoles',
        ];

        // 1. Scan physical directories using your existing function
        $diskFiles = [];
        foreach ($targetPaths as $prefix =>$dirPath) {
            $files = scan_path_files($dirPath);
            foreach ($files as$file) {
                // Skip error diagnostics strings if a folder was unreadable
                if (str_starts_with($file, '[')) {                     
                    continue;                 
                }
                $diskFiles[] = $prefix . '/' .$file;
            }
        }

        // 2. Direct inline queries for DB paths without helper functions
        // FETCH_COLUMN directly returns a flat 1D array of paths
        $dbImages = array_merge($pdo->query("SELECT `Image` FROM `Games` WHERE `Image` IS NOT NULL AND `Image` != ''")->fetchAll(PDO::FETCH_COLUMN),
            $pdo->query("SELECT `BoxArt` FROM `Games` WHERE `BoxArt` IS NOT NULL AND `BoxArt` != ''")->fetchAll(PDO::FETCH_COLUMN),
            $pdo->query("SELECT `Image` FROM `Consoles` WHERE `Image` IS NOT NULL AND `Image` != ''")->fetchAll(PDO::FETCH_COLUMN),
            $pdo->query("SELECT `Logo` FROM `Consoles` WHERE `Logo` IS NOT NULL AND `Logo` != ''")->fetchAll(PDO::FETCH_COLUMN)
        );

        // 3. Normalize DB paths (trim whitespace and leading slashes)
        $dbLookup = [];
        foreach ($dbImages as$img) {
            $clean = ltrim(str_replace('\\', '/', trim((string)$img)), '/');
            if ($clean !== '') {
                $dbLookup[$clean] = true;
            }
        }

        // 4. Identify orphans (files on disk not referenced in DB)
        $orphans = [];
        foreach ($diskFiles as $file) {
            $cleanDisk = ltrim(str_replace('\\', '/', trim($file)), '/');
            if (!isset($dbLookup[$cleanDisk])) {
                $orphans[] = mb_convert_encoding($file, 'utf-8');
            }
        }
        
        // 5. Store the orphaned files into the DB
        $pdo->exec("TRUNCATE TABLE `Orphans`");
        if (!empty($orphans)) {
            $stmt =$pdo->prepare("INSERT IGNORE INTO `Orphans` (`filepath`) VALUES (?)");
            foreach ($orphans as$orphanPath) {
                $stmt->execute([$orphanPath]);
            }
        }

        // 5. Build report string for the admin textarea log
        $reportLines = [];
        $reportLines[] = "Scan complete at " . date('H:i:s');
        $reportLines[] = "Files checked on disk: " . count($diskFiles);
        $reportLines[] = "Database references checked: " . count($dbLookup);
        $reportLines[] = "Orphaned files found: " . count($orphans);
        //$reportLines[] = "Orphaned files found: " . mb_convert_encoding(print_r($orphans,true), 'utf-8');

        if (!empty($orphans)) {$reportLines[] = "----------------------------------------";
            $reportLines[] = "List of orphaned files:";
            foreach ($orphans as$orphan) {
                $reportLines[] = " - " . $orphan;
            }
        }

        // 6. Return response to frontend
        echo json_encode([
            'success' => true,
            'data'    => [
                'report'  => implode("\n", $reportLines),
                'orphans' => $orphans
            ]
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if ($action === 'purge_orphans') {
        // 1. Check if staging table exists and retrieve file paths
        $stagedFiles =$pdo->query("SELECT `filepath` FROM `Orphans`")->fetchAll(PDO::FETCH_COLUMN);

        if (empty($stagedFiles)) {
            echo json_encode([
                'success' => true,
                'data'    => [
                    'report'  => "No files queued in staging table to purge.\n",
                    'purged'  => 0
                ]
            ]);
            exit;
        }

        // 2. Iterate and delete physical files
        $deletedCount = 0;
        $failedCount  = 0;
        $reportLines  = [];$reportLines[] = "Purge execution started at " . date('H:i:s');
        $reportLines[] = "Queued items in staging: " . count($stagedFiles);$reportLines[] = "-------------------------------------------------------";

        foreach ($stagedFiles as $relPath) {$cleanRel = ltrim(str_replace('\\', '/', trim((string)$relPath)), '/');$fullPath = $baseDir . '/' .$cleanRel;

            // Security guard: ensure targeted file is inside allowed image directories
            $isGamesFolder    = str_starts_with($cleanRel, 'images/games/');
            $isConsolesFolder = str_starts_with($cleanRel, 'images/consoles/');

            if (!$isGamesFolder && !$isConsolesFolder) {$failedCount++;
                $reportLines[] = " [SKIPPED - RESTRICTED PATH] " . $cleanRel;
                continue;
            }

            if (file_exists($fullPath) && is_file($fullPath)) {
                if (@unlink($fullPath)) {$deletedCount++;
                    $reportLines[] = " [DELETED] " . $cleanRel;
                } else {
                    $failedCount++;
                    $reportLines[] = " [FAILED - PERMISSION] " . $cleanRel;
                }
            } else {
                $reportLines[] = " [ALREADY REMOVED] " . $cleanRel;
            }
        }

        // 3. Drop the temporary staging table
        $pdo->exec("DELETE FROM `Orphans`");

        $reportLines[] = "-------------------------------------------------------";
        $reportLines[] = "Purge complete. Successfully deleted: {$deletedCount} | Failed: {$failedCount}";

        echo json_encode([
            'success' => true,
            'data'    => [
                'report'  => implode("\n", $reportLines),
                'purged'  => $deletedCount
            ]
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
    exit;
}