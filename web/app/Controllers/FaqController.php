<?php
declare(strict_types=1);

namespace FFTicketWeb\Controllers;

final class FaqController extends BaseController
{
    public function index(): void
    {
        if ($this->auth->user() === null || $this->token() === null) {
            $this->respond(false, 'Please sign in to view FAQs.', [], 401);
        }

        $response = $this->api->get('faqs/index.php', $this->token());
        $this->respond(
            (bool)$response['ok'],
            (string)($response['message'] ?? 'Unable to load FAQs right now.'),
            $response['data'] ?? [],
            (int)($response['status'] ?? 0)
        );
    }

    private function respond(bool $ok, string $message, mixed $data, int $status): never
    {
        if (!$ok) {
            http_response_code($status >= 400 && $status <= 599 ? $status : 502);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => $ok ? 'success' : 'error',
            'message' => $message,
            'data' => $data,
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
}
