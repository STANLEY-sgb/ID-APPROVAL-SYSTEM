<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Controllers;

use Mengo\IdApproval\Repositories\NotificationRepository;
use Mengo\IdApproval\Security\SessionManager;
use Mengo\IdApproval\Support\Request;
use Mengo\IdApproval\Support\Response;
use Mengo\IdApproval\Support\View;

class NotificationController
{
    private NotificationRepository $notifRepo;

    public function __construct()
    {
        $this->notifRepo = new NotificationRepository();
    }

    public function index(Request $request): void
    {
        $userId = (int)SessionManager::getUserId();
        $role = (string)SessionManager::getUserRole();

        $page = max(1, (int)$request->get('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $notifications = $this->notifRepo->getForUser($userId, $role, $limit, $offset);
        $unreadCount = $this->notifRepo->countUnreadForUser($userId, $role);

        View::render('notifications/index', [
            'pageTitle' => 'Notification Center — Mengo Hospital ID System',
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'page' => $page
        ]);
    }

    public function markRead(Request $request, array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->notifRepo->markAsRead($id);

        if ($request->isAjax()) {
            Response::json(['success' => true]);
        }

        Response::redirect('/notifications');
    }

    public function markAllRead(Request $request): void
    {
        $userId = (int)SessionManager::getUserId();
        $role = (string)SessionManager::getUserRole();

        $this->notifRepo->markAllAsReadForUser($userId, $role);

        if ($request->isAjax()) {
            Response::json(['success' => true]);
        }

        SessionManager::flash('success', 'All notifications marked as read.');
        Response::redirect('/notifications');
    }

    public function unreadCount(Request $request): void
    {
        $userId = (int)SessionManager::getUserId();
        $role = (string)SessionManager::getUserRole();

        $unreadCount = $this->notifRepo->countUnreadForUser($userId, $role);
        Response::json(['unread_count' => $unreadCount]);
    }
}
