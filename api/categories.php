<?php
/**
 * api/categories.php - Backend RESTful Endpoint for Categories
 */
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$method   =$_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');$input    = json_decode($rawInput, true) ?? $_POST;

// Detect Subcategories foreign key column dynamically (handles `Category ID` vs `CategoryID`)
$subCols =$pdo->query("SHOW COLUMNS FROM `Subcategories`")->fetchAll(PDO::FETCH_COLUMN);
$subCatFk = in_array('Category ID',$subCols, true) ? '`Category ID`' : '`CategoryID`';

// Detect Subcategory label column (`Subcategory` vs `Name`)
$subLabelCol = in_array('Subcategory',$subCols, true) ? '`Subcategory`' : '`Name`';

try {
    // -------------------------------------------------------------------------
    // GET: List all categories with subcategory & game counts + subcategory list
    // -------------------------------------------------------------------------
    if ($method === 'GET') {$search = trim($_GET['q'] ?? '');$params = [];

        $sql = "SELECT c.`ID`, c.`Category`,
                       COUNT(DISTINCT g.`ID`) AS game_count,
                       COUNT(DISTINCT s.`ID`) AS subcat_count,
                       GROUP_CONCAT(DISTINCT s.{$subLabelCol} ORDER BY s.{$subLabelCol} ASC SEPARATOR '||') AS subcategories_joined
                FROM `Categories` c
                LEFT JOIN `Games` g ON g.`Category ID` = c.`ID`
                LEFT JOIN `Subcategories` s ON s.{$subCatFk} = c.`ID`";

        if ($search !== '') {$sql .= " WHERE c.`Category` LIKE ?";
            $params[] = "\%{$search}%";
        }

        $sql .= " GROUP BY c.`ID`, c.`Category` ORDER BY c.`ID` ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $categories =$stmt->fetchAll();

        // Format data types and build clean subcategory array
        foreach ($categories as &$item) {
            $item['ID']           = (int)$item['ID'];
            $item['game_count']   = (int)$item['game_count'];
            $item['subcat_count'] = (int)$item['subcat_count'];
            $item['subcategories'] = !empty($item['subcategories_joined']) 
                ? explode('||', $item['subcategories_joined']) 
                : [];
            unset($item['subcategories_joined']);
        }

        json_response($categories);
    }

    // -------------------------------------------------------------------------
    // POST: Create or Update a Category
    // -------------------------------------------------------------------------
    if ($method === 'POST') {$id       = !empty($input['id']) ? (int)$input['id'] : null;
        $category = trim($input['category'] ?? '');

        if ($category === '') {
            json_error('Category name cannot be empty.', 422);
        }

        if ($id) {
            // Check for duplicate names on other IDs
            $check =$pdo->prepare("SELECT `ID` FROM `Categories` WHERE LOWER(`Category`) = LOWER(?) AND `ID` != ?");
            $check->execute([$category,$id]);
            if ($check->fetch()) {
                json_error("Another category named '{$category}' already exists.", 409);
            }

            $stmt =$pdo->prepare("UPDATE `Categories` SET `Category` = ? WHERE `ID` = ?");
            $stmt->execute([$category,$id]);

            json_response([
                'id'       => $id,
                'category' => $category,
                'message'  => "Category #{$id} updated."
            ]);
        } else {
            // Check for duplicate name
            $check =$pdo->prepare("SELECT `ID` FROM `Categories` WHERE LOWER(`Category`) = LOWER(?)");
            $check->execute([$category]);
            if ($check->fetch()) {
                json_error("A category named '{$category}' already exists.", 409);
            }

            $stmt =$pdo->prepare("INSERT INTO `Categories` (`Category`) VALUES (?)");
            $stmt->execute([$category]);
            $newId = (int)$pdo->lastInsertId();

            json_response([
                'id'       => $newId,
                'category' => $category,
                'message'  => "Category created successfully."
            ], 201);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE: Safeguarded removal
    // -------------------------------------------------------------------------
    if ($method === 'DELETE' || ($method === 'POST' && ($input['action'] ?? '') === 'delete')) {
        $id = !empty($input['id']) ? (int)$input['id'] : (int)($_GET['id'] ?? 0);

        if (!$id) {
            json_error('Valid Category ID is required for deletion.', 400);
        }

        // 1. Check linked games
        $checkGames =$pdo->prepare("SELECT COUNT(*) FROM `Games` WHERE `Category ID` = ?");
        $checkGames->execute([$id]);
        $linkedGames = (int)$checkGames->fetchColumn();

        // 2. Check linked subcategories
        $checkSubcats =$pdo->prepare("SELECT COUNT(*) FROM `Subcategories` WHERE {$subCatFk} = ?");
        $checkSubcats->execute([$id]);
        $linkedSubcats = (int)$checkSubcats->fetchColumn();

        if ($linkedGames > 0 || $linkedSubcats > 0) {$reasons = [];
            if ($linkedGames > 0) $reasons[] = "{$linkedGames} game(s)";
            if ($linkedSubcats > 0) $reasons[] = "{$linkedSubcats} subcategory(ies)";

            json_error(
                "Cannot delete: Category is linked to " . implode(' and ', $reasons) . ". Reassign them before deleting.",
                409,
                ['linked_games' => $linkedGames, 'linked_subcategories' =>$linkedSubcats]
            );
        }

        $stmt =$pdo->prepare("DELETE FROM `Categories` WHERE `ID` = ?");
        $stmt->execute([$id]);

        json_response([
            'id'      => $id,
            'message' => "Category #{$id} deleted successfully."
        ]);
    }

    json_error('Method not allowed.', 405);

} catch (PDOException $e) {
    error_log('[Categories API Error] ' . $e->getMessage());
    json_error('Database operation failed: ' . $e->getMessage(), 500);
}