<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Controllers;

use Mengo\IdApproval\Models\Role;
use Mengo\IdApproval\Security\SessionManager;
use Mengo\IdApproval\Support\Request;
use Mengo\IdApproval\Support\Response;

class DashboardController
{
    public function index(Request $request): void
    {
        $role = SessionManager::getUserRole();

        match ($role) {
            Role::DESIGNER => Response::redirect('/designer/dashboard'),
            Role::HR_MANAGER => Response::redirect('/hr/dashboard'),
            Role::PRINTING_OFFICER => Response::redirect('/printing/dashboard'),
            default => Response::redirect('/login'),
        };
    }
}
