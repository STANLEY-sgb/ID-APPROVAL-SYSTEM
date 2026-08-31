<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Repositories;

use Mengo\IdApproval\Models\PrintBatch;
use Mengo\IdApproval\Models\PrintBatchItem;
use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;
use PDO;

class PrintBatchRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO print_batches (
            batch_reference,
            printing_user_id,
            printing_user_name,
            status,
            total_cards,
            selected_count,
            valid_count,
            failed_count,
            page_count,
            file_size,
            orientation,
            page_size,
            output_filename,
            output_path,
            output_hash,
            notes,
            error_summary,
            download_count,
            created_at,
            completed_at,
            expires_at
        ) VALUES (
            :ref, :user_id, :user_name, :status, :total, :selected, :valid, :failed,
            :pages, :size, :orientation, :page_size, :filename, :path, :hash,
            :notes, :errors, :downloads, :created_at, :completed_at, :expires_at
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':ref' => $data['batch_reference'],
            ':user_id' => $data['printing_user_id'],
            ':user_name' => $data['printing_user_name'],
            ':status' => $data['status'] ?? PrintBatch::STATUS_READY,
            ':total' => $data['total_cards'] ?? ($data['valid_count'] ?? 1),
            ':selected' => $data['selected_count'] ?? 0,
            ':valid' => $data['valid_count'] ?? 0,
            ':failed' => $data['failed_count'] ?? 0,
            ':pages' => $data['page_count'] ?? 0,
            ':size' => $data['file_size'] ?? 0,
            ':orientation' => $data['orientation'] ?? 'ORIGINAL',
            ':page_size' => $data['page_size'] ?? 'ORIGINAL',
            ':filename' => $data['output_filename'] ?? null,
            ':path' => $data['output_path'] ?? null,
            ':hash' => $data['output_hash'] ?? null,
            ':notes' => $data['notes'] ?? null,
            ':errors' => $data['error_summary'] ?? null,
            ':downloads' => $data['download_count'] ?? 0,
            ':created_at' => $data['created_at'] ?? Timezone::nowString(),
            ':completed_at' => $data['completed_at'] ?? null,
            ':expires_at' => $data['expires_at'] ?? null
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        foreach ($data as $key => $val) {
            $fields[] = "{$key} = :{$key}";
            $params[":{$key}"] = $val;
        }

        if (empty($fields)) return false;

        $sql = "UPDATE print_batches SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function findById(int $id): ?PrintBatch
    {
        $stmt = $this->pdo->prepare("SELECT * FROM print_batches WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $row['items'] = $this->getItems($id);
        return PrintBatch::fromArray($row);
    }

    public function findByReference(string $ref): ?PrintBatch
    {
        $stmt = $this->pdo->prepare("SELECT * FROM print_batches WHERE batch_reference = ?");
        $stmt->execute([$ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $row['items'] = $this->getItems((int)$row['id']);
        return PrintBatch::fromArray($row);
    }

    public function addItem(array $data): int
    {
        $sql = "INSERT INTO print_batch_items (
            batch_id,
            id_card_id,
            approved_version_id,
            employee_id,
            employee_name,
            sequence_number,
            validation_status,
            failure_reason,
            included_in_output,
            is_printed,
            printed_at
        ) VALUES (
            :batch_id, :card_id, :version_id, :employee_id, :employee_name,
            :sequence, :status, :failure, :included, :is_printed, :printed_at
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':batch_id' => $data['batch_id'],
            ':card_id' => $data['id_card_id'],
            ':version_id' => $data['approved_version_id'] ?? null,
            ':employee_id' => $data['employee_id'] ?? null,
            ':employee_name' => $data['employee_name'],
            ':sequence' => $data['sequence_number'] ?? 1,
            ':status' => $data['validation_status'] ?? PrintBatchItem::STATUS_VALID,
            ':failure' => $data['failure_reason'] ?? null,
            ':included' => isset($data['included_in_output']) ? (int)$data['included_in_output'] : 1,
            ':is_printed' => isset($data['is_printed']) ? (int)$data['is_printed'] : 0,
            ':printed_at' => $data['printed_at'] ?? null
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function getItems(int $batchId): array
    {
        $sql = "SELECT pbi.*, ic.card_reference, e.staff_id, d.name AS department_name
                FROM print_batch_items pbi
                LEFT JOIN id_cards ic ON ic.id = pbi.id_card_id
                LEFT JOIN employees e ON e.id = pbi.employee_id
                LEFT JOIN departments d ON d.id = e.department_id
                WHERE pbi.batch_id = ?
                ORDER BY pbi.sequence_number ASC, pbi.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$batchId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($r) => PrintBatchItem::fromArray($r), $rows);
    }

    public function markItemPrinted(int $batchId, int $cardId, string $now): bool
    {
        $stmt = $this->pdo->prepare("UPDATE print_batch_items SET is_printed = 1, printed_at = ? WHERE batch_id = ? AND id_card_id = ?");
        return $stmt->execute([$now, $batchId, $cardId]);
    }

    public function incrementDownloadCount(int $batchId): void
    {
        $stmt = $this->pdo->prepare("UPDATE print_batches SET download_count = download_count + 1 WHERE id = ?");
        $stmt->execute([$batchId]);
    }

    public function getRecent(int $limit = 20, int $offset = 0): array
    {
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        $stmt = $this->pdo->prepare("SELECT * FROM print_batches ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r) => PrintBatch::fromArray($r), $rows);
    }

    public function countAll(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM print_batches");
        return (int)$stmt->fetchColumn();
    }

    public function getExpiredBatches(int $expirationHours = 48): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - ($expirationHours * 3600));
        $now = Timezone::nowString();
        $stmt = $this->pdo->prepare("SELECT * FROM print_batches WHERE status != 'EXPIRED' AND (created_at <= :cutoff OR (expires_at IS NOT NULL AND expires_at <= :now))");
        $stmt->execute([':cutoff' => $cutoff, ':now' => $now]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r) => PrintBatch::fromArray($r), $rows);
    }
}
