<?php
declare(strict_types=1);

namespace FFTicket;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use PDO;
use RuntimeException;

final class IntegrationOutbox
{
    private const EVENT_TYPES = [
        'ticket.created',
        'ticket.updated',
        'ticket.backfill',
    ];

    public static function enqueueTicket(PDO $db, int $ticketId, string $eventType): void
    {
        if (!in_array($eventType, self::EVENT_TYPES, true)) {
            throw new RuntimeException('Unsupported integration event type.');
        }

        $stmt = $db->prepare(
            'SELECT t.id, t.ticket_number, t.subject, t.status, t.created_at, t.closed_at,
                    urgency.name AS priority, assignee.name AS assignee_name
             FROM tickets t
             LEFT JOIN urgency_types urgency ON urgency.id = t.urgency_type_id
             LEFT JOIN users assignee ON assignee.id = t.assigned_to
             WHERE t.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $ticketId]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            throw new RuntimeException('Cannot enqueue an unknown ticket.');
        }

        $payload = [
            'eventType' => $eventType,
            'ticket' => [
                'id' => (string)$ticket['ticket_number'],
                'title' => (string)$ticket['subject'],
                'status' => self::normalizedStatus((string)$ticket['status']),
                'priority' => self::nullableUppercase($ticket['priority'] ?? null),
                'assignee' => self::nullableString($ticket['assignee_name'] ?? null),
                'url' => self::ticketUrl((int)$ticket['id']),
                'openedAt' => self::dateTime((string)$ticket['created_at']),
                'resolvedAt' => $ticket['closed_at'] === null
                    ? null
                    : self::dateTime((string)$ticket['closed_at']),
            ],
        ];

        try {
            $encodedPayload = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode the integration event.', 0, $exception);
        }

        $insert = $db->prepare(
            'INSERT INTO integration_outbox
                (event_id, event_type, ticket_id, payload, next_attempt_at)
             VALUES
                (:event_id, :event_type, :ticket_id, :payload, UTC_TIMESTAMP())'
        );
        $insert->execute([
            'event_id' => self::uuidV4(),
            'event_type' => $eventType,
            'ticket_id' => $ticketId,
            'payload' => $encodedPayload,
        ]);
    }

    private static function normalizedStatus(string $status): string
    {
        return match ($status) {
            'Open' => 'OPEN',
            'In Progress' => 'IN_PROGRESS',
            'Pending User Input' => 'PENDING',
            'Closed' => 'CLOSED',
            default => throw new RuntimeException('Unsupported ticket status.'),
        };
    }

    private static function nullableUppercase(mixed $value): ?string
    {
        $value = self::nullableString($value);
        return $value === null ? null : mb_strtoupper($value);
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string)($value ?? ''));
        return $value === '' ? null : $value;
    }

    private static function dateTime(string $value): string
    {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format(DATE_ATOM);
    }

    private static function ticketUrl(int $ticketId): ?string
    {
        $appUrl = rtrim((string)env_value('APP_URL', ''), '/');
        if (
            $appUrl === '' ||
            filter_var($appUrl, FILTER_VALIDATE_URL) === false ||
            parse_url($appUrl, PHP_URL_SCHEME) !== 'https'
        ) {
            return null;
        }
        return $appUrl . '/web/tickets/' . $ticketId;
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
