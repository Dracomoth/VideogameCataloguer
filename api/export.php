<?php
/**
 * api/reports.php - RESTful Telemetry & Export Engine
 */
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$action =$_GET['action'] ?? 'summary';

try {
    // -------------------------------------------------------------
    // 1. Asynchronous Telemetry Summary (Zero DB queries in HTML)
    // -------------------------------------------------------------
    if ($action === 'summary') {$counts = [
            'games'        => (int)$pdo->query("SELECT COUNT(*) FROM `Games`")->fetchColumn(),
            'consoles'     => (int)$pdo->query("SELECT COUNT(*) FROM `Consoles`")->fetchColumn(),
            'publishers'   => (int)$pdo->query("SELECT COUNT(*) FROM `Publishers`")->fetchColumn(),
            'categories'   => (int)$pdo->query("SELECT COUNT(*) FROM `Categories`")->fetchColumn(),
            'languages'    => (int)$pdo->query("SELECT COUNT(*) FROM `Languages`")->fetchColumn(),
            'inventory'    => (int)$pdo->query("SELECT COUNT(*) FROM `Games` WHERE `InCollection` = 1")->fetchColumn(),
            'won'          => (int)$pdo->query("SELECT COUNT(*) FROM `Games` WHERE `Won` = 1")->fetchColumn(),
            'missing_art'  => (int)$pdo->query("SELECT COUNT(*) FROM `Games` WHERE (`BoxArt` IS NULL OR `BoxArt` = '') OR (`Image` IS NULL OR `Image` = '')")->fetchColumn(),
        ];

        echo json_encode(['success' => true, 'data' => $counts]);
        exit;
    }

    // -------------------------------------------------------------
    // 2. Data Stream Exporters (.csv, .xlsx, .txt)
    // -------------------------------------------------------------
    if ($action === 'export') {
        $target =$_GET['target'] ?? 'games';
        $format = strtolower($_GET['format'] ?? 'csv');
        $filename = 'vault_' . preg_replace('/[^a-z0-9_]/i', '', $target) . '_' . date('Y-m-d');

        switch ($target) {
            case 'consoles':
                $sql = "SELECT c.`ID`, c.`Console`, p.`Publisher` AS `Maker`, c.`Year`, c.`Generation`,
                               IF(c.`IsHandheld`=1,'Yes','No') AS `Portable`,
                               IF(c.`IsComputer`=1,'Yes','No') AS `Computer`,
                               IF(c.`IsArcade`=1,'Yes','No') AS `Arcade`,
                               c.`Emulator`, c.`RetroArchCore`
                        FROM `Consoles` c
                        LEFT JOIN `Publishers` p ON c.`Publisher ID` = p.`ID`
                        ORDER BY c.`Console` ASC";
                break;

            case 'publishers':
                $sql = "SELECT p.`ID`, p.`Publisher`,
                               IF(p.`Console Maker`=1,'Yes','No') AS `Makes Consoles`,
                               COUNT(g.`ID`) AS `Total Games`
                        FROM `Publishers` p
                        LEFT JOIN `Games` g ON g.`Publisher ID` = p.`ID`
                        GROUP BY p.`ID`, p.`Publisher`, p.`Console Maker`
                        ORDER BY p.`Publisher` ASC";
                break;

            case 'categories':
                $sql = "SELECT cat.`Category`, sub.`Subcategory`, COUNT(g.`ID`) AS `Total Games`
                        FROM `Categories` cat
                        LEFT JOIN `Subcategories` sub ON sub.`Category ID` = cat.`ID`
                        LEFT JOIN `Games` g ON g.`Subcategory ID` = sub.`ID`
                        GROUP BY cat.`ID`, sub.`ID`
                        ORDER BY cat.`Category` ASC, sub.`Subcategory` ASC";
                break;

            case 'languages':
                $sql = "SELECT l.`ID`, l.`Language`, COUNT(g.`ID`) AS `Total Games`
                        FROM `Languages` l
                        LEFT JOIN `Games` g ON g.`Language ID` = l.`ID`
                        GROUP BY l.`ID`, l.`Language`
                        ORDER BY l.`Language` ASC";
                break;

            case 'missing_art':
                $sql = "SELECT g.`ID`, g.`Game`, c.`Console`,
                               IF(g.`BoxArt` IS NULL OR g.`BoxArt`='', 'Missing', 'Present') AS `BoxArt`,
                               IF(g.`Image` IS NULL OR g.`Image`='', 'Missing', 'Present') AS `Screenshot`
                        FROM `Games` g
                        LEFT JOIN `Consoles` c ON g.`Console ID` = c.`ID`
                        WHERE (g.`BoxArt` IS NULL OR g.`BoxArt` = '')
                           OR (g.`Image` IS NULL OR g.`Image` = '')
                        ORDER BY c.`Console` ASC, g.`Game` ASC";
                break;

            case 'won':
                $sql = "SELECT g.`ID`, g.`Game`, c.`Console`, g.`Year`, pub.`Publisher`, g.`Comments`
                        FROM `Games` g
                        LEFT JOIN `Consoles` c ON g.`Console ID` = c.`ID`
                        LEFT JOIN `Publishers` pub ON g.`Publisher ID` = pub.`ID`
                        WHERE g.`Won` = 1
                        ORDER BY g.`Game` ASC";
                break;

            case 'inventory':
                $sql = "SELECT g.`ID`, g.`Game`, c.`Console`, g.`Year`, pub.`Publisher`,
                               IF(g.`Played`=1,'Yes','No') AS `Played`,
                               IF(g.`Won`=1,'Yes','No') AS `Won`,
                               g.`Comments`
                        FROM `Games` g
                        LEFT JOIN `Consoles` c ON g.`Console ID` = c.`ID`
                        LEFT JOIN `Publishers` pub ON g.`Publisher ID` = pub.`ID`
                        WHERE g.`InCollection` = 1
                        ORDER BY c.`Console` ASC, g.`Game` ASC";
                break;

            case 'games':
            default:
                $sql = "SELECT g.`ID`, g.`Game`, c.`Console`, cat.`Category`, sub.`Subcategory`,
                               pub.`Publisher`, lang.`Language`, g.`Year`,
                               IF(g.`InCollection`=1,'Yes','No') AS `In Collection`,
                               IF(g.`Played`=1,'Yes','No') AS `Played`,
                               IF(g.`Won`=1,'Yes','No') AS `Won`,
                               g.`Tags`, g.`Comments`
                        FROM `Games` g
                        LEFT JOIN `Consoles` c ON g.`Console ID` = c.`ID`
                        LEFT JOIN `Categories` cat ON g.`Category ID` = cat.`ID`
                        LEFT JOIN `Subcategories` sub ON g.`Subcategory ID` = sub.`ID`
                        LEFT JOIN `Publishers` pub ON g.`Publisher ID` = pub.`ID`
                        LEFT JOIN `Languages` lang ON g.`Language ID` = lang.`ID`
                        ORDER BY g.`Game` ASC";
                break;
        }

        $stmt =$pdo->query($sql);$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);$columns = !empty($rows) ? array_keys($rows[0]) : ['Message'];
        if (empty($rows)) {$rows = [['Message' => 'No records found.']];
        }

        // Output CSV
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out,$columns);
            foreach ($rows as$row) {
                fputcsv($out,$row);
            }
            fclose($out);
            exit;
        }

        // Output Plain Text Table
        if ($format === 'txt') {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.txt"');

            // Print header row
            echo implode("\t", $columns) . "\r\n";

            // Print data rows
            foreach ($rows as $r) {
                $line = [];
                foreach ($columns as $col) {
                    $val = (string)($r[$col] ?? '');
                    // Sanitize tabs and newlines inside data fields to prevent line break corruption
                    $val = str_replace(["\t", "\r", "\n"], [' ', ' ', ' '], $val);
                    $line[] = $val;
                }
                echo implode("\t", $line) . "\r\n";
            }
            exit;
        }

        // Output XML Spreadsheet (Excel/XLSX compatible)
        if ($format === 'xlsx') {
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
            echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            echo "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\">\n";
            echo " <Styles><Style ss:ID=\"hdr\"><Font ss:Bold=\"1\" ss:Color=\"#FFFFFF\"/><Interior ss:Color=\"#151D30\" ss:Pattern=\"Solid\"/></Style></Styles>\n";
            echo " <Worksheet ss:Name=\"Export\"><Table>\n<Row>\n";
            foreach ($columns as$col) {
                echo "  <Cell ss:StyleID=\"hdr\"><Data ss:Type=\"String\">" . htmlspecialchars($col) . "</Data></Cell>\n";
            }
            echo " </Row>\n";
            foreach ($rows as$r) {
                echo " <Row>\n";
                foreach ($columns as$col) {
                    $val = (string)($r[$col] ?? '');$type = is_numeric($val) && strlen($val) < 10 ? 'Number' : 'String';
                    echo "  <Cell><Data ss:Type=\"$type\">" . htmlspecialchars($val) . "</Data></Cell>\n";
                }
                echo " </Row>\n";
            }
            echo " </Table></Worksheet></Workbook>";
            exit;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}