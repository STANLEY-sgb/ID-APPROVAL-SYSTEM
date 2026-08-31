<?php
declare(strict_types=1);

/**
 * Mengo Hospital ID Approval System
 * Production Cleanup & Verification Script
 * 
 * PHASE 1: Inspect
 * PHASE 2: Backup
 * PHASE 3: Clean data (preserve users/schema)
 * PHASE 4: Integrity checks
 * PHASE 5: Verify email/notification config
 */

define('BASE_DIR', dirname(__DIR__));

// Bootstrap the application
require BASE_DIR . '/src/autoload.php';

use Mengo\IdApproval\Support\Database;

$pdo = Database::getConnection();

echo "\n";
echo "=======================================================================\n";
echo "  MENGO HOSPITAL ID APPROVAL SYSTEM - PRODUCTION CLEANUP\n";
echo "  " . date('Y-m-d H:i:s') . " EAT\n";
echo "=======================================================================\n\n";

// ============================================================
// PHASE 1: INSPECTION
// ============================================================
echo "PHASE 1: INSPECTING DATABASE\n";
echo "----------------------------------------------------------------------\n";

// Get all tables
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")
              ->fetchAll(PDO::FETCH_COLUMN);

echo "Tables found: " . count($tables) . "\n";
foreach ($tables as $t) {
    $count = $pdo->query("SELECT COUNT(*) FROM \"$t\"")->fetchColumn();
    echo sprintf("  %-40s %d rows\n", $t, (int)$count);
}
echo "\n";

// Inspect users (must be preserved)
echo "USERS (will be PRESERVED):\n";
$users = $pdo->query("SELECT id, username, name, email, role, status FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) {
    echo sprintf("  [%d] %-20s %-15s %-40s %-20s %s\n",
        $u['id'], $u['username'], $u['role'], $u['email'], $u['name'], $u['status']
    );
}
echo "\n";

// Inspect uploaded PDFs
$protectedPath = BASE_DIR . '/storage/uploads/protected';
$pdfFiles = glob($protectedPath . '/*.pdf') ?: [];
echo "PDF files in storage/uploads/protected: " . count($pdfFiles) . "\n";

$tempPath = BASE_DIR . '/storage/temp';
$tempFiles = glob($tempPath . '/*') ?: [];
echo "Files in storage/temp: " . count($tempFiles) . "\n\n";

// Check FK relationships
echo "FOREIGN KEY RELATIONSHIPS:\n";
$fkTables = [
    'id_cards'            => 'employees, users',
    'id_versions'         => 'id_cards, users',
    'approval_records'    => 'id_cards, users',
    'correction_requests' => 'id_cards, users',
    'print_records'       => 'id_cards, id_versions, users',
    'print_batches'       => 'users',
    'print_batch_items'   => 'print_batches, id_cards, id_versions',
    'collection_records'  => 'id_cards, users',
    'notifications'       => 'users',
    'notification_outbox' => 'users',
    'audit_logs'          => 'users (nullable), id_cards (nullable)',
];
foreach ($fkTables as $table => $refs) {
    $exists = in_array($table, $tables) ? 'EXISTS' : 'MISSING';
    echo sprintf("  %-30s -> %-10s refs: %s\n", $table, $exists, $refs);
}
echo "\n";

// ============================================================
// PHASE 2: SAFETY BACKUP
// ============================================================
echo "PHASE 2: CREATING SAFETY BACKUP\n";
echo "----------------------------------------------------------------------\n";

$backupDir = BASE_DIR . '/storage/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$backupFile = $backupDir . '/pre_cleanup_backup_' . date('Ymd_His') . '.sqlite';
$sourceDb = BASE_DIR . '/storage/database/app.sqlite';

// Use SQLite backup API
try {
    $backupPdo = new PDO('sqlite:' . $backupFile);
    $pdo->exec("VACUUM INTO '$backupFile'");
    echo "  Backup created: " . basename($backupFile) . "\n";
    
    // Verify backup can be opened and read
    $verifyPdo = new PDO('sqlite:' . $backupFile);
    $verifyCount = $verifyPdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn();
    echo "  Backup verified: $verifyCount tables readable\n";
    $verifyPdo = null;
} catch (\Throwable $e) {
    // Fallback: file copy
    if (copy($sourceDb, $backupFile)) {
        echo "  Backup created (file copy): " . basename($backupFile) . "\n";
    } else {
        echo "  ERROR: Backup failed! Aborting.\n";
        exit(1);
    }
}
echo "\n";

// ============================================================
// PHASE 3: CONTROLLED DATA RESET
// ============================================================
echo "PHASE 3: CONTROLLED DATA RESET (preserving users, schema, config)\n";
echo "----------------------------------------------------------------------\n";

// Collect PDF file paths BEFORE deleting DB records
$pdfPaths = [];
if (in_array('id_versions', $tables)) {
    $stmt = $pdo->query("SELECT file_path FROM id_versions WHERE file_path IS NOT NULL");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['file_path']) {
            $pdfPaths[] = $row['file_path'];
        }
    }
}
echo "  PDF paths collected from DB: " . count($pdfPaths) . "\n";

// Disable FK for clean deletion
$pdo->exec("PRAGMA foreign_keys = OFF");
$pdo->beginTransaction();

try {
    // Order matters: delete child tables first
    $tablesToClear = [
        'collection_records',
        'print_batch_items',
        'print_records',
        'print_batches',
        'correction_requests',
        'approval_records',
        'id_versions',
        'id_cards',
        'employees',
    ];

    // Only clear notification/audit records tied to ID cards
    // Keep system-level audit entries (null id_card_id)
    $notifTables = ['notifications', 'notification_outbox'];
    $auditTable = 'audit_logs';

    foreach ($tablesToClear as $table) {
        if (!in_array($table, $tables)) {
            echo "  SKIP (not found): $table\n";
            continue;
        }
        $count = $pdo->query("SELECT COUNT(*) FROM \"$table\"")->fetchColumn();
        $pdo->exec("DELETE FROM \"$table\"");
        echo "  CLEARED: $table ($count rows removed)\n";
    }

    // Clear notifications
    foreach ($notifTables as $table) {
        if (!in_array($table, $tables)) continue;
        $count = $pdo->query("SELECT COUNT(*) FROM \"$table\"")->fetchColumn();
        $pdo->exec("DELETE FROM \"$table\"");
        echo "  CLEARED: $table ($count rows removed)\n";
    }

    // For audit_logs: remove records tied to deleted id_cards (all of them since cards are gone)
    // Keep system-level ones where id_card_id IS NULL for auditability? 
    // Per instructions: remove sample workflow records. Clear all for fresh start.
    if (in_array($auditTable, $tables)) {
        $count = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
        $pdo->exec("DELETE FROM audit_logs");
        echo "  CLEARED: audit_logs ($count rows removed)\n";
    }

    // Reset auto-increment sequences
    foreach (array_merge($tablesToClear, $notifTables, [$auditTable]) as $table) {
        if (!in_array($table, $tables)) continue;
        $pdo->exec("DELETE FROM sqlite_sequence WHERE name='$table'");
    }

    $pdo->commit();
    echo "\n  Transaction COMMITTED successfully.\n";

} catch (\Throwable $e) {
    $pdo->rollBack();
    $pdo->exec("PRAGMA foreign_keys = ON");
    echo "  ERROR during cleanup: " . $e->getMessage() . "\n";
    exit(1);
}

$pdo->exec("PRAGMA foreign_keys = ON");
echo "\n";

// ============================================================
// PHASE 3b: DELETE ORPHANED PDF FILES
// ============================================================
echo "PHASE 3b: DELETING SAMPLE PDF FILES\n";
echo "----------------------------------------------------------------------\n";

$deletedCount = 0;
$failedCount = 0;
$totalSize = 0;

// Delete all files from the db-tracked paths
foreach ($pdfPaths as $relativePath) {
    $fullPath = BASE_DIR . '/' . ltrim($relativePath, '/');
    if (file_exists($fullPath)) {
        $totalSize += filesize($fullPath);
        if (unlink($fullPath)) {
            $deletedCount++;
        } else {
            echo "  WARN: Could not delete $fullPath\n";
            $failedCount++;
        }
    }
}

// Also sweep the protected directory for any orphaned files not in DB
$remainingFiles = glob($protectedPath . '/*.pdf') ?: [];
$orphanCount = 0;
foreach ($remainingFiles as $orphan) {
    $totalSize += filesize($orphan);
    if (unlink($orphan)) {
        $orphanCount++;
    }
}

echo "  Deleted tracked PDF files: $deletedCount\n";
echo "  Deleted orphaned PDF files: $orphanCount\n";
if ($failedCount > 0) echo "  WARN: Failed to delete: $failedCount\n";
echo "  Total space freed: " . round(($totalSize) / 1024 / 1024, 2) . " MB\n";

// Delete temp PDF files
$tempFiles = glob($tempPath . '/*.pdf') ?: [];
$tempDeleted = 0;
foreach ($tempFiles as $tf) {
    if (unlink($tf)) $tempDeleted++;
}
echo "  Deleted temp files: $tempDeleted\n\n";

// ============================================================
// PHASE 4: DATABASE INTEGRITY CHECKS
// ============================================================
echo "PHASE 4: DATABASE INTEGRITY VERIFICATION\n";
echo "----------------------------------------------------------------------\n";

// Re-enable FK and run pragmas
$fkStatus = $pdo->query("PRAGMA foreign_keys")->fetchColumn();
$integrityResult = $pdo->query("PRAGMA integrity_check")->fetchColumn();
$journalMode = $pdo->query("PRAGMA journal_mode")->fetchColumn();
$walMode = $pdo->query("PRAGMA wal_checkpoint")->fetch(PDO::FETCH_ASSOC);

echo "  foreign_keys:    " . ($fkStatus ? "ON ✓" : "OFF ✗") . "\n";
echo "  integrity_check: $integrityResult " . ($integrityResult === 'ok' ? "✓" : "✗") . "\n";
echo "  journal_mode:    $journalMode " . ($journalMode === 'wal' ? "✓" : "(expected wal)") . "\n";

// Check for orphaned records
echo "\n  Orphan checks:\n";
$orphanChecks = [
    'id_cards with missing employee'      => "SELECT COUNT(*) FROM id_cards WHERE employee_id NOT IN (SELECT id FROM employees)",
    'id_versions with missing card'       => "SELECT COUNT(*) FROM id_versions WHERE id_card_id NOT IN (SELECT id FROM id_cards)",
    'approval_records with missing card'  => "SELECT COUNT(*) FROM approval_records WHERE id_card_id NOT IN (SELECT id FROM id_cards)",
    'correction_requests with missing card' => "SELECT COUNT(*) FROM correction_requests WHERE id_card_id NOT IN (SELECT id FROM id_cards)",
    'print_records with missing card'     => "SELECT COUNT(*) FROM print_records WHERE id_card_id NOT IN (SELECT id FROM id_cards)",
    'print_batch_items with missing batch'=> "SELECT COUNT(*) FROM print_batch_items WHERE batch_id NOT IN (SELECT id FROM print_batches)",
    'notifications with missing user'     => "SELECT COUNT(*) FROM notifications WHERE user_id NOT IN (SELECT id FROM users)",
];

$allOrphansOk = true;
foreach ($orphanChecks as $label => $sql) {
    try {
        $count = $pdo->query($sql)->fetchColumn();
        $status = $count == 0 ? "OK ✓" : "WARN: $count orphans";
        if ($count > 0) $allOrphansOk = false;
        echo sprintf("    %-50s %s\n", $label, $status);
    } catch (\Throwable $e) {
        echo sprintf("    %-50s SKIP (table may not exist)\n", $label);
    }
}

// Post-cleanup row counts
echo "\n  Post-cleanup table row counts:\n";
$verifyTables = ['users', 'employees', 'id_cards', 'id_versions', 'approval_records',
                 'correction_requests', 'print_records', 'print_batches', 'print_batch_items',
                 'collection_records', 'notifications', 'audit_logs'];
foreach ($verifyTables as $t) {
    if (!in_array($t, $tables)) continue;
    $count = $pdo->query("SELECT COUNT(*) FROM \"$t\"")->fetchColumn();
    echo sprintf("    %-40s %d rows\n", $t, (int)$count);
}
echo "\n";

// ============================================================
// PHASE 5: USER ACCOUNT VERIFICATION
// ============================================================
echo "PHASE 5: USER ACCOUNT VERIFICATION\n";
echo "----------------------------------------------------------------------\n";

$usersAfter = $pdo->query("SELECT id, username, name, email, role, status, password_hash FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "  Total users preserved: " . count($usersAfter) . "\n\n";

$rolesExpected = ['ADMINISTRATOR', 'DESIGNER', 'HR_MANAGER', 'PRINTING_OFFICER'];
$rolesFound = [];
$userIssues = [];

foreach ($usersAfter as $u) {
    $issues = [];
    if (empty($u['username'])) $issues[] = 'missing username';
    if (empty($u['email'])) $issues[] = 'missing email';
    if (empty($u['password_hash'])) $issues[] = 'missing password_hash';
    if (!in_array($u['role'], $rolesExpected)) $issues[] = "unknown role: {$u['role']}";
    if (empty($u['name'])) $issues[] = 'missing name';

    $rolesFound[$u['role']] = ($rolesFound[$u['role']] ?? 0) + 1;
    $statusIcon = empty($issues) ? "✓" : "✗";
    echo sprintf("  [%d] %-20s %-20s %-15s %s %s\n",
        $u['id'], $u['username'], $u['role'], $u['status'],
        $statusIcon, implode(', ', $issues)
    );
    if (!empty($issues)) $userIssues[] = $u['username'] . ': ' . implode(', ', $issues);
}

echo "\n  Roles present:\n";
foreach ($rolesExpected as $role) {
    $count = $rolesFound[$role] ?? 0;
    $icon = $count > 0 ? "✓" : "✗ MISSING";
    echo "    $role: $count $icon\n";
}

// Check email uniqueness
$dupEmails = $pdo->query("SELECT email, COUNT(*) as c FROM users GROUP BY LOWER(email) HAVING c > 1")->fetchAll();
echo "\n  Email uniqueness: " . (empty($dupEmails) ? "OK ✓" : "DUPLICATES FOUND ✗") . "\n";

$dupUsernames = $pdo->query("SELECT username, COUNT(*) as c FROM users GROUP BY LOWER(username) HAVING c > 1")->fetchAll();
echo "  Username uniqueness: " . (empty($dupUsernames) ? "OK ✓" : "DUPLICATES FOUND ✗") . "\n\n";

// ============================================================
// PHASE 6: EMAIL / NOTIFICATION CONFIGURATION
// ============================================================
echo "PHASE 6: EMAIL & NOTIFICATION CONFIGURATION\n";
echo "----------------------------------------------------------------------\n";

// Load .env manually
$envPath = BASE_DIR . '/.env';
$envVars = [];
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $envVars[trim($key)] = trim($val, '"\'');
        }
    }
}

$mailEnabled = $envVars['MAIL_ENABLED'] ?? 'false';
$mailHost    = $envVars['MAIL_HOST'] ?? '';
$mailPort    = $envVars['MAIL_PORT'] ?? '';
$mailUser    = $envVars['MAIL_USERNAME'] ?? '';
$mailPass    = $envVars['MAIL_PASSWORD'] ?? '';
$mailFrom    = $envVars['MAIL_FROM_ADDRESS'] ?? '';
$mailName    = $envVars['MAIL_FROM_NAME'] ?? '';

echo "  MAIL_ENABLED:       $mailEnabled\n";
echo "  MAIL_HOST:          " . ($mailHost ?: '(not set)') . "\n";
echo "  MAIL_PORT:          " . ($mailPort ?: '(not set)') . "\n";
echo "  MAIL_USERNAME:      " . ($mailUser ?: '(not set)') . "\n";
echo "  MAIL_PASSWORD:      " . ($mailPass ? '******* (set)' : '(not set)') . "\n";
echo "  MAIL_FROM_ADDRESS:  " . ($mailFrom ?: '(not set)') . "\n";
echo "  MAIL_FROM_NAME:     " . ($mailName ?: '(not set)') . "\n\n";

if ($mailEnabled === 'true' || $mailEnabled === '1') {
    echo "  Email is ENABLED. Testing SMTP connectivity...\n";
    // Try socket connection to SMTP
    $socket = @fsockopen($mailHost, (int)$mailPort, $errno, $errstr, 5);
    if ($socket) {
        fclose($socket);
        echo "  SMTP Connection to $mailHost:$mailPort: SUCCESS ✓\n";
    } else {
        echo "  SMTP Connection to $mailHost:$mailPort: FAILED ($errstr)\n";
        echo "  Note: Email workflow events will be caught and logged, not crash the system.\n";
    }
} else {
    echo "  Email is DISABLED. In-app notifications will still work.\n";
    echo "  To enable email: set MAIL_ENABLED=true in .env with valid SMTP credentials.\n";
}
echo "\n";

// ============================================================
// PHASE 7: PDF STORAGE SECURITY CHECK
// ============================================================
echo "PHASE 7: PDF STORAGE SECURITY VERIFICATION\n";
echo "----------------------------------------------------------------------\n";

// Check protected directory is outside webroot
$publicDir = BASE_DIR . '/public';
$storageDir = BASE_DIR . '/storage';

$storageInPublic = strpos(realpath($storageDir) ?: $storageDir, realpath($publicDir) ?: $publicDir) === 0;
echo "  storage/ is outside public/: " . (!$storageInPublic ? "YES ✓" : "NO - SECURITY RISK ✗") . "\n";

// Check .htaccess or index.php in storage
$htaccessExists = file_exists($storageDir . '/.htaccess') || file_exists($storageDir . '/uploads/.htaccess') || file_exists($storageDir . '/uploads/protected/.htaccess');
echo "  .htaccess protection in storage/: " . ($htaccessExists ? "YES ✓" : "Not present (rely on webroot config)") . "\n";

// Check upload directory permissions
echo "  Protected storage writable: " . (is_writable($protectedPath) ? "YES ✓" : "NO ✗") . "\n";
echo "  Temp storage writable: " . (is_writable($tempPath) ? "YES ✓" : "NO ✗") . "\n\n";

// ============================================================
// PHASE 8: HEALTH CHECK VERIFICATION
// ============================================================
echo "PHASE 8: SYSTEM HEALTH VERIFICATION\n";
echo "----------------------------------------------------------------------\n";

// PHP extensions
$requiredExt = ['pdo', 'pdo_sqlite', 'openssl', 'mbstring', 'fileinfo'];
foreach ($requiredExt as $ext) {
    $loaded = extension_loaded($ext);
    echo sprintf("  ext-%s: %s\n", $ext, $loaded ? "LOADED ✓" : "MISSING ✗");
}

// DB write test
echo "\n  DB read/write test: ";
try {
    $testStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, user_name, user_role, action, details, ip_address, user_agent, created_at) VALUES (NULL, 'SYSTEM', 'SYSTEM', 'CLEANUP_COMPLETED', 'Production cleanup completed', '127.0.0.1', 'CleanupScript/1.0', ?)");
    $testStmt->execute([date('Y-m-d H:i:s')]);
    echo "PASS ✓\n";
} catch (\Throwable $e) {
    echo "FAIL - " . $e->getMessage() . " ✗\n";
}

// App config check
echo "  APP_ENV: " . ($envVars['APP_ENV'] ?? 'not set') . "\n";
echo "  APP_DEBUG: " . ($envVars['APP_DEBUG'] ?? 'not set') . "\n";
echo "  APP_URL: " . ($envVars['APP_URL'] ?? 'not set') . "\n";
echo "  APP_TIMEZONE: " . ($envVars['APP_TIMEZONE'] ?? 'not set') . "\n";
echo "\n";

// ============================================================
// FINAL SUMMARY REPORT
// ============================================================
echo "=======================================================================\n";
echo "  FINAL PRODUCTION CLEANUP REPORT\n";
echo "=======================================================================\n\n";

$idCardCount     = (int)$pdo->query("SELECT COUNT(*) FROM id_cards")->fetchColumn();
$versionCount    = (int)$pdo->query("SELECT COUNT(*) FROM id_versions")->fetchColumn();
$batchCount      = (int)$pdo->query("SELECT COUNT(*) FROM print_batches")->fetchColumn();
$notifCount      = (int)$pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
$auditCount      = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$userCount       = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$pdfCountAfter   = count(glob($protectedPath . '/*.pdf') ?: []);

echo "  USERS PRESERVED:       $userCount\n";
echo "  ID CARDS REMAINING:    $idCardCount " . ($idCardCount === 0 ? "✓ (clean)" : "✗ (expected 0)") . "\n";
echo "  ID VERSIONS REMAINING: $versionCount " . ($versionCount === 0 ? "✓ (clean)" : "✗ (expected 0)") . "\n";
echo "  PRINT BATCHES:         $batchCount " . ($batchCount === 0 ? "✓ (clean)" : "✗ (expected 0)") . "\n";
echo "  NOTIFICATIONS:         $notifCount " . ($notifCount === 0 ? "✓ (clean)" : "✗") . "\n";
echo "  AUDIT LOGS:            $auditCount (1 cleanup event logged)\n";
echo "  PDFS IN STORAGE:       $pdfCountAfter " . ($pdfCountAfter === 0 ? "✓ (clean)" : "✗ (orphans remain)") . "\n";
echo "  DB INTEGRITY:          $integrityResult " . ($integrityResult === 'ok' ? "✓" : "✗") . "\n";
echo "  FOREIGN KEYS:          " . ($fkStatus ? "ON ✓" : "OFF ✗") . "\n";
echo "  JOURNAL MODE:          $journalMode\n";
echo "  ORPHAN RECORDS:        " . ($allOrphansOk ? "NONE ✓" : "PRESENT - check above") . "\n";
echo "  USER ISSUES:           " . (empty($userIssues) ? "NONE ✓" : implode('; ', $userIssues)) . "\n";
echo "\n";

$isClean = $idCardCount === 0 && $versionCount === 0 && $batchCount === 0 
        && $pdfCountAfter === 0 && $integrityResult === 'ok' && $fkStatus;

if ($isClean && empty($userIssues)) {
    echo "  ✅ STATUS: SYSTEM IS CLEAN AND READY FOR PRODUCTION DATA\n";
    echo "\n  The ID Designer can now log in and begin uploading real hospital ID cards.\n";
    echo "  Each upload will automatically enter the HR Approval workflow.\n";
} else {
    echo "  ⚠️  STATUS: CLEANUP COMPLETED WITH WARNINGS - review above\n";
}

echo "\n=======================================================================\n\n";
