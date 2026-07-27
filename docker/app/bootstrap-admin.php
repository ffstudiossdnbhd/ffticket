<?php
declare(strict_types=1);

require_once '/var/www/html/backend/src/bootstrap.php';

use FFTicket\Database;

$email = trim((string)(getenv('BOOTSTRAP_ADMIN_EMAIL') ?: ''));
$password = (string)(getenv('BOOTSTRAP_ADMIN_PASSWORD') ?: '');
$name = trim((string)(getenv('BOOTSTRAP_ADMIN_NAME') ?: 'System Admin'));

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($password) < 12) {
    fwrite(STDERR, "Bootstrap admin credentials are missing or invalid.\n");
    exit(1);
}

$database = null;
for ($attempt = 1; $attempt <= 30; $attempt++) {
    try {
        $database = Database::connection();
        break;
    } catch (Throwable $exception) {
        if ($attempt === 30) {
            fwrite(STDERR, "Unable to initialize the FFTicket administrator.\n");
            exit(1);
        }
        sleep(2);
    }
}

$countStatement = $database->prepare('SELECT COUNT(*) FROM users');
$countStatement->execute();
if ((int)$countStatement->fetchColumn() > 0) {
    exit(0);
}

$insertStatement = $database->prepare(
    'INSERT INTO users (name, nickname, email, password_hash, role)
     VALUES (:name, :nickname, :email, :password_hash, :role)'
);
$insertStatement->execute([
    'name' => $name === '' ? 'System Admin' : $name,
    'nickname' => 'Admin',
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'role' => 'admin',
]);
