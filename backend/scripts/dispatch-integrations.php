<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../src/bootstrap.php';

use FFTicket\Database;
use FFTicket\IntegrationDispatcher;

try {
    $result = (new IntegrationDispatcher(Database::connection()))->run();
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(($result['failed'] ?? 0) > 0 ? 1 : 0);
} catch (Throwable $exception) {
    error_log('FFTicket integration dispatcher failed: ' . $exception->getMessage());
    fwrite(STDERR, "Integration dispatch failed.\n");
    exit(1);
}
