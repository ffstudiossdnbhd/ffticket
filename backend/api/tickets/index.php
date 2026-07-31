<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Auth;
use FFTicket\Database;
use FFTicket\TicketRepository;

if (current_request_method() !== 'GET') {
    json_response('error', 'Method not allowed.', null, 405);
}

$user = Auth::requireUser();

$filters = $_GET;
if (!empty($filters['status'])) {
    $filters['status'] = assert_enum('status', $filters['status'], ['Open', 'In Progress', 'Pending User Input', 'Closed']);
}
if (!empty($filters['urgency'])) {
    $filters['urgency'] = clean_string($filters['urgency'], 100);
}
if (!empty($filters['urgency_type_id']) && !ctype_digit((string)$filters['urgency_type_id'])) {
    json_response('error', 'Invalid urgency type.', null, 422);
}

$fromInput = isset($filters['from']) ? trim((string)$filters['from']) : '';
$toInput = isset($filters['to']) ? trim((string)$filters['to']) : '';
$hasFrom = $fromInput !== '';
$hasTo = $toInput !== '';
if ($hasFrom !== $hasTo) {
    error_log(sprintf(
        'FFTicket tickets/index.php warning: ignoring date filters due missing pair from=%s to=%s',
        $fromInput,
        $toInput
    ));
    unset($filters['from'], $filters['to']);
} elseif ($hasFrom && $hasTo) {
    $from = $fromInput;
    $to = $toInput;
    $fromDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $from);
    $toDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $to);
    if (
        $fromDate === false ||
        $toDate === false ||
        $fromDate->format('Y-m-d') !== $from ||
        $toDate->format('Y-m-d') !== $to
    ) {
        error_log(sprintf(
            'FFTicket tickets/index.php warning: ignoring invalid date range from=%s to=%s',
            $from,
            $to
        ));
        unset($filters['from'], $filters['to']);
    } elseif ($fromDate > $toDate) {
        error_log(sprintf(
            'FFTicket tickets/index.php warning: ignoring reversed date range from=%s to=%s',
            $from,
            $to
        ));
        unset($filters['from'], $filters['to']);
    }
}

try {
    $tickets = (new TicketRepository(Database::connection()))->listTickets($filters, $user);
    json_response('success', 'Tickets retrieved.', $tickets);
} catch (Throwable $exception) {
    error_log(sprintf(
        'FFTicket tickets/index.php failed: %s in %s:%d',
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));
    json_response('error', 'Unable to retrieve tickets.', null, 500);
}
