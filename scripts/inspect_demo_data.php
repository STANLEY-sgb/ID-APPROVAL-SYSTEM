<?php
declare(strict_types=1);
/**
 * Deep inspection script - identifies all demo/test records
 * without modifying anything
 */
define('BASE_DIR', dirname(__DIR__));
require BASE_DIR . '/src/autoload.php';
use Mengo\IdApproval\Support\Database;

$pdo = Database::getConnection();

echo "\n";
echo "=======================================================================\n";
echo "  MENGO HOSPITAL - FULL DATABASE INSPECTION (READ-ONLY)\n";
echo "  " . date('Y-m-d H:i:s') . " EAT\n";
echo "=======================================================================\n\n";

// 1. ALL TABLES + ROW COUNTS
echo "ALL TABLES:\n";
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    $count = $pdo->query("SELECT COUNT(*) FROM \"$t\"")->fetchColumn();
    printf("  %-40s %d rows\n", $t, (int)$count);
}
echo "\n";

// 2. ALL EMPLOYEES
echo "ALL EMPLOYEES:\n";
$employees = $pdo->query("SELECT id, staff_id, full_name, status FROM employees ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
if (empty($employees)) {
    echo "  (none)\n";
} else {
    foreach ($employees as $e) {
        printf("  [emp_id=%d] staff_id=%-15s name=%s [%s]\n",
            $e['id'], $e['staff_id'], $e['full_name'], $e['status']);
    }
}
echo "\n";

// 3. ALL ID CARDS
echo "ALL ID CARDS:\n";
$cards = $pdo->query("
    SELECT c.id, c.card_reference, c.current_status, c.current_version_number,
           e.full_name as employee_name, e.id as employee_id,
           c.created_by_user_id
    FROM id_cards c
    LEFT JOIN employees e ON e.id = c.employee_id
    ORDER BY c.id
")->fetchAll(PDO::FETCH_ASSOC);
if (empty($cards)) {
    echo "  (none)\n";
} else {
    foreach ($cards as $c) {
        printf("  [card_id=%d] ref=%-20s status=%-25s v%d emp='%s' [emp_id=%d]\n",
            $c['id'], $c['card_reference'], $c['current_status'],
            $c['current_version_number'], $c['employee_name'], $c['employee_id']);
    }
}
echo "\n";

// 4. IDENTIFY DEMO/TEST RECORDS
echo "IDENTIFIED DEMO/TEST RECORDS:\n";
$demoPatterns = ['%Dr. Batch Staff%', '%Test%', '%Demo%', '%Sample%', '%Dummy%', '%Batch Staff%', '%FINAL WORKFLOW TEST%'];
$demoEmployeeIds = [];
$demoCardIds = [];

foreach ($demoPatterns as $pattern) {
    $stmt = $pdo->prepare("SELECT id, full_name FROM employees WHERE full_name LIKE ?");
    $stmt->execute([$pattern]);
    $found = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($found as $f) {
        if (!in_array($f['id'], $demoEmployeeIds)) {
            $demoEmployeeIds[] = $f['id'];
            echo "  DEMO EMPLOYEE: [id={$f['id']}] '{$f['full_name']}' (matched: $pattern)\n";
        }
    }
}

if (!empty($demoEmployeeIds)) {
    $placeholders = implode(',', array_fill(0, count($demoEmployeeIds), '?'));
    $stmt = $pdo->prepare("SELECT id, card_reference, current_status FROM id_cards WHERE employee_id IN ($placeholders)");
    $stmt->execute($demoEmployeeIds);
    $demoCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($demoCards as $dc) {
        if (!in_array($dc['id'], $demoCardIds)) {
            $demoCardIds[] = $dc['id'];
            echo "  DEMO CARD: [id={$dc['id']}] ref={$dc['card_reference']} status={$dc['current_status']}\n";
        }
    }
} else {
    echo "  (none found by name patterns)\n";
}

// Also check by known IDs 3-12
$specificIds = [3,4,5,6,7,8,9,10,11,12];
$stmt = $pdo->prepare("SELECT c.id, c.card_reference, c.current_status, e.full_name FROM id_cards c LEFT JOIN employees e ON e.id=c.employee_id WHERE c.id IN (" . implode(',', $specificIds) . ")");
$stmt->execute();
$specificCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!empty($specificCards)) {
    echo "\n  BY SPECIFIC ID (3-12):\n";
    foreach ($specificCards as $sc) {
        echo "    [card_id={$sc['id']}] ref={$sc['card_reference']} status={$sc['current_status']} emp='{$sc['full_name']}'\n";
        if (!in_array($sc['id'], $demoCardIds)) {
            $demoCardIds[] = $sc['id'];
        }
    }
}
echo "\n  Total demo card IDs identified: " . count($demoCardIds) . ": " . implode(', ', $demoCardIds) . "\n";
echo "\n";

// 5. DEPENDENCIES FOR DEMO CARDS
if (!empty($demoCardIds)) {
    $ph = implode(',', $demoCardIds);
    echo "DEMO CARD DEPENDENCIES:\n";

    $r = $pdo->query("SELECT COUNT(*) FROM id_versions WHERE id_card_id IN ($ph)")->fetchColumn();
    echo "  id_versions: $r rows\n";
    $r = $pdo->query("SELECT COUNT(*) FROM approval_records WHERE id_card_id IN ($ph)")->fetchColumn();
    echo "  approval_records: $r rows\n";
    $r = $pdo->query("SELECT COUNT(*) FROM correction_requests WHERE id_card_id IN ($ph)")->fetchColumn();
    echo "  correction_requests: $r rows\n";
    $r = $pdo->query("SELECT COUNT(*) FROM print_records WHERE id_card_id IN ($ph)")->fetchColumn();
    echo "  print_records: $r rows\n";
    $r = $pdo->query("SELECT COUNT(*) FROM print_batch_items WHERE id_card_id IN ($ph)")->fetchColumn();
    echo "  print_batch_items: $r rows\n";
    $r = $pdo->query("SELECT COUNT(*) FROM collection_records WHERE id_card_id IN ($ph)")->fetchColumn();
    echo "  collection_records: $r rows\n";
    $r = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE id_card_id IN ($ph)")->fetchColumn();
    echo "  audit_logs: $r rows\n";
    echo "\n";

    // PDF files for demo cards
    echo "PDF FILES FOR DEMO CARDS:\n";
    $vStmt = $pdo->prepare("SELECT file_path, file_sha256 FROM id_versions WHERE id_card_id IN ($ph)");
    $vStmt->execute();
    $vRows = $vStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($vRows as $v) {
        $exists = file_exists(BASE_DIR . '/storage/uploads/protected/' . $v['file_path']) ? "EXISTS" : "MISSING";
        echo "  {$v['file_path']} [$exists]\n";
    }
    echo "\n";

    // Print batches for demo cards
    echo "PRINT BATCHES CONTAINING DEMO CARDS:\n";
    $bStmt = $pdo->prepare("SELECT DISTINCT pbi.batch_id, pb.batch_reference, pb.status FROM print_batch_items pbi JOIN print_batches pb ON pb.id=pbi.batch_id WHERE pbi.id_card_id IN ($ph)");
    $bStmt->execute();
    $bRows = $bStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($bRows)) {
        echo "  (none)\n";
    } else {
        foreach ($bRows as $b) {
            // Check if batch has ANY non-demo cards
            $nonDemoInBatch = $pdo->prepare("SELECT COUNT(*) FROM print_batch_items WHERE batch_id=? AND id_card_id NOT IN ($ph)");
            $nonDemoInBatch->execute([$b['batch_id']]);
            $nonDemoCount = $nonDemoInBatch->fetchColumn();
            $pureDemo = $nonDemoCount == 0 ? "PURE DEMO (safe to delete)" : "MIXED (has $nonDemoCount non-demo items - keep!)";
            echo "  [batch_id={$b['batch_id']}] ref={$b['batch_reference']} status={$b['status']} → $pureDemo\n";
        }
    }
    echo "\n";

    // Notifications tied to demo cards
    echo "NOTIFICATIONS FOR DEMO CARDS:\n";
    $nStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE related_id IN ($ph) AND related_type='id_card'");
    $nStmt->execute();
    $nCount = $nStmt->fetchColumn();
    echo "  notifications: $nCount rows\n";
    $noStmt = $pdo->prepare("SELECT COUNT(*) FROM notification_outbox WHERE related_id IN ($ph) AND related_type='id_card'");
    $noStmt->execute();
    $noCount = $noStmt->fetchColumn();
    echo "  notification_outbox: $noCount rows\n\n";
}

// 6. USERS
echo "ALL USERS (will be PRESERVED):\n";
$users = $pdo->query("SELECT id, username, name, email, role, status FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) {
    printf("  [%d] %-20s %-20s %-15s %s\n", $u['id'], $u['username'], $u['role'], $u['status'], $u['email']);
}
echo "\n";

// 7. CURRENT STORAGE STATE
echo "STORAGE STATE:\n";
$protectedPath = BASE_DIR . '/storage/uploads/protected';
$allPdfs = glob($protectedPath . '/*.pdf') ?: [];
echo "  PDFs in storage/uploads/protected: " . count($allPdfs) . "\n";
$tempFiles = glob(BASE_DIR . '/storage/temp/*.pdf') ?: [];
echo "  PDFs in storage/temp: " . count($tempFiles) . "\n";
$backups = glob(BASE_DIR . '/storage/backups/*.sqlite') ?: [];
echo "  Backups in storage/backups: " . count($backups) . "\n\n";

// Final summary
echo "=======================================================================\n";
echo "  INSPECTION SUMMARY\n";
echo "=======================================================================\n";
echo "  Demo employee IDs found: " . implode(', ', $demoEmployeeIds) . "\n";
echo "  Demo card IDs found:     " . implode(', ', $demoCardIds) . "\n";
echo "  Total demo cards:        " . count($demoCardIds) . "\n";
echo "  Total employees:         " . $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn() . "\n";
echo "  Total id_cards:          " . $pdo->query("SELECT COUNT(*) FROM id_cards")->fetchColumn() . "\n";
echo "=======================================================================\n\n";
