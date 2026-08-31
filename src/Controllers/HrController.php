<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Controllers;

use Mengo\IdApproval\Models\IdStatus;
use Mengo\IdApproval\Repositories\EmployeeRepository;
use Mengo\IdApproval\Repositories\IdCardRepository;
use Mengo\IdApproval\Repositories\UserRepository;
use Mengo\IdApproval\Services\ReportService;
use Mengo\IdApproval\Support\Request;
use Mengo\IdApproval\Support\View;

class HrController
{
    private IdCardRepository $cardRepo;
    private EmployeeRepository $employeeRepo;
    private UserRepository $userRepo;
    private ReportService $reportService;

    public function __construct()
    {
        $this->cardRepo = new IdCardRepository();
        $this->employeeRepo = new EmployeeRepository();
        $this->userRepo = new UserRepository();
        $this->reportService = new ReportService();
    }

    public function dashboard(Request $request): void
    {
        $summary = $this->reportService->getExecutiveSummary();

        // Pending approval queue (Top 10)
        $pendingQueue = $this->cardRepo->getFiltered([
            'status' => IdStatus::PENDING_HR_APPROVAL
        ], 10, 0);

        // Overdue (>24h)
        $overdueList = $this->cardRepo->getOverduePendingApprovals(24);

        // Awaiting collection (Top 5)
        $awaitingCollection = $this->cardRepo->getFiltered([
            'status' => IdStatus::PRINTED
        ], 5, 0);

        $workflowService = new \Mengo\IdApproval\Services\WorkflowService();
        $smartAlerts = $workflowService->getSmartFollowUpAlerts();

        View::render('hr/dashboard', [
            'pageTitle' => 'HR Approval Dashboard — Mengo Hospital ID System',
            'summary' => $summary,
            'smartAlerts' => $smartAlerts,
            'pendingQueue' => $pendingQueue,
            'overdueList' => $overdueList,
            'awaitingCollection' => $awaitingCollection
        ]);
    }

    public function pendingApprovals(Request $request): void
    {
        $page = max(1, (int)$request->get('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $filters = [
            'status' => IdStatus::PENDING_HR_APPROVAL,
            'department_id' => $request->get('department_id'),
            'search' => $request->get('search')
        ];

        $cards = $this->cardRepo->getFiltered($filters, $limit, $offset);
        $total = $this->cardRepo->countFiltered($filters);
        $totalPages = ceil($total / $limit);
        $departments = $this->employeeRepo->getDepartments();

        View::render('hr/pending_approvals', [
            'pageTitle' => 'Pending HR Approvals — Mengo Hospital ID System',
            'cards' => $cards,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'departments' => $departments,
            'filters' => $filters
        ]);
    }

    public function allIds(Request $request): void
    {
        $page = max(1, (int)$request->get('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $filters = [
            'status' => $request->get('status'),
            'department_id' => $request->get('department_id'),
            'search' => $request->get('search'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to')
        ];

        $cards = $this->cardRepo->getFiltered($filters, $limit, $offset);
        $total = $this->cardRepo->countFiltered($filters);
        $totalPages = ceil($total / $limit);
        $departments = $this->employeeRepo->getDepartments();

        View::render('hr/all_ids', [
            'pageTitle' => 'Employee ID Directory — Mengo Hospital ID System',
            'cards' => $cards,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'departments' => $departments,
            'filters' => $filters
        ]);
    }

    public function corrections(Request $request): void
    {
        $page = max(1, (int)$request->get('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $filters = [
            'status' => IdStatus::CORRECTION_REQUESTED,
            'department_id' => $request->get('department_id'),
            'search' => $request->get('search')
        ];

        $cards = $this->cardRepo->getFiltered($filters, $limit, $offset);
        $total = $this->cardRepo->countFiltered($filters);
        $totalPages = ceil($total / $limit);
        $departments = $this->employeeRepo->getDepartments();

        View::render('hr/corrections', [
            'pageTitle' => 'Corrections Requested — Mengo Hospital ID System',
            'cards' => $cards,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'departments' => $departments,
            'filters' => $filters
        ]);
    }

    public function printingStatus(Request $request): void
    {
        $readyCards = $this->cardRepo->getFiltered(['status' => IdStatus::APPROVED], 50, 0);
        $printedCards = $this->cardRepo->getFiltered(['status' => IdStatus::PRINTED], 50, 0);

        View::render('hr/printing_status', [
            'pageTitle' => 'Card Printing & Production Status — Mengo Hospital ID System',
            'readyCards' => $readyCards,
            'printedCards' => $printedCards
        ]);
    }

    public function collection(Request $request): void
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

        View::render('hr/collection', [
            'pageTitle' => 'Employee ID Collection Management — Mengo Hospital ID System',
            'cards' => $cards,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'departments' => $departments,
            'filters' => $filters
        ]);
    }

    public function reviewQueue(Request $request): void
    {
        $cards = $this->cardRepo->getFiltered([
            'status' => IdStatus::IMPORT_REVIEW_REQUIRED
        ], 100, 0);

        View::render('hr/review_queue', [
            'pageTitle' => 'Import Review Required Queue — Mengo Hospital ID System',
            'cards' => $cards
        ]);
    }

    public function approve(Request $request): void
    {
        $id = (int)$request->post('id_card_id', 0);
        $currentUser = \Mengo\IdApproval\Models\User::fromArray(\Mengo\IdApproval\Security\SessionManager::getUser());
        $checklist = [
            'photo' => $request->post('check_photo', 1),
            'name' => $request->post('check_name', 1),
            'staff_no' => $request->post('check_staff_no', 1),
            'department' => $request->post('check_department', 1),
            'designation' => $request->post('check_designation', 1),
            'layout' => $request->post('check_layout', 1)
        ];
        $notes = trim((string)$request->post('approval_notes', ''));

        try {
            $workflowService = new \Mengo\IdApproval\Services\WorkflowService();
            $workflowService->approveId(
                $id,
                $checklist,
                $notes ?: null,
                $currentUser,
                $request->ip(),
                $request->userAgent()
            );

            \Mengo\IdApproval\Security\SessionManager::flash('success', "Employee ID successfully approved by {$currentUser->name}! Status is now APPROVED.");
        } catch (\Throwable $e) {
            \Mengo\IdApproval\Security\SessionManager::flash('error', $e->getMessage());
        }

        \Mengo\IdApproval\Support\Response::redirect("/id-cards/{$id}");
    }

    public function requestCorrection(Request $request): void
    {
        $id = (int)$request->post('id_card_id', 0);
        $currentUser = \Mengo\IdApproval\Models\User::fromArray(\Mengo\IdApproval\Security\SessionManager::getUser());
        $reason = trim((string)($request->post('correction_remarks') ?? $request->post('correction_reason', '')));

        try {
            $workflowService = new \Mengo\IdApproval\Services\WorkflowService();
            $workflowService->requestCorrection(
                $id,
                $reason,
                $currentUser,
                $request->ip(),
                $request->userAgent()
            );

            \Mengo\IdApproval\Security\SessionManager::flash('warning', "Correction request submitted. Status updated to CORRECTION REQUESTED.");
        } catch (\Throwable $e) {
            \Mengo\IdApproval\Security\SessionManager::flash('error', $e->getMessage());
        }

        \Mengo\IdApproval\Support\Response::redirect("/id-cards/{$id}");
    }

    public function markCollected(Request $request): void
    {
        $id = (int)$request->post('id_card_id', 0);
        $currentUser = \Mengo\IdApproval\Models\User::fromArray(\Mengo\IdApproval\Security\SessionManager::getUser());
        $recipientName = trim((string)$request->post('collected_by_name', ''));
        $relationship = trim((string)$request->post('collected_by_relationship', 'SELF'));
        $contact = trim((string)$request->post('recipient_contact', ''));
        $ref = trim((string)$request->post('collection_reference', ''));
        $notes = trim((string)$request->post('collection_notes', ''));

        try {
            $workflowService = new \Mengo\IdApproval\Services\WorkflowService();
            $workflowService->markAsCollected(
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

            \Mengo\IdApproval\Security\SessionManager::flash('success', "Employee ID card marked as COLLECTED. Complete lifecycle is now archived.");
        } catch (\Throwable $e) {
            \Mengo\IdApproval\Security\SessionManager::flash('error', $e->getMessage());
        }

        \Mengo\IdApproval\Support\Response::redirect("/id-cards/{$id}");
    }
}
