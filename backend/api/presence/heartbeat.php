<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\AccountTimeoutService;
use FFTicket\Auth;
use FFTicket\Database;

if (current_request_method() !== 'POST') {
    json_response('error', 'Method not allowed.', null, 405);
}

$user = Auth::requireUser();
$payload = read_json_body();
$clientId = clean_string($payload['client_id'] ?? '', 64);
if (!preg_match('/^[A-Za-z0-9._-]{8,64}$/', $clientId)) {
    json_response('error', 'Invalid client identifier.', null, 422);
}

$ticketId = (int)($payload['ticket_id'] ?? 0);
$mode = clean_string($payload['mode'] ?? 'viewing', 12);
if (!in_array($mode, ['viewing', 'editing'], true)) {
    json_response('error', 'Invalid collaboration mode.', null, 422);
}
if ($ticketId > 0) {
    Auth::requireRole($user, ['admin', 'it_staff']);
}

try {
    $db = Database::connection();
    $activity = $db->prepare('UPDATE users SET last_seen_at = UTC_TIMESTAMP() WHERE id = :id');
    $activity->execute(['id' => $user['id']]);
    $collaborators = [];

    if ($ticketId > 0) {
        $ticket = $db->prepare('SELECT id FROM tickets WHERE id = :id LIMIT 1');
        $ticket->execute(['id' => $ticketId]);
        if (!$ticket->fetch()) {
            json_response('error', 'Ticket not found.', null, 404);
        }

        $upsert = $db->prepare(
            'INSERT INTO ticket_collaboration_presence (ticket_id, user_id, client_id, mode, last_seen_at)
             VALUES (:ticket_id, :user_id, :client_id, :mode, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE mode = VALUES(mode), last_seen_at = UTC_TIMESTAMP()'
        );
        $upsert->execute([
            'ticket_id' => $ticketId,
            'user_id' => $user['id'],
            'client_id' => $clientId,
            'mode' => $mode,
        ]);

        $active = $db->prepare(
            "SELECT p.user_id,
                    COALESCE(NULLIF(u.nickname, ''), u.name) AS name,
                    CASE WHEN MAX(p.mode = 'editing') = 1 THEN 'editing' ELSE 'viewing' END AS mode
             FROM ticket_collaboration_presence p
             INNER JOIN users u ON u.id = p.user_id
             WHERE p.ticket_id = :ticket_id
               AND p.user_id <> :user_id
               AND p.last_seen_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 45 SECOND)
             GROUP BY p.user_id, u.name, u.nickname
             ORDER BY name ASC"
        );
        $active->execute(['ticket_id' => $ticketId, 'user_id' => $user['id']]);
        $collaborators = $active->fetchAll();
    }

    json_response('success', 'Presence updated.', [
        'collaborators' => $collaborators,
        'timeout' => AccountTimeoutService::status($user),
    ]);
} catch (Throwable $exception) {
    error_log('FFTicket presence/heartbeat.php failed: ' . $exception->getMessage());
    json_response('error', 'Unable to update activity.', null, 500);
}
