<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Middleware;

use Mengo\IdApproval\Security\SessionManager;
use Mengo\IdApproval\Support\ForbiddenException;
use Mengo\IdApproval\Support\Request;
use Mengo\IdApproval\Support\Response;

class AuthMiddleware
{
    /**
     * Require an authenticated session. Redirect to login if missing.
     */
    public static function require(Request $request, Response $response): void
    {
        if (!SessionManager::isAuthenticated()) {
            if ($request->isAjax()) {
                Response::json(['success' => false, 'message' => 'Unauthenticated session. Please log in.'], 401);
            }
            SessionManager::flash('warning', 'Please sign in to access this page.');
            Response::redirect('/login');
        }

        // Force password change if flagged
        $user = SessionManager::getUser();
        $currentPath = $request->path();
        if ($user && !empty($user['force_password_change'])
            && $currentPath !== '/change-password'
            && $currentPath !== '/logout'
        ) {
            Response::redirect('/change-password');
        }
    }

    /**
     * Require a specific role. Throws ForbiddenException if wrong role.
     */
    public static function requireRole(Request $request, Response $response, string $requiredRole): void
    {
        self::require($request, $response);

        $user = SessionManager::getUser();
        if (!$user || ($user['role'] ?? '') !== $requiredRole) {
            throw new ForbiddenException(
                "Access denied. This page requires the '{$requiredRole}' role."
            );
        }
    }

    /**
     * Require any one of the specified roles. Throws ForbiddenException if not authorized.
     */
    public static function requireRoles(Request $request, Response $response, array $allowedRoles): void
    {
        self::require($request, $response);

        $user = SessionManager::getUser();
        if (!$user || !in_array($user['role'] ?? '', $allowedRoles, true)) {
            $rolesList = implode(', ', $allowedRoles);
            throw new ForbiddenException(
                "Access denied. This action requires one of the following roles: {$rolesList}."
            );
        }
    }

    /**
     * BC alias — used by older code
     */
    public static function handle(Request $request): void
    {
        static $dummyResponse;
        if (!$dummyResponse) {
            $dummyResponse = new Response();
        }
        self::require($request, $dummyResponse);
    }
}
