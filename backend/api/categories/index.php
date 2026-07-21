<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Auth;
use FFTicket\Database;

if (current_request_method() !== 'GET') {
    json_response('error', 'Method not allowed.', null, 405);
}

Auth::requireUser();

try {
    $db = Database::connection();
    $stmt = $db->query('SELECT id, name, description FROM categories ORDER BY name ASC');
    json_response('success', 'Categories retrieved.', $stmt->fetchAll());
} catch (Throwable) {
    json_response('error', 'Unable to retrieve categories.', null, 500);
}

