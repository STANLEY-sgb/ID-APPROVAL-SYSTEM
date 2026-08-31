<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Controllers;

use Mengo\IdApproval\Models\Role;
use Mengo\IdApproval\Models\User;
use Mengo\IdApproval\Repositories\AuditLogRepository;
use Mengo\IdApproval\Repositories\UserRepository;
use Mengo\IdApproval\Security\PasswordHasher;
use Mengo\IdApproval\Security\SessionManager;
use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Request;
use Mengo\IdApproval\Support\Response;
use Mengo\IdApproval\Support\View;

class AdminController
{
    private UserRepository $userRepo;
    private AuditLogRepository $auditRepo;

    public function __construct()
    {
        $this->userRepo = new UserRepository();
        $this->auditRepo = new AuditLogRepository();
    }

    public function hrAccounts(Request $request): void
    {
        $allUsers = $this->userRepo->all();
        $userStats = array_map(function($u) {
            $arr = (array)$u;
            $arr['approval_count'] = 0;
            return $arr;
        }, $allUsers);

        View::render('admin/hr_accounts', [
            'pageTitle' => 'System User Administration — Mengo Hospital ID System',
            'hrManagers' => $userStats
        ]);
    }

    public function createHrAccount(Request $request): void
    {
        $name = trim((string)$request->post('name', ''));
        $username = trim((string)$request->post('username', ''));
        $email = strtolower(trim((string)$request->post('email', '')));
        $role = trim((string)$request->post('role', Role::HR_MANAGER));
        $password = (string)$request->post('password', '');
        $phone = trim((string)$request->post('phone', ''));
        $department = trim((string)$request->post('department', 'Administration'));

        if (empty($name) || empty($password)) {
            SessionManager::flash('error', 'Full Name and Initial Password are required.');
            Response::redirect('/admin/hr-accounts');
            return;
        }

        if (empty($username)) {
            $username = strtolower(str_replace(' ', '.', $name));
        }

        if (empty($email)) {
            $email = "{$username}@mengohospital.org";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            SessionManager::flash('error', "The email address '{$email}' is invalid.");
            Response::redirect('/admin/hr-accounts');
            return;
        }

        if (strlen($password) < 8) {
            SessionManager::flash('error', 'Password must be at least 8 characters long.');
            Response::redirect('/admin/hr-accounts');
            return;
        }

        if ($this->userRepo->isUsernameTaken($username)) {
            SessionManager::flash('error', "An account with username '{$username}' already exists.");
            Response::redirect('/admin/hr-accounts');
            return;
        }

        if ($this->userRepo->isEmailTaken($email)) {
            SessionManager::flash('error', "The email address '{$email}' is already assigned to another account.");
            Response::redirect('/admin/hr-accounts');
            return;
        }

        $adminUser = User::fromArray(SessionManager::getUser());
        $hash = PasswordHasher::hash($password);
        $prefix = match($role) {
            Role::ADMINISTRATOR => 'MH-ADM-',
            Role::HR_MANAGER => 'MH-HR-',
            Role::DESIGNER => 'MH-DES-',
            Role::PRINTING_OFFICER => 'MH-PRT-',
            default => 'MH-STF-'
        };
        $staffId = $prefix . strtoupper(substr(uniqid(), -5));

        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $newUserId = $this->userRepo->create([
                'staff_id' => $staffId,
                'username' => $username,
                'name' => $name,
                'email' => $email,
                'password_hash' => $hash,
                'role' => $role,
                'department' => $department ?: 'Administration',
                'phone' => $phone ?: null,
                'status' => 'ACTIVE',
                'force_password_change' => 0
            ]);

            $this->auditRepo->create([
                'user_id' => $adminUser->id,
                'user_name' => $adminUser->name,
                'user_role' => $adminUser->role,
                'action' => 'USER_ACCOUNT_CREATED',
                'details' => "Administrator {$adminUser->name} created new {$role} account for {$name} (Username: {$username}, Email: {$email}, Staff ID: {$staffId}).",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[ADMIN_CREATE_USER_ERROR] ' . $e->getMessage());
            SessionManager::flash('error', 'Unable to create user account due to a database constraint error.');
            Response::redirect('/admin/hr-accounts');
            return;
        }

        SessionManager::flash('success', "Account for '{$name}' ({$role}) created successfully with username: {$username}");
        Response::redirect('/admin/hr-accounts');
    }

    public function toggleHrStatus(Request $request): void
    {
        $userId = (int)$request->post('user_id', 0);
        $targetUser = $this->userRepo->findById($userId);

        if (!$targetUser) {
            SessionManager::flash('error', 'User account not found.');
            Response::redirect('/admin/hr-accounts');
            return;
        }

        $adminUser = User::fromArray(SessionManager::getUser());
        $newStatus = $targetUser->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';

        $this->userRepo->updateStatus($userId, $newStatus);

        $this->auditRepo->create([
            'user_id' => $adminUser->id,
            'user_name' => $adminUser->name,
            'user_role' => $adminUser->role,
            'action' => 'USER_STATUS_CHANGED',
            'details' => "Administrator {$adminUser->name} changed {$targetUser->name} ({$targetUser->role}) status from {$targetUser->status} to {$newStatus}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        SessionManager::flash('success', "Account for '{$targetUser->name}' is now {$newStatus}.");
        Response::redirect('/admin/hr-accounts');
    }

    public function resetHrPassword(Request $request): void
    {
        $userId = (int)$request->post('user_id', 0);
        $newPassword = (string)$request->post('new_password', '');

        if (strlen($newPassword) < 8) {
            SessionManager::flash('error', 'New password must be at least 8 characters.');
            Response::redirect('/admin/hr-accounts');
            return;
        }

        $targetUser = $this->userRepo->findById($userId);
        if (!$targetUser) {
            SessionManager::flash('error', 'User account not found.');
            Response::redirect('/admin/hr-accounts');
            return;
        }

        $adminUser = User::fromArray(SessionManager::getUser());
        $hash = PasswordHasher::hash($newPassword);
        $this->userRepo->updatePassword($userId, $hash);

        $this->auditRepo->create([
            'user_id' => $adminUser->id,
            'user_name' => $adminUser->name,
            'user_role' => $adminUser->role,
            'action' => 'USER_PASSWORD_RESET',
            'details' => "Administrator {$adminUser->name} reset password for user {$targetUser->name} (Username: {$targetUser->username}).",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        SessionManager::flash('success', "Password for user '{$targetUser->name}' reset successfully!");
        Response::redirect('/admin/hr-accounts');
    }

    public function updateUserAccount(Request $request): void
    {
        $userId = (int)$request->post('user_id', 0);
        $name = trim((string)$request->post('name', ''));
        $username = trim((string)$request->post('username', ''));
        $email = strtolower(trim((string)$request->post('email', '')));
        $role = trim((string)$request->post('role', ''));
        $department = trim((string)$request->post('department', ''));
        $phone = trim((string)$request->post('phone', ''));

        $targetUser = $this->userRepo->findById($userId);
        if (!$targetUser) {
            SessionManager::flash('error', 'User account not found.');
            Response::redirect('/admin/hr-accounts');
            return;
        }

        if (empty($name) || empty($username) || empty($email) || empty($role)) {
            SessionManager::flash('error', 'Name, Username, Email, and Role are required.');
            Response::redirect('/admin/hr-accounts');
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            SessionManager::flash('error', "The email address '{$email}' is invalid.");
            Response::redirect('/admin/hr-accounts');
            return;
        }

        // Check username uniqueness if changed or claimed by another user
        if ($this->userRepo->isUsernameTaken($username, $userId)) {
            SessionManager::flash('error', "Username '{$username}' is already taken by another account.");
            Response::redirect('/admin/hr-accounts');
            return;
        }

        // Check email uniqueness if changed or claimed by another user
        if ($this->userRepo->isEmailTaken($email, $userId)) {
            SessionManager::flash('error', "This email address '{$email}' is already assigned to another account.");
            Response::redirect('/admin/hr-accounts');
            return;
        }

        $adminUser = User::fromArray(SessionManager::getUser());
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $this->userRepo->updateUser($userId, [
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'department' => $department ?: 'Administration',
                'phone' => $phone ?: null
            ]);

            $this->auditRepo->create([
                'user_id' => $adminUser->id,
                'user_name' => $adminUser->name,
                'user_role' => $adminUser->role,
                'action' => 'USER_ACCOUNT_UPDATED',
                'details' => "Administrator {$adminUser->name} updated account details for {$name} (Username: {$username}, Email: {$email}, Role: {$role}).",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[ADMIN_UPDATE_USER_ERROR] ' . $e->getMessage());
            SessionManager::flash('error', 'Unable to update user account due to a database constraint error.');
            Response::redirect('/admin/hr-accounts');
            return;
        }

        SessionManager::flash('success', "User account for '{$name}' updated successfully.");
        Response::redirect('/admin/hr-accounts');
    }
}
