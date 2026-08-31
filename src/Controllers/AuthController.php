<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Controllers;

use Mengo\IdApproval\Repositories\UserRepository;
use Mengo\IdApproval\Security\SessionManager;
use Mengo\IdApproval\Services\AuthService;
use Mengo\IdApproval\Support\Config;
use Mengo\IdApproval\Support\Request;
use Mengo\IdApproval\Support\Response;
use Mengo\IdApproval\Support\View;

class AuthController
{
    private AuthService $authService;
    private UserRepository $userRepo;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->userRepo = new UserRepository();
    }

    public function showLogin(Request $request): void
    {
        if (SessionManager::isAuthenticated()) {
            Response::redirect('/dashboard');
        }

        $allUsers = $this->userRepo->all();

        View::render('auth/login', [
            'pageTitle' => 'Sign In — Mengo Hospital ID System',
            'quickUsers' => $allUsers,
            'isDev' => !Config::isProduction()
        ], 'layouts/auth');
    }

    public function login(Request $request): void
    {
        $username = (string)($request->post('username') ?? $request->post('email', ''));
        $password = (string)$request->post('password', '');

        try {
            $user = $this->authService->authenticate($username, $password, $request);

            if ($user->force_password_change) {
                SessionManager::flash('warning', 'Please set a new password before continuing.');
                Response::redirect('/change-password');
            }

            SessionManager::flash('success', "Welcome back, {$user->name}!");
            Response::redirect('/dashboard');
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            Response::redirect('/login');
        }
    }

    public function quickLogin(Request $request): void
    {
        if (Config::isProduction()) {
            Response::forbidden("Quick login is disabled in production mode.");
        }

        $userId = (int)$request->post('user_id', 0);
        $user = $this->userRepo->findById($userId);

        if (!$user) {
            SessionManager::flash('error', 'Selected user account not found.');
            Response::redirect('/login');
        }

        SessionManager::setUser($user);
        $this->userRepo->updateLastLogin($user->id);

        SessionManager::flash('info', "Switched active account to: {$user->name} ({$user->role})");
        Response::redirect('/dashboard');
    }

    public function showChangePassword(Request $request): void
    {
        $user = SessionManager::getUser();
        if (!$user) {
            Response::redirect('/login');
        }

        View::render('auth/first_login_change_password', [
            'pageTitle' => 'Change Password — Mengo Hospital ID System',
            'user' => $user
        ], 'layouts/auth');
    }

    public function changePassword(Request $request): void
    {
        $userId = SessionManager::getUserId();
        if (!$userId) {
            Response::redirect('/login');
        }

        $current = (string)$request->post('current_password', '');
        $new = (string)$request->post('new_password', '');
        $confirm = (string)$request->post('confirm_password', '');

        if ($new !== $confirm) {
            SessionManager::flash('error', 'New password and confirmation do not match.');
            Response::redirect('/change-password');
        }

        try {
            $this->authService->changePassword($userId, $current, $new, $request);
            SessionManager::flash('success', 'Your password has been changed successfully.');
            Response::redirect('/dashboard');
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            Response::redirect('/change-password');
        }
    }

    public function logout(Request $request): void
    {
        $this->authService->logout($request);
        SessionManager::flash('info', 'You have been signed out.');
        Response::redirect('/login');
    }
}
