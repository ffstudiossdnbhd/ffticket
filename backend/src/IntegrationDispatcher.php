<?php
declare(strict_types=1);

namespace FFTicket;

use JsonException;
use PDO;
use RuntimeException;

final class IntegrationDispatcher
{
    private const LOCK_NAME = 'ffticket_integration_dispatch_v1';
    private const BATCH_SIZE = 50;

    public function __construct(private readonly PDO $db)
    {
    }

    public function run(): array
    {
        $configuration = $this->configuration();
        if ($configuration === null) {
            return ['configured' => false, 'processed' => 0, 'delivered' => 0, 'failed' => 0];
        }

        if (!$this->acquireLock()) {
            return ['configured' => true, 'processed' => 0, 'delivered' => 0, 'failed' => 0];
        }

        $processed = 0;
        $delivered = 0;
        $failed = 0;

        try {
            $this->purgeOldDeliveredEvents();
            $events = $this->pendingEvents();
            foreach ($events as $event) {
                $processed++;
                try {
                    $this->deliver($event, $configuration);
                    $this->markDelivered((int)$event['id']);
                    $delivered++;
                } catch (\Throwable $exception) {
                    $this->markFailed(
                        (int)$event['id'],
                        (int)$event['attempts'],
                        $exception->getMessage()
                    );
                    $failed++;
                }
            }
        } finally {
            $this->releaseLock();
        }

        return compact('processed', 'delivered', 'failed') + ['configured' => true];
    }

    private function configuration(): ?array
    {
        $url = trim((string)env_value('FF_IT_HUB_WEBHOOK_URL', ''));
        $integrationId = trim((string)env_value('FF_IT_HUB_INTEGRATION_ID', ''));
        $secret = (string)env_value('FF_IT_HUB_WEBHOOK_SECRET', '');

        if ($url === '' && $integrationId === '' && $secret === '') {
            return null;
        }
        if (
            filter_var($url, FILTER_VALIDATE_URL) === false ||
            parse_url($url, PHP_URL_SCHEME) !== 'https' ||
            parse_url($url, PHP_URL_USER) !== null ||
            parse_url($url, PHP_URL_PASS) !== null
        ) {
            throw new RuntimeException('FF_IT_HUB_WEBHOOK_URL must be a credential-free HTTPS URL.');
        }
        if ($integrationId === '' || strlen($integrationId) > 191) {
            throw new RuntimeException('FF_IT_HUB_INTEGRATION_ID is invalid.');
        }
        if (strlen($secret) < 32 || strlen($secret) > 512) {
            throw new RuntimeException('FF_IT_HUB_WEBHOOK_SECRET is invalid.');
        }

        return [
            'url' => $url,
            'integration_id' => $integrationId,
            'secret' => $secret,
        ];
    }

    private function pendingEvents(): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, event_id, payload, attempts
             FROM integration_outbox
             WHERE delivered_at IS NULL
               AND next_attempt_at <= UTC_TIMESTAMP()
             ORDER BY id ASC
             LIMIT ' . self::BATCH_SIZE
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function deliver(array $event, array $configuration): void
    {
        try {
            $payload = json_decode((string)$event['payload'], true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new RuntimeException('Stored integration payload is invalid.');
            }
            $payload['integrationId'] = $configuration['integration_id'];
            $payload['eventId'] = (string)$event['event_id'];
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException('Stored integration payload is invalid.', 0, $exception);
        }

        $timestamp = (string)time();
        $signature = hash_hmac(
            'sha256',
            $timestamp . '.' . $body,
            $configuration['secret']
        );
        $handle = curl_init($configuration['url']);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize the webhook request.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: FFTicket-Integration/1.0',
                'X-FF-Webhook-Timestamp: ' . $timestamp,
                'X-FF-Webhook-Signature: sha256=' . $signature,
            ],
            CURLOPT_POSTFIELDS => $body,
        ]);

        $response = curl_exec($handle);
        $statusCode = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($response === false) {
            throw new RuntimeException($error !== '' ? $error : 'Webhook request failed.');
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException("Webhook returned HTTP {$statusCode}.");
        }
    }

    private function markDelivered(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE integration_outbox
             SET delivered_at = UTC_TIMESTAMP(), attempts = attempts + 1, last_error = NULL
             WHERE id = :id AND delivered_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
    }

    private function markFailed(int $id, int $attempts, string $message): void
    {
        $nextAttempts = min($attempts + 1, 20);
        $delaySeconds = min(3600, 30 * (2 ** min($nextAttempts - 1, 7)));
        $nextAttempt = gmdate('Y-m-d H:i:s', time() + $delaySeconds);
        $safeMessage = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $message) ?? 'Webhook delivery failed.';
        $safeMessage = mb_substr($safeMessage, 0, 500);

        $stmt = $this->db->prepare(
            'UPDATE integration_outbox
             SET attempts = attempts + 1,
                 next_attempt_at = :next_attempt_at,
                 last_error = :last_error
             WHERE id = :id AND delivered_at IS NULL'
        );
        $stmt->execute([
            'id' => $id,
            'next_attempt_at' => $nextAttempt,
            'last_error' => $safeMessage,
        ]);
    }

    private function purgeOldDeliveredEvents(): void
    {
        $this->db->exec(
            'DELETE FROM integration_outbox
             WHERE delivered_at IS NOT NULL
               AND delivered_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)'
        );
    }

    private function acquireLock(): bool
    {
        $stmt = $this->db->prepare('SELECT GET_LOCK(:lock_name, 0)');
        $stmt->execute(['lock_name' => self::LOCK_NAME]);
        return (int)$stmt->fetchColumn() === 1;
    }

    private function releaseLock(): void
    {
        $stmt = $this->db->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $stmt->execute(['lock_name' => self::LOCK_NAME]);
    }
}
