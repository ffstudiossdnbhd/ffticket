<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Auth;
use FFTicket\Database;
use FFTicket\TicketOptionRepository;

if (current_request_method() !== 'GET') {
    json_response('error', 'Method not allowed.', null, 405);
}

$user = Auth::requireUser();
$includeInactive = ($_GET['include_inactive'] ?? '') === '1';
if ($includeInactive) {
    Auth::requireRole($user, ['admin', 'it_staff']);
}

try {
    $options = (new TicketOptionRepository(Database::connection()))->listOptions('locations', $includeInactive);
    json_response('success', 'Locations retrieved.', $options);
} catch (Throwable) {
    json_response('error', 'Unable to retrieve locations.', null, 500);
}
