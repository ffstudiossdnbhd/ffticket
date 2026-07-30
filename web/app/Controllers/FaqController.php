<?php
declare(strict_types=1);

namespace FFTicketWeb\Controllers;

final class FaqController extends BaseController
{
    public function index(): void
    {
        $this->auth->requireLogin();
        $response = $this->api->get('faqs/index.php', $this->token());
        if (!$response['ok']) {
            $this->handleApiFailure($response, '/dashboard');
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'success',
            'data' => $response['data'] ?? [],
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
}
