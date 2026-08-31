<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Repositories;

use Mengo\IdApproval\Models\CorrectionRequest;
use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;
use PDO;

class CorrectionRequestRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function findById(int $id): ?CorrectionRequest
    {
        $stmt = $this->pdo->prepare("
            SELECT cr.*, u.name as requester_name, u.email as requester_email,
                   v.version_number, res_v.version_number as resolved_version_number
            FROM correction_requests cr
            JOIN users u ON u.id = cr.requested_by_user_id
            JOIN id_versions v ON v.id = cr.version_id
            LEFT JOIN id_versions res_v ON res_v.id = cr.resolved_version_id
            WHERE cr.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? CorrectionRequest::fromArray($row) : null;
    }

    public function getForCard(int $idCardId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT cr.*, u.name as requester_name, u.email as requester_email,
                   v.version_number, res_v.version_number as resolved_version_number
            FROM correction_requests cr
            JOIN users u ON u.id = cr.requested_by_user_id
            JOIN id_versions v ON v.id = cr.version_id
            LEFT JOIN id_versions res_v ON res_v.id = cr.resolved_version_id
            WHERE cr.id_card_id = ?
            ORDER BY cr.requested_at DESC
        ");
        $stmt->execute([$idCardId]);
        $rows = $stmt->fetchAll();
        return array_map(fn($r) => CorrectionRequest::fromArray($r), $rows);
    }

    public function getPendingForCard(int $idCardId): ?CorrectionRequest
    {
        $stmt = $this->pdo->prepare("
            SELECT cr.*, u.name as requester_name, u.email as requester_email,
                   v.version_number
            FROM correction_requests cr
            JOIN users u ON u.id = cr.requested_by_user_id
            JOIN id_versions v ON v.id = cr.version_id
            WHERE cr.id_card_id = ? AND cr.status = 'PENDING'
            ORDER BY cr.requested_at DESC LIMIT 1
        ");
        $stmt->execute([$idCardId]);
        $row = $stmt->fetch();
        return $row ? CorrectionRequest::fromArray($row) : null;
    }

    public function create(array $data): int
    {
        $now = Timezone::nowString();
        $stmt = $this->pdo->prepare("
            INSERT INTO correction_requests (
                id_card_id, version_id, requested_by_user_id, reason, status, requested_at
            ) VALUES (?, ?, ?, ?, 'PENDING', ?)
        ");
        $stmt->execute([
            $data['id_card_id'],
            $data['version_id'],
            $data['requested_by_user_id'],
            $data['reason'],
            $now
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function resolve(int $id, int $resolvedVersionId): void
    {
        $now = Timezone::nowString();
        $stmt = $this->pdo->prepare("
            UPDATE correction_requests 
            SET status = 'RESOLVED', resolved_version_id = ?, resolved_at = ? 
            WHERE id = ?
        ");
        $stmt->execute([$resolvedVersionId, $now, $id]);
    }
}
