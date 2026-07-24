<?php
declare(strict_types=1);

namespace FFTicketWeb\Controllers;

use FFTicketWeb\Core\Flash;

final class TicketController extends BaseController
{
    public function index(): void
    {
        $user = $this->auth->requireLogin();
        if (($user['role'] ?? 'staff') !== 'staff') {
            $this->redirect('/admin/tickets');
        }

        $categories = $this->api->get('categories/index.php', $this->token());
        $locations = $this->api->get('locations/index.php', $this->token());
        $tickets = $this->api->get('tickets/index.php', $this->token());

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

        $this->view->render('tickets/detail', [
            'title' => 'Ticket Detail',
            'user' => $user,
            'isTech' => $this->auth->isTech(),
            'isAdmin' => $this->auth->isAdmin(),
            'detail' => $response['data'] ?? [],
        ]);
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
}
