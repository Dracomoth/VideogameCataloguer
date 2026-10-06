<?php
/**
 * src/Controllers/AuthController.php
 * Controller handling user authentication, login submissions, and session termination.
 */

declare(strict_types=1);

namespace Vault\Controllers;

use Vault\Auth\Auth;
use Vault\Repositories\SystemInfoRepository;
use Vault\Services\Response;
use Vault\Services\View;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class AuthController
{
    /**
     * Renders the authentication login view.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function showLogin(array $request): void
    {
        Auth::startSession();

        // If already authenticated, redirect to the dashboard
        if (Auth::check()) {
            Response::redirect('/');
        }

        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);

        $versions = SystemInfoRepository::getVersions();

        $html = View::render('login', [
            'pageTitle'       => 'Sign In',
            'error'           => $error,
            'systemVersion'   => $versions['system'],
            'databaseVersion' => $versions['database'],
        ], null);

        Response::html($html);
    }

    /**
     * Processes credentials submission from the login form.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function login(array $request): void
    {
        $body = $request['body'] ?? [];
        $email = trim((string)($body['email'] ?? ''));
        $password = (string)($body['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->handleFailedLogin('Please enter both your email address and password.', $request);
        }

        if (Auth::attempt($email, $password)) {
            if ($this->wantsJson($request)) {
                Response::json(['success' => true, 'redirect' => '/']);
            }
            Response::redirect('/');
        }

        $this->handleFailedLogin('Invalid email or password. Please verify your credentials.', $request);
    }

    /**
     * Terminates the active authenticated session.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function logout(array $request): void
    {
        Auth::logout();
        Response::redirect('/login');
    }

    /**
     * Handles login failure responses according to content negotiation.
     *
     * @param string $message
     * @param array<string, mixed> $request
     * @return never
     */
    private function handleFailedLogin(string $message, array $request): never
    {
        if ($this->wantsJson($request)) {
            Response::json(['success' => false, 'error' => $message], 401);
        }

        Auth::startSession();
        $_SESSION['flash_error'] = $message;
        Response::redirect('/login');
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
