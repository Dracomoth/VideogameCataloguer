<?php
/**
 * src/Controllers/PublisherController.php
 * Controller managing game publishers, hardware manufacturer classifications, and mutations.
 */

declare(strict_types=1);

namespace Vault\Controllers;

use Vault\Auth\Auth;
use Vault\Repositories\PublisherRepository;
use Vault\Services\Response;
use Vault\Services\View;
use Throwable;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class PublisherController
{
    private PublisherRepository $repo;

    public function __construct()
    {
        $this->repo = new PublisherRepository();
    }

    /**
     * Displays the publishers taxonomy and hardware classification view.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function index(array $request): void
    {
        Auth::requireAccess('publishers', 'read');

        $publishers = $this->repo->getAll();
        $canWrite   = Auth::canWrite('publishers');

        $flashMessage = $_SESSION['flash_message'] ?? null;
        $flashError   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_message'], $_SESSION['flash_error']);

        $viewData = [
            'pageTitle'    => 'Publishers Maintenance',
            'activeNav'    => 'publishers',
            'publishers'   => $publishers,
            'canWrite'     => $canWrite,
            'flashMessage' => $flashMessage,
            'flashError'   => $flashError,
        ];

        $html = View::render('publishers', $viewData, 'layout');
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
        Auth::requireAccess('publishers', 'read');

        $publishers = $this->repo->getAll();
        Response::json([
            'publishers' => $publishers,
            'can_write'  => Auth::canWrite('publishers'),
        ]);
    }

    /**
     * Creates a new publisher record.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function create(array $request): void
    {
        Auth::requireAccess('publishers', 'write');

        $body           = $request['body'] ?? [];
        $name           = (string)($body['name'] ?? ($body['publisher'] ?? ''));
        $isConsoleMaker = !empty($body['is_console_maker']) && in_array($body['is_console_maker'], [1, '1', true, 'true', 'on'], true);

        try {
            $newId = $this->repo->create($name, $isConsoleMaker);
            $msg = "Publisher '{$name}' created successfully.";

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $newId,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/publishers');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/publishers');
        }
    }

    /**
     * Updates an existing publisher record.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function update(array $request): void
    {
        Auth::requireAccess('publishers', 'write');

        $body           = $request['body'] ?? [];
        $id             = (int)($request['params']['id'] ?? ($body['id'] ?? 0));
        $name           = (string)($body['name'] ?? ($body['publisher'] ?? ''));
        $isConsoleMaker = !empty($body['is_console_maker']) && in_array($body['is_console_maker'], [1, '1', true, 'true', 'on'], true);

        try {
            $this->repo->update($id, $name, $isConsoleMaker);
            $msg = "Publisher updated successfully.";

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $id,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/publishers');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/publishers');
        }
    }

    /**
     * Deletes a publisher record if unreferenced by games and consoles.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function delete(array $request): void
    {
        Auth::requireAccess('publishers', 'write');

        $id = (int)($request['params']['id'] ?? ($request['body']['id'] ?? 0));

        try {
            $this->repo->delete($id);
            $msg = 'Publisher deleted successfully.';

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $id,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/publishers');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/publishers');
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
