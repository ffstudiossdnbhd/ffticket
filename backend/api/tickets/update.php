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

if (!array_key_exists('status', $payload) && !array_key_exists('urgency', $payload) && !array_key_exists('urgency_type_id', $payload) && !array_key_exists('assigned_to', $payload)) {
    json_response('error', 'No supported ticket fields were provided.', null, 422);
}

try {
    $db = Database::connection();
    $exists = $db->prepare(
        'SELECT t.id, t.status, t.urgency_type_id, t.assigned_to, u.name AS urgency
         FROM tickets t LEFT JOIN urgency_types u ON u.id = t.urgency_type_id
         WHERE t.id = :id LIMIT 1'
    );
    $exists->execute(['id' => $ticketId]);
    $ticket = $exists->fetch();
    if (!$ticket) {
        json_response('error', 'Ticket not found.', null, 404);
    }

    $status = array_key_exists('status', $payload)
        ? assert_enum('status', $payload['status'], ['Open', 'In Progress', 'Pending User Input', 'Closed'])
        : (string)$ticket['status'];
    $urgencyTypeId = $ticket['urgency_type_id'] === null ? null : (int)$ticket['urgency_type_id'];
    $urgency = (string)($ticket['urgency'] ?? '');
    if (array_key_exists('urgency_type_id', $payload) || array_key_exists('urgency', $payload)) {
        if (array_key_exists('urgency_type_id', $payload) && ctype_digit((string)$payload['urgency_type_id'])) {
            $urgencyStmt = $db->prepare('SELECT id, name FROM urgency_types WHERE id = :id AND is_active = 1 LIMIT 1');
            $urgencyStmt->execute(['id' => (int)$payload['urgency_type_id']]);
        } else {
            $urgencyName = clean_string($payload['urgency'] ?? '', 100);
            $urgencyStmt = $db->prepare('SELECT id, name FROM urgency_types WHERE name = :name AND is_active = 1 LIMIT 1');
            $urgencyStmt->execute(['name' => $urgencyName]);
        }

        $selectedUrgency = $urgencyStmt->fetch();
        if (!$selectedUrgency) {
            json_response('error', 'Selected urgency does not exist.', null, 422);
        }
        $urgencyTypeId = (int)$selectedUrgency['id'];
        $urgency = (string)$selectedUrgency['name'];
    }
    $assignedTo = array_key_exists('assigned_to', $payload)
        ? ($payload['assigned_to'] === null || $payload['assigned_to'] === '' ? null : (int)$payload['assigned_to'])
        : ($ticket['assigned_to'] === null ? null : (int)$ticket['assigned_to']);

    if (array_key_exists('status', $payload)) {
        $auditNotes[] = "Status set to {$status}.";
    }
    if (array_key_exists('urgency', $payload) || array_key_exists('urgency_type_id', $payload)) {
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
             urgency_type_id = :urgency_type_id,
             assigned_to = :assigned_to,
             closed_at = CASE
                 WHEN :closed_status = :closed_compare THEN COALESCE(closed_at, CURRENT_TIMESTAMP)
                 ELSE NULL
             END
         WHERE id = :id'
    );
    $stmt->execute([
        'status' => $status,
        'urgency_type_id' => $urgencyTypeId,
        'assigned_to' => $assignedTo,
        'closed_status' => $status,
        'closed_compare' => 'Closed',
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
} catch (Throwable $exception) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log(sprintf(
        'FFTicket tickets/update.php failed: %s in %s:%d',
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));
    json_response('error', 'Unable to update ticket.', null, 500);
}
