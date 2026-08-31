<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Repositories;

use Mengo\IdApproval\Models\Notification;
use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;
use PDO;

class NotificationRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function create(array $data): int
    {
        $now = Timezone::nowString();
        $stmt = $this->pdo->prepare("
            INSERT INTO notifications (
                user_id, role_target, type, title, message, id_card_id, link_url, is_read, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)
        ");
        $stmt->execute([
            $data['user_id'] ?? null,
            $data['role_target'] ?? null,
            $data['type'],
            $data['title'],
            $data['message'],
            $data['id_card_id'] ?? null,
            $data['link_url'] ?? null,
            $now
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getForUser(int $userId, string $role, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        $stmt = $this->pdo->prepare("
            SELECT * FROM notifications 
            WHERE (user_id = ? OR (user_id IS NULL AND role_target = ?))
            ORDER BY created_at DESC 
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute([$userId, $role]);
        $rows = $stmt->fetchAll();
        return array_map(fn($r) => Notification::fromArray($r), $rows);
    }

    public function countUnreadForUser(int $userId, string $role): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE (user_id = ? OR (user_id IS NULL AND role_target = ?))
              AND is_read = 0
        ");
        $stmt->execute([$userId, $role]);
        return (int)$stmt->fetchColumn();
    }

    public function markAsRead(int $notificationId): void
    {
        $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->execute([$notificationId]);
    }

    public function markAllAsReadForUser(int $userId, string $role): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE (user_id = ? OR (user_id IS NULL AND role_target = ?))
              AND is_read = 0
        ");
        $stmt->execute([$userId, $role]);
    }
}
