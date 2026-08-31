<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Services;

use Mengo\IdApproval\Models\AuditLog;
use Mengo\IdApproval\Models\IdCard;
use Mengo\IdApproval\Models\IdStatus;
use Mengo\IdApproval\Models\Role;
use Mengo\IdApproval\Models\User;
use Mengo\IdApproval\Repositories\ApprovalRecordRepository;
use Mengo\IdApproval\Repositories\CollectionRecordRepository;
use Mengo\IdApproval\Repositories\CorrectionRequestRepository;
use Mengo\IdApproval\Repositories\EmployeeRepository;
use Mengo\IdApproval\Repositories\IdCardRepository;
use Mengo\IdApproval\Repositories\IdVersionRepository;
use Mengo\IdApproval\Repositories\PrintRecordRepository;
use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;
use RuntimeException;

class WorkflowService
{
    private IdCardRepository $cardRepo;
    private IdVersionRepository $versionRepo;
    private CorrectionRequestRepository $correctionRepo;
    private ApprovalRecordRepository $approvalRepo;
    private PrintRecordRepository $printRepo;
    private CollectionRecordRepository $collectionRepo;
    private EmployeeRepository $employeeRepo;
    private AuditService $auditService;
    private NotificationService $notifService;
    private PdfService $pdfService;

    public function __construct(
        ?IdCardRepository $cardRepo = null,
        ?IdVersionRepository $versionRepo = null,
        ?CorrectionRequestRepository $correctionRepo = null,
        ?ApprovalRecordRepository $approvalRepo = null,
        ?PrintRecordRepository $printRepo = null,
        ?CollectionRecordRepository $collectionRepo = null,
        ?EmployeeRepository $employeeRepo = null,
        ?AuditService $auditService = null,
        ?NotificationService $notifService = null,
        ?PdfService $pdfService = null
    ) {
        $this->cardRepo = $cardRepo ?? new IdCardRepository();
        $this->versionRepo = $versionRepo ?? new IdVersionRepository();
        $this->correctionRepo = $correctionRepo ?? new CorrectionRequestRepository();
        $this->approvalRepo = $approvalRepo ?? new ApprovalRecordRepository();
        $this->printRepo = $printRepo ?? new PrintRecordRepository();
        $this->collectionRepo = $collectionRepo ?? new CollectionRecordRepository();
        $this->employeeRepo = $employeeRepo ?? new EmployeeRepository();
        $this->auditService = $auditService ?? new AuditService();
        $this->notifService = $notifService ?? new NotificationService();
        $this->pdfService = $pdfService ?? new PdfService();
    }

    /**
     * Create ID request and upload initial PDF design (v1)
     */
    public function uploadInitialDesign(
        int $employeeId,
        array $fileUpload,
        User $user,
        ?string $cardRef = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): IdCard {
        if (!$user->isDesigner() && !$user->isHrManager()) {
            throw new RuntimeException("Only Designers (or authorized HR) can create and upload ID card designs.");
        }

        $employee = $this->employeeRepo->findById($employeeId);
        if (!$employee) {
            throw new RuntimeException("Employee record not found.");
        }

        // Validate and store uploaded PDF
        $storedPdf = $this->pdfService->validateAndStoreUpload($fileUpload);

        return Database::transaction(function () use ($employee, $storedPdf, $user, $cardRef, $ip, $userAgent) {
            if (empty($cardRef)) {
                $countStmt = Database::getConnection()->query("SELECT COUNT(*) FROM id_cards");
                $num = (int)$countStmt->fetchColumn() + 1;
                $cardRef = sprintf("MH-ID-2026-%05d", $num);
            }

            // Create ID Card record
            $cardId = $this->cardRepo->create([
                'card_reference' => $cardRef,
                'employee_id' => $employee->id,
                'current_status' => IdStatus::PENDING_HR_APPROVAL,
                'current_version_number' => 1,
                'created_by_user_id' => $user->id,
                'assigned_designer_id' => $user->id,
            ]);

            // Create Version 1 record
            $versionId = $this->versionRepo->create([
                'id_card_id' => $cardId,
                'version_number' => 1,
                'file_path' => $storedPdf['relative_path'],
                'original_filename' => $storedPdf['original_filename'],
                'file_size' => $storedPdf['file_size'],
                'file_sha256' => $storedPdf['file_sha256'],
                'mime_type' => $storedPdf['mime_type'],
                'uploaded_by_user_id' => $user->id,
                'is_approved' => 0
            ]);

            $idCard = $this->cardRepo->findById($cardId);

            // Audit log
            $this->auditService->logWorkflow(
                $cardId,
                AuditLog::ACTION_PDF_UPLOADED,
                IdStatus::DRAFT,
                IdStatus::PENDING_HR_APPROVAL,
                1,
                "Designer {$user->name} uploaded initial ID design (v1: {$storedPdf['original_filename']}) for {$employee->full_name}. SHA-256: " . substr($storedPdf['file_sha256'], 0, 16) . "...",
                $ip,
                $userAgent
            );

            // Notify HR Managers
            $this->notifService->notifyIdUploaded($idCard, $user->name);

            return $idCard;
        });
    }

    /**
     * Re-upload a corrected ID design (creates next version v2, v3, etc.)
     */
    public function uploadCorrectedDesign(
        int $idCardId,
        array $fileUpload,
        User $user,
        ?string $ip = null,
        ?string $userAgent = null
    ): IdCard {
        if (!$user->isDesigner()) {
            throw new RuntimeException("Only the ID Designer can re-upload corrected designs.");
        }

        $card = $this->cardRepo->findById($idCardId);
        if (!$card) {
            throw new RuntimeException("ID card record not found.");
        }

        if ($card->current_status !== IdStatus::CORRECTION_REQUESTED) {
            throw new RuntimeException("Cannot re-upload: ID card is currently '{$card->current_status}', not 'CORRECTION_REQUESTED'.");
        }

        // Store new PDF
        $storedPdf = $this->pdfService->validateAndStoreUpload($fileUpload);

        return Database::transaction(function () use ($card, $storedPdf, $user, $ip, $userAgent) {
            $nextVersionNumber = $card->current_version_number + 1;

            // Find pending correction request to resolve
            $pendingCorrection = $this->correctionRepo->getPendingForCard($card->id);

            // Create new version record
            $versionId = $this->versionRepo->create([
                'id_card_id' => $card->id,
                'version_number' => $nextVersionNumber,
                'file_path' => $storedPdf['relative_path'],
                'original_filename' => $storedPdf['original_filename'],
                'file_size' => $storedPdf['file_size'],
                'file_sha256' => $storedPdf['file_sha256'],
                'mime_type' => $storedPdf['mime_type'],
                'uploaded_by_user_id' => $user->id,
                'correction_request_id' => $pendingCorrection?->id,
                'is_approved' => 0
            ]);

            // Resolve pending correction request
            if ($pendingCorrection) {
                $this->correctionRepo->resolve($pendingCorrection->id, $versionId);
            }

            // Update ID Card to PENDING_HR_APPROVAL and increment version
            $this->cardRepo->incrementVersion($card->id, $nextVersionNumber, IdStatus::PENDING_HR_APPROVAL);

            $updatedCard = $this->cardRepo->findById($card->id);

            // Audit log
            $this->auditService->logWorkflow(
                $card->id,
                AuditLog::ACTION_PDF_REUPLOADED,
                IdStatus::CORRECTION_REQUESTED,
                IdStatus::PENDING_HR_APPROVAL,
                $nextVersionNumber,
                "Designer {$user->name} uploaded corrected version v{$nextVersionNumber} ({$storedPdf['original_filename']}). SHA-256: " . substr($storedPdf['file_sha256'], 0, 16) . "...",
                $ip,
                $userAgent
            );

            // Notify HR Managers
            $this->notifService->notifyIdReuploaded($updatedCard, $user->name, $nextVersionNumber);

            return $updatedCard;
        });
    }

    /**
     * HR Manager requests correction
     */
    public function requestCorrection(
        int $idCardId,
        string $reason,
        User $user,
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        if (!$user->isHrManager()) {
            throw new RuntimeException("Access Denied: Only registered HR Managers can request ID corrections.");
        }

        $reason = trim($reason);
        if (empty($reason)) {
            throw new RuntimeException("A detailed correction reason is mandatory when requesting changes.");
        }

        $card = $this->cardRepo->findById($idCardId);
        if (!$card) {
            throw new RuntimeException("ID card not found.");
        }

        if ($card->current_status !== IdStatus::PENDING_HR_APPROVAL) {
            throw new RuntimeException("Cannot request correction: ID status is '{$card->current_status}', must be 'PENDING_HR_APPROVAL'.");
        }

        Database::transaction(function () use ($card, $reason, $user, $ip, $userAgent) {
            $currentVersion = $this->versionRepo->findByCardAndVersion($card->id, $card->current_version_number);
            if (!$currentVersion) {
                throw new RuntimeException("Current version record not found.");
            }

            // Record correction request
            $correctionId = $this->correctionRepo->create([
                'id_card_id' => $card->id,
                'version_id' => $currentVersion->id,
                'requested_by_user_id' => $user->id,
                'reason' => $reason
            ]);

            // Update ID Card Status
            $this->cardRepo->updateStatus($card->id, IdStatus::CORRECTION_REQUESTED);

            // Audit log
            $this->auditService->logWorkflow(
                $card->id,
                AuditLog::ACTION_CORRECTION_REQUESTED,
                IdStatus::PENDING_HR_APPROVAL,
                IdStatus::CORRECTION_REQUESTED,
                $card->current_version_number,
                "HR Manager {$user->name} ({$user->email}) requested correction for v{$card->current_version_number}. Reason: \"{$reason}\"",
                $ip,
                $userAgent
            );

            // Notify Designer
            $this->notifService->notifyCorrectionRequested($card, $user->name, $reason, $card->assigned_designer_id ?? $card->created_by_user_id);
        });
    }

    /**
     * HR Manager Approves ID card (Strict verification checklist + atomic CAS concurrency check)
     */
    public function approveId(
        int $idCardId,
        array $checklist,
        ?string $approvalNotes,
        User $user,
        ?string $ip = null,
        ?string $userAgent = null
    ): IdCard {
        if (!$user->isHrManager()) {
            throw new RuntimeException("Access Denied: Only registered HR Managers have approval privileges.");
        }

        // Verify all mandatory checklist items are checked
        $requiredChecks = ['photo', 'name', 'staff_no', 'department', 'designation', 'layout'];
        foreach ($requiredChecks as $check) {
            if (empty($checklist[$check])) {
                throw new RuntimeException("Verification Checklist incomplete: You must verify '{$check}' before approving.");
            }
        }

        $card = $this->cardRepo->findById($idCardId);
        if (!$card) {
            throw new RuntimeException("ID card not found.");
        }

        if ($card->current_status !== IdStatus::PENDING_HR_APPROVAL) {
            if ($card->current_status === IdStatus::APPROVED) {
                $existingApproval = $this->approvalRepo->findByCardId($card->id);
                $approvedBy = $existingApproval ? $existingApproval->hr_name : 'another HR Manager';
                $approvedAt = $existingApproval ? Timezone::formatDetailed($existingApproval->approved_at) : 'recently';
                throw new RuntimeException("Approval Concurrency Conflict: This ID has already been approved by {$approvedBy} on {$approvedAt}.");
            }
            throw new RuntimeException("Cannot approve: Current status is '{$card->current_status}', must be 'PENDING_HR_APPROVAL'.");
        }

        $currentVersion = $this->versionRepo->findByCardAndVersion($card->id, $card->current_version_number);
        if (!$currentVersion) {
            throw new RuntimeException("Current version record not found.");
        }

        // Verify file integrity on disk
        if (!$this->pdfService->verifyIntegrity($currentVersion->file_path, $currentVersion->file_sha256)) {
            throw new RuntimeException("Critical Integrity Error: PDF hash mismatch detected on storage. Approval aborted.");
        }

        return Database::transaction(function () use ($card, $currentVersion, $checklist, $approvalNotes, $user, $ip, $userAgent) {
            // Atomic CAS check: Ensure status is STILL 'PENDING_HR_APPROVAL'
            $updated = $this->cardRepo->updateStatusConditional(
                $card->id,
                IdStatus::PENDING_HR_APPROVAL,
                IdStatus::APPROVED,
                $currentVersion->id
            );

            if (!$updated) {
                // Another manager approved in the millisecond window!
                $existingApproval = $this->approvalRepo->findByCardId($card->id);
                $approvedBy = $existingApproval ? $existingApproval->hr_name : 'another HR Manager';
                $approvedAt = $existingApproval ? Timezone::formatDetailed($existingApproval->approved_at) : 'recently';
                throw new RuntimeException("Approval Concurrency Conflict: This ID has already been approved by {$approvedBy} on {$approvedAt}.");
            }

            // Lock approved version
            $this->versionRepo->markAsApproved($currentVersion->id);

            // Record permanent approval metadata
            $now = Timezone::nowString();
            $this->approvalRepo->create([
                'id_card_id' => $card->id,
                'version_id' => $currentVersion->id,
                'hr_user_id' => $user->id,
                'hr_name' => $user->name,
                'hr_email' => $user->email,
                'hr_role' => $user->role,
                'checklist_photo' => 1,
                'checklist_name' => 1,
                'checklist_staff_no' => 1,
                'checklist_department' => 1,
                'checklist_designation' => 1,
                'checklist_layout' => 1,
                'approval_notes' => $approvalNotes,
                'file_sha256_at_approval' => $currentVersion->file_sha256,
                'approved_at' => $now
            ]);

            // Audit log
            $this->auditService->logWorkflow(
                $card->id,
                AuditLog::ACTION_ID_APPROVED,
                IdStatus::PENDING_HR_APPROVAL,
                IdStatus::APPROVED,
                $currentVersion->version_number,
                "Approved by HR Manager {$user->name} ({$user->email}) at " . Timezone::formatDetailed($now) . ". Approved Version: v{$currentVersion->version_number}. Notes: " . ($approvalNotes ?: 'None'),
                $ip,
                $userAgent
            );

            // Dispatch Notifications to Designer & Printing Officer
            $this->notifService->notifyIdApproved(
                $card,
                $user->name,
                $currentVersion->version_number,
                $card->assigned_designer_id ?? $card->created_by_user_id
            );

            return $this->cardRepo->findById($card->id);
        });
    }

    /**
     * Printing Officer marks physical card as printed
     */
    public function markAsPrinted(
        int $idCardId,
        ?string $printNotes,
        User $user,
        ?string $ip = null,
        ?string $userAgent = null
    ): IdCard {
        if (!$user->isPrintingOfficer()) {
            throw new RuntimeException("Access Denied: Only the Printing Officer can mark ID cards as printed.");
        }

        $card = $this->cardRepo->findById($idCardId);
        if (!$card) {
            throw new RuntimeException("ID card not found.");
        }

        if ($card->current_status !== IdStatus::APPROVED) {
            throw new RuntimeException("Cannot print: ID status is '{$card->current_status}', must be 'APPROVED'.");
        }

        $approvedVersionId = $card->approved_version_id;
        $version = $approvedVersionId ? $this->versionRepo->findById($approvedVersionId) : null;
        if (!$version) {
            $version = $this->versionRepo->findByCardAndVersion($card->id, $card->current_version_number);
        }

        if (!$version) {
            throw new RuntimeException("Approved PDF version not found.");
        }

        // Verify PDF integrity before printing
        if (!$this->pdfService->verifyIntegrity($version->file_path, $version->file_sha256)) {
            throw new RuntimeException("Critical Error: Approved PDF file integrity check failed. Cannot mark as printed.");
        }

        return Database::transaction(function () use ($card, $version, $printNotes, $user, $ip, $userAgent) {
            $updated = $this->cardRepo->updateStatusConditional($card->id, IdStatus::APPROVED, IdStatus::PRINTED);
            if (!$updated) {
                throw new RuntimeException("ID status was modified by another operation.");
            }

            $now = Timezone::nowString();
            $this->printRepo->create([
                'id_card_id' => $card->id,
                'version_id' => $version->id,
                'printing_user_id' => $user->id,
                'printing_user_name' => $user->name,
                'file_sha256_at_print' => $version->file_sha256,
                'print_notes' => $printNotes,
                'printed_at' => $now
            ]);

            // Audit log
            $this->auditService->logWorkflow(
                $card->id,
                AuditLog::ACTION_ID_PRINTED,
                IdStatus::APPROVED,
                IdStatus::PRINTED,
                $version->version_number,
                "Printing Officer {$user->name} confirmed physical ID card printing on " . Timezone::formatDetailed($now) . ". Notes: " . ($printNotes ?: 'None'),
                $ip,
                $userAgent
            );

            // Notify HR
            $this->notifService->notifyIdPrinted($card, $user->name);

            return $this->cardRepo->findById($card->id);
        });
    }

    /**
     * HR marks printed card as collected by employee
     */
    public function markAsCollected(
        int $idCardId,
        string $collectedByName,
        string $relationship,
        ?string $recipientIdPhone,
        ?string $reference,
        ?string $notes,
        User $user,
        ?string $ip = null,
        ?string $userAgent = null
    ): IdCard {
        if (!$user->isHrManager()) {
            throw new RuntimeException("Access Denied: Only HR Managers can record ID card collection.");
        }

        $collectedByName = trim($collectedByName);
        if (empty($collectedByName)) {
            throw new RuntimeException("Recipient name is required.");
        }

        $card = $this->cardRepo->findById($idCardId);
        if (!$card) {
            throw new RuntimeException("ID card not found.");
        }

        if ($card->current_status !== IdStatus::PRINTED) {
            throw new RuntimeException("Cannot mark collected: ID status is '{$card->current_status}', must be 'PRINTED'.");
        }

        return Database::transaction(function () use ($card, $collectedByName, $relationship, $recipientIdPhone, $reference, $notes, $user, $ip, $userAgent) {
            $updated = $this->cardRepo->updateStatusConditional($card->id, IdStatus::PRINTED, IdStatus::COLLECTED);
            if (!$updated) {
                throw new RuntimeException("ID status was modified by another operation.");
            }

            $now = Timezone::nowString();
            $this->collectionRepo->create([
                'id_card_id' => $card->id,
                'hr_user_id' => $user->id,
                'hr_name' => $user->name,
                'collected_by_name' => $collectedByName,
                'collected_by_relationship' => $relationship,
                'recipient_contact' => $recipientIdPhone,
                'collection_reference' => $reference,
                'collection_notes' => $notes,
                'collected_at' => $now
            ]);

            // Audit log
            $this->auditService->logWorkflow(
                $card->id,
                AuditLog::ACTION_ID_COLLECTED,
                IdStatus::PRINTED,
                IdStatus::COLLECTED,
                $card->current_version_number,
                "HR Manager {$user->name} recorded ID handover to recipient '{$collectedByName}' ({$relationship}) on " . Timezone::formatDetailed($now) . ".",
                $ip,
                $userAgent
            );

            // Notify HR & Designer
            $this->notifService->notifyIdCollected($card, $user->name, $collectedByName);

            return $this->cardRepo->findById($card->id);
        });
    }

    /**
     * Step 1: Pre-merge validation & manifest preparation
     */
    public function validateAndCreatePrintBatch(
        array $idCardIds,
        User $user,
        array $options = [],
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        if (!$user->isPrintingOfficer()) {
            throw new RuntimeException("Access Denied: Only Printing Officers can create and validate print batches.");
        }

        $mergeService = new PdfMergeService($this->cardRepo, $this->versionRepo, null, $this->pdfService);
        $validation = $mergeService->validateDocuments($idCardIds);

        $now = Timezone::nowString();
        $batchRef = 'MB-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        $batchRepo = new \Mengo\IdApproval\Repositories\PrintBatchRepository();
        $batchId = $batchRepo->create([
            'batch_reference' => $batchRef,
            'printing_user_id' => $user->id,
            'printing_user_name' => $user->name,
            'status' => $validation['failed_count'] > 0 ? \Mengo\IdApproval\Models\PrintBatch::STATUS_PARTIAL_SUCCESS : \Mengo\IdApproval\Models\PrintBatch::STATUS_PREPARING,
            'total_cards' => $validation['valid_count'],
            'selected_count' => $validation['total_selected'],
            'valid_count' => $validation['valid_count'],
            'failed_count' => $validation['failed_count'],
            'page_count' => $validation['total_pages'],
            'file_size' => $validation['estimated_size'],
            'orientation' => $options['orientation'] ?? 'ORIGINAL',
            'page_size' => $validation['is_mixed_size'] ? 'Mixed' : ($validation['page_sizes'][0] ?? 'Standard ID'),
            'notes' => $options['notes'] ?? null,
            'error_summary' => !empty($validation['failed_items']) ? json_encode($validation['failed_items']) : null,
            'created_at' => $now,
            'expires_at' => date('Y-m-d H:i:s', time() + (48 * 3600))
        ]);

        // Record batch items
        foreach ($validation['valid_items'] as $item) {
            $batchRepo->addItem([
                'batch_id' => $batchId,
                'id_card_id' => $item['id_card_id'],
                'approved_version_id' => $item['approved_version_id'],
                'employee_id' => $item['employee_id'],
                'employee_name' => $item['employee_name'],
                'sequence_number' => $item['sequence_number'],
                'validation_status' => \Mengo\IdApproval\Models\PrintBatchItem::STATUS_VALID,
                'included_in_output' => 1
            ]);
        }

        foreach ($validation['failed_items'] as $item) {
            $batchRepo->addItem([
                'batch_id' => $batchId,
                'id_card_id' => $item['id_card_id'],
                'approved_version_id' => $item['version_id'] ?? null,
                'employee_id' => null,
                'employee_name' => $item['employee_name'],
                'sequence_number' => 999,
                'validation_status' => $item['status'],
                'failure_reason' => $item['reason'],
                'included_in_output' => 0
            ]);
        }

        // Audit log
        $this->auditService->log([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => 'BATCH_VALIDATION_COMPLETED',
            'details' => "Printing Officer {$user->name} validated print batch '{$batchRef}': {$validation['valid_count']} valid, {$validation['failed_count']} failed.",
            'ip_address' => $ip,
            'user_agent' => $userAgent
        ]);

        return [
            'batch_id' => $batchId,
            'batch_reference' => $batchRef,
            'validation' => $validation
        ];
    }

    /**
     * Step 2: Merge valid PDFs into consolidated print artifact
     */
    public function executeBatchMerge(
        int $batchId,
        User $user,
        string $orientation = 'ORIGINAL',
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        if (!$user->isPrintingOfficer()) {
            throw new RuntimeException("Access Denied: Only Printing Officers can execute PDF merges.");
        }

        $batchRepo = new \Mengo\IdApproval\Repositories\PrintBatchRepository();
        $batch = $batchRepo->findById($batchId);
        if (!$batch) {
            throw new RuntimeException("Print batch not found.");
        }

        $items = $batchRepo->getItems($batchId);
        $validFiles = [];

        foreach ($items as $item) {
            if ($item->included_in_output && $item->validation_status === \Mengo\IdApproval\Models\PrintBatchItem::STATUS_VALID) {
                $version = $this->versionRepo->findById((int)$item->approved_version_id);
                if ($version) {
                    $fullPath = $this->pdfService->getAbsolutePath($version->file_path);
                    if (file_exists($fullPath)) {
                        $validFiles[] = $fullPath;
                    }
                }
            }
        }

        if (empty($validFiles)) {
            $batchRepo->update($batchId, ['status' => \Mengo\IdApproval\Models\PrintBatch::STATUS_FAILED, 'error_summary' => 'No valid PDF files to merge']);
            throw new RuntimeException("Cannot merge: No valid PDF files exist in this batch.");
        }

        $batchRepo->update($batchId, ['status' => \Mengo\IdApproval\Models\PrintBatch::STATUS_MERGING]);

        $filename = "MENGO_ID_BATCH_" . date('Y_m_d') . "_{$batch->batch_reference}.pdf";
        $tempDir = dirname(__DIR__, 2) . '/storage/temp';
        $outputPath = $tempDir . '/' . $filename;

        $mergeService = new PdfMergeService($this->cardRepo, $this->versionRepo, $batchRepo, $this->pdfService, $tempDir);
        $mergeResult = $mergeService->mergeFiles($validFiles, $outputPath, $orientation);

        $now = Timezone::nowString();
        $batchRepo->update($batchId, [
            'status' => $batch->failed_count > 0 ? \Mengo\IdApproval\Models\PrintBatch::STATUS_PARTIAL_SUCCESS : \Mengo\IdApproval\Models\PrintBatch::STATUS_READY,
            'output_filename' => $filename,
            'output_path' => $outputPath,
            'output_hash' => $mergeResult['output_hash'],
            'page_count' => $mergeResult['page_count'],
            'file_size' => $mergeResult['file_size'],
            'orientation' => $orientation,
            'completed_at' => $now
        ]);

        // Audit log
        $this->auditService->log([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => 'PDF_MERGE_COMPLETED',
            'details' => "Consolidated PDF generated for batch '{$batch->batch_reference}': {$mergeResult['page_count']} pages, " . round($mergeResult['file_size'] / 1024 / 1024, 2) . " MB.",
            'ip_address' => $ip,
            'user_agent' => $userAgent
        ]);

        return array_merge($mergeResult, [
            'batch_id' => $batchId,
            'batch_reference' => $batch->batch_reference,
            'download_url' => "/printing/batches/{$batchId}/download",
            'preview_url' => "/printing/batches/{$batchId}/preview"
        ]);
    }

    /**
     * Step 3: Confirm Physical Printing (Marks IDs as PRINTED)
     */
    public function confirmPhysicalPrint(
        int $batchId,
        array $confirmedCardIds,
        ?string $notes,
        User $user,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        if (!$user->isPrintingOfficer()) {
            throw new RuntimeException("Access Denied: Only Printing Officers can confirm physical printing.");
        }

        $batchRepo = new \Mengo\IdApproval\Repositories\PrintBatchRepository();
        $batch = $batchRepo->findById($batchId);
        if (!$batch) {
            throw new RuntimeException("Batch not found.");
        }

        $confirmedCardIds = array_values(array_unique(array_filter(array_map('intval', $confirmedCardIds))));
        if (empty($confirmedCardIds)) {
            throw new RuntimeException("Please select at least one employee ID to confirm physical printing.");
        }

        return Database::transaction(function () use ($batch, $confirmedCardIds, $notes, $user, $ip, $userAgent, $batchRepo) {
            $now = Timezone::nowString();
            $printedCards = [];

            foreach ($confirmedCardIds as $cardId) {
                $card = $this->cardRepo->findById($cardId);
                if (!$card) continue;

                if ($card->current_status !== IdStatus::APPROVED) {
                    continue; // Skip or already printed
                }

                $version = null;
                if ($card->approved_version_id) {
                    $version = $this->versionRepo->findById($card->approved_version_id);
                }
                if (!$version) {
                    $version = $this->versionRepo->findByCardAndVersion($card->id, $card->current_version_number);
                }

                $updated = $this->cardRepo->updateStatusConditional($card->id, IdStatus::APPROVED, IdStatus::PRINTED);
                if (!$updated) {
                    continue; // Concurrent update safety
                }

                $this->printRepo->create([
                    'id_card_id' => $card->id,
                    'version_id' => $version ? $version->id : $card->approved_version_id,
                    'printing_user_id' => $user->id,
                    'printing_user_name' => $user->name,
                    'file_sha256_at_print' => $version ? $version->file_sha256 : '',
                    'print_notes' => "Batch {$batch->batch_reference}" . ($notes ? ": {$notes}" : ''),
                    'print_batch_id' => $batch->id,
                    'printed_at' => $now
                ]);

                $batchRepo->markItemPrinted($batch->id, $card->id, $now);

                // Audit log for individual card
                $this->auditService->logWorkflow(
                    $card->id,
                    AuditLog::ACTION_ID_PRINTED,
                    IdStatus::APPROVED,
                    IdStatus::PRINTED,
                    $card->current_version_number,
                    "Printing Officer {$user->name} confirmed physical printing in batch '{$batch->batch_reference}' on " . Timezone::formatDetailed($now) . ".",
                    $ip,
                    $userAgent
                );

                // Notify HR & Designer
                $this->notifService->notifyIdPrinted($card, $user->name);
                $printedCards[] = $this->cardRepo->findById($card->id);
            }

            $batchRepo->update($batch->id, [
                'status' => \Mengo\IdApproval\Models\PrintBatch::STATUS_COMPLETED,
                'completed_at' => $now
            ]);

            // Audit log for the entire batch
            $this->auditService->log([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_role' => $user->role,
                'action' => 'PRINT_CONFIRMED',
                'details' => "Physical printing confirmed for batch '{$batch->batch_reference}': " . count($printedCards) . " cards marked as PRINTED.",
                'ip_address' => $ip,
                'user_agent' => $userAgent
            ]);

            return [
                'batch_id' => $batch->id,
                'batch_reference' => $batch->batch_reference,
                'total_printed' => count($printedCards),
                'cards' => $printedCards,
                'printed_at' => $now
            ];
        });
    }

    /**
     * Backward-compatible 1-step bulk print runner
     */
    public function bulkPrint(
        array $idCardIds,
        ?string $batchNotes,
        User $user,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        $prep = $this->validateAndCreatePrintBatch($idCardIds, $user, ['notes' => $batchNotes], $ip, $userAgent);
        if ($prep['validation']['failed_count'] > 0 && $prep['validation']['valid_count'] === 0) {
            $firstFail = $prep['validation']['failed_items'][0]['reason'] ?? 'Ineligible status';
            throw new RuntimeException("Bulk Print Rejected: {$prep['validation']['failed_count']} selected card(s) are not eligible ({$firstFail}).");
        }
        if ($prep['validation']['failed_count'] > 0) {
            throw new RuntimeException("Bulk Print Rejected: {$prep['validation']['failed_count']} selected card(s) are not eligible for printing. Only verified APPROVED ID cards can enter a print batch.");
        }

        $this->executeBatchMerge($prep['batch_id'], $user, 'ORIGINAL', $ip, $userAgent);

        $validIds = array_column($prep['validation']['valid_items'], 'id_card_id');
        return $this->confirmPhysicalPrint($prep['batch_id'], $validIds, $batchNotes, $user, $ip, $userAgent);
    }

    /**
     * Get Smart Follow-up & Attention Thresholds
     */
    public function getSmartFollowUpAlerts(): array
    {
        $overdueApprovals = $this->cardRepo->getOverduePendingApprovals(24);
        $staleCorrections = $this->cardRepo->getFiltered(['status' => IdStatus::CORRECTION_REQUESTED], 100, 0);
        $printingDelays = $this->cardRepo->getFiltered(['status' => IdStatus::APPROVED], 100, 0);
        $collectionDelays = $this->cardRepo->getFiltered(['status' => IdStatus::PRINTED], 100, 0);

        // Filter stale corrections (>48 hours)
        $staleCorrections = array_filter($staleCorrections, function($c) {
            return Timezone::hoursDifference($c->updated_at) >= 48;
        });

        // Filter printing delays (>24 hours)
        $printingDelays = array_filter($printingDelays, function($c) {
            return Timezone::hoursDifference($c->updated_at) >= 24;
        });

        // Filter collection delays (>7 days / 168 hours)
        $collectionDelays = array_filter($collectionDelays, function($c) {
            return Timezone::hoursDifference($c->updated_at) >= 168;
        });

        return [
            'overdue_approvals' => [
                'count' => count($overdueApprovals),
                'label' => 'Approvals Pending > 24 Hours',
                'badge' => 'OVERDUE',
                'severity' => 'danger',
                'items' => array_values($overdueApprovals)
            ],
            'stale_corrections' => [
                'count' => count($staleCorrections),
                'label' => 'Corrections Awaiting Designer > 48 Hours',
                'badge' => 'FOLLOW UP',
                'severity' => 'warning',
                'items' => array_values($staleCorrections)
            ],
            'printing_delays' => [
                'count' => count($printingDelays),
                'label' => 'Approved Cards Unprinted > 24 Hours',
                'badge' => 'PRINTING DELAY',
                'severity' => 'warning',
                'items' => array_values($printingDelays)
            ],
            'collection_delays' => [
                'count' => count($collectionDelays),
                'label' => 'Printed Cards Uncollected > 7 Days',
                'badge' => 'COLLECTION DELAY',
                'severity' => 'info',
                'items' => array_values($collectionDelays)
            ],
            'total_alerts' => count($overdueApprovals) + count($staleCorrections) + count($printingDelays) + count($collectionDelays)
        ];
    }
}
