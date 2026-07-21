<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Auth;
use FFTicket\Database;
use FFTicket\TicketRepository;

if (current_request_method() !== 'GET') {
    json_response('error', 'Method not allowed.', null, 405);
}

$user = Auth::requireUser();
$ticketId = (int)($_GET['id'] ?? 0);
if ($ticketId < 1) {
    json_response('error', 'Ticket id is required.', null, 422);
}

try {
    $db = Database::connection();
    $repo = new TicketRepository($db);
    $ticket = $repo->findVisibleTicket($ticketId, $user);
    if (!$ticket) {
        json_response('error', 'Ticket not found.', null, 404);
    }

    $attachments = $db->prepare('SELECT id, file_name, file_size, file_type, uploaded_at FROM ticket_attachments WHERE ticket_id = :ticket_id ORDER BY uploaded_at ASC');
    $attachments->execute(['ticket_id' => $ticketId]);

    $logs = $db->prepare(
        'SELECT a.id, a.action, a.notes, a.created_at, u.name AS performed_by_name
         FROM audit_logs a INNER JOIN users u ON u.id = a.performed_by
         WHERE a.ticket_id = :ticket_id ORDER BY a.created_at ASC'
    );
    $logs->execute(['ticket_id' => $ticketId]);

    $comments = [];
    if (in_array($user['role'], ['admin', 'it_staff'], true)) {
        $commentStmt = $db->prepare(
            'SELECT c.id, c.body, c.created_at, u.name AS created_by_name
             FROM ticket_comments c INNER JOIN users u ON u.id = c.created_by
             WHERE c.ticket_id = :ticket_id ORDER BY c.created_at ASC'
        );
        $commentStmt->execute(['ticket_id' => $ticketId]);
        $comments = $commentStmt->fetchAll();
    }

    json_response('success', 'Ticket detail retrieved.', [
        'ticket' => $ticket,
        'attachments' => $attachments->fetchAll(),
        'audit_logs' => $logs->fetchAll(),
        'comments' => $comments,
    ]);
} catch (Throwable) {
    json_response('error', 'Unable to retrieve ticket detail.', null, 500);
}

