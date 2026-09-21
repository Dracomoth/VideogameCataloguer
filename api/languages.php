<?php
/**
 * api/languages.php - Backend RESTful Endpoint for Languages
 */
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$method =$_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');$input = json_decode($rawInput, true) ?? $_POST;

try {
    // -------------------------------------------------------------------------
    // GET: List all languages or search query with live game usage counts
    // -------------------------------------------------------------------------
    if ($method === 'GET') {$search = trim($_GET['q'] ?? '');$params = [];

        $sql = "SELECT l.`ID`, l.`Language`, COUNT(g.`ID`) AS game_count
                FROM `Languages` l
                LEFT JOIN `Games` g ON g.`Language ID` = l.`ID`";

        if ($search !== '') {$sql .= " WHERE l.`Language` LIKE ?";
            $params[] = "\%{$search}%";
        }

        $sql .= " GROUP BY l.`ID`, l.`Language` ORDER BY l.`ID` ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $languages =$stmt->fetchAll();

        // Cast game_count to integer
        foreach ($languages as &$item) {
            $item['ID'] = (int)$item['ID'];
            $item['game_count'] = (int)$item['game_count'];
        }

        json_response($languages);
    }

    // -------------------------------------------------------------------------
    // POST: Create or Update a Language
    // -------------------------------------------------------------------------
    if ($method === 'POST') {$id = !empty($input['id']) ? (int)$input['id'] : null;
        $language = trim($input['language'] ?? '');

        if ($language === '') {
            json_error('Language name cannot be empty.', 422);
        }

        if ($id) {
            // Update Existing Record
            $check =$pdo->prepare("SELECT `ID` FROM `Languages` WHERE LOWER(`Language`) = LOWER(?) AND `ID` != ?");
            $check->execute([$language,$id]);
            if ($check->fetch()) {
                json_error("Another language named '{$language}' already exists.", 409);
            }

            $stmt =$pdo->prepare("UPDATE `Languages` SET `Language` = ? WHERE `ID` = ?");
            $stmt->execute([$language,$id]);

            json_response([
                'id' => $id,
                'language' => $language,
                'message' => "Language #{$id} updated."
            ]);
        } else {
            // Create New Record
            $check =$pdo->prepare("SELECT `ID` FROM `Languages` WHERE LOWER(`Language`) = LOWER(?)");
            $check->execute([$language]);
            if ($check->fetch()) {
                json_error("A language named '{$language}' already exists.", 409);
            }

            $stmt =$pdo->prepare("INSERT INTO `Languages` (`Language`) VALUES (?)");
            $stmt->execute([$language]);
            $newId = (int)$pdo->lastInsertId();

            json_response([
                'id' => $newId,
                'language' => $language,
                'message' => "Language created successfully."
            ], 201);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE: Remove a Language (Safeguarded by usage checks)
    // -------------------------------------------------------------------------
    if ($method === 'DELETE' || ($method === 'POST' && ($input['action'] ?? '') === 'delete')) {
        $id = !empty($input['id']) ? (int)$input['id'] : (int)($_GET['id'] ?? 0);

        if (!$id) {
            json_error('Valid Language ID is required for deletion.', 400);
        }

        // Check if any games are currently linked to this language
        $checkStmt =$pdo->prepare("SELECT COUNT(*) FROM `Games` WHERE `Language ID` = ?");
        $checkStmt->execute([$id]);
        $linkedGames = (int)$checkStmt->fetchColumn();

        if ($linkedGames > 0) {
            json_error(
                "Cannot delete: This language is assigned to {$linkedGames} game(s). Please reassign those games first.",
                409,
                ['linked_games' => $linkedGames]
            );
        }

        $stmt =$pdo->prepare("DELETE FROM `Languages` WHERE `ID` = ?");
        $stmt->execute([$id]);

        json_response([
            'id' => $id,
            'message' => "Language #{$id} deleted successfully."
        ]);
    }

    json_error('Method not allowed.', 405);

} catch (PDOException $e) {
    error_log('[Languages API Error] ' . $e->getMessage());
    json_error('Database operation failed: ' . $e->getMessage(), 500);
}