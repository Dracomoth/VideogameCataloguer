<?php
/**
 * api/subcategories.php - Backend RESTful Endpoint for Subcategories
 */
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$method   =$_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');$input    = json_decode($rawInput, true) ?? $_POST;

// 1. Detect column schema variations dynamically
$subCols =$pdo->query("SHOW COLUMNS FROM `Subcategories`")->fetchAll(PDO::FETCH_COLUMN);

$subCol = in_array('Subcategory',$subCols, true) ? '`Subcategory`' : (in_array('Name', $subCols, true) ? '`Name`' : '`Subcategory`');
$catFkCol = in_array('Category ID',$subCols, true) ? '`Category ID`' : (in_array('CategoryID', $subCols, true) ? '`CategoryID`' : '`Category ID`');

// Detect Games table column variation for subcategory link
$gameCols =$pdo->query("SHOW COLUMNS FROM `Games`")->fetchAll(PDO::FETCH_COLUMN);
$gameSubFk = in_array('Subcategory ID',$gameCols, true) ? '`Subcategory ID`' : (in_array('SubcategoryID', $gameCols, true) ? '`SubcategoryID`' : '`Subcategory ID`');

try {
    // -------------------------------------------------------------------------
    // GET: List all subcategories with parent category details & game counts
    // -------------------------------------------------------------------------
    if ($method === 'GET') {$categoryId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $search     = trim($_GET['q'] ?? '');$params     = [];

        // Main subcategories query
        $sql = "SELECT s.`ID`, s.{$subCol} AS subcategory, s.{$catFkCol} AS category_id,
                       COALESCE(c.`Category`, CONCAT('Category #', s.{$catFkCol})) AS category_name,
                       COUNT(DISTINCT g.`ID`) AS game_count
                FROM `Subcategories` s
                LEFT JOIN `Categories` c ON s.{$catFkCol} = c.`ID`
                LEFT JOIN `Games` g ON g.{$gameSubFk} = s.`ID`";

        $where = [];
        if ($categoryId) {
            $where[] = "s.{$catFkCol} = ?";
            $params[] =$categoryId;
        }
        if ($search !== '') {
            $where[] = "(s.{$subCol} LIKE ? OR c.`Category` LIKE ?)";
            $params[] = "\%{$search}%";
            $params[] = "\%{$search}%";
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " GROUP BY s.`ID`, s.{$subCol}, s.{$catFkCol}, c.`Category` ORDER BY c.`Category` ASC, s.{$subCol} ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $subcategories =$stmt->fetchAll();

        foreach ($subcategories as &$item) {
            $item['ID']          = (int)$item['ID'];
            $item['category_id'] = (int)$item['category_id'];
            $item['game_count']  = (int)$item['game_count'];
        }

        // Fetch categories list for dropdowns
        $categories =$pdo->query("SELECT `ID`, `Category` FROM `Categories` ORDER BY `Category` ASC")->fetchAll();
        foreach ($categories as &$c) {
            $c['ID'] = (int)$c['ID'];
        }

        json_response([
            'subcategories' => $subcategories,
            'categories'    => $categories
        ]);
    }

    // -------------------------------------------------------------------------
    // POST: Create or Update a Subcategory
    // -------------------------------------------------------------------------
    if ($method === 'POST') {$id          = !empty($input['id']) ? (int)$input['id'] : null;
        $subcategory = trim($input['subcategory'] ?? '');$categoryId  = !empty($input['category_id']) ? (int)$input['category_id'] : null;

        if ($subcategory === '') {
            json_error('Subcategory name cannot be empty.', 422);
        }
        if (!$categoryId) {
            json_error('Please select a valid parent category.', 422);
        }

        // Verify that parent category exists
        $catCheck =$pdo->prepare("SELECT `ID` FROM `Categories` WHERE `ID` = ?");
        $catCheck->execute([$categoryId]);
        if (!$catCheck->fetch()) {
            json_error('Selected parent category does not exist.', 404);
        }

        if ($id) {
            // Check for duplicate subcategory name UNDER THE SAME CATEGORY (excluding current record)
            $dupCheck =$pdo->prepare("SELECT `ID` FROM `Subcategories` 
                                       WHERE LOWER({$subCol}) = LOWER(?) 
                                         AND {$catFkCol} = ? 
                                         AND `ID` != ?");
            $dupCheck->execute([$subcategory, $categoryId,$id]);
            if ($dupCheck->fetch()) {
                json_error("A subcategory named '{$subcategory}' already exists under this category.", 409);
            }

            $stmt =$pdo->prepare("UPDATE `Subcategories` SET {$subCol} = ?, {$catFkCol} = ? WHERE `ID` = ?");
            $stmt->execute([$subcategory, $categoryId,$id]);

            json_response([
                'id'          => $id,
                'subcategory' => $subcategory,
                'category_id' => $categoryId,
                'message'     => "Subcategory #{$id} updated successfully."
            ]);
        } else {
            // Check for duplicate subcategory name UNDER THE SAME CATEGORY
            $dupCheck =$pdo->prepare("SELECT `ID` FROM `Subcategories` 
                                       WHERE LOWER({$subCol}) = LOWER(?) 
                                         AND {$catFkCol} = ?");
            $dupCheck->execute([$subcategory,$categoryId]);
            if ($dupCheck->fetch()) {
                json_error("A subcategory named '{$subcategory}' already exists under this category.", 409);
            }

            $stmt =$pdo->prepare("INSERT INTO `Subcategories` ({$subCol}, {$catFkCol}) VALUES (?, ?)");
            $stmt->execute([$subcategory,$categoryId]);
            $newId = (int)$pdo->lastInsertId();

            json_response([
                'id'          => $newId,
                'subcategory' => $subcategory,
                'category_id' => $categoryId,
                'message'     => "Subcategory created successfully."
            ], 201);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE: Remove Subcategory (guarded against orphan games)
    // -------------------------------------------------------------------------
    if ($method === 'DELETE' || ($method === 'POST' && ($input['action'] ?? '') === 'delete')) {
        $id = !empty($input['id']) ? (int)$input['id'] : (int)($_GET['id'] ?? 0);

        if (!$id) {
            json_error('Valid Subcategory ID is required for deletion.', 400);
        }

        // Check if any games are linked to this subcategory
        $checkStmt =$pdo->prepare("SELECT COUNT(*) FROM `Games` WHERE {$gameSubFk} = ?");
        $checkStmt->execute([$id]);
        $linkedGames = (int)$checkStmt->fetchColumn();

        if ($linkedGames > 0) {
            json_error(
                "Cannot delete: Subcategory is assigned to {$linkedGames} game(s). Reassign them first.",
                409,
                ['linked_games' => $linkedGames]
            );
        }

        $stmt =$pdo->prepare("DELETE FROM `Subcategories` WHERE `ID` = ?");
        $stmt->execute([$id]);

        json_response([
            'id'      => $id,
            'message' => "Subcategory #{$id} deleted successfully."
        ]);
    }

    json_error('Method not allowed.', 405);

} catch (PDOException $e) {
    error_log('[Subcategories API Error] ' . $e->getMessage());
    json_error('Database operation failed: ' . $e->getMessage(), 500);
}