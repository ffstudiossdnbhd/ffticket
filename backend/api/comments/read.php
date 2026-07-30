<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Auth;
use FFTicket\Database;

if (current_request_method() !== 'POST') {
    json_response('error', 'Method not allowed.', null, 405);
}

$user = Auth::requireUser();
$payload = read_json_body();
$ticketId = (int)($payload['ticket_id'] ?? 0);
if ($ticketId < 1) {
    json_response('error', 'Ticket id is required.', null, 422);
}

try {
    $db = Database::connection();
    $ticket = $db->prepare('SELECT user_id FROM tickets WHERE id = :id LIMIT 1');
    $ticket->execute(['id' => $ticketId]);
    if ((int)$ticket->fetchColumn() !== (int)$user['id']) {
        json_response('error', 'You cannot update comment reads for this ticket.', null, 403);
    }

    $latest = $db->prepare(
        "SELECT COALESCE(MAX(c.id), 0)
         FROM ticket_comments c
         INNER JOIN users author ON author.id = c.created_by
         WHERE c.ticket_id = :ticket_id AND author.role IN ('admin', 'it_staff')"
    );
    $latest->execute(['ticket_id' => $ticketId]);
    $lastReadCommentId = (int)$latest->fetchColumn();

    $statement = $db->prepare(
        'INSERT INTO ticket_comment_reads (ticket_id, user_id, last_read_comment_id, read_at)
         VALUES (:ticket_id, :user_id, :last_read_comment_id, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            last_read_comment_id = GREATEST(last_read_comment_id, VALUES(last_read_comment_id)),
            read_at = UTC_TIMESTAMP()'
    );
    $statement->execute([
        'ticket_id' => $ticketId,
        'user_id' => $user['id'],
        'last_read_comment_id' => $lastReadCommentId,
    ]);
    json_response('success', 'Comments marked as read.', ['ticket_id' => $ticketId]);
} catch (Throwable $exception) {
    error_log('FFTicket comments/read.php failed: ' . $exception->getMessage());
    json_response('error', 'Unable to update comment reads.', null, 500);
}
