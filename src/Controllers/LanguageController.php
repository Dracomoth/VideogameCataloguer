<?php
/**
 * src/Controllers/LanguageController.php
 * Controller managing taxonomy language maintenance, grid datasets, and mutations.
 */

declare(strict_types=1);

namespace Vault\Controllers;

use Vault\Auth\Auth;
use Vault\Repositories\LanguageRepository;
use Vault\Services\Response;
use Vault\Services\View;
use Throwable;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class LanguageController
{
    private LanguageRepository $repo;

    public function __construct()
    {
        $this->repo = new LanguageRepository();
    }

    /**
     * Displays the languages taxonomy management view.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function index(array $request): void
    {
        Auth::requireAccess('languages', 'read');

        $languages = $this->repo->getAll();
        $canWrite  = Auth::canWrite('languages');

        $flashMessage = $_SESSION['flash_message'] ?? null;
        $flashError   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_message'], $_SESSION['flash_error']);

        $viewData = [
            'pageTitle'    => 'Languages & Regions',
            'activeNav'    => 'languages',
            'languages'    => $languages,
            'canWrite'     => $canWrite,
            'flashMessage' => $flashMessage,
            'flashError'   => $flashError,
        ];

        $html = View::render('languages', $viewData, 'layout');
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
        Auth::requireAccess('languages', 'read');

        $languages = $this->repo->getAll();
        Response::json([
            'languages' => $languages,
            'can_write' => Auth::canWrite('languages'),
        ]);
    }

    /**
     * Creates a new language record.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function create(array $request): void
    {
        Auth::requireAccess('languages', 'write');

        $body = $request['body'] ?? [];
        $name = (string)($body['name'] ?? ($body['language'] ?? ''));

        try {
            $newId = $this->repo->create($name);
            $_SESSION['flash_message'] = "Language '{$name}' created successfully.";

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $newId,
                    'message' => "Language '{$name}' created successfully.",
                ]);
            }
            Response::redirect('/languages');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/languages');
        }
    }

    /**
     * Updates an existing language record.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function update(array $request): void
    {
        Auth::requireAccess('languages', 'write');

        $body = $request['body'] ?? [];
        $id   = (int)($request['params']['id'] ?? ($body['id'] ?? 0));
        $name = (string)($body['name'] ?? ($body['language'] ?? ''));

        try {
            $this->repo->update($id, $name);
            $_SESSION['flash_message'] = "Language updated successfully.";

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $id,
                    'message' => "Language updated successfully.",
                ]);
            }
            Response::redirect('/languages');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/languages');
        }
    }

    /**
     * Deletes a language record if unreferenced by games.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function delete(array $request): void
    {
        Auth::requireAccess('languages', 'write');

        $id = (int)($request['params']['id'] ?? ($request['body']['id'] ?? 0));

        try {
            $this->repo->delete($id);
            $_SESSION['flash_message'] = 'Language deleted successfully.';

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $id,
                    'message' => 'Language deleted successfully.',
                ]);
            }
            Response::redirect('/languages');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/languages');
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
