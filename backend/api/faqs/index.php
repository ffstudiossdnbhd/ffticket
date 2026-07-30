<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Auth;
use FFTicket\Database;

if (current_request_method() !== 'GET') {
    json_response('error', 'Method not allowed.', null, 405);
}

Auth::requireUser();

try {
    $statement = Database::connection()->prepare(
        'SELECT id, title, description, created_at, updated_at FROM faqs ORDER BY id DESC'
    );
    $statement->execute();
    json_response('success', 'FAQs retrieved.', $statement->fetchAll());
} catch (Throwable $exception) {
    error_log('FFTicket faqs/index.php failed: ' . $exception->getMessage());
    json_response('error', 'Unable to retrieve FAQs.', null, 500);
}
