<?php
/**
 * src/Controllers/GameController.php
 * Controller managing game catalog records, taxonomy relations, and visual media assets.
 */

declare(strict_types=1);

namespace Vault\Controllers;

use Vault\Auth\Auth;
use Vault\Repositories\GameRepository;
use Vault\Repositories\ConsoleRepository;
use Vault\Repositories\CategoryRepository;
use Vault\Repositories\SubcategoryRepository;
use Vault\Repositories\PublisherRepository;
use Vault\Repositories\LanguageRepository;
use Vault\Services\Response;
use Vault\Services\View;
use Throwable;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class GameController
{
    private GameRepository $repo;
    private ConsoleRepository $consoleRepo;
    private CategoryRepository $categoryRepo;
    private SubcategoryRepository $subcategoryRepo;
    private PublisherRepository $publisherRepo;
    private LanguageRepository $languageRepo;

    public function __construct()
    {
        $this->repo = new GameRepository();
        $this->consoleRepo = new ConsoleRepository();
        $this->categoryRepo = new CategoryRepository();
        $this->subcategoryRepo = new SubcategoryRepository();
        $this->publisherRepo = new PublisherRepository();
        $this->languageRepo = new LanguageRepository();
    }

    /**
     * Displays the Games Cataloguer & Asset Hub workbench view.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function index(array $request): void
    {
        Auth::requireAccess('games', 'read');

        $games         = $this->repo->getAll();
        $consoles      = $this->consoleRepo->getAll();
        $categories    = $this->categoryRepo->getAll();
        $subcategories = $this->subcategoryRepo->getAll();
        $publishers    = $this->publisherRepo->getAll();
        $languages     = $this->languageRepo->getAll();
        $canWrite      = Auth::canWrite('games');

        $flashMessage = $_SESSION['flash_message'] ?? null;
        $flashError   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_message'], $_SESSION['flash_error']);

        $viewData = [
            'pageTitle'     => 'Games Cataloguer',
            'activeNav'     => 'games',
            'games'         => $games,
            'consoles'      => $consoles,
            'categories'    => $categories,
            'subcategories' => $subcategories,
            'publishers'    => $publishers,
            'languages'     => $languages,
            'canWrite'      => $canWrite,
            'flashMessage'  => $flashMessage,
            'flashError'    => $flashError,
        ];

        $html = View::render('games', $viewData, 'layout');
        Response::html($html);
    }

    /**
     * Supplies JSON dataset for the <data-grid> component and asynchronous search.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function apiList(array $request): void
    {
        Auth::requireAccess('games', 'read');

        $games         = $this->repo->getAll();
        $consoles      = $this->consoleRepo->getAll();
        $categories    = $this->categoryRepo->getAll();
        $subcategories = $this->subcategoryRepo->getAll();
        $publishers    = $this->publisherRepo->getAll();
        $languages     = $this->languageRepo->getAll();

        Response::json([
            'games'         => $games,
            'consoles'      => $consoles,
            'categories'    => $categories,
            'subcategories' => $subcategories,
            'publishers'    => $publishers,
            'languages'     => $languages,
            'can_write'     => Auth::canWrite('games'),
        ]);
    }

    /**
     * Creates a new game record with optional visual assets.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function create(array $request): void
    {
        Auth::requireAccess('games', 'write');

        $body  = $request['body'] ?? [];
        $files = $request['files'] ?? [];
        $title = trim((string)($body['title'] ?? ($body['game'] ?? '')));

        try {
            $newId = $this->repo->create($body, $files);
            $msg = "Game '{$title}' created successfully.";

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $newId,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/games');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/games');
        }
    }

    /**
     * Updates an existing game record and reconciles its visual assets.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function update(array $request): void
    {
        Auth::requireAccess('games', 'write');

        $body  = $request['body'] ?? [];
        $files = $request['files'] ?? [];
        $id    = (int)($request['params']['id'] ?? ($body['id'] ?? 0));
        $title = trim((string)($body['title'] ?? ($body['game'] ?? '')));

        try {
            $this->repo->update($id, $body, $files);
            $msg = "Game #{$id} updated successfully.";

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $id,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/games');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/games');
        }
    }

    /**
     * Deletes a game record and removes its associated visual assets from disk.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function delete(array $request): void
    {
        Auth::requireAccess('games', 'write');

        $id = (int)($request['params']['id'] ?? ($request['body']['id'] ?? 0));

        try {
            $this->repo->delete($id);
            $msg = "Game #{$id} and its associated images were deleted.";

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $id,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/games');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/games');
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
