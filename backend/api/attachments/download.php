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
$attachmentId = (int)($_GET['id'] ?? 0);
if ($attachmentId < 1) {
    json_response('error', 'Attachment id is required.', null, 422);
}

try {
    $db = Database::connection();
    $stmt = $db->prepare(
        'SELECT id, ticket_id, file_name, file_path, file_size, file_type
         FROM ticket_attachments
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $attachmentId]);
    $attachment = $stmt->fetch();
    if (!$attachment) {
        json_response('error', 'Attachment not found.', null, 404);
    }

    $ticket = (new TicketRepository($db))->findVisibleTicket((int)$attachment['ticket_id'], $user);
    if (!$ticket) {
        json_response('error', 'Attachment not found.', null, 404);
    }

    $backendRoot = dirname(__DIR__, 2);
    $uploadDir = env_value('UPLOAD_DIR', 'storage/uploads');
    $storageDir = realpath($backendRoot . '/' . $uploadDir);
    if ($storageDir === false) {
        json_response('error', 'Attachment file not found.', null, 404);
    }

    $storedName = basename((string)$attachment['file_path']);
    $filePath = realpath($storageDir . DIRECTORY_SEPARATOR . $storedName);
    if ($filePath === false || !str_starts_with($filePath, $storageDir . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
        json_response('error', 'Attachment file not found.', null, 404);
    }

    $downloadName = str_replace(["\r", "\n", '"'], '', basename((string)$attachment['file_name']));
    if ($downloadName === '') {
        $downloadName = 'attachment';
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: ' . ((string)$attachment['file_type'] ?: 'application/octet-stream'));
    header('Content-Length: ' . (string)filesize($filePath));
    header('Content-Disposition: attachment; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    header('Cache-Control: private, max-age=0, must-revalidate');
    readfile($filePath);
    exit;
} catch (Throwable $exception) {
    error_log(sprintf(
        'FFTicket attachments/download.php failed: %s in %s:%d',
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));
    json_response('error', 'Unable to download attachment.', null, 500);
}
