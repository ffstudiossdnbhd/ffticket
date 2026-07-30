<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\AccountTimeoutService;
use FFTicket\Auth;
use FFTicket\Database;

$method = current_request_method();
if (!in_array($method, ['GET', 'POST'], true)) {
    json_response('error', 'Method not allowed.', null, 405);
}

$admin = Auth::requireUser();
Auth::requireRole($admin, ['admin']);

try {
    $db = Database::connection();
    if ($method === 'GET') {
        $statement = $db->prepare(
            'SELECT id, name, nickname, email, role, last_seen_at, timeout_until, timeout_effective_at
             FROM users
             ORDER BY name ASC, id ASC'
        );
        $statement->execute();
        $users = [];
        $onlineCutoff = AccountTimeoutService::now()->sub(new \DateInterval('PT90S'));
        foreach ($statement->fetchAll() as $row) {
            $timeout = AccountTimeoutService::status($row);
            $lastSeen = !empty($row['last_seen_at'])
                ? new \DateTimeImmutable((string)$row['last_seen_at'], new \DateTimeZone('UTC'))
                : null;
            $users[] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'nickname' => $row['nickname'] === null ? null : (string)$row['nickname'],
                'email' => (string)$row['email'],
                'role' => (string)$row['role'],
                'online' => $lastSeen !== null && $lastSeen >= $onlineCutoff,
                'timed_out' => $timeout !== null,
                'timeout_warning' => $timeout['warning'] ?? false,
                'release_at' => $timeout['release_at'] ?? null,
                'release_at_myt' => $timeout['release_at_myt'] ?? null,
                'can_timeout' => in_array((string)$row['role'], ['staff', 'it_staff'], true),
            ];
        }
        json_response('success', 'Timeout users retrieved.', $users);
    }

    $payload = read_json_body();
    $action = assert_enum('action', $payload['action'] ?? '', ['start', 'update', 'release']);
    $userId = (int)($payload['user_id'] ?? 0);
    if ($userId < 1) {
        json_response('error', 'User id is required.', null, 422);
    }

    $target = $db->prepare(
        'SELECT id, name, role, timeout_until, timeout_effective_at
         FROM users WHERE id = :id LIMIT 1 FOR UPDATE'
    );
    $db->beginTransaction();
    $target->execute(['id' => $userId]);
    $user = $target->fetch();
    if (!$user) {
        $db->rollBack();
        json_response('error', 'User not found.', null, 404);
    }
    if (!in_array((string)$user['role'], ['staff', 'it_staff'], true)) {
        $db->rollBack();
        json_response('error', 'Administrators cannot be timed out.', null, 422);
    }

    if ($action === 'release') {
        $release = $db->prepare(
            'UPDATE users SET timeout_until = NULL, timeout_effective_at = NULL WHERE id = :id'
        );
        $release->execute(['id' => $userId]);
        $db->commit();
        json_response('success', 'User released from timeout.', ['id' => $userId]);
    }

    $releaseAt = AccountTimeoutService::parseMytRelease(clean_string($payload['release_at'] ?? '', 16));
    $effectiveAt = AccountTimeoutService::status($user) === null
        ? AccountTimeoutService::newEffectiveAt()
        : new \DateTimeImmutable((string)$user['timeout_effective_at'], new \DateTimeZone('UTC'));
    if ($releaseAt === null || $releaseAt <= $effectiveAt) {
        $db->rollBack();
        json_response('error', 'Choose a MYT release time later than the one-minute grace period.', null, 422);
    }

    $update = $db->prepare(
        'UPDATE users SET timeout_until = :timeout_until, timeout_effective_at = :timeout_effective_at WHERE id = :id'
    );
    $update->execute([
        'id' => $userId,
        'timeout_until' => $releaseAt->format('Y-m-d H:i:s'),
        'timeout_effective_at' => $effectiveAt->format('Y-m-d H:i:s'),
    ]);
    $db->commit();
    json_response('success', $action === 'start' ? 'Timeout started.' : 'Timeout release time updated.', [
        'id' => $userId,
        'release_at' => $releaseAt->format('Y-m-d\TH:i:s\Z'),
        'release_at_myt' => $releaseAt->setTimezone(new \DateTimeZone(AccountTimeoutService::TIMEZONE))->format('Y-m-d H:i'),
    ]);
} catch (Throwable $exception) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('FFTicket admin/timeouts.php failed: ' . $exception->getMessage());
    json_response('error', 'Unable to manage the timeout.', null, 500);
}
