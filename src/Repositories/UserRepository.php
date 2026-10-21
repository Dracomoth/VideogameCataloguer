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
    private string $avatarDir;

    public function __construct()
    {
        $this->avatarDir = dirname(__DIR__, 2) . '/images/avatars';
        if (!is_dir($this->avatarDir)) {
            @mkdir($this->avatarDir, 0775, true);
        }
    }

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
                u.username,
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
                u.username,
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
     * Looks up an existing user by unique username.
     *
     * @param string $username
     * @return array<string, mixed>|null
     */
    public function findByUsername(string $username): ?array
    {
        $sql = "SELECT * FROM `users` WHERE `username` = :username LIMIT 1";
        return Database::fetchOne($sql, [':username' => trim($username)]);
    }

    /**
     * Creates a new user record with validation and password hashing.
     *
     * @param array<string, mixed> $data
     * @return int New user ID
     */
    public function create(array $data): int
    {
        $username  = trim((string)($data['username'] ?? ''));
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

        if ($username === '') {
            throw new InvalidArgumentException('Username is required.');
        }

        if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
            throw new InvalidArgumentException('Username must be 3-50 characters and contain only letters, numbers, underscores, dashes, or dots.');
        }

        if ($this->findByUsername($username) !== null) {
            throw new InvalidArgumentException("Username '{$username}' is already in use.");
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
                `role_id`, `username`, `first_name`, `last_name`, `email`, `password_hash`, `avatar_path`, `is_active`
            ) VALUES (
                :role_id, :username, :first_name, :last_name, :email, :password_hash, :avatar_path, :is_active
            )
        ";

        Database::execute($sql, [
            ':role_id'       => $roleId,
            ':username'      => $username,
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

        $username  = trim((string)($data['username'] ?? ($user['username'] ?? '')));
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

        if ($username === '') {
            throw new InvalidArgumentException('Username is required.');
        }

        if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
            throw new InvalidArgumentException('Username must be 3-50 characters and contain only letters, numbers, underscores, dashes, or dots.');
        }

        $existingUsername = $this->findByUsername($username);
        if ($existingUsername && (int)$existingUsername['id'] !== $id) {
            throw new InvalidArgumentException("Username '{$username}' is already in use by another user.");
        }

        // Email uniqueness check
        $existing = $this->findByEmail($email);
        if ($existing && (int)$existing['id'] !== $id) {
            throw new InvalidArgumentException("Email '{$email}' is already in use by another user.");
        }

        $params = [
            ':id'         => $id,
            ':role_id'    => $roleId,
            ':username'   => $username,
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
                `username`    = :username,
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

        $this->deleteAvatarFileOnly($id);

        $sql = "DELETE FROM `users` WHERE `id` = :id";
        return Database::execute($sql, [':id' => $id]) > 0;
    }

    /**
     * Uploads and validates an avatar file, saving as images/avatars/<userId>_avatar.<ext>.
     * Returns the relative asset path.
     *
     * @param int $userId
     * @param array<string, mixed> $file
     * @return string
     */
    public function uploadAvatarFileOnly(int $userId, array $file): string
    {
        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Upload failed for avatar with error code {$file['error']}.");
        }

        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
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
            if (in_array($origExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                $ext = ($origExt === 'jpeg') ? 'jpg' : $origExt;
            }
        }

        if ($ext === null) {
            throw new InvalidArgumentException("Invalid file format for avatar. Allowed formats: JPG, PNG, WEBP, GIF.");
        }

        $filename = "{$userId}_avatar.{$ext}";
        $destination = $this->avatarDir . '/' . $filename;

        // Purge any prior avatar files for this user ID regardless of extension
        $this->deleteAvatarFileOnly($userId);

        $saved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $destination)
            : copy($file['tmp_name'], $destination);

        if (!$saved) {
            throw new RuntimeException("Failed to save avatar asset to disk.");
        }

        @chmod($destination, 0664);

        return "images/avatars/{$filename}";
    }

    /**
     * Uploads avatar and immediately updates user record in DB.
     *
     * @param int $userId
     * @param array<string, mixed> $file
     * @return string
     */
    public function uploadAvatar(int $userId, array $file): string
    {
        $path = $this->uploadAvatarFileOnly($userId, $file);
        Database::execute(
            "UPDATE `users` SET `avatar_path` = :avatar_path WHERE `id` = :id",
            [':avatar_path' => $path, ':id' => $userId]
        );
        return $path;
    }

    /**
     * Removes avatar for user and deletes physical file.
     *
     * @param int $userId
     */
    public function removeAvatar(int $userId): void
    {
        $this->deleteAvatarFileOnly($userId);
        Database::execute(
            "UPDATE `users` SET `avatar_path` = NULL WHERE `id` = :id",
            [':id' => $userId]
        );
    }

    /**
     * Purges physical avatar file(s) for a user from disk.
     *
     * @param int $userId
     */
    public function deleteAvatarFileOnly(int $userId): void
    {
        $pattern = $this->avatarDir . '/' . $userId . '_avatar.*';
        $files = glob($pattern);
        if (is_array($files)) {
            foreach ($files as $f) {
                if (is_file($f)) {
                    @unlink($f);
                    clearstatcache(true, $f);
                }
            }
        }
    }

    /**
     * Updates an authenticated user's own profile.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $avatarFile
     * @return array<string, mixed>
     */
    public function updateProfile(int $id, array $data, ?array $avatarFile = null): array
    {
        $user = $this->getById($id);
        if (!$user) {
            throw new RuntimeException("User with ID {$id} not found.");
        }

        $firstName = trim((string)($data['first_name'] ?? $user['first_name']));
        $lastName  = trim((string)($data['last_name'] ?? $user['last_name']));
        $username  = trim((string)($data['username'] ?? $user['username']));
        $email     = strtolower(trim((string)($data['email'] ?? $user['email'])));

        if ($firstName === '' || $lastName === '') {
            throw new InvalidArgumentException('First and last name are required.');
        }

        if ($username === '') {
            throw new InvalidArgumentException('Username is required.');
        }

        if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
            throw new InvalidArgumentException('Username must be 3-50 characters and contain only letters, numbers, underscores, dashes, or dots.');
        }

        $existingUsername = $this->findByUsername($username);
        if ($existingUsername && (int)$existingUsername['id'] !== $id) {
            throw new InvalidArgumentException("Username '{$username}' is already in use by another user.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email address is required.');
        }

        $existingEmail = $this->findByEmail($email);
        if ($existingEmail && (int)$existingEmail['id'] !== $id) {
            throw new InvalidArgumentException("Email '{$email}' is already in use by another user.");
        }

        $params = [
            ':id'         => $id,
            ':first_name' => $firstName,
            ':last_name'  => $lastName,
            ':username'   => $username,
            ':email'      => $email,
        ];

        // Password change handling
        $passwordSql = '';
        if (!empty($data['password'])) {
            $newPassword = (string)$data['password'];
            $confirmPassword = (string)($data['password_confirmation'] ?? '');

            if ($newPassword !== $confirmPassword) {
                throw new InvalidArgumentException('Password confirmation does not match.');
            }

            if (strlen($newPassword) < 8) {
                throw new InvalidArgumentException('New password must be at least 8 characters in length.');
            }

            $passwordSql = ", `password_hash` = :password_hash";
            $params[':password_hash'] = Auth::hashPassword($newPassword);
        }

        // Avatar handling
        $avatarSql = '';
        if (!empty($data['delete_avatar'])) {
            $this->deleteAvatarFileOnly($id);
            $avatarSql = ", `avatar_path` = NULL";
        } elseif ($avatarFile !== null && !empty($avatarFile['tmp_name']) && (!isset($avatarFile['error']) || $avatarFile['error'] === UPLOAD_ERR_OK)) {
            $avatarPath = $this->uploadAvatarFileOnly($id, $avatarFile);
            $avatarSql = ", `avatar_path` = :avatar_path";
            $params[':avatar_path'] = $avatarPath;
        }

        $sql = "
            UPDATE `users` SET 
                `first_name`  = :first_name,
                `last_name`   = :last_name,
                `username`    = :username,
                `email`       = :email,
                `updated_at`  = NOW()
                {$passwordSql}
                {$avatarSql}
            WHERE `id` = :id
        ";

        Database::execute($sql, $params);

        return $this->getById($id) ?? [];
    }
}
