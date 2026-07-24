<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Auth;
use FFTicket\Database;

if (current_request_method() !== 'GET') {
    json_response('error', 'Method not allowed.', null, 405);
}

$user = Auth::requireUser();
Auth::requireRole($user, ['admin']);

try {
    $db = Database::connection();
    $stmt = $db->prepare('SELECT id, name, nickname, email, role, created_at, updated_at FROM users ORDER BY id ASC');
    $stmt->execute();
    json_response('success', 'Users retrieved.', $stmt->fetchAll());
} catch (Throwable) {
    json_response('error', 'Unable to retrieve users.', null, 500);
}
