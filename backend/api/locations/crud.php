<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Auth;
use FFTicket\Database;
use FFTicket\TicketOptionRepository;

$method = current_request_method();
if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    json_response('error', 'Method not allowed.', null, 405);
}

$user = Auth::requireUser();
Auth::requireRole($user, ['admin', 'it_staff']);
$payload = read_json_body();

try {
    $repo = new TicketOptionRepository(Database::connection());

    if ($method === 'POST') {
        $name = clean_string($payload['name'] ?? '', 120);
        $description = clean_string($payload['description'] ?? '', 255);
        if ($name === '') {
            json_response('error', 'Name is required.', null, 422);
        }

        $id = $repo->createOrReactivate('locations', $name, $description === '' ? null : $description);
        json_response('success', 'Location saved.', ['id' => $id], 201);
    }

    $id = (int)($payload['id'] ?? 0);
    if ($id < 1) {
        json_response('error', 'Option id is required.', null, 422);
    }

    if ($method === 'PUT') {
        $name = clean_string($payload['name'] ?? '', 120);
        $description = clean_string($payload['description'] ?? '', 255);
        $isActive = filter_var($payload['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($name === '' || $isActive === null) {
            json_response('error', 'Name and active state are required.', null, 422);
        }

        if (!$repo->updateOption('locations', $id, $name, $description === '' ? null : $description, $isActive)) {
            json_response('error', 'Location not found.', null, 404);
        }
        json_response('success', 'Location updated.', ['id' => $id]);
    }

    if (!$repo->deactivateOption('locations', $id)) {
        json_response('error', 'Location not found.', null, 404);
    }
    json_response('success', 'Location deactivated.', ['id' => $id]);
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        json_response('error', 'An option with this name already exists.', null, 409);
    }
    json_response('error', 'Unable to process location request.', null, 500);
} catch (Throwable) {
    json_response('error', 'Unable to process location request.', null, 500);
}
