<?php
declare(strict_types=1);

use FFTicketWeb\Controllers\AdminController;
use FFTicketWeb\Controllers\AttachmentController;
use FFTicketWeb\Controllers\AuthController;
use FFTicketWeb\Controllers\DashboardController;
use FFTicketWeb\Controllers\TicketController;
use FFTicketWeb\Core\Config;
use FFTicketWeb\Core\Router;
use FFTicketWeb\Core\View;
use FFTicketWeb\Services\ApiClient;
use FFTicketWeb\Services\AuthService;

define('WEB_ROOT', __DIR__);
define('WEB_APP', __DIR__ . '/app');

spl_autoload_register(static function (string $class): void {
    $prefix = 'FFTicketWeb\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file = WEB_APP . DIRECTORY_SEPARATOR . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

require_once WEB_APP . '/Support/helpers.php';

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$config = new Config(WEB_ROOT);
$api = new ApiClient($config);
$auth = new AuthService($api);
$view = new View(WEB_ROOT . '/views', $config);
$router = new Router($config);

$authController = new AuthController($view, $api, $auth);
$dashboardController = new DashboardController($view, $api, $auth);
$ticketController = new TicketController($view, $api, $auth);
$adminController = new AdminController($view, $api, $auth);
$attachmentController = new AttachmentController($view, $api, $auth);

$router->get('/', [$dashboardController, 'index']);
$router->get('/dashboard', [$dashboardController, 'index']);
$router->get('/login', [$authController, 'loginForm']);
$router->post('/login', [$authController, 'login']);
$router->post('/logout', [$authController, 'logout']);
$router->get('/change-password', [$authController, 'changePasswordForm']);
$router->post('/change-password', [$authController, 'changePassword']);

$router->get('/tickets', [$ticketController, 'index']);
$router->post('/tickets/create', [$ticketController, 'create']);
$router->get('/tickets/{id}', [$ticketController, 'detail']);
$router->post('/tickets/{id}/comment', [$ticketController, 'comment']);
$router->post('/tickets/{id}/close', [$ticketController, 'close']);
$router->post('/tickets/{id}/update', [$ticketController, 'update']);
$router->get('/attachments/{id}/download', [$attachmentController, 'download']);

$router->get('/admin/tickets', [$adminController, 'tickets']);
$router->post('/admin/tickets/update', [$adminController, 'updateTicket']);
$router->get('/admin/kanban', [$adminController, 'kanban']);
$router->post('/admin/kanban/move', [$adminController, 'moveTicket']);
$router->get('/admin/users', [$adminController, 'users']);
$router->post('/admin/users/create', [$adminController, 'createUser']);
$router->post('/admin/users/update', [$adminController, 'updateUser']);
$router->post('/admin/users/delete', [$adminController, 'deleteUser']);
$router->get('/admin/customize', [$adminController, 'customize']);
$router->post('/admin/customize/add', [$adminController, 'addOption']);
$router->post('/admin/customize/update', [$adminController, 'updateOption']);
$router->post('/admin/customize/deactivate', [$adminController, 'deactivateOption']);
$router->get('/reports/export', [$adminController, 'exportReport']);

$router->dispatch();
