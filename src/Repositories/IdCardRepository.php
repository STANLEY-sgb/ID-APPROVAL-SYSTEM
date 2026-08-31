<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Repositories;

use Mengo\IdApproval\Models\IdCard;
use Mengo\IdApproval\Models\IdStatus;
use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;
use PDO;

class IdCardRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function findById(int $id): ?IdCard
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                c.*,
                e.full_name AS employee_name,
                e.staff_id AS employee_staff_id,
                e.designation,
                e.blood_group,
                e.phone,
                e.email,
                e.national_id,
                d.name AS department_name,
                u_creator.name AS created_by_name,
                u_designer.name AS assigned_designer_name,
                v.file_sha256 AS latest_pdf_sha256,
                v.file_size AS latest_pdf_size,
                v.file_path AS latest_pdf_path,
                app.hr_name AS approved_by_name,
                app.approved_at AS approved_at
            FROM id_cards c
            JOIN employees e ON e.id = c.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN users u_creator ON u_creator.id = c.created_by_user_id
            LEFT JOIN users u_designer ON u_designer.id = c.assigned_designer_id
            LEFT JOIN id_versions v ON v.id_card_id = c.id AND v.version_number = c.current_version_number
            LEFT JOIN approval_records app ON app.id_card_id = c.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return $data ? IdCard::fromArray($data) : null;
    }

    public function findByCardReference(string $ref): ?IdCard
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                c.*,
                e.full_name AS employee_name,
                e.staff_id AS employee_staff_id,
                e.designation,
                e.blood_group,
                e.phone,
                e.email,
                e.national_id,
                d.name AS department_name,
                u_creator.name AS created_by_name,
                u_designer.name AS assigned_designer_name,
                v.file_sha256 AS latest_pdf_sha256,
                v.file_size AS latest_pdf_size,
                v.file_path AS latest_pdf_path,
                app.hr_name AS approved_by_name,
                app.approved_at AS approved_at
            FROM id_cards c
            JOIN employees e ON e.id = c.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN users u_creator ON u_creator.id = c.created_by_user_id
            LEFT JOIN users u_designer ON u_designer.id = c.assigned_designer_id
            LEFT JOIN id_versions v ON v.id_card_id = c.id AND v.version_number = c.current_version_number
            LEFT JOIN approval_records app ON app.id_card_id = c.id
            WHERE c.card_reference = ?
        ");
        $stmt->execute([trim($ref)]);
        $data = $stmt->fetch();
        return $data ? IdCard::fromArray($data) : null;
    }

    public function findByEmployeeId(int $employeeId): ?IdCard
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, e.full_name AS employee_name, e.staff_id AS employee_staff_id, d.name AS department_name
            FROM id_cards c
            JOIN employees e ON e.id = c.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE c.employee_id = ?
        ");
        $stmt->execute([$employeeId]);
        $data = $stmt->fetch();
        return $data ? IdCard::fromArray($data) : null;
    }

    public function create(array $data): int
    {
        $now = Timezone::nowString();
        $stmt = $this->pdo->prepare("
            INSERT INTO id_cards (
                card_reference, employee_id, current_status, current_version_number,
                created_by_user_id, assigned_designer_id, needs_import_review, import_notes,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['card_reference'],
            $data['employee_id'],
            $data['current_status'] ?? IdStatus::DRAFT,
            $data['current_version_number'] ?? 1,
            $data['created_by_user_id'] ?? 1,
            $data['assigned_designer_id'] ?? null,
            $data['needs_import_review'] ?? 0,
            $data['import_notes'] ?? null,
            $data['created_at'] ?? $now,
            $data['updated_at'] ?? $now,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, string $newStatus, ?int $approvedVersionId = null): bool
    {
        $now = Timezone::nowString();
        if ($approvedVersionId !== null) {
            $stmt = $this->pdo->prepare("
                UPDATE id_cards 
                SET current_status = ?, approved_version_id = ?, updated_at = ? 
                WHERE id = ?
            ");
            return $stmt->execute([$newStatus, $approvedVersionId, $now, $id]);
        }

        $stmt = $this->pdo->prepare("
            UPDATE id_cards 
            SET current_status = ?, updated_at = ? 
            WHERE id = ?
        ");
        return $stmt->execute([$newStatus, $now, $id]);
    }

    /**
     * Atomic CAS (Compare-And-Swap) conditional status update for concurrency safety.
     * Returns true if exactly 1 row was updated, false if status was already changed by someone else.
     */
    public function updateStatusConditional(int $id, string $expectedStatus, string $newStatus, ?int $approvedVersionId = null): bool
    {
        $now = Timezone::nowString();
        if ($approvedVersionId !== null) {
            $stmt = $this->pdo->prepare("
                UPDATE id_cards 
                SET current_status = ?, approved_version_id = ?, updated_at = ? 
                WHERE id = ? AND current_status = ?
            ");
            $stmt->execute([$newStatus, $approvedVersionId, $now, $id, $expectedStatus]);
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE id_cards 
                SET current_status = ?, updated_at = ? 
                WHERE id = ? AND current_status = ?
            ");
            $stmt->execute([$newStatus, $now, $id, $expectedStatus]);
        }

        return $stmt->rowCount() === 1;
    }

    public function incrementVersion(int $id, int $newVersionNumber, string $newStatus): void
    {
        $now = Timezone::nowString();
        $stmt = $this->pdo->prepare("
            UPDATE id_cards 
            SET current_version_number = ?, current_status = ?, updated_at = ? 
            WHERE id = ?
        ");
        $stmt->execute([$newVersionNumber, $newStatus, $now, $id]);
    }

    public function getFiltered(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = "
            SELECT 
                c.*,
                e.full_name AS employee_name,
                e.staff_id AS employee_staff_id,
                e.designation,
                e.blood_group,
                d.name AS department_name,
                u_creator.name AS created_by_name,
                u_designer.name AS assigned_designer_name,
                v.file_sha256 AS latest_pdf_sha256,
                v.file_size AS latest_pdf_size,
                v.file_path AS latest_pdf_path,
                app.hr_name AS approved_by_name,
                app.approved_at AS approved_at
            FROM id_cards c
            JOIN employees e ON e.id = c.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN users u_creator ON u_creator.id = c.created_by_user_id
            LEFT JOIN users u_designer ON u_designer.id = c.assigned_designer_id
            LEFT JOIN id_versions v ON v.id_card_id = c.id AND v.version_number = c.current_version_number
            LEFT JOIN approval_records app ON app.id_card_id = c.id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND c.current_status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['department_id'])) {
            $sql .= " AND e.department_id = ?";
            $params[] = (int)$filters['department_id'];
        }

        if (!empty($filters['designer_id'])) {
            $sql .= " AND (c.created_by_user_id = ? OR c.assigned_designer_id = ?)";
            $params[] = (int)$filters['designer_id'];
            $params[] = (int)$filters['designer_id'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $sql .= " AND (
                e.full_name LIKE ? OR 
                e.staff_id LIKE ? OR 
                c.card_reference LIKE ? OR 
                e.designation LIKE ? OR
                d.name LIKE ?
            )";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND c.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND c.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $limit = max(1, $limit);
        $offset = max(0, $offset);
        $sql .= " ORDER BY c.updated_at DESC LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(fn($r) => IdCard::fromArray($r), $rows);
    }

    public function countFiltered(array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*) 
            FROM id_cards c
            JOIN employees e ON e.id = c.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND c.current_status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['department_id'])) {
            $sql .= " AND e.department_id = ?";
            $params[] = (int)$filters['department_id'];
        }

        if (!empty($filters['designer_id'])) {
            $sql .= " AND (c.created_by_user_id = ? OR c.assigned_designer_id = ?)";
            $params[] = (int)$filters['designer_id'];
            $params[] = (int)$filters['designer_id'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $sql .= " AND (
                e.full_name LIKE ? OR 
                e.staff_id LIKE ? OR 
                c.card_reference LIKE ? OR 
                e.designation LIKE ? OR
                d.name LIKE ?
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

    public function getIdsFiltered(array $filters = []): array
    {
        $sql = "
            SELECT c.id 
            FROM id_cards c
            JOIN employees e ON e.id = c.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND c.current_status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['department_id'])) {
            $sql .= " AND e.department_id = ?";
            $params[] = (int)$filters['department_id'];
        }

        if (!empty($filters['designer_id'])) {
            $sql .= " AND (c.created_by_user_id = ? OR c.assigned_designer_id = ?)";
            $params[] = (int)$filters['designer_id'];
            $params[] = (int)$filters['designer_id'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $sql .= " AND (
                e.full_name LIKE ? OR 
                e.staff_id LIKE ? OR 
                c.card_reference LIKE ? OR 
                e.designation LIKE ? OR
                d.name LIKE ?
            )";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND c.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND c.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $sql .= " ORDER BY c.updated_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function getCountsByStatus(): array
    {
        $stmt = $this->pdo->query("
            SELECT current_status, COUNT(*) as cnt 
            FROM id_cards 
            GROUP BY current_status
        ");
        $results = [
            IdStatus::DRAFT => 0,
            IdStatus::UPLOADED => 0,
            IdStatus::PENDING_HR_APPROVAL => 0,
            IdStatus::CORRECTION_REQUESTED => 0,
            IdStatus::APPROVED => 0,
            IdStatus::PRINTED => 0,
            IdStatus::COLLECTED => 0,
            IdStatus::IMPORT_REVIEW_REQUIRED => 0,
            'total' => 0
        ];

        $total = 0;
        while ($row = $stmt->fetch()) {
            $results[$row['current_status']] = (int)$row['cnt'];
            $total += (int)$row['cnt'];
        }
        $results['total'] = $total;
        return $results;
    }

    public function getOverduePendingApprovals(int $hours = 24): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - ($hours * 3600));
        $stmt = $this->pdo->prepare("
            SELECT c.*, e.full_name AS employee_name, e.staff_id AS employee_staff_id, d.name AS department_name
            FROM id_cards c
            JOIN employees e ON e.id = c.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE c.current_status = 'PENDING_HR_APPROVAL' AND c.updated_at <= ?
            ORDER BY c.updated_at ASC
        ");
        $stmt->execute([$cutoff]);
        $rows = $stmt->fetchAll();
        return array_map(fn($r) => IdCard::fromArray($r), $rows);
    }
}
