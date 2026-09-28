<?php
/**
 * src/Controllers/RoleController.php
 * Controller managing custom roles, screen registry, and dual-device permission matrix configuration.
 */

declare(strict_types=1);

namespace Vault\Controllers;

use Vault\Auth\Auth;
use Vault\Repositories\RoleRepository;
use Vault\Services\Response;
use Vault\Services\View;
use Throwable;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class RoleController
{
    private RoleRepository $repo;

    public function __construct()
    {
        $this->repo = new RoleRepository();
    }

    /**
     * Displays the role management workbench and dual-device permissions grid.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function index(array $request): void
    {
        Auth::requireAccess('roles', 'read');

        $roles = $this->repo->getAll();
        $canWrite = Auth::canWrite('roles');

        // Determine currently inspected role
        $selectedId = (int)($request['query']['role_id'] ?? 0);
        if ($selectedId <= 0 && !empty($roles)) {
            // Default to first non-super role if available, otherwise first role
            $selectedId = (int)$roles[0]['id'];
            foreach ($roles as $r) {
                if (empty($r['is_super'])) {
                    $selectedId = (int)$r['id'];
                    break;
                }
            }
        }

        $selectedRole = $this->repo->getById($selectedId);
        $screensWithPermissions = $selectedRole ? $this->repo->getScreensWithPermissions($selectedId) : [];

        $flashMessage = $_SESSION['flash_message'] ?? null;
        $flashError   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_message'], $_SESSION['flash_error']);

        $viewData = [
            'pageTitle'              => 'Roles & Permissions',
            'activeNav'              => 'roles',
            'roles'                  => $roles,
            'selectedId'             => $selectedId,
            'selectedRole'           => $selectedRole,
            'screensWithPermissions' => $screensWithPermissions,
            'canWrite'               => $canWrite,
            'flashMessage'           => $flashMessage,
            'flashError'             => $flashError,
        ];

        $html = View::render('roles', $viewData, 'layout');
        Response::html($html);
    }

    /**
     * API endpoint returning dual-device permissions matrix for a requested role.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function matrix(array $request): void
    {
        Auth::requireAccess('roles', 'read');

        $roleId = (int)($request['params']['id'] ?? ($request['query']['role_id'] ?? 0));
        $role = $this->repo->getById($roleId);

        if (!$role) {
            Response::json(['error' => 'Role not found'], 404);
        }

        $screens = $this->repo->getScreensWithPermissions($roleId);
        Response::json([
            'role'    => $role,
            'screens' => $screens,
        ]);
    }

    /**
     * Creates a new role and persists its initial dual-device permissions.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function store(array $request): void
    {
        Auth::requireAccess('roles', 'write');

        $body = $request['body'] ?? [];

        try {
            $roleId = $this->repo->create($body);

            // Save permissions matrix if submitted alongside role
            $permissions = $body['permissions'] ?? [];
            if (is_array($permissions) && !empty($permissions)) {
                $this->repo->savePermissions($roleId, $permissions);
            }

            $_SESSION['flash_message'] = 'Role created successfully.';

            if ($this->wantsJson($request)) {
                Response::json(['success' => true, 'role_id' => $roleId]);
            }
            Response::redirect("/roles?role_id={$roleId}");
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/roles');
        }
    }

    /**
     * Updates role details and saves the dual-device permissions matrix.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function update(array $request): void
    {
        Auth::requireAccess('roles', 'write');

        $id = (int)($request['params']['id'] ?? ($request['body']['id'] ?? 0));
        $body = $request['body'] ?? [];

        try {
            $this->repo->update($id, $body);

            // Save permissions matrix
            $permissions = $body['permissions'] ?? [];
            if (is_array($permissions)) {
                $this->repo->savePermissions($id, $permissions);
            }

            $_SESSION['flash_message'] = 'Role and dual-device permissions saved successfully.';

            if ($this->wantsJson($request)) {
                Response::json(['success' => true]);
            }
            Response::redirect("/roles?role_id={$id}");
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect("/roles?role_id={$id}");
        }
    }

    /**
     * Deletes a role.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function delete(array $request): void
    {
        Auth::requireAccess('roles', 'write');

        $id = (int)($request['params']['id'] ?? ($request['body']['id'] ?? 0));

        try {
            $this->repo->delete($id);
            $_SESSION['flash_message'] = 'Role deleted successfully.';

            if ($this->wantsJson($request)) {
                Response::json(['success' => true]);
            }
            Response::redirect('/roles');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/roles');
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
