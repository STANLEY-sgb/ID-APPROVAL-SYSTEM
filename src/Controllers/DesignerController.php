<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Controllers;

use Mengo\IdApproval\Models\AuditLog;
use Mengo\IdApproval\Models\IdStatus;
use Mengo\IdApproval\Models\User;
use Mengo\IdApproval\Repositories\AuditLogRepository;
use Mengo\IdApproval\Repositories\EmployeeRepository;
use Mengo\IdApproval\Repositories\IdCardRepository;
use Mengo\IdApproval\Repositories\UserRepository;
use Mengo\IdApproval\Security\SessionManager;
use Mengo\IdApproval\Services\WorkflowService;
use Mengo\IdApproval\Support\Request;
use Mengo\IdApproval\Support\Response;
use Mengo\IdApproval\Support\View;

class DesignerController
{
    private IdCardRepository $cardRepo;
    private EmployeeRepository $employeeRepo;
    private UserRepository $userRepo;
    private AuditLogRepository $auditRepo;
    private WorkflowService $workflowService;

    public function __construct()
    {
        $this->cardRepo = new IdCardRepository();
        $this->employeeRepo = new EmployeeRepository();
        $this->userRepo = new UserRepository();
        $this->auditRepo = new AuditLogRepository();
        $this->workflowService = new WorkflowService();
    }

    public function dashboard(Request $request): void
    {
        $userId = SessionManager::getUserId();
        $statusCounts = $this->cardRepo->getCountsByStatus();

        // Corrections requiring action
        $corrections = $this->cardRepo->getFiltered([
            'status' => IdStatus::CORRECTION_REQUESTED
        ], 10, 0);

        // Recent activity for designer
        $recentActivity = $this->auditRepo->getFiltered([
            'limit' => 10
        ], 10, 0);

        // Recent IDs
        $recentIds = $this->cardRepo->getFiltered([], 10, 0);

        View::render('designer/dashboard', [
            'pageTitle' => 'Designer Dashboard — Mengo Hospital ID System',
            'statusCounts' => $statusCounts,
            'corrections' => $corrections,
            'recentActivity' => $recentActivity,
            'recentIds' => $recentIds,
        ]);
    }

    public function myIds(Request $request): void
    {
        $page = max(1, (int)$request->get('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $filters = [
            'status' => $request->get('status'),
            'department_id' => $request->get('department_id'),
            'search' => $request->get('search')
        ];

        $cards = $this->cardRepo->getFiltered($filters, $limit, $offset);
        $total = $this->cardRepo->countFiltered($filters);
        $totalPages = ceil($total / $limit);
        $departments = $this->employeeRepo->getDepartments();

        View::render('designer/my_ids', [
            'pageTitle' => 'My ID Designs — Mengo Hospital ID System',
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
        $cards = $this->cardRepo->getFiltered([
            'status' => IdStatus::CORRECTION_REQUESTED
        ], 100, 0);

        View::render('designer/corrections', [
            'pageTitle' => 'Action Required: Corrections — Mengo Hospital ID System',
            'cards' => $cards
        ]);
    }

    public function showCreate(Request $request): void
    {
        $departments = $this->employeeRepo->getDepartments();
        $employees = $this->employeeRepo->all(200, 0);

        View::render('designer/create_id', [
            'pageTitle' => 'Create ID Card Record — Mengo Hospital ID System',
            'departments' => $departments,
            'employees' => $employees
        ]);
    }

    public function create(Request $request): void
    {
        $currentUser = User::fromArray(SessionManager::getUser());

        $mode = $request->post('employee_mode', 'new'); // 'new' or 'existing'
        $file = $request->file('id_pdf');

        if (empty($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            SessionManager::flash('error', 'Please select a high-quality ID card PDF to upload.');
            Response::redirect('/designer/create');
        }

        try {
            $fullName = trim((string)$request->post('full_name', ''));
            if (empty($fullName)) {
                $employeeId = (int)$request->post('existing_employee_id', 0);
                if (!$employeeId) {
                    throw new \RuntimeException("Employee full name is required.");
                }
            } else {
                // Check if employee with same name already exists
                $existing = $this->employeeRepo->findByName($fullName);
                if ($existing) {
                    $employeeId = $existing->id;
                } else {
                    $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $fullName) ?: 'EMP', 0, 3));
                    $autoStaffId = 'MH-' . $prefix . '-' . date('ym') . str_pad((string)random_int(100, 999), 3, '0', STR_PAD_LEFT);
                    $deptId = (int)($request->post('department_id') ?: 1);
                    $designation = trim((string)$request->post('designation', 'Hospital Staff'));

                    $employeeId = $this->employeeRepo->create([
                        'staff_id' => $autoStaffId,
                        'full_name' => $fullName,
                        'department_id' => $deptId,
                        'designation' => $designation,
                        'status' => 'ACTIVE'
                    ]);
                }
            }

            $cardRef = trim((string)$request->post('card_reference', ''));

            $card = $this->workflowService->uploadInitialDesign(
                $employeeId,
                $file,
                $currentUser,
                $cardRef ?: null,
                $request->ip(),
                $request->userAgent()
            );

            SessionManager::flash('success', "ID card uploaded successfully for {$card->employee_name} ({$card->card_reference}). Status: PENDING HR APPROVAL.");
            Response::redirect("/id-cards/{$card->id}");
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            Response::redirect('/designer/create');
        }
    }

    public function createForm(Request $request): void
    {
        $this->showCreate($request);
    }

    public function reupload(Request $request): void
    {
        $id = (int)$request->post('id_card_id', 0);
        $currentUser = User::fromArray(SessionManager::getUser());
        $file = $request->file('id_pdf') ?? $request->file('corrected_pdf');

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
}
