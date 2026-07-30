<?php
declare(strict_types=1);

namespace FFTicketWeb\Controllers;

use FFTicketWeb\Core\Csrf;
use FFTicketWeb\Core\Flash;
use FFTicketWeb\Core\View;
use FFTicketWeb\Services\ApiClient;
use FFTicketWeb\Services\AuthService;

abstract class BaseController
{
    public function __construct(
        protected readonly View $view,
        protected readonly ApiClient $api,
        protected readonly AuthService $auth
    ) {
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . $this->url($path));
        exit;
    }

    protected function url(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/web/index.php')), '/');
        return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
    }

    protected function csrf(): void
    {
        Csrf::validate();
    }

    protected function token(): ?string
    {
        return $this->auth->token();
    }

    protected function handleApiFailure(array $response, string $fallbackPath): void
    {
        if (in_array((int)($response['status'] ?? 0), [401, 423], true)) {
            $this->auth->logout();
            Flash::error($response['message'] ?? 'Session expired. Please sign in again.');
            $this->redirect('/login');
        }

        Flash::error($response['message'] ?? 'Request failed.');
        $this->redirect($fallbackPath);
    }

    protected function field(string $name, int $maxLength = 5000): string
    {
        $value = trim((string)($_POST[$name] ?? ''));
        return mb_substr($value, 0, $maxLength);
    }
}
