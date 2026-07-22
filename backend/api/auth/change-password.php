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

$currentPassword = (string)($payload['current_password'] ?? '');
$newPassword = (string)($payload['new_password'] ?? '');

if ($currentPassword === '' || $newPassword === '') {
    json_response('error', 'Current password and new password are required.', null, 422);
}

try {
    $db = Database::connection();
    $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int)$user['id']]);
    $account = $stmt->fetch();

    if (!$account || !password_verify($currentPassword, (string)$account['password_hash'])) {
        json_response('error', 'Current password is incorrect.', null, 401);
    }

    $update = $db->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
    $update->execute([
        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        'id' => (int)$user['id'],
    ]);

    json_response('success', 'Password changed successfully.');
} catch (Throwable) {
    json_response('error', 'Unable to change password.', null, 500);
}
