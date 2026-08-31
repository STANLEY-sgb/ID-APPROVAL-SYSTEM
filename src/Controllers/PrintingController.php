<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Controllers;

use Mengo\IdApproval\Models\IdStatus;
use Mengo\IdApproval\Models\PrintBatch;
use Mengo\IdApproval\Models\User;
use Mengo\IdApproval\Repositories\AuditLogRepository;
use Mengo\IdApproval\Repositories\EmployeeRepository;
use Mengo\IdApproval\Repositories\IdCardRepository;
use Mengo\IdApproval\Repositories\PrintBatchRepository;
use Mengo\IdApproval\Repositories\PrintRecordRepository;
use Mengo\IdApproval\Security\SessionManager;
use Mengo\IdApproval\Services\PdfMergeService;
use Mengo\IdApproval\Services\ReportService;
use Mengo\IdApproval\Services\WorkflowService;
use Mengo\IdApproval\Support\Request;
use Mengo\IdApproval\Support\Response;
use Mengo\IdApproval\Support\View;

class PrintingController
{
    private IdCardRepository $cardRepo;
    private PrintRecordRepository $printRepo;
    private PrintBatchRepository $batchRepo;
    private EmployeeRepository $employeeRepo;
    private ReportService $reportService;
    private WorkflowService $workflowService;
    private AuditLogRepository $auditRepo;

    public function __construct()
    {
        $this->cardRepo = new IdCardRepository();
        $this->printRepo = new PrintRecordRepository();
        $this->batchRepo = new PrintBatchRepository();
        $this->employeeRepo = new EmployeeRepository();
        $this->reportService = new ReportService();
        $this->workflowService = new WorkflowService();
        $this->auditRepo = new AuditLogRepository();
    }

    public function dashboard(Request $request): void
    {
        $summary = $this->reportService->getExecutiveSummary();

        // Main Ready to Print queue (Top 15)
        $readyQueue = $this->cardRepo->getFiltered([
            'status' => IdStatus::APPROVED
        ], 15, 0);

        // Recently Printed
        $recentlyPrinted = $this->cardRepo->getFiltered([
            'status' => IdStatus::PRINTED
        ], 5, 0);

        // Recent Print Batches
        $recentBatches = $this->batchRepo->getRecent(5, 0);

        View::render('printing/dashboard', [
            'pageTitle' => 'Production Dashboard — Mengo Hospital ID System',
            'summary' => $summary,
            'readyQueue' => $readyQueue,
            'recentlyPrinted' => $recentlyPrinted,
            'recentBatches' => $recentBatches
        ]);
    }

    public function readyToPrint(Request $request): void
    {
        $page = max(1, (int)$request->get('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $filters = [
            'status' => IdStatus::APPROVED,
            'department_id' => $request->get('department_id'),
            'search' => $request->get('search')
        ];

        $cards = $this->cardRepo->getFiltered($filters, $limit, $offset);
        $total = $this->cardRepo->countFiltered($filters);
        $totalPages = ceil($total / $limit);
        $departments = $this->employeeRepo->getDepartments();

        View::render('printing/ready_to_print', [
            'pageTitle' => 'Ready for Printing — Mengo Hospital ID System',
            'cards' => $cards,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'departments' => $departments,
            'filters' => $filters
        ]);
    }

    public function printedIds(Request $request): void
    {
        $page = max(1, (int)$request->get('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $filters = [
            'status' => IdStatus::PRINTED,
            'department_id' => $request->get('department_id'),
            'search' => $request->get('search')
        ];

        $cards = $this->cardRepo->getFiltered($filters, $limit, $offset);
        $total = $this->cardRepo->countFiltered($filters);
        $totalPages = ceil($total / $limit);
        $departments = $this->employeeRepo->getDepartments();

        View::render('printing/printed_ids', [
            'pageTitle' => 'Printed IDs — Mengo Hospital ID System',
            'cards' => $cards,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'departments' => $departments,
            'filters' => $filters
        ]);
    }

    public function awaitingCollection(Request $request): void
    {
        $cards = $this->cardRepo->getFiltered([
            'status' => IdStatus::PRINTED
        ], 100, 0);

        View::render('printing/awaiting_collection', [
            'pageTitle' => 'Awaiting Collection — Mengo Hospital ID System',
            'cards' => $cards
        ]);
    }

    public function markPrinted(Request $request): void
    {
        $id = (int)$request->post('id_card_id', 0);
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
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }

        Response::redirect("/id-cards/{$id}");
    }

    /**
     * AJAX Pre-merge Validation Endpoint
     */
    public function validateBatch(Request $request): void
    {
        $currentUser = User::fromArray(SessionManager::getUser());
        if ($request->post('select_all_matching') === '1') {
            $filters = [
                'status' => IdStatus::APPROVED,
                'department_id' => $request->post('department_id'),
                'search' => $request->post('search')
            ];
            $cardIds = $this->cardRepo->getIdsFiltered($filters);
        } else {
            $cardIds = (array)($request->post('selected_card_ids') ?? $request->post('card_ids') ?? []);
        }

        try {
            $mergeService = new PdfMergeService($this->cardRepo, new \Mengo\IdApproval\Repositories\IdVersionRepository());
            $result = $mergeService->validateDocuments($cardIds);

            // If requested, also create the draft print_batches record
            $createRecord = (bool)$request->post('create_batch', true);
            $batchId = null;
            $batchRef = null;

            if ($createRecord && $result['valid_count'] > 0) {
                $prep = $this->workflowService->validateAndCreatePrintBatch(
                    $cardIds,
                    $currentUser,
                    ['orientation' => $request->post('orientation', 'ORIGINAL')],
                    $request->ip(),
                    $request->userAgent()
                );
                $batchId = $prep['batch_id'];
                $batchRef = $prep['batch_reference'];
            }

            Response::json([
                'success' => true,
                'batch_id' => $batchId,
                'batch_reference' => $batchRef,
                'validation' => $result
            ]);
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Execute server-side PDF Merge
     */
    public function mergeBatch(Request $request): void
    {
        $currentUser = User::fromArray(SessionManager::getUser());
        $batchId = (int)$request->post('batch_id', 0);
        $orientation = (string)$request->post('orientation', 'ORIGINAL');

        try {
            if (!$batchId) {
                if ($request->post('select_all_matching') === '1') {
                    $filters = [
                        'status' => IdStatus::APPROVED,
                        'department_id' => $request->post('department_id'),
                        'search' => $request->post('search')
                    ];
                    $cardIds = $this->cardRepo->getIdsFiltered($filters);
                } else {
                    $cardIds = (array)($request->post('selected_card_ids') ?? []);
                }
                $prep = $this->workflowService->validateAndCreatePrintBatch(
                    $cardIds,
                    $currentUser,
                    ['orientation' => $orientation],
                    $request->ip(),
                    $request->userAgent()
                );
                $batchId = $prep['batch_id'];
            }

            $result = $this->workflowService->executeBatchMerge(
                $batchId,
                $currentUser,
                $orientation,
                $request->ip(),
                $request->userAgent()
            );

            Response::json([
                'success' => true,
                'batch_id' => $batchId,
                'batch_reference' => $result['batch_reference'],
                'page_count' => $result['page_count'],
                'file_size' => $result['file_size'],
                'file_size_formatted' => round($result['file_size'] / 1024 / 1024, 2) . ' MB',
                'preview_url' => "/printing/batches/{$batchId}/preview",
                'download_url' => "/printing/batches/{$batchId}/download"
            ]);
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Preview Merged Batch PDF
     */
    public function previewBatch(int $id, Request $request): void
    {
        $batch = $this->batchRepo->findById($id);

        if (!$batch || empty($batch->output_path) || !file_exists($batch->output_path)) {
            Response::error("Batch PDF not found or has expired.", 404);
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($batch->output_path) . '"');
        header('Content-Length: ' . filesize($batch->output_path));
        readfile($batch->output_path);
        exit;
    }

    /**
     * Secure Download for Merged Batch PDF
     */
    public function downloadBatch(int $id, Request $request): void
    {
        $batch = $this->batchRepo->findById($id);

        if (!$batch || empty($batch->output_path) || !file_exists($batch->output_path)) {
            SessionManager::flash('error', 'Batch print document not found or expired.');
            Response::redirect('/printing/batches');
            return;
        }

        $this->batchRepo->incrementDownloadCount($id);

        $currentUser = User::fromArray(SessionManager::getUser());
        $this->auditRepo->create([
            'user_id' => $currentUser->id,
            'user_name' => $currentUser->name,
            'user_role' => $currentUser->role,
            'action' => 'BATCH_DOWNLOADED',
            'details' => "Printing Officer {$currentUser->name} downloaded print batch '{$batch->batch_reference}' ({$batch->page_count} pages).",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($batch->output_path) . '"');
        header('Content-Length: ' . filesize($batch->output_path));
        readfile($batch->output_path);
        exit;
    }

    /**
     * Confirm Physical Printing of Batch
     */
    public function confirmBatchPrint(Request $request): void
    {
        $batchId = (int)$request->post('batch_id', 0);
        $confirmedIds = (array)($request->post('confirmed_card_ids') ?? $request->post('selected_card_ids') ?? []);
        $notes = trim((string)$request->post('print_notes', ''));
        $currentUser = User::fromArray(SessionManager::getUser());

        try {
            $result = $this->workflowService->confirmPhysicalPrint(
                $batchId,
                $confirmedIds,
                $notes ?: null,
                $currentUser,
                $request->ip(),
                $request->userAgent()
            );

            SessionManager::flash(
                'success',
                "Physical printing confirmed for batch {$result['batch_reference']}! {$result['total_printed']} employee ID cards marked as PRINTED."
            );
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }

        Response::redirect('/printing/ready');
    }

    /**
     * Batch History Page
     */
    public function batchHistory(Request $request): void
    {
        $page = max(1, (int)$request->get('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $batches = $this->batchRepo->getRecent($limit, $offset);
        $total = $this->batchRepo->countAll();
        $totalPages = ceil($total / $limit);

        View::render('printing/batch_history', [
            'pageTitle' => 'Print Batch History — Mengo Hospital ID System',
            'batches' => $batches,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages
        ]);
    }

    /**
     * Batch Detail & Manifest Page
     */
    public function showBatch(int $id, Request $request): void
    {
        $batch = $this->batchRepo->findById($id);

        if (!$batch) {
            SessionManager::flash('error', 'Print batch not found.');
            Response::redirect('/printing/batches');
            return;
        }

        $items = $this->batchRepo->getItems($id);
        $auditLogs = $this->auditRepo->getFiltered(['search' => $batch->batch_reference], 50, 0);

        View::render('printing/batch_show', [
            'pageTitle' => "Batch {$batch->batch_reference} — Mengo Hospital ID System",
            'batch' => $batch,
            'items' => $items,
            'auditLogs' => $auditLogs
        ]);
    }

    public function bulkPrint(Request $request): void
    {
        $cardIds = (array)($request->post('selected_card_ids') ?? $request->post('card_ids') ?? []);
        $batchNotes = trim((string)$request->post('batch_notes', ''));
        $currentUser = User::fromArray(SessionManager::getUser());

        try {
            $result = $this->workflowService->bulkPrint(
                $cardIds,
                $batchNotes ?: null,
                $currentUser,
                $request->ip(),
                $request->userAgent()
            );

            SessionManager::flash(
                'success',
                "Bulk print batch {$result['batch_reference']} completed successfully! {$result['total_printed']} employee ID card(s) marked as PRINTED."
            );
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }

        Response::redirect("/printing/ready");
    }
}
