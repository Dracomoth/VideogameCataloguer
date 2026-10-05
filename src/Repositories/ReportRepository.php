<?php
/**
 * src/Repositories/ReportRepository.php
 * Repository providing datasets, telemetry metrics, and schema metadata for reporting.
 */

declare(strict_types=1);

namespace Vault\Repositories;

use Vault\Services\Database;
use InvalidArgumentException;
use PDO;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class ReportRepository
{
    /**
     * Whitelist of tables permitted for custom reports.
     */
    public const ALLOWED_TABLES = [
        'games'         => ['label' => 'Games (Catalog Library)', 'icon' => '🎮'],
        'consoles'      => ['label' => 'Consoles (Hardware Platforms)', 'icon' => '🕹️'],
        'publishers'    => ['label' => 'Publishers & Hardware Manufacturers', 'icon' => '🏢'],
        'console_types' => ['label' => 'Console Types (Platform Categories)', 'icon' => '🏷️'],
        'categories'    => ['label' => 'Categories (Game Genres)', 'icon' => '📂'],
        'subcategories' => ['label' => 'Subcategories (Game Subgenres)', 'icon' => '📑'],
        'languages'     => ['label' => 'Languages & Regional Localizations', 'icon' => '🌐'],
        'users'         => ['label' => 'User Accounts', 'icon' => '👥'],
        'roles'         => ['label' => 'Roles & Permissions', 'icon' => '🛡️'],
    ];

    /**
     * Friendly column labels dictionary for cleaner reports and builder displays.
     */
    public const COLUMN_LABELS = [
        'id'                    => 'Record ID',
        'title'                 => 'Game Title',
        'name'                  => 'Name',
        'console_id'            => 'Console ID',
        'category_id'           => 'Category ID',
        'subcategory_id'        => 'Subcategory ID',
        'language_id'           => 'Language ID',
        'publisher_id'          => 'Publisher ID',
        'year'                  => 'Release Year',
        'generation'            => 'Generation',
        'console_type_id'       => 'Console Type ID',
        'tags'                  => 'Tags',
        'screenshot_path'       => 'Screenshot Path',
        'boxart_path'           => 'BoxArt Path',
        'image_path'            => 'Hardware Image Path',
        'logo_path'             => 'Logo Path',
        'in_collection'         => 'In Collection (Owned)',
        'is_console_maker'      => 'Is Console Maker',
        'is_for_reference'      => 'Is Reference Only',
        'master_reference_id'   => 'Master Platform ID',
        'badge_bg_color'        => 'Badge Background Color',
        'badge_font_color'      => 'Badge Font Color',
        'emulator'              => 'PC Emulator',
        'emulator_link'         => 'PC Emulator URL',
        'emulator_android'      => 'Android Emulator',
        'emulator_android_link' => 'Android Emulator URL',
        'retroarch_core'        => 'RetroArch Core',
        'core_link'             => 'Core Link',
        'comments'              => 'Comments & Notes',
        'first_name'            => 'First Name',
        'last_name'             => 'Last Name',
        'email'                 => 'Email Address',
        'role_id'               => 'Role ID',
        'is_active'             => 'Account Active',
        'last_login_at'         => 'Last Login Timestamp',
        'avatar_path'           => 'Avatar Path',
        'description'           => 'Description',
        'is_super'              => 'Super Admin Flag',
        'created_at'            => 'Created At',
        'updated_at'            => 'Updated At',
    ];

    /**
     * Returns real-time counts for curated collection audits.
     *
     * @return array{physical_inventory: int, catalog_health: int, incomplete_media: int}
     */
    public function getPremadeStats(): array
    {
        $owned = Database::fetchOne("SELECT COUNT(*) AS c FROM `games` WHERE `in_collection` = 1");
        $healthIssues = Database::fetchOne(
            "SELECT COUNT(*) AS c FROM `games` g
             WHERE (g.title IS NULL OR TRIM(g.title) = '')
                OR (g.year IS NULL OR TRIM(g.year) = '')
                OR g.console_id IS NULL OR g.console_id <= 0
                OR g.category_id IS NULL OR g.category_id <= 0
                OR g.subcategory_id IS NULL OR g.subcategory_id <= 0
                OR g.publisher_id IS NULL OR g.publisher_id <= 0
                OR g.language_id IS NULL OR g.language_id <= 0
                OR g.boxart_path IS NULL OR TRIM(g.boxart_path) = ''
                OR g.screenshot_path IS NULL OR TRIM(g.screenshot_path) = ''
                OR g.tags IS NULL OR TRIM(g.tags) = ''
                OR g.comments IS NULL OR TRIM(g.comments) = ''
                OR (g.in_collection = 1 AND NOT EXISTS (SELECT 1 FROM downloadable_files df WHERE df.game_id = g.id))"
        );

        $healthCount = (int)($healthIssues['c'] ?? 0);

        return [
            'physical_inventory' => (int)($owned['c'] ?? 0),
            'catalog_health'     => $healthCount,
            'incomplete_media'   => $healthCount,
        ];
    }

    /**
     * Fetches dataset for a specific curated premade audit.
     *
     * @param string $report
     * @return array{title: string, filename: string, headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    public function getPremadeReport(string $report): array
    {
        $report = strtolower(trim($report));

        switch ($report) {
            case 'physical_inventory':
                $sql = "SELECT 
                            g.id,
                            g.title,
                            COALESCE(c.name, '') AS console,
                            COALESCE(cat.name, '') AS category,
                            COALESCE(sub.name, '') AS subcategory,
                            COALESCE(pub.name, '') AS publisher,
                            COALESCE(g.year, '') AS year,
                            COALESCE(g.tags, '') AS tags,
                            g.in_collection,
                            COALESCE(g.comments, '') AS comments
                        FROM `games` g
                        LEFT JOIN `consoles` c ON c.id = g.console_id
                        LEFT JOIN `categories` cat ON cat.id = g.category_id
                        LEFT JOIN `subcategories` sub ON sub.id = g.subcategory_id
                        LEFT JOIN `publishers` pub ON pub.id = g.publisher_id
                        WHERE g.in_collection = 1
                        ORDER BY g.title ASC";

                $data = Database::fetchAll($sql);
                $headers = ['id', 'title', 'console', 'category', 'subcategory', 'publisher', 'year', 'tags', 'in_collection', 'comments'];
                $rows = [];
                foreach ($data as $d) {
                    $row = [];
                    foreach ($headers as $h) {
                        $row[] = $d[$h] ?? '';
                    }
                    $rows[] = $row;
                }

                return [
                    'title'    => 'Physical Inventory Audit',
                    'filename' => 'physical_inventory_audit_' . date('Ymd_His'),
                    'headers'  => $headers,
                    'rows'     => $rows,
                ];

            case 'catalog_health':
            case 'incomplete_media':
                $sql = "SELECT 
                            g.id,
                            g.title,
                            g.year,
                            g.console_id,
                            g.publisher_id,
                            g.category_id,
                            g.subcategory_id,
                            g.language_id,
                            g.boxart_path,
                            g.screenshot_path,
                            g.tags,
                            g.comments,
                            g.in_collection,
                            (SELECT COUNT(*) FROM downloadable_files df WHERE df.game_id = g.id) AS downloads_count
                        FROM `games` g
                        WHERE (g.title IS NULL OR TRIM(g.title) = '')
                           OR (g.year IS NULL OR TRIM(g.year) = '')
                           OR g.console_id IS NULL OR g.console_id <= 0
                           OR g.category_id IS NULL OR g.category_id <= 0
                           OR g.subcategory_id IS NULL OR g.subcategory_id <= 0
                           OR g.publisher_id IS NULL OR g.publisher_id <= 0
                           OR g.language_id IS NULL OR g.language_id <= 0
                           OR g.boxart_path IS NULL OR TRIM(g.boxart_path) = ''
                           OR g.screenshot_path IS NULL OR TRIM(g.screenshot_path) = ''
                           OR g.tags IS NULL OR TRIM(g.tags) = ''
                           OR g.comments IS NULL OR TRIM(g.comments) = ''
                           OR (g.in_collection = 1 AND NOT EXISTS (SELECT 1 FROM downloadable_files df WHERE df.game_id = g.id))
                        ORDER BY g.title ASC";

                $data = Database::fetchAll($sql);
                $headers = ['id', 'title', 'missing'];
                $rows = [];

                foreach ($data as $d) {
                    $missing = [];

                    if ($d['title'] === null || trim((string)$d['title']) === '') {
                        $missing[] = 'Title';
                    }
                    if ($d['year'] === null || trim((string)$d['year']) === '') {
                        $missing[] = 'Release Year';
                    }
                    if (empty($d['console_id']) || (int)$d['console_id'] <= 0) {
                        $missing[] = 'Console';
                    }
                    if (empty($d['publisher_id']) || (int)$d['publisher_id'] <= 0) {
                        $missing[] = 'Publisher';
                    }
                    if (empty($d['category_id']) || (int)$d['category_id'] <= 0) {
                        $missing[] = 'Category';
                    }
                    if (empty($d['subcategory_id']) || (int)$d['subcategory_id'] <= 0) {
                        $missing[] = 'Subcategory';
                    }
                    if (empty($d['language_id']) || (int)$d['language_id'] <= 0) {
                        $missing[] = 'Language';
                    }
                    if ((int)($d['in_collection'] ?? 0) === 1 && (int)($d['downloads_count'] ?? 0) <= 0) {
                        $missing[] = 'Download File';
                    }
                    if ($d['boxart_path'] === null || trim((string)$d['boxart_path']) === '') {
                        $missing[] = 'BoxArt';
                    }
                    if ($d['screenshot_path'] === null || trim((string)$d['screenshot_path']) === '') {
                        $missing[] = 'Screenshot';
                    }
                    if ($d['tags'] === null || trim((string)$d['tags']) === '') {
                        $missing[] = 'Tags';
                    }
                    if ($d['comments'] === null || trim((string)$d['comments']) === '') {
                        $missing[] = 'Comments';
                    }

                    $rows[] = [
                        (int)$d['id'],
                        (string)($d['title'] ?? ''),
                        implode(', ', $missing),
                    ];
                }

                return [
                    'title'    => 'Catalog Health Report',
                    'filename' => 'catalog_health_report_' . date('Ymd_His'),
                    'headers'  => $headers,
                    'rows'     => $rows,
                ];

            case 'cleared_beaten':
                return [
                    'title'    => 'Cleared & Beaten Logbook',
                    'filename' => 'cleared_beaten_logbook_' . date('Ymd_His'),
                    'headers'  => ['id', 'title', 'comments'],
                    'rows'     => [],
                ];

            default:
                throw new InvalidArgumentException("Unknown premade report: '{$report}'");
        }
    }

    /**
     * Returns schema and column metadata for all allowed tables to power the custom report builder.
     *
     * @return array<string, array{table: string, label: string, icon: string, total_records: int, columns: array<int, array<string, mixed>>}>
     */
    public function getTableCatalog(): array
    {
        $catalog = [];

        foreach (self::ALLOWED_TABLES as $table => $meta) {
            $rawCols = Database::fetchAll("SHOW COLUMNS FROM `{$table}`");
            $countRow = Database::fetchOne("SELECT COUNT(*) AS c FROM `{$table}`");
            $totalRecords = (int)($countRow['c'] ?? 0);

            $columns = [];
            foreach ($rawCols as $col) {
                $fieldName = (string)$col['Field'];
                $columns[] = [
                    'field'     => $fieldName,
                    'label'     => self::COLUMN_LABELS[$fieldName] ?? ucwords(str_replace('_', ' ', $fieldName)),
                    'type'      => (string)$col['Type'],
                    'is_null'   => $col['Null'] === 'YES',
                    'is_pk'     => $col['Key'] === 'PRI',
                    'default'   => $col['Default'],
                ];
            }

            $catalog[$table] = [
                'table'         => $table,
                'label'         => $meta['label'],
                'icon'          => $meta['icon'],
                'total_records' => $totalRecords,
                'columns'       => $columns,
            ];
        }

        return $catalog;
    }

    /**
     * Generates custom report dataset for a given table and an ordered array of columns.
     *
     * @param string $table
     * @param array<int, string> $selectedColumns
     * @return array{table: string, filename: string, headers: array<int, string>, rows: array<int, array<int, mixed>>, total_records: int}
     */
    public function getCustomReport(string $table, array $selectedColumns): array
    {
        $table = strtolower(trim($table));
        if (!isset(self::ALLOWED_TABLES[$table])) {
            throw new InvalidArgumentException("Table '{$table}' is not supported for reporting.");
        }

        // Fetch valid existing columns in database for security validation
        $rawCols = Database::fetchAll("SHOW COLUMNS FROM `{$table}`");
        $existingCols = array_fill_keys(array_map('strtolower', array_column($rawCols, 'Field')), true);
        $realColMap = [];
        foreach ($rawCols as $c) {
            $realColMap[strtolower((string)$c['Field'])] = (string)$c['Field'];
        }

        // Filter and sanitize selected columns preserving user order
        $sanitizedCols = [];
        foreach ($selectedColumns as $col) {
            $cLower = strtolower(trim((string)$col));
            if (isset($existingCols[$cLower])) {
                $sanitizedCols[] = $realColMap[$cLower];
            }
        }

        if (empty($sanitizedCols)) {
            throw new InvalidArgumentException("Please select at least one valid database column for the report.");
        }

        // Build safe SQL statement
        $escapedCols = array_map(fn($c) => "`{$c}`", $sanitizedCols);
        $sql = "SELECT " . implode(', ', $escapedCols) . " FROM `{$table}`";

        // Optional deterministic sorting
        if (in_array('id', $sanitizedCols, true)) {
            $sql .= " ORDER BY `id` ASC";
        } elseif (in_array('title', $sanitizedCols, true)) {
            $sql .= " ORDER BY `title` ASC";
        } elseif (in_array('name', $sanitizedCols, true)) {
            $sql .= " ORDER BY `name` ASC";
        }

        $data = Database::fetchAll($sql);
        $rows = [];

        foreach ($data as $record) {
            $row = [];
            foreach ($sanitizedCols as $col) {
                $val = $record[$col] ?? '';
                $row[] = $val;
            }
            $rows[] = $row;
        }

        return [
            'table'         => $table,
            'filename'      => 'custom_' . $table . '_report_' . date('Ymd_His'),
            'headers'       => $sanitizedCols,
            'rows'          => $rows,
            'total_records' => count($rows),
        ];
    }
}
