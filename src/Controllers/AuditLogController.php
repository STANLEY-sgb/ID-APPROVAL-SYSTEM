<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Controllers;

use Mengo\IdApproval\Repositories\AuditLogRepository;
use Mengo\IdApproval\Repositories\UserRepository;
use Mengo\IdApproval\Support\Request;
use Mengo\IdApproval\Support\View;

class AuditLogController
{
    private AuditLogRepository $auditRepo;
    private UserRepository $userRepo;

    public function __construct()
    {
        $this->auditRepo = new AuditLogRepository();
        $this->userRepo = new UserRepository();
    }

    public function index(Request $request): void
    {
        $page = max(1, (int)$request->get('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $filters = [
            'action' => $request->get('action'),
            'user_role' => $request->get('user_role'),
            'user_id' => $request->get('user_id'),
            'search' => $request->get('search'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to')
        ];

        $logs = $this->auditRepo->getFiltered($filters, $limit, $offset);
        $total = $this->auditRepo->countFiltered($filters);
        $totalPages = ceil($total / $limit);
        $users = $this->userRepo->all();

        View::render('audit/index', [
            'pageTitle' => 'Immutable System Audit Logs — Mengo Hospital ID System',
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'users' => $users,
            'filters' => $filters
        ]);
    }
}
