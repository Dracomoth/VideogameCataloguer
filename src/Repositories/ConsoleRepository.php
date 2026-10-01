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
                c.console_type_id,
                ct.name AS console_type_name,
                ct.badge_bg_color,
                ct.badge_font_color,
                c.is_for_reference,
                c.master_reference_id,
                mc.name AS master_console_name,
                c.created,
                c.updated,
                (SELECT COUNT(*) FROM `consoles` sub_ref WHERE sub_ref.master_reference_id = c.id) AS reference_consoles_count,
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
            LEFT JOIN `console_types` ct ON ct.id = c.console_type_id
            LEFT JOIN `publishers` p ON p.id = c.publisher_id
            LEFT JOIN `consoles` mc ON mc.id = c.master_reference_id
            LEFT JOIN `games` g ON g.console_id = c.id
            GROUP BY c.id, mc.name
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
                c.console_type_id,
                ct.name AS console_type_name,
                ct.badge_bg_color,
                ct.badge_font_color,
                c.is_for_reference,
                c.master_reference_id,
                mc.name AS master_console_name,
                c.created,
                c.updated,
                (SELECT COUNT(*) FROM `consoles` sub_ref WHERE sub_ref.master_reference_id = c.id) AS reference_consoles_count,
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
            LEFT JOIN `console_types` ct ON ct.id = c.console_type_id
            LEFT JOIN `publishers` p ON p.id = c.publisher_id
            LEFT JOIN `consoles` mc ON mc.id = c.master_reference_id
            LEFT JOIN `games` g ON g.console_id = c.id
            WHERE c.id = :id
            GROUP BY c.id, mc.name
        ";

        $row = Database::fetchOne($sql, [':id' => $id]);
        if (!$row) {
            return null;
        }

        return $this->formatConsoleRecord($row);
    }

    /**
     * Retrieves all non-reference consoles (playable systems that can have games).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPlayableConsoles(): array
    {
        $all = $this->getAll();
        return array_values(array_filter($all, function (array $c): bool {
            return empty($c['is_for_reference']);
        }));
    }

    /**
     * Retrieves candidate consoles that can serve as master platforms.
     *
     * @param int|null $excludeId Optional console ID to exclude from being its own master
     * @return array<int, array<string, mixed>>
     */
    public function getMasterCandidates(?int $excludeId = null): array
    {
        $all = $this->getAll();
        return array_values(array_filter($all, function (array $c) use ($excludeId): bool {
            if (!empty($c['is_for_reference'])) {
                return false;
            }
            if ($excludeId !== null && (int)$c['id'] === $excludeId) {
                return false;
            }
            return true;
        }));
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
            clearstatcache(true, $fullPath);
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
            clearstatcache(true, $destination);
        }

        $saved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $destination)
            : copy($file['tmp_name'], $destination);

        if (!$saved) {
            throw new RuntimeException("Failed to save uploaded {$type} to destination: {$filename}");
        }

        clearstatcache(true, $destination);

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
        $consoleTypeId      = !empty($data['console_type_id']) ? (int)$data['console_type_id'] : 1;
        $isForReference     = !empty($data['is_for_reference']) ? 1 : 0;
        $comments           = trim((string)($data['comments'] ?? '')) ?: null;
        $emulator           = trim((string)($data['emulator'] ?? '')) ?: null;
        $emulatorLink       = trim((string)($data['emulator_link'] ?? '')) ?: null;
        $emulatorAndroid    = trim((string)($data['emulator_android'] ?? '')) ?: null;
        $emulatorAndroidLink= trim((string)($data['emulator_android_link'] ?? '')) ?: null;
        $retroarchCore      = trim((string)($data['retroarch_core'] ?? '')) ?: null;
        $coreLink           = trim((string)($data['core_link'] ?? '')) ?: null;

        $masterReferenceId  = null;
        if ($isForReference) {
            $masterReferenceId = !empty($data['master_reference_id']) ? (int)$data['master_reference_id'] : null;
            if ($masterReferenceId === null || $masterReferenceId <= 0) {
                throw new InvalidArgumentException("Please select a Master Platform for this reference-only console.");
            }
            $masterConsole = Database::fetchOne("SELECT id, name, is_for_reference FROM `consoles` WHERE id = :id", [':id' => $masterReferenceId]);
            if (!$masterConsole) {
                throw new InvalidArgumentException("Selected Master Platform not found.");
            }
            if (!empty($masterConsole['is_for_reference'])) {
                throw new InvalidArgumentException("The selected Master Platform is itself marked as reference-only. A reference console cannot be the master of another console.");
            }
        }

        $now = date('Y-m-d H:i:s');

        $sql = "
            INSERT INTO `consoles` (
                `name`, `publisher_id`, `year`, `generation`,
                `console_type_id`, `is_for_reference`, `master_reference_id`,
                `image_path`, `logo_path`, `comments`,
                `emulator`, `emulator_link`,
                `emulator_android`, `emulator_android_link`,
                `retroarch_core`, `core_link`,
                `created`, `updated`
            ) VALUES (
                :name, :publisher_id, :year, :generation,
                :console_type_id, :is_for_reference, :master_reference_id,
                NULL, NULL, :comments,
                :emulator, :emulator_link,
                :emulator_android, :emulator_android_link,
                :retroarch_core, :core_link,
                :created, :updated
            )
        ";

        Database::execute($sql, [
            ':name'                 => $name,
            ':publisher_id'         => $publisherId,
            ':year'                 => $year,
            ':generation'           => $generation,
            ':console_type_id'      => $consoleTypeId,
            ':is_for_reference'     => $isForReference,
            ':master_reference_id'  => $masterReferenceId,
            ':comments'             => $comments,
            ':emulator'             => $emulator,
            ':emulator_link'        => $emulatorLink,
            ':emulator_android'     => $emulatorAndroid,
            ':emulator_android_link'=> $emulatorAndroidLink,
            ':retroarch_core'       => $retroarchCore,
            ':core_link'            => $coreLink,
            ':created'              => $now,
            ':updated'              => $now,
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
            Database::execute("UPDATE `consoles` SET `image_path` = :img, `logo_path` = :logo, `updated` = :updated WHERE `id` = :id", [
                ':img'     => $newImagePath,
                ':logo'    => $newLogoPath,
                ':updated' => $now,
                ':id'      => $newId,
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
        $consoleTypeId      = !empty($data['console_type_id']) ? (int)$data['console_type_id'] : 1;
        $isForReference     = !empty($data['is_for_reference']) ? 1 : 0;
        $comments           = trim((string)($data['comments'] ?? '')) ?: null;
        $emulator           = trim((string)($data['emulator'] ?? '')) ?: null;
        $emulatorLink       = trim((string)($data['emulator_link'] ?? '')) ?: null;
        $emulatorAndroid    = trim((string)($data['emulator_android'] ?? '')) ?: null;
        $emulatorAndroidLink= trim((string)($data['emulator_android_link'] ?? '')) ?: null;
        $retroarchCore      = trim((string)($data['retroarch_core'] ?? '')) ?: null;
        $coreLink           = trim((string)($data['core_link'] ?? '')) ?: null;

        $masterReferenceId  = null;
        if ($isForReference) {
            $masterReferenceId = !empty($data['master_reference_id']) ? (int)$data['master_reference_id'] : null;
            if ($masterReferenceId === null || $masterReferenceId <= 0) {
                throw new InvalidArgumentException("Please select a Master Platform for this reference-only console.");
            }
            if ($masterReferenceId === $id) {
                throw new InvalidArgumentException("A console cannot select itself as its own master platform.");
            }
            $masterConsole = Database::fetchOne("SELECT id, name, is_for_reference FROM `consoles` WHERE id = :id", [':id' => $masterReferenceId]);
            if (!$masterConsole) {
                throw new InvalidArgumentException("Selected Master Platform not found.");
            }
            if (!empty($masterConsole['is_for_reference'])) {
                throw new InvalidArgumentException("The selected Master Platform is itself marked as reference-only. A reference console cannot be the master of another console.");
            }

            // Cannot mark console as reference if it has games associated
            $gamesCount = $this->getGamesCount($id);
            if ($gamesCount > 0) {
                throw new InvalidArgumentException("Cannot mark this console as reference-only because it has {$gamesCount} game(s) in its library. Reference consoles cannot host game libraries.");
            }

            // Cannot mark console as reference if it is master of other reference console
            $refCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM `consoles` WHERE master_reference_id = :id", [':id' => $id]);
            if ($refCount > 0) {
                throw new InvalidArgumentException("Cannot mark this console as reference-only because it is already the master platform for {$refCount} other reference console(s).");
            }
        }

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

        $now = date('Y-m-d H:i:s');

        $sql = "
            UPDATE `consoles` SET
                `name`                  = :name,
                `publisher_id`          = :publisher_id,
                `year`                  = :year,
                `generation`            = :generation,
                `console_type_id`       = :console_type_id,
                `is_for_reference`      = :is_for_reference,
                `master_reference_id`   = :master_reference_id,
                `image_path`            = :image_path,
                `logo_path`             = :logo_path,
                `comments`              = :comments,
                `emulator`              = :emulator,
                `emulator_link`         = :emulator_link,
                `emulator_android`      = :emulator_android,
                `emulator_android_link` = :emulator_android_link,
                `retroarch_core`        = :retroarch_core,
                `core_link`             = :core_link,
                `updated`               = :updated
            WHERE `id` = :id
        ";

        $affected = Database::execute($sql, [
            ':name'                 => $name,
            ':publisher_id'         => $publisherId,
            ':year'                 => $year,
            ':generation'           => $generation,
            ':console_type_id'      => $consoleTypeId,
            ':is_for_reference'     => $isForReference,
            ':master_reference_id'  => $masterReferenceId,
            ':image_path'           => $currentImagePath,
            ':logo_path'            => $currentLogoPath,
            ':comments'             => $comments,
            ':emulator'             => $emulator,
            ':emulator_link'        => $emulatorLink,
            ':emulator_android'     => $emulatorAndroid,
            ':emulator_android_link'=> $emulatorAndroidLink,
            ':retroarch_core'       => $retroarchCore,
            ':core_link'            => $coreLink,
            ':updated'              => $now,
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

        // Check if any reference consoles have this console as master
        $refCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM `consoles` WHERE master_reference_id = :id", [':id' => $id]);
        if ($refCount > 0) {
            throw new RuntimeException("Cannot delete this console because it is the master platform for {$refCount} other reference console(s). Reassign or delete those reference consoles first.");
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

        $row['id']                       = (int)$row['id'];
        $row['publisher_id']             = !empty($row['publisher_id']) ? (int)$row['publisher_id'] : null;
        $row['console_type_id']          = (int)($row['console_type_id'] ?? 1);
        $row['console_type_name']        = (string)($row['console_type_name'] ?? 'Home');
        $row['badge_bg_color']           = (string)($row['badge_bg_color'] ?? '#1E3A8A');
        $row['badge_font_color']         = (string)($row['badge_font_color'] ?? '#93C5FD');
        $row['is_for_reference']         = (int)($row['is_for_reference'] ?? 0);
        $row['master_reference_id']      = !empty($row['master_reference_id']) ? (int)$row['master_reference_id'] : null;
        $row['master_console_id']        = $row['master_reference_id'];
        $row['master_console_name']      = (string)($row['master_console_name'] ?? '');
        $row['reference_consoles_count'] = (int)($row['reference_consoles_count'] ?? 0);
        $row['games_count']              = (int)($row['games_count'] ?? 0);
        $row['game_count']               = (int)($row['game_count'] ?? 0);
        $row['created']                  = !empty($row['created']) ? (string)$row['created'] : '';
        $row['updated']                  = !empty($row['updated']) ? (string)$row['updated'] : '';

        // Web accessible URLs for reading with automatic filemtime cache-busting
        $imgVer = '';
        if ($imagePath !== '') {
            $cleanImg = ltrim(trim($imagePath), '/\\');
            $fullImg  = $this->rootPath . '/' . $cleanImg;
            if (file_exists($fullImg)) {
                $imgVer = '?v=' . filemtime($fullImg);
            }
        }

        $logoVer = '';
        if ($logoPath !== '') {
            $cleanLogo = ltrim(trim($logoPath), '/\\');
            $fullLogo  = $this->rootPath . '/' . $cleanLogo;
            if (file_exists($fullLogo)) {
                $logoVer = '?v=' . filemtime($fullLogo);
            }
        }

        $row['image_url'] = $imagePath !== '' ? ('/' . ltrim($imagePath, '/\\') . $imgVer) : '';
        $row['logo_url']  = $logoPath !== ''  ? ('/' . ltrim($logoPath, '/\\')  . $logoVer) : '';

        return $row;
    }
}
