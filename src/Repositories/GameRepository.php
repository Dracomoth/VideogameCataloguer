<?php
/**
 * src/Repositories/GameRepository.php
 * Data repository managing game catalog items, taxonomies, and visual asset lifecycle.
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

final class GameRepository
{
    private string $rootPath;
    private string $gamesImageDir;

    public function __construct()
    {
        $this->rootPath = (string)($GLOBALS['config']['paths']['root'] ?? dirname(__DIR__, 2));
        $this->gamesImageDir = $this->rootPath . '/images/games';

        if (!is_dir($this->gamesImageDir)) {
            @mkdir($this->gamesImageDir, 0755, true);
        }
    }

    /**
     * Retrieves all games joined with parent console, category, subcategory, publisher, and language.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $sql = "
            SELECT 
                g.id,
                g.title,
                g.console_id,
                g.category_id,
                g.subcategory_id,
                g.language_id,
                g.publisher_id,
                g.year,
                g.tags,
                g.screenshot_path,
                g.boxart_path,
                g.in_collection,
                g.comments,
                g.created,
                g.updated,
                c.name AS console_name,
                cat.name AS category_name,
                sub.name AS subcategory_name,
                pub.name AS publisher_name,
                lang.name AS language_name
            FROM `games` g
            LEFT JOIN `consoles` c ON c.id = g.console_id
            LEFT JOIN `categories` cat ON cat.id = g.category_id
            LEFT JOIN `subcategories` sub ON sub.id = g.subcategory_id
            LEFT JOIN `publishers` pub ON pub.id = g.publisher_id
            LEFT JOIN `languages` lang ON lang.id = g.language_id
            ORDER BY g.id DESC
        ";

        $rows = Database::fetchAll($sql);

        // Fetch attached downloadable files for all games
        $allFiles = Database::fetchAll("SELECT * FROM `downloadable_files` WHERE game_id IS NOT NULL ORDER BY display_name ASC");
        $filesByGame = [];
        foreach ($allFiles as $f) {
            $filesByGame[(int)$f['game_id']][] = $f;
        }

        return array_map(function (array $row) use ($filesByGame): array {
            $row['downloadable_files'] = $filesByGame[(int)$row['id']] ?? [];
            return $this->formatGameRecord($row);
        }, $rows);
    }

    /**
     * Retrieves a single game record by ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT 
                g.id,
                g.title,
                g.console_id,
                g.category_id,
                g.subcategory_id,
                g.language_id,
                g.publisher_id,
                g.year,
                g.tags,
                g.screenshot_path,
                g.boxart_path,
                g.in_collection,
                g.comments,
                g.created,
                g.updated,
                c.name AS console_name,
                cat.name AS category_name,
                sub.name AS subcategory_name,
                pub.name AS publisher_name,
                lang.name AS language_name
            FROM `games` g
            LEFT JOIN `consoles` c ON c.id = g.console_id
            LEFT JOIN `categories` cat ON cat.id = g.category_id
            LEFT JOIN `subcategories` sub ON sub.id = g.subcategory_id
            LEFT JOIN `publishers` pub ON pub.id = g.publisher_id
            LEFT JOIN `languages` lang ON lang.id = g.language_id
            WHERE g.id = :id
            LIMIT 1
        ";

        $row = Database::fetchOne($sql, [':id' => $id]);
        if (!$row) {
            return null;
        }

        $record = $this->formatGameRecord($row);
        $record['downloadable_files'] = Database::fetchAll(
            "SELECT * FROM `downloadable_files` WHERE game_id = :id ORDER BY display_name ASC",
            [':id' => $id]
        );

        return $record;
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
     * Synchronizes downloadable files for a given game.
     *
     * @param int $gameId
     * @param mixed $filesData JSON string or array of files
     */
    public function syncDownloadableFiles(int $gameId, mixed $filesData): void
    {
        if ($filesData === null || $filesData === '') {
            return;
        }

        $files = is_string($filesData) ? json_decode($filesData, true) : $filesData;
        if (!is_array($files)) {
            return;
        }

        $downloadRepo = new \Vault\Repositories\DownloadRepository();
        $existingFiles = $downloadRepo->getByGameId($gameId);
        $existingMap = [];
        foreach ($existingFiles as $ef) {
            $existingMap[(int)$ef['id']] = $ef;
        }

        $retainedIds = [];

        foreach ($files as $file) {
            if (!is_array($file)) continue;

            $displayName     = trim((string)($file['display_name'] ?? ''));
            $storageProvider = ($file['storage_provider'] ?? '') === 'blackblaze' ? 'blackblaze' : 'external';
            $fileKeyOrUrl    = trim((string)($file['file_key_or_url'] ?? ($file['path_or_url'] ?? '')));
            $fileId          = !empty($file['id']) && is_numeric($file['id']) ? (int)$file['id'] : null;

            if ($displayName === '' && $fileKeyOrUrl === '') {
                continue;
            }

            if ($fileId !== null && isset($existingMap[$fileId])) {
                // Update existing record
                $downloadRepo->update($fileId, [
                    'console_id'       => null,
                    'game_id'          => $gameId,
                    'display_name'     => $displayName,
                    'storage_provider' => $storageProvider,
                    'file_key_or_url'  => $fileKeyOrUrl,
                ]);
                $retainedIds[] = $fileId;
            } else {
                // Create new record
                $newFileId = $downloadRepo->create([
                    'console_id'       => null,
                    'game_id'          => $gameId,
                    'display_name'     => $displayName,
                    'storage_provider' => $storageProvider,
                    'file_key_or_url'  => $fileKeyOrUrl,
                    'download_count'   => 0,
                ]);
                $retainedIds[] = $newFileId;
            }
        }

        // Delete any existing files that were removed in the editor
        foreach ($existingFiles as $ef) {
            $efId = (int)$ef['id'];
            if (!in_array($efId, $retainedIds, true)) {
                $downloadRepo->delete($efId);
            }
        }
    }

    /**
     * Handles uploading, renaming, and storing a visual asset according to the convention:
     * <ID>_Img.<ext> for screenshot path
     * <ID>_Box.<ext> for boxart path
     *
     * @param array<string, mixed> $file PHP $_FILES entry
     * @param int $gameId
     * @param string $type 'Img' or 'Box'
     * @return string Relative path stored in database (e.g. "images/games/42_Img.jpg")
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function storeAssetFile(array $file, int $gameId, string $type): string
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

        $filename = "{$gameId}_{$type}.{$ext}";
        $destination = $this->gamesImageDir . '/' . $filename;

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

        return "images/games/{$filename}";
    }

    /**
     * Creates a new game record with optional uploaded screenshot & boxart assets.
     *
     * 1) Insert: The images uploaded will be renamed before stored in the server folder (images/games).
     * The naming conventions are: <ID>_Img.<ext> for screenshot path and <ID>_Box.<ext> for boxart path.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $files
     * @return int Inserted game ID
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function create(array $data, array $files = []): int
    {
        $title = trim((string)($data['title'] ?? ($data['game'] ?? '')));

        if ($title === '') {
            throw new InvalidArgumentException('Game title cannot be empty.');
        }

        if (strlen($title) > 255) {
            throw new InvalidArgumentException('Game title cannot exceed 255 characters.');
        }

        $consoleId = !empty($data['console_id']) ? (int)$data['console_id'] : null;
        if ($consoleId === null || $consoleId <= 0) {
            throw new InvalidArgumentException('Please select a platform/console.');
        }

        $targetConsole = Database::fetchOne("SELECT id, is_for_reference FROM `consoles` WHERE id = :id", [':id' => $consoleId]);
        if ($targetConsole && !empty($targetConsole['is_for_reference'])) {
            throw new InvalidArgumentException("Cannot assign a game to a Reference-Only console platform.");
        }

        $categoryId    = !empty($data['category_id']) ? (int)$data['category_id'] : null;
        $subcategoryId = !empty($data['subcategory_id']) ? (int)$data['subcategory_id'] : null;
        $languageId    = !empty($data['language_id']) ? (int)$data['language_id'] : null;
        $publisherId   = !empty($data['publisher_id']) ? (int)$data['publisher_id'] : null;
        $year          = trim((string)($data['year'] ?? '')) ?: null;
        $tags          = trim((string)($data['tags'] ?? '')) ?: null;
        $comments      = trim((string)($data['comments'] ?? '')) ?: null;

        $inCollection  = !empty($data['in_collection']) ? 1 : 0;

        $now = date('Y-m-d H:i:s');

        $sql = "
            INSERT INTO `games` (
                `title`, `console_id`, `category_id`, `subcategory_id`,
                `language_id`, `publisher_id`, `year`, `tags`,
                `screenshot_path`, `boxart_path`,
                `in_collection`, `comments`,
                `created`, `updated`
            ) VALUES (
                :title, :console_id, :category_id, :subcategory_id,
                :language_id, :publisher_id, :year, :tags,
                NULL, NULL,
                :in_collection, :comments,
                :created, :updated
            )
        ";

        Database::execute($sql, [
            ':title'          => $title,
            ':console_id'     => $consoleId,
            ':category_id'    => $categoryId,
            ':subcategory_id' => $subcategoryId,
            ':language_id'    => $languageId,
            ':publisher_id'   => $publisherId,
            ':year'           => $year,
            ':tags'           => $tags,
            ':in_collection'  => $inCollection,
            ':comments'       => $comments,
            ':created'        => $now,
            ':updated'        => $now,
        ]);

        $newId = (int)Database::lastInsertId();

        // Process visual asset uploads
        $newScreenshotPath = null;
        $newBoxartPath = null;

        // Screenshot upload (<ID>_Img.<ext>)
        $screenFile = $files['screenshot_file'] ?? ($files['screen_file'] ?? ($files['screenshot'] ?? null));
        if (!empty($screenFile['tmp_name'])) {
            $newScreenshotPath = $this->storeAssetFile($screenFile, $newId, 'Img');
        }

        // Boxart upload (<ID>_Box.<ext>)
        $boxartFile = $files['boxart_file'] ?? ($files['boxart'] ?? null);
        if (!empty($boxartFile['tmp_name'])) {
            $newBoxartPath = $this->storeAssetFile($boxartFile, $newId, 'Box');
        }

        if ($newScreenshotPath !== null || $newBoxartPath !== null) {
            Database::execute("UPDATE `games` SET `screenshot_path` = :screen, `boxart_path` = :box, `updated` = :updated WHERE `id` = :id", [
                ':screen'  => $newScreenshotPath,
                ':box'     => $newBoxartPath,
                ':updated' => $now,
                ':id'      => $newId,
            ]);
        }

        if (isset($data['downloadable_files_json']) || isset($data['downloadable_files'])) {
            $this->syncDownloadableFiles($newId, $data['downloadable_files_json'] ?? $data['downloadable_files']);
        }

        return $newId;
    }

    /**
     * Updates an existing game record.
     *
     * 2) Update: The server will retrieve the previous value in both fields from the database
     * and will delete the existing images (if any, and no matter what value they have),
     * then rename the uploaded images and save them, with the same convention as insert.
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
            throw new InvalidArgumentException('Invalid game ID specified.');
        }

        $existing = $this->getById($id);
        if (!$existing) {
            throw new RuntimeException("Game #{$id} not found.");
        }

        $title = trim((string)($data['title'] ?? ($data['game'] ?? '')));

        if ($title === '') {
            throw new InvalidArgumentException('Game title cannot be empty.');
        }

        if (strlen($title) > 255) {
            throw new InvalidArgumentException('Game title cannot exceed 255 characters.');
        }

        $consoleId = !empty($data['console_id']) ? (int)$data['console_id'] : null;
        if ($consoleId === null || $consoleId <= 0) {
            throw new InvalidArgumentException('Please select a platform/console.');
        }

        $targetConsole = Database::fetchOne("SELECT id, is_for_reference FROM `consoles` WHERE id = :id", [':id' => $consoleId]);
        if ($targetConsole && !empty($targetConsole['is_for_reference'])) {
            throw new InvalidArgumentException("Cannot assign a game to a Reference-Only console platform.");
        }

        $categoryId    = !empty($data['category_id']) ? (int)$data['category_id'] : null;
        $subcategoryId = !empty($data['subcategory_id']) ? (int)$data['subcategory_id'] : null;
        $languageId    = !empty($data['language_id']) ? (int)$data['language_id'] : null;
        $publisherId   = !empty($data['publisher_id']) ? (int)$data['publisher_id'] : null;
        $year          = trim((string)($data['year'] ?? '')) ?: null;
        $tags          = trim((string)($data['tags'] ?? '')) ?: null;
        $comments      = trim((string)($data['comments'] ?? '')) ?: null;

        $inCollection  = !empty($data['in_collection']) ? 1 : 0;

        $deleteScreenshot = !empty($data['delete_screenshot']) || !empty($data['delete_screen']);
        $deleteBoxart     = !empty($data['delete_boxart']);

        $currentScreenshotPath = $existing['screenshot_path'];
        $currentBoxartPath     = $existing['boxart_path'];

        // Manage Screenshot (<ID>_Img.<ext>) Lifecycle
        $screenFile = $files['screenshot_file'] ?? ($files['screen_file'] ?? ($files['screenshot'] ?? null));
        if (!empty($screenFile['tmp_name'])) {
            // New image uploaded: delete existing file on disk regardless of its path value
            $this->deleteAssetFile($currentScreenshotPath);
            // Save new uploaded image with convention <ID>_Img.<ext>
            $currentScreenshotPath = $this->storeAssetFile($screenFile, $id, 'Img');
        } elseif ($deleteScreenshot) {
            // Explicit deletion requested: delete file on disk and clear field in database
            $this->deleteAssetFile($currentScreenshotPath);
            $currentScreenshotPath = null;
        }

        // Manage Box Art (<ID>_Box.<ext>) Lifecycle
        $boxartFile = $files['boxart_file'] ?? ($files['boxart'] ?? null);
        if (!empty($boxartFile['tmp_name'])) {
            // New image uploaded: delete existing file on disk regardless of its path value
            $this->deleteAssetFile($currentBoxartPath);
            // Save new uploaded image with convention <ID>_Box.<ext>
            $currentBoxartPath = $this->storeAssetFile($boxartFile, $id, 'Box');
        } elseif ($deleteBoxart) {
            // Explicit deletion requested: delete file on disk and clear field in database
            $this->deleteAssetFile($currentBoxartPath);
            $currentBoxartPath = null;
        }

        $now = date('Y-m-d H:i:s');

        $sql = "
            UPDATE `games` SET
                `title`           = :title,
                `console_id`      = :console_id,
                `category_id`     = :category_id,
                `subcategory_id`  = :subcategory_id,
                `language_id`     = :language_id,
                `publisher_id`    = :publisher_id,
                `year`            = :year,
                `tags`            = :tags,
                `screenshot_path` = :screenshot_path,
                `boxart_path`     = :boxart_path,
                `in_collection`   = :in_collection,
                `comments`        = :comments,
                `updated`         = :updated
            WHERE `id` = :id
        ";

        $affected = Database::execute($sql, [
            ':title'           => $title,
            ':console_id'      => $consoleId,
            ':category_id'     => $categoryId,
            ':subcategory_id'  => $subcategoryId,
            ':language_id'     => $languageId,
            ':publisher_id'    => $publisherId,
            ':year'            => $year,
            ':tags'            => $tags,
            ':screenshot_path' => $currentScreenshotPath,
            ':boxart_path'     => $currentBoxartPath,
            ':in_collection'   => $inCollection,
            ':comments'        => $comments,
            ':updated'         => $now,
            ':id'              => $id,
        ]);

        if (isset($data['downloadable_files_json']) || isset($data['downloadable_files'])) {
            $this->syncDownloadableFiles($id, $data['downloadable_files_json'] ?? $data['downloadable_files']);
        }

        return $affected >= 0;
    }

    /**
     * Deletes a game by ID and cleans up associated image files.
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
            throw new InvalidArgumentException('Invalid game ID specified.');
        }

        $existing = $this->getById($id);
        if (!$existing) {
            throw new RuntimeException("Game #{$id} does not exist.");
        }

        // Delete whatever files are referenced in screenshot_path and boxart_path
        $this->deleteAssetFile($existing['screenshot_path']);
        $this->deleteAssetFile($existing['boxart_path']);

        $sql = "DELETE FROM `games` WHERE `id` = :id";
        $affected = Database::execute($sql, [':id' => $id]);

        return $affected > 0;
    }

    /**
     * Formats a raw game row from database, resolving asset URLs and collection badges.
     *
     * 3) Read: Retrieves the values from both fields (if any) and prepares web URLs.
     * Does not enforce any convention on read; uses whatever is stored in the fields.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatGameRecord(array $row): array
    {
        $screenshotPath = (string)($row['screenshot_path'] ?? '');
        $boxartPath     = (string)($row['boxart_path'] ?? '');

        $row['id']             = (int)$row['id'];
        $row['console_id']     = !empty($row['console_id']) ? (int)$row['console_id'] : null;
        $row['category_id']    = !empty($row['category_id']) ? (int)$row['category_id'] : null;
        $row['subcategory_id'] = !empty($row['subcategory_id']) ? (int)$row['subcategory_id'] : null;
        $row['language_id']    = !empty($row['language_id']) ? (int)$row['language_id'] : null;
        $row['publisher_id']   = !empty($row['publisher_id']) ? (int)$row['publisher_id'] : null;
        $row['in_collection']  = (int)($row['in_collection'] ?? 0);
        $row['created']        = !empty($row['created']) ? (string)$row['created'] : '';
        $row['updated']        = !empty($row['updated']) ? (string)$row['updated'] : '';

        if (isset($row['is_downloaded'])) {
            $row['is_downloaded'] = (int)$row['is_downloaded'];
        }
        if (isset($row['download_count'])) {
            $row['download_count'] = (int)$row['download_count'];
        }
        if (isset($row['is_played'])) {
            $row['is_played'] = (int)$row['is_played'];
        }
        if (isset($row['is_won'])) {
            $row['is_won'] = (int)$row['is_won'];
        }

        // Web accessible URLs for reading
        $row['screenshot_url'] = $screenshotPath !== '' ? ('/' . ltrim($screenshotPath, '/\\')) : '';
        $row['boxart_url']     = $boxartPath !== ''     ? ('/' . ltrim($boxartPath, '/\\'))     : '';

        // Aliases for compatibility
        $row['game']       = $row['title'];
        $row['screen_url'] = $row['screenshot_url'];

        // Determine collection & completion badge based on user criteria:
        // - Text: PENDING if game is not in collection, BACKLOG if in collection
        // - Color:
        //   - red: if ANY main field is missing (name, year, language, publisher, category, subcategory) OR if in_collection=1 but no download file is provided
        //   - amber (gold, matching provided image): if base info complete but missing either/both images and/or tags/comments
        //   - green: if everything is properly filled up
        $title          = trim((string)($row['title'] ?? ($row['game'] ?? '')));
        $year           = trim((string)($row['year'] ?? ''));
        $hasConsole     = !empty($row['console_id']) && (int)$row['console_id'] > 0;
        $hasLanguage    = !empty($row['language_id']) && (int)$row['language_id'] > 0;
        $hasPublisher   = !empty($row['publisher_id']) && (int)$row['publisher_id'] > 0;
        $hasCategory    = !empty($row['category_id']) && (int)$row['category_id'] > 0;
        $hasSubcategory = !empty($row['subcategory_id']) && (int)$row['subcategory_id'] > 0;

        $hasMainInfo = ($title !== '')
            && ($year !== '')
            && $hasConsole
            && $hasLanguage
            && $hasPublisher
            && $hasCategory
            && $hasSubcategory;

        $inCollection = ((int)($row['in_collection'] ?? 0)) === 1;
        $files        = $row['downloadable_files'] ?? [];
        $hasDownload  = !$inCollection || !empty($files) || !empty($row['has_download_files']) || !empty($row['download_file_id']) || !empty($row['download_count']);

        $hasScreenshot = !empty(trim((string)($row['screenshot_path'] ?? '')));
        $hasBoxart     = !empty(trim((string)($row['boxart_path'] ?? '')));
        $hasTags       = !empty(trim((string)($row['tags'] ?? '')));
        $hasComments   = !empty(trim((string)($row['comments'] ?? '')));

        $hasCompleteSecondary = $hasScreenshot && $hasBoxart && $hasTags && $hasComments;

        if (!$hasMainInfo || !$hasDownload) {
            $row['collection_badge_color'] = 'red';
        } elseif (!$hasCompleteSecondary) {
            $row['collection_badge_color'] = 'amber';
        } else {
            $row['collection_badge_color'] = 'green';
        }

        $row['collection_badge_text'] = ((int)($row['in_collection'] ?? 0) === 1) ? 'BACKLOG' : 'PENDING';
        $row['collection_status']     = $row['collection_badge_text'];

        return $row;
    }

    /**
     * Retrieves lightweight aggregate counts for real-time header telemetry.
     *
     * @return array{total_games: int, owned_games: int, total_consoles: int}
     */
    public function getTelemetry(): array
    {
        $sql = "
            SELECT 
                COUNT(*) AS total_games,
                SUM(CASE WHEN in_collection = 1 THEN 1 ELSE 0 END) AS owned_games
            FROM `games`
        ";
        $row = Database::fetchOne($sql) ?? [];
        $totalConsoles = (int)Database::fetchColumn("SELECT COUNT(*) FROM `consoles`");

        return [
            'total_games'    => (int)($row['total_games'] ?? 0),
            'owned_games'    => (int)($row['owned_games'] ?? 0),
            'total_consoles' => $totalConsoles,
        ];
    }

    /**
     * Retrieves lightweight catalog list for Games Portal.
     *
     * @param int|null $playerId
     * @return array<int, array<string, mixed>>
     */
    public function getGamePortalList(?int $playerId = null): array
    {
        if ($playerId === null) {
            $playerId = (int)(\Vault\Auth\Auth::id() ?? 1);
        }

        $sql = "
            SELECT 
                g.id,
                g.title,
                g.console_id,
                g.category_id,
                g.subcategory_id,
                g.publisher_id,
                g.language_id,
                g.year,
                g.tags,
                g.comments,
                g.in_collection,
                g.boxart_path,
                g.screenshot_path,
                c.name AS console_name,
                cat.name AS category_name,
                sub.name AS subcategory_name,
                pub.name AS publisher_name,
                COALESCE(pg.is_downloaded, 0) AS is_downloaded,
                COALESCE(pg.download_count, 0) AS download_count,
                pg.last_download_date,
                COALESCE(pg.is_played, 0) AS is_played,
                COALESCE(pg.is_won, 0) AS is_won
            FROM `games` g
            LEFT JOIN `consoles` c ON c.id = g.console_id
            LEFT JOIN `categories` cat ON cat.id = g.category_id
            LEFT JOIN `subcategories` sub ON sub.id = g.subcategory_id
            LEFT JOIN `publishers` pub ON pub.id = g.publisher_id
            LEFT JOIN `player_games` pg ON pg.game_id = g.id AND pg.player_id = :player_id
            ORDER BY g.title ASC
        ";

        $rows = Database::fetchAll($sql, [':player_id' => $playerId]);

        return array_map(function (array $row): array {
            return $this->formatGameRecord($row);
        }, $rows);
    }

    /**
     * Retrieves random games from a specific console (excluding given game ID if provided).
     *
     * @param int $consoleId
     * @param int $limit
     * @param int $excludeGameId
     * @return array<int, array<string, mixed>>
     */
    public function getRandomGamesByConsole(int $consoleId, int $limit = 15, int $excludeGameId = 0): array
    {
        $limit = max(1, $limit);
        $params = [':console_id' => $consoleId];
        $where = "WHERE g.console_id = :console_id";

        if ($excludeGameId > 0) {
            $where .= " AND g.id != :exclude_id";
            $params[':exclude_id'] = $excludeGameId;
        }

        $sql = "
            SELECT 
                g.id,
                g.title,
                g.boxart_path,
                g.screenshot_path,
                g.year,
                c.name AS console_name,
                p.name AS publisher_name
            FROM `games` g
            LEFT JOIN `consoles` c ON c.id = g.console_id
            LEFT JOIN `publishers` p ON p.id = g.publisher_id
            {$where}
            ORDER BY RAND()
            LIMIT {$limit}
        ";

        $rows = Database::fetchAll($sql, $params);
        return array_map(function (array $r): array {
            $boxart = trim((string)($r['boxart_path'] ?? ''));
            $r['boxart_url'] = $boxart !== '' ? ('/' . ltrim($boxart, '/\\')) : '';
            return $r;
        }, $rows);
    }

    /**
     * Retrieves random similar games (same category and subcategory; falls back to same category).
     *
     * @param int $categoryId
     * @param int $subcategoryId
     * @param int $limit
     * @param int $excludeGameId
     * @return array<int, array<string, mixed>>
     */
    public function getRandomSimilarGames(int $categoryId, int $subcategoryId, int $limit = 15, int $excludeGameId = 0): array
    {
        $limit = max(1, $limit);
        if ($categoryId <= 0) {
            return [];
        }

        $results = [];
        $existingIds = $excludeGameId > 0 ? [$excludeGameId] : [];

        // 1. Try matching both category and subcategory if subcategory is set
        if ($subcategoryId > 0) {
            $params = [
                ':cat_id' => $categoryId,
                ':subcat_id' => $subcategoryId,
            ];
            $where = "WHERE g.category_id = :cat_id AND g.subcategory_id = :subcat_id";
            if ($excludeGameId > 0) {
                $where .= " AND g.id != :exclude_id";
                $params[':exclude_id'] = $excludeGameId;
            }

            $sql = "
                SELECT 
                    g.id,
                    g.title,
                    g.boxart_path,
                    g.screenshot_path,
                    g.year,
                    c.name AS console_name,
                    p.name AS publisher_name,
                    cat.name AS category_name,
                    sub.name AS subcategory_name
                FROM `games` g
                LEFT JOIN `consoles` c ON c.id = g.console_id
                LEFT JOIN `publishers` p ON p.id = g.publisher_id
                LEFT JOIN `categories` cat ON cat.id = g.category_id
                LEFT JOIN `subcategories` sub ON sub.id = g.subcategory_id
                {$where}
                ORDER BY RAND()
                LIMIT {$limit}
            ";

            $results = Database::fetchAll($sql, $params);
            foreach ($results as $r) {
                $existingIds[] = (int)$r['id'];
            }
        }

        // 2. If we need more to reach $limit, fill with other games from the same category
        $needed = $limit - count($results);
        if ($needed > 0) {
            $params = [':cat_id' => $categoryId];
            $where = "WHERE g.category_id = :cat_id";
            if (!empty($existingIds)) {
                $inClause = implode(',', array_map('intval', $existingIds));
                $where .= " AND g.id NOT IN ({$inClause})";
            }

            $sql = "
                SELECT 
                    g.id,
                    g.title,
                    g.boxart_path,
                    g.screenshot_path,
                    g.year,
                    c.name AS console_name,
                    p.name AS publisher_name,
                    cat.name AS category_name,
                    sub.name AS subcategory_name
                FROM `games` g
                LEFT JOIN `consoles` c ON c.id = g.console_id
                LEFT JOIN `publishers` p ON p.id = g.publisher_id
                LEFT JOIN `categories` cat ON cat.id = g.category_id
                LEFT JOIN `subcategories` sub ON sub.id = g.subcategory_id
                {$where}
                ORDER BY RAND()
                LIMIT {$needed}
            ";

            $more = Database::fetchAll($sql, $params);
            $results = array_merge($results, $more);
        }

        return array_map(function (array $r): array {
            $boxart = trim((string)($r['boxart_path'] ?? ''));
            $r['boxart_url'] = $boxart !== '' ? ('/' . ltrim($boxart, '/\\')) : '';
            return $r;
        }, $results);
    }

    /**
     * Retrieves full game portal detail for a game (game record, downloadable files, 15 random games by console, 15 similar games, global downloads, player stats).
     *
     * @param int $gameId
     * @param int|null $playerId
     * @return array<string, mixed>|null
     */
    public function getGamePortalDetail(int $gameId, ?int $playerId = null): ?array
    {
        $game = $this->getById($gameId);
        if (!$game) {
            return null;
        }

        if ($playerId === null) {
            $playerId = (int)(\Vault\Auth\Auth::id() ?? 1);
        }

        $consoleId = (int)($game['console_id'] ?? 0);
        $categoryId = (int)($game['category_id'] ?? 0);
        $subcategoryId = (int)($game['subcategory_id'] ?? 0);

        $consoleGames = $consoleId > 0 ? $this->getRandomGamesByConsole($consoleId, 15, $gameId) : [];
        $similarGames = $categoryId > 0 ? $this->getRandomSimilarGames($categoryId, $subcategoryId, 15, $gameId) : [];

        // Global download count for this game
        $globalDownloadCount = (int)Database::fetchColumn("
            SELECT GREATEST(
                COALESCE((SELECT SUM(df.download_count) FROM `downloadable_files` df WHERE df.game_id = :id1), 0),
                COALESCE((SELECT SUM(pg.download_count) FROM `player_games` pg WHERE pg.game_id = :id2), 0)
            )
        ", [':id1' => $gameId, ':id2' => $gameId]);

        // Player statistics for this game
        $playerGame = Database::fetchOne("
            SELECT * FROM `player_games` 
            WHERE `player_id` = :player_id AND `game_id` = :game_id 
            LIMIT 1
        ", [
            ':player_id' => $playerId,
            ':game_id'   => $gameId,
        ]);

        $isDownloaded = !empty($playerGame['is_downloaded']) || (!empty($playerGame['download_count']) && (int)$playerGame['download_count'] > 0);
        $isPlayed = !empty($playerGame['is_played']);
        $isWon = !empty($playerGame['is_won']);

        $playerStats = [
            'is_downloaded'       => $isDownloaded,
            'download_count'      => (int)($playerGame['download_count'] ?? 0),
            'first_download_date' => $playerGame['first_download_date'] ?? null,
            'last_download_date'  => $playerGame['last_download_date'] ?? null,
            'is_played'           => $isPlayed,
            'played_date'         => $playerGame['played_date'] ?? null,
            'is_won'              => $isWon,
            'win_date'            => $playerGame['win_date'] ?? null,
        ];

        // Also merge player stats and global download count directly into the $game record
        $game['is_downloaded']        = $isDownloaded ? 1 : 0;
        $game['download_count']       = (int)($playerGame['download_count'] ?? 0);
        $game['last_download_date']   = $playerGame['last_download_date'] ?? null;
        $game['is_played']            = $isPlayed ? 1 : 0;
        $game['is_won']               = $isWon ? 1 : 0;
        $game['global_download_count'] = $globalDownloadCount;

        return [
            'game'                  => $game,
            'files'                 => $game['downloadable_files'] ?? [],
            'console_games'         => $consoleGames,
            'similar_games'         => $similarGames,
            'global_download_count' => $globalDownloadCount,
            'player_stats'          => $playerStats,
        ];
    }
}

