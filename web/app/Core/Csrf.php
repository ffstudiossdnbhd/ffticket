<?php
declare(strict_types=1);

namespace FFTicketWeb\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['csrf_token'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function validate(): void
    {
        $token = (string)($_POST['_csrf'] ?? '');
        if ($token === '' || !hash_equals(self::token(), $token)) {
            Flash::error('Your session form token expired. Please try again.');
            $path = parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH);
            header('Location: ' . ($path ?: './'));
            exit;
        }
    }
}
