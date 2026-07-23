<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Auth;
use FFTicket\Database;
use FFTicket\TelegramNotifier;
use FFTicket\TicketRepository;
use FFTicket\UploadService;

if (current_request_method() !== 'POST') {
    json_response('error', 'Method not allowed.', null, 405);
}

$user = Auth::requireUser();

$subject = clean_string($_POST['subject'] ?? '', 180);
$description = clean_string($_POST['description'] ?? '', 5000);
$categoryId = (int)($_POST['category_id'] ?? 0);
$locationId = (int)($_POST['location_id'] ?? 0);

if ($subject === '' || $description === '' || $categoryId < 1 || $locationId < 1) {
    json_response('error', 'Subject, description, category, and location are required.', null, 422);
}

try {
    $db = Database::connection();
    $db->beginTransaction();

    $categoryStmt = $db->prepare('SELECT id, name FROM categories WHERE id = :id AND is_active = 1 LIMIT 1');
    $categoryStmt->execute(['id' => $categoryId]);
    $category = $categoryStmt->fetch();
    if (!$category) {
        $db->rollBack();
        json_response('error', 'Selected category does not exist.', null, 422);
    }

    $locationStmt = $db->prepare('SELECT id, name FROM locations WHERE id = :id AND is_active = 1 LIMIT 1');
    $locationStmt->execute(['id' => $locationId]);
    $location = $locationStmt->fetch();
    if (!$location) {
        $db->rollBack();
        json_response('error', 'Selected location does not exist.', null, 422);
    }

    $ticketNumber = 'TCK-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $insertTicket = $db->prepare(
        'INSERT INTO tickets (ticket_number, user_id, category_id, urgency_type_id, location_id, subject, description, status)
         VALUES (:ticket_number, :user_id, :category_id, :urgency_type_id, :location_id, :subject, :description, :status)'
    );
    $insertTicket->execute([
        'ticket_number' => $ticketNumber,
        'user_id' => $user['id'],
        'category_id' => $categoryId,
        'urgency_type_id' => null,
        'location_id' => $locationId,
        'subject' => $subject,
        'description' => $description,
        'status' => 'Open',
    ]);

    $ticketId = (int)$db->lastInsertId();
    $attachment = (new UploadService())->saveOptionalAttachment('attachment');
    if ($attachment !== null) {
        $insertAttachment = $db->prepare(
            'INSERT INTO ticket_attachments (ticket_id, file_name, file_path, file_size, file_type)
             VALUES (:ticket_id, :file_name, :file_path, :file_size, :file_type)'
        );
        $insertAttachment->execute([
            'ticket_id' => $ticketId,
            'file_name' => $attachment['file_name'],
            'file_path' => $attachment['file_path'],
            'file_size' => $attachment['file_size'],
            'file_type' => $attachment['file_type'],
        ]);
    }

    $audit = $db->prepare(
        'INSERT INTO audit_logs (ticket_id, performed_by, action, notes)
         VALUES (:ticket_id, :performed_by, :action, :notes)'
    );
    $audit->execute([
        'ticket_id' => $ticketId,
        'performed_by' => $user['id'],
        'action' => 'Created',
        'notes' => 'Ticket created by staff user.',
    ]);

    $db->commit();

    $ticket = (new TicketRepository($db))->findVisibleTicket($ticketId, $user);
    (new TelegramNotifier())->sendTicketCreated($ticket ?? [
        'ticket_number' => $ticketNumber,
        'subject' => $subject,
        'urgency' => '',
        'category_name' => (string)$category['name'],
        'creator_name' => $user['name'],
    ]);

    json_response('success', 'Ticket created successfully.', $ticket, 201);
} catch (Throwable) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    json_response('error', 'Unable to create ticket.', null, 500);
}
