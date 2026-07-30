<?php
declare(strict_types=1);

namespace FFTicketWeb\Core;

final class View
{
    public function __construct(
        private readonly string $viewsPath,
        private readonly Config $config
    ) {
    }

    public function render(string $template, array $data = [], string $layout = 'app'): void
    {
        $templateFile = $this->viewsPath . '/' . $template . '.php';
        if (!is_file($templateFile)) {
            http_response_code(500);
            echo 'View not found.';
            return;
        }

        $basePath = $this->config->basePath();
        $url = static fn(string $path = ''): string => $basePath . ($path === '/' ? '' : '/' . ltrim($path, '/'));
        $asset = static function (string $path) use ($basePath): string {
            $path = ltrim($path, '/');
            $file = dirname(__DIR__, 2) . '/public/assets/' . $path;
            $version = is_file($file) ? '?v=' . (string)filemtime($file) : '';

            return $basePath . '/public/assets/' . $path . $version;
        };
        $flash = Flash::pull();
        $csrf = static fn(): string => Csrf::field();

        extract($data, EXTR_SKIP);
        ob_start();
        require $templateFile;
        $content = ob_get_clean();

        $layoutFile = $this->viewsPath . '/layouts/' . $layout . '.php';
        require $layoutFile;
    }
}
