<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Auth;
use FFTicket\Database;

if (current_request_method() !== 'GET') {
    json_response('error', 'Method not allowed.', null, 405);
}

$user = Auth::requireUser();
$afterId = (int)($_GET['after_id'] ?? 0);
$limit = (int)($_GET['limit'] ?? 100);
$unread = ($_GET['unread'] ?? '1') !== '0';
if ($afterId < 0 || $limit < 1 || $limit > 100) {
    json_response('error', 'Invalid notification query.', null, 422);
}

try {
    $sql =
        'SELECT n.id, n.ticket_id, n.event_type, n.title, n.body, n.created_at, n.read_at
         FROM user_notifications n
         WHERE n.recipient_user_id = :user_id
           AND n.id > :after_id';
    if ($unread) {
        $sql .= ' AND n.read_at IS NULL';
    }
    $sql .= ' ORDER BY id ASC LIMIT ' . $limit;

    $statement = Database::connection()->prepare($sql);
    $statement->execute([
        'user_id' => $user['id'],
        'after_id' => $afterId,
    ]);
    json_response('success', 'Notifications retrieved.', $statement->fetchAll());
} catch (Throwable $exception) {
    error_log('FFTicket notifications/index.php failed: ' . $exception->getMessage());
    json_response('error', 'Unable to retrieve notifications.', null, 500);
}
