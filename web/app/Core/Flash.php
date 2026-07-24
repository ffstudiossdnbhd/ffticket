<?php
declare(strict_types=1);

namespace FFTicketWeb\Core;

final class Flash
{
    public static function success(string $message): void
    {
        $_SESSION['flash_success'] = $message;
    }

    public static function error(string $message): void
    {
        $_SESSION['flash_error'] = $message;
    }

    public static function pull(): array
    {
        $messages = [
            'success' => $_SESSION['flash_success'] ?? '',
            'error' => $_SESSION['flash_error'] ?? '',
        ];
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);
        return $messages;
    }
}
