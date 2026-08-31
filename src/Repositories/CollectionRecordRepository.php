<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Repositories;

use Mengo\IdApproval\Models\CollectionRecord;
use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;
use PDO;

class CollectionRecordRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function findByCardId(int $idCardId): ?CollectionRecord
    {
        $stmt = $this->pdo->prepare("
            SELECT cr.*, u.name as hr_name
            FROM collection_records cr
            JOIN users u ON u.id = cr.hr_user_id
            WHERE cr.id_card_id = ?
        ");
        $stmt->execute([$idCardId]);
        $row = $stmt->fetch();
        return $row ? CollectionRecord::fromArray($row) : null;
    }

    public function create(array $data): int
    {
        $now = Timezone::nowString();
        $stmt = $this->pdo->prepare("
            INSERT INTO collection_records (
                id_card_id, hr_user_id, collected_by_name, collected_by_relationship,
                recipient_national_id_or_phone, collection_reference, notes, collected_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['id_card_id'],
            $data['hr_user_id'],
            $data['collected_by_name'],
            $data['collected_by_relationship'] ?? 'SELF',
            $data['recipient_national_id_or_phone'] ?? null,
            $data['collection_reference'] ?? null,
            $data['notes'] ?? null,
            $data['collected_at'] ?? $now,
        ]);
        return (int)$this->pdo->lastInsertId();
    }
}
