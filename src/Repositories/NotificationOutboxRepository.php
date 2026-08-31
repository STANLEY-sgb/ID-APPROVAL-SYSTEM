<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Repositories;

use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;
use PDO;

/**
 * NotificationOutboxRepository
 *
 * Manages the notification_outbox table — the persistence layer for the
 * transactional notification outbox pattern.
 *
 * Workflow transitions write outbox rows inside the same DB transaction as
 * the state change. The CLI worker (scripts/process_outbox.php) reads pending
 * rows and delivers emails outside the transaction, decoupling email delivery
 * latency from workflow performance.
 */
class NotificationOutboxRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Insert a new pending outbox event.
     *
     * @param array{
     *   event_type: string,
     *   to_emails: string[],
     *   subject: string,
     *   headline: string,
     *   body_text: string,
     *   details_json?: array<string,mixed>|null,
     *   id_card_id?: int|null
     * } $data
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO notification_outbox
                (event_type, to_emails, subject, headline, body_text, details_json, id_card_id, status, attempts, created_at)
            VALUES
                (:event_type, :to_emails, :subject, :headline, :body_text, :details_json, :id_card_id, 'PENDING', 0, :created_at)
        ");

        $stmt->execute([
            ':event_type'   => $data['event_type'],
            ':to_emails'    => json_encode($data['to_emails'] ?? []),
            ':subject'      => $data['subject'],
            ':headline'     => $data['headline'],
            ':body_text'    => $data['body_text'],
            ':details_json' => isset($data['details_json']) ? json_encode($data['details_json']) : null,
            ':id_card_id'   => $data['id_card_id'] ?? null,
            ':created_at'   => Timezone::nowString(),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Atomically claim a batch of PENDING rows for processing.
     * Sets status to PROCESSING to prevent duplicate delivery by concurrent workers.
     *
     * @return array<int, object> List of outbox row objects
     */
    public function claimPendingBatch(int $limit = 10): array
    {
        // Fetch PENDING rows whose attempt count is under the max
        $stmt = $this->pdo->prepare("
            SELECT * FROM notification_outbox
            WHERE status = 'PENDING'
              AND attempts < max_attempts
            ORDER BY created_at ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

        if (empty($rows)) {
            return [];
        }

        // Mark claimed rows as PROCESSING
        $ids = array_map(fn($r) => (int)$r->id, $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $upd = $this->pdo->prepare(
            "UPDATE notification_outbox SET status = 'PROCESSING' WHERE id IN ({$placeholders}) AND status = 'PENDING'"
        );
        $upd->execute($ids);

        return $rows;
    }

    /**
     * Mark an outbox row as successfully delivered.
     */
    public function markSent(int $id): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE notification_outbox
            SET status = 'SENT', processed_at = :now
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id, ':now' => Timezone::nowString()]);
    }

    /**
     * Mark an outbox row as failed, incrementing attempts.
     * If attempts >= max_attempts the status becomes FAILED (terminal).
     */
    public function markFailed(int $id, string $error): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE notification_outbox
            SET
                attempts   = attempts + 1,
                last_error = :error,
                status     = CASE WHEN (attempts + 1) >= max_attempts THEN 'FAILED' ELSE 'PENDING' END,
                processed_at = CASE WHEN (attempts + 1) >= max_attempts THEN :now ELSE NULL END
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id, ':error' => $error, ':now' => Timezone::nowString()]);
    }

    /**
     * Count rows by status — used for monitoring / health dashboards.
     */
    public function countByStatus(string $status): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notification_outbox WHERE status = ?");
        $stmt->execute([$status]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Retrieve recent outbox rows for admin inspection.
     *
     * @return array<int, object>
     */
    public function getRecent(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM notification_outbox
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
