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
     * Determines whether the incoming request expects a JSON response.
     */
    private function wantsJson(array $request): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        return str_contains($accept, 'application/json') || str_contains($contentType, 'application/json');
    }
}
