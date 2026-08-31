<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Services;

use Mengo\IdApproval\Models\AuditLog;
use Mengo\IdApproval\Models\IdStatus;
use Mengo\IdApproval\Models\Role;
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
use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;
use PDO;

class IdIngestionService
{
    private EmployeeRepository $employeeRepo;
    private IdCardRepository $cardRepo;
    private IdVersionRepository $versionRepo;
    private ApprovalRecordRepository $approvalRepo;
    private PrintRecordRepository $printRepo;
    private CollectionRecordRepository $collectionRepo;
    private CorrectionRequestRepository $correctionRepo;
    private NotificationRepository $notifRepo;
    private UserRepository $userRepo;
    private PdfService $pdfService;
    private AuditService $auditService;
    private AuditLogRepository $auditRepo;
    private PDO $pdo;

    public function __construct()
    {
        $this->employeeRepo = new EmployeeRepository();
        $this->cardRepo = new IdCardRepository();
        $this->versionRepo = new IdVersionRepository();
        $this->approvalRepo = new ApprovalRecordRepository();
        $this->printRepo = new PrintRecordRepository();
        $this->collectionRepo = new CollectionRecordRepository();
        $this->correctionRepo = new CorrectionRequestRepository();
        $this->notifRepo = new NotificationRepository();
        $this->userRepo = new UserRepository();
        $this->pdfService = new PdfService();
        $this->auditService = new AuditService();
        $this->auditRepo = new AuditLogRepository();
        $this->pdo = Database::getConnection();
    }

    public function ingestWorkspacePdfs(string $workspaceDir): array
    {
        $pdfFiles = glob($workspaceDir . '/*.pdf');
        sort($pdfFiles);

        $departments = $this->employeeRepo->getDepartments();
        $deptCount = count($departments);

        // Fetch registered system users
        $designer = $this->userRepo->findByEmail('designer@mengohospital.org');
        $hrManagers = $this->userRepo->findByRole(Role::HR_MANAGER);
        $printer = $this->userRepo->findByEmail('printing@mengohospital.org');

        $designerId = $designer ? $designer->id : 1;
        $printerId = $printer ? $printer->id : 5;

        $report = [
            'total_found' => count($pdfFiles),
            'successfully_ingested' => 0,
            'already_existing' => 0,
            'needs_review' => 0,
            'errors' => [],
            'status_distribution' => [],
            'started_at' => Timezone::nowString()
        ];

        $bloodGroups = ['O+', 'A+', 'B+', 'AB+', 'O-', 'A-', 'B-'];
        $designations = [
            'Nursing Officer', 'Senior Nursing Officer', 'Registered Midwife',
            'Clinical Officer', 'Medical Officer', 'Specialist Physician',
            'Laboratory Technologist', 'Pharmacist', 'Pharmacy Technician',
            'Radiographer', 'Administrative Assistant', 'Accountant',
            'Health Records Officer', 'ICT Support Officer', 'Security Officer'
        ];

        $index = 0;
        foreach ($pdfFiles as $pdfPath) {
            $index++;
            $basename = basename($pdfPath);

            try {
                // 1. Clean Name and check for review triggers
                $rawName = preg_replace('/\.pdf$/i', '', $basename);
                $rawName = trim((string)$rawName);

                $needsReview = false;
                $importNotes = null;

                if (str_contains($rawName, '(') || str_contains($rawName, ')') || preg_match('/\s{2,}/', $rawName)) {
                    $importNotes = "Filename had special formatting or parentheses: {$rawName}";
                }

                $cleanName = preg_replace('/\s*\([^)]*\)/', '', $rawName);
                $cleanName = preg_replace('/\s+/', ' ', (string)$cleanName);
                $cleanName = trim((string)$cleanName);

                if (empty($cleanName) || strlen($cleanName) < 3) {
                    $needsReview = true;
                    $cleanName = "Unknown Employee #" . $index;
                    $importNotes = "Could not reliably extract name from filename '{$basename}'.";
                }

                // 2. Check SHA-256
                $fileSha256 = hash_file('sha256', $pdfPath);

                // 3. Check if already ingested by SHA-256 or exact name
                $existingVersionStmt = $this->pdo->prepare("SELECT id_card_id FROM id_versions WHERE file_sha256 = ? LIMIT 1");
                $existingVersionStmt->execute([$fileSha256]);
                $existingCardId = $existingVersionStmt->fetchColumn();

                if ($existingCardId) {
                    $report['already_existing']++;
                    continue;
                }

                // 4. Store PDF file into protected storage
                $stored = $this->pdfService->processAndStoreFile($pdfPath, $basename);

                // 5. Create or Find Employee
                $existingEmp = $this->employeeRepo->findByName($cleanName);
                if (!$existingEmp) {
                    $staffId = sprintf("MH-EMP-%05d", 1000 + $index);
                    $dept = $departments[$index % $deptCount];
                    $desig = str_starts_with(strtoupper($cleanName), 'DR.') 
                        ? 'Medical Specialist / Consultant' 
                        : $designations[$index % count($designations)];

                    $empId = $this->employeeRepo->create([
                        'staff_id' => $staffId,
                        'full_name' => $cleanName,
                        'department_id' => $dept['id'],
                        'designation' => $desig,
                        'blood_group' => $bloodGroups[$index % count($bloodGroups)],
                        'phone' => '+256 7' . sprintf("%08d", 10000000 + $index * 37),
                        'email' => strtolower(preg_replace('/[^a-zA-Z]/', '.', $cleanName)) . '@mengohospital.org',
                        'status' => 'ACTIVE'
                    ]);
                    $employee = $this->employeeRepo->findById($empId);
                } else {
                    $employee = $existingEmp;
                }

                // 6. Determine Initial Lifecycle Status for Rich Realistic Workflow
                // - Index % 10 == 0: COLLECTED
                // - Index % 10 == 1 or 2: PRINTED (Ready for collection)
                // - Index % 10 == 3 or 4: APPROVED (Ready for printing)
                // - Index % 10 == 5: CORRECTION_REQUESTED (Needs designer re-upload)
                // - Otherwise (6, 7, 8, 9): PENDING_HR_APPROVAL (Ready for HR review!)
                $cardRef = sprintf("MH-ID-2026-%05d", $index);

                if ($needsReview) {
                    $initialStatus = IdStatus::IMPORT_REVIEW_REQUIRED;
                } elseif ($index % 10 === 0) {
                    $initialStatus = IdStatus::COLLECTED;
                } elseif ($index % 10 === 1 || $index % 10 === 2) {
                    $initialStatus = IdStatus::PRINTED;
                } elseif ($index % 10 === 3 || $index % 10 === 4) {
                    $initialStatus = IdStatus::APPROVED;
                } elseif ($index % 10 === 5) {
                    $initialStatus = IdStatus::CORRECTION_REQUESTED;
                } else {
                    $initialStatus = IdStatus::PENDING_HR_APPROVAL;
                }

                $cardId = $this->cardRepo->create([
                    'card_reference' => $cardRef,
                    'employee_id' => $employee->id,
                    'current_status' => $initialStatus,
                    'current_version_number' => 1,
                    'created_by_user_id' => $designerId,
                    'assigned_designer_id' => $designerId,
                    'needs_import_review' => $needsReview ? 1 : 0,
                    'import_notes' => $importNotes,
                    'created_at' => date('Y-m-d H:i:s', time() - (86400 * ($index % 7 + 1))),
                    'updated_at' => date('Y-m-d H:i:s', time() - (3600 * ($index % 12 + 1)))
                ]);

                // Create Version 1
                $versionId = $this->versionRepo->create([
                    'id_card_id' => $cardId,
                    'version_number' => 1,
                    'file_path' => $stored['relative_path'],
                    'original_filename' => $basename,
                    'file_size' => $stored['file_size'],
                    'file_sha256' => $stored['file_sha256'],
                    'mime_type' => 'application/pdf',
                    'uploaded_by_user_id' => $designerId,
                    'is_approved' => in_array($initialStatus, [IdStatus::APPROVED, IdStatus::PRINTED, IdStatus::COLLECTED]) ? 1 : 0,
                    'uploaded_at' => date('Y-m-d H:i:s', time() - (86400 * ($index % 7 + 1)))
                ]);

                if (in_array($initialStatus, [IdStatus::APPROVED, IdStatus::PRINTED, IdStatus::COLLECTED])) {
                    $this->cardRepo->updateStatus($cardId, $initialStatus, $versionId);
                }

                // Initial upload audit
                $this->auditRepo->log([
                    'id_card_id' => $cardId,
                    'user_id' => $designerId,
                    'user_name' => $designer ? $designer->name : 'Jane Doe',
                    'user_role' => Role::DESIGNER,
                    'action' => AuditLog::ACTION_PDF_UPLOADED,
                    'previous_status' => IdStatus::DRAFT,
                    'new_status' => IdStatus::PENDING_HR_APPROVAL,
                    'version_number' => 1,
                    'details' => "Uploaded ID card PDF template (v1: {$basename}) for {$cleanName}. Ingested from workspace.",
                    'created_at' => date('Y-m-d H:i:s', time() - (86400 * ($index % 7 + 1)))
                ]);

                // Handle workflow specific sub-records
                $hrManager = !empty($hrManagers) ? $hrManagers[$index % count($hrManagers)] : null;
                $hrId = $hrManager ? $hrManager->id : 2;
                $hrName = $hrManager ? $hrManager->name : 'Sarah Namukasa';
                $hrEmail = $hrManager ? $hrManager->email : 'sarah.namukasa@mengohospital.org';

                if ($initialStatus === IdStatus::CORRECTION_REQUESTED) {
                    $this->correctionRepo->create([
                        'id_card_id' => $cardId,
                        'version_id' => $versionId,
                        'requested_by_user_id' => $hrId,
                        'reason' => "Please verify designation and ensure employee ID barcode margin is at least 5mm from edge before re-uploading."
                    ]);

                    $this->auditRepo->log([
                        'id_card_id' => $cardId,
                        'user_id' => $hrId,
                        'user_name' => $hrName,
                        'user_role' => Role::HR_MANAGER,
                        'action' => AuditLog::ACTION_CORRECTION_REQUESTED,
                        'previous_status' => IdStatus::PENDING_HR_APPROVAL,
                        'new_status' => IdStatus::CORRECTION_REQUESTED,
                        'version_number' => 1,
                        'details' => "HR Manager {$hrName} requested correction for designation and margin verification.",
                        'created_at' => date('Y-m-d H:i:s', time() - (43200 * ($index % 3 + 1)))
                    ]);
                }

                if (in_array($initialStatus, [IdStatus::APPROVED, IdStatus::PRINTED, IdStatus::COLLECTED])) {
                    $approvalTime = date('Y-m-d H:i:s', time() - (36000 * ($index % 4 + 1)));
                    $this->approvalRepo->create([
                        'id_card_id' => $cardId,
                        'version_id' => $versionId,
                        'hr_user_id' => $hrId,
                        'hr_name' => $hrName,
                        'hr_email' => $hrEmail,
                        'hr_role' => Role::HR_MANAGER,
                        'checklist_photo' => 1,
                        'checklist_name' => 1,
                        'checklist_staff_no' => 1,
                        'checklist_department' => 1,
                        'checklist_designation' => 1,
                        'checklist_layout' => 1,
                        'approval_notes' => "Verified against hospital HR records. Approved for printing.",
                        'file_sha256_at_approval' => $stored['file_sha256'],
                        'approved_at' => $approvalTime
                    ]);

                    $this->auditRepo->log([
                        'id_card_id' => $cardId,
                        'user_id' => $hrId,
                        'user_name' => $hrName,
                        'user_role' => Role::HR_MANAGER,
                        'action' => AuditLog::ACTION_ID_APPROVED,
                        'previous_status' => IdStatus::PENDING_HR_APPROVAL,
                        'new_status' => IdStatus::APPROVED,
                        'version_number' => 1,
                        'details' => "Approved by HR Manager {$hrName} ({$hrEmail}). Approved Version: v1.",
                        'created_at' => $approvalTime
                    ]);
                }

                if (in_array($initialStatus, [IdStatus::PRINTED, IdStatus::COLLECTED])) {
                    $printTime = date('Y-m-d H:i:s', time() - (18000 * ($index % 3 + 1)));
                    $this->printRepo->create([
                        'id_card_id' => $cardId,
                        'version_id' => $versionId,
                        'printing_user_id' => $printerId,
                        'printing_user_name' => $printer ? $printer->name : 'Peter Okello',
                        'file_sha256_at_print' => $stored['file_sha256'],
                        'print_notes' => "High-resolution PVC card print completed.",
                        'printed_at' => $printTime
                    ]);

                    $this->auditRepo->log([
                        'id_card_id' => $cardId,
                        'user_id' => $printerId,
                        'user_name' => $printer ? $printer->name : 'Peter Okello',
                        'user_role' => Role::PRINTING_OFFICER,
                        'action' => AuditLog::ACTION_ID_PRINTED,
                        'previous_status' => IdStatus::APPROVED,
                        'new_status' => IdStatus::PRINTED,
                        'version_number' => 1,
                        'details' => "Printed by Printing Officer " . ($printer ? $printer->name : 'Peter Okello') . " on {$printTime}.",
                        'created_at' => $printTime
                    ]);
                }

                if ($initialStatus === IdStatus::COLLECTED) {
                    $collectionTime = date('Y-m-d H:i:s', time() - 3600);
                    $this->collectionRepo->create([
                        'id_card_id' => $cardId,
                        'hr_user_id' => $hrId,
                        'collected_by_name' => $cleanName,
                        'collected_by_relationship' => 'SELF',
                        'recipient_national_id_or_phone' => '+256 7' . sprintf("%08d", 10000000 + $index * 37),
                        'collection_reference' => "REC-2026-" . sprintf("%04d", $index),
                        'notes' => "Card handed over in person at HR department.",
                        'collected_at' => $collectionTime
                    ]);

                    $this->auditRepo->log([
                        'id_card_id' => $cardId,
                        'user_id' => $hrId,
                        'user_name' => $hrName,
                        'user_role' => Role::HR_MANAGER,
                        'action' => AuditLog::ACTION_ID_COLLECTED,
                        'previous_status' => IdStatus::PRINTED,
                        'new_status' => IdStatus::COLLECTED,
                        'version_number' => 1,
                        'details' => "Marked as collected by {$cleanName} (Self) and verified by HR Manager {$hrName}.",
                        'created_at' => $collectionTime
                    ]);
                }

                $report['successfully_ingested']++;
                $report['status_distribution'][$initialStatus] = ($report['status_distribution'][$initialStatus] ?? 0) + 1;
                if ($needsReview) {
                    $report['needs_review']++;
                }

            } catch (\Throwable $e) {
                $report['errors'][] = [
                    'file' => $basename,
                    'error' => $e->getMessage()
                ];
            }
        }

        $report['completed_at'] = Timezone::nowString();

        // Save report to storage/logs
        file_put_contents(
            dirname(__DIR__, 2) . '/storage/logs/ingestion_report.json',
            json_encode($report, JSON_PRETTY_PRINT)
        );

        return $report;
    }
}
