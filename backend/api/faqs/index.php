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
        'SELECT f.id, f.title, f.description, f.category_id, c.name AS category_name, f.created_at, f.updated_at
         FROM faqs f
         LEFT JOIN categories c ON c.id = f.category_id
         ORDER BY f.id DESC'
    );
    $statement->execute();
    json_response('success', 'FAQs retrieved.', $statement->fetchAll());
} catch (Throwable $exception) {
    error_log('FFTicket faqs/index.php failed: ' . $exception->getMessage());
    json_response('error', 'Unable to retrieve FAQs.', null, 500);
}
