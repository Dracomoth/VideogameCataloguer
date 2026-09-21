<?php
/**
 * api/collection.php - Backend RESTful Endpoint for Player Hub
 */
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$method =$_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $mode =$_GET['mode'] ?? 'list';

        // ---------------------------------------------------------------------
        // 1. Bootstrap Mode
        // ---------------------------------------------------------------------
        if ($mode === 'bootstrap') {
            $consoles =$pdo->query("SELECT `ID`, `Console`, `IsHandheld` FROM `Consoles` ORDER BY `Console` ASC")->fetchAll();
            $categories =$pdo->query("SELECT `ID`, `Category` FROM `Categories` ORDER BY `Category` ASC")->fetchAll();

            $subCols =$pdo->query("SHOW COLUMNS FROM `Subcategories`")->fetchAll(PDO::FETCH_COLUMN);
            $subCatFk = in_array('Category ID',$subCols, true) ? '`Category ID`' : '`CategoryID`';
            $subNameCol = in_array('Subcategory',$subCols, true) ? '`Subcategory`' : '`Name`';

            $subcategories =$pdo->query("SELECT `ID`, {$subNameCol} AS subcategory, {$subCatFk} AS category_id FROM `Subcategories` ORDER BY subcategory ASC")->fetchAll();

            foreach ($consoles as &$c) {
                $c['ID'] = (int)$c['ID'];
                $c['IsHandheld'] = !empty($c['IsHandheld']) ? 1 : 0;
            }
            foreach ($categories as &$cat) {
                $cat['ID'] = (int)$cat['ID'];
            }
            foreach ($subcategories as &$sub) {
                $sub['ID'] = (int)$sub['ID'];
                $sub['category_id'] = (int)$sub['category_id'];
            }

            json_response([
                'consoles'      => $consoles,
                'categories'    => $categories,
                'subcategories' => $subcategories
            ]);
        }

        // ---------------------------------------------------------------------
        // 2. Query / List & Random Modes
        // ---------------------------------------------------------------------
        $search        = trim($_GET['q'] ?? '');$status        = $_GET['status'] ?? 'all';$consoleId     = !empty($_GET['console_id']) ? (int)$_GET['console_id'] : null;
        $categoryId    = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $subcategoryId = !empty($_GET['subcategory_id']) ? (int)$_GET['subcategory_id'] : null;
        $sortBy        =$_GET['sort'] ?? 'name_asc';

        $where  = [];$params = [];

        if ($search !== '') {$where[]  = "(g.`Game` LIKE ? OR g.`Comments` LIKE ? OR g.`Tags` LIKE ?)";
            $term     = "\%{$search}%";
            $params[] =$term;
            $params[] =$term;
            $params[] =$term;
        }

        if ($consoleId) {$where[]  = "g.`Console ID` = ?";
            $params[] =$consoleId;
        }

        if ($categoryId) {$where[]  = "g.`Category ID` = ?";
            $params[] =$categoryId;
        }

        if ($subcategoryId) {$where[]  = "g.`Subcategory ID` = ?";
            $params[] =$subcategoryId;
        }

        switch ($status) {
            case 'backlog':
                $where[] = "g.`InCollection` = 1 AND g.`Played` = 0 AND g.`Won` = 0";
                break;
            case 'playing':
                $where[] = "g.`InCollection` = 1 AND g.`Played` = 1 AND g.`Won` = 0";
                break;
            case 'won':
                $where[] = "g.`Won` = 1";
                break;
            case 'all':
            default:
                break;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Random mode picks 1 game matching the active filters
        if ($mode === 'random') {$sql = "SELECT g.`ID`, g.`Game`, g.`Year`, g.`BoxArt`, g.`Image`,
                           g.`InCollection`, g.`Played`, g.`Won`, g.`Tags`, g.`Comments`,
                           c.`Console` AS console_name, c.`Image` AS console_image,
                           c.`Emulator`, c.`RetroArchCore`,
                           cat.`Category` AS category_name,
                           sub.`Subcategory` AS subcategory_name,
                           pub.`Publisher` AS publisher_name,
                           lang.`Language` AS language_name
                    FROM `Games` g
                    LEFT JOIN `Consoles` c ON g.`Console ID` = c.`ID`
                    LEFT JOIN `Categories` cat ON g.`Category ID` = cat.`ID`
                    LEFT JOIN `Subcategories` sub ON g.`Subcategory ID` = sub.`ID`
                    LEFT JOIN `Publishers` pub ON g.`Publisher ID` = pub.`ID`
                    LEFT JOIN `Languages` lang ON g.`Language ID` = lang.`ID`
                    {$whereClause}
                    ORDER BY RAND()
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $picked =$stmt->fetch();

            if (!$picked) {
                json_response(['game' => null]);
            }

            $picked['ID']           = (int)$picked['ID'];
            $picked['InCollection'] = !empty($picked['InCollection']) ? 1 : 0;
            $picked['Played']       = !empty($picked['Played']) ? 1 : 0;
            $picked['Won']          = !empty($picked['Won']) ? 1 : 0;

            json_response(['game' => $picked]);
        }

        // Standard List Mode
        $orderClause = match ($sortBy) {
            'name_desc' => 'ORDER BY g.`Game` DESC',
            'year_desc' => 'ORDER BY g.`Year` DESC, g.`Game` ASC',
            'year_asc'  => 'ORDER BY g.`Year` ASC, g.`Game` ASC',
            'id_desc'   => 'ORDER BY g.`ID` DESC',
            default     => 'ORDER BY g.`Game` ASC',
        };

        $sql = "SELECT g.`ID`, g.`Game`, g.`Year`, g.`BoxArt`, g.`Image`,
                       g.`InCollection`, g.`Played`, g.`Won`,
                       c.`Console` AS console_name,
                       cat.`Category` AS category_name,
                       sub.`Subcategory` AS subcategory_name
                FROM `Games` g
                LEFT JOIN `Consoles` c ON g.`Console ID` = c.`ID`
                LEFT JOIN `Categories` cat ON g.`Category ID` = cat.`ID`
                LEFT JOIN `Subcategories` sub ON g.`Subcategory ID` = sub.`ID`
                {$whereClause}
                {$orderClause}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $games =$stmt->fetchAll();

        foreach ($games as &$row) {
            $row['ID']           = (int)$row['ID'];
            $row['InCollection'] = !empty($row['InCollection']) ? 1 : 0;
            $row['Played']       = !empty($row['Played']) ? 1 : 0;
            $row['Won']          = !empty($row['Won']) ? 1 : 0;
        }

        json_response([
            'count' => count($games),
            'games' => $games
        ]);
    }

    json_error('Method not allowed.', 405);
} catch (Exception $e) {
    error_log('[Player Hub API Error] ' . $e->getMessage());
    json_error('Database operation failed: ' . $e->getMessage(), 500);
}