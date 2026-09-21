<?php
/**
 * api/publishers.php - Backend RESTful Endpoint for Publishers
 */
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$method   = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$input    = json_decode($rawInput, true) ?? $_POST;

// Detect Console Maker column name variation (`Console Maker` vs `ConsoleMaker`)
$pubCols = $pdo->query("SHOW COLUMNS FROM `Publishers`")->fetchAll(PDO::FETCH_COLUMN);
$cmCol = in_array('Console Maker', $pubCols, true) 
    ? 'Console Maker' 
    : (in_array('ConsoleMaker', $pubCols, true) ? 'ConsoleMaker' : 'Console Maker');
$escapedCmCol = "`" . str_replace("`", "``", $cmCol) . "`";

try {
    // -------------------------------------------------------------------------
    // GET: List all publishers with game & console counts + hardware list
    // -------------------------------------------------------------------------
    if ($method === 'GET') {$search = trim($_GET['q'] ?? '');$params = [];

        $sql = "SELECT p.`ID`, p.`Publisher`, p.{$escapedCmCol} AS is_console_maker,
                       COUNT(DISTINCT g.`ID`) AS game_count,
                       COUNT(DISTINCT c.`ID`) AS console_count,
                       GROUP_CONCAT(DISTINCT c.`Console` ORDER BY c.`Console` ASC SEPARATOR '||') AS consoles_joined
                FROM `Publishers` p
                LEFT JOIN `Games` g ON g.`Publisher ID` = p.`ID`
                LEFT JOIN `Consoles` c ON c.`Publisher ID` = p.`ID`";

        if ($search !== '') {$sql .= " WHERE p.`Publisher` LIKE ?";
            $params[] = "\%{$search}%";
        }

        $sql .= " GROUP BY p.`ID`, p.`Publisher`, p.{$escapedCmCol} ORDER BY p.`ID` ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $publishers =$stmt->fetchAll();

        foreach ($publishers as &$item) {
            $item['ID']               = (int)$item['ID'];
            $item['is_console_maker'] = !empty($item['is_console_maker']) ? 1 : 0;
            $item['game_count']       = (int)$item['game_count'];
            $item['console_count']    = (int)$item['console_count'];
            $item['consoles']         = !empty($item['consoles_joined']) 
                ? explode('||', $item['consoles_joined']) 
                : [];
            unset($item['consoles_joined']);
        }

        json_response($publishers);
    }

    // -------------------------------------------------------------------------
    // POST: Create or Update a Publisher
    // -------------------------------------------------------------------------
    if ($method === 'POST') {$id             = !empty($input['id']) ? (int)$input['id'] : null;
        $publisher      = trim($input['publisher'] ?? '');
        $isConsoleMaker = !empty($input['console_maker']) ? 1 : 0;

        if ($publisher === '') {
            json_error('Publisher name cannot be empty.', 422);
        }

        if ($id) {
            // Check for duplicate names on other IDs
            $check =$pdo->prepare("SELECT `ID` FROM `Publishers` WHERE LOWER(`Publisher`) = LOWER(?) AND `ID` != ?");
            $check->execute([$publisher,$id]);
            if ($check->fetch()) {
                json_error("Another publisher named '{$publisher}' already exists.", 409);
            }

            $stmt =$pdo->prepare("UPDATE `Publishers` SET `Publisher` = ?, {$escapedCmCol} = ? WHERE `ID` = ?");
            $stmt->execute([$publisher, $isConsoleMaker,$id]);

            json_response([
                'id'            => $id,
                'publisher'     => $publisher,
                'console_maker' => $isConsoleMaker,
                'message'       => "Publisher #{$id} updated."
            ]);
        } else {
            // Check for duplicate name
            $check =$pdo->prepare("SELECT `ID` FROM `Publishers` WHERE LOWER(`Publisher`) = LOWER(?)");
            $check->execute([$publisher]);
            if ($check->fetch()) {
                json_error("A publisher named '{$publisher}' already exists.", 409);
            }

            $stmt =$pdo->prepare("INSERT INTO `Publishers` (`Publisher`, {$escapedCmCol}) VALUES (?, ?)");
            $stmt->execute([$publisher,$isConsoleMaker]);
            $newId = (int)$pdo->lastInsertId();

            json_response([
                'id'            => $newId,
                'publisher'     => $publisher,
                'console_maker' => $isConsoleMaker,
                'message'       => "Publisher created successfully."
            ], 201);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE: Safeguarded removal
    // -------------------------------------------------------------------------
    if ($method === 'DELETE' || ($method === 'POST' && ($input['action'] ?? '') === 'delete')) {
        $id = !empty($input['id']) ? (int)$input['id'] : (int)($_GET['id'] ?? 0);

        if (!$id) {
            json_error('Valid Publisher ID is required for deletion.', 400);
        }

        // 1. Check linked games
        $checkGames =$pdo->prepare("SELECT COUNT(*) FROM `Games` WHERE `Publisher ID` = ?");
        $checkGames->execute([$id]);
        $linkedGames = (int)$checkGames->fetchColumn();

        // 2. Check linked consoles
        $checkConsoles =$pdo->prepare("SELECT COUNT(*) FROM `Consoles` WHERE `Publisher ID` = ?");
        $checkConsoles->execute([$id]);
        $linkedConsoles = (int)$checkConsoles->fetchColumn();

        if ($linkedGames > 0 || $linkedConsoles > 0) {$reasons = [];
            if ($linkedGames > 0) $reasons[] = "{$linkedGames} game(s)";
            if ($linkedConsoles > 0) $reasons[] = "{$linkedConsoles} console(s)";

            json_error(
                "Cannot delete: Publisher is assigned to " . implode(' and ', $reasons) . ". Reassign them before deleting.",
                409,
                ['linked_games' => $linkedGames, 'linked_consoles' =>$linkedConsoles]
            );
        }

        $stmt =$pdo->prepare("DELETE FROM `Publishers` WHERE `ID` = ?");
        $stmt->execute([$id]);

        json_response([
            'id'      => $id,
            'message' => "Publisher #{$id} deleted successfully."
        ]);
    }

    json_error('Method not allowed.', 405);

} catch (PDOException $e) {
    error_log('[Publishers API Error] ' . $e->getMessage());
    json_error('Database operation failed: ' . $e->getMessage(), 500);
}