<?php
declare(strict_types=1);

namespace FFTicketWeb\Core;

final class Config
{
    private array $values = [];

    public function __construct(private readonly string $root)
    {
        $this->values = [
            'WEB_BASE_PATH' => '/web',
            'API_BASE_URL' => '/backend/api',
            'API_TIMEOUT_SECONDS' => '30',
        ];

        $envFile = $root . '/.env';
        if (is_file($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }

                [$key, $value] = array_map('trim', explode('=', $line, 2));
                $this->values[$key] = trim($value, "\"'");
            }
        }

        foreach ($this->values as $key => $value) {
            $env = getenv($key);
            if ($env !== false && $env !== '') {
                $this->values[$key] = (string)$env;
            }
        }
    }

    public function basePath(): string
    {
        return rtrim($this->get('WEB_BASE_PATH', '/web'), '/') ?: '';
    }

    public function apiBaseUrl(): string
    {
        return rtrim($this->get('API_BASE_URL', '/backend/api'), '/');
    }

    public function apiTimeout(): int
    {
        $timeout = (int)$this->get('API_TIMEOUT_SECONDS', '30');
        return $timeout > 0 ? $timeout : 30;
    }

    public function absoluteApiBaseUrl(): string
    {
        $base = $this->apiBaseUrl();
        if (preg_match('#^https?://#i', $base)) {
            return $base;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . '/' . ltrim($base, '/');
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->values[$key] ?? $default;
    }
}
