<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Controllers;

use Mengo\IdApproval\Repositories\AuditLogRepository;
use Mengo\IdApproval\Repositories\IdCardRepository;
use Mengo\IdApproval\Repositories\NotificationRepository;
use Mengo\IdApproval\Security\SessionManager;
use Mengo\IdApproval\Services\WorkflowService;
use Mengo\IdApproval\Support\Request;
use Mengo\IdApproval\Support\Response;
use Mengo\IdApproval\Support\Timezone;

class SyncController
{
    private IdCardRepository $cardRepo;
    private NotificationRepository $notifRepo;
    private AuditLogRepository $auditRepo;
    private WorkflowService $workflowService;

    public function __construct()
    {
        $this->cardRepo = new IdCardRepository();
        $this->notifRepo = new NotificationRepository();
        $this->auditRepo = new AuditLogRepository();
        $this->workflowService = new WorkflowService();
    }

    public function sync(Request $request): void
    {
        $userId = (int)SessionManager::getUserId();
        $role = (string)SessionManager::getUserRole();

        if (!$userId || !$role) {
            Response::json(['authenticated' => false, 'message' => 'Unauthenticated session'], 401);
        }

        $since = $request->get('since');
        $unreadNotifs = $this->notifRepo->countUnreadForUser($userId, $role);
        $statusCounts = $this->cardRepo->getCountsByStatus();
        $smartAlerts = $this->workflowService->getSmartFollowUpAlerts();

        $recentEvents = [];
        if (!empty($since)) {
            $recentEvents = $this->auditRepo->getFiltered([
                'date_from' => $since
            ], 15, 0);
        }

        Response::json([
            'authenticated' => true,
            'user' => [
                'id' => $userId,
                'role' => $role
            ],
            'unread_notifications' => $unreadNotifs,
            'status_counts' => $statusCounts,
            'smart_alerts' => [
                'total' => $smartAlerts['total_alerts'],
                'overdue_approvals' => $smartAlerts['overdue_approvals']['count'],
                'stale_corrections' => $smartAlerts['stale_corrections']['count'],
                'printing_delays' => $smartAlerts['printing_delays']['count'],
                'collection_delays' => $smartAlerts['collection_delays']['count']
            ],
            'recent_events_count' => count($recentEvents),
            'server_time' => Timezone::nowString(),
            'timezone' => 'Africa/Kampala'
        ]);
    }
}
