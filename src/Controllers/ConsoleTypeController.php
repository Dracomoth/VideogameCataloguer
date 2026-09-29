<?php
/**
 * src/Controllers/ConsoleTypeController.php
 * Controller managing console hardware types, badge styling, and taxonomy datasets.
 */

declare(strict_types=1);

namespace Vault\Controllers;

use Vault\Auth\Auth;
use Vault\Repositories\ConsoleTypeRepository;
use Vault\Services\Response;
use Vault\Services\View;
use Throwable;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class ConsoleTypeController
{
    private ConsoleTypeRepository $repo;

    public function __construct()
    {
        $this->repo = new ConsoleTypeRepository();
    }

    /**
     * Displays the console types taxonomy management view.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function index(array $request): void
    {
        Auth::requireAccess('console_types', 'read');

        $consoleTypes = $this->repo->getAll();
        $canWrite     = Auth::canWrite('console_types');

        $flashMessage = $_SESSION['flash_message'] ?? null;
        $flashError   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_message'], $_SESSION['flash_error']);

        $viewData = [
            'pageTitle'    => 'Console Types',
            'activeNav'    => 'console_types',
            'consoleTypes' => $consoleTypes,
            'canWrite'     => $canWrite,
            'flashMessage' => $flashMessage,
            'flashError'   => $flashError,
        ];

        $html = View::render('console_types', $viewData, 'layout');
        Response::html($html);
    }

    /**
     * Supplies JSON dataset for the <data-grid> component.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function apiList(array $request): void
    {
        Auth::requireAccess('console_types', 'read');

        $consoleTypes = $this->repo->getAll();
        Response::json([
            'console_types' => $consoleTypes,
            'can_write'     => Auth::canWrite('console_types'),
        ]);
    }

    /**
     * Creates a new console type record.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function create(array $request): void
    {
        Auth::requireAccess('console_types', 'write');

        $body           = $request['body'] ?? [];
        $name           = (string)($body['name'] ?? '');
        $badgeBgColor   = (string)($body['badge_bg_color'] ?? '#1e3a8a');
        $badgeFontColor = (string)($body['badge_font_color'] ?? '#93c5fd');

        try {
            $newId = $this->repo->create($name, $badgeBgColor, $badgeFontColor);
            $msg   = "Console type '{$name}' created successfully.";

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $newId,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/console-types');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/console-types');
        }
    }

    /**
     * Updates an existing console type record.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function update(array $request): void
    {
        Auth::requireAccess('console_types', 'write');

        $body           = $request['body'] ?? [];
        $id             = (int)($request['params']['id'] ?? ($body['id'] ?? 0));
        $name           = (string)($body['name'] ?? '');
        $badgeBgColor   = (string)($body['badge_bg_color'] ?? '#1e3a8a');
        $badgeFontColor = (string)($body['badge_font_color'] ?? '#93c5fd');

        try {
            $this->repo->update($id, $name, $badgeBgColor, $badgeFontColor);
            $msg = "Console type updated successfully.";

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $id,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/console-types');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/console-types');
        }
    }

    /**
     * Deletes a console type record if unreferenced by consoles.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function delete(array $request): void
    {
        Auth::requireAccess('console_types', 'write');

        $id = (int)($request['params']['id'] ?? ($request['body']['id'] ?? 0));

        try {
            $this->repo->delete($id);
            $msg = 'Console type deleted successfully.';

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $id,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/console-types');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/console-types');
        }
    }

    /**
     * Determines whether the incoming request expects a JSON response.
     *
     * @param array<string, mixed> $request
     * @return bool
     */
    private function wantsJson(array $request): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        return str_contains($accept, 'application/json') || str_contains($contentType, 'application/json');
    }
}
