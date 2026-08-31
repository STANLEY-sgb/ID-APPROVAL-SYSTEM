<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Middleware;

use Mengo\IdApproval\Security\SessionManager;
use Mengo\IdApproval\Support\Request;
use Mengo\IdApproval\Support\Response;

class RoleMiddleware
{
    public static function handle(Request $request, array|string $allowedRoles): void
    {
        AuthMiddleware::handle($request);

        $allowed = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
        $currentRole = SessionManager::getUserRole();

        if ($currentRole === null || !in_array($currentRole, $allowed, true)) {
            if ($request->isAjax()) {
                Response::json([
                    'success' => false, 
                    'message' => 'Access Denied: Your role (' . ($currentRole ?? 'None') . ') is not authorized for this operation.'
                ], 403);
            }
            Response::forbidden('Access Denied. You do not have the required permissions for this action.');
        }
    }
}
