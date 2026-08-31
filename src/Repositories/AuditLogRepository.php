<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Repositories;

use Mengo\IdApproval\Models\AuditLog;
use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;
use PDO;

class AuditLogRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function log(array $data): int
    {
        $now = Timezone::nowString();
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_logs (
                id_card_id, user_id, user_name, user_role, action, entity_type,
                entity_id, previous_status, new_status, version_number,
                ip_address, user_agent, details, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $idCardId = (!empty($data['id_card_id']) && (int)$data['id_card_id'] > 0) ? (int)$data['id_card_id'] : null;
        $userId = (!empty($data['user_id']) && (int)$data['user_id'] > 0) ? (int)$data['user_id'] : null;

        $stmt->execute([
            $idCardId,
            $userId,
            $data['user_name'] ?? 'System',
            $data['user_role'] ?? 'SYSTEM',
            $data['action'],
            $data['entity_type'] ?? ($idCardId ? 'ID_CARD' : 'SYSTEM'),
            $data['entity_id'] ?? $idCardId,
            $data['previous_status'] ?? null,
            $data['new_status'] ?? null,
            $data['version_number'] ?? null,
            $data['ip_address'] ?? null,
            $data['user_agent'] ?? null,
            $data['details'] ?? null,
            $data['created_at'] ?? $now,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Alias for log() to adhere to standard repository create contract.
     */
    public function create(array $data): int
    {
        return $this->log($data);
    }

    public function getForCard(int $idCardId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*, c.card_reference, e.full_name as employee_name
            FROM audit_logs a
            LEFT JOIN id_cards c ON c.id = a.id_card_id
            LEFT JOIN employees e ON e.id = c.employee_id
            WHERE a.id_card_id = ?
            ORDER BY a.created_at ASC, a.id ASC
        ");
        $stmt->execute([$idCardId]);
        $rows = $stmt->fetchAll();
        return array_map(fn($r) => AuditLog::fromArray($r), $rows);
    }

    public function getFiltered(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = "
            SELECT a.*, c.card_reference, e.full_name as employee_name
            FROM audit_logs a
            LEFT JOIN id_cards c ON c.id = a.id_card_id
            LEFT JOIN employees e ON e.id = c.employee_id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['action'])) {
            $sql .= " AND a.action = ?";
            $params[] = $filters['action'];
        }

        if (!empty($filters['user_role'])) {
            $sql .= " AND a.user_role = ?";
            $params[] = $filters['user_role'];
        }

        if (!empty($filters['user_id'])) {
            $sql .= " AND a.user_id = ?";
            $params[] = (int)$filters['user_id'];
        }

        if (!empty($filters['id_card_id'])) {
            $sql .= " AND a.id_card_id = ?";
            $params[] = (int)$filters['id_card_id'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $sql .= " AND (
                a.user_name LIKE ? OR 
                a.action LIKE ? OR 
                a.details LIKE ? OR 
                c.card_reference LIKE ? OR 
                e.full_name LIKE ?
            )";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND a.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND a.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $limit = max(1, $limit);
        $offset = max(0, $offset);
        $sql .= " ORDER BY a.created_at DESC, a.id DESC LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(fn($r) => AuditLog::fromArray($r), $rows);
    }

    public function countFiltered(array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*) 
            FROM audit_logs a
            LEFT JOIN id_cards c ON c.id = a.id_card_id
            LEFT JOIN employees e ON e.id = c.employee_id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['action'])) {
            $sql .= " AND a.action = ?";
            $params[] = $filters['action'];
        }

        if (!empty($filters['user_role'])) {
            $sql .= " AND a.user_role = ?";
            $params[] = $filters['user_role'];
        }

        if (!empty($filters['user_id'])) {
            $sql .= " AND a.user_id = ?";
            $params[] = (int)$filters['user_id'];
        }

        if (!empty($filters['id_card_id'])) {
            $sql .= " AND a.id_card_id = ?";
            $params[] = (int)$filters['id_card_id'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $sql .= " AND (
                a.user_name LIKE ? OR 
                a.action LIKE ? OR 
                a.details LIKE ? OR 
                c.card_reference LIKE ? OR 
                e.full_name LIKE ?
            )";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}
