<?php
declare(strict_types=1);

namespace FFTicket;

final class TelegramNotifier
{
    public function sendTicketCreated(array $ticket): void
    {
        $token = env_value('TELEGRAM_BOT_TOKEN');
        $chatId = env_value('TELEGRAM_CHAT_ID');
        if ($token === null || $chatId === null) {
            return;
        }
        $messageThreadId = env_value('TELEGRAM_MESSAGE_THREAD_ID');
        $description = trim((string)($ticket['description'] ?? ''));
        if (mb_strlen($description) > 2500) {
            $description = mb_substr($description, 0, 2499) . '…';
        }

        $message = sprintf(
            "<b>New FFTicket</b>\nTicket: %s\nSubject: %s\nDescription: %s\nCategory: %s\nCreator: %s",
            htmlspecialchars((string)$ticket['ticket_number'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)$ticket['subject'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($description, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)$ticket['category_name'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)$ticket['creator_name'], ENT_QUOTES, 'UTF-8')
        );

        $postFields = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => 'true',
        ];

        if ($messageThreadId !== null && ctype_digit($messageThreadId)) {
            $postFields['message_thread_id'] = $messageThreadId;
        }

        $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_POSTFIELDS => $postFields,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
