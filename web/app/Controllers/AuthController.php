<?php
declare(strict_types=1);

namespace FFTicketWeb\Controllers;

use FFTicketWeb\Core\Flash;

final class AuthController extends BaseController
{
    public function loginForm(): void
    {
        if ($this->auth->user() !== null) {
            $this->redirect('/dashboard');
        }

        $this->view->render('auth/login', ['title' => 'Sign in'], 'auth');
    }

    public function login(): void
    {
        $this->csrf();
        $email = $this->field('email', 190);
        $password = (string)($_POST['password'] ?? '');
        $remember = isset($_POST['remember']);

        if ($email === '' || $password === '') {
            Flash::error('Email and password are required.');
            $this->redirect('/login');
        }

        $response = $this->auth->login($email, $password, $remember);
        if (!$response['ok']) {
            Flash::error($response['message'] ?? 'Unable to sign in.');
            $this->redirect('/login');
        }

        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        $this->csrf();
        $this->auth->logout();
        Flash::success('Signed out.');
        $this->redirect('/login');
    }

    public function changePasswordForm(): void
    {
        $this->auth->requireLogin();
        $this->view->render('auth/change-password', [
            'title' => 'Change Password',
            'user' => $this->auth->user(),
            'isTech' => $this->auth->isTech(),
            'isAdmin' => $this->auth->isAdmin(),
        ]);
    }

    public function changePassword(): void
    {
        $this->auth->requireLogin();
        $this->csrf();

        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($current === '' || $new === '') {
            Flash::error('Current password and new password are required.');
            $this->redirect('/change-password');
        }

        if ($new !== $confirm) {
            Flash::error('New password and confirmation do not match.');
            $this->redirect('/change-password');
        }

        $response = $this->api->postJson('auth/change-password.php', [
            'current_password' => $current,
            'new_password' => $new,
        ], $this->token());

        if (!$response['ok']) {
            $this->handleApiFailure($response, '/change-password');
        }

        Flash::success('Password changed.');
        $this->redirect('/change-password');
    }
}
