<?php
declare(strict_types=1);

namespace FFTicket;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

final class Auth
{
    public static function createToken(array $user): string
    {
        $secret = self::secret();
        $now = time();
        $ttl = (int)env_value('JWT_TTL_SECONDS', '28800');

        return JWT::encode([
            'iss' => env_value('JWT_ISSUER', 'ffticket-api'),
            'aud' => env_value('JWT_AUDIENCE', 'ffticket-desktop'),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
            'sub' => (string)$user['id'],
            'user' => [
                'id' => (int)$user['id'],
                'name' => (string)$user['name'],
                'nickname' => $user['nickname'] ?? null,
                'email' => (string)$user['email'],
                'role' => (string)$user['role'],
            ],
        ], $secret, 'HS256');
    }

    public static function requireUser(): array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            json_response('error', 'Missing bearer token.', null, 401);
        }

        try {
            $decoded = JWT::decode($matches[1], new Key(self::secret(), 'HS256'));
        } catch (ExpiredException) {
            json_response('error', 'Session expired. Please sign in again.', null, 401);
        } catch (Throwable) {
            json_response('error', 'Invalid authentication token.', null, 401);
        }

        $tokenUser = (array)($decoded->user ?? []);
        if (!isset($tokenUser['id'])) {
            json_response('error', 'Invalid authentication token payload.', null, 401);
        }

        try {
            $statement = Database::connection()->prepare(
                'SELECT id, name, nickname, email, role, timeout_until, timeout_effective_at
                 FROM users
                 WHERE id = :id
                 LIMIT 1'
            );
            $statement->execute(['id' => (int)$tokenUser['id']]);
            $user = $statement->fetch();
        } catch (Throwable) {
            json_response('error', 'Unable to verify the current account.', null, 500);
        }

        if (!$user) {
            json_response('error', 'This account is no longer available.', null, 401);
        }

        AccountTimeoutService::assertCanUseApi($user);

        return [
            'id' => (int)$user['id'],
            'name' => (string)($user['name'] ?? ''),
            'nickname' => $user['nickname'] === null ? null : (string)$user['nickname'],
            'email' => (string)($user['email'] ?? ''),
            'role' => (string)$user['role'],
        ];
    }

    public static function requireRole(array $user, array $roles): void
    {
        if (!in_array($user['role'], $roles, true)) {
            json_response('error', 'You do not have permission to perform this action.', null, 403);
        }
    }

    private static function secret(): string
    {
        $secret = env_value('JWT_SECRET');
        if ($secret === null || strlen($secret) < 32) {
            json_response('error', 'JWT secret is not configured securely.', null, 500);
        }
        return $secret;
    }
}
