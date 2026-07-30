<?php
declare(strict_types=1);

namespace FFTicketWeb\Controllers;

use FFTicketWeb\Core\Flash;

final class TicketController extends BaseController
{
    private const MUTATION_STATUSES = ['Open', 'In Progress', 'Pending User Input', 'Closed'];

    public function index(): void
    {
        $user = $this->auth->requireLogin();
        if (($user['role'] ?? 'staff') !== 'staff') {
            $this->redirect('/admin/tickets');
        }

        $categories = $this->api->get('categories/index.php', $this->token());
        $locations = $this->api->get('locations/index.php', $this->token());
        $search = trim((string)($_GET['search'] ?? ''));
        if (mb_strlen($search) > 180) {
            $search = mb_substr($search, 0, 180);
        }
        $ticketsPath = 'tickets/index.php' . ($search === '' ? '' : '?' . http_build_query(['search' => $search]));
        $tickets = $this->api->get($ticketsPath, $this->token());

        $this->view->render('tickets/index', [
            'title' => 'My Tickets',
            'user' => $user,
            'isTech' => false,
            'isAdmin' => false,
            'categories' => $categories['data'] ?? [],
            'locations' => $locations['data'] ?? [],
            'tickets' => $tickets['data'] ?? [],
            'loadError' => (!$categories['ok'] || !$locations['ok'] || !$tickets['ok'])
                ? (($categories['message'] ?? '') ?: ($locations['message'] ?? '') ?: ($tickets['message'] ?? 'Unable to load tickets.'))
                : '',
        ]);
    }

    public function create(): void
    {
        $this->auth->requireLogin();
        $this->csrf();

        $categoryId = (int)($_POST['category_id'] ?? 0);
        $locationId = (int)($_POST['location_id'] ?? 0);
        $subject = $this->field('subject', 180);
        $description = $this->field('description', 5000);

        if ($categoryId < 1 || $locationId < 1 || $subject === '' || $description === '') {
            Flash::error('Category, location, subject, and description are required.');
            $this->redirect('/tickets');
        }

        $file = $_FILES['attachment'] ?? null;
        $response = $this->api->postMultipart('tickets/create.php', [
            'category_id' => (string)$categoryId,
            'location_id' => (string)$locationId,
            'subject' => $subject,
            'description' => $description,
        ], is_array($file) ? $file : null, $this->token());

        if (!$response['ok']) {
            $this->handleApiFailure($response, '/tickets');
        }

        Flash::success('Ticket submitted.');
        $this->redirect('/tickets');
    }

    public function detail(string $id): void
    {
        $user = $this->auth->requireLogin();
        $ticketId = (int)$id;
        $response = $this->api->get('tickets/detail.php?id=' . $ticketId, $this->token());
        if (!$response['ok']) {
            $this->handleApiFailure($response, '/dashboard');
        }

        if (($user['role'] ?? '') === 'staff') {
            $this->api->postJson('comments/read.php', ['ticket_id' => $ticketId], $this->token());
        }

        $isTech = $this->auth->isTech();
        $assignableUsers = [];
        $urgencyTypes = [];
        $updateLoadError = '';

        if ($isTech) {
            $usersResponse = $this->api->get('users/assignable.php', $this->token());
            $urgenciesResponse = $this->api->get('urgency-types/index.php', $this->token());

            if (!$usersResponse['ok'] || !$urgenciesResponse['ok']) {
                $updateLoadError = !$usersResponse['ok']
                    ? ($usersResponse['message'] ?? 'Unable to load assignable users.')
                    : ($urgenciesResponse['message'] ?? 'Unable to load urgency types.');
            } else {
                $assignableUsers = $usersResponse['data'] ?? [];
                $urgencyTypes = $urgenciesResponse['data'] ?? [];
            }
        }

        $this->view->render('tickets/detail', [
            'title' => 'Ticket Detail',
            'user' => $user,
            'isTech' => $isTech,
            'isAdmin' => $this->auth->isAdmin(),
            'detail' => $response['data'] ?? [],
            'assignableUsers' => $assignableUsers,
            'urgencyTypes' => $urgencyTypes,
            'mutationStatuses' => self::MUTATION_STATUSES,
            'canUpdateTicket' => $isTech && $updateLoadError === '',
            'backPath' => $isTech ? '/admin/tickets' : '/tickets',
            'loadError' => $updateLoadError,
        ]);
    }

    public function heartbeat(): void
    {
        $this->auth->requireLogin();
        $this->csrf();

        $payload = [
            'client_id' => $this->field('client_id', 64),
            'ticket_id' => (int)($_POST['ticket_id'] ?? 0),
            'mode' => $this->field('mode', 12) ?: 'viewing',
        ];
        $response = $this->api->postJson('presence/heartbeat.php', $payload, $this->token());
        if (!$response['ok'] && (int)($response['status'] ?? 0) === 423) {
            $this->auth->logout();
        }

        http_response_code((int)($response['status'] ?? 500));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => $response['ok'] ? 'success' : 'error',
            'message' => $response['message'] ?? '',
            'data' => $response['data'] ?? null,
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function comment(string $id): void
    {
        $this->auth->requireRole(['admin', 'it_staff']);
        $this->csrf();

        $body = $this->field('body', 5000);
        if ($body === '') {
            Flash::error('Comment body is required.');
            $this->redirect('/tickets/' . (int)$id);
        }

        $response = $this->api->postJson('comments/create.php', [
            'ticket_id' => (int)$id,
            'body' => $body,
        ], $this->token());

        if (!$response['ok']) {
            $this->handleApiFailure($response, '/tickets/' . (int)$id);
        }

        Flash::success('Comment added.');
        $this->redirect('/tickets/' . (int)$id);
    }

    public function close(string $id): void
    {
        $this->auth->requireRole(['admin', 'it_staff']);
        $this->csrf();

        $response = $this->api->putJson('tickets/update.php', [
            'id' => (int)$id,
            'status' => 'Closed',
        ], $this->token());

        if (!$response['ok']) {
            $this->handleApiFailure($response, '/tickets/' . (int)$id);
        }

        Flash::success('Ticket closed.');
        $this->redirect('/tickets/' . (int)$id);
    }

    public function update(string $id): void
    {
        $this->auth->requireRole(['admin', 'it_staff']);
        $this->csrf();

        $ticketId = (int)$id;
        $status = $this->field('status', 60);
        $urgencyTypeId = trim((string)($_POST['urgency_type_id'] ?? ''));
        $assignedTo = trim((string)($_POST['assigned_to'] ?? ''));
        $payload = ['id' => $ticketId];

        if ($ticketId < 1) {
            Flash::error('Invalid ticket.');
            $this->redirect('/dashboard');
        }

        if ($status !== '') {
            if (!in_array($status, self::MUTATION_STATUSES, true)) {
                Flash::error('Choose a valid status.');
                $this->redirect('/tickets/' . $ticketId);
            }
            $payload['status'] = $status;
        }

        if ($urgencyTypeId !== '') {
            if (!ctype_digit($urgencyTypeId) || (int)$urgencyTypeId < 1) {
                Flash::error('Choose a valid urgency.');
                $this->redirect('/tickets/' . $ticketId);
            }
            $payload['urgency_type_id'] = (int)$urgencyTypeId;
        }

        if ($assignedTo !== '') {
            if ($assignedTo === 'unassigned') {
                $payload['assigned_to'] = null;
            } elseif (ctype_digit($assignedTo) && (int)$assignedTo > 0) {
                $payload['assigned_to'] = (int)$assignedTo;
            } else {
                Flash::error('Choose a valid assignee.');
                $this->redirect('/tickets/' . $ticketId);
            }
        }

        if (count($payload) === 1) {
            Flash::error('Choose at least one ticket field to update.');
            $this->redirect('/tickets/' . $ticketId);
        }

        $response = $this->api->putJson('tickets/update.php', $payload, $this->token());
        if (!$response['ok']) {
            $this->handleApiFailure($response, '/tickets/' . $ticketId);
        }

        Flash::success('Ticket updated.');
        $this->redirect('/tickets/' . $ticketId);
    }
}
