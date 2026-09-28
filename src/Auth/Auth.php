<?php
/**
 * src/Auth/Auth.php
 * Central Authentication, Session, and Dual-Device RBAC Authorization Service.
 */

declare(strict_types=1);

namespace Vault\Auth;

use Vault\Services\Database;
use Vault\Services\Response;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class Auth
{
    private const SESSION_KEY = 'vault_user';
    private static ?array $currentUser = null;
    private static ?array $permissionsCache = null;

    /**
     * Initializes the secure PHP session.
     */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

            session_set_cookie_params([
                'lifetime' => 86400 * 7, // 7 days
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            session_name('vault_session');
            session_start();
        }
    }

    /**
     * Attempts authentication with email and plaintext password.
     */
    public static function attempt(string $email, string $password): bool
    {
        self::startSession();

        $user = Database::fetchOne(
            "SELECT u.*, r.name AS role_name, r.is_super 
             FROM `users` u 
             INNER JOIN `roles` r ON r.id = u.role_id 
             WHERE u.email = :email AND u.is_active = 1 
             LIMIT 1",
            [':email' => trim($email)]
        );

        if (!$user) {
            return false;
        }

        if (!password_verify($password, (string)$user['password_hash'])) {
            return false;
        }

        // Update last login timestamp
        Database::execute(
            "UPDATE `users` SET `last_login_at` = NOW() WHERE `id` = :id",
            [':id' => (int)$user['id']]
        );

        self::login($user);
        return true;
    }

    /**
     * Logs in a user array and establishes session state.
     *
     * @param array<string, mixed> $user
     */
    public static function login(array $user): void
    {
        self::startSession();
        session_regenerate_id(true);

        $_SESSION[self::SESSION_KEY] = [
            'id'         => (int)$user['id'],
            'role_id'    => (int)$user['role_id'],
            'role_name'  => (string)($user['role_name'] ?? 'User'),
            'first_name' => (string)$user['first_name'],
            'last_name'  => (string)$user['last_name'],
            'email'      => (string)$user['email'],
            'avatar_path'=> $user['avatar_path'] ?? null,
            'is_super'   => (bool)($user['is_super'] ?? false),
        ];

        self::$currentUser = $_SESSION[self::SESSION_KEY];
        self::$permissionsCache = null;
    }

    /**
     * Destroys current session and logs out.
     */
    public static function logout(): void
    {
        self::startSession();
        $_SESSION[self::SESSION_KEY] = null;
        unset($_SESSION[self::SESSION_KEY]);

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        self::$currentUser = null;
        self::$permissionsCache = null;
    }

    /**
     * Checks if a user is currently authenticated.
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Retrieves current logged in user session data.
     *
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        self::startSession();

        if (self::$currentUser !== null) {
            return self::$currentUser;
        }

        if (!empty($_SESSION[self::SESSION_KEY])) {
            self::$currentUser = $_SESSION[self::SESSION_KEY];
            return self::$currentUser;
        }

        return null;
    }

    /**
     * Current user ID or null.
     */
    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int)$u['id'] : null;
    }

    /**
     * Current role ID or null.
     */
    public static function roleId(): ?int
    {
        $u = self::user();
        return $u ? (int)$u['role_id'] : null;
    }

    /**
     * Checks if the active user is a Super Admin (full system bypass).
     */
    public static function isSuper(): bool
    {
        $u = self::user();
        return $u !== null && !empty($u['is_super']);
    }

    /**
     * Detects if the current client is a desktop/PC or another device (mobile/tablet/handheld).
     */
    public static function isPc(): bool
    {
        return self::deviceType() === 'pc';
    }

    /**
     * Returns 'pc' or 'other' based on User-Agent inspection.
     */
    public static function deviceType(): string
    {
        // Allow manual testing override via query parameter or session if needed
        if (isset($_GET['device']) && in_array($_GET['device'], ['pc', 'other'], true)) {
            $_SESSION['vault_device_override'] = $_GET['device'];
        }
        if (!empty($_SESSION['vault_device_override'])) {
            return (string)$_SESSION['vault_device_override'];
        }

        $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($ua === '') {
            return 'pc';
        }

        // Keywords indicating mobile, handheld, or tablet devices
        $mobileKeywords = [
            'mobile', 'android', 'iphone', 'ipad', 'ipod', 'blackberry',
            'silk', 'kindle', 'opera mini', 'windows phone', 'touch', 'switch'
        ];

        foreach ($mobileKeywords as $keyword) {
            if (str_contains($ua, $keyword)) {
                return 'other';
            }
        }

        return 'pc';
    }

    /**
     * Evaluates permission for a specific screen based on active user role and device type.
     *
     * @param string $screen Screen key (e.g., 'games', 'consoles', 'users')
     * @param string $requiredLevel 'read' or 'write'
     * @return bool
     */
    public static function can(string $screen, string $requiredLevel = 'read'): bool
    {
        // 1. Unauthenticated users have no access
        if (!self::check()) {
            return false;
        }

        // 2. Super User bypasses all permission checks with full write access
        if (self::isSuper()) {
            return true;
        }

        $roleId = self::roleId();
        if ($roleId === null) {
            return false;
        }

        $permissions = self::loadRolePermissions($roleId);
        $deviceCol = self::isPc() ? 'access_pc' : 'access_other';

        $level = $permissions[$screen][$deviceCol] ?? 'none';

        if ($level === 'none') {
            return false;
        }

        if ($requiredLevel === 'read') {
            return $level === 'read' || $level === 'write';
        }

        if ($requiredLevel === 'write') {
            return $level === 'write';
        }

        return false;
    }

    /**
     * Checks if the user has write/edit access on a screen for the active device.
     */
    public static function canWrite(string $screen): bool
    {
        return self::can($screen, 'write');
    }

    /**
     * Checks if the user has read/navigation access on a screen for the active device.
     */
    public static function canRead(string $screen): bool
    {
        return self::can($screen, 'read');
    }

    /**
     * Asserts permission on a screen, aborting or redirecting if unauthorized.
     */
    public static function requireAccess(string $screen, string $requiredLevel = 'read'): void
    {
        if (!self::check()) {
            Response::redirect('/login');
        }

        if (!self::can($screen, $requiredLevel)) {
            Response::error("Access Denied: You do not have {$requiredLevel} permissions for this resource on this device.", 403);
        }
    }

    /**
     * Generates a standard salted password hash.
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Loads and caches role permissions matrix from the database.
     *
     * @return array<string, array{access_pc: string, access_other: string}>
     */
    private static function loadRolePermissions(int $roleId): array
    {
        if (self::$permissionsCache !== null) {
            return self::$permissionsCache;
        }

        $rows = Database::fetchAll(
            "SELECT `screen_key`, `access_pc`, `access_other` 
             FROM `role_permissions` 
             WHERE `role_id` = :role_id",
            [':role_id' => $roleId]
        );

        $matrix = [];
        foreach ($rows as $row) {
            $matrix[$row['screen_key']] = [
                'access_pc'    => $row['access_pc'],
                'access_other' => $row['access_other'],
            ];
        }

        self::$permissionsCache = $matrix;
        return self::$permissionsCache;
    }
}
