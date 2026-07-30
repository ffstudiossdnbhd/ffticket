<?php
declare(strict_types=1);

namespace FFTicket;

use PDO;
use RuntimeException;

final class NotificationService
{
    public const EVENT_PENDING_USER_INPUT = 'ticket.pending_user_input';
    public const EVENT_COMMENT_POSTED = 'ticket.comment_posted';

    public static function notifyPendingUserInput(
        PDO $db,
        int $ticketId,
        int $recipientUserId,
        int $actorUserId,
        int $auditLogId
    ): ?int {
        return self::create(
            $db,
            $ticketId,
            $recipientUserId,
            $actorUserId,
            self::EVENT_PENDING_USER_INPUT,
            'audit_log',
            $auditLogId,
            'Action required',
            'Ticket %s is waiting for your input.'
        );
    }

    public static function notifyCommentPosted(
        PDO $db,
        int $ticketId,
        int $recipientUserId,
        int $actorUserId,
        int $commentId
    ): ?int {
        return self::create(
            $db,
            $ticketId,
            $recipientUserId,
            $actorUserId,
            self::EVENT_COMMENT_POSTED,
            'ticket_comment',
            $commentId,
            'New ticket comment',
            'There is a new comment on ticket %s.'
        );
    }

    private static function create(
        PDO $db,
        int $ticketId,
        int $recipientUserId,
        int $actorUserId,
        string $eventType,
        string $sourceType,
        int $sourceId,
        string $title,
        string $bodyTemplate
    ): ?int {
        if ($recipientUserId < 1 || $ticketId < 1 || $actorUserId < 1 || $sourceId < 1) {
            throw new RuntimeException('Invalid notification context.');
        }
        if ($recipientUserId === $actorUserId) {
            return null;
        }

        $ticket = $db->prepare('SELECT ticket_number FROM tickets WHERE id = :id LIMIT 1');
        $ticket->execute(['id' => $ticketId]);
        $ticketNumber = $ticket->fetchColumn();
        if (!is_string($ticketNumber) || $ticketNumber === '') {
            throw new RuntimeException('Cannot create a notification for an unknown ticket.');
        }

        $insert = $db->prepare(
            'INSERT IGNORE INTO user_notifications
                (recipient_user_id, ticket_id, actor_user_id, event_type, source_type, source_id, title, body)
             VALUES
                (:recipient_user_id, :ticket_id, :actor_user_id, :event_type, :source_type, :source_id, :title, :body)'
        );
        $insert->execute([
            'recipient_user_id' => $recipientUserId,
            'ticket_id' => $ticketId,
            'actor_user_id' => $actorUserId,
            'event_type' => $eventType,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'title' => $title,
            'body' => sprintf($bodyTemplate, $ticketNumber),
        ]);

        if ($insert->rowCount() !== 1) {
            return null;
        }

        return (int)$db->lastInsertId();
    }
}
