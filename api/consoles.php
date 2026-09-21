<?php
/**
 * api/consoles.php - Backend RESTful API & Asset Controller for Consoles
 */
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$baseDir = dirname(__DIR__);
$uploadDir = $baseDir . '/images/consoles';

// Ensure upload directory exists
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

// ---------------------------------------------------------------------------
// File Asset Helpers
// ---------------------------------------------------------------------------

/**
 * Sanitizes console names for filesystem usage (e.g. "Xbox Series X|S" -> "Xbox_Series_X_S")
 */
function slugify_console_name(string $name): string {
    // Replace non-alphanumeric characters (including slashes, spaces, pipes) with underscores
    $clean = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($name));
    // Collapse duplicate underscores and trim edges
    return trim(preg_replace('/_+/', '_', $clean), '_');
}

/**
 * Unlinks the exact file referenced by the database record path.
 */
function remove_console_asset(?string $storedPath): void {
    global $baseDir;

    if (empty($storedPath)) {
        return;
    }

    $clean = ltrim(trim($storedPath), '/');$fullPath = $baseDir . '/' .$clean;

    if (file_exists($fullPath) && is_file($fullPath)) {
        @unlink($fullPath);
    }
}

/**
 * Validates, renames, and stores uploaded images without keeping old versions.
 */
function store_console_asset(array $file, int $consoleId, string $consoleName, string $type = 'image'): ?string {
    global $uploadDir;

    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowedMimes = [
        'image/jpeg'    => 'jpg',
        'image/png'     => 'png',
        'image/webp'    => 'webp',
        'image/gif'     => 'gif',
        'image/svg+xml' => 'svg'
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!isset($allowedMimes[$mime])) {
        throw new Exception("Invalid image format ({$mime}). Allowed: JPG, PNG, WEBP, GIF, SVG.");
    }

    $ext = $allowedMimes[$mime];
    $slug = slugify_console_name($consoleName);

    // Naming format: <ID>_<ConsoleName>_image.<ext> or <ID>_<ConsoleName>_logo.<ext>
    $filename = "{$consoleId}_{$slug}_{$type}.{$ext}";
    $destination = "{$uploadDir}/{$filename}";
    
    // Enforce "no duplicate versions" policy: purge existing files before writing new one
    remove_console_asset($destination);

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception("Failed to save uploaded {$type} to server.");
    }

    return "images/consoles/{$filename}";
}

/**
 * Resolves a web-accessible URL for an asset, falling back to legacy paths if needed.
 */
function resolve_asset_url(?string $path, int $id, string $suffix = ''): string {
    global $baseDir, $uploadDir;

    // 1. Direct path check from DB field
    if (!empty($path)) {
        $clean = trim($path);
        // Absolute or external URLs
        if (str_starts_with($clean, 'http://') || str_starts_with($clean, 'https://')) {
            return $clean;
        }
        // Local relative file check
        if (file_exists($baseDir . '/' . ltrim($clean, '/'))) {
            return ltrim($clean, '/');
        }
    }

    // 2. Fallback: Search disk for new convention: <ID>_*_<type>.<ext>
    if ($id > 0 && is_dir($uploadDir)) {
        $matches = glob("{$uploadDir}/{$id}_*_{$type}.*");
        if (!empty($matches) && is_file($matches[0])) {
            return 'images/consoles/' . basename($matches[0]);
        }

        // 3. Backward compatibility: check legacy patterns (console_{id} or {id})
        $suffix = ($type === 'logo') ? '_logo' : '';
        $legacyMatches = array_merge(
            glob("{$uploadDir}/console_{$id}{$suffix}.*") ?: [],
            glob("{$uploadDir}/{$id}{$suffix}.*") ?: []
        );
        if (!empty($legacyMatches) && is_file($legacyMatches[0])) {
            return 'images/consoles/' . basename($legacyMatches[0]);
        }
    }

    return '';
}

// ---------------------------------------------------------------------------
// Request Router
// ---------------------------------------------------------------------------

try {
    // -------------------------------------------------------------------------
    // GET: List Consoles with Maker metadata & Image URLs
    // -------------------------------------------------------------------------
    if ($method === 'GET') {
        $search = trim($_GET['q'] ?? '');
        $params = [];

        $sql = "SELECT c.*, 
                       p.`Publisher` AS maker_name,
                       COUNT(DISTINCT g.`ID`) AS game_count
                FROM `Consoles` c
                LEFT JOIN `Publishers` p ON c.`Publisher ID` = p.`ID`
                LEFT JOIN `Games` g ON g.`Console ID` = c.`ID`";

        if ($search !== '') {
            $sql .= " WHERE (c.`Console` LIKE ? OR p.`Publisher` LIKE ? OR c.`Year` LIKE ? OR c.`Generation` LIKE ?)";
            $term = "%{$search}%";
            $params = [$term, $term, $term, $term];
        }

        $sql .= " GROUP BY c.`ID` ORDER BY c.`ID` ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $consoles = $stmt->fetchAll();

        foreach ($consoles as &$item) {
            $id = (int)$item['ID'];
            $item['ID']             = $id;
            $item['Publisher ID']   = !empty($item['Publisher ID']) ? (int)$item['Publisher ID'] : null;
            $item['IsHandheld']     = !empty($item['IsHandheld']) ? 1 : 0;
            $item['IsComputer']     = !empty($item['IsComputer']) ? 1 : 0;
            $item['IsArcade']       = !empty($item['IsArcade']) ? 1 : 0;
            $item['IsForReference'] = !empty($item['IsForReference']) ? 1 : 0;
            $item['game_count']     = (int)$item['game_count'];

            // Expose real image URLs
            $item['image_url'] = resolve_asset_url($item['Image'] ?? '', $id, '');
            $item['logo_url']  = resolve_asset_url($item['Logo'] ?? '', $id, '_logo');
        }

        // Fetch makers for select dropdown
        $pubCols = $pdo->query("SHOW COLUMNS FROM `Publishers`")->fetchAll(PDO::FETCH_COLUMN);
        $cmCol = in_array('Console Maker', $pubCols, true) ? '`Console Maker`' : (in_array('ConsoleMaker', $pubCols, true) ? '`ConsoleMaker`' : null);

        $makerSql = "SELECT `ID`, `Publisher` FROM `Publishers`";
        if ($cmCol) {
            $makerSql .= " WHERE {$cmCol} = 1";
        }
        $makerSql .= " ORDER BY `Publisher` ASC";
        $makers = $pdo->query($makerSql)->fetchAll();

        json_response([
            'consoles' => $consoles,
            'makers'   => $makers
        ]);
    }

    // -------------------------------------------------------------------------
    // POST: Create or Update Console Record with Multipart File Assets
    // -------------------------------------------------------------------------
    if ($method === 'POST') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $console = trim($_POST['console'] ?? '');

        if ($console === '') {
            json_error('Console title cannot be empty.', 422);
        }

        $publisherId    = !empty($_POST['publisher_id']) ? (int)$_POST['publisher_id'] : null;
        $year           = trim($_POST['year'] ?? '') ?: null;
        $generation     = trim($_POST['generation'] ?? '') ?: null;
        $isHandheld     = !empty($_POST['is_handheld']) ? 1 : 0;
        $isComputer     = !empty($_POST['is_computer']) ? 1 : 0;
        $isArcade       = !empty($_POST['is_arcade']) ? 1 : 0;
        $isForReference = !empty($_POST['is_for_reference']) ? 1 : 0;
        $comments       = trim($_POST['comments'] ?? '');
        $emulator       = trim($_POST['emulator'] ?? '');
        $emulatorLink   = trim($_POST['emulator_link'] ?? '');
        $emuAndroid     = trim($_POST['emulator_android'] ?? '');
        $emuAndroidLink = trim($_POST['emulator_android_link'] ?? '');
        $retroArchCore  = trim($_POST['retroarch_core'] ?? '');
        $coreLink       = trim($_POST['core_link'] ?? '');

        $deleteImage = !empty($_POST['delete_image']);
        $deleteLogo  = !empty($_POST['delete_logo']);

        if ($id) {
            // --- UPDATE EXISTING CONSOLE ---
            $stmt = $pdo->prepare("SELECT `Image`, `Logo` FROM `Consoles` WHERE `ID` = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch();

            if (!$existing) {
                json_error('Console not found.', 404);
            }

            $currentImage = $existing['Image'];
            $currentLogo  = $existing['Logo'];

            // 1. Process Image Deletion
            if ($deleteImage) {
                remove_console_asset($currentImage);
                $currentImage = null;
            }

            // 2. Process Logo Deletion
            if ($deleteLogo) {
                remove_console_asset($currentLogo);
                $currentLogo = null;
            }

            // 3. Process New Image Upload
            if (!empty($_FILES['image_file']['name'])) {
                $newImage = store_console_asset($_FILES['image_file'], $id, $console, 'image');
                if ($newImage) $currentImage = $newImage;
            }

            // 4. Process New Logo Upload
            if (!empty($_FILES['logo_file']['name'])) {
                $newLogo = store_console_asset($_FILES['logo_file'], $id, $console, 'logo');
                if ($newLogo) $currentLogo = $newLogo;
            }

            $updateSql = "UPDATE `Consoles` SET
                            `Console` = ?, `Publisher ID` = ?, `Year` = ?, `Generation` = ?,
                            `IsHandheld` = ?, `IsComputer` = ?, `IsArcade` = ?, `IsForReference` = ?,
                            `Image` = ?, `Logo` = ?, `Comments` = ?,
                            `Emulator` = ?, `Emulator Link` = ?,
                            `EmulatorAndroid` = ?, `EmulatorAndroid Link` = ?,
                            `RetroArchCore` = ?, `Core Link` = ?
                          WHERE `ID` = ?";
            $pdo->prepare($updateSql)->execute([
                $console,$publisherId, $year,$generation,
                $isHandheld,$isComputer, $isArcade,$isForReference,
                $currentImage,$currentLogo, $comments,$emulator, $emulatorLink,$emuAndroid, $emuAndroidLink,$retroArchCore, $coreLink,$id
            ]);

            json_response([
                'id'      => $id,
                'message' => "Console #{$id} updated successfully."
            ]);
        } else {
            // --- INSERT NEW CONSOLE ---
            $insertSql = "INSERT INTO `Consoles` (
                            `Console`, `Publisher ID`, `Year`, `Generation`,
                            `IsHandheld`, `IsComputer`, `IsArcade`, `IsForReference`,
                            `Image`, `Logo`, `Comments`,
                            `Emulator`, `Emulator Link`,
                            `EmulatorAndroid`, `EmulatorAndroid Link`,
                            `RetroArchCore`, `Core Link`
                          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, ?, ?)";
            
            $pdo->prepare($insertSql)->execute([$console, $publisherId,$year, $generation,$isHandheld, $isComputer,$isArcade, $isForReference,$comments, $emulator,$emulatorLink,
                $emuAndroid,$emuAndroidLink, $retroArchCore,$coreLink
            ]);

            $newId = (int)$pdo->lastInsertId();

            // Store uploaded images using the new ID
            $storedImage = null;
            $storedLogo  = null;

            if (!empty($_FILES['image_file']['name'])) {$storedImage = store_console_asset($_FILES['image_file'], $newId, $console, 'image');
            }
            if (!empty($_FILES['logo_file']['name'])) {$storedLogo = store_console_asset($_FILES['logo_file'], $newId, $console, 'logo');
            }

            if ($storedImage || $storedLogo) {$pdo->prepare("UPDATE `Consoles` SET `Image` = ?, `Logo` = ? WHERE `ID` = ?")
                    ->execute([$storedImage, $storedLogo,$newId]);
            }

            json_response([
                'id'      => $newId,
                'message' => "Console #{$newId} created successfully."
            ], 201);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE: Safeguarded Console & Associated Image Deletion
    // -------------------------------------------------------------------------
    if ($method === 'DELETE' || ($method === 'POST' && ($_POST['action'] ?? '') === 'delete')) {
        $raw = file_get_contents('php://input');$data = json_decode($raw, true) ?? $_POST;
        $id = !empty($data['id']) ? (int)$data['id'] : (int)($_GET['id'] ?? 0);

        if (!$id) {
            json_error('Valid Console ID is required for deletion.', 400);
        }

        // Integrity check: do games reference this console?
        $checkGames =$pdo->prepare("SELECT COUNT(*) FROM `Games` WHERE `Console ID` = ?");
        $checkGames->execute([$id]);
        $linkedGames = (int)$checkGames->fetchColumn();

        if ($linkedGames > 0) {
            json_error(
                "Cannot delete: Console is referenced by {$linkedGames} game(s). Reassign or delete those games first.",
                409,
                ['linked_games' => $linkedGames]
            );
        }

        // Fetch stored image paths before removing the record
        $stmt =$pdo->prepare("SELECT `Image`, `Logo` FROM `Consoles` WHERE `ID` = ?");
        $stmt->execute([$id]);
        $files =$stmt->fetch();

        if ($files) {
            remove_console_asset($files['Image']);
            remove_console_asset($files['Logo']);
        }

        $delStmt =$pdo->prepare("DELETE FROM `Consoles` WHERE `ID` = ?");
        $delStmt->execute([$id]);

        json_response([
            'id'      => $id,
            'message' => "Console #{$id} and its associated media files were deleted."
        ]);
    }

    json_error('Method not allowed.', 405);

} catch (Exception $e) {
    error_log('[Consoles API Error] ' . $e->getMessage());
    json_error('Operation failed: ' . $e->getMessage(), 500);
}