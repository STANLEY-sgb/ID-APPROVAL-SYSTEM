<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Repositories;

use Mengo\IdApproval\Models\PrintRecord;
use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;
use PDO;

class PrintRecordRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function findLatestForCard(int $idCardId): ?PrintRecord
    {
        $stmt = $this->pdo->prepare("
            SELECT pr.*, v.version_number
            FROM print_records pr
            JOIN id_versions v ON v.id = pr.version_id
            WHERE pr.id_card_id = ?
            ORDER BY pr.printed_at DESC LIMIT 1
        ");
        $stmt->execute([$idCardId]);
        $row = $stmt->fetch();
        return $row ? PrintRecord::fromArray($row) : null;
    }

    public function create(array $data): int
    {
        $now = Timezone::nowString();
        $stmt = $this->pdo->prepare("
            INSERT INTO print_records (
                id_card_id, version_id, printing_user_id, printing_user_name,
                file_sha256_at_print, print_notes, printed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['id_card_id'],
            $data['version_id'],
            $data['printing_user_id'],
            $data['printing_user_name'],
            $data['file_sha256_at_print'],
            $data['print_notes'] ?? null,
            $data['printed_at'] ?? $now,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getPrintsByOfficer(): array
    {
        $stmt = $this->pdo->query("
            SELECT printing_user_id, printing_user_name, COUNT(*) as print_count,
                   MAX(printed_at) as last_printed_at
            FROM print_records
            GROUP BY printing_user_id, printing_user_name
            ORDER BY print_count DESC
        ");
        return $stmt->fetchAll();
    }
}
