<?php
declare(strict_types=1);

namespace FFTicketWeb\Controllers;

use FFTicketWeb\Core\Flash;

final class AttachmentController extends BaseController
{
    public function download(string $id): void
    {
        $this->auth->requireLogin();
        $response = $this->api->download('attachments/download.php?id=' . urlencode($id), $this->token());

        if (!$response['ok']) {
            Flash::error($response['message'] ?? 'Unable to download attachment.');
            $path = parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH);
            header('Location: ' . ($path ?: $this->url('/dashboard')));
            exit;
        }

        $headers = $response['headers'] ?? [];
        $contentType = $this->safeHeader($headers['content-type'] ?? ($response['content_type'] ?? 'application/octet-stream'));
        $disposition = $this->safeHeader($headers['content-disposition'] ?? 'attachment');

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: ' . $disposition);
        if (isset($headers['content-length'])) {
            header('Content-Length: ' . $headers['content-length']);
        }
        echo $response['body'];
        exit;
    }

    private function safeHeader(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }
}
