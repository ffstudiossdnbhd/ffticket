<?php
declare(strict_types=1);

namespace FFTicket;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;

final class DesktopSessionService
{
    private const REFRESH_TOKEN_TTL_DAYS = 30;

    public function login(PDO $db, string $email, string $password, string $deviceId): ?array
    {
        self::assertDeviceId($deviceId);

        $statement = $db->prepare(
            'SELECT id, name, nickname, email, password_hash, role
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            return null;
        }

        return $this->issue($db, self::profile($user), $deviceId);
    }

    public function refresh(PDO $db, string $deviceId, string $refreshToken): ?array
    {
        self::assertDeviceId($deviceId);
        $hash = self::hashToken($refreshToken);

        $db->beginTransaction();
        try {
            $statement = $db->prepare(
                'SELECT id, user_id, device_id, expires_at, revoked_at
                 FROM refresh_tokens
                 WHERE token_hash = :token_hash
                 LIMIT 1
                 FOR UPDATE'
            );
            $statement->execute(['token_hash' => $hash]);
            $stored = $statement->fetch();

            if (
                !$stored ||
                !hash_equals((string)$stored['device_id'], $deviceId) ||
                $stored['revoked_at'] !== null ||
                new DateTimeImmutable((string)$stored['expires_at'], new DateTimeZone('UTC')) <= new DateTimeImmutable('now', new DateTimeZone('UTC'))
            ) {
                if ($stored && hash_equals((string)$stored['device_id'], $deviceId)) {
                    $this->revokeDeviceTokens($db, (int)$stored['user_id'], $deviceId, 'invalid_refresh_token');
                }
                $db->commit();
                return null;
            }

            $userStatement = $db->prepare(
                'SELECT id, name, nickname, email, role
                 FROM users
                 WHERE id = :id
                 LIMIT 1'
            );
            $userStatement->execute(['id' => (int)$stored['user_id']]);
            $user = $userStatement->fetch();
            if (!$user) {
                $this->revokeDeviceTokens($db, (int)$stored['user_id'], $deviceId, 'user_missing');
                $db->commit();
                return null;
            }

            $revoke = $db->prepare(
                'UPDATE refresh_tokens
                 SET revoked_at = UTC_TIMESTAMP(), last_used_at = UTC_TIMESTAMP(), revocation_reason = :reason
                 WHERE id = :id AND revoked_at IS NULL'
            );
            $revoke->execute(['id' => (int)$stored['id'], 'reason' => 'rotated']);

            $session = $this->issue($db, self::profile($user), $deviceId);
            $db->commit();
            return $session;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function revokeDevice(PDO $db, int $userId, string $deviceId): void
    {
        self::assertDeviceId($deviceId);
        $this->revokeDeviceTokens($db, $userId, $deviceId, 'logout');
    }

    private function issue(PDO $db, array $profile, string $deviceId): array
    {
        $refreshToken = self::newRefreshToken();
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->add(new DateInterval('P' . self::REFRESH_TOKEN_TTL_DAYS . 'D'))
            ->format('Y-m-d H:i:s');

        $insert = $db->prepare(
            'INSERT INTO refresh_tokens (user_id, device_id, token_hash, expires_at, last_used_at)
             VALUES (:user_id, :device_id, :token_hash, :expires_at, UTC_TIMESTAMP())'
        );
        $insert->execute([
            'user_id' => (int)$profile['id'],
            'device_id' => $deviceId,
            'token_hash' => self::hashToken($refreshToken),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => Auth::createToken($profile),
            'refresh_token' => $refreshToken,
            'user' => $profile,
            'device_id' => $deviceId,
        ];
    }

    private function revokeDeviceTokens(PDO $db, int $userId, string $deviceId, string $reason): void
    {
        $statement = $db->prepare(
            'UPDATE refresh_tokens
             SET revoked_at = COALESCE(revoked_at, UTC_TIMESTAMP()),
                 revocation_reason = COALESCE(revocation_reason, :reason)
             WHERE user_id = :user_id AND device_id = :device_id AND revoked_at IS NULL'
        );
        $statement->execute([
            'user_id' => $userId,
            'device_id' => $deviceId,
            'reason' => $reason,
        ]);
    }

    public static function assertDeviceId(string $deviceId): void
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $deviceId)) {
            throw new InvalidArgumentException('Invalid device identifier.');
        }
    }

    private static function profile(array $user): array
    {
        return [
            'id' => (int)$user['id'],
            'name' => (string)$user['name'],
            'nickname' => !isset($user['nickname']) || $user['nickname'] === null
                ? null
                : (string)$user['nickname'],
            'email' => (string)$user['email'],
            'role' => (string)$user['role'],
        ];
    }

    private static function newRefreshToken(): string
    {
        return 'fft_r1_' . rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    private static function hashToken(string $token): string
    {
        if (strlen($token) < 40 || strlen($token) > 512) {
            throw new InvalidArgumentException('Invalid refresh token.');
        }
        return hash('sha256', $token);
    }
}
