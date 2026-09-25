<?php
/**
 * api/games.php - Backend RESTful Endpoint & Asset Controller for Games
 * (Cleaned: NoCover and NoScreen columns removed)
 */
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$method    = $_SERVER['REQUEST_METHOD'];
$baseDir   = dirname(__DIR__);
$uploadDir = $baseDir . '/images/games';

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

// ---------------------------------------------------------------------------
// File Asset Helpers
// ---------------------------------------------------------------------------

function remove_game_asset(?string $storedPath): void {
    global $baseDir;

    if (empty($storedPath)) {
        return;
    }

    $clean = ltrim(trim($storedPath), '/');
    $fullPath = $baseDir . '/' . $clean;

    if (file_exists($fullPath) && is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function store_game_asset(array $file, int $gameId, string $type = 'Img'): ?string {
    global $uploadDir;

    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    if (!isset($allowedMimes[$mime])) {
        throw new Exception("Invalid image format ({$mime}). Allowed: JPG, PNG, WEBP, GIF.");
    }

    $ext         = $allowedMimes[$mime];
    $filename    = "{$gameId}_{$type}.{$ext}";
    $destination = "{$uploadDir}/{$filename}";

    if (file_exists($destination)) {
        @unlink($destination);
    }

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception("Failed to save uploaded {$type} file.");
    }

    return "images/games/{$filename}";
}

function resolve_game_asset_url(?string $path): string {
    global $baseDir;

    // Strictly return empty if the database path is NULL or empty
    if (empty($path)) {
        return '';
    }

    $clean = trim($path);

    // Support external URLs
    if (str_starts_with($clean, 'http://') || str_starts_with($clean, 'https://')) {
        return $clean;
    }

    // Verify local file existence for the exact recorded path
    if (file_exists($baseDir . '/' . ltrim($clean, '/'))) {
        return ltrim($clean, '/');
    }

    return '';
}

// ---------------------------------------------------------------------------
// Request Router
// ---------------------------------------------------------------------------

try {
    // -------------------------------------------------------------------------
    // GET: List Games (with pagination & multi-faceted search) + Dropdowns
    // -------------------------------------------------------------------------
    if ($method === 'GET') {
        $search     = trim($_GET['q'] ?? '');
        $consoleId  = !empty($_GET['console_id']) ? (int)$_GET['console_id'] : null;
        $categoryId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $page       = max(1, (int)($_GET['page'] ?? 1));
        $limit      = max(10, min(100, (int)($_GET['limit'] ?? 25)));
        $offset     = ($page - 1) * $limit;

        $where  = [];
        $params = [];

        if ($search !== '') {
            $where[]  = "(g.`Game` LIKE ? OR g.`Comments` LIKE ? OR g.`Tags` LIKE ?)";
            $term     = "%{$search}%";
            $params[] = $term; $params[] = $term; $params[] = $term;
        }
        if ($consoleId) {
            $where[]  = "g.`Console ID` = ?";
            $params[] = $consoleId;
        }
        if ($categoryId) {
            $where[]  = "g.`Category ID` = ?";
            $params[] = $categoryId;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `Games` g {$whereClause}");
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetchColumn();

        $sql = "SELECT g.*,
                       c.`Console` AS console_name,
                       cat.`Category` AS category_name,
                       sub.`Subcategory` AS subcategory_name,
                       p.`Publisher` AS publisher_name,
                       l.`Language` AS language_name
                FROM `Games` g
                LEFT JOIN `Consoles` c ON g.`Console ID` = c.`ID`
                LEFT JOIN `Categories` cat ON g.`Category ID` = cat.`ID`
                LEFT JOIN `Subcategories` sub ON g.`Subcategory ID` = sub.`ID`
                LEFT JOIN `Publishers` p ON g.`Publisher ID` = p.`ID`
                LEFT JOIN `Languages` l ON g.`Language ID` = l.`ID`
                {$whereClause}
                ORDER BY g.`ID` DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $games = $stmt->fetchAll();

        foreach ($games as &$row) {
            $id = (int)$row['ID'];
            $row['ID']             = $id;
            $row['Console ID']     = !empty($row['Console ID']) ? (int)$row['Console ID'] : null;
            $row['Category ID']    = !empty($row['Category ID']) ? (int)$row['Category ID'] : null;
            $row['Subcategory ID'] = !empty($row['Subcategory ID']) ? (int)$row['Subcategory ID'] : null;
            $row['Language ID']    = !empty($row['Language ID']) ? (int)$row['Language ID'] : null;
            $row['Publisher ID']   = !empty($row['Publisher ID']) ? (int)$row['Publisher ID'] : null;
            $row['InCollection']   = !empty($row['InCollection']) ? 1 : 0;
            $row['Played']         = !empty($row['Played']) ? 1 : 0;
            $row['Won']            = !empty($row['Won']) ? 1 : 0;

            // Resolved media URLs
            // Updated code:
            $row['boxart_url'] = resolve_game_asset_url($row['BoxArt'] ?? null);
            $row['screen_url'] = resolve_game_asset_url($row['Image'] ?? null);
        }

        // Dropdown lookups
        $consoles   = $pdo->query("SELECT `ID`, `Console`, `IsHandheld` FROM `Consoles` ORDER BY `Console` ASC")->fetchAll();
        $categories = $pdo->query("SELECT `ID`, `Category` FROM `Categories` ORDER BY `Category` ASC")->fetchAll();
        $publishers = $pdo->query("SELECT `ID`, `Publisher` FROM `Publishers` ORDER BY `Publisher` ASC")->fetchAll();
        $languages  = $pdo->query("SELECT `ID`, `Language` FROM `Languages` ORDER BY `Language` ASC")->fetchAll();
        
        $subCols = $pdo->query("SHOW COLUMNS FROM `Subcategories`")->fetchAll(PDO::FETCH_COLUMN);
        $subCatFk = in_array('Category ID', $subCols, true) ? '`Category ID`' : '`CategoryID`';
        $subNameCol = in_array('Subcategory', $subCols, true) ? '`Subcategory`' : '`Name`';
        $subcategories = $pdo->query("SELECT `ID`, {$subNameCol} AS subcategory, {$subCatFk} AS category_id FROM `Subcategories` ORDER BY subcategory ASC")->fetchAll();

        json_response([
            'games'         => $games,
            'total'         => $totalRecords,
            'page'          => $page,
            'limit'         => $limit,
            'total_pages'   => max(1, (int)ceil($totalRecords / $limit)),
            'lookups'       => [
                'consoles'      => $consoles,
                'categories'    => $categories,
                'subcategories' => $subcategories,
                'publishers'    => $publishers,
                'languages'     => $languages
            ]
        ]);
    }

    // -------------------------------------------------------------------------
    // POST: Create or Update Game Record & Manage Assets
    // -------------------------------------------------------------------------
    if ($method === 'POST') {
        $id   = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $game = trim($_POST['game'] ?? '');

        if ($game === '') {
            json_error('Game title cannot be empty.', 422);
        }

        $consoleId     = !empty($_POST['console_id']) ? (int)$_POST['console_id'] : null;
        $categoryId    = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $subcategoryId = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
        $languageId    = !empty($_POST['language_id']) ? (int)$_POST['language_id'] : null;
        $publisherId   = !empty($_POST['publisher_id']) ? (int)$_POST['publisher_id'] : null;
        $year          = trim($_POST['year'] ?? '') ?: null;
        $tags          = trim($_POST['tags'] ?? '');
        $comments      = trim($_POST['comments'] ?? '');

        $inCollection  = !empty($_POST['in_collection']) ? 1 : 0;
        $played        = !empty($_POST['played']) ? 1 : 0;
        $won           = !empty($_POST['won']) ? 1 : 0;

        $deleteBoxArt  = !empty($_POST['delete_boxart']);
        $deleteScreen  = !empty($_POST['delete_screen']);

        if ($id) {
            // --- UPDATE EXISTING GAME ---
            $stmt = $pdo->prepare("SELECT `Image`, `BoxArt` FROM `Games` WHERE `ID` = ?");
            $stmt->execute([$id]);
            $existing =$stmt->fetch();

            if (!$existing) {
                json_error('Game record not found.', 404);
            }

            $currentBoxArt =$existing['BoxArt'];
            $currentScreen =$existing['Image'];

            // 1. Delete BoxArt if explicitly removed
            if ($deleteBoxArt && !empty($currentBoxArt)) {
                remove_game_asset($currentBoxArt);$currentBoxArt = null;
            }

            // 2. Delete Screenshot if explicitly removed
            if ($deleteScreen && !empty($currentScreen)) {
                remove_game_asset($currentScreen);$currentScreen = null;
            }

            // 3. Handle new BoxArt upload
            if (!empty($_FILES['boxart_file']['name'])) {
                remove_game_asset($currentBoxArt);$currentBoxArt = store_game_asset($_FILES['boxart_file'],$id, 'Box');
            }

            // 4. Handle new Screenshot upload
            if (!empty($_FILES['screen_file']['name'])) {
                remove_game_asset($currentScreen);$currentScreen = store_game_asset($_FILES['screen_file'],$id, 'Img');
            }

            $updateSql = "UPDATE `Games` SET
                            `Game` = ?, `Console ID` = ?, `Category ID` = ?, `Subcategory ID` = ?,
                            `Language ID` = ?, `Publisher ID` = ?, `Year` = ?, `Tags` = ?,
                            `Image` = ?, `BoxArt` = ?, `InCollection` = ?,
                            `Played` = ?, `Won` = ?, `Comments` = ?
                          WHERE `ID` = ?";
            
            $pdo->prepare($updateSql)->execute([$game, $consoleId,$categoryId, $subcategoryId,$languageId, $publisherId,$year, $tags,$currentScreen, $currentBoxArt,$inCollection,
                $played,$won, $comments,$id
            ]);

            json_response([
                'id'      => $id,
                'message' => "Game #{$id} updated successfully."
            ]);
        } else {
            // --- INSERT NEW GAME ---
            $insertSql = "INSERT INTO `Games` (
                            `Game`, `Console ID`, `Category ID`, `Subcategory ID`,
                            `Language ID`, `Publisher ID`, `Year`, `Tags`,
                            `Image`, `BoxArt`, `InCollection`,
                            `Played`, `Won`, `Comments`
                          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?)";
            
            $pdo->prepare($insertSql)->execute([
                $game,$consoleId, $categoryId,$subcategoryId,
                $languageId,$publisherId, $year,$tags,
                $inCollection,$played, $won,$comments
            ]);

            $newId = (int)$pdo->lastInsertId();

            $storedBoxArt = null;
            $storedScreen = null;

            if (!empty($_FILES['boxart_file']['name'])) {$storedBoxArt = store_game_asset($_FILES['boxart_file'],$newId, 'Box');
            }
            if (!empty($_FILES['screen_file']['name'])) {$storedScreen = store_game_asset($_FILES['screen_file'],$newId, 'Img');
            }

            if ($storedBoxArt || $storedScreen) {$pdo->prepare("UPDATE `Games` SET `BoxArt` = ?, `Image` = ? WHERE `ID` = ?")
                    ->execute([$storedBoxArt, $storedScreen,$newId]);
            }

            json_response([
                'id'      => $newId,
                'message' => "Game #{$newId} created successfully."
            ], 201);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE: Remove Game and Associated Media Assets
    // -------------------------------------------------------------------------
    if ($method === 'DELETE' || ($method === 'POST' && ($_POST['action'] ?? '') === 'delete')) {
        $raw  = file_get_contents('php://input');$data = json_decode($raw, true) ?? $_POST;
        $id   = !empty($data['id']) ? (int)$data['id'] : (int)($_GET['id'] ?? 0);

        if (!$id) {
            json_error('Valid Game ID is required for deletion.', 400);
        }

        $stmt =$pdo->prepare("SELECT `Image`, `BoxArt` FROM `Games` WHERE `ID` = ?");
        $stmt->execute([$id]);
        $files =$stmt->fetch();

        if ($files) {
            remove_game_asset($files['Image']);
            remove_game_asset($files['BoxArt']);
        }

        $delStmt =$pdo->prepare("DELETE FROM `Games` WHERE `ID` = ?");
        $delStmt->execute([$id]);

        json_response([
            'id'      => $id,
            'message' => "Game #{$id} and its associated images were deleted."
        ]);
    }

    json_error('Method not allowed.', 405);

} catch (Exception $e) {
    error_log('[Games API Error] ' . $e->getMessage());
    json_error('Operation failed: ' . $e->getMessage(), 500);
}