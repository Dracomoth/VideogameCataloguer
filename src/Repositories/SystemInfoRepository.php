<?php
/**
 * src/Repositories/SystemInfoRepository.php
 * Provides access to system and database version information from the system_info table.
 */

declare(strict_types=1);

namespace Vault\Repositories;

use Vault\Services\Database;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class SystemInfoRepository
{
    /** @var array{system: string, database: string}|null */
    private static ?array $cachedVersions = null;

    /**
     * Retrieves system and database version numbers.
     *
     * @return array{system: string, database: string}
     */
    public static function getVersions(): array
    {
        if (self::$cachedVersions !== null) {
            return self::$cachedVersions;
        }

        $versions = [
            'system'   => '4.0.0',
            'database' => '10.0.0',
        ];

        try {
            $rows = Database::fetchAll("SELECT item, version FROM system_info");
            foreach ($rows as $row) {
                $item = strtolower((string)($row['item'] ?? ''));
                if (isset($row['version']) && array_key_exists($item, $versions)) {
                    $versions[$item] = (string)$row['version'];
                }
            }
        } catch (\Throwable $e) {
            // Silently retain fallback versions if database call fails
        }

        self::$cachedVersions = $versions;
        return self::$cachedVersions;
    }
}
