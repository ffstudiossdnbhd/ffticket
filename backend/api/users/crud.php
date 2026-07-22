<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Auth;
use FFTicket\Database;

$method = current_request_method();
if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    json_response('error', 'Method not allowed.', null, 405);
}

$currentUser = Auth::requireUser();
Auth::requireRole($currentUser, ['admin']);
$payload = read_json_body();

try {
    $db = Database::connection();

    if ($method === 'POST') {
        require_fields($payload, ['name', 'email', 'role']);
        $name = clean_string($payload['name'], 120);
        $email = filter_var(trim((string)$payload['email']), FILTER_VALIDATE_EMAIL);
        $role = assert_enum('role', $payload['role'], ['admin', 'it_staff', 'staff']);
        $password = (string)($payload['password'] ?? '');
        if ($email === false) {
            json_response('error', 'A valid email is required.', null, 422);
        }

        $generatedPassword = $password === '' ? bin2hex(random_bytes(8)) : null;
        $passwordToHash = $generatedPassword ?? $password;
        $stmt = $db->prepare(
            'INSERT INTO users (name, email, password_hash, role)
             VALUES (:name, :email, :password_hash, :role)'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($passwordToHash, PASSWORD_DEFAULT),
            'role' => $role,
        ]);
        json_response('success', $generatedPassword === null ? 'User created.' : 'User created. Share the temporary password securely.', [
            'id' => (int)$db->lastInsertId(),
            'temporary_password' => $generatedPassword,
        ], 201);
    }

    $id = (int)($payload['id'] ?? 0);
    if ($id < 1) {
        json_response('error', 'User id is required.', null, 422);
    }

    if ($method === 'PUT') {
        $existingStmt = $db->prepare('SELECT id, name, email, password_hash, role FROM users WHERE id = :id LIMIT 1');
        $existingStmt->execute(['id' => $id]);
        $existing = $existingStmt->fetch();
        if (!$existing) {
            json_response('error', 'User not found.', null, 404);
        }

        $name = array_key_exists('name', $payload) ? clean_string($payload['name'], 120) : (string)$existing['name'];
        $email = (string)$existing['email'];
        if (array_key_exists('email', $payload)) {
            $validatedEmail = filter_var(trim((string)$payload['email']), FILTER_VALIDATE_EMAIL);
            if ($validatedEmail === false) {
                json_response('error', 'Enter a valid email address.', null, 422);
            }
            $email = $validatedEmail;
        }
        $role = array_key_exists('role', $payload)
            ? assert_enum('role', $payload['role'], ['admin', 'it_staff', 'staff'])
            : (string)$existing['role'];
        $passwordHash = (string)$existing['password_hash'];
        if (array_key_exists('password', $payload) && trim((string)$payload['password']) !== '') {
            $passwordHash = password_hash((string)$payload['password'], PASSWORD_DEFAULT);
        }

        $stmt = $db->prepare(
            'UPDATE users
             SET name = :name, email = :email, password_hash = :password_hash, role = :role
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => $role,
            'id' => $id,
        ]);
        json_response('success', 'User updated.', ['id' => $id]);
    }

    if ($id === (int)$currentUser['id']) {
        json_response('error', 'You cannot delete your own account.', null, 422);
    }

    $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    json_response('success', 'User deleted.', ['id' => $id]);
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        json_response('error', 'This user cannot be changed because related records exist or the email is already used.', null, 409);
    }
    json_response('error', 'Unable to process user request.', null, 500);
} catch (Throwable) {
    json_response('error', 'Unable to process user request.', null, 500);
}
