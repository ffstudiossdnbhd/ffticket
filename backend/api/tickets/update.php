<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Auth;
use FFTicket\Database;

if (current_request_method() !== 'PUT') {
    json_response('error', 'Method not allowed.', null, 405);
}

$user = Auth::requireUser();
Auth::requireRole($user, ['admin', 'it_staff']);

$payload = read_json_body();
$ticketId = (int)($payload['id'] ?? 0);
if ($ticketId < 1) {
    json_response('error', 'Ticket id is required.', null, 422);
}

$auditNotes = [];

if (!array_key_exists('status', $payload) && !array_key_exists('urgency', $payload) && !array_key_exists('assigned_to', $payload)) {
    json_response('error', 'No supported ticket fields were provided.', null, 422);
}

try {
    $db = Database::connection();
    $exists = $db->prepare('SELECT id, status, urgency, assigned_to FROM tickets WHERE id = :id LIMIT 1');
    $exists->execute(['id' => $ticketId]);
    $ticket = $exists->fetch();
    if (!$ticket) {
        json_response('error', 'Ticket not found.', null, 404);
    }

    $status = array_key_exists('status', $payload)
        ? assert_enum('status', $payload['status'], ['Open', 'In Progress', 'Pending User Input', 'Closed'])
        : (string)$ticket['status'];
    $urgency = array_key_exists('urgency', $payload)
        ? assert_enum('urgency', $payload['urgency'], ['Low', 'Medium', 'High', 'Critical'])
        : (string)$ticket['urgency'];
    $assignedTo = array_key_exists('assigned_to', $payload)
        ? ($payload['assigned_to'] === null || $payload['assigned_to'] === '' ? null : (int)$payload['assigned_to'])
        : ($ticket['assigned_to'] === null ? null : (int)$ticket['assigned_to']);

    if (array_key_exists('status', $payload)) {
        $auditNotes[] = "Status set to {$status}.";
    }
    if (array_key_exists('urgency', $payload)) {
        $auditNotes[] = "Urgency set to {$urgency}.";
    }
    if (array_key_exists('assigned_to', $payload)) {
        $auditNotes[] = $assignedTo === null ? 'Ticket unassigned.' : "Assigned to user {$assignedTo}.";
    }

    if ($assignedTo !== null) {
        $assignee = $db->prepare("SELECT id FROM users WHERE id = :id AND role IN ('admin', 'it_staff') LIMIT 1");
        $assignee->execute(['id' => $assignedTo]);
        if (!$assignee->fetch()) {
            json_response('error', 'Assignee must be an admin or IT staff user.', null, 422);
        }
    }

    $db->beginTransaction();
    $stmt = $db->prepare(
        'UPDATE tickets
         SET status = :status,
             urgency = :urgency,
             assigned_to = :assigned_to,
             closed_at = CASE
                 WHEN :closed_status = "Closed" THEN COALESCE(closed_at, CURRENT_TIMESTAMP)
                 ELSE NULL
             END
         WHERE id = :id'
    );
    $stmt->execute([
        'status' => $status,
        'urgency' => $urgency,
        'assigned_to' => $assignedTo,
        'closed_status' => $status,
        'id' => $ticketId,
    ]);

    $audit = $db->prepare(
        'INSERT INTO audit_logs (ticket_id, performed_by, action, notes)
         VALUES (:ticket_id, :performed_by, :action, :notes)'
    );
    $audit->execute([
        'ticket_id' => $ticketId,
        'performed_by' => $user['id'],
        'action' => 'Updated',
        'notes' => implode(' ', $auditNotes),
    ]);
    $db->commit();

    json_response('success', 'Ticket updated successfully.', ['id' => $ticketId]);
} catch (Throwable) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    json_response('error', 'Unable to update ticket.', null, 500);
}
