<?php
/**
 * src/Repositories/PlayerHubRepository.php
 * Data repository providing query logic, filtering, random spotlight selection,
 * and gallery collection data for the Player Hub experience.
 */

declare(strict_types=1);

namespace Vault\Repositories;

use Vault\Services\Database;
use PDO;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class PlayerHubRepository
{
    /**
     * Retrieves taxonomy lookups for Player Hub filters (Platforms, Categories, Subcategories).
     *
     * @return array{
     *   platforms: array<int, array{id: int, name: string}>,
     *   categories: array<int, array{id: int, name: string}>,
     *   subcategories: array<int, array{id: int, name: string, category_id: int}>
     * }
     */
    public function getFilterTaxonomies(): array
    {
        $platforms = Database::fetchAll("
            SELECT id, name 
            FROM `consoles` 
            WHERE is_for_reference = 0 OR is_for_reference IS NULL 
            ORDER BY name ASC
        ");

        $categories = Database::fetchAll("
            SELECT id, name 
            FROM `categories` 
            ORDER BY name ASC
        ");

        $subcategories = Database::fetchAll("
            SELECT id, name, category_id 
            FROM `subcategories` 
            ORDER BY name ASC
        ");

        return [
            'platforms'     => $platforms,
            'categories'    => $categories,
            'subcategories' => $subcategories,
        ];
    }

    /**
     * Builds WHERE clause and bound parameters for Player Hub filters.
     *
     * @param array<string, mixed> $filters
     * @return array{sql: string, params: array<string, mixed>}
     */
    private function buildWhereClause(array $filters): array
    {
        $conditions = [];
        $params = [];

        $platformId = !empty($filters['platform_id']) ? (int)$filters['platform_id'] : 0;
        if ($platformId > 0) {
            $conditions[] = "g.console_id = :platform_id";
            $params[':platform_id'] = $platformId;
        }

        $categoryId = !empty($filters['category_id']) ? (int)$filters['category_id'] : 0;
        if ($categoryId > 0) {
            $conditions[] = "g.category_id = :category_id";
            $params[':category_id'] = $categoryId;
        }

        $subcategoryId = !empty($filters['subcategory_id']) ? (int)$filters['subcategory_id'] : 0;
        if ($subcategoryId > 0) {
            $conditions[] = "g.subcategory_id = :subcategory_id";
            $params[':subcategory_id'] = $subcategoryId;
        }

        $query = trim((string)($filters['q'] ?? ($filters['query'] ?? '')));
        if ($query !== '') {
            $conditions[] = "(g.title LIKE :search OR g.tags LIKE :search OR g.comments LIKE :search)";
            $params[':search'] = '%' . $query . '%';
        }

        $sql = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * Counts games meeting the provided criteria.
     *
     * @param array<string, mixed> $filters
     * @return int
     */
    public function countMatching(array $filters): int
    {
        $where = $this->buildWhereClause($filters);
        $sql = "SELECT COUNT(*) AS total FROM `games` g {$where['sql']}";
        $row = Database::fetchOne($sql, $where['params']);

        return (int)($row['total'] ?? 0);
    }

    /**
     * Picks a random game satisfying the filters.
     *
     * @param array<string, mixed> $filters
     * @param int|null $excludeId Optional ID to avoid picking the exact same game on re-roll
     * @return array<string, mixed>|null
     */
    public function pickRandom(array $filters, ?int $excludeId = null): ?array
    {
        $where = $this->buildWhereClause($filters);
        $params = $where['params'];
        $sqlWhere = $where['sql'];

        if ($excludeId !== null && $excludeId > 0) {
            $count = $this->countMatching($filters);
            if ($count > 1) {
                $sqlWhere .= ($sqlWhere === '' ? 'WHERE ' : ' AND ') . 'g.id != :exclude_id';
                $params[':exclude_id'] = $excludeId;
            }
        }

        $sql = "
            SELECT 
                g.id,
                g.title,
                g.year,
                g.tags,
                g.screenshot_path,
                g.boxart_path,
                g.in_collection,
                g.comments,
                c.name AS console_name,
                c.retroarch_core,
                c.emulator,
                ct.name AS console_type_name,
                ct.badge_bg_color,
                ct.badge_font_color,
                cat.name AS category_name,
                sub.name AS subcategory_name,
                pub.name AS publisher_name,
                lang.name AS language_name
            FROM `games` g
            LEFT JOIN `consoles` c ON c.id = g.console_id
            LEFT JOIN `console_types` ct ON ct.id = c.console_type_id
            LEFT JOIN `categories` cat ON cat.id = g.category_id
            LEFT JOIN `subcategories` sub ON sub.id = g.subcategory_id
            LEFT JOIN `publishers` pub ON pub.id = g.publisher_id
            LEFT JOIN `languages` lang ON lang.id = g.language_id
            {$sqlWhere}
            ORDER BY RAND()
            LIMIT 1
        ";

        $game = Database::fetchOne($sql, $params);
        return $game ? $this->formatGamePayload($game) : null;
    }

    /**
     * Retrieves a single game record by ID with full joined metadata.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function getGameById(int $id): ?array
    {
        $sql = "
            SELECT 
                g.id,
                g.title,
                g.year,
                g.tags,
                g.screenshot_path,
                g.boxart_path,
                g.in_collection,
                g.comments,
                c.name AS console_name,
                c.retroarch_core,
                c.emulator,
                ct.name AS console_type_name,
                ct.badge_bg_color,
                ct.badge_font_color,
                cat.name AS category_name,
                sub.name AS subcategory_name,
                pub.name AS publisher_name,
                lang.name AS language_name
            FROM `games` g
            LEFT JOIN `consoles` c ON c.id = g.console_id
            LEFT JOIN `console_types` ct ON ct.id = c.console_type_id
            LEFT JOIN `categories` cat ON cat.id = g.category_id
            LEFT JOIN `subcategories` sub ON sub.id = g.subcategory_id
            LEFT JOIN `publishers` pub ON pub.id = g.publisher_id
            LEFT JOIN `languages` lang ON lang.id = g.language_id
            WHERE g.id = :id
            LIMIT 1
        ";

        $game = Database::fetchOne($sql, [':id' => $id]);
        return $game ? $this->formatGamePayload($game) : null;
    }

    /**
     * Retrieves lightweight game rows (id, title, screenshot_path, boxart_path)
     * for populating the Others Section gallery grid.
     *
     * @param array<string, mixed> $filters
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function getMatchingGames(array $filters, int $limit = 2500): array
    {
        $where = $this->buildWhereClause($filters);
        $sql = "
            SELECT 
                g.id,
                g.title,
                g.screenshot_path,
                g.boxart_path
            FROM `games` g
            {$where['sql']}
            ORDER BY g.title ASC
            LIMIT {$limit}
        ";

        $rows = Database::fetchAll($sql, $where['params']);

        return array_map(function (array $r): array {
            return [
                'id'              => (int)$r['id'],
                'title'           => (string)$r['title'],
                'screenshot_path' => $this->normalizeImagePath($r['screenshot_path'], 'no_screen.jpg'),
                'boxart_path'     => $this->normalizeImagePath($r['boxart_path'], 'no_cover.jpg'),
            ];
        }, $rows);
    }

    /**
     * Formats a raw database row into an enriched game card payload.
     *
     * @param array<string, mixed> $game
     * @return array<string, mixed>
     */
    private function formatGamePayload(array $game): array
    {
        $publisher = trim((string)($game['publisher_name'] ?? ''));
        $catName = trim((string)($game['category_name'] ?? ''));
        $subcatName = trim((string)($game['subcategory_name'] ?? ''));
        $year = trim((string)($game['year'] ?? ''));
        $console = trim((string)($game['console_name'] ?? ''));

        // Build: Publisher • Category>Subcategory • Year • Console
        $categoryCombined = '';
        if ($catName !== '' && $subcatName !== '') {
            $categoryCombined = $catName . '>' . $subcatName;
        } elseif ($catName !== '') {
            $categoryCombined = $catName;
        } elseif ($subcatName !== '') {
            $categoryCombined = $subcatName;
        }

        $metaSegments = [];
        if ($publisher !== '') $metaSegments[] = $publisher;
        if ($categoryCombined !== '') $metaSegments[] = $categoryCombined;
        if ($year !== '') $metaSegments[] = $year;
        if ($console !== '') $metaSegments[] = $console;

        $metaString = implode(' • ', $metaSegments);

        return [
            'id'                => (int)$game['id'],
            'title'             => (string)$game['title'],
            'year'              => $year,
            'meta_string'       => $metaString,
            'boxart_path'       => $this->normalizeImagePath($game['boxart_path'], 'no_cover.jpg'),
            'screenshot_path'   => $this->normalizeImagePath($game['screenshot_path'], 'no_screen.jpg'),
            'in_collection'     => (int)($game['in_collection'] ?? 0),
            'console_name'      => $console,
            'console_type_name' => (string)($game['console_type_name'] ?? ''),
            'badge_bg_color'    => (string)($game['badge_bg_color'] ?? '#1e293b'),
            'badge_font_color'  => (string)($game['badge_font_color'] ?? '#38bdf8'),
            'language_name'     => (string)($game['language_name'] ?? ''),
            'retroarch_core'    => (string)($game['retroarch_core'] ?? ''),
            'emulator'          => (string)($game['emulator'] ?? ''),
            'comments'          => (string)($game['comments'] ?? ''),
        ];
    }

    /**
     * Resolves an image path or falls back to support placeholder images.
     */
    private function normalizeImagePath(?string $path, string $fallbackFile): string
    {
        if ($path === null || trim($path) === '') {
            return '/images/support/' . $fallbackFile;
        }

        $trimmed = ltrim(trim($path), '/\\');
        return '/' . $trimmed;
    }
}
