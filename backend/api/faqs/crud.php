<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Auth;
use FFTicket\Database;

$method = current_request_method();
if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    json_response('error', 'Method not allowed.', null, 405);
}

$user = Auth::requireUser();
Auth::requireRole($user, ['admin']);
$payload = read_json_body();

try {
    $db = Database::connection();
    $categoryIdInput = trim((string)($payload['category_id'] ?? ''));
    $categoryId = null;
    if ($categoryIdInput !== '') {
        if (!ctype_digit($categoryIdInput)) {
            json_response('error', 'Invalid FAQ category.', null, 422);
        }
        $categoryId = (int)$categoryIdInput;
    }

    if ($method === 'POST') {
        require_fields($payload, ['title', 'description']);
        $statement = $db->prepare('INSERT INTO faqs (title, description, category_id) VALUES (:title, :description, :category_id)');
        $statement->execute([
            'title' => clean_string($payload['title'], 180),
            'description' => clean_string($payload['description'], 5000),
            'category_id' => $categoryId,
        ]);
        json_response('success', 'FAQ created.', ['id' => (int)$db->lastInsertId()], 201);
    }

    $id = (int)($payload['id'] ?? 0);
    if ($id < 1) {
        json_response('error', 'FAQ id is required.', null, 422);
    }

    if ($method === 'PUT') {
        require_fields($payload, ['title', 'description']);
        $statement = $db->prepare(
            'UPDATE faqs SET title = :title, description = :description, category_id = :category_id WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'title' => clean_string($payload['title'], 180),
            'description' => clean_string($payload['description'], 5000),
            'category_id' => $categoryId,
        ]);
        if ($statement->rowCount() === 0) {
            $exists = $db->prepare('SELECT id FROM faqs WHERE id = :id LIMIT 1');
            $exists->execute(['id' => $id]);
            if (!$exists->fetch()) {
                json_response('error', 'FAQ not found.', null, 404);
            }
        }
        json_response('success', 'FAQ updated.', ['id' => $id]);
    }

    $statement = $db->prepare('DELETE FROM faqs WHERE id = :id');
    $statement->execute(['id' => $id]);
    if ($statement->rowCount() === 0) {
        json_response('error', 'FAQ not found.', null, 404);
    }
    json_response('success', 'FAQ deleted.', ['id' => $id]);
} catch (Throwable $exception) {
    error_log('FFTicket faqs/crud.php failed: ' . $exception->getMessage());
    json_response('error', 'Unable to save the FAQ.', null, 500);
}
