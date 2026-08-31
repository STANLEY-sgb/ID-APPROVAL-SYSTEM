<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Repositories;

use Mengo\IdApproval\Models\IdStatus;
use Mengo\IdApproval\Support\Database;
use PDO;

class ReportRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    // =====================================================================
    // PRIMARY KPI QUERIES — Authoritative single-source-of-truth counts
    // All counts are derived directly from id_cards.current_status
    // =====================================================================

    /**
     * Total ID records currently in the system.
     */
    public function getTotalIds(?string $dateFrom = null, ?string $dateTo = null): int
    {
        [$sql, $params] = $this->buildDateWhere('c.created_at', $dateFrom, $dateTo);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM id_cards c WHERE 1=1{$sql}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * IDs currently waiting for HR approval.
     */
    public function getPendingApprovalCount(?string $dateFrom = null, ?string $dateTo = null): int
    {
        [$sql, $params] = $this->buildDateWhere('c.created_at', $dateFrom, $dateTo);
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM id_cards c WHERE c.current_status = ?" . $sql
        );
        $stmt->execute([IdStatus::PENDING_HR_APPROVAL, ...$params]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * IDs returned to the designer for correction.
     */
    public function getCorrectionRequestedCount(?string $dateFrom = null, ?string $dateTo = null): int
    {
        [$sql, $params] = $this->buildDateWhere('c.created_at', $dateFrom, $dateTo);
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM id_cards c WHERE c.current_status = ?" . $sql
        );
        $stmt->execute([IdStatus::CORRECTION_REQUESTED, ...$params]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * IDs approved by HR and ready for printing (status = APPROVED).
     */
    public function getApprovedCount(?string $dateFrom = null, ?string $dateTo = null): int
    {
        [$sql, $params] = $this->buildDateWhere('c.created_at', $dateFrom, $dateTo);
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM id_cards c WHERE c.current_status = ?" . $sql
        );
        $stmt->execute([IdStatus::APPROVED, ...$params]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * IDs that have physically been printed (status = PRINTED).
     */
    public function getPrintedCount(?string $dateFrom = null, ?string $dateTo = null): int
    {
        [$sql, $params] = $this->buildDateWhere('c.created_at', $dateFrom, $dateTo);
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM id_cards c WHERE c.current_status = ?" . $sql
        );
        $stmt->execute([IdStatus::PRINTED, ...$params]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * IDs already handed over to employees (status = COLLECTED).
     */
    public function getCollectedCount(?string $dateFrom = null, ?string $dateTo = null): int
    {
        [$sql, $params] = $this->buildDateWhere('c.created_at', $dateFrom, $dateTo);
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM id_cards c WHERE c.current_status = ?" . $sql
        );
        $stmt->execute([IdStatus::COLLECTED, ...$params]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Consolidated KPI query — all 6 counters in a single SQL pass.
     * This is the primary method used by the dashboard.
     */
    public function getAllKpis(?string $dateFrom = null, ?string $dateTo = null): array
    {
        [$dateWhere, $params] = $this->buildDateWhere('c.created_at', $dateFrom, $dateTo);

        $sql = "
            SELECT
                COUNT(c.id) AS total_ids,
                SUM(CASE WHEN c.current_status = ? THEN 1 ELSE 0 END) AS pending_approval,
                SUM(CASE WHEN c.current_status = ? THEN 1 ELSE 0 END) AS correction_requested,
                SUM(CASE WHEN c.current_status = ? THEN 1 ELSE 0 END) AS approved_ready,
                SUM(CASE WHEN c.current_status = ? THEN 1 ELSE 0 END) AS printed,
                SUM(CASE WHEN c.current_status = ? THEN 1 ELSE 0 END) AS collected
            FROM id_cards c
            WHERE 1=1{$dateWhere}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            IdStatus::PENDING_HR_APPROVAL,
            IdStatus::CORRECTION_REQUESTED,
            IdStatus::APPROVED,
            IdStatus::PRINTED,
            IdStatus::COLLECTED,
            ...$params
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_ids'             => (int)($row['total_ids'] ?? 0),
            'pending_approval'      => (int)($row['pending_approval'] ?? 0),
            'correction_requested'  => (int)($row['correction_requested'] ?? 0),
            'approved_ready'        => (int)($row['approved_ready'] ?? 0),
            'printed'               => (int)($row['printed'] ?? 0),
            'collected'             => (int)($row['collected'] ?? 0),
        ];
    }

    // =====================================================================
    // NEEDS ATTENTION — Genuinely actionable items for the administrator
    // =====================================================================

    /**
     * Get genuinely actionable items requiring admin attention.
     * Returns at most $limit items per category.
     */
    public function getNeedsAttentionItems(int $limit = 6): array
    {
        $overdueCutoff  = date('Y-m-d H:i:s', time() - 86400);     // > 24 hours
        $printCutoff    = date('Y-m-d H:i:s', time() - 86400);     // > 24 hours approved, unprinted
        $collectCutoff  = date('Y-m-d H:i:s', time() - (7 * 86400)); // > 7 days printed, uncollected

        // Overdue approvals (pending > 24 hours)
        $stmtOverdue = $this->pdo->prepare("
            SELECT c.id, c.card_reference, e.full_name AS employee_name, c.updated_at
            FROM id_cards c
            JOIN employees e ON e.id = c.employee_id
            WHERE c.current_status = ? AND c.updated_at <= ?
            ORDER BY c.updated_at ASC
            LIMIT ?
        ");
        $stmtOverdue->execute([IdStatus::PENDING_HR_APPROVAL, $overdueCutoff, $limit]);
        $overdueApprovals = $stmtOverdue->fetchAll(PDO::FETCH_ASSOC);

        // IDs approved but unprinted > 24 hours
        $stmtPrintDelay = $this->pdo->prepare("
            SELECT c.id, c.card_reference, e.full_name AS employee_name, c.updated_at
            FROM id_cards c
            JOIN employees e ON e.id = c.employee_id
            WHERE c.current_status = ? AND c.updated_at <= ?
            ORDER BY c.updated_at ASC
            LIMIT ?
        ");
        $stmtPrintDelay->execute([IdStatus::APPROVED, $printCutoff, $limit]);
        $printingDelays = $stmtPrintDelay->fetchAll(PDO::FETCH_ASSOC);

        // IDs printed but uncollected > 7 days
        $stmtCollectDelay = $this->pdo->prepare("
            SELECT c.id, c.card_reference, e.full_name AS employee_name, c.updated_at
            FROM id_cards c
            JOIN employees e ON e.id = c.employee_id
            WHERE c.current_status = ? AND c.updated_at <= ?
            ORDER BY c.updated_at ASC
            LIMIT ?
        ");
        $stmtCollectDelay->execute([IdStatus::PRINTED, $collectCutoff, $limit]);
        $collectionDelays = $stmtCollectDelay->fetchAll(PDO::FETCH_ASSOC);

        // Correction requests awaiting designer (any that have been in CORRECTION_REQUESTED > 48h)
        $correctionCutoff = date('Y-m-d H:i:s', time() - (48 * 3600));
        $stmtCorrections = $this->pdo->prepare("
            SELECT c.id, c.card_reference, e.full_name AS employee_name, c.updated_at
            FROM id_cards c
            JOIN employees e ON e.id = c.employee_id
            WHERE c.current_status = ? AND c.updated_at <= ?
            ORDER BY c.updated_at ASC
            LIMIT ?
        ");
        $stmtCorrections->execute([IdStatus::CORRECTION_REQUESTED, $correctionCutoff, $limit]);
        $staleCorrections = $stmtCorrections->fetchAll(PDO::FETCH_ASSOC);

        // Totals for each category (for "View All" links)
        $stmtOverdueCount = $this->pdo->prepare(
            "SELECT COUNT(*) FROM id_cards WHERE current_status = ? AND updated_at <= ?"
        );
        $stmtOverdueCount->execute([IdStatus::PENDING_HR_APPROVAL, $overdueCutoff]);
        $totalOverdue = (int)$stmtOverdueCount->fetchColumn();

        $stmtPrintCount = $this->pdo->prepare(
            "SELECT COUNT(*) FROM id_cards WHERE current_status = ? AND updated_at <= ?"
        );
        $stmtPrintCount->execute([IdStatus::APPROVED, $printCutoff]);
        $totalPrintDelays = (int)$stmtPrintCount->fetchColumn();

        $stmtCollectCount = $this->pdo->prepare(
            "SELECT COUNT(*) FROM id_cards WHERE current_status = ? AND updated_at <= ?"
        );
        $stmtCollectCount->execute([IdStatus::PRINTED, $collectCutoff]);
        $totalCollectDelays = (int)$stmtCollectCount->fetchColumn();

        $stmtCorrCount = $this->pdo->prepare(
            "SELECT COUNT(*) FROM id_cards WHERE current_status = ? AND updated_at <= ?"
        );
        $stmtCorrCount->execute([IdStatus::CORRECTION_REQUESTED, $correctionCutoff]);
        $totalStaleCorrections = (int)$stmtCorrCount->fetchColumn();

        return [
            'overdue_approvals' => [
                'items' => $overdueApprovals,
                'count' => count($overdueApprovals),
                'total' => $totalOverdue,
                'label' => 'IDs pending HR approval &gt; 24 hours',
                'link'  => '/hr/pending',
                'severity' => 'danger',
            ],
            'printing_delays' => [
                'items' => $printingDelays,
                'count' => count($printingDelays),
                'total' => $totalPrintDelays,
                'label' => 'Approved IDs awaiting printing &gt; 24 hours',
                'link'  => '/printing/ready',
                'severity' => 'warning',
            ],
            'collection_delays' => [
                'items' => $collectionDelays,
                'count' => count($collectionDelays),
                'total' => $totalCollectDelays,
                'label' => 'Printed IDs awaiting collection &gt; 7 days',
                'link'  => '/printing/awaiting-collection',
                'severity' => 'info',
            ],
            'stale_corrections' => [
                'items' => $staleCorrections,
                'count' => count($staleCorrections),
                'total' => $totalStaleCorrections,
                'label' => 'Correction requests awaiting designer &gt; 48 hours',
                'link'  => '/designer/corrections',
                'severity' => 'warning',
            ],
            'total_alerts' => $totalOverdue + $totalPrintDelays + $totalCollectDelays + $totalStaleCorrections,
        ];
    }

    // =====================================================================
    // RECENT ACTIVITY — Compact audit log for Reports dashboard
    // =====================================================================

    /**
     * Get recent meaningful system activity for the Reports page.
     * Shows only important operational actions; excludes health checks,
     * login events, and VIEW-only events to keep the feed clean.
     */
    public function getRecentActivity(int $limit = 10): array
    {
        $importantActions = [
            'PDF_UPLOADED', 'PDF_REUPLOADED', 'CORRECTION_REQUESTED',
            'ID_APPROVED', 'ID_PRINTED', 'ID_COLLECTED',
            'PRINT_CONFIRMED', 'DATA_EXPORTED',
            'USER_CREATED', 'USER_UPDATED', 'USER_ACTIVATED', 'USER_DEACTIVATED',
            'PASSWORD_CHANGED'
        ];

        $placeholders = implode(',', array_fill(0, count($importantActions), '?'));

        $stmt = $this->pdo->prepare("
            SELECT
                a.id,
                a.action,
                a.user_name,
                a.user_role,
                a.previous_status,
                a.new_status,
                a.details,
                a.created_at,
                a.id_card_id,
                c.card_reference,
                e.full_name AS employee_name
            FROM audit_logs a
            LEFT JOIN id_cards c ON c.id = a.id_card_id
            LEFT JOIN employees e ON e.id = c.employee_id
            WHERE a.action IN ({$placeholders})
            ORDER BY a.created_at DESC, a.id DESC
            LIMIT ?
        ");

        $stmt->execute([...$importantActions, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================================
    // SEARCH — Efficient server-side search with pagination
    // =====================================================================

    /**
     * Search ID card records by employee name or card reference.
     * Uses INDEXED columns. Applies optional status and date filters.
     */
    public function searchIdCards(
        string $query,
        ?string $status,
        ?string $dateFrom,
        ?string $dateTo,
        int $limit = 20,
        int $offset = 0
    ): array {
        $params = [];
        $where  = " WHERE 1=1";

        if ($query !== '') {
            $q = '%' . $query . '%';
            $where .= " AND (e.full_name LIKE ? OR c.card_reference LIKE ?)";
            $params[] = $q;
            $params[] = $q;
        }

        if (!empty($status)) {
            $where .= " AND c.current_status = ?";
            $params[] = $status;
        }

        if (!empty($dateFrom)) {
            $where .= " AND c.created_at >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }

        if (!empty($dateTo)) {
            $where .= " AND c.created_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }

        $stmt = $this->pdo->prepare("
            SELECT
                c.id,
                c.card_reference,
                c.current_status,
                c.current_version_number,
                c.created_at,
                c.updated_at,
                e.full_name AS employee_name,
                e.staff_id   AS employee_staff_id,
                app.hr_name  AS approved_by_name,
                app.approved_at
            FROM id_cards c
            JOIN employees e ON e.id = c.employee_id
            LEFT JOIN approval_records app ON app.id_card_id = c.id
            {$where}
            ORDER BY c.updated_at DESC
            LIMIT ? OFFSET ?
        ");

        $stmt->execute([...$params, max(1, $limit), max(0, $offset)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count matching records for pagination.
     */
    public function countSearchIdCards(
        string $query,
        ?string $status,
        ?string $dateFrom,
        ?string $dateTo
    ): int {
        $params = [];
        $where  = " WHERE 1=1";

        if ($query !== '') {
            $q = '%' . $query . '%';
            $where .= " AND (e.full_name LIKE ? OR c.card_reference LIKE ?)";
            $params[] = $q;
            $params[] = $q;
        }

        if (!empty($status)) {
            $where .= " AND c.current_status = ?";
            $params[] = $status;
        }

        if (!empty($dateFrom)) {
            $where .= " AND c.created_at >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }

        if (!empty($dateTo)) {
            $where .= " AND c.created_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM id_cards c
            JOIN employees e ON e.id = c.employee_id
            {$where}
        ");

        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // =====================================================================
    // CSV EXPORT — Accurate rows with real approval/printed/collected dates
    // =====================================================================

    /**
     * Get rows for CSV export with accurate workflow dates.
     * LEFT JOINs approval_records, print_records, collection_records
     * to retrieve real event timestamps per card.
     */
    public function getExportRows(
        ?string $status,
        ?string $dateFrom,
        ?string $dateTo,
        string $search = '',
        int $limit = 50000,
        int $offset = 0
    ): array {
        $params = [];
        $where  = " WHERE 1=1";

        if (!empty($status)) {
            $where .= " AND c.current_status = ?";
            $params[] = $status;
        }

        if (!empty($dateFrom)) {
            $where .= " AND c.created_at >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }

        if (!empty($dateTo)) {
            $where .= " AND c.created_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }

        if ($search !== '') {
            $q = '%' . $search . '%';
            $where .= " AND (e.full_name LIKE ? OR c.card_reference LIKE ? OR e.staff_id LIKE ?)";
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                c.card_reference,
                e.full_name         AS employee_name,
                e.staff_id          AS employee_staff_id,
                c.current_status,
                c.current_version_number,
                c.created_at,
                c.updated_at,
                app.hr_name         AS approved_by_name,
                app.approved_at,
                pr.printed_at,
                pr.printing_user_name,
                cr.collected_at,
                cr.collected_by_name
            FROM id_cards c
            JOIN employees e ON e.id = c.employee_id
            LEFT JOIN approval_records app ON app.id_card_id = c.id
            LEFT JOIN print_records pr ON pr.id_card_id = c.id
            LEFT JOIN collection_records cr ON cr.id_card_id = c.id
            {$where}
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ");

        $stmt->execute([...$params, max(1, $limit), max(0, $offset)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================================
    // LEGACY / COMPATIBILITY — Kept to avoid breaking existing test suite
    // These are no longer used by the main Reports page UI
    // =====================================================================

    /**
     * @deprecated Use getAllKpis() for dashboard KPIs.
     * Preserved for backward compatibility with existing test suite.
     */
    public function getOverviewStats(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $departmentId = null
    ): array {
        $kpis = $this->getAllKpis($dateFrom, $dateTo);

        $totalIds      = $kpis['total_ids'];
        $pending       = $kpis['pending_approval'];
        $corrections   = $kpis['correction_requested'];
        $approvedReady = $kpis['approved_ready'];
        $printed       = $kpis['printed'];
        $collected     = $kpis['collected'];

        // Overdue count (> 24h pending review)
        $cutoff = date('Y-m-d H:i:s', time() - 86400);
        $stmtOverdue = $this->pdo->prepare(
            "SELECT COUNT(*) FROM id_cards WHERE current_status = ? AND updated_at <= ?"
        );
        $stmtOverdue->execute([IdStatus::PENDING_HR_APPROVAL, $cutoff]);
        $overdueCount = (int)$stmtOverdue->fetchColumn();

        // Total print batches
        $totalBatches = (int)$this->pdo->query("SELECT COUNT(*) FROM print_batches")->fetchColumn();

        // Cards with at least one correction request
        $stmtCardsWithCorr = $this->pdo->query("
            SELECT COUNT(DISTINCT id_card_id) FROM correction_requests
        ");
        $cardsWithCorrections = (int)$stmtCardsWithCorr->fetchColumn();

        $approvedEver      = $approvedReady + $printed + $collected;
        $totalPrintedEver  = $printed + $collected;
        $approvalRate      = $totalIds > 0 ? round(($approvedEver / $totalIds) * 100, 1) : 0.0;
        $correctionRate    = $totalIds > 0 ? round(($cardsWithCorrections / $totalIds) * 100, 1) : 0.0;
        $printingRate      = $approvedEver > 0 ? round(($totalPrintedEver / $approvedEver) * 100, 1) : 0.0;
        $collectionRate    = $totalPrintedEver > 0 ? round(($collected / $totalPrintedEver) * 100, 1) : 0.0;
        $completionRate    = $totalIds > 0 ? round(($collected / $totalIds) * 100, 1) : 0.0;

        return [
            'total_ids'               => $totalIds,
            'pending_approval'        => $pending,
            'correction_requested'    => $corrections,
            'cards_with_corrections'  => $cardsWithCorrections,
            'approved_ready'          => $approvedReady,
            'approved_total'          => $approvedEver,
            'printed_total'           => $printed,
            'printed_ever'            => $totalPrintedEver,
            'collected_total'         => $collected,
            'total_batches'           => $totalBatches,
            'overdue_count'           => $overdueCount,
            'approval_rate'           => $approvalRate,
            'correction_rate'         => $correctionRate,
            'printing_rate'           => $printingRate,
            'collection_rate'         => $collectionRate,
            'completion_rate'         => $completionRate,
        ];
    }

    /**
     * @deprecated Use getExportRows() instead.
     */
    public function getFilteredRows(array $filters = [], int $limit = 5000, int $offset = 0): array
    {
        return $this->getExportRows(
            $filters['status'] ?? null,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
            $filters['search'] ?? '',
            $limit,
            $offset
        );
    }

    public function getDepartmentBreakdown(?string $dateFrom = null, ?string $dateTo = null): array
    {
        [$dateFilter, $params] = $this->buildDateWhere('c.created_at', $dateFrom, $dateTo);

        $sql = "
            SELECT
                d.id   AS department_id,
                d.name AS department_name,
                d.code AS department_code,
                COUNT(c.id) AS total_ids,
                SUM(CASE WHEN c.current_status = 'PENDING_HR_APPROVAL' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN c.current_status = 'CORRECTION_REQUESTED' THEN 1 ELSE 0 END) AS correction_count,
                SUM(CASE WHEN c.current_status = 'APPROVED'   THEN 1 ELSE 0 END) AS approved_count,
                SUM(CASE WHEN c.current_status = 'PRINTED'    THEN 1 ELSE 0 END) AS printed_count,
                SUM(CASE WHEN c.current_status = 'COLLECTED'  THEN 1 ELSE 0 END) AS collected_count
            FROM departments d
            LEFT JOIN employees e ON e.department_id = d.id
            LEFT JOIN id_cards c ON c.employee_id = e.id{$dateFilter}
            GROUP BY d.id, d.name, d.code
            ORDER BY total_ids DESC, d.name ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($r) {
            $total     = (int)$r['total_ids'];
            $collected = (int)$r['collected_count'];
            return [
                'department_id'   => (int)$r['department_id'],
                'name'            => $r['department_name'],
                'code'            => $r['department_code'],
                'total'           => $total,
                'pending'         => (int)$r['pending_count'],
                'corrections'     => (int)$r['correction_count'],
                'approved'        => (int)$r['approved_count'],
                'printed'         => (int)$r['printed_count'],
                'collected'       => $collected,
                'completion_rate' => $total > 0 ? round(($collected / $total) * 100, 1) : 0.0,
            ];
        }, $rows);
    }

    public function getHrManagerPerformance(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $appFilter  = '';
        $corrFilter = '';
        $paramsApp  = [];
        $paramsCorr = [];

        if (!empty($dateFrom)) {
            $appFilter  .= " AND a.approved_at >= ?";
            $paramsApp[] = $dateFrom . ' 00:00:00';
            $corrFilter .= " AND cr.requested_at >= ?";
            $paramsCorr[] = $dateFrom . ' 00:00:00';
        }
        if (!empty($dateTo)) {
            $appFilter  .= " AND a.approved_at <= ?";
            $paramsApp[] = $dateTo . ' 23:59:59';
            $corrFilter .= " AND cr.requested_at <= ?";
            $paramsCorr[] = $dateTo . ' 23:59:59';
        }

        $sql = "
            SELECT
                u.id AS hr_user_id,
                u.name AS hr_name,
                u.email AS hr_email,
                u.status AS hr_status,
                u.last_login_at,
                (SELECT COUNT(*) FROM approval_records a WHERE a.hr_user_id = u.id{$appFilter}) AS approval_count,
                (SELECT COUNT(*) FROM correction_requests cr WHERE cr.requested_by_user_id = u.id{$corrFilter}) AS correction_count,
                (SELECT MAX(a.approved_at) FROM approval_records a WHERE a.hr_user_id = u.id) AS last_approved_at
            FROM users u
            WHERE u.role = 'HR_MANAGER'
            ORDER BY approval_count DESC, u.name ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($paramsApp, $paramsCorr));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($r) {
            $approvals   = (int)$r['approval_count'];
            $corrections = (int)$r['correction_count'];
            $total       = $approvals + $corrections;
            return [
                'hr_user_id'     => (int)$r['hr_user_id'],
                'name'           => $r['hr_name'],
                'email'          => $r['hr_email'],
                'status'         => $r['hr_status'],
                'approval_count' => $approvals,
                'correction_count' => $corrections,
                'total_reviews'  => $total,
                'approval_ratio' => $total > 0 ? round(($approvals / $total) * 100, 1) : 0.0,
                'last_approved_at' => $r['last_approved_at'],
                'last_login_at'  => $r['last_login_at'],
            ];
        }, $rows);
    }

    public function getDesignerPerformance(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = "
            SELECT
                u.id AS designer_id,
                u.name AS designer_name,
                u.email AS designer_email,
                u.status AS designer_status,
                u.last_login_at,
                COUNT(DISTINCT c.id) AS submitted_count,
                SUM(CASE WHEN c.current_status IN ('APPROVED','PRINTED','COLLECTED') THEN 1 ELSE 0 END) AS approved_count,
                COUNT(DISTINCT cr.id) AS corrections_received,
                ROUND(AVG(c.current_version_number), 1) AS avg_versions,
                MAX(c.created_at) AS last_submitted_at
            FROM users u
            LEFT JOIN id_cards c ON (c.created_by_user_id = u.id OR c.assigned_designer_id = u.id)
            LEFT JOIN correction_requests cr ON cr.id_card_id = c.id
            WHERE u.role = 'DESIGNER'
            GROUP BY u.id, u.name, u.email, u.status, u.last_login_at
            ORDER BY submitted_count DESC
        ";

        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($r) {
            $submitted = (int)$r['submitted_count'];
            $approved  = (int)$r['approved_count'];
            return [
                'designer_id'        => (int)$r['designer_id'],
                'name'               => $r['designer_name'],
                'email'              => $r['designer_email'],
                'status'             => $r['designer_status'],
                'submitted_count'    => $submitted,
                'approved_count'     => $approved,
                'corrections_received' => (int)$r['corrections_received'],
                'avg_versions'       => (float)($r['avg_versions'] ?? 1.0),
                'success_rate'       => $submitted > 0 ? round(($approved / $submitted) * 100, 1) : 0.0,
                'last_submitted_at'  => $r['last_submitted_at'],
                'last_login_at'      => $r['last_login_at'],
            ];
        }, $rows);
    }

    public function getPrintingPerformance(): array
    {
        $sqlOfficers = "
            SELECT
                u.id AS printing_user_id,
                u.name AS printing_user_name,
                u.email AS printing_user_email,
                COUNT(DISTINCT pr.id) AS total_printed,
                COUNT(DISTINCT pb.id) AS batch_count,
                MAX(pr.printed_at) AS last_printed_at
            FROM users u
            LEFT JOIN print_records pr ON pr.printing_user_id = u.id
            LEFT JOIN print_batches pb ON pb.printing_user_id = u.id
            WHERE u.role = 'PRINTING_OFFICER'
            GROUP BY u.id, u.name, u.email
        ";
        $officers = $this->pdo->query($sqlOfficers)->fetchAll(PDO::FETCH_ASSOC);

        $today     = date('Y-m-d');
        $thisMonth = date('Y-m');

        $stmtToday = $this->pdo->prepare("SELECT COUNT(*) FROM print_records WHERE printed_at >= ?");
        $stmtToday->execute([$today . ' 00:00:00']);
        $printedToday = (int)$stmtToday->fetchColumn();

        $stmtMonth = $this->pdo->prepare("SELECT COUNT(*) FROM print_records WHERE printed_at >= ?");
        $stmtMonth->execute([$thisMonth . '-01 00:00:00']);
        $printedThisMonth = (int)$stmtMonth->fetchColumn();

        $batchStats = $this->pdo->query("
            SELECT COUNT(*) AS total_batches, SUM(valid_count) AS total_batched_cards,
                   SUM(page_count) AS total_pages, AVG(valid_count) AS avg_batch_size
            FROM print_batches
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'officers'            => $officers,
            'printed_today'       => $printedToday,
            'printed_this_month'  => $printedThisMonth,
            'total_batches'       => (int)($batchStats['total_batches'] ?? 0),
            'total_batched_cards' => (int)($batchStats['total_batched_cards'] ?? 0),
            'total_pages'         => (int)($batchStats['total_pages'] ?? 0),
            'avg_batch_size'      => round((float)($batchStats['avg_batch_size'] ?? 0), 1),
        ];
    }

    public function getRecentBatches(int $limit = 8): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, batch_reference, printing_user_name, status,
                   valid_count, page_count, file_size, created_at, completed_at
            FROM print_batches
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTimeSeries(int $days = 14): array
    {
        $startDate = date('Y-m-d', time() - (($days - 1) * 86400));

        $sql = "SELECT date(created_at) AS log_date, COUNT(id) AS submitted_count
                FROM id_cards WHERE created_at >= ? GROUP BY date(created_at) ORDER BY log_date ASC";
        $stmtS = $this->pdo->prepare($sql);
        $stmtS->execute([$startDate . ' 00:00:00']);
        $submittedByDate = $stmtS->fetchAll(PDO::FETCH_KEY_PAIR);

        $stmtA = $this->pdo->prepare(
            "SELECT date(approved_at) AS log_date, COUNT(*) AS cnt FROM approval_records WHERE approved_at >= ? GROUP BY date(approved_at)"
        );
        $stmtA->execute([$startDate . ' 00:00:00']);
        $approvedByDate = $stmtA->fetchAll(PDO::FETCH_KEY_PAIR);

        $stmtP = $this->pdo->prepare(
            "SELECT date(printed_at) AS log_date, COUNT(*) AS cnt FROM print_records WHERE printed_at >= ? GROUP BY date(printed_at)"
        );
        $stmtP->execute([$startDate . ' 00:00:00']);
        $printedByDate = $stmtP->fetchAll(PDO::FETCH_KEY_PAIR);

        $stmtC = $this->pdo->prepare(
            "SELECT date(collected_at) AS log_date, COUNT(*) AS cnt FROM collection_records WHERE collected_at >= ? GROUP BY date(collected_at)"
        );
        $stmtC->execute([$startDate . ' 00:00:00']);
        $collectedByDate = $stmtC->fetchAll(PDO::FETCH_KEY_PAIR);

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', time() - ($i * 86400));
            $series[] = [
                'date'      => $d,
                'label'     => date('M d', strtotime($d)),
                'submitted' => (int)($submittedByDate[$d] ?? 0),
                'approved'  => (int)($approvedByDate[$d] ?? 0),
                'printed'   => (int)($printedByDate[$d] ?? 0),
                'collected' => (int)($collectedByDate[$d] ?? 0),
            ];
        }
        return $series;
    }

    // =====================================================================
    // PRIVATE HELPERS
    // =====================================================================

    /**
     * Build a simple date WHERE fragment for a given column.
     * Returns [sql_fragment, params_array].
     */
    private function buildDateWhere(string $column, ?string $dateFrom, ?string $dateTo): array
    {
        $sql    = '';
        $params = [];

        if (!empty($dateFrom)) {
            $sql      .= " AND {$column} >= ?";
            $params[]  = $dateFrom . ' 00:00:00';
        }
        if (!empty($dateTo)) {
            $sql      .= " AND {$column} <= ?";
            $params[]  = $dateTo . ' 23:59:59';
        }

        return [$sql, $params];
    }
}
