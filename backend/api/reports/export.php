<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FFTicket\Auth;
use FFTicket\Database;

if (current_request_method() !== 'GET') {
    json_response('error', 'Method not allowed.', null, 405);
}

$user = Auth::requireUser();
Auth::requireRole($user, ['admin', 'it_staff']);

$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
if (!is_string($from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !is_string($to) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    json_response('error', 'from and to dates are required in YYYY-MM-DD format.', null, 422);
}
$fromDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $from);
$toDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $to);
if (
    $fromDate === false ||
    $toDate === false ||
    $fromDate->format('Y-m-d') !== $from ||
    $toDate->format('Y-m-d') !== $to ||
    $fromDate > $toDate
) {
    json_response('error', 'Invalid report date range.', null, 422);
}

try {
    $db = Database::connection();
    $stmt = $db->prepare(
        'SELECT t.ticket_number, t.subject, t.status, COALESCE(u.name, \'\') AS urgency, c.name AS category,
            l.name AS location, creator.name AS creator, assignee.name AS assignee, t.created_at, t.closed_at
         FROM tickets t
         INNER JOIN categories c ON c.id = t.category_id
         LEFT JOIN urgency_types u ON u.id = t.urgency_type_id
         INNER JOIN locations l ON l.id = t.location_id
         INNER JOIN users creator ON creator.id = t.user_id
         LEFT JOIN users assignee ON assignee.id = t.assigned_to
         WHERE DATE(t.created_at) BETWEEN :from_date AND :to_date
         ORDER BY t.created_at ASC'
    );
    $stmt->execute(['from_date' => $from, 'to_date' => $to]);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ffticket-report-' . $from . '-to-' . $to . '.csv"');

    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Ticket Number', 'Subject', 'Status', 'Urgency', 'Category', 'Location', 'Creator', 'Assignee', 'Created At', 'Closed At']);
    while ($row = $stmt->fetch()) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
} catch (Throwable) {
    json_response('error', 'Unable to export report.', null, 500);
}
