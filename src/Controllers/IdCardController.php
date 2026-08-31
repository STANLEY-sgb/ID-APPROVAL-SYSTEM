<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Controllers;

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
use Mengo\IdApproval\Repositories\PrintRecordRepository;
use Mengo\IdApproval\Security\SessionManager;
use Mengo\IdApproval\Services\AuditService;
use Mengo\IdApproval\Services\PdfService;
use Mengo\IdApproval\Services\WorkflowService;
use Mengo\IdApproval\Support\Request;
use Mengo\IdApproval\Support\Response;
use Mengo\IdApproval\Support\View;

class IdCardController
{
    private IdCardRepository $cardRepo;
    private IdVersionRepository $versionRepo;
    private ApprovalRecordRepository $approvalRepo;
    private CorrectionRequestRepository $correctionRepo;
    private PrintRecordRepository $printRepo;
    private CollectionRecordRepository $collectionRepo;
    private AuditLogRepository $auditRepo;
    private EmployeeRepository $employeeRepo;
    private WorkflowService $workflowService;
    private PdfService $pdfService;
    private AuditService $auditService;

    public function __construct()
    {
        $this->cardRepo = new IdCardRepository();
        $this->versionRepo = new IdVersionRepository();
        $this->approvalRepo = new ApprovalRecordRepository();
        $this->correctionRepo = new CorrectionRequestRepository();
        $this->printRepo = new PrintRecordRepository();
        $this->collectionRepo = new CollectionRecordRepository();
        $this->auditRepo = new AuditLogRepository();
        $this->employeeRepo = new EmployeeRepository();
        $this->workflowService = new WorkflowService();
        $this->pdfService = new PdfService();
        $this->auditService = new AuditService();
    }

    public function show(mixed $requestOrId = null, mixed $paramsOrRequest = null): void
    {
        if (is_numeric($requestOrId)) {
            $id = (int)$requestOrId;
            $request = ($paramsOrRequest instanceof Request) ? $paramsOrRequest : new Request();
        } else {
            $request = ($requestOrId instanceof Request) ? $requestOrId : new Request();
            $id = (int)(is_array($paramsOrRequest) ? ($paramsOrRequest['id'] ?? 0) : ($paramsOrRequest ?? $request->get('id', 0)));
        }

        $card = $this->cardRepo->findById($id);

        if (!$card) {
            Response::notFound("Employee ID record #{$id} not found.");
        }

        $user = User::fromArray(SessionManager::getUser());

        // Printing Officer permission check: Can only see Approved, Printed, Collected IDs
        if ($user->isPrintingOfficer() && !in_array($card->current_status, [IdStatus::APPROVED, IdStatus::PRINTED, IdStatus::COLLECTED], true)) {
            Response::forbidden("Printing Officers can only view approved, printed, or collected ID cards.");
        }

        $employee = $this->employeeRepo->findById($card->employee_id);
        $versions = $this->versionRepo->getVersionsForCard($card->id);
        $approval = $this->approvalRepo->findByCardId($card->id);
        $corrections = $this->correctionRepo->getForCard($card->id);
        $pendingCorrection = $this->correctionRepo->getPendingForCard($card->id);
        $printRecord = $this->printRepo->findLatestForCard($card->id);
        $collectionRecord = $this->collectionRepo->findByCardId($card->id);
        $auditLogs = $this->auditRepo->getForCard($card->id);

        // Requested version to display or latest
        $requestedVersionNum = (int)$request->get('v', $card->current_version_number);
        $activeVersion = $this->versionRepo->findByCardAndVersion($card->id, $requestedVersionNum) 
            ?? ($versions[0] ?? null);

        View::render('id_cards/show', [
            'pageTitle' => "ID Details — {$card->employee_name} ({$card->card_reference})",
            'card' => $card,
            'idCard' => $card,
            'employee' => $employee,
            'versions' => $versions,
            'versionList' => $versions,
            'activeVersion' => $activeVersion,
            'currentVersion' => $activeVersion,
            'approval' => $approval,
            'latestApproval' => $approval,
            'corrections' => $corrections,
            'correctionList' => $corrections,
            'pendingCorrection' => $pendingCorrection,
            'printRecord' => $printRecord,
            'latestPrint' => $printRecord,
            'collectionRecord' => $collectionRecord,
            'latestCollection' => $collectionRecord,
            'auditLogs' => $auditLogs,
            'auditEvents' => $auditLogs,
            'currentUser' => $user
        ]);
    }

    public function servePdf(mixed $requestOrId = null, mixed $paramsOrRequest = null): void
    {
        $this->streamPdf($requestOrId, $paramsOrRequest);
    }

    public function streamPdf(mixed $requestOrId = null, mixed $paramsOrRequest = null): void
    {
        if (is_numeric($requestOrId)) {
            $id = (int)$requestOrId;
            $request = ($paramsOrRequest instanceof Request) ? $paramsOrRequest : new Request();
        } else {
            $request = ($requestOrId instanceof Request) ? $requestOrId : new Request();
            $id = (int)(is_array($paramsOrRequest) ? ($paramsOrRequest['id'] ?? 0) : ($paramsOrRequest ?? $request->get('id', 0)));
        }

        $card = $this->cardRepo->findById($id);

        if (!$card) {
            Response::notFound("ID record not found.");
        }

        $user = User::fromArray(SessionManager::getUser());

        if ($user->isPrintingOfficer() && !in_array($card->current_status, [IdStatus::APPROVED, IdStatus::PRINTED, IdStatus::COLLECTED], true)) {
            Response::forbidden("Printing Officers are only permitted to view and print approved ID card PDFs.");
        }

        $versionNum = (int)$request->get('v', $request->get('version', 0));
        if ($versionNum > 0) {
            $version = $this->versionRepo->findByCardAndVersion($card->id, $versionNum);
        } else {
            // Default to approved version if approved/printed, or latest version
            if ($card->approved_version_id) {
                $version = $this->versionRepo->findById($card->approved_version_id);
            } else {
                $version = $this->versionRepo->findByCardAndVersion($card->id, $card->current_version_number);
            }
        }

        if (!$version) {
            Response::notFound("Requested PDF version not found.");
        }

        $fullPath = $this->pdfService->getAbsolutePath($version->file_path);
        if (!file_exists($fullPath)) {
            Response::notFound("Physical PDF file not found in storage.");
        }

        // Verify SHA-256 integrity
        if (!$this->pdfService->verifyIntegrity($version->file_path, $version->file_sha256)) {
            $this->auditService->logSecurity(
                'PDF_INTEGRITY_MISMATCH',
                "Critical: PDF integrity check failed for Card {$card->card_reference} (v{$version->version_number}). Expected {$version->file_sha256}",
                $request->ip(),
                $request->userAgent()
            );
            Response::error("Critical Security Alert: PDF file integrity check failed. The file hash does not match the database record.", 500);
        }

        $download = (bool)$request->get('download', false);
        $filename = "{$card->employee_name}_{$card->card_reference}_v{$version->version_number}.pdf";

        // Audit view/download event
        $this->auditService->logWorkflow(
            $card->id,
            $download ? AuditLog::ACTION_PDF_DOWNLOADED : AuditLog::ACTION_PDF_VIEWED,
            null,
            null,
            $version->version_number,
            "User {$user->name} ({$user->role}) " . ($download ? "downloaded" : "previewed") . " PDF v{$version->version_number} ({$version->original_filename}).",
            $request->ip(),
            $request->userAgent()
        );

        Response::streamFile($fullPath, $filename, 'application/pdf', !$download);
    }

    public function approve(Request $request, array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $currentUser = User::fromArray(SessionManager::getUser());

        $checklist = [
            'photo' => $request->post('check_photo'),
            'name' => $request->post('check_name'),
            'staff_no' => $request->post('check_staff_no'),
            'department' => $request->post('check_department'),
            'designation' => $request->post('check_designation'),
            'layout' => $request->post('check_layout')
        ];

        $notes = trim((string)$request->post('approval_notes', ''));

        try {
            $this->workflowService->approveId(
                $id,
                $checklist,
                $notes ?: null,
                $currentUser,
                $request->ip(),
                $request->userAgent()
            );

            SessionManager::flash('success', "Employee ID successfully approved by {$currentUser->name}! Status is now APPROVED.");
            Response::redirect("/id-cards/{$id}");
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            Response::redirect("/id-cards/{$id}");
        }
    }

    public function requestCorrection(Request $request, array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $currentUser = User::fromArray(SessionManager::getUser());
        $reason = trim((string)$request->post('correction_reason', ''));

        try {
            $this->workflowService->requestCorrection(
                $id,
                $reason,
                $currentUser,
                $request->ip(),
                $request->userAgent()
            );

            SessionManager::flash('warning', "Correction request submitted. Status updated to CORRECTION REQUESTED.");
            Response::redirect("/id-cards/{$id}");
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            Response::redirect("/id-cards/{$id}");
        }
    }

    public function reupload(Request $request, array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $currentUser = User::fromArray(SessionManager::getUser());
        $file = $request->file('corrected_pdf');

        if (empty($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            SessionManager::flash('error', 'Please select a corrected PDF file to upload.');
            Response::redirect("/id-cards/{$id}");
        }

        try {
            $updatedCard = $this->workflowService->uploadCorrectedDesign(
                $id,
                $file,
                $currentUser,
                $request->ip(),
                $request->userAgent()
            );

            SessionManager::flash('success', "Corrected design uploaded as Version {$updatedCard->current_version_number}. Status updated to PENDING HR APPROVAL.");
            Response::redirect("/id-cards/{$id}");
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            Response::redirect("/id-cards/{$id}");
        }
    }

    public function markPrinted(Request $request, array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $currentUser = User::fromArray(SessionManager::getUser());
        $notes = trim((string)$request->post('print_notes', ''));

        try {
            $this->workflowService->markAsPrinted(
                $id,
                $notes ?: null,
                $currentUser,
                $request->ip(),
                $request->userAgent()
            );

            SessionManager::flash('success', "ID card confirmed as PRINTED! HR has been notified for collection.");
            Response::redirect("/id-cards/{$id}");
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            Response::redirect("/id-cards/{$id}");
        }
    }

    public function markCollected(Request $request, array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $currentUser = User::fromArray(SessionManager::getUser());

        $recipientName = trim((string)$request->post('collected_by_name', ''));
        $relationship = trim((string)$request->post('collected_by_relationship', 'SELF'));
        $contact = trim((string)$request->post('recipient_contact', ''));
        $ref = trim((string)$request->post('collection_reference', ''));
        $notes = trim((string)$request->post('collection_notes', ''));

        try {
            $this->workflowService->markAsCollected(
                $id,
                $recipientName,
                $relationship,
                $contact ?: null,
                $ref ?: null,
                $notes ?: null,
                $currentUser,
                $request->ip(),
                $request->userAgent()
            );

            SessionManager::flash('success', "Employee ID card marked as COLLECTED. Complete lifecycle is now archived.");
            Response::redirect("/id-cards/{$id}");
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            Response::redirect("/id-cards/{$id}");
        }
    }
}
