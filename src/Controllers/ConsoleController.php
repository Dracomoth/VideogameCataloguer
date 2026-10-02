<?php
/**
 * src/Controllers/ConsoleController.php
 * Controller managing game consoles, hardware specifications, and visual assets.
 */

declare(strict_types=1);

namespace Vault\Controllers;

use Vault\Auth\Auth;
use Vault\Repositories\ConsoleRepository;
use Vault\Repositories\ConsoleTypeRepository;
use Vault\Repositories\PublisherRepository;
use Vault\Services\Response;
use Vault\Services\View;
use Vault\Services\GeminiService;
use Throwable;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class ConsoleController
{
    private ConsoleRepository $repo;
    private PublisherRepository $publisherRepo;
    private ConsoleTypeRepository $typeRepo;

    public function __construct()
    {
        $this->repo = new ConsoleRepository();
        $this->publisherRepo = new PublisherRepository();
        $this->typeRepo = new ConsoleTypeRepository();
    }

    /**
     * Displays the consoles taxonomy and hardware management view.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function index(array $request): void
    {
        Auth::requireAccess('consoles', 'read');

        $consoles     = $this->repo->getAll();
        $makers       = $this->publisherRepo->getConsoleMakers();
        $consoleTypes = $this->typeRepo->getAll();
        $canWrite     = Auth::canWrite('consoles');

        $flashMessage = $_SESSION['flash_message'] ?? null;
        $flashError   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_message'], $_SESSION['flash_error']);

        $masterConsoles = $this->repo->getPlayableConsoles();

        $viewData = [
            'pageTitle'      => 'Consoles Maintenance',
            'activeNav'      => 'consoles',
            'consoles'       => $consoles,
            'makers'         => $makers,
            'consoleTypes'   => $consoleTypes,
            'masterConsoles' => $masterConsoles,
            'canWrite'       => $canWrite,
            'flashMessage'   => $flashMessage,
            'flashError'     => $flashError,
        ];

        $html = View::render('consoles', $viewData, 'layout');
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
        Auth::requireAccess('consoles', 'read');

        $consoles       = $this->repo->getAll();
        $makers         = $this->publisherRepo->getConsoleMakers();
        $consoleTypes   = $this->typeRepo->getAll();
        $masterConsoles = $this->repo->getPlayableConsoles();

        Response::json([
            'consoles'        => $consoles,
            'makers'          => $makers,
            'console_types'   => $consoleTypes,
            'master_consoles' => $masterConsoles,
            'can_write'       => Auth::canWrite('consoles'),
        ]);
    }

    /**
     * Creates a new console record with visual assets.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function create(array $request): void
    {
        Auth::requireAccess('consoles', 'write');

        $body  = $request['body'] ?? [];
        $files = $request['files'] ?? [];
        $name  = trim((string)($body['name'] ?? ($body['console'] ?? '')));

        try {
            $newId = $this->repo->create($body, $files);
            $msg = "Console '{$name}' created successfully.";

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $newId,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/consoles');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/consoles');
        }
    }

    /**
     * Updates an existing console record.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function update(array $request): void
    {
        Auth::requireAccess('consoles', 'write');

        $body  = $request['body'] ?? [];
        $files = $request['files'] ?? [];
        $id    = (int)($request['params']['id'] ?? ($body['id'] ?? 0));
        $name  = trim((string)($body['name'] ?? ($body['console'] ?? '')));

        try {
            $this->repo->update($id, $body, $files);
            $msg = "Console updated successfully.";

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $id,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/consoles');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/consoles');
        }
    }

    /**
     * Deletes a console record and purges its visual assets.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function delete(array $request): void
    {
        Auth::requireAccess('consoles', 'write');

        $id = (int)($request['params']['id'] ?? ($request['body']['id'] ?? 0));

        try {
            $this->repo->delete($id);
            $msg = 'Console deleted successfully.';

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $id,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/consoles');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/consoles');
        }
    }

    /**
     * Calls Gemini AI to generate technical specifications, hardware overview, and BIOS notes for a console.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function autofillSpecs(array $request): void
    {
        Auth::requireAccess('consoles', 'write');

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        @ini_set('max_execution_time', '120');

        $body = $request['body'] ?? [];
        $name = trim((string)($body['name'] ?? ''));

        if ($name === '') {
            Response::error('Console name is required to generate specifications.', 422);
        }

        // Resolve maker name if manufacturer ID provided
        $makerName = trim((string)($body['maker'] ?? ''));
        $publisherId = (int)($body['publisher_id'] ?? 0);
        if ($makerName === '' && $publisherId > 0) {
            $maker = $this->publisherRepo->getById($publisherId);
            if ($maker !== null) {
                $makerName = (string)($maker['name'] ?? '');
            }
        }

        // Resolve type name if type ID provided
        $typeName = trim((string)($body['type'] ?? ''));
        $typeId = (int)($body['console_type_id'] ?? ($body['type_id'] ?? 0));
        if ($typeName === '' && $typeId > 0) {
            $type = $this->typeRepo->getById($typeId);
            if ($type !== null) {
                $typeName = (string)($type['name'] ?? '');
            }
        }

        try {
            $gemini = new GeminiService();
            $specs = $gemini->generateConsoleSpecs([
                'name'       => $name,
                'maker'      => $makerName,
                'type'       => $typeName,
                'generation' => $body['generation'] ?? '',
                'year'       => $body['year'] ?? ($body['release_year'] ?? ''),
            ]);

            Response::json([
                'specs'      => $specs,
                'model_used' => $gemini->getLastUsedModel(),
            ]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 422);
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
