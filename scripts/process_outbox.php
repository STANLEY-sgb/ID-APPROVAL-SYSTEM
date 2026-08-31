<?php
/**
 * Mengo Hospital ID Approval System
 * Notification Outbox Worker
 *
 * Reads PENDING rows from notification_outbox and delivers them via EmailService.
 * Designed to be run as a scheduled task (Windows Task Scheduler or cron):
 *
 *   Windows Task Scheduler (every 5 minutes):
 *     Program: C:\xampp\php\php.exe
 *     Arguments: "e:\ID APPROVAL SYSTEM\scripts\process_outbox.php"
 *
 *   Linux/macOS cron (every 5 minutes):
 *     * /5 * * * * /usr/bin/php /var/www/html/scripts/process_outbox.php >> /var/log/notif_outbox.log 2>&1
 *
 * Safety guarantees:
 *  - Rows are claimed atomically (status → PROCESSING) before delivery.
 *  - On success, row is marked SENT with processed_at timestamp.
 *  - On failure, row increments attempts; permanently FAILED after max_attempts (default: 3).
 *  - The worker is idempotent — running concurrently is safe (claim step prevents duplicate delivery).
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/src/autoload.php';

use Mengo\IdApproval\Repositories\NotificationOutboxRepository;
use Mengo\IdApproval\Services\EmailService;
use Mengo\IdApproval\Support\Timezone;

Timezone::configure();

$batchSize    = (int)($argv[1] ?? 20);   // Configurable: php process_outbox.php 50
$startTime    = microtime(true);
$processed    = 0;
$sent         = 0;
$failed       = 0;

$outboxRepo   = new NotificationOutboxRepository();
$emailService = new EmailService();

$pendingBefore = $outboxRepo->countByStatus('PENDING');
$rows = $outboxRepo->claimPendingBatch($batchSize);

echo sprintf(
    "[%s] Outbox Worker Started — %d PENDING found, claiming %d\n",
    Timezone::nowString(),
    $pendingBefore,
    count($rows)
);

foreach ($rows as $row) {
    $processed++;
    $toEmails = json_decode($row->to_emails, true);

    if (empty($toEmails) || !is_array($toEmails)) {
        $outboxRepo->markFailed($row->id, "No valid recipients in to_emails: {$row->to_emails}");
        $failed++;
        echo "  [SKIP] #{$row->id} {$row->event_type} — no valid recipients\n";
        continue;
    }

    try {
        $details = $row->details_json ? json_decode($row->details_json, true) : [];

        $emailService->send(
            $toEmails,
            $row->subject,
            $row->headline,
            $row->body_text,
            $details ?: []
        );

        $outboxRepo->markSent($row->id);
        $sent++;
        echo "  [SENT] #{$row->id} {$row->event_type} → " . implode(', ', $toEmails) . "\n";
    } catch (\Throwable $e) {
        $errorMsg = get_class($e) . ': ' . $e->getMessage();
        $outboxRepo->markFailed($row->id, $errorMsg);
        $failed++;
        echo "  [FAIL] #{$row->id} {$row->event_type} — {$errorMsg}\n";
    }
}

$elapsed        = round((microtime(true) - $startTime) * 1000, 1);
$pendingAfter   = $outboxRepo->countByStatus('PENDING');
$totalFailed    = $outboxRepo->countByStatus('FAILED');

echo sprintf(
    "\n[%s] Done — %d processed | %d sent | %d failed | %d still pending | %d permanently failed | %s ms\n",
    Timezone::nowString(),
    $processed,
    $sent,
    $failed,
    $pendingAfter,
    $totalFailed,
    $elapsed
);

exit($failed > 0 ? 1 : 0);
