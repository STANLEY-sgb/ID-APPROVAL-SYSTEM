<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Repositories;

use Mengo\IdApproval\Models\IdVersion;
use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;
use PDO;

class IdVersionRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function findById(int $id): ?IdVersion
    {
        $stmt = $this->pdo->prepare("
            SELECT v.*, u.name as uploader_name 
            FROM id_versions v
            LEFT JOIN users u ON u.id = v.uploaded_by_user_id
            WHERE v.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? IdVersion::fromArray($row) : null;
    }

    public function findByCardAndVersion(int $idCardId, int $versionNumber): ?IdVersion
    {
        $stmt = $this->pdo->prepare("
            SELECT v.*, u.name as uploader_name 
            FROM id_versions v
            LEFT JOIN users u ON u.id = v.uploaded_by_user_id
            WHERE v.id_card_id = ? AND v.version_number = ?
        ");
        $stmt->execute([$idCardId, $versionNumber]);
        $row = $stmt->fetch();
        return $row ? IdVersion::fromArray($row) : null;
    }

    public function getVersionsForCard(int $idCardId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT v.*, u.name as uploader_name 
            FROM id_versions v
            LEFT JOIN users u ON u.id = v.uploaded_by_user_id
            WHERE v.id_card_id = ?
            ORDER BY v.version_number DESC
        ");
        $stmt->execute([$idCardId]);
        $rows = $stmt->fetchAll();
        return array_map(fn($r) => IdVersion::fromArray($r), $rows);
    }

    public function create(array $data): int
    {
        $now = Timezone::nowString();
        $stmt = $this->pdo->prepare("
            INSERT INTO id_versions (
                id_card_id, version_number, file_path, original_filename,
                file_size, file_sha256, mime_type, uploaded_by_user_id,
                correction_request_id, is_approved, uploaded_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['id_card_id'],
            $data['version_number'],
            $data['file_path'],
            $data['original_filename'],
            $data['file_size'],
            $data['file_sha256'],
            $data['mime_type'] ?? 'application/pdf',
            $data['uploaded_by_user_id'] ?? 1,
            $data['correction_request_id'] ?? null,
            $data['is_approved'] ?? 0,
            $data['uploaded_at'] ?? $now
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function markAsApproved(int $versionId): void
    {
        $stmt = $this->pdo->prepare("UPDATE id_versions SET is_approved = 1 WHERE id = ?");
        $stmt->execute([$versionId]);
    }
}
