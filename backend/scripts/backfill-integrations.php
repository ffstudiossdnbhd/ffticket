<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../src/bootstrap.php';

use FFTicket\Database;
use FFTicket\IntegrationOutbox;

try {
    $db = Database::connection();
    $ticketIds = $db->query('SELECT id FROM tickets ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN);
    $queued = 0;

    $db->beginTransaction();
    foreach ($ticketIds as $ticketId) {
        IntegrationOutbox::enqueueTicket($db, (int)$ticketId, 'ticket.backfill');
        $queued++;
        if ($queued % 100 === 0) {
            $db->commit();
            $db->beginTransaction();
        }
    }
    $db->commit();

    fwrite(STDOUT, json_encode(['queued' => $queued]) . PHP_EOL);
} catch (Throwable $exception) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('FFTicket integration backfill failed: ' . $exception->getMessage());
    fwrite(STDERR, "Integration backfill failed.\n");
    exit(1);
}
