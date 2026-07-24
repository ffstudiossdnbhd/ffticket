<?php
declare(strict_types=1);

namespace FFTicketWeb\Controllers;

final class DashboardController extends BaseController
{
    public function index(): void
    {
        $user = $this->auth->requireLogin();
        $this->redirect(($user['role'] ?? 'staff') === 'staff' ? '/tickets' : '/admin/tickets');
    }
}
