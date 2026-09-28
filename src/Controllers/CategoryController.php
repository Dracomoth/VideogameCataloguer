<?php
/**
 * src/Controllers/CategoryController.php
 * Controller managing genre categories maintenance, grid datasets, and mutations.
 */

declare(strict_types=1);

namespace Vault\Controllers;

use Vault\Auth\Auth;
use Vault\Repositories\CategoryRepository;
use Vault\Services\Response;
use Vault\Services\View;
use Throwable;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class CategoryController
{
    private CategoryRepository $repo;

    public function __construct()
    {
        $this->repo = new CategoryRepository();
    }

    /**
     * Displays the categories taxonomy management view.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function index(array $request): void
    {
        Auth::requireAccess('categories', 'read');

        $categories = $this->repo->getAll();
        $canWrite   = Auth::canWrite('categories');

        $flashMessage = $_SESSION['flash_message'] ?? null;
        $flashError   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_message'], $_SESSION['flash_error']);

        $viewData = [
            'pageTitle'    => 'Categories Maintenance',
            'activeNav'    => 'categories',
            'categories'   => $categories,
            'canWrite'     => $canWrite,
            'flashMessage' => $flashMessage,
            'flashError'   => $flashError,
        ];

        $html = View::render('categories', $viewData, 'layout');
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
        Auth::requireAccess('categories', 'read');

        $categories = $this->repo->getAll();
        Response::json([
            'categories' => $categories,
            'can_write'  => Auth::canWrite('categories'),
        ]);
    }

    /**
     * Creates a new category record.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function create(array $request): void
    {
        Auth::requireAccess('categories', 'write');

        $body = $request['body'] ?? [];
        $name = (string)($body['name'] ?? ($body['category'] ?? ''));

        try {
            $newId = $this->repo->create($name);
            $msg = "Category '{$name}' created successfully.";

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $newId,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/categories');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/categories');
        }
    }

    /**
     * Updates an existing category record.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function update(array $request): void
    {
        Auth::requireAccess('categories', 'write');

        $body = $request['body'] ?? [];
        $id   = (int)($request['params']['id'] ?? ($body['id'] ?? 0));
        $name = (string)($body['name'] ?? ($body['category'] ?? ''));

        try {
            $this->repo->update($id, $name);
            $msg = "Category updated successfully.";

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $id,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/categories');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/categories');
        }
    }

    /**
     * Deletes a category record if unreferenced by games and subcategories.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function delete(array $request): void
    {
        Auth::requireAccess('categories', 'write');

        $id = (int)($request['params']['id'] ?? ($request['body']['id'] ?? 0));

        try {
            $this->repo->delete($id);
            $msg = 'Category deleted successfully.';

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $id,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/categories');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/categories');
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
