<?php
declare(strict_types=1);

use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
if (file_exists($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

date_default_timezone_set('UTC');

header('X-Content-Type-Options: nosniff');

function env_value(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);
    return $value === false || $value === null || $value === '' ? $default : (string)$value;
}

function json_response(string $status, string $message, mixed $data = null, int $httpCode = 200): never
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        json_response('error', 'Invalid JSON request body.', null, 400);
    }

    return $decoded;
}

function clean_string(mixed $value, int $maxLength): string
{
    $value = trim((string)$value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    if (mb_strlen($value) > $maxLength) {
        json_response('error', "Value exceeds {$maxLength} characters.", null, 422);
    }
    return $value;
}

function require_fields(array $payload, array $fields): void
{
    foreach ($fields as $field) {
        if (!array_key_exists($field, $payload) || trim((string)$payload[$field]) === '') {
            json_response('error', "Missing required field: {$field}.", null, 422);
        }
    }
}

function assert_enum(string $field, mixed $value, array $allowed): string
{
    $value = clean_string($value, 60);
    if (!in_array($value, $allowed, true)) {
        json_response('error', "Invalid {$field}.", ['allowed' => $allowed], 422);
    }
    return $value;
}

function current_request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

