<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Repositories;

use Mengo\IdApproval\Models\ApprovalRecord;
use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;
use PDO;

class ApprovalRecordRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function findByCardId(int $idCardId): ?ApprovalRecord
    {
        $stmt = $this->pdo->prepare("
            SELECT app.*, v.version_number
            FROM approval_records app
            JOIN id_versions v ON v.id = app.version_id
            WHERE app.id_card_id = ?
        ");
        $stmt->execute([$idCardId]);
        $row = $stmt->fetch();
        return $row ? ApprovalRecord::fromArray($row) : null;
    }

    public function create(array $data): int
    {
        $now = Timezone::nowString();
        $stmt = $this->pdo->prepare("
            INSERT INTO approval_records (
                id_card_id, version_id, hr_user_id, hr_name, hr_email, hr_role,
                checklist_photo, checklist_name, checklist_staff_no, checklist_department,
                checklist_designation, checklist_layout, approval_notes, file_sha256_at_approval,
                approved_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['id_card_id'],
            $data['version_id'],
            $data['hr_user_id'],
            $data['hr_name'],
            $data['hr_email'],
            $data['hr_role'] ?? 'HR_MANAGER',
            $data['checklist_photo'] ?? 1,
            $data['checklist_name'] ?? 1,
            $data['checklist_staff_no'] ?? 1,
            $data['checklist_department'] ?? 1,
            $data['checklist_designation'] ?? 1,
            $data['checklist_layout'] ?? 1,
            $data['approval_notes'] ?? null,
            $data['file_sha256_at_approval'],
            $data['approved_at'] ?? $now,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getApprovalsByManager(): array
    {
        $stmt = $this->pdo->query("
            SELECT hr_user_id, hr_name, hr_email, COUNT(*) as approval_count,
                   MAX(approved_at) as last_approved_at
            FROM approval_records
            GROUP BY hr_user_id, hr_name, hr_email
            ORDER BY approval_count DESC
        ");
        return $stmt->fetchAll();
    }
}
