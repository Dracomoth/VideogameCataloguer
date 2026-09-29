<?php
/**
 * src/Repositories/UserRepository.php
 * Data repository managing user persistence, role joins, credentials, and profile state.
 */

declare(strict_types=1);

namespace Vault\Repositories;

use Vault\Auth\Auth;
use Vault\Services\Database;
use InvalidArgumentException;
use RuntimeException;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class UserRepository
{
    /**
     * Retrieves all registered users joined with their assigned role.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $sql = "
            SELECT 
                u.id,
                u.role_id,
                u.first_name,
                u.last_name,
                u.email,
                u.avatar_path,
                u.is_active,
                u.last_login_at,
                u.created_at,
                u.updated_at,
                r.name AS role_name,
                r.is_super
            FROM `users` u
            INNER JOIN `roles` r ON r.id = u.role_id
            ORDER BY u.id ASC
        ";

        return Database::fetchAll($sql);
    }

    /**
     * Retrieves a single user record by primary key ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT 
                u.id,
                u.role_id,
                u.first_name,
                u.last_name,
                u.email,
                u.avatar_path,
                u.is_active,
                u.last_login_at,
                u.created_at,
                u.updated_at,
                r.name AS role_name,
                r.is_super
            FROM `users` u
            INNER JOIN `roles` r ON r.id = u.role_id
            WHERE u.id = :id
            LIMIT 1
        ";

        return Database::fetchOne($sql, [':id' => $id]);
    }

    /**
     * Looks up an existing user by unique email.
     *
     * @param string $email
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT * FROM `users` WHERE `email` = :email LIMIT 1";
        return Database::fetchOne($sql, [':email' => trim($email)]);
    }

    /**
     * Creates a new user record with validation and password hashing.
     *
     * @param array<string, mixed> $data
     * @return int New user ID
     */
    public function create(array $data): int
    {
        $firstName = trim((string)($data['first_name'] ?? ''));
        $lastName  = trim((string)($data['last_name'] ?? ''));
        $email     = strtolower(trim((string)($data['email'] ?? '')));
        $password  = (string)($data['password'] ?? '');
        $roleId    = (int)($data['role_id'] ?? 0);
        $avatar    = !empty($data['avatar_path']) ? trim((string)$data['avatar_path']) : null;
        $isActive  = array_key_exists('is_active', $data) ? (int)(bool)$data['is_active'] : 1;

        // Super Admin accounts must always be active
        $roleRepo = new RoleRepository();
        $targetRole = $roleRepo->getById($roleId);
        if (!empty($targetRole['is_super'])) {
            $isActive = 1;
        }

        if ($firstName === '' || $lastName === '') {
            throw new InvalidArgumentException('First and last name are required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email address is required.');
        }

        if ($this->findByEmail($email) !== null) {
            throw new InvalidArgumentException("A user with the email '{$email}' already exists.");
        }

        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters in length.');
        }

        if ($roleId <= 0) {
            throw new InvalidArgumentException('A valid role must be assigned.');
        }

        $passwordHash = Auth::hashPassword($password);

        $sql = "
            INSERT INTO `users` (
                `role_id`, `first_name`, `last_name`, `email`, `password_hash`, `avatar_path`, `is_active`
            ) VALUES (
                :role_id, :first_name, :last_name, :email, :password_hash, :avatar_path, :is_active
            )
        ";

        Database::execute($sql, [
            ':role_id'       => $roleId,
            ':first_name'    => $firstName,
            ':last_name'     => $lastName,
            ':email'         => $email,
            ':password_hash' => $passwordHash,
            ':avatar_path'   => $avatar,
            ':is_active'     => $isActive,
        ]);

        return (int)Database::lastInsertId();
    }

    /**
     * Updates an existing user record.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $user = $this->getById($id);
        if (!$user) {
            throw new RuntimeException("User with ID {$id} not found.");
        }

        $firstName = trim((string)($data['first_name'] ?? $user['first_name']));
        $lastName  = trim((string)($data['last_name'] ?? $user['last_name']));
        $email     = strtolower(trim((string)($data['email'] ?? $user['email'])));
        $roleId    = isset($data['role_id']) ? (int)$data['role_id'] : (int)$user['role_id'];
        $avatar    = array_key_exists('avatar_path', $data) ? $data['avatar_path'] : $user['avatar_path'];
        $isActive  = array_key_exists('is_active', $data) ? (int)(bool)$data['is_active'] : (int)$user['is_active'];

        // Protect Super Admin accounts from deactivation
        $roleRepo = new RoleRepository();
        $targetRole = $roleRepo->getById($roleId);
        $isSuper = ($id === 1) || !empty($user['is_super']) || (!empty($targetRole) && !empty($targetRole['is_super']));
        if ($isSuper) {
            $isActive = 1;
        }

        // Protect primary Super Admin (ID 1) from role alteration
        if ($id === 1) {
            $roleId = 1;
        }

        // Email uniqueness check
        $existing = $this->findByEmail($email);
        if ($existing && (int)$existing['id'] !== $id) {
            throw new InvalidArgumentException("Email '{$email}' is already in use by another user.");
        }

        $params = [
            ':id'         => $id,
            ':role_id'    => $roleId,
            ':first_name' => $firstName,
            ':last_name'  => $lastName,
            ':email'      => $email,
            ':avatar_path'=> $avatar,
            ':is_active'  => $isActive,
        ];

        // If a new password is provided, rehash and update it
        $passwordSql = '';
        if (!empty($data['password'])) {
            $newPassword = (string)$data['password'];
            if (strlen($newPassword) < 8) {
                throw new InvalidArgumentException('New password must be at least 8 characters in length.');
            }
            $passwordSql = ", `password_hash` = :password_hash";
            $params[':password_hash'] = Auth::hashPassword($newPassword);
        }

        $sql = "
            UPDATE `users` SET 
                `role_id`     = :role_id,
                `first_name`  = :first_name,
                `last_name`   = :last_name,
                `email`       = :email,
                `avatar_path` = :avatar_path,
                `is_active`   = :is_active
                {$passwordSql}
            WHERE `id` = :id
        ";

        return Database::execute($sql, $params) > 0;
    }

    /**
     * Toggles active state of a user.
     *
     * @param int $id
     * @return bool
     */
    public function toggleActive(int $id): bool
    {
        $user = $this->getById($id);
        if (!$user) {
            throw new RuntimeException("User with ID {$id} not found.");
        }

        if ($id === 1 || !empty($user['is_super'])) {
            throw new RuntimeException('Super Admin accounts cannot be deactivated.');
        }

        $sql = "UPDATE `users` SET `is_active` = IF(`is_active` = 1, 0, 1) WHERE `id` = :id";
        return Database::execute($sql, [':id' => $id]) > 0;
    }

    /**
     * Deletes a user record.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $user = $this->getById($id);
        if (!$user) {
            throw new RuntimeException("User with ID {$id} not found.");
        }

        if ($id === 1 || !empty($user['is_super'])) {
            throw new RuntimeException('Super Admin accounts cannot be deleted.');
        }

        $sql = "DELETE FROM `users` WHERE `id` = :id";
        return Database::execute($sql, [':id' => $id]) > 0;
    }
}
