<?php
declare(strict_types=1);

namespace FFTicketWeb\Services;

use FFTicketWeb\Core\Config;

final class ApiClient
{
    public function __construct(private readonly Config $config)
    {
    }

    public function get(string $path, ?string $token = null): array
    {
        return $this->send('GET', $path, null, $token);
    }

    public function postJson(string $path, array $payload, ?string $token = null): array
    {
        return $this->send('POST', $path, $payload, $token);
    }

    public function putJson(string $path, array $payload, ?string $token = null): array
    {
        return $this->send('PUT', $path, $payload, $token);
    }

    public function deleteJson(string $path, array $payload, ?string $token = null): array
    {
        return $this->send('DELETE', $path, $payload, $token);
    }

    public function postMultipart(string $path, array $fields, ?array $file, ?string $token = null): array
    {
        if (!function_exists('curl_init')) {
            return $this->failure('PHP cURL is required for API requests.');
        }

        $payload = $fields;
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
                return $this->failure('File upload failed.');
            }

            $payload['attachment'] = new \CURLFile(
                (string)$file['tmp_name'],
                (string)($file['type'] ?? 'application/octet-stream'),
                (string)($file['name'] ?? 'attachment')
            );
        }

        return $this->curl('POST', $path, $payload, $token, false, true);
    }

    public function download(string $path, ?string $token = null): array
    {
        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'PHP cURL is required for API requests.',
                'body' => '',
                'headers' => [],
            ];
        }

        return $this->curl('GET', $path, null, $token, true);
    }

    private function send(string $method, string $path, ?array $payload, ?string $token): array
    {
        if (!function_exists('curl_init')) {
            return $this->failure('PHP cURL is required for API requests.');
        }

        $response = $this->curl($method, $path, $payload, $token, false);
        if (($response['raw'] ?? false) === true) {
            return $response;
        }

        return $response;
    }

    private function curl(
        string $method,
        string $path,
        mixed $payload,
        ?string $token,
        bool $raw,
        bool $multipart = false
    ): array
    {
        $headers = ['Accept: ' . ($raw ? '*/*' : 'application/json')];
        $url = $this->url($path);
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $this->config->apiTimeout(),
        ]);

        if ($token !== null && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        if (is_array($payload)) {
            if ($multipart || $this->hasCurlFile($payload)) {
                curl_setopt($handle, CURLOPT_POSTFIELDS, $payload);
            } else {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
            }
        }

        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($handle);
        if ($result === false) {
            $message = curl_error($handle) ?: 'Unable to reach the FFTicket API.';
            curl_close($handle);
            return $raw
                ? ['ok' => false, 'status' => 0, 'message' => $message, 'body' => '', 'headers' => []]
                : $this->failure($message);
        }

        $headerSize = (int)curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $statusCode = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $contentType = (string)curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        curl_close($handle);

        $rawHeaders = substr($result, 0, $headerSize);
        $body = substr($result, $headerSize);
        $parsedHeaders = $this->parseHeaders($rawHeaders);

        if ($raw || str_contains(strtolower($contentType), 'text/csv')) {
            return [
                'ok' => $statusCode >= 200 && $statusCode < 300,
                'status' => $statusCode,
                'message' => $statusCode >= 200 && $statusCode < 300 ? 'Download ready.' : $this->messageFromBody($body),
                'body' => $body,
                'headers' => $parsedHeaders,
                'content_type' => $contentType,
                'raw' => true,
            ];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return $this->failure('The API returned a response the app could not read.', $statusCode);
        }

        $ok = $statusCode >= 200 && $statusCode < 300 && ($decoded['status'] ?? '') !== 'error';
        return [
            'ok' => $ok,
            'status' => $statusCode,
            'message' => (string)($decoded['message'] ?? ($ok ? 'Request complete.' : 'Request failed.')),
            'data' => $decoded['data'] ?? null,
        ];
    }

    private function url(string $path): string
    {
        return $this->config->absoluteApiBaseUrl() . '/' . ltrim($path, '/');
    }

    private function hasCurlFile(array $payload): bool
    {
        foreach ($payload as $value) {
            if ($value instanceof \CURLFile) {
                return true;
            }
        }
        return false;
    }

    private function parseHeaders(string $headers): array
    {
        $parsed = [];
        foreach (explode("\r\n", trim($headers)) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $parsed[strtolower(trim($key))] = trim($value);
        }
        return $parsed;
    }

    private function messageFromBody(string $body): string
    {
        $decoded = json_decode($body, true);
        return is_array($decoded) ? (string)($decoded['message'] ?? 'Download failed.') : 'Download failed.';
    }

    private function failure(string $message, int $status = 0): array
    {
        return [
            'ok' => false,
            'status' => $status,
            'message' => $message,
            'data' => null,
        ];
    }
}
