<?php
/**
 * src/Repositories/BulkUploadRepository.php
 * Repository and validator for batch structured data ingestion.
 */

declare(strict_types=1);

namespace Vault\Repositories;

use Vault\Services\Database;
use InvalidArgumentException;
use RuntimeException;
use PDO;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class BulkUploadRepository
{
    /**
     * Supported tables eligible for bulk ingestion.
     */
    public const ALLOWED_TABLES = [
        'games',
        'consoles',
        'publishers',
        'console_types',
        'categories',
        'subcategories',
        'languages',
    ];

    /**
     * Returns full schema catalog metadata for all supported tables.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSchemaCatalog(): array
    {
        $catalog = [];
        foreach (self::ALLOWED_TABLES as $table) {
            $catalog[$table] = $this->getTableSchema($table);
        }
        return $catalog;
    }

    /**
     * Returns schema configuration, column mapping, and constraints for a specific table.
     *
     * @param string $table
     * @return array<string, mixed>
     */
    public function getTableSchema(string $table): array
    {
        $table = strtolower(trim($table));
        if (!in_array($table, self::ALLOWED_TABLES, true)) {
            throw new InvalidArgumentException("Table '{$table}' is not supported for bulk ingestion.");
        }

        switch ($table) {
            case 'games':
                return [
                    'table' => 'games',
                    'label' => 'Games (Catalog Library)',
                    'icon'  => '🎮',
                    'primary_key' => 'id',
                    'recognized_headers' => 'title, console_id, category_id, subcategory_id, language_id, publisher_id, year, tags, screenshot_path, boxart_path, in_collection, is_played, is_won, comments',
                    'columns' => [
                        'title' => [
                            'name' => 'title',
                            'label' => 'Game Title',
                            'type' => 'string',
                            'required' => true,
                            'max_length' => 255,
                        ],
                        'console_id' => [
                            'name' => 'console_id',
                            'label' => 'Console ID',
                            'type' => 'integer',
                            'required' => false,
                            'foreign_key' => ['table' => 'consoles', 'column' => 'id'],
                        ],
                        'category_id' => [
                            'name' => 'category_id',
                            'label' => 'Category ID',
                            'type' => 'integer',
                            'required' => false,
                            'foreign_key' => ['table' => 'categories', 'column' => 'id'],
                        ],
                        'subcategory_id' => [
                            'name' => 'subcategory_id',
                            'label' => 'Subcategory ID',
                            'type' => 'integer',
                            'required' => false,
                            'foreign_key' => ['table' => 'subcategories', 'column' => 'id'],
                        ],
                        'language_id' => [
                            'name' => 'language_id',
                            'label' => 'Language ID',
                            'type' => 'integer',
                            'required' => false,
                            'foreign_key' => ['table' => 'languages', 'column' => 'id'],
                        ],
                        'publisher_id' => [
                            'name' => 'publisher_id',
                            'label' => 'Publisher ID',
                            'type' => 'integer',
                            'required' => false,
                            'foreign_key' => ['table' => 'publishers', 'column' => 'id'],
                        ],
                        'year' => [
                            'name' => 'year',
                            'label' => 'Release Year',
                            'type' => 'string',
                            'required' => false,
                            'max_length' => 255,
                        ],
                        'tags' => [
                            'name' => 'tags',
                            'label' => 'Tags',
                            'type' => 'text',
                            'required' => false,
                        ],
                        'screenshot_path' => [
                            'name' => 'screenshot_path',
                            'label' => 'Screenshot / Image Path',
                            'type' => 'string',
                            'required' => false,
                            'max_length' => 255,
                        ],
                        'boxart_path' => [
                            'name' => 'boxart_path',
                            'label' => 'BoxArt Path',
                            'type' => 'string',
                            'required' => false,
                            'max_length' => 255,
                        ],
                        'in_collection' => [
                            'name' => 'in_collection',
                            'label' => 'In Collection',
                            'type' => 'boolean',
                            'required' => false,
                            'default' => 0,
                        ],
                        'is_played' => [
                            'name' => 'is_played',
                            'label' => 'Played',
                            'type' => 'boolean',
                            'required' => false,
                            'default' => 0,
                        ],
                        'is_won' => [
                            'name' => 'is_won',
                            'label' => 'Won / Beaten',
                            'type' => 'boolean',
                            'required' => false,
                            'default' => 0,
                        ],
                        'comments' => [
                            'name' => 'comments',
                            'label' => 'Comments / Notes',
                            'type' => 'text',
                            'required' => false,
                        ],
                    ],
                ];

            case 'consoles':
                return [
                    'table' => 'consoles',
                    'label' => 'Consoles (Hardware Platforms)',
                    'icon'  => '🕹️',
                    'primary_key' => 'id',
                    'recognized_headers' => 'name, publisher_id, year, generation, console_type_id, image_path, logo_path, comments, emulator, emulator_link, emulator_android, emulator_android_link, retroarch_core, core_link, is_for_reference, master_reference_id',
                    'columns' => [
                        'name' => [
                            'name' => 'name',
                            'label' => 'Console Name',
                            'type' => 'string',
                            'required' => true,
                            'max_length' => 255,
                        ],
                        'publisher_id' => [
                            'name' => 'publisher_id',
                            'label' => 'Publisher / Manufacturer ID',
                            'type' => 'integer',
                            'required' => false,
                            'foreign_key' => ['table' => 'publishers', 'column' => 'id'],
                        ],
                        'year' => [
                            'name' => 'year',
                            'label' => 'Release Year',
                            'type' => 'string',
                            'required' => false,
                            'max_length' => 255,
                        ],
                        'generation' => [
                            'name' => 'generation',
                            'label' => 'Generation',
                            'type' => 'string',
                            'required' => false,
                            'max_length' => 255,
                        ],
                        'console_type_id' => [
                            'name' => 'console_type_id',
                            'label' => 'Console Type ID',
                            'type' => 'integer',
                            'required' => false,
                            'default' => 1,
                            'foreign_key' => ['table' => 'console_types', 'column' => 'id'],
                        ],
                        'image_path' => [
                            'name' => 'image_path',
                            'label' => 'Hardware Photo Path',
                            'type' => 'string',
                            'required' => false,
                            'max_length' => 255,
                        ],
                        'logo_path' => [
                            'name' => 'logo_path',
                            'label' => 'Vector/PNG Logo Path',
                            'type' => 'string',
                            'required' => false,
                            'max_length' => 255,
                        ],
                        'comments' => [
                            'name' => 'comments',
                            'label' => 'Comments',
                            'type' => 'text',
                            'required' => false,
                        ],
                        'emulator' => [
                            'name' => 'emulator',
                            'label' => 'PC Emulator Name',
                            'type' => 'string',
                            'required' => false,
                            'max_length' => 255,
                        ],
                        'emulator_link' => [
                            'name' => 'emulator_link',
                            'label' => 'PC Emulator URL',
                            'type' => 'text',
                            'required' => false,
                        ],
                        'emulator_android' => [
                            'name' => 'emulator_android',
                            'label' => 'Android Emulator Name',
                            'type' => 'string',
                            'required' => false,
                            'max_length' => 255,
                        ],
                        'emulator_android_link' => [
                            'name' => 'emulator_android_link',
                            'label' => 'Android Emulator URL',
                            'type' => 'text',
                            'required' => false,
                        ],
                        'retroarch_core' => [
                            'name' => 'retroarch_core',
                            'label' => 'RetroArch Core',
                            'type' => 'string',
                            'required' => false,
                            'max_length' => 255,
                        ],
                        'core_link' => [
                            'name' => 'core_link',
                            'label' => 'Core Documentation URL',
                            'type' => 'text',
                            'required' => false,
                        ],
                        'is_for_reference' => [
                            'name' => 'is_for_reference',
                            'label' => 'Is Reference Only',
                            'type' => 'boolean',
                            'required' => false,
                            'default' => 0,
                        ],
                        'master_reference_id' => [
                            'name' => 'master_reference_id',
                            'label' => 'Master Platform ID',
                            'type' => 'integer',
                            'required' => false,
                            'foreign_key' => ['table' => 'consoles', 'column' => 'id'],
                        ],
                    ],
                ];

            case 'publishers':
                return [
                    'table' => 'publishers',
                    'label' => 'Publishers & Hardware Manufacturers',
                    'icon'  => '🏢',
                    'primary_key' => 'id',
                    'recognized_headers' => 'name, is_console_maker',
                    'columns' => [
                        'name' => [
                            'name' => 'name',
                            'label' => 'Publisher / Company Name',
                            'type' => 'string',
                            'required' => true,
                            'max_length' => 255,
                        ],
                        'is_console_maker' => [
                            'name' => 'is_console_maker',
                            'label' => 'Is Console Maker',
                            'type' => 'boolean',
                            'required' => false,
                            'default' => 0,
                        ],
                    ],
                ];

            case 'console_types':
                return [
                    'table' => 'console_types',
                    'label' => 'Console Types (Platform Categories)',
                    'icon'  => '🏷️',
                    'primary_key' => 'id',
                    'recognized_headers' => 'name, badge_bg_color, badge_font_color',
                    'columns' => [
                        'name' => [
                            'name' => 'name',
                            'label' => 'Type Designation',
                            'type' => 'string',
                            'required' => true,
                            'max_length' => 100,
                            'unique' => true,
                        ],
                        'badge_bg_color' => [
                            'name' => 'badge_bg_color',
                            'label' => 'Badge Background Color',
                            'type' => 'color_hex',
                            'required' => false,
                            'default' => '#1e3a8a',
                        ],
                        'badge_font_color' => [
                            'name' => 'badge_font_color',
                            'label' => 'Badge Font Color',
                            'type' => 'color_hex',
                            'required' => false,
                            'default' => '#93c5fd',
                        ],
                    ],
                ];

            case 'categories':
                return [
                    'table' => 'categories',
                    'label' => 'Categories (Game Genres)',
                    'icon'  => '📂',
                    'primary_key' => 'id',
                    'recognized_headers' => 'name',
                    'columns' => [
                        'name' => [
                            'name' => 'name',
                            'label' => 'Genre / Category Name',
                            'type' => 'string',
                            'required' => true,
                            'max_length' => 255,
                        ],
                    ],
                ];

            case 'subcategories':
                return [
                    'table' => 'subcategories',
                    'label' => 'Subcategories (Game Subgenres)',
                    'icon'  => '📑',
                    'primary_key' => 'id',
                    'recognized_headers' => 'name, category_id',
                    'columns' => [
                        'name' => [
                            'name' => 'name',
                            'label' => 'Subcategory Name',
                            'type' => 'string',
                            'required' => true,
                            'max_length' => 255,
                        ],
                        'category_id' => [
                            'name' => 'category_id',
                            'label' => 'Parent Category ID',
                            'type' => 'integer',
                            'required' => true,
                            'foreign_key' => ['table' => 'categories', 'column' => 'id'],
                        ],
                    ],
                ];

            case 'languages':
                return [
                    'table' => 'languages',
                    'label' => 'Languages & Regional Localizations',
                    'icon'  => '🌐',
                    'primary_key' => 'id',
                    'recognized_headers' => 'name',
                    'columns' => [
                        'name' => [
                            'name' => 'name',
                            'label' => 'Language / Locale Name',
                            'type' => 'string',
                            'required' => true,
                            'max_length' => 255,
                        ],
                    ],
                ];
        }

        throw new RuntimeException("Unconfigured table schema for: {$table}");
    }

    /**
     * Resolves incoming file headers against table columns.
     * Primary key ID columns are skipped, unknown headers are ignored.
     *
     * @param array<string, mixed> $schema
     * @param array<int|string, string> $fileHeaders
     * @return array{mapped: array<string, string>, ignored: array<int, string>}
     */
    public function resolveHeaderMapping(array $schema, array $fileHeaders): array
    {
        $columns = $schema['columns'];
        $mapped = [];   // fileHeader => dbColumnName
        $ignored = [];  // unrecognized or primary key headers

        // Build case-insensitive lookup of exact database column names
        $dbColLookup = [];
        foreach ($columns as $colKey => $colDef) {
            $dbColLookup[strtolower((string)$colKey)] = (string)$colKey;
        }

        foreach ($fileHeaders as $header) {
            $rawHeader = trim((string)$header);
            if ($rawHeader === '') {
                continue;
            }

            $cleanLower = strtolower($rawHeader);

            // 1. Primary key ID columns are explicitly ignored
            if (in_array($cleanLower, ['id', '#', 'pk'], true)) {
                $ignored[] = $rawHeader . ' (Primary Key - Skipped)';
                continue;
            }

            // 2. Exact match against DB column names (case-insensitive)
            if (isset($dbColLookup[$cleanLower])) {
                $dbCol = $dbColLookup[$cleanLower];
                // Check if this DB column is already mapped
                if (!in_array($dbCol, $mapped, true)) {
                    $mapped[$rawHeader] = $dbCol;
                } else {
                    $ignored[] = $rawHeader . ' (Duplicate mapping for ' . $dbCol . ')';
                }
            } else {
                // Different name: treated as different name and thus ignored
                $ignored[] = $rawHeader;
            }
        }

        return [
            'mapped'  => $mapped,
            'ignored' => $ignored,
        ];
    }

    /**
     * Preloads lookup sets of foreign key IDs and unique constraints for high-performance validation.
     *
     * @param string $table
     * @return array<string, mixed>
     */
    private function loadValidationLookups(string $table): array
    {
        $lookups = [];

        switch ($table) {
            case 'games':
                $consoles = Database::fetchAll("SELECT id FROM consoles");
                $lookups['consoles'] = array_fill_keys(array_map('intval', array_column($consoles, 'id')), true);

                $categories = Database::fetchAll("SELECT id FROM categories");
                $lookups['categories'] = array_fill_keys(array_map('intval', array_column($categories, 'id')), true);

                $subcats = Database::fetchAll("SELECT id, category_id FROM subcategories");
                $subcatMap = [];
                foreach ($subcats as $s) {
                    $subcatMap[(int)$s['id']] = $s['category_id'] !== null ? (int)$s['category_id'] : null;
                }
                $lookups['subcategories'] = $subcatMap;

                $languages = Database::fetchAll("SELECT id FROM languages");
                $lookups['languages'] = array_fill_keys(array_map('intval', array_column($languages, 'id')), true);

                $publishers = Database::fetchAll("SELECT id FROM publishers");
                $lookups['publishers'] = array_fill_keys(array_map('intval', array_column($publishers, 'id')), true);
                break;

            case 'consoles':
                $publishers = Database::fetchAll("SELECT id FROM publishers");
                $lookups['publishers'] = array_fill_keys(array_map('intval', array_column($publishers, 'id')), true);

                $consoleTypes = Database::fetchAll("SELECT id FROM console_types");
                $lookups['console_types'] = array_fill_keys(array_map('intval', array_column($consoleTypes, 'id')), true);

                $consoles = Database::fetchAll("SELECT id FROM consoles");
                $lookups['consoles'] = array_fill_keys(array_map('intval', array_column($consoles, 'id')), true);
                break;

            case 'console_types':
                $existingTypes = Database::fetchAll("SELECT LOWER(name) AS lname FROM console_types");
                $lookups['existing_names'] = array_fill_keys(array_column($existingTypes, 'lname'), true);
                break;

            case 'subcategories':
                $categories = Database::fetchAll("SELECT id FROM categories");
                $lookups['categories'] = array_fill_keys(array_map('intval', array_column($categories, 'id')), true);
                break;
        }

        return $lookups;
    }

    /**
     * Validates a batch of rows against table schema and database constraints.
     *
     * @param string $table
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public function validateBatch(string $table, array $rows): array
    {
        $schema = $this->getTableSchema($table);
        $columns = $schema['columns'];

        if (empty($rows)) {
            return [
                'table'          => $table,
                'total_records'  => 0,
                'valid_count'    => 0,
                'invalid_count'  => 0,
                'mapped_columns' => [],
                'ignored_columns'=> [],
                'all_ok'         => true,
                'all_invalid'    => true,
                'records'        => [],
            ];
        }

        // Determine available headers from row keys
        $firstRow = reset($rows);
        $headers = array_keys($firstRow);
        $mappingResult = $this->resolveHeaderMapping($schema, $headers);
        $headerMap = $mappingResult['mapped']; // rawHeader => dbColumn
        $ignoredHeaders = $mappingResult['ignored'];

        if (empty($headerMap)) {
            throw new RuntimeException("None of the file headers match any valid column in table '{$table}'. Recognized columns: " . $schema['recognized_headers']);
        }

        $lookups = $this->loadValidationLookups($table);
        $validatedRecords = [];
        $validCount = 0;
        $invalidCount = 0;

        foreach ($rows as $rowIndex => $rawRow) {
            $rowNum = $rowIndex + 1;
            $rowErrors = [];
            $normalizedData = [];

            // Map values according to resolved header mapping
            foreach ($headerMap as $rawHeader => $dbCol) {
                $val = $rawRow[$rawHeader] ?? null;
                $colDef = $columns[$dbCol];
                $type = $colDef['type'] ?? 'string';

                // Normalize strings
                if (is_string($val)) {
                    $val = trim($val);
                    if ($val === '') {
                        $val = null;
                    }
                }

                // Type checks & normalization
                switch ($type) {
                    case 'boolean':
                        if ($val === null || $val === '') {
                            $normalizedData[$dbCol] = $colDef['default'] ?? 0;
                        } else {
                            $lower = is_string($val) ? strtolower($val) : $val;
                            if (in_array($lower, [1, '1', true, 'true', 'yes', 'y', 'on'], true)) {
                                $normalizedData[$dbCol] = 1;
                            } elseif (in_array($lower, [0, '0', false, 'false', 'no', 'n', 'off'], true)) {
                                $normalizedData[$dbCol] = 0;
                            } else {
                                $rowErrors[] = "Column '{$dbCol}' expects boolean (1/0/yes/no), received '{$val}'.";
                                $normalizedData[$dbCol] = 0;
                            }
                        }
                        break;

                    case 'integer':
                        if ($val === null || $val === '') {
                            $normalizedData[$dbCol] = null;
                        } elseif (is_numeric($val) && (int)$val == $val) {
                            $intVal = (int)$val;
                            $normalizedData[$dbCol] = $intVal;
                        } else {
                            $rowErrors[] = "Column '{$dbCol}' expects an integer ID, received '{$val}'.";
                            $normalizedData[$dbCol] = null;
                        }
                        break;

                    case 'color_hex':
                        if ($val === null || $val === '') {
                            $normalizedData[$dbCol] = $colDef['default'] ?? '#1e3a8a';
                        } else {
                            $cleanHex = strtolower((string)$val);
                            if (!str_starts_with($cleanHex, '#')) {
                                $cleanHex = '#' . $cleanHex;
                            }
                            if (preg_match('/^#[0-9a-f]{3}$/i', $cleanHex)) {
                                // Expand 3-digit hex to 6-digit
                                $cleanHex = '#' . $cleanHex[1] . $cleanHex[1] . $cleanHex[2] . $cleanHex[2] . $cleanHex[3] . $cleanHex[3];
                            }
                            if (preg_match('/^#[0-9a-f]{6}$/i', $cleanHex)) {
                                $normalizedData[$dbCol] = $cleanHex;
                            } else {
                                $rowErrors[] = "Column '{$dbCol}' must be a valid hex color code (e.g. #1e3a8a), received '{$val}'.";
                                $normalizedData[$dbCol] = $colDef['default'] ?? '#1e3a8a';
                            }
                        }
                        break;

                    case 'string':
                    case 'text':
                    default:
                        if ($val !== null && !empty($colDef['max_length']) && strlen((string)$val) > $colDef['max_length']) {
                            $rowErrors[] = "Column '{$dbCol}' exceeds max length of {$colDef['max_length']} characters.";
                        }
                        $normalizedData[$dbCol] = $val !== null ? (string)$val : null;
                        break;
                }
            }

            // Fill default values for omitted optional columns
            foreach ($columns as $colName => $colDef) {
                if (!array_key_exists($colName, $normalizedData)) {
                    if (array_key_exists('default', $colDef)) {
                        $normalizedData[$colName] = $colDef['default'];
                    }
                }
            }

            // Validate required fields
            foreach ($columns as $colName => $colDef) {
                if (!empty($colDef['required'])) {
                    $hasColInFile = in_array($colName, $headerMap, true);
                    if (!$hasColInFile) {
                        $rowErrors[] = "Missing mandatory column in file header: '{$colName}'.";
                    } elseif (!isset($normalizedData[$colName]) || $normalizedData[$colName] === null || $normalizedData[$colName] === '') {
                        $rowErrors[] = "Missing mandatory field value for '{$colName}'.";
                    }
                }
            }

            // Validate Foreign Keys
            if (isset($normalizedData['console_id']) && $normalizedData['console_id'] !== null) {
                $cid = (int)$normalizedData['console_id'];
                if (empty($lookups['consoles'][$cid])) {
                    $rowErrors[] = "Foreign key violation: console_id '{$cid}' does not exist in 'consoles' table.";
                }
            }

            if (isset($normalizedData['category_id']) && $normalizedData['category_id'] !== null) {
                $catId = (int)$normalizedData['category_id'];
                if (empty($lookups['categories'][$catId])) {
                    $rowErrors[] = "Foreign key violation: category_id '{$catId}' does not exist in 'categories' table.";
                }
            }

            if (isset($normalizedData['subcategory_id']) && $normalizedData['subcategory_id'] !== null) {
                $subId = (int)$normalizedData['subcategory_id'];
                if (!array_key_exists($subId, $lookups['subcategories'] ?? [])) {
                    $rowErrors[] = "Foreign key violation: subcategory_id '{$subId}' does not exist in 'subcategories' table.";
                } elseif (isset($normalizedData['category_id']) && $normalizedData['category_id'] !== null) {
                    $parentCat = $lookups['subcategories'][$subId] ?? null;
                    if ($parentCat !== null && $parentCat !== (int)$normalizedData['category_id']) {
                        $rowErrors[] = "Subcategory ID '{$subId}' does not belong to Category ID '{$normalizedData['category_id']}'.";
                    }
                }
            }

            if (isset($normalizedData['language_id']) && $normalizedData['language_id'] !== null) {
                $langId = (int)$normalizedData['language_id'];
                if (empty($lookups['languages'][$langId])) {
                    $rowErrors[] = "Foreign key violation: language_id '{$langId}' does not exist in 'languages' table.";
                }
            }

            if (isset($normalizedData['publisher_id']) && $normalizedData['publisher_id'] !== null) {
                $pubId = (int)$normalizedData['publisher_id'];
                if (empty($lookups['publishers'][$pubId])) {
                    $rowErrors[] = "Foreign key violation: publisher_id '{$pubId}' does not exist in 'publishers' table.";
                }
            }

            if (isset($normalizedData['console_type_id']) && $normalizedData['console_type_id'] !== null) {
                $ctId = (int)$normalizedData['console_type_id'];
                if (empty($lookups['console_types'][$ctId])) {
                    $rowErrors[] = "Foreign key violation: console_type_id '{$ctId}' does not exist in 'console_types' table.";
                }
            }

            if (isset($normalizedData['master_reference_id']) && $normalizedData['master_reference_id'] !== null) {
                $mRefId = (int)$normalizedData['master_reference_id'];
                if (empty($lookups['consoles'][$mRefId])) {
                    $rowErrors[] = "Foreign key violation: master_reference_id '{$mRefId}' does not exist in 'consoles' table.";
                }
            }

            // Consoles: If is_for_reference is 1, require master_reference_id
            if ($table === 'consoles') {
                $isRef = !empty($normalizedData['is_for_reference']);
                $hasMaster = !empty($normalizedData['master_reference_id']);
                if ($isRef && !$hasMaster) {
                    $rowErrors[] = "Reference-only console requires a valid Master Platform ('master_reference_id').";
                }
            }

            // Console types: Check unique name
            if ($table === 'console_types' && !empty($normalizedData['name'])) {
                $lowerName = strtolower(trim((string)$normalizedData['name']));
                if (!empty($lookups['existing_names'][$lowerName])) {
                    $rowErrors[] = "Console type name '{$normalizedData['name']}' already exists in the database.";
                }
            }

            $isOk = empty($rowErrors);
            if ($isOk) {
                $validCount++;
            } else {
                $invalidCount++;
            }

            $validatedRecords[] = [
                'row_number' => $rowNum,
                'status'     => $isOk ? 'OK' : 'NOT_OK',
                'data'       => $normalizedData,
                'errors'     => $rowErrors,
            ];
        }

        return [
            'table'           => $table,
            'total_records'   => count($rows),
            'valid_count'     => $validCount,
            'invalid_count'   => $invalidCount,
            'mapped_columns'  => array_values($headerMap),
            'ignored_columns' => $ignoredHeaders,
            'all_ok'          => ($invalidCount === 0),
            'all_invalid'     => ($validCount === 0),
            'records'         => $validatedRecords,
        ];
    }

    /**
     * Inserts an array of validated records into the database within a transaction.
     *
     * @param string $table
     * @param array<int, array<string, mixed>> $records
     * @return array<string, mixed>
     */
    public function insertBatch(string $table, array $records): array
    {
        $schema = $this->getTableSchema($table);
        $allowedCols = array_keys($schema['columns']);

        if (empty($records)) {
            return [
                'table'          => $table,
                'total_records'  => 0,
                'uploaded_count' => 0,
                'failed_count'   => 0,
                'errors'         => [],
            ];
        }

        return Database::transaction(function (PDO $pdo) use ($table, $records, $allowedCols) {
            $inserted = 0;
            $failed = 0;
            $errors = [];

            foreach ($records as $index => $record) {
                $rowNum = $record['row_number'] ?? ($index + 1);
                $data = $record['data'] ?? $record;

                // Filter down to valid columns for the target table (excluding id)
                $insertCols = [];
                $placeholders = [];
                $params = [];

                foreach ($allowedCols as $col) {
                    if (array_key_exists($col, $data)) {
                        $insertCols[] = "`{$col}`";
                        $paramKey = ":{$col}";
                        $placeholders[] = $paramKey;
                        $params[$paramKey] = $data[$col];
                    }
                }

                if (empty($insertCols)) {
                    $failed++;
                    $errors[] = "Row {$rowNum}: No valid column data to insert.";
                    continue;
                }

                $sql = sprintf(
                    "INSERT INTO `%s` (%s) VALUES (%s)",
                    $table,
                    implode(', ', $insertCols),
                    implode(', ', $placeholders)
                );

                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $inserted++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = "Row {$rowNum}: Database insertion error - " . $e->getMessage();
                }
            }

            return [
                'table'          => $table,
                'total_records'  => count($records),
                'uploaded_count' => $inserted,
                'failed_count'   => $failed,
                'errors'         => $errors,
            ];
        });
    }
}
