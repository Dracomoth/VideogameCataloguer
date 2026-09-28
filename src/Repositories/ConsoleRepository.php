<?php
/**
 * src/Repositories/ConsoleRepository.php
 * Data repository managing game consoles, hardware specifications, and asset lifecycle.
 */

declare(strict_types=1);

namespace Vault\Repositories;

use Vault\Services\Database;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class ConsoleRepository
{
    private string $rootPath;
    private string $consolesImageDir;

    public function __construct()
    {
        $this->rootPath = (string)($GLOBALS['config']['paths']['root'] ?? dirname(__DIR__, 2));
        $this->consolesImageDir = $this->rootPath . '/images/consoles';

        if (!is_dir($this->consolesImageDir)) {
            @mkdir($this->consolesImageDir, 0755, true);
        }
    }

    /**
     * Retrieves all consoles with attached publisher maker and linked game counts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $sql = "
            SELECT 
                c.id,
                c.name,
                c.publisher_id,
                c.year,
                c.generation,
                c.is_handheld,
                c.is_computer,
                c.is_arcade,
                c.is_for_reference,
                c.image_path,
                c.logo_path,
                c.comments,
                c.emulator,
                c.emulator_link,
                c.emulator_android,
                c.emulator_android_link,
                c.retroarch_core,
                c.core_link,
                p.name AS maker_name,
                COUNT(DISTINCT g.id) AS games_count,
                COUNT(DISTINCT g.id) AS game_count
            FROM `consoles` c
            LEFT JOIN `publishers` p ON p.id = c.publisher_id
            LEFT JOIN `games` g ON g.console_id = c.id
            GROUP BY c.id
            ORDER BY c.name ASC
        ";

        $rows = Database::fetchAll($sql);

        return array_map(function (array $row): array {
            return $this->formatConsoleRecord($row);
        }, $rows);
    }

    /**
     * Retrieves a single console by ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT 
                c.id,
                c.name,
                c.publisher_id,
                c.year,
                c.generation,
                c.is_handheld,
                c.is_computer,
                c.is_arcade,
                c.is_for_reference,
                c.image_path,
                c.logo_path,
                c.comments,
                c.emulator,
                c.emulator_link,
                c.emulator_android,
                c.emulator_android_link,
                c.retroarch_core,
                c.core_link,
                p.name AS maker_name,
                COUNT(DISTINCT g.id) AS games_count,
                COUNT(DISTINCT g.id) AS game_count
            FROM `consoles` c
            LEFT JOIN `publishers` p ON p.id = c.publisher_id
            LEFT JOIN `games` g ON g.console_id = c.id
            WHERE c.id = :id
            GROUP BY c.id
        ";

        $row = Database::fetchOne($sql, [':id' => $id]);
        if (!$row) {
            return null;
        }

        return $this->formatConsoleRecord($row);
    }

    /**
     * Counts how many games are assigned to a specific console.
     *
     * @param int $id
     * @return int
     */
    public function getGamesCount(int $id): int
    {
        $sql = "SELECT COUNT(id) AS total FROM `games` WHERE console_id = :id";
        $row = Database::fetchOne($sql, [':id' => $id]);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Checks if a console name already exists, optionally excluding a specific ID.
     *
     * @param string $name
     * @param int|null $excludeId
     * @return bool
     */
    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $trimmed = trim($name);
        if ($excludeId !== null) {
            $sql = "SELECT id FROM `consoles` WHERE LOWER(name) = LOWER(:name) AND id != :excludeId LIMIT 1";
            $res = Database::fetchOne($sql, [':name' => $trimmed, ':excludeId' => $excludeId]);
        } else {
            $sql = "SELECT id FROM `consoles` WHERE LOWER(name) = LOWER(:name) LIMIT 1";
            $res = Database::fetchOne($sql, [':name' => $trimmed]);
        }

        return $res !== null;
    }

    /**
     * Escapes console name for filesystem storage.
     * Replaces non-alphanumeric chars with underscore, collapses duplicates.
     *
     * @param string $name
     * @return string
     */
    public static function escapeConsoleName(string $name): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($name));
        $clean = trim(preg_replace('/_+/', '_', (string)$clean), '_');
        return $clean !== '' ? $clean : 'console';
    }

    /**
     * Deletes a file on disk given a relative path stored in the database.
     * Does NOT consider any naming convention; unlinks whatever file is referenced.
     *
     * @param string|null $relativePath
     */
    public function deleteAssetFile(?string $relativePath): void
    {
        if (empty($relativePath)) {
            return;
        }

        $clean = ltrim(trim($relativePath), '/\\');
        $fullPath = $this->rootPath . '/' . $clean;

        if (file_exists($fullPath) && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * Handles uploading, renaming, and storing an image asset according to the convention:
     * <id>_<console name(escaped)>_image.<ext> or <id>_<console name(escaped)>_logo.<ext>
     *
     * @param array<string, mixed> $file PHP $_FILES entry
     * @param int $consoleId
     * @param string $consoleName
     * @param string $type 'image' or 'logo'
     * @return string Relative path stored in database
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function storeAssetFile(array $file, int $consoleId, string $consoleName, string $type): string
    {
        if (empty($file['tmp_name']) || (!is_uploaded_file($file['tmp_name']) && !file_exists($file['tmp_name']))) {
            throw new InvalidArgumentException("No valid uploaded file found for {$type}.");
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Upload failed for {$type} with error code {$file['error']}.");
        }

        $allowedMimes = [
            'image/jpeg'    => 'jpg',
            'image/png'     => 'png',
            'image/webp'    => 'webp',
            'image/gif'     => 'gif',
            'image/svg+xml' => 'svg',
        ];

        $ext = null;
        if (class_exists('finfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (isset($allowedMimes[$mime])) {
                $ext = $allowedMimes[$mime];
            }
        }

        if ($ext === null) {
            $origExt = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
            if (in_array($origExt, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true)) {
                $ext = ($origExt === 'jpeg') ? 'jpg' : $origExt;
            }
        }

        if ($ext === null) {
            throw new InvalidArgumentException("Invalid file format for {$type}. Allowed formats: JPG, PNG, WEBP, GIF, SVG.");
        }

        $slug = self::escapeConsoleName($consoleName);
        $filename = "{$consoleId}_{$slug}_{$type}.{$ext}";
        $destination = $this->consolesImageDir . '/' . $filename;

        // If file with exact destination already exists, unlink it first
        if (file_exists($destination) && is_file($destination)) {
            @unlink($destination);
        }

        $saved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $destination)
            : copy($file['tmp_name'], $destination);

        if (!$saved) {
            throw new RuntimeException("Failed to save uploaded {$type} to destination: {$filename}");
        }

        return "images/consoles/{$filename}";
    }

    /**
     * Creates a new console record with optional uploaded photo & logo assets.
     *
     * 1) Insert: Images uploaded are renamed before storing in the server folder.
     * Naming convention: <id>_<console name(escaped)>_image.<ext> for image_path,
     * and <id>_<console name(escaped)>_logo.<ext> for logo_path.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $files
     * @return int Inserted console ID
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function create(array $data, array $files = []): int
    {
        $name = trim((string)($data['name'] ?? ($data['console'] ?? '')));

        if ($name === '') {
            throw new InvalidArgumentException('Console title cannot be empty.');
        }

        if (strlen($name) > 255) {
            throw new InvalidArgumentException('Console title cannot exceed 255 characters.');
        }

        if ($this->existsByName($name)) {
            throw new InvalidArgumentException("A console named '{$name}' already exists.");
        }

        $publisherId        = !empty($data['publisher_id']) ? (int)$data['publisher_id'] : null;
        $year               = trim((string)($data['year'] ?? '')) ?: null;
        $generation         = trim((string)($data['generation'] ?? '')) ?: null;
        $isHandheld         = !empty($data['is_handheld']) ? 1 : 0;
        $isComputer         = !empty($data['is_computer']) ? 1 : 0;
        $isArcade           = !empty($data['is_arcade']) ? 1 : 0;
        $isForReference     = !empty($data['is_for_reference']) ? 1 : 0;
        $comments           = trim((string)($data['comments'] ?? '')) ?: null;
        $emulator           = trim((string)($data['emulator'] ?? '')) ?: null;
        $emulatorLink       = trim((string)($data['emulator_link'] ?? '')) ?: null;
        $emulatorAndroid    = trim((string)($data['emulator_android'] ?? '')) ?: null;
        $emulatorAndroidLink= trim((string)($data['emulator_android_link'] ?? '')) ?: null;
        $retroarchCore      = trim((string)($data['retroarch_core'] ?? '')) ?: null;
        $coreLink           = trim((string)($data['core_link'] ?? '')) ?: null;

        $sql = "
            INSERT INTO `consoles` (
                `name`, `publisher_id`, `year`, `generation`,
                `is_handheld`, `is_computer`, `is_arcade`, `is_for_reference`,
                `image_path`, `logo_path`, `comments`,
                `emulator`, `emulator_link`,
                `emulator_android`, `emulator_android_link`,
                `retroarch_core`, `core_link`
            ) VALUES (
                :name, :publisher_id, :year, :generation,
                :is_handheld, :is_computer, :is_arcade, :is_for_reference,
                NULL, NULL, :comments,
                :emulator, :emulator_link,
                :emulator_android, :emulator_android_link,
                :retroarch_core, :core_link
            )
        ";

        Database::execute($sql, [
            ':name'                 => $name,
            ':publisher_id'         => $publisherId,
            ':year'                 => $year,
            ':generation'           => $generation,
            ':is_handheld'          => $isHandheld,
            ':is_computer'          => $isComputer,
            ':is_arcade'            => $isArcade,
            ':is_for_reference'     => $isForReference,
            ':comments'             => $comments,
            ':emulator'             => $emulator,
            ':emulator_link'        => $emulatorLink,
            ':emulator_android'     => $emulatorAndroid,
            ':emulator_android_link'=> $emulatorAndroidLink,
            ':retroarch_core'       => $retroarchCore,
            ':core_link'            => $coreLink,
        ]);

        $newId = (int)Database::lastInsertId();

        // Process visual asset uploads
        $newImagePath = null;
        $newLogoPath = null;

        if (!empty($files['image_file']['tmp_name'])) {
            $newImagePath = $this->storeAssetFile($files['image_file'], $newId, $name, 'image');
        }

        if (!empty($files['logo_file']['tmp_name'])) {
            $newLogoPath = $this->storeAssetFile($files['logo_file'], $newId, $name, 'logo');
        }

        if ($newImagePath !== null || $newLogoPath !== null) {
            Database::execute("UPDATE `consoles` SET `image_path` = :img, `logo_path` = :logo WHERE `id` = :id", [
                ':img'  => $newImagePath,
                ':logo' => $newLogoPath,
                ':id'   => $newId,
            ]);
        }

        return $newId;
    }

    /**
     * Updates an existing console record.
     *
     * 2) Update: The server retrieves the previous value in both fields from the database
     * and deletes existing images (if any, no matter what value they have),
     * then renames uploaded images and saves them with the same convention as insert.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @param array<string, mixed> $files
     * @return bool
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function update(int $id, array $data, array $files = []): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid console ID specified.');
        }

        $existing = $this->getById($id);
        if (!$existing) {
            throw new RuntimeException("Console #{$id} not found.");
        }

        $name = trim((string)($data['name'] ?? ($data['console'] ?? '')));

        if ($name === '') {
            throw new InvalidArgumentException('Console title cannot be empty.');
        }

        if (strlen($name) > 255) {
            throw new InvalidArgumentException('Console title cannot exceed 255 characters.');
        }

        if ($this->existsByName($name, $id)) {
            throw new InvalidArgumentException("Another console named '{$name}' already exists.");
        }

        $publisherId        = !empty($data['publisher_id']) ? (int)$data['publisher_id'] : null;
        $year               = trim((string)($data['year'] ?? '')) ?: null;
        $generation         = trim((string)($data['generation'] ?? '')) ?: null;
        $isHandheld         = !empty($data['is_handheld']) ? 1 : 0;
        $isComputer         = !empty($data['is_computer']) ? 1 : 0;
        $isArcade           = !empty($data['is_arcade']) ? 1 : 0;
        $isForReference     = !empty($data['is_for_reference']) ? 1 : 0;
        $comments           = trim((string)($data['comments'] ?? '')) ?: null;
        $emulator           = trim((string)($data['emulator'] ?? '')) ?: null;
        $emulatorLink       = trim((string)($data['emulator_link'] ?? '')) ?: null;
        $emulatorAndroid    = trim((string)($data['emulator_android'] ?? '')) ?: null;
        $emulatorAndroidLink= trim((string)($data['emulator_android_link'] ?? '')) ?: null;
        $retroarchCore      = trim((string)($data['retroarch_core'] ?? '')) ?: null;
        $coreLink           = trim((string)($data['core_link'] ?? '')) ?: null;

        $deleteImage = !empty($data['delete_image']);
        $deleteLogo  = !empty($data['delete_logo']);

        $currentImagePath = $existing['image_path'];
        $currentLogoPath  = $existing['logo_path'];

        // Manage Hardware Photo lifecycle
        if (!empty($files['image_file']['tmp_name'])) {
            // New image uploaded: delete existing file on disk regardless of its path value
            $this->deleteAssetFile($currentImagePath);
            // Save new uploaded image with convention
            $currentImagePath = $this->storeAssetFile($files['image_file'], $id, $name, 'image');
        } elseif ($deleteImage) {
            // Explicit deletion requested: delete file and clear field
            $this->deleteAssetFile($currentImagePath);
            $currentImagePath = null;
        }

        // Manage Brand Logo lifecycle
        if (!empty($files['logo_file']['tmp_name'])) {
            // New logo uploaded: delete existing file on disk regardless of its path value
            $this->deleteAssetFile($currentLogoPath);
            // Save new uploaded logo with convention
            $currentLogoPath = $this->storeAssetFile($files['logo_file'], $id, $name, 'logo');
        } elseif ($deleteLogo) {
            // Explicit deletion requested: delete file and clear field
            $this->deleteAssetFile($currentLogoPath);
            $currentLogoPath = null;
        }

        $sql = "
            UPDATE `consoles` SET
                `name`                  = :name,
                `publisher_id`          = :publisher_id,
                `year`                  = :year,
                `generation`            = :generation,
                `is_handheld`           = :is_handheld,
                `is_computer`           = :is_computer,
                `is_arcade`             = :is_arcade,
                `is_for_reference`      = :is_for_reference,
                `image_path`            = :image_path,
                `logo_path`             = :logo_path,
                `comments`              = :comments,
                `emulator`              = :emulator,
                `emulator_link`         = :emulator_link,
                `emulator_android`      = :emulator_android,
                `emulator_android_link` = :emulator_android_link,
                `retroarch_core`        = :retroarch_core,
                `core_link`             = :core_link
            WHERE `id` = :id
        ";

        $affected = Database::execute($sql, [
            ':name'                 => $name,
            ':publisher_id'         => $publisherId,
            ':year'                 => $year,
            ':generation'           => $generation,
            ':is_handheld'          => $isHandheld,
            ':is_computer'          => $isComputer,
            ':is_arcade'            => $isArcade,
            ':is_for_reference'     => $isForReference,
            ':image_path'           => $currentImagePath,
            ':logo_path'            => $currentLogoPath,
            ':comments'             => $comments,
            ':emulator'             => $emulator,
            ':emulator_link'        => $emulatorLink,
            ':emulator_android'     => $emulatorAndroid,
            ':emulator_android_link'=> $emulatorAndroidLink,
            ':retroarch_core'       => $retroarchCore,
            ':core_link'            => $coreLink,
            ':id'                   => $id,
        ]);

        return $affected >= 0;
    }

    /**
     * Deletes a console by ID and cleans up associated image files.
     *
     * 4) Delete: Looks for files indicated in both fields (no matter what is inside,
     * not taking any convention into account) and deletes whatever file is referenced (if any).
     *
     * @param int $id
     * @return bool
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid console ID specified.');
        }

        $existing = $this->getById($id);
        if (!$existing) {
            throw new RuntimeException("Console #{$id} does not exist.");
        }

        $gamesCount = $this->getGamesCount($id);
        if ($gamesCount > 0) {
            throw new RuntimeException("Cannot delete this console because it has {$gamesCount} game(s) in its library. Reassign or delete those games first.");
        }

        // Delete whatever files are referenced in image_path and logo_path
        $this->deleteAssetFile($existing['image_path']);
        $this->deleteAssetFile($existing['logo_path']);

        $sql = "DELETE FROM `consoles` WHERE `id` = :id";
        $affected = Database::execute($sql, [':id' => $id]);

        return $affected > 0;
    }

    /**
     * Formats a raw console row from database, resolving asset URLs.
     *
     * 3) Read: Retrieves the values from both fields (if any) and prepares web URLs.
     * Does not enforce any convention on read; uses whatever is stored in the fields.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatConsoleRecord(array $row): array
    {
        $imagePath = (string)($row['image_path'] ?? '');
        $logoPath  = (string)($row['logo_path'] ?? '');

        $row['id']               = (int)$row['id'];
        $row['publisher_id']     = !empty($row['publisher_id']) ? (int)$row['publisher_id'] : null;
        $row['is_handheld']      = (int)($row['is_handheld'] ?? 0);
        $row['is_computer']      = (int)($row['is_computer'] ?? 0);
        $row['is_arcade']        = (int)($row['is_arcade'] ?? 0);
        $row['is_for_reference']  = (int)($row['is_for_reference'] ?? 0);
        $row['games_count']      = (int)($row['games_count'] ?? 0);
        $row['game_count']       = (int)($row['game_count'] ?? 0);

        // Web accessible URLs for reading
        $row['image_url'] = $imagePath !== '' ? ('/' . ltrim($imagePath, '/\\')) : '';
        $row['logo_url']  = $logoPath !== ''  ? ('/' . ltrim($logoPath, '/\\'))  : '';

        return $row;
    }
}
