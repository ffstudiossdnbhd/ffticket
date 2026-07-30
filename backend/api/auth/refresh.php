<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Database;
use FFTicket\DesktopSessionService;

if (current_request_method() !== 'POST') {
    json_response('error', 'Method not allowed.', null, 405);
}

$payload = read_json_body();
require_fields($payload, ['device_id', 'refresh_token']);
$deviceId = clean_string($payload['device_id'], 36);
$refreshToken = clean_string($payload['refresh_token'], 512);

try {
    $session = (new DesktopSessionService())->refresh(Database::connection(), $deviceId, $refreshToken);
    if ($session === null) {
        json_response('error', 'Your desktop session has expired. Please sign in again.', null, 401);
    }

    json_response('success', 'Desktop session refreshed.', $session);
} catch (\InvalidArgumentException) {
    json_response('error', 'Invalid desktop session request.', null, 422);
} catch (Throwable $exception) {
    error_log(sprintf(
        'FFTicket auth/refresh.php failed: %s in %s:%d',
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));
    json_response('error', 'Unable to refresh the desktop session.', null, 500);
}
