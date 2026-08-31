<?php
declare(strict_types=1);
/**
 * Mengo Hospital ID Approval System
 * FINAL PRODUCTION DEMO DATA CLEANUP
 *
 * Removes ALL test/demo ID card records created by the automated test suite.
 * Preserves: application users, schema, config, .env, source code.
 *
 * CONFIRMED DEMO RECORDS (from inspection):
 *   Employees 1-18: All test-suite generated (Dr. Automation Test, Dr. Batch Staff 1-10,
 *                   Nurse Concurrency Simulation, Pending Staff, Correction Staff,
 *                   Nurse Batch Sample 1-4)
 *   Cards 1-18:    All test-suite generated
 *   Batches 1-3:   All test-suite generated (batch 1's "non-demo" items were
 *                  also test records: CAS test card #2, ineligible test cards #13-14)
 */

define('BASE_DIR', dirname(__DIR__));
require BASE_DIR . '/src/autoload.php';
use Mengo\IdApproval\Support\Database;

$pdo = Database::getConnection();

echo "\n";
echo "=======================================================================\n";
echo "  MENGO HOSPITAL — FINAL DEMO DATA CLEANUP\n";
echo "  " . date('Y-m-d H:i:s') . " EAT\n";
echo "=======================================================================\n\n";

// ============================================================
// PRE-FLIGHT: Verify FK + integrity
// ============================================================
$fkStatus = $pdo->query("PRAGMA foreign_keys")->fetchColumn();
if (!$fkStatus) {
    echo "ERROR: Foreign keys are OFF. Refusing to proceed.\n";
    exit(1);
}
echo "Foreign keys: ON ✓\n";
$integrity = $pdo->query("PRAGMA integrity_check")->fetchColumn();
if ($integrity !== 'ok') {
    echo "ERROR: Database integrity check failed: $integrity\n";
    exit(1);
}
echo "Integrity check: ok ✓\n\n";

// ============================================================
// PHASE 1: IDENTIFY ALL DEMO RECORDS
// ============================================================
echo "PHASE 1: IDENTIFYING DEMO RECORDS\n";
echo "----------------------------------------------------------------------\n";

// All 18 employees + cards are from the test suite.
// Verified by inspection: staff_id prefixes BULK-, CAS-, MH-TEST-, INELIGIBLE-, MERGE-
// and names: Dr. Automation Test, Nurse Concurrency Simulation, Dr. Batch Staff 1-10,
//            Pending Staff, Correction Staff, Nurse Batch Sample 1-4
$demoCardIds = [];
$demoEmployeeIds = [];

$demoPatterns = [
    '%Dr. Automation Test%',
    '%Nurse Concurrency Simulation%',
    '%Dr. Batch Staff%',
    '%Pending Staff%',
    '%Correction Staff%',
    '%Nurse Batch Sample%',
    '%FINAL WORKFLOW TEST%',
];

// Also match by staff_id prefix patterns from test suite
$staffIdPatterns = [
    'MH-TEST-%',
    'MH-CAS-%',
    'BULK-APPROVED-%',
    'INELIGIBLE-%',
    'MERGE-EMP-%',
];

$allEmpIds = [];
foreach ($demoPatterns as $p) {
    $stmt = $pdo->prepare("SELECT id, full_name FROM employees WHERE full_name LIKE ?");
    $stmt->execute([$p]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!in_array($row['id'], $allEmpIds)) {
            $allEmpIds[] = $row['id'];
            echo "  DEMO EMPLOYEE (name): [{$row['id']}] {$row['full_name']}\n";
        }
    }
}
foreach ($staffIdPatterns as $p) {
    $stmt = $pdo->prepare("SELECT id, full_name, staff_id FROM employees WHERE staff_id LIKE ?");
    $stmt->execute([$p]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!in_array($row['id'], $allEmpIds)) {
            $allEmpIds[] = $row['id'];
            echo "  DEMO EMPLOYEE (staff_id): [{$row['id']}] {$row['full_name']} ({$row['staff_id']})\n";
        }
    }
}

$demoEmployeeIds = $allEmpIds;

// Get their card IDs
if (!empty($demoEmployeeIds)) {
    $ph = implode(',', array_fill(0, count($demoEmployeeIds), '?'));
    $stmt = $pdo->prepare("SELECT id, card_reference, current_status FROM id_cards WHERE employee_id IN ($ph)");
    $stmt->execute($demoEmployeeIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $card) {
        if (!in_array($card['id'], $demoCardIds)) {
            $demoCardIds[] = $card['id'];
            echo "  DEMO CARD: [card_id={$card['id']}] ref={$card['card_reference']} status={$card['current_status']}\n";
        }
    }
}

if (empty($demoCardIds)) {
    echo "\n  No demo records found. Database may already be clean.\n";
    // Still run integrity check below
}

echo "\n  Total demo employees: " . count($demoEmployeeIds) . "\n";
echo "  Total demo cards: " . count($demoCardIds) . "\n\n";

// Collect PDF paths BEFORE any deletion
$pdfPathsToDelete = [];
if (!empty($demoCardIds)) {
    $ph = implode(',', array_fill(0, count($demoCardIds), '?'));
    $stmt = $pdo->prepare("SELECT file_path FROM id_versions WHERE id_card_id IN ($ph) AND file_path IS NOT NULL AND file_path != ''");
    $stmt->execute($demoCardIds);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pdfPathsToDelete[] = $row['file_path'];
    }
}
echo "  PDF files to delete: " . count($pdfPathsToDelete) . "\n";
foreach ($pdfPathsToDelete as $p) echo "    - $p\n";
echo "\n";

// Identify demo batches
$demoBatchIds = [];
$mixedBatchIds = [];
if (!empty($demoCardIds)) {
    $ph = implode(',', array_fill(0, count($demoCardIds), '?'));
    // Find all batches that contain at least one demo card
    $stmt = $pdo->prepare("SELECT DISTINCT batch_id FROM print_batch_items WHERE id_card_id IN ($ph)");
    $stmt->execute($demoCardIds);
    $batchesWithDemo = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($batchesWithDemo as $batchId) {
        // Check if this batch has ANY non-demo items
        $nonDemoStmt = $pdo->prepare("SELECT COUNT(*) FROM print_batch_items WHERE batch_id=? AND id_card_id NOT IN ($ph)");
        $nonDemoStmt->execute(array_merge([$batchId], $demoCardIds));
        $nonDemoCount = (int)$nonDemoStmt->fetchColumn();

        $ref = $pdo->prepare("SELECT batch_reference FROM print_batches WHERE id=?");
        $ref->execute([$batchId]);
        $batchRef = $ref->fetchColumn();

        if ($nonDemoCount === 0) {
            $demoBatchIds[] = $batchId;
            echo "  PURE DEMO BATCH: [batch_id=$batchId] ref=$batchRef → will DELETE\n";
        } else {
            $mixedBatchIds[] = $batchId;
            echo "  MIXED BATCH: [batch_id=$batchId] ref=$batchRef → $nonDemoCount non-demo items remain (will remove demo items only)\n";
        }
    }
}
echo "\n";

// ============================================================
// PHASE 2: BACKUP
// ============================================================
echo "PHASE 2: CREATING SAFETY BACKUP\n";
echo "----------------------------------------------------------------------\n";

$backupDir = BASE_DIR . '/storage/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}
$backupFile = $backupDir . '/pre_demo_cleanup_' . date('Ymd_His') . '.sqlite';
$sourceDb   = BASE_DIR . '/storage/database/app.sqlite';

try {
    $pdo->exec("VACUUM INTO '$backupFile'");
    if (!file_exists($backupFile) || filesize($backupFile) < 4096) {
        throw new RuntimeException("Backup file too small or missing.");
    }
    $verifyPdo = new PDO('sqlite:' . $backupFile);
    $tableCount = $verifyPdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn();
    $verifyPdo = null;
    echo "  Backup created: " . basename($backupFile) . "\n";
    echo "  Backup verified: $tableCount tables readable ✓\n\n";
} catch (Throwable $e) {
    // Fallback: file copy
    if (!copy($sourceDb, $backupFile)) {
        echo "  CRITICAL ERROR: Backup failed — " . $e->getMessage() . "\n";
        echo "  ABORTING. No changes made.\n";
        exit(1);
    }
    echo "  Backup created (file copy): " . basename($backupFile) . " ✓\n\n";
}

// ============================================================
// PHASE 3: TRANSACTIONAL CLEANUP
// ============================================================
echo "PHASE 3: TRANSACTIONAL DEMO DATA REMOVAL\n";
echo "----------------------------------------------------------------------\n";

if (empty($demoCardIds) && empty($demoEmployeeIds)) {
    echo "  Nothing to remove — database is already clean.\n\n";
} else {
    $ph  = !empty($demoCardIds)    ? implode(',', $demoCardIds)    : '0';
    $eph = !empty($demoEmployeeIds) ? implode(',', $demoEmployeeIds) : '0';

    // Disable FK temporarily only to allow ordered deletion without CASCADE issues
    // We will re-enable immediately after and verify integrity
    $pdo->exec("PRAGMA foreign_keys = OFF");
    $pdo->beginTransaction();

    try {
        // --- 3a. Remove child records of demo cards (order: deepest first) ---
        $steps = [
            "collection_records (demo cards)"   => "DELETE FROM collection_records WHERE id_card_id IN ($ph)",
            "print_batch_items (demo cards)"     => "DELETE FROM print_batch_items WHERE id_card_id IN ($ph)",
            "print_records (demo cards)"         => "DELETE FROM print_records WHERE id_card_id IN ($ph)",
            "correction_requests (demo cards)"   => "DELETE FROM correction_requests WHERE id_card_id IN ($ph)",
            "approval_records (demo cards)"      => "DELETE FROM approval_records WHERE id_card_id IN ($ph)",
            "id_versions (demo cards)"           => "DELETE FROM id_versions WHERE id_card_id IN ($ph)",
            "audit_logs (demo cards)"            => "DELETE FROM audit_logs WHERE id_card_id IN ($ph)",
        ];

        foreach ($steps as $label => $sql) {
            $count = $pdo->query("SELECT COUNT(*) FROM (" . str_replace("DELETE FROM", "SELECT * FROM", $sql) . ")")->fetchColumn();
            $pdo->exec($sql);
            echo "  DELETED: $label → $count rows\n";
        }

        // --- 3b. Remove demo notifications (by id_card_id column) ---
        $notifCols = $pdo->query("PRAGMA table_info(notifications)")->fetchAll(PDO::FETCH_ASSOC);
        $notifColNames = array_column($notifCols, 'name');
        if (in_array('id_card_id', $notifColNames)) {
            $count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE id_card_id IN ($ph)")->fetchColumn();
            $pdo->exec("DELETE FROM notifications WHERE id_card_id IN ($ph)");
            echo "  DELETED: notifications (id_card_id) → $count rows\n";
        } else {
            // Fallback: delete all notifications (DB was fully cleared earlier, so any present are from tests)
            $count = $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
            $pdo->exec("DELETE FROM notifications");
            echo "  DELETED: notifications (all, no id_card_id column) → $count rows\n";
        }

        // --- 3c. Remove demo outbox (has id_card_id column per schema inspection) ---
        $outboxCols = $pdo->query("PRAGMA table_info(notification_outbox)")->fetchAll(PDO::FETCH_ASSOC);
        $outboxColNames = array_column($outboxCols, 'name');
        if (in_array('id_card_id', $outboxColNames)) {
            $count = $pdo->query("SELECT COUNT(*) FROM notification_outbox WHERE id_card_id IN ($ph)")->fetchColumn();
            $pdo->exec("DELETE FROM notification_outbox WHERE id_card_id IN ($ph)");
            echo "  DELETED: notification_outbox (id_card_id) → $count rows\n";
        } else {
            $count = $pdo->query("SELECT COUNT(*) FROM notification_outbox")->fetchColumn();
            $pdo->exec("DELETE FROM notification_outbox");
            echo "  DELETED: notification_outbox (all) → $count rows\n";
        }

        // --- 3d. Remove pure-demo print batches ---
        if (!empty($demoBatchIds)) {
            $bph = implode(',', $demoBatchIds);
            $pdo->exec("DELETE FROM print_batch_items WHERE batch_id IN ($bph)");
            $count = $pdo->query("SELECT COUNT(*) FROM print_batches WHERE id IN ($bph)")->fetchColumn();
            $pdo->exec("DELETE FROM print_batches WHERE id IN ($bph)");
            echo "  DELETED: pure-demo print_batches → $count rows\n";
        }

        // For mixed batches: demo items already removed above, batch header kept IF it has remaining items
        if (!empty($mixedBatchIds)) {
            foreach ($mixedBatchIds as $batchId) {
                $remaining = (int)$pdo->query("SELECT COUNT(*) FROM print_batch_items WHERE batch_id=$batchId")->fetchColumn();
                if ($remaining === 0) {
                    $pdo->exec("DELETE FROM print_batches WHERE id=$batchId");
                    echo "  DELETED: mixed batch_id=$batchId (no items remain after demo removal)\n";
                } else {
                    echo "  KEPT: mixed batch_id=$batchId ($remaining non-demo items remain)\n";
                }
            }
        }

        // --- 3e. Remove demo ID cards ---
        $count = $pdo->query("SELECT COUNT(*) FROM id_cards WHERE id IN ($ph)")->fetchColumn();
        $pdo->exec("DELETE FROM id_cards WHERE id IN ($ph)");
        echo "  DELETED: id_cards → $count rows\n";

        // --- 3f. Remove demo employees ---
        $count = $pdo->query("SELECT COUNT(*) FROM employees WHERE id IN ($eph)")->fetchColumn();
        $pdo->exec("DELETE FROM employees WHERE id IN ($eph)");
        echo "  DELETED: employees → $count rows\n";

        // --- 3g. Clean orphaned audit_logs (where id_card_id no longer exists) ---
        $orphanAudit = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE id_card_id IS NOT NULL AND id_card_id NOT IN (SELECT id FROM id_cards)")->fetchColumn();
        if ($orphanAudit > 0) {
            $pdo->exec("DELETE FROM audit_logs WHERE id_card_id IS NOT NULL AND id_card_id NOT IN (SELECT id FROM id_cards)");
            echo "  DELETED: orphaned audit_logs → $orphanAudit rows\n";
        }

        $pdo->commit();
        echo "\n  ✅ Transaction COMMITTED successfully.\n\n";

    } catch (Throwable $e) {
        $pdo->rollBack();
        $pdo->exec("PRAGMA foreign_keys = ON");
        echo "\n  ❌ ERROR — ROLLBACK PERFORMED: " . $e->getMessage() . "\n";
        exit(1);
    }

    $pdo->exec("PRAGMA foreign_keys = ON");
    echo "  Foreign keys re-enabled: ON ✓\n\n";
}

// ============================================================
// PHASE 4: DELETE DEMO PDF FILES
// ============================================================
echo "PHASE 4: DELETING DEMO PDF FILES\n";
echo "----------------------------------------------------------------------\n";

$protectedPath = BASE_DIR . '/storage/uploads/protected';
$deleted = 0;
$missing = 0;
$failed  = 0;
$totalBytes = 0;

foreach ($pdfPathsToDelete as $relativePath) {
    $filename  = basename($relativePath);
    $fullPath  = $protectedPath . '/' . $filename;
    if (!file_exists($fullPath)) {
        echo "  MISSING (already gone): $filename\n";
        $missing++;
        continue;
    }
    $totalBytes += filesize($fullPath);
    if (unlink($fullPath)) {
        echo "  DELETED: $filename\n";
        $deleted++;
    } else {
        echo "  WARN: Could not delete: $filename\n";
        $failed++;
    }
}

// Delete temp PDFs (batch merges are always regenerated; temp files are ephemeral)
$tempFiles = glob(BASE_DIR . '/storage/temp/*.pdf') ?: [];
$tempDeleted = 0;
foreach ($tempFiles as $tf) {
    if (unlink($tf)) {
        $tempDeleted++;
        echo "  DELETED TEMP: " . basename($tf) . "\n";
    }
}

echo "\n  PDF files deleted:  $deleted\n";
echo "  PDF files missing:  $missing (already gone)\n";
if ($failed > 0) echo "  PDF delete failed:  $failed ⚠️\n";
echo "  Temp files deleted: $tempDeleted\n";
echo "  Space freed:        " . round($totalBytes / 1024 / 1024, 2) . " MB\n\n";

// ============================================================
// PHASE 5: INTEGRITY VERIFICATION
// ============================================================
echo "PHASE 5: POST-CLEANUP INTEGRITY VERIFICATION\n";
echo "----------------------------------------------------------------------\n";

$pdo->exec("PRAGMA foreign_keys = ON");
$fk      = $pdo->query("PRAGMA foreign_keys")->fetchColumn();
$integ   = $pdo->query("PRAGMA integrity_check")->fetchColumn();
$journal = $pdo->query("PRAGMA journal_mode")->fetchColumn();

echo "  foreign_keys:    " . ($fk ? "ON ✓" : "OFF ✗") . "\n";
echo "  integrity_check: $integ " . ($integ === 'ok' ? "✓" : "✗") . "\n";
echo "  journal_mode:    $journal\n\n";

// Orphan checks
$orphanChecks = [
    'id_cards → orphaned employees'       => "SELECT COUNT(*) FROM id_cards WHERE employee_id NOT IN (SELECT id FROM employees)",
    'id_versions → missing cards'         => "SELECT COUNT(*) FROM id_versions WHERE id_card_id NOT IN (SELECT id FROM id_cards)",
    'approval_records → missing cards'    => "SELECT COUNT(*) FROM approval_records WHERE id_card_id NOT IN (SELECT id FROM id_cards)",
    'correction_requests → missing cards' => "SELECT COUNT(*) FROM correction_requests WHERE id_card_id NOT IN (SELECT id FROM id_cards)",
    'print_records → missing cards'       => "SELECT COUNT(*) FROM print_records WHERE id_card_id NOT IN (SELECT id FROM id_cards)",
    'print_batch_items → missing batches' => "SELECT COUNT(*) FROM print_batch_items WHERE batch_id NOT IN (SELECT id FROM print_batches)",
    'audit_logs → ghost cards'            => "SELECT COUNT(*) FROM audit_logs WHERE id_card_id IS NOT NULL AND id_card_id NOT IN (SELECT id FROM id_cards)",
];

$allOk = true;
foreach ($orphanChecks as $label => $sql) {
    $count = (int)$pdo->query($sql)->fetchColumn();
    $status = $count === 0 ? "OK ✓" : "ORPHANS: $count ✗";
    if ($count > 0) $allOk = false;
    printf("  %-45s %s\n", $label, $status);
}
echo "\n";

// ============================================================
// PHASE 6: POST-CLEANUP STATE
// ============================================================
echo "PHASE 6: FINAL DATABASE STATE\n";
echo "----------------------------------------------------------------------\n";

$tables = ['users','employees','id_cards','id_versions','approval_records',
           'correction_requests','print_records','print_batches','print_batch_items',
           'collection_records','notifications','notification_outbox','audit_logs'];
foreach ($tables as $t) {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM \"$t\"")->fetchColumn();
    printf("  %-40s %d rows\n", $t, $count);
}
echo "\n";

// Verify NO demo names remain
echo "  Searching for residual demo records:\n";
$demoNameCheck = $pdo->query("SELECT id, full_name FROM employees WHERE full_name LIKE '%Batch Staff%' OR full_name LIKE '%Automation Test%' OR full_name LIKE '%Concurrency Simulation%' OR full_name LIKE '%Batch Sample%' OR full_name LIKE '%Pending Staff%' OR full_name LIKE '%Correction Staff%'")->fetchAll(PDO::FETCH_ASSOC);
if (empty($demoNameCheck)) {
    echo "    No demo employee names found ✓\n";
} else {
    foreach ($demoNameCheck as $r) {
        echo "    STILL EXISTS: [{$r['id']}] {$r['full_name']} ✗\n";
    }
}

$demoCardCheck = $pdo->query("SELECT id, card_reference FROM id_cards WHERE card_reference LIKE 'MH-TEST-%' OR card_reference LIKE 'MH-BULK-%' OR card_reference LIKE 'MH-CAS-%' OR card_reference LIKE 'MH-MRG-%' OR card_reference LIKE 'MH-INEL-%'")->fetchAll(PDO::FETCH_ASSOC);
if (empty($demoCardCheck)) {
    echo "    No demo card references found ✓\n";
} else {
    foreach ($demoCardCheck as $r) {
        echo "    STILL EXISTS: [{$r['id']}] {$r['card_reference']} ✗\n";
    }
}
echo "\n";

// PDF storage state after cleanup
$remainingPdfs = glob($protectedPath . '/*.pdf') ?: [];
echo "  PDFs remaining in protected storage: " . count($remainingPdfs) . "\n";
if (!empty($remainingPdfs)) {
    foreach ($remainingPdfs as $pdf) {
        // Only keep if referenced in DB
        $fname = basename($pdf);
        $dbRef = $pdo->prepare("SELECT COUNT(*) FROM id_versions WHERE file_path=? OR file_path LIKE ?");
        $dbRef->execute([$fname, '%' . $fname . '%']);
        $dbCount = (int)$dbRef->fetchColumn();
        $status = $dbCount > 0 ? "LEGITIMATE (in DB)" : "ORPHAN ✗";
        echo "    $fname → $status\n";
    }
}
echo "\n";

// Log this cleanup event
$pdo->prepare("INSERT INTO audit_logs (user_id, user_name, user_role, action, entity_type, details, ip_address, user_agent, created_at) VALUES (NULL, 'SYSTEM', 'SYSTEM', 'DEMO_DATA_CLEANUP', 'SYSTEM', 'Production cleanup: removed all test/demo ID card records and associated PDFs. System is clean for real use.', '127.0.0.1', 'CleanupScript/2.0', ?)")->execute([date('Y-m-d H:i:s')]);

// ============================================================
// FINAL REPORT
// ============================================================
echo "=======================================================================\n";
echo "  FINAL PRODUCTION CLEANUP REPORT\n";
echo "=======================================================================\n\n";

$empCount     = (int)$pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$cardCount    = (int)$pdo->query("SELECT COUNT(*) FROM id_cards")->fetchColumn();
$batchCount   = (int)$pdo->query("SELECT COUNT(*) FROM print_batches")->fetchColumn();
$notifCount   = (int)$pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
$outboxCount  = (int)$pdo->query("SELECT COUNT(*) FROM notification_outbox")->fetchColumn();
$auditCount   = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$userCount    = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$pdfCount     = count(glob($protectedPath . '/*.pdf') ?: []);

echo "  USERS PRESERVED:            $userCount ✓\n";
echo "  EMPLOYEES REMAINING:        $empCount " . ($empCount === 0 ? "✓ (clean)" : "") . "\n";
echo "  ID CARDS REMAINING:         $cardCount " . ($cardCount === 0 ? "✓ (clean)" : "") . "\n";
echo "  PRINT BATCHES REMAINING:    $batchCount " . ($batchCount === 0 ? "✓ (clean)" : "") . "\n";
echo "  NOTIFICATIONS REMAINING:    $notifCount " . ($notifCount === 0 ? "✓ (clean)" : "") . "\n";
echo "  OUTBOX REMAINING:           $outboxCount " . ($outboxCount === 0 ? "✓ (clean)" : "") . "\n";
echo "  AUDIT LOG ENTRIES:          $auditCount (1 = cleanup event)\n";
echo "  PDF FILES IN STORAGE:       $pdfCount " . ($pdfCount === 0 ? "✓ (clean)" : "") . "\n";
echo "  DB INTEGRITY:               $integ " . ($integ === 'ok' ? "✓" : "✗") . "\n";
echo "  FOREIGN KEYS:               " . ($fk ? "ON ✓" : "OFF ✗") . "\n";
echo "  ORPHAN RECORDS:             " . ($allOk ? "NONE ✓" : "PRESENT — check above ✗") . "\n";
echo "\n";

$isClean = $cardCount === 0 && $pdfCount === 0 && $integ === 'ok' && $fk && $allOk;

if ($isClean) {
    echo "  ✅ STATUS: SYSTEM IS CLEAN AND PRODUCTION-READY\n\n";
    echo "  The ID Designer can now log in and upload real hospital employee IDs.\n";
    echo "  Every upload will enter the HR → Printing → Collection workflow.\n";
} else {
    echo "  ⚠️  STATUS: CLEANUP COMPLETED WITH WARNINGS — review output above\n";
}
echo "\n=======================================================================\n\n";
