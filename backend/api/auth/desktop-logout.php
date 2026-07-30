<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Auth;
use FFTicket\Database;
use FFTicket\DesktopSessionService;

if (current_request_method() !== 'POST') {
    json_response('error', 'Method not allowed.', null, 405);
}

$user = Auth::requireUser();
$payload = read_json_body();
require_fields($payload, ['device_id']);
$deviceId = clean_string($payload['device_id'], 36);

try {
    (new DesktopSessionService())->revokeDevice(Database::connection(), $user['id'], $deviceId);

    json_response('success', 'Desktop session signed out.');
} catch (\InvalidArgumentException) {
    json_response('error', 'Invalid desktop session request.', null, 422);
} catch (Throwable $exception) {
    error_log(sprintf(
        'FFTicket auth/desktop-logout.php failed: %s in %s:%d',
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));
    json_response('error', 'Unable to sign out the desktop session.', null, 500);
}
