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
$ids = $payload['ids'] ?? [];
if (!is_array($ids) || count($ids) < 1 || count($ids) > 100) {
    json_response('error', 'Provide between one and 100 notification IDs.', null, 422);
}

$ids = array_values(array_unique(array_map(static fn (mixed $id): int => (int)$id, $ids)));
if (in_array(0, $ids, true)) {
    json_response('error', 'Invalid notification ID.', null, 422);
}

try {
    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $statement = Database::connection()->prepare(
        "UPDATE user_notifications
         SET read_at = COALESCE(read_at, UTC_TIMESTAMP())
         WHERE recipient_user_id = ? AND id IN ({$placeholders})"
    );
    $statement->execute([$user['id'], ...$ids]);
    json_response('success', 'Notifications marked as read.', ['updated' => $statement->rowCount()]);
} catch (Throwable $exception) {
    error_log('FFTicket notifications/read.php failed: ' . $exception->getMessage());
    json_response('error', 'Unable to update notifications.', null, 500);
}
