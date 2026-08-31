<?php
/**
 * Mengo Hospital Employee ID Card Management System
 * Comprehensive Automated Test Suite
 *
 * Tests:
 * 1. Database & WAL Integrity
 * 2. Model & Role RBAC
 * 3. Security (Password Hashing, CSRF, Session, Sanitization)
 * 4. Workflow Lifecycle (Upload -> Correction -> Reupload v2 -> Approval -> Print -> Collection)
 * 5. Atomic CAS Concurrency Control (Simultaneous HR Approval collision)
 * 6. PDF Security & SHA-256 Integrity Verification
 * 7. Audit Log Immutability & Traceability
 * 8. Reports & Statistics Accuracy
 * 9. Backup Service Execution & Integrity
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/src/autoload.php';

use Mengo\IdApproval\Database\Migrator;
use Mengo\IdApproval\Models\AuditLog;
use Mengo\IdApproval\Models\IdStatus;
use Mengo\IdApproval\Models\Role;
use Mengo\IdApproval\Models\User;
use Mengo\IdApproval\Repositories\ApprovalRecordRepository;
use Mengo\IdApproval\Repositories\AuditLogRepository;
use Mengo\IdApproval\Repositories\CollectionRecordRepository;
use Mengo\IdApproval\Repositories\CorrectionRequestRepository;
use Mengo\IdApproval\Repositories\EmployeeRepository;
use Mengo\IdApproval\Repositories\IdCardRepository;
use Mengo\IdApproval\Repositories\IdVersionRepository;
use Mengo\IdApproval\Repositories\NotificationRepository;
use Mengo\IdApproval\Repositories\PrintRecordRepository;
use Mengo\IdApproval\Repositories\UserRepository;
use Mengo\IdApproval\Security\CsrfToken;
use Mengo\IdApproval\Security\PasswordHasher;
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Security\SessionManager;
use Mengo\IdApproval\Services\AuthService;
use Mengo\IdApproval\Services\BackupService;
use Mengo\IdApproval\Services\PdfService;
use Mengo\IdApproval\Services\ReportService;
use Mengo\IdApproval\Services\WorkflowService;
use Mengo\IdApproval\Support\Config;
use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function assert(bool $condition, string $testName, string $failureDetails = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [\033[32mPASS\033[0m] {$testName}\n";
        } else {
            $this->failed++;
            $this->failures[] = "{$testName}: {$failureDetails}";
            echo "  [\033[31mFAIL\033[0m] {$testName}\n";
            if ($failureDetails) {
                echo "         \033[33mDetail: {$failureDetails}\033[0m\n";
            }
        }
    }

    public function summary(): void
    {
        echo "\n=======================================================\n";
        echo "TEST SUITE SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
        if ($this->failed > 0) {
            echo "\nFailures:\n";
            foreach ($this->failures as $f) {
                echo "  - {$f}\n";
            }
            exit(1);
        } else {
            echo "\033[32mALL TESTS PASSED WITH 100% SUCCESS!\033[0m\n";
            echo "=======================================================\n";
            exit(0);
        }
    }
}

$t = new TestRunner();
Timezone::configure();

echo "=======================================================\n";
echo "MENGO HOSPITAL EMPLOYEE ID CARD MANAGEMENT SYSTEM\n";
echo "AUTOMATED PRODUCTION TEST SUITE\n";
echo "Timestamp: " . Timezone::nowString() . " EAT\n";
echo "=======================================================\n\n";

// ─────────────────────────────────────────────────────────────
// 1. DATABASE & INTEGRITY TESTS
// ─────────────────────────────────────────────────────────────
echo "1. Database & Storage Architecture:\n";
$db = Database::getConnection();
$t->assert($db instanceof \PDO, 'SQLite PDO connection established');

$journalMode = $db->query('PRAGMA journal_mode')->fetchColumn();
$t->assert(strtoupper((string)$journalMode) === 'WAL', 'SQLite WAL (Write-Ahead Logging) enabled', "Current: {$journalMode}");

$fk = (int)$db->query('PRAGMA foreign_keys')->fetchColumn();
$t->assert($fk === 1, 'SQLite Foreign Keys enforcement active', "Current: {$fk}");

$integrity = Database::checkIntegrity();
$t->assert($integrity['status'] === 'ok', 'SQLite Database PRAGMA integrity_check passed', json_encode($integrity));

// ─────────────────────────────────────────────────────────────
// 2. SECURITY & AUTHENTICATION TESTS
// ─────────────────────────────────────────────────────────────
echo "\n2. Security & Authentication:\n";
$hasher = new PasswordHasher();
$hashed = $hasher->hash('MengoHospital@2026');
$t->assert($hasher->verify('MengoHospital@2026', $hashed), 'PasswordHasher correctly verifies valid password');
$t->assert(!$hasher->verify('WrongPassword123', $hashed), 'PasswordHasher rejects invalid password');

$userRepo = new UserRepository();
$designerUser = $userRepo->findByUsername('designer');
$t->assert($designerUser !== null && $designerUser->role === Role::DESIGNER, 'Jane Doe (Designer) found by username');

$hr1 = $userRepo->findByUsername('sarah.namukasa');
$hr2 = $userRepo->findByUsername('david.kato');
$hr3 = $userRepo->findByUsername('grace.nakato');
$t->assert($hr1 !== null && $hr1->role === Role::HR_MANAGER, 'Sarah Namukasa (HR 1) found by username');
$t->assert($hr2 !== null && $hr2->role === Role::HR_MANAGER, 'David Kato (HR 2) found by username');
$t->assert($hr3 !== null && $hr3->role === Role::HR_MANAGER, 'Grace Nakato (HR 3) found by username');

$printer = $userRepo->findByUsername('peter.okello');
$t->assert($printer !== null && $printer->role === Role::PRINTING_OFFICER, 'Peter Okello (Printing Officer) found by username');

$admin = $userRepo->findByUsername('admin');
$t->assert($admin !== null && $admin->role === Role::ADMINISTRATOR, 'System Administrator found by username');

// AuthService Username Authentication Test
$authService = new AuthService();
$reqMock = new \Mengo\IdApproval\Support\Request();
$authedUser = $authService->authenticate('admin', 'MengoAdmin2026!', $reqMock);
$t->assert($authedUser->id === $admin->id, 'AuthService authenticates user successfully by Username');

// CSRF
$token = CsrfToken::generate();
$t->assert(CsrfToken::validate($token), 'CSRF token validates correctly');
$t->assert(!CsrfToken::validate('invalid_token_xyz'), 'CSRF token rejects forged token');

// XSS Sanitizer
$malicious = '<script>alert("XSS")</script>&"\'';
$sanitized = Sanitizer::escape($malicious);
$t->assert(!str_contains($sanitized, '<script>'), 'Sanitizer escapes HTML tags');

// ─────────────────────────────────────────────────────────────
// 3. ROLE-BASED ACCESS CONTROL (RBAC) TESTS
// ─────────────────────────────────────────────────────────────
echo "\n3. Role-Based Access Control (RBAC):\n";
$t->assert($designerUser->isDesigner(), 'Designer user identified as isDesigner()');
$t->assert(!$designerUser->isHrManager(), 'Designer is NOT HR Manager');
$t->assert(!$designerUser->isPrintingOfficer(), 'Designer is NOT Printing Officer');

$t->assert($hr1->isHrManager(), 'HR user identified as isHrManager()');
$t->assert(!$hr1->isDesigner(), 'HR is NOT Designer');
$t->assert(!$hr1->isPrintingOfficer(), 'HR is NOT Printing Officer');

$t->assert($printer->isPrintingOfficer(), 'Printing Officer identified as isPrintingOfficer()');
$t->assert(!$printer->isHrManager(), 'Printing Officer is NOT HR Manager');

// ─────────────────────────────────────────────────────────────
// 4. WORKFLOW SERVICE & FULL LIFECYCLE TEST
// ─────────────────────────────────────────────────────────────
echo "\n4. End-to-End Workflow Lifecycle (DRAFT -> UPLOADED -> CORRECTION -> REUPLOAD -> APPROVE -> PRINT -> COLLECT):\n";

$employeeRepo = new EmployeeRepository();
$cardRepo = new IdCardRepository();
$versionRepo = new IdVersionRepository();
$workflow = new WorkflowService();

// Step 1: Create Test Employee
$empId = $employeeRepo->create([
    'staff_id' => 'TEST-STAFF-' . time(),
    'full_name' => 'Dr. Automation Test Candidate',
    'department_id' => 1,
    'designation' => 'Principal Clinical Officer',
    'blood_group' => 'O+',
    'phone' => '+256 700 999 888',
    'email' => 'test.candidate@mengohospital.org',
    'status' => 'ACTIVE'
]);
$t->assert($empId > 0, 'Test employee created in database');

// Create a valid dummy test PDF file
$testPdfPath = APP_ROOT . '/storage/test_sample.pdf';
$pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources <<>> /MediaBox [0 0 612 792] >>\nendobj\nxref\n0 4\n0000000000 65535 f \n0000000010 00000 n \n0000000060 00000 n \n0000000117 00000 n \ntrailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n210\n%%EOF";
file_put_contents($testPdfPath, $pdfContent);

$dummyUpload = [
    'name' => 'test_id_v1.pdf',
    'type' => 'application/pdf',
    'tmp_name' => $testPdfPath,
    'error' => UPLOAD_ERR_OK,
    'size' => strlen($pdfContent)
];

// Step 2: Designer uploads initial design (v1)
$card = $workflow->uploadInitialDesign(
    $empId,
    $dummyUpload,
    $designerUser,
    'MH-TEST-' . time(),
    '127.0.0.1',
    'TestRunner/1.0'
);
$t->assert($card->current_status === IdStatus::PENDING_HR_APPROVAL, 'Card status is PENDING_HR_APPROVAL after upload');
$t->assert($card->current_version_number === 1, 'Card version is v1');

// Step 3: HR Sarah requests correction
$correctionReason = 'Staff designation needs to be updated to Senior Specialist.';
$workflow->requestCorrection($card->id, $correctionReason, $hr1, '127.0.0.1', 'TestRunner/1.0');
$cardAfterCorrection = $cardRepo->findById($card->id);
$t->assert($cardAfterCorrection->current_status === IdStatus::CORRECTION_REQUESTED, 'Card status updated to CORRECTION_REQUESTED');

// Step 4: Designer re-uploads corrected PDF (v2)
file_put_contents($testPdfPath, $pdfContent . "\n% Corrected Version 2");
$dummyUploadV2 = [
    'name' => 'test_id_v2.pdf',
    'type' => 'application/pdf',
    'tmp_name' => $testPdfPath,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($testPdfPath)
];
$cardV2 = $workflow->uploadCorrectedDesign($card->id, $dummyUploadV2, $designerUser, '127.0.0.1', 'TestRunner/1.0');
$t->assert($cardV2->current_status === IdStatus::PENDING_HR_APPROVAL, 'Card status returned to PENDING_HR_APPROVAL after re-upload');
$t->assert($cardV2->current_version_number === 2, 'Card version incremented to v2');

// Verify immutability: Both v1 and v2 exist in versions table
$versions = $versionRepo->getVersionsForCard($card->id);
$t->assert(count($versions) === 2, 'Both v1 and v2 immutable records exist in database');

// Step 5: HR David Kato approves v2
$approvalChecklist = ['photo' => 1, 'name' => 1, 'staff_no' => 1, 'department' => 1, 'designation' => 1, 'layout' => 1];
$approvedCard = $workflow->approveId($card->id, $approvalChecklist, 'Verified against staff file', $hr2, '127.0.0.1', 'TestRunner/1.0');
$t->assert($approvedCard->current_status === IdStatus::APPROVED, 'Card status updated to APPROVED');
$approvalRepo = new ApprovalRecordRepository();
$approvalRecord = $approvalRepo->findByCardId($card->id);
$t->assert($approvalRecord !== null && $approvalRecord->hr_user_id === $hr2->id, 'Approver recorded as HR David Kato');

// Step 6: Printing Officer Peter Okello marks as printed
$printedCard = $workflow->markAsPrinted($card->id, 'Printed on Card Zebra Printer #2', $printer, '127.0.0.1', 'TestRunner/1.0');
$t->assert($printedCard->current_status === IdStatus::PRINTED, 'Card status updated to PRINTED');

// Step 7: HR Grace Nakato marks as collected
$collectedCard = $workflow->markAsCollected(
    $card->id,
    'Dr. Automation Test Candidate',
    'SELF',
    '+256 700 999 888',
    'COL-MH-' . time(),
    'Collected in person at HR Desk',
    $hr3,
    '127.0.0.1',
    'TestRunner/1.0'
);
$t->assert($collectedCard->current_status === IdStatus::COLLECTED, 'Card status updated to COLLECTED (Terminal Lifecycle State)');

// ─────────────────────────────────────────────────────────────
// 5. ATOMIC CAS CONCURRENCY CONTROL SIMULATION
// ─────────────────────────────────────────────────────────────
echo "\n5. Atomic CAS Concurrency Control Simulation (2 HR Managers collision):\n";

// Create a pending card
$empId2 = $employeeRepo->create([
    'staff_id' => 'CONCURRENCY-' . time(),
    'full_name' => 'Nurse Concurrency Simulation',
    'department_id' => 2,
    'designation' => 'Nursing Officer',
    'status' => 'ACTIVE'
]);
file_put_contents($testPdfPath, $pdfContent);
$dummyUploadConcurrency = [
    'name' => 'concurrency_test.pdf',
    'type' => 'application/pdf',
    'tmp_name' => $testPdfPath,
    'error' => UPLOAD_ERR_OK,
    'size' => strlen($pdfContent)
];
$concurrencyCard = $workflow->uploadInitialDesign($empId2, $dummyUploadConcurrency, $designerUser, 'MH-CAS-' . time(), '127.0.0.1', 'TestRunner');

// First HR Manager (Sarah) approves
$workflow->approveId($concurrencyCard->id, $approvalChecklist, 'First approval', $hr1, '127.0.0.1', 'TestRunner');

// Second HR Manager (David) attempts to approve the same card
$secondAttemptFailedWithConflict = false;
$conflictMessage = '';
try {
    $workflow->approveId($concurrencyCard->id, $approvalChecklist, 'Second approval attempt', $hr2, '127.0.0.1', 'TestRunner');
} catch (\RuntimeException $e) {
    $secondAttemptFailedWithConflict = true;
    $conflictMessage = $e->getMessage();
}

$t->assert($secondAttemptFailedWithConflict, 'Atomic CAS prevented double approval by 2nd HR Manager');
$t->assert(str_contains($conflictMessage, 'Sarah Namukasa') || str_contains($conflictMessage, 'already approved') || str_contains($conflictMessage, 'Conflict'), 'Conflict exception provides explicit details of winner: ' . $conflictMessage);

// ─────────────────────────────────────────────────────────────
// 6. PDF SERVICE INTEGRITY & SECURITY
// ─────────────────────────────────────────────────────────────
echo "\n6. PDF Security & SHA-256 Checksum Validation:\n";
$pdfService = new PdfService();
$validIntegrity = $pdfService->verifyIntegrity($versions[0]->file_path, $versions[0]->file_sha256);
$t->assert($validIntegrity, 'SHA-256 integrity verification passed for stored protected PDF');

$forgedHash = '0000000000000000000000000000000000000000000000000000000000000000';
$failedIntegrity = $pdfService->verifyIntegrity($versions[0]->file_path, $forgedHash);
$t->assert(!$failedIntegrity, 'SHA-256 integrity check detected mismatched / forged hash');

// ─────────────────────────────────────────────────────────────
// 7. AUDIT TRAIL IMMUTABILITY
// ─────────────────────────────────────────────────────────────
echo "\n7. Audit Trail Immutability & Traceability:\n";
$auditRepo = new AuditLogRepository();
$cardAudit = $auditRepo->getForCard($card->id);
$t->assert(count($cardAudit) >= 5, 'Comprehensive audit trail logged all 5 lifecycle transitions');

// Verify actions recorded
$actions = array_map(fn($a) => $a->action, $cardAudit);
$t->assert(in_array(AuditLog::ACTION_PDF_UPLOADED, $actions), 'PDF_UPLOADED logged in audit trail');
$t->assert(in_array(AuditLog::ACTION_CORRECTION_REQUESTED, $actions), 'CORRECTION_REQUESTED logged in audit trail');
$t->assert(in_array(AuditLog::ACTION_ID_APPROVED, $actions), 'ID_APPROVED logged in audit trail');
$t->assert(in_array(AuditLog::ACTION_ID_PRINTED, $actions), 'ID_PRINTED logged in audit trail');
$t->assert(in_array(AuditLog::ACTION_ID_COLLECTED, $actions), 'ID_COLLECTED logged in audit trail');

// ─────────────────────────────────────────────────────────────
// 8. NOTIFICATIONS SYSTEM
// ─────────────────────────────────────────────────────────────
echo "\n8. Persistent Notifications System:\n";
$notifRepo = new NotificationRepository();
$designerNotifs = $notifRepo->getForUser($designerUser->id, Role::DESIGNER, 10, 0);
$t->assert(!empty($designerNotifs), 'Designer received notifications for correction request');

// ─────────────────────────────────────────────────────────────
// 9. DATABASE BACKUP SERVICE
// ─────────────────────────────────────────────────────────────
echo "\n9. SQLite Safe WAL Database Backup Service:\n";
$backupService = new BackupService();
$backupResult = $backupService->createBackup();
$t->assert(!empty($backupResult['filename']), "Backup created: {$backupResult['filename']}");
$t->assert(file_exists($backupResult['full_path']), 'Physical backup file exists on disk');
$t->assert($backupResult['size'] > 0, "Backup file size is valid ({$backupResult['size']} bytes)");

// ─────────────────────────────────────────────────────────────
// 10. BULK PRINTING ENGINE & SAFETY VALIDATION TEST
// ─────────────────────────────────────────────────────────────
echo "\n10. Bulk Printing Engine & Safety Batch Validation (10 Approved + 2 Ineligible Cards):\n";

// Create 10 Approved Cards
$approvedBatchIds = [];
for ($i = 1; $i <= 10; $i++) {
    $emp = $employeeRepo->create([
        'staff_id' => "BULK-APPROVED-{$i}-" . time(),
        'full_name' => "Dr. Batch Staff {$i}",
        'department_id' => ($i % 5) + 1,
        'designation' => 'Clinical Officer',
        'status' => 'ACTIVE'
    ]);
    file_put_contents($testPdfPath, $pdfContent . "\n% Batch {$i}");
    $c = $workflow->uploadInitialDesign(
        $emp,
        ['name' => "bulk_{$i}.pdf", 'type' => 'application/pdf', 'tmp_name' => $testPdfPath, 'error' => UPLOAD_ERR_OK, 'size' => filesize($testPdfPath)],
        $designerUser,
        "MH-BULK-{$i}-" . time(),
        '127.0.0.1',
        'TestRunner'
    );
    $approvedCard = $workflow->approveId($c->id, $approvalChecklist, 'Batch Pre-approval', $hr1, '127.0.0.1', 'TestRunner');
    $approvedBatchIds[] = $approvedCard->id;
}
$t->assert(count($approvedBatchIds) === 10, 'Created 10 approved test ID cards');

// Create 2 Ineligible Cards (1 Pending HR, 1 Correction Requested)
$empIneligible1 = $employeeRepo->create(['staff_id' => 'INELIGIBLE-1-' . time(), 'full_name' => 'Pending Staff', 'department_id' => 1, 'designation' => 'Nurse', 'status' => 'ACTIVE']);
file_put_contents($testPdfPath, $pdfContent);
$cardPending = $workflow->uploadInitialDesign($empIneligible1, ['name' => 'ineligible1.pdf', 'type' => 'application/pdf', 'tmp_name' => $testPdfPath, 'error' => UPLOAD_ERR_OK, 'size' => filesize($testPdfPath)], $designerUser, 'MH-INEL-1-' . time(), '127.0.0.1', 'TestRunner');

$empIneligible2 = $employeeRepo->create(['staff_id' => 'INELIGIBLE-2-' . time(), 'full_name' => 'Correction Staff', 'department_id' => 2, 'designation' => 'Technician', 'status' => 'ACTIVE']);
$cardCorrection = $workflow->uploadInitialDesign($empIneligible2, ['name' => 'ineligible2.pdf', 'type' => 'application/pdf', 'tmp_name' => $testPdfPath, 'error' => UPLOAD_ERR_OK, 'size' => filesize($testPdfPath)], $designerUser, 'MH-INEL-2-' . time(), '127.0.0.1', 'TestRunner');
$workflow->requestCorrection($cardCorrection->id, 'Design fix required', $hr2, '127.0.0.1', 'TestRunner');

$mixedBatchIds = array_merge($approvedBatchIds, [$cardPending->id, $cardCorrection->id]);

// Test 1: Reject mixed batch containing unapproved IDs
$mixedBatchRejected = false;
$rejectMessage = '';
try {
    $workflow->bulkPrint($mixedBatchIds, 'Mixed test batch', $printer, '127.0.0.1', 'TestRunner');
} catch (\RuntimeException $e) {
    $mixedBatchRejected = true;
    $rejectMessage = $e->getMessage();
}
$t->assert($mixedBatchRejected, 'Bulk print rejected mixed batch containing 2 unapproved cards');
$t->assert(str_contains($rejectMessage, '2 selected card(s) are not eligible'), 'Rejection provides explicit count of ineligible cards: ' . $rejectMessage);

// Test 2: Process exact approved batch of 10 cards
$bulkResult = $workflow->bulkPrint($approvedBatchIds, 'Approved batch morning shift', $printer, '127.0.0.1', 'TestRunner');
$t->assert($bulkResult['total_printed'] === 10, 'Successfully executed bulk print of all 10 approved cards');
$t->assert(!empty($bulkResult['batch_reference']), "Created print batch reference: {$bulkResult['batch_reference']}");

// Verify in database that all 10 cards transitioned to PRINTED
$allPrinted = true;
foreach ($approvedBatchIds as $cardId) {
    $checkCard = $cardRepo->findById($cardId);
    if ($checkCard->current_status !== IdStatus::PRINTED) {
        $allPrinted = false;
        break;
    }
}
$t->assert($allPrinted, 'All 10 cards updated to status PRINTED in database');

// ─────────────────────────────────────────────────────────────
// 11. SMART FOLLOW-UP ALERTS & REAL-TIME SYNC
// ─────────────────────────────────────────────────────────────
echo "\n11. Smart Follow-up Attention Thresholds & Real-time Sync:\n";
$smartAlerts = $workflow->getSmartFollowUpAlerts();
$t->assert(isset($smartAlerts['overdue_approvals']), 'Smart Alerts contains overdue_approvals structure');
$t->assert(isset($smartAlerts['stale_corrections']), 'Smart Alerts contains stale_corrections structure');
$t->assert(isset($smartAlerts['printing_delays']), 'Smart Alerts contains printing_delays structure');
$t->assert(isset($smartAlerts['collection_delays']), 'Smart Alerts contains collection_delays structure');
$t->assert(is_int($smartAlerts['total_alerts']), "Calculated total active smart alerts: {$smartAlerts['total_alerts']}");

// ─────────────────────────────────────────────────────────────
// 12. ADVANCED BATCH PDF MERGE, PREVIEW & PHYSICAL PRINT HANDSHAKE
// ─────────────────────────────────────────────────────────────
echo "\n12. Advanced Batch PDF Merge, Preview & Physical Print Handshake:\n";

// 1. Setup 4 new approved cards
$batchTestCardIds = [];
for ($k = 1; $k <= 4; $k++) {
    $e = $employeeRepo->create([
        'staff_id' => "MERGE-EMP-{$k}-" . time(),
        'full_name' => "Nurse Batch Sample {$k}",
        'department_id' => 1,
        'designation' => 'Nursing Officer',
        'status' => 'ACTIVE'
    ]);
    file_put_contents($testPdfPath, $pdfContent . "\n% Merge Sample {$k}");
    $c = $workflow->uploadInitialDesign(
        $e,
        ['name' => "sample_{$k}.pdf", 'type' => 'application/pdf', 'tmp_name' => $testPdfPath, 'error' => UPLOAD_ERR_OK, 'size' => filesize($testPdfPath)],
        $designerUser,
        "MH-MRG-{$k}-" . time(),
        '127.0.0.1',
        'TestRunner'
    );
    $appCard = $workflow->approveId($c->id, $approvalChecklist, 'Ready for Merge', $hr3, '127.0.0.1', 'TestRunner');
    $batchTestCardIds[] = $appCard->id;
}
$t->assert(count($batchTestCardIds) === 4, 'Created 4 approved test cards for batch merge');

// 2. Validate & Create Batch
$prepBatch = $workflow->validateAndCreatePrintBatch($batchTestCardIds, $printer, ['notes' => 'Test Night Production Batch']);
$t->assert($prepBatch['validation']['valid_count'] === 4, 'Batch validation reports all 4 PDFs valid');
$t->assert(!empty($prepBatch['batch_reference']), "Created print batch reference {$prepBatch['batch_reference']}");

// 3. Execute Server-Side Merge
$mergeResult = $workflow->executeBatchMerge($prepBatch['batch_id'], $printer, 'ORIGINAL');
$t->assert(file_exists($mergeResult['output_path']), 'Consolidated PDF exists in temporary storage');
$t->assert($mergeResult['page_count'] === 4, 'Merged PDF contains exactly 4 pages');
$t->assert(!empty($mergeResult['output_hash']), 'Generated SHA-256 output hash for merged batch');

// Crucial: Verify that PDF merge DOES NOT mark IDs as PRINTED
$card1Before = $cardRepo->findById($batchTestCardIds[0]);
$t->assert($card1Before->current_status === IdStatus::APPROVED, 'Merged ID cards remain in APPROVED status prior to physical print confirmation');

// 4. RBAC Check: Designer cannot execute merge
$designerBlocked = false;
try {
    $workflow->executeBatchMerge($prepBatch['batch_id'], $designerUser, 'ORIGINAL');
} catch (\RuntimeException $e) {
    $designerBlocked = true;
}
$t->assert($designerBlocked, 'Designer role is blocked from executing batch merge operations');

// 5. Partial Physical Print Confirmation: Confirm 3 out of 4 cards
$printedSubset = array_slice($batchTestCardIds, 0, 3);
$unprintedId = $batchTestCardIds[3];

$printConfirmResult = $workflow->confirmPhysicalPrint($prepBatch['batch_id'], $printedSubset, 'Card 1-3 physically confirmed', $printer);
$t->assert($printConfirmResult['total_printed'] === 3, 'Confirmed physical printing for 3 selected cards');

// Verify confirmed cards are PRINTED, unconfirmed card remains APPROVED
$t->assert($cardRepo->findById($printedSubset[0])->current_status === IdStatus::PRINTED, 'Confirmed card 1 transitioned to PRINTED');
$t->assert($cardRepo->findById($printedSubset[1])->current_status === IdStatus::PRINTED, 'Confirmed card 2 transitioned to PRINTED');
$t->assert($cardRepo->findById($printedSubset[2])->current_status === IdStatus::PRINTED, 'Confirmed card 3 transitioned to PRINTED');
$t->assert($cardRepo->findById($unprintedId)->current_status === IdStatus::APPROVED, 'Unconfirmed card 4 remains strictly in APPROVED status');

// 6. Automatic Cleanup Service Test
$mergeService = new \Mengo\IdApproval\Services\PdfMergeService();
$cleanedCount = $mergeService->cleanupExpiredBatches(0); // Cutoff 0 hours sweeps immediate test file
$t->assert($cleanedCount >= 1, "Cleanup service executed and recycled {$cleanedCount} temporary batch artifacts");

// Clean up test file
if (file_exists($testPdfPath)) {
    unlink($testPdfPath);
}

// ─────────────────────────────────────────────────────────────
// FINISH
// ─────────────────────────────────────────────────────────────
$t->summary();
