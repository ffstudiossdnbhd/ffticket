<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Auth;
use FFTicket\Database;
use FFTicket\NotificationService;

if (current_request_method() !== 'POST') {
    json_response('error', 'Method not allowed.', null, 405);
}

$user = Auth::requireUser();
Auth::requireRole($user, ['admin', 'it_staff']);

$payload = read_json_body();
$ticketId = (int)($payload['ticket_id'] ?? 0);
$body = clean_string($payload['body'] ?? '', 5000);
if ($ticketId < 1 || $body === '') {
    json_response('error', 'Ticket id and note body are required.', null, 422);
}

try {
    $db = Database::connection();
    $exists = $db->prepare('SELECT id, user_id FROM tickets WHERE id = :id LIMIT 1');
    $exists->execute(['id' => $ticketId]);
    $ticket = $exists->fetch();
    if (!$ticket) {
        json_response('error', 'Ticket not found.', null, 404);
    }

    $db->beginTransaction();
    $stmt = $db->prepare(
        'INSERT INTO ticket_comments (ticket_id, created_by, body, visibility)
         VALUES (:ticket_id, :created_by, :body, :visibility)'
    );
    $stmt->execute([
        'ticket_id' => $ticketId,
        'created_by' => $user['id'],
        'body' => $body,
        'visibility' => 'public',
    ]);
    $commentId = (int)$db->lastInsertId();
    NotificationService::notifyCommentPosted(
        $db,
        $ticketId,
        (int)$ticket['user_id'],
        (int)$user['id'],
        $commentId
    );
    $db->commit();

    json_response('success', 'Comment added.', ['id' => $commentId], 201);
} catch (Throwable $exception) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log(sprintf(
        'FFTicket comments/create.php failed: %s in %s:%d',
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));
    json_response('error', 'Unable to add internal note.', null, 500);
}
