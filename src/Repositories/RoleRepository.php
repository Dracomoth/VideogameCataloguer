<?php
/**
 * src/Repositories/RoleRepository.php
 * Data repository managing roles, system screens registry, and the dual-device permissions matrix.
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

final class RoleRepository
{
    /**
     * Retrieves all roles joined with total assigned active users count.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $sql = "
            SELECT 
                r.id,
                r.name,
                r.description,
                r.is_super,
                r.created_at,
                r.updated_at,
                COUNT(u.id) AS user_count
            FROM `roles` r
            LEFT JOIN `users` u ON u.role_id = r.id
            GROUP BY r.id
            ORDER BY r.id ASC
        ";

        return Database::fetchAll($sql);
    }

    /**
     * Retrieves a single role record by ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT 
                r.id,
                r.name,
                r.description,
                r.is_super,
                r.created_at,
                r.updated_at,
                COUNT(u.id) AS user_count
            FROM `roles` r
            LEFT JOIN `users` u ON u.role_id = r.id
            WHERE r.id = :id
            GROUP BY r.id
            LIMIT 1
        ";

        return Database::fetchOne($sql, [':id' => $id]);
    }

    /**
     * Finds a role by name for uniqueness verification.
     *
     * @param string $name
     * @return array<string, mixed>|null
     */
    public function findByName(string $name): ?array
    {
        $sql = "SELECT * FROM `roles` WHERE `name` = :name LIMIT 1";
        return Database::fetchOne($sql, [':name' => trim($name)]);
    }

    /**
     * Creates a new custom role.
     *
     * @param array<string, mixed> $data
     * @return int New role ID
     */
    public function create(array $data): int
    {
        $name = trim((string)($data['name'] ?? ''));
        $description = !empty($data['description']) ? trim((string)$data['description']) : null;

        if ($name === '') {
            throw new InvalidArgumentException('Role title cannot be empty.');
        }

        if ($this->findByName($name) !== null) {
            throw new InvalidArgumentException("A role named '{$name}' already exists.");
        }

        $sql = "INSERT INTO `roles` (`name`, `description`, `is_super`) VALUES (:name, :description, 0)";
        Database::execute($sql, [
            ':name'        => $name,
            ':description' => $description,
        ]);

        return (int)Database::lastInsertId();
    }

    /**
     * Updates an existing role's title and description.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $role = $this->getById($id);
        if (!$role) {
            throw new RuntimeException("Role with ID {$id} not found.");
        }

        $name = trim((string)($data['name'] ?? $role['name']));
        $description = array_key_exists('description', $data) 
            ? (empty($data['description']) ? null : trim((string)$data['description']))
            : $role['description'];

        if ($name === '') {
            throw new InvalidArgumentException('Role title cannot be empty.');
        }

        // If ID 1 (Super Admin), protect role name
        if ($id === 1) {
            $name = 'Super Admin';
        }

        $existing = $this->findByName($name);
        if ($existing && (int)$existing['id'] !== $id) {
            throw new InvalidArgumentException("A role named '{$name}' already exists.");
        }

        $sql = "UPDATE `roles` SET `name` = :name, `description` = :description WHERE `id` = :id";
        return Database::execute($sql, [
            ':id'          => $id,
            ':name'        => $name,
            ':description' => $description,
        ]) > 0;
    }

    /**
     * Deletes a role if no users are assigned and it is not Super Admin.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        if ($id === 1) {
            throw new RuntimeException('The primary Super Admin role cannot be deleted.');
        }

        $role = $this->getById($id);
        if (!$role) {
            throw new RuntimeException("Role with ID {$id} not found.");
        }

        if ((int)$role['user_count'] > 0) {
            throw new RuntimeException("Cannot delete role: {$role['user_count']} user(s) are currently assigned to it. Reassign those users first.");
        }

        $sql = "DELETE FROM `roles` WHERE `id` = :id";
        return Database::execute($sql, [':id' => $id]) > 0;
    }

    /**
     * Retrieves all registered screens ordered by sort order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllScreens(): array
    {
        $sql = "SELECT `id`, `screen_key`, `name`, `category`, `sort_order` FROM `screens` ORDER BY `sort_order` ASC, `id` ASC";
        return Database::fetchAll($sql);
    }

    /**
     * Retrieves role permissions keyed by screen_key.
     *
     * @param int $roleId
     * @return array<string, array{access_pc: string, access_other: string}>
     */
    public function getPermissions(int $roleId): array
    {
        $sql = "SELECT `screen_key`, `access_pc`, `access_other` FROM `role_permissions` WHERE `role_id` = :role_id";
        $rows = Database::fetchAll($sql, [':role_id' => $roleId]);

        $matrix = [];
        foreach ($rows as $row) {
            $matrix[$row['screen_key']] = [
                'access_pc'    => $row['access_pc'],
                'access_other' => $row['access_other'],
            ];
        }

        return $matrix;
    }

    /**
     * Retrieves all registered screens combined with their dual-device permissions for a role.
     * Defaults to 'none' if no explicit permission row exists.
     *
     * @param int $roleId
     * @return array<int, array<string, mixed>>
     */
    public function getScreensWithPermissions(int $roleId): array
    {
        $screens = $this->getAllScreens();
        $permissions = $this->getPermissions($roleId);

        foreach ($screens as &$screen) {
            $key = $screen['screen_key'];
            $screen['access_pc']    = $permissions[$key]['access_pc'] ?? 'none';
            $screen['access_other'] = $permissions[$key]['access_other'] ?? 'none';
        }
        unset($screen);

        return $screens;
    }

    /**
     * Saves or replaces the dual-device permissions matrix for a role within a transaction.
     *
     * @param int $roleId
     * @param array<string, array{access_pc?: string, access_other?: string}> $matrix
     * @return void
     */
    public function savePermissions(int $roleId, array $matrix): void
    {
        $role = $this->getById($roleId);
        if (!$role) {
            throw new RuntimeException("Role with ID {$roleId} not found.");
        }

        // Super Admin permissions cannot be modified
        if (!empty($role['is_super'])) {
            return;
        }

        $validLevels = ['none', 'read', 'write'];
        $screens = $this->getAllScreens();
        $validKeys = array_column($screens, 'screen_key');

        Database::transaction(function (PDO $pdo) use ($roleId, $matrix, $validLevels, $validKeys): void {
            // Remove existing permissions
            $delStmt = $pdo->prepare("DELETE FROM `role_permissions` WHERE `role_id` = :role_id");
            $delStmt->execute([':role_id' => $roleId]);

            // Prepare bulk insertion
            $insertSql = "
                INSERT INTO `role_permissions` (`role_id`, `screen_key`, `access_pc`, `access_other`) 
                VALUES (:role_id, :screen_key, :access_pc, :access_other)
            ";
            $insStmt = $pdo->prepare($insertSql);

            foreach ($validKeys as $screenKey) {
                $raw = $matrix[$screenKey] ?? [];
                $pc    = in_array($raw['access_pc'] ?? '', $validLevels, true) ? $raw['access_pc'] : 'none';
                $other = in_array($raw['access_other'] ?? '', $validLevels, true) ? $raw['access_other'] : 'none';

                $insStmt->execute([
                    ':role_id'      => $roleId,
                    ':screen_key'   => $screenKey,
                    ':access_pc'    => $pc,
                    ':access_other' => $other,
                ]);
            }
        });
    }
}
