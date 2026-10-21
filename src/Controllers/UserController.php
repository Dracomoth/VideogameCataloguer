<?php
/**
 * src/Controllers/UserController.php
 * Controller handling user management, RBAC enforcement, and profile administration.
 */

declare(strict_types=1);

namespace Vault\Controllers;

use Vault\Auth\Auth;
use Vault\Repositories\RoleRepository;
use Vault\Repositories\UserRepository;
use Vault\Services\Response;
use Vault\Services\View;
use Throwable;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class UserController
{
    private UserRepository $userRepo;
    private RoleRepository $roleRepo;

    public function __construct()
    {
        $this->userRepo = new UserRepository();
        $this->roleRepo = new RoleRepository();
    }

    /**
     * Displays the user management list and workbench view.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function index(array $request): void
    {
        Auth::requireAccess('users', 'read');

        $users = $this->userRepo->getAll();
        $roles = $this->roleRepo->getAll();
        $canWrite = Auth::canWrite('users');

        $flashMessage = $_SESSION['flash_message'] ?? null;
        $flashError   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_message'], $_SESSION['flash_error']);

        $viewData = [
            'pageTitle'    => 'User Management',
            'activeNav'    => 'users',
            'users'        => $users,
            'roles'        => $roles,
            'canWrite'     => $canWrite,
            'flashMessage' => $flashMessage,
            'flashError'   => $flashError,
        ];

        $html = View::render('users', $viewData, 'layout');
        Response::html($html);
    }

    /**
     * Handles the creation of a new user.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function store(array $request): void
    {
        Auth::requireAccess('users', 'write');

        $body = $request['body'] ?? [];
        if (!isset($body['is_active']) && isset($body['email'])) {
            $body['is_active'] = 0;
        }

        try {
            $this->userRepo->create($body);
            $_SESSION['flash_message'] = 'User successfully created.';

            if ($this->wantsJson($request)) {
                Response::json(['success' => true, 'message' => 'User created successfully.']);
            }
            Response::redirect('/users');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/users');
        }
    }

    /**
     * Updates an existing user record.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function update(array $request): void
    {
        Auth::requireAccess('users', 'write');

        $id = (int)($request['params']['id'] ?? ($request['body']['id'] ?? 0));
        $body = $request['body'] ?? [];
        if (!isset($body['is_active']) && isset($body['email'])) {
            $body['is_active'] = 0;
        }

        try {
            $this->userRepo->update($id, $body);
            $_SESSION['flash_message'] = 'User successfully updated.';

            if ($this->wantsJson($request)) {
                Response::json(['success' => true, 'message' => 'User updated successfully.']);
            }
            Response::redirect('/users');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/users');
        }
    }

    /**
     * Toggles the active status of a user.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function toggle(array $request): void
    {
        Auth::requireAccess('users', 'write');

        $id = (int)($request['params']['id'] ?? ($request['body']['id'] ?? 0));

        try {
            $this->userRepo->toggleActive($id);
            $_SESSION['flash_message'] = 'User status updated.';

            if ($this->wantsJson($request)) {
                Response::json(['success' => true]);
            }
            Response::redirect('/users');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/users');
        }
    }

    /**
     * Deletes a user record.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function delete(array $request): void
    {
        Auth::requireAccess('users', 'write');

        $id = (int)($request['params']['id'] ?? ($request['body']['id'] ?? 0));

        try {
            $this->userRepo->delete($id);
            $_SESSION['flash_message'] = 'User deleted.';

            if ($this->wantsJson($request)) {
                Response::json(['success' => true]);
            }
            Response::redirect('/users');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/users');
        }
    }

    /**
     * Returns JSON profile data for the authenticated user.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function getProfile(array $request): void
    {
        if (!Auth::check()) {
            Response::error('Authentication required.', 401);
        }

        $userId = (int)Auth::id();
        $user = $this->userRepo->getById($userId);
        if (!$user) {
            Response::error('User profile not found.', 404);
        }

        $avatarUrl = !empty($user['avatar_path'])
            ? View::assetUrl((string)$user['avatar_path'], $user['updated_at'] ?? null)
            : null;

        $first = trim((string)($user['first_name'] ?? ''));
        $last  = trim((string)($user['last_name'] ?? ''));
        $initials = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
        if ($initials === '') {
            $initials = 'U';
        }

        $user['avatar_url'] = $avatarUrl;
        $user['initials']   = $initials;
        unset($user['password_hash']);

        Response::json([
            'user' => $user,
        ]);
    }

    /**
     * Updates profile attributes and avatar for the authenticated user.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function updateProfile(array $request): void
    {
        if (!Auth::check()) {
            Response::error('Authentication required.', 401);
        }

        $userId = (int)Auth::id();
        $body = $request['body'] ?? [];
        $avatarFile = $request['files']['avatar'] ?? null;

        try {
            $updatedUser = $this->userRepo->updateProfile($userId, $body, $avatarFile);
            Auth::refreshSession($userId);

            $avatarUrl = !empty($updatedUser['avatar_path'])
                ? View::assetUrl((string)$updatedUser['avatar_path'], $updatedUser['updated_at'] ?? null)
                : null;

            $first = trim((string)($updatedUser['first_name'] ?? ''));
            $last  = trim((string)($updatedUser['last_name'] ?? ''));
            $initials = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
            if ($initials === '') {
                $initials = 'U';
            }

            $updatedUser['avatar_url'] = $avatarUrl;
            $updatedUser['initials']   = $initials;
            unset($updatedUser['password_hash']);

            Response::json([
                'message' => 'Profile updated successfully.',
                'user'    => $updatedUser,
            ]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 422);
        }
    }

    /**
     * Determines whether the incoming request expects a JSON response.
     */
    private function wantsJson(array $request): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        return str_contains($accept, 'application/json') || str_contains($contentType, 'application/json');
    }
}
