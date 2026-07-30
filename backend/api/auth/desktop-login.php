<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Database;
use FFTicket\DesktopSessionService;

if (current_request_method() !== 'POST') {
    json_response('error', 'Method not allowed.', null, 405);
}

$payload = read_json_body();
require_fields($payload, ['email', 'password', 'device_id']);

$email = filter_var(trim((string)$payload['email']), FILTER_VALIDATE_EMAIL);
if ($email === false) {
    json_response('error', 'Enter a valid email address.', null, 422);
}

$password = (string)$payload['password'];
$deviceId = clean_string($payload['device_id'], 36);

try {
    $session = (new DesktopSessionService())->login(Database::connection(), $email, $password, $deviceId);
    if ($session === null) {
        json_response('error', 'Invalid email or password.', null, 401);
    }

    json_response('success', 'Login successful.', $session);
} catch (\InvalidArgumentException) {
    json_response('error', 'Invalid desktop sign-in request.', null, 422);
} catch (Throwable $exception) {
    error_log(sprintf(
        'FFTicket auth/desktop-login.php failed: %s in %s:%d',
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));
    json_response('error', 'Unable to sign in right now.', null, 500);
}
