<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Auth;
use FFTicket\Database;

if (current_request_method() !== 'POST') {
    json_response('error', 'Method not allowed.', null, 405);
}

$payload = read_json_body();
require_fields($payload, ['email', 'password']);

$email = filter_var(trim((string)$payload['email']), FILTER_VALIDATE_EMAIL);
if ($email === false) {
    json_response('error', 'Enter a valid email address.', null, 422);
}

$password = (string)$payload['password'];

try {
    $db = Database::connection();
    $stmt = $db->prepare('SELECT id, name, nickname, email, password_hash, role FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string)$user['password_hash'])) {
        json_response('error', 'Invalid email or password.', null, 401);
    }

    $profile = [
        'id' => (int)$user['id'],
        'name' => (string)$user['name'],
        'nickname' => $user['nickname'] === null ? null : (string)$user['nickname'],
        'email' => (string)$user['email'],
        'role' => (string)$user['role'],
    ];

    json_response('success', 'Login successful.', [
        'token' => Auth::createToken($profile),
        'user' => $profile,
    ]);
} catch (Throwable) {
    json_response('error', 'Unable to sign in right now.', null, 500);
}
