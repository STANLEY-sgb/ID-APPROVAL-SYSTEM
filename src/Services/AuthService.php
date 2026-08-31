<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Services;

use Mengo\IdApproval\Models\AuditLog;
use Mengo\IdApproval\Models\User;
use Mengo\IdApproval\Repositories\UserRepository;
use Mengo\IdApproval\Security\PasswordHasher;
use Mengo\IdApproval\Security\RateLimiter;
use Mengo\IdApproval\Security\SessionManager;
use Mengo\IdApproval\Support\Request;
use RuntimeException;

class AuthService
{
    private UserRepository $userRepo;
    private AuditService $auditService;

    public function __construct(
        ?UserRepository $userRepo = null,
        ?AuditService $auditService = null
    ) {
        $this->userRepo = $userRepo ?? new UserRepository();
        $this->auditService = $auditService ?? new AuditService();
    }

    public function authenticate(string $identifier, string $password, Request $request): User
    {
        $ip = $request->ip();
        $identifier = trim($identifier);

        if ($identifier === '') {
            throw new RuntimeException("Invalid username or password.");
        }

        if (RateLimiter::isLocked($identifier, $ip)) {
            $this->auditService->logSecurity(
                'LOGIN_LOCKED',
                "Account/IP temporarily locked due to too many failed attempts for identifier: {$identifier}",
                $ip,
                $request->userAgent()
            );
            throw new RuntimeException("Too many failed login attempts. Please wait 15 minutes before trying again.");
        }

        // Strict username-only lookup — email login is intentionally NOT supported.
        $user = $this->userRepo->findByUsername($identifier);
        if (!$user || !PasswordHasher::verify($password, $user->password_hash)) {
            RateLimiter::recordAttempt($identifier, $ip, 'FAILED');
            $this->auditService->logSecurity(
                AuditLog::ACTION_LOGIN_FAILED,
                "Failed login attempt for username: {$identifier}",
                $ip,
                $request->userAgent()
            );
            throw new RuntimeException("Invalid username or password.");
        }

        if ($user->status !== 'ACTIVE') {
            RateLimiter::recordAttempt($identifier, $ip, 'LOCKED');
            throw new RuntimeException("Your account is {$user->status}. Please contact the hospital administrator.");
        }

        // Successful authentication
        RateLimiter::reset($identifier, $ip);
        RateLimiter::recordAttempt($identifier, $ip, 'SUCCESS');
        $this->userRepo->updateLastLogin($user->id);

        SessionManager::setUser($user);

        $this->auditService->logUserAction(
            $user,
            AuditLog::ACTION_LOGIN_SUCCESS,
            "User {$user->name} ({$user->role}) logged in successfully.",
            $ip,
            $request->userAgent()
        );

        return $user;
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword, Request $request): void
    {
        $user = $this->userRepo->findById($userId);
        if (!$user) {
            throw new RuntimeException("User not found.");
        }

        if (!PasswordHasher::verify($currentPassword, $user->password_hash)) {
            throw new RuntimeException("Current password is incorrect.");
        }

        if (strlen($newPassword) < 8) {
            throw new RuntimeException("New password must be at least 8 characters long.");
        }

        $newHash = PasswordHasher::hash($newPassword);
        $this->userRepo->updatePassword($userId, $newHash);

        // Update session
        $user->force_password_change = 0;
        SessionManager::setUser($user);

        $this->auditService->logUserAction(
            $user,
            AuditLog::ACTION_PASSWORD_CHANGED,
            "User {$user->name} changed their account password.",
            $request->ip(),
            $request->userAgent()
        );
    }

    public function logout(Request $request): void
    {
        $userArray = SessionManager::getUser();
        if ($userArray) {
            $user = User::fromArray($userArray);
            $this->auditService->logUserAction(
                $user,
                'LOGOUT',
                "User {$user->name} logged out.",
                $request->ip(),
                $request->userAgent()
            );
        }

        SessionManager::destroy();
    }
}
