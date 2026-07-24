<?php
declare(strict_types=1);

namespace FFTicketWeb\Services;

use FFTicketWeb\Core\Flash;

final class AuthService
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    public function login(string $email, string $password, bool $remember): array
    {
        $response = $this->api->postJson('auth/login.php', [
            'email' => trim($email),
            'password' => $password,
        ]);

        if (!$response['ok'] || !is_array($response['data'] ?? null)) {
            return $response;
        }

        $_SESSION['api_token'] = (string)$response['data']['token'];
        $_SESSION['user'] = $response['data']['user'];
        $_SESSION['remember_session'] = $remember;

        return $response;
    }

    public function logout(): void
    {
        unset($_SESSION['api_token'], $_SESSION['user'], $_SESSION['remember_session']);
    }

    public function token(): ?string
    {
        return isset($_SESSION['api_token']) ? (string)$_SESSION['api_token'] : null;
    }

    public function user(): ?array
    {
        return is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : null;
    }

    public function requireLogin(): array
    {
        $user = $this->user();
        if ($user === null || $this->token() === null) {
            Flash::error('Please sign in to continue.');
            header('Location: ' . $this->baseLoginPath());
            exit;
        }
        return $user;
    }

    public function requireRole(array $roles): array
    {
        $user = $this->requireLogin();
        if (!in_array((string)($user['role'] ?? ''), $roles, true)) {
            http_response_code(403);
            echo 'You do not have permission to view this page.';
            exit;
        }
        return $user;
    }

    public function isTech(): bool
    {
        $role = (string)($this->user()['role'] ?? '');
        return in_array($role, ['admin', 'it_staff'], true);
    }

    public function isAdmin(): bool
    {
        return (string)($this->user()['role'] ?? '') === 'admin';
    }

    private function baseLoginPath(): string
    {
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/web/index.php')), '/');
        return ($scriptDir === '' ? '' : $scriptDir) . '/login';
    }
}
