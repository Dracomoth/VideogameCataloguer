<?php
/**
 * src/Controllers/SubcategoryController.php
 * Controller managing subcategories taxonomy, parent category relations, and mutations.
 */

declare(strict_types=1);

namespace Vault\Controllers;

use Vault\Auth\Auth;
use Vault\Repositories\CategoryRepository;
use Vault\Repositories\SubcategoryRepository;
use Vault\Services\Response;
use Vault\Services\View;
use Throwable;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class SubcategoryController
{
    private SubcategoryRepository $repo;
    private CategoryRepository $categoryRepo;

    public function __construct()
    {
        $this->repo = new SubcategoryRepository();
        $this->categoryRepo = new CategoryRepository();
    }

    /**
     * Displays the subcategories taxonomy management view.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function index(array $request): void
    {
        Auth::requireAccess('subcategories', 'read');

        $subcategories = $this->repo->getAll();
        $categories    = $this->categoryRepo->getAll();
        $canWrite      = Auth::canWrite('subcategories');

        $flashMessage = $_SESSION['flash_message'] ?? null;
        $flashError   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_message'], $_SESSION['flash_error']);

        $viewData = [
            'pageTitle'     => 'Subcategories Maintenance',
            'activeNav'     => 'subcategories',
            'subcategories' => $subcategories,
            'categories'    => $categories,
            'canWrite'      => $canWrite,
            'flashMessage'  => $flashMessage,
            'flashError'    => $flashError,
        ];

        $html = View::render('subcategories', $viewData, 'layout');
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
        Auth::requireAccess('subcategories', 'read');

        $categoryId    = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $subcategories = $this->repo->getAll($categoryId);
        $categories    = $this->categoryRepo->getAll();

        Response::json([
            'subcategories' => $subcategories,
            'categories'    => $categories,
            'can_write'     => Auth::canWrite('subcategories'),
        ]);
    }

    /**
     * Creates a new subcategory record.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function create(array $request): void
    {
        Auth::requireAccess('subcategories', 'write');

        $body       = $request['body'] ?? [];
        $categoryId = (int)($body['category_id'] ?? 0);
        $name       = (string)($body['name'] ?? ($body['subcategory'] ?? ''));

        try {
            $newId = $this->repo->create($categoryId, $name);
            $msg = "Subcategory '{$name}' created successfully.";

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $newId,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/subcategories');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/subcategories');
        }
    }

    /**
     * Updates an existing subcategory record.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function update(array $request): void
    {
        Auth::requireAccess('subcategories', 'write');

        $body       = $request['body'] ?? [];
        $id         = (int)($request['params']['id'] ?? ($body['id'] ?? 0));
        $categoryId = (int)($body['category_id'] ?? 0);
        $name       = (string)($body['name'] ?? ($body['subcategory'] ?? ''));

        try {
            $this->repo->update($id, $categoryId, $name);
            $msg = "Subcategory updated successfully.";

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $id,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/subcategories');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/subcategories');
        }
    }

    /**
     * Deletes a subcategory record if unreferenced by games.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function delete(array $request): void
    {
        Auth::requireAccess('subcategories', 'write');

        $id = (int)($request['params']['id'] ?? ($request['body']['id'] ?? 0));

        try {
            $this->repo->delete($id);
            $msg = 'Subcategory deleted successfully.';

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $id,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/subcategories');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/subcategories');
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
