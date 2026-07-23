<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Auth;
use FFTicket\Database;

if (current_request_method() !== 'GET') {
    json_response('error', 'Method not allowed.', null, 405);
}

$user = Auth::requireUser();
Auth::requireRole($user, ['admin', 'it_staff']);

try {
    $db = Database::connection();
    $stmt = $db->query(
        "SELECT id, name, nickname, role
         FROM users
         WHERE role IN ('admin', 'it_staff')
         ORDER BY name ASC"
    );
    json_response('success', 'Assignable users retrieved.', $stmt->fetchAll());
} catch (Throwable) {
    json_response('error', 'Unable to retrieve assignable users.', null, 500);
}
