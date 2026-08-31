<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Controllers;

use Mengo\IdApproval\Models\AuditLog;
use Mengo\IdApproval\Models\Role;
use Mengo\IdApproval\Security\SessionManager;
use Mengo\IdApproval\Services\AuditService;
use Mengo\IdApproval\Services\ReportService;
use Mengo\IdApproval\Support\Request;
use Mengo\IdApproval\Support\Response;
use Mengo\IdApproval\Support\View;

class ReportController
{
    private ReportService $reportService;
    private AuditService  $auditService;

    public function __construct()
    {
        $this->reportService = new ReportService();
        $this->auditService  = new AuditService();
    }

    /**
     * Main reports page.
     *
     * Accepts:
     *   GET ?period=all_time|today|last_7_days|last_30_days|custom
     *       &date_from=YYYY-MM-DD
     *       &date_to=YYYY-MM-DD
     *       &status=PENDING_HR_APPROVAL|...
     *       &search=name
     *       &page=1
     *
     * Returns JSON when:
     *   - X-Requested-With: XMLHttpRequest (AJAX)
     *   - &format=json
     */
    public function index(Request $request): void
    {
        $user = SessionManager::getUser();
        $role = $user['role'] ?? '';

        // RBAC: Only HR_MANAGER and ADMINISTRATOR may access reports.
        if (!in_array($role, [Role::HR_MANAGER, Role::ADMINISTRATOR], true)) {
            throw new \Mengo\IdApproval\Support\ForbiddenException(
                'You do not have permission to access System Reports.'
            );
        }

        $filters = [
            'period'    => $request->get('period',    'all_time'),
            'date_from' => $request->get('date_from'),
            'date_to'   => $request->get('date_to'),
            'status'    => $request->get('status'),
            'search'    => $request->get('search'),
        ];

        // ── AJAX: Live search / refresh ───────────────────────────────
        if ($request->isAjax() || $request->get('format') === 'json') {
            try {
                $query    = trim((string)$request->get('search', ''));
                $status   = $request->get('status');
                $dateFrom = null;
                $dateTo   = null;

                if (!empty($filters['period']) && $filters['period'] !== 'all_time') {
                    $resolved = $this->reportService->resolveDateFilter($filters);
                    $dateFrom = $resolved['date_from'];
                    $dateTo   = $resolved['date_to'];
                }

                // Search request
                if ($query !== '' || !empty($status) || !empty($dateFrom)) {
                    $page   = max(1, (int)$request->get('page', 1));
                    $result = $this->reportService->search($query, $status, $dateFrom, $dateTo, $page, 20);
                    Response::json(['ok' => true, 'search' => $result]);
                    return;
                }

                // KPI refresh request
                $data = $this->reportService->getDashboardData($filters);
                Response::json(['ok' => true, 'kpis' => $data['kpis'], 'generated_at' => $data['generated_at']]);
            } catch (\Throwable $e) {
                error_log('[MENGO-REPORTS] ' . $e->getMessage());
                Response::json(['ok' => false, 'error' => 'Unable to load report data.'], 500);
            }
            return;
        }

        // ── Full page render ──────────────────────────────────────────
        try {
            $data = $this->reportService->getDashboardData($filters);
        } catch (\Throwable $e) {
            error_log('[MENGO-REPORTS] ' . $e->getMessage());
            // Render with zero-state so page never shows a crash
            $data = [
                'filters'        => $this->reportService->resolveDateFilter($filters),
                'kpis'           => [
                    'total_ids' => 0, 'pending_approval' => 0, 'correction_requested' => 0,
                    'approved_ready' => 0, 'printed' => 0, 'collected' => 0,
                ],
                'needsAttention' => ['total_alerts' => 0, 'overdue_approvals' => [], 'printing_delays' => [],
                                     'collection_delays' => [], 'stale_corrections' => []],
                'recentActivity' => [],
                'systemHealth'   => ['in_app_operational' => false, 'email_enabled' => false,
                                     'email_operational' => false, 'email_failure' => false],
                'generated_at'   => date('d F Y \a\t H:i:s'),
                'error'          => 'Report data temporarily unavailable. Please try again.',
            ];
        }

        // Audit: log that a report was viewed (once per page load — not per refresh)
        // We log only when it's not an AJAX refresh to avoid noise.
        try {
            $userName = $user['name'] ?? 'Unknown';
            $userRole = $role;
            $this->auditService->log([
                'user_id'   => $user['id'] ?? null,
                'user_name' => $userName,
                'user_role' => $userRole,
                'action'    => 'REPORT_VIEWED',
                'details'   => "System Reports page viewed by {$userName} ({$userRole}).",
            ]);
        } catch (\Throwable $e) {
            // Audit failure must never break report rendering
            error_log('[MENGO-AUDIT] REPORT_VIEWED audit failed: ' . $e->getMessage());
        }

        View::render('reports/index', [
            'pageTitle'      => 'System Reports — Mengo Hospital ID System',
            'data'           => $data,
            'kpis'           => $data['kpis'],
            'needsAttention' => $data['needsAttention'],
            'recentActivity' => $data['recentActivity'],
            'systemHealth'   => $data['systemHealth'],
            'filters'        => $data['filters'],
            'generatedAt'    => $data['generated_at'],
            'error'          => $data['error'] ?? null,
        ]);
    }

    /**
     * CSV Export.
     *
     * Only ADMINISTRATOR and HR_MANAGER may export.
     * Generates an audit entry with id_card_id = NULL (no specific card).
     */
    public function exportCsv(Request $request): void
    {
        $user = SessionManager::getUser();
        $role = $user['role'] ?? '';

        if (!in_array($role, [Role::HR_MANAGER, Role::ADMINISTRATOR], true)) {
            throw new \Mengo\IdApproval\Support\ForbiddenException(
                'Only HR Managers and Administrators may export reports.'
            );
        }

        $filters = [
            'period'    => $request->get('period',    'all_time'),
            'status'    => $request->get('status'),
            'date_from' => $request->get('date_from'),
            'date_to'   => $request->get('date_to'),
            'search'    => $request->get('search'),
        ];

        try {
            $csv = $this->reportService->exportCsv($filters);
        } catch (\Throwable $e) {
            error_log('[MENGO-REPORTS-EXPORT] ' . $e->getMessage());
            http_response_code(500);
            echo 'Export failed. Please try again.';
            exit;
        }

        // Compliance audit — id_card_id = NULL (system-level action, no specific card)
        try {
            $userName = $user['name'] ?? 'System Administrator';
            $userRole = $role;
            $this->auditService->logWorkflow(
                null,                           // id_card_id: NULL — not card-specific
                AuditLog::ACTION_DATA_EXPORTED,
                null,
                null,
                null,
                "User {$userName} ({$userRole}) exported ID Card records to CSV. Filters: " . json_encode($filters),
                $request->ip(),
                $request->userAgent()
            );
        } catch (\Throwable $e) {
            error_log('[MENGO-AUDIT] DATA_EXPORTED audit failed: ' . $e->getMessage());
        }

        $filterSuffix = !empty($filters['status']) ? '_' . strtolower((string)$filters['status']) : '';
        $filename = 'mengo_employee_ids' . $filterSuffix . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $csv;
        exit;
    }

    /**
     * Return the resolved date filter for use by the AJAX polling endpoint.
     * (Used internally by index() when handling AJAX search requests.)
     */
    public function resolveDateFilter(array $params): array
    {
        return $this->reportService->resolveDateFilter($params);
    }
}
