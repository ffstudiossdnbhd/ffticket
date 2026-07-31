<?php
declare(strict_types=1);

namespace FFTicketWeb\Controllers;

use FFTicketWeb\Core\Flash;

final class AdminController extends BaseController
{
    private const STATUSES = ['All', 'Open', 'In Progress', 'Pending User Input', 'Closed'];
    private const MUTATION_STATUSES = ['Open', 'In Progress', 'Pending User Input', 'Closed'];

    public function tickets(): void
    {
        $user = $this->auth->requireRole(['admin', 'it_staff']);
        $query = [];
        foreach (['status', 'urgency', 'search'] as $field) {
            $value = trim((string)($_GET[$field] ?? ''));
            if ($value !== '' && $value !== 'All') {
                $query[$field] = $value;
            }
        }

        $defaultFrom = date('Y-m-d', strtotime('-30 days'));
        $defaultTo = date('Y-m-d');
        $fromInput = trim((string)($_GET['from'] ?? $defaultFrom));
        $toInput = trim((string)($_GET['to'] ?? $defaultTo));
        $dateError = '';

        if (!$this->isValidDate($fromInput) || !$this->isValidDate($toInput)) {
            $dateError = 'Choose valid Created From and Created To dates.';
            $fromInput = $defaultFrom;
            $toInput = $defaultTo;
        } elseif ($fromInput > $toInput) {
            $dateError = 'Created From must be on or before Created To.';
            $fromInput = $defaultFrom;
            $toInput = $defaultTo;
        }

        $query['from'] = $fromInput;
        $query['to'] = $toInput;
        $ticketsPath = 'tickets/index.php?' . http_build_query($query);
        $tickets = $this->api->get($ticketsPath, $this->token());
        $users = $this->api->get('users/assignable.php', $this->token());
        $urgencies = $this->api->get('urgency-types/index.php?include_inactive=1', $this->token());

        $this->view->render('admin/tickets', [
            'title' => 'Ticket Overview',
            'user' => $user,
            'isTech' => true,
            'isAdmin' => $this->auth->isAdmin(),
            'tickets' => $tickets['data'] ?? [],
            'users' => $users['data'] ?? [],
            'urgencyTypes' => $urgencies['data'] ?? [],
            'statuses' => self::STATUSES,
            'mutationStatuses' => self::MUTATION_STATUSES,
            'filters' => [
                'status' => $_GET['status'] ?? 'All',
                'urgency' => $_GET['urgency'] ?? 'All',
                'search' => $_GET['search'] ?? '',
                'from' => $fromInput,
                'to' => $toInput,
            ],
            'loadError' => $dateError !== '' ? $dateError : ((!$tickets['ok'] || !$users['ok'] || !$urgencies['ok'])
                ? (($tickets['message'] ?? '') ?: ($users['message'] ?? '') ?: ($urgencies['message'] ?? 'Unable to load ticket overview.'))
                : ''),
        ]);
    }

    public function updateTicket(): void
    {
        $this->auth->requireRole(['admin', 'it_staff']);
        $this->csrf();

        $ticketId = (int)($_POST['id'] ?? 0);
        $status = $this->field('status', 60);
        $urgencyTypeId = (int)($_POST['urgency_type_id'] ?? 0);
        $assignedTo = trim((string)($_POST['assigned_to'] ?? 'no_change'));

        if ($ticketId < 1 || !in_array($status, self::MUTATION_STATUSES, true)) {
            Flash::error('Select a ticket and valid status.');
            $this->redirect('/admin/tickets');
        }

        $payload = [
            'id' => $ticketId,
            'status' => $status,
        ];
        if ($urgencyTypeId > 0) {
            $payload['urgency_type_id'] = $urgencyTypeId;
        }
        if ($assignedTo !== 'no_change') {
            if ($assignedTo === '') {
                $payload['assigned_to'] = null;
            } elseif (ctype_digit($assignedTo) && (int)$assignedTo > 0) {
                $payload['assigned_to'] = (int)$assignedTo;
            } else {
                Flash::error('Choose a valid assignee.');
                $this->redirect('/admin/tickets');
            }
        }

        $response = $this->api->putJson('tickets/update.php', $payload, $this->token());
        if (!$response['ok']) {
            $this->handleApiFailure($response, '/admin/tickets');
        }

        Flash::success('Ticket updated.');
        $this->redirect('/admin/tickets');
    }

    public function kanban(): void
    {
        $user = $this->auth->requireRole(['admin', 'it_staff']);
        $response = $this->api->get('tickets/index.php', $this->token());
        $columns = [
            'Open' => [],
            'In Progress' => [],
            'Pending User Input' => [],
            'Closed' => [],
        ];
        foreach (($response['data'] ?? []) as $ticket) {
            $status = (string)($ticket['status'] ?? 'Open');
            $columns[$status][] = $ticket;
        }

        $this->view->render('admin/kanban', [
            'title' => 'Kanban Board',
            'user' => $user,
            'isTech' => true,
            'isAdmin' => $this->auth->isAdmin(),
            'columns' => $columns,
            'loadError' => $response['ok'] ? '' : ($response['message'] ?? 'Unable to load Kanban board.'),
        ]);
    }

    public function moveTicket(): void
    {
        $this->auth->requireRole(['admin', 'it_staff']);
        $this->csrf();
        $ticketId = (int)($_POST['id'] ?? 0);
        $status = $this->field('status', 60);

        if ($ticketId < 1 || !in_array($status, self::MUTATION_STATUSES, true)) {
            Flash::error('Select a ticket and valid status.');
            $this->redirect('/admin/kanban');
        }

        $response = $this->api->putJson('tickets/update.php', [
            'id' => $ticketId,
            'status' => $status,
        ], $this->token());

        if (!$response['ok']) {
            $this->handleApiFailure($response, '/admin/kanban');
        }

        Flash::success('Ticket moved.');
        $this->redirect('/admin/kanban');
    }

    public function users(): void
    {
        $user = $this->auth->requireRole(['admin']);
        $response = $this->api->get('users/index.php', $this->token());

        $this->view->render('admin/users', [
            'title' => 'User Management',
            'user' => $user,
            'isTech' => true,
            'isAdmin' => true,
            'users' => $response['data'] ?? [],
            'roles' => ['staff', 'it_staff', 'admin'],
            'loadError' => $response['ok'] ? '' : ($response['message'] ?? 'Unable to load users.'),
        ]);
    }

    public function createUser(): void
    {
        $this->auth->requireRole(['admin']);
        $this->csrf();

        $response = $this->api->postJson('users/crud.php', [
            'name' => $this->field('name', 120),
            'nickname' => $this->field('nickname', 120),
            'email' => $this->field('email', 190),
            'password' => (string)($_POST['password'] ?? ''),
            'role' => $this->field('role', 30),
        ], $this->token());

        if (!$response['ok']) {
            $this->handleApiFailure($response, '/admin/users');
        }

        $temporaryPassword = (string)($response['data']['temporary_password'] ?? '');
        Flash::success($temporaryPassword !== '' ? 'User created. Temporary password: ' . $temporaryPassword : 'User created.');
        $this->redirect('/admin/users');
    }

    public function updateUser(): void
    {
        $this->auth->requireRole(['admin']);
        $this->csrf();

        $password = (string)($_POST['password'] ?? '');
        $response = $this->api->putJson('users/crud.php', [
            'id' => (int)($_POST['id'] ?? 0),
            'name' => $this->field('name', 120),
            'nickname' => $this->field('nickname', 120),
            'email' => $this->field('email', 190),
            'role' => $this->field('role', 30),
            'password' => trim($password) === '' ? null : $password,
        ], $this->token());

        if (!$response['ok']) {
            $this->handleApiFailure($response, '/admin/users');
        }

        Flash::success('User updated.');
        $this->redirect('/admin/users');
    }

    public function deleteUser(): void
    {
        $this->auth->requireRole(['admin']);
        $this->csrf();

        $response = $this->api->deleteJson('users/crud.php', [
            'id' => (int)($_POST['id'] ?? 0),
        ], $this->token());

        if (!$response['ok']) {
            $this->handleApiFailure($response, '/admin/users');
        }

        Flash::success('User deleted.');
        $this->redirect('/admin/users');
    }

    public function faqs(): void
    {
        $user = $this->auth->requireRole(['admin']);
        $response = $this->api->get('faqs/index.php', $this->token());
        $categories = $this->api->get('categories/index.php?include_inactive=1', $this->token());
        $this->view->render('admin/faqs', [
            'title' => 'FAQ Management',
            'user' => $user,
            'isTech' => true,
            'isAdmin' => true,
            'faqs' => $response['data'] ?? [],
            'categories' => $categories['data'] ?? [],
            'loadError' => ($response['ok'] && $categories['ok'])
                ? '' : (($response['message'] ?? '') ?: ($categories['message'] ?? 'Unable to load FAQ management data.')),
        ]);
    }

    public function createFaq(): void
    {
        $this->mutateFaq('POST');
    }

    public function updateFaq(): void
    {
        $this->mutateFaq('PUT');
    }

    public function deleteFaq(): void
    {
        $this->mutateFaq('DELETE');
    }

    public function timeouts(): void
    {
        $user = $this->auth->requireRole(['admin']);
        $response = $this->api->get('admin/timeouts.php', $this->token());
        $this->view->render('admin/timeouts', [
            'title' => 'Timeouts',
            'user' => $user,
            'isTech' => true,
            'isAdmin' => true,
            'timeoutUsers' => $response['data'] ?? [],
            'loadError' => $response['ok'] ? '' : ($response['message'] ?? 'Unable to load timeout users.'),
        ]);
    }

    public function saveTimeout(): void
    {
        $this->auth->requireRole(['admin']);
        $this->csrf();
        $userId = (int)($_POST['user_id'] ?? 0);
        $action = $this->field('action', 12);
        $response = $this->api->postJson('admin/timeouts.php', [
            'action' => $action === 'update' ? 'update' : 'start',
            'user_id' => $userId,
            'release_at' => $this->field('release_at', 16),
        ], $this->token());
        if (!$response['ok']) {
            $this->handleApiFailure($response, '/admin/timeouts');
        }
        Flash::success($response['message'] ?? 'Timeout saved.');
        $this->redirect('/admin/timeouts');
    }

    public function releaseTimeout(): void
    {
        $this->auth->requireRole(['admin']);
        $this->csrf();
        $response = $this->api->postJson('admin/timeouts.php', [
            'action' => 'release',
            'user_id' => (int)($_POST['user_id'] ?? 0),
        ], $this->token());
        if (!$response['ok']) {
            $this->handleApiFailure($response, '/admin/timeouts');
        }
        Flash::success($response['message'] ?? 'User released.');
        $this->redirect('/admin/timeouts');
    }

    public function customize(): void
    {
        $user = $this->auth->requireRole(['admin', 'it_staff']);
        $categories = $this->api->get('categories/index.php?include_inactive=1', $this->token());
        $urgencies = $this->api->get('urgency-types/index.php?include_inactive=1', $this->token());
        $locations = $this->api->get('locations/index.php?include_inactive=1', $this->token());

        $this->view->render('admin/customize', [
            'title' => 'Customize Ticket',
            'user' => $user,
            'isTech' => true,
            'isAdmin' => $this->auth->isAdmin(),
            'groups' => [
                'categories' => ['label' => 'Categories', 'add' => 'Add Category', 'items' => $categories['data'] ?? []],
                'urgency-types' => ['label' => 'Urgency Types', 'add' => 'Add Urgency', 'items' => $urgencies['data'] ?? []],
                'locations' => ['label' => 'Locations', 'add' => 'Add Location', 'items' => $locations['data'] ?? []],
            ],
            'loadError' => (!$categories['ok'] || !$urgencies['ok'] || !$locations['ok'])
                ? (($categories['message'] ?? '') ?: ($urgencies['message'] ?? '') ?: ($locations['message'] ?? 'Unable to load customization options.'))
                : '',
        ]);
    }

    public function addOption(): void
    {
        $this->mutateOption('POST');
    }

    public function updateOption(): void
    {
        $this->mutateOption('PUT');
    }

    public function deactivateOption(): void
    {
        $this->mutateOption('DELETE');
    }

    public function exportReport(): void
    {
        $this->auth->requireRole(['admin', 'it_staff']);
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? (string)$_GET['from'] : date('Y-m-d', strtotime('-30 days'));
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? '')) ? (string)$_GET['to'] : date('Y-m-d');
        $response = $this->api->download('reports/export.php?' . http_build_query(['from' => $from, 'to' => $to]), $this->token());

        if (!$response['ok']) {
            $this->handleApiFailure($response, '/admin/tickets');
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ffticket-report-' . $from . '-to-' . $to . '.csv"');
        echo $response['body'];
        exit;
    }

    private function mutateOption(string $method): void
    {
        $this->auth->requireRole(['admin', 'it_staff']);
        $this->csrf();

        $type = (string)($_POST['type'] ?? '');
        $endpoint = option_endpoint($type);
        if ($endpoint === null) {
            Flash::error('Invalid ticket option type.');
            $this->redirect('/admin/customize');
        }
        $returnPath = '/admin/customize?type=' . urlencode($type);

        if ($method === 'POST') {
            $response = $this->api->postJson($endpoint, [
                'name' => $this->field('name', 100),
                'description' => $this->field('description', 255),
            ], $this->token());
        } elseif ($method === 'PUT') {
            $response = $this->api->putJson($endpoint, [
                'id' => (int)($_POST['id'] ?? 0),
                'name' => $this->field('name', 100),
                'description' => $this->field('description', 255),
                'is_active' => isset($_POST['is_active']),
            ], $this->token());
        } else {
            $response = $this->api->deleteJson($endpoint, [
                'id' => (int)($_POST['id'] ?? 0),
            ], $this->token());
        }

        if (!$response['ok']) {
            $this->handleApiFailure($response, $returnPath);
        }

        Flash::success($method === 'DELETE' ? 'Option deactivated.' : 'Option saved.');
        $this->redirect($returnPath);
    }

    private function mutateFaq(string $method): void
    {
        $this->auth->requireRole(['admin']);
        $this->csrf();
        $payload = ['id' => (int)($_POST['id'] ?? 0)];
        $categoryIdInput = trim((string)($_POST['category_id'] ?? ''));
        $categoryId = $categoryIdInput === '' ? null : ((ctype_digit($categoryIdInput) ? (int)$categoryIdInput : null));
        if ($categoryIdInput !== '' && $categoryId === null) {
            Flash::error('Invalid FAQ category.');
            $this->redirect('/admin/faqs');
        }

        if ($method !== 'DELETE') {
            $payload['title'] = $this->field('title', 180);
            $payload['description'] = $this->field('description', 5000);
            $payload['category_id'] = $categoryId;
        }

        $response = match ($method) {
            'POST' => $this->api->postJson('faqs/crud.php', $payload, $this->token()),
            'PUT' => $this->api->putJson('faqs/crud.php', $payload, $this->token()),
            default => $this->api->deleteJson('faqs/crud.php', $payload, $this->token()),
        };
        if (!$response['ok']) {
            $this->handleApiFailure($response, '/admin/faqs');
        }
        Flash::success($response['message'] ?? 'FAQ saved.');
        $this->redirect('/admin/faqs');
    }

    private function isValidDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value;
    }
}
