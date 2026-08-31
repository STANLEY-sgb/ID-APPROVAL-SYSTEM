<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Services;

use Mengo\IdApproval\Repositories\ReportRepository;
use Mengo\IdApproval\Support\Config;
use Mengo\IdApproval\Support\Timezone;

class ReportService
{
    private ReportRepository $reportRepo;

    public function __construct(?ReportRepository $reportRepo = null)
    {
        $this->reportRepo = $reportRepo ?? new ReportRepository();
    }

    /**
     * Resolve a period preset to concrete date_from / date_to strings.
     */
    public function resolveDateFilter(array $params): array
    {
        $period = $params['period'] ?? 'all_time';
        $today  = date('Y-m-d');

        $dateFrom = null;
        $dateTo   = null;

        switch ($period) {
            case 'today':
                $dateFrom = $dateTo = $today;
                break;
            case 'yesterday':
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $dateFrom  = $dateTo = $yesterday;
                break;
            case 'last_7_days':
                $dateFrom = date('Y-m-d', strtotime('-7 days'));
                $dateTo   = $today;
                break;
            case 'last_30_days':
                $dateFrom = date('Y-m-d', strtotime('-30 days'));
                $dateTo   = $today;
                break;
            case 'this_month':
                $dateFrom = date('Y-m-01');
                $dateTo   = $today;
                break;
            case 'last_month':
                $dateFrom = date('Y-m-01', strtotime('first day of last month'));
                $dateTo   = date('Y-m-t',  strtotime('last day of last month'));
                break;
            case 'this_year':
                $dateFrom = date('Y-01-01');
                $dateTo   = $today;
                break;
            case 'custom':
                $dateFrom = !empty($params['date_from']) ? trim((string)$params['date_from']) : null;
                $dateTo   = !empty($params['date_to'])   ? trim((string)$params['date_to'])   : null;
                break;
            default:
                $period = 'all_time';
        }

        return [
            'period'    => $period,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'status'    => !empty($params['status']) ? trim((string)$params['status']) : null,
            'search'    => !empty($params['search']) ? trim((string)$params['search']) : null,
        ];
    }

    /**
     * Primary dashboard data — used by the Reports page.
     *
     * Returns only what is needed:
     *  - kpis:            6 authoritative KPI counts
     *  - needsAttention:  actionable items
     *  - recentActivity:  compact audit feed
     *  - systemHealth:    notification service status
     *  - filters:         resolved filter state
     *  - generated_at:    timestamp
     */
    public function getDashboardData(array $filterParams = []): array
    {
        $filters  = $this->resolveDateFilter($filterParams);
        $dateFrom = $filters['date_from'];
        $dateTo   = $filters['date_to'];

        $kpis            = $this->reportRepo->getAllKpis($dateFrom, $dateTo);
        $needsAttention  = $this->reportRepo->getNeedsAttentionItems(6);
        $recentActivity  = $this->reportRepo->getRecentActivity(10);
        $systemHealth    = $this->getSystemHealthStatus();

        return [
            'filters'         => $filters,
            'kpis'            => $kpis,
            'needsAttention'  => $needsAttention,
            'recentActivity'  => $recentActivity,
            'systemHealth'    => $systemHealth,
            'generated_at'    => Timezone::nowString(),

            // Legacy aliases — kept so existing test suite continues to pass
            'overview'         => $this->reportRepo->getOverviewStats($dateFrom, $dateTo),
            'departments'      => $this->reportRepo->getDepartmentBreakdown($dateFrom, $dateTo),
            'hrPerformance'    => $this->reportRepo->getHrManagerPerformance($dateFrom, $dateTo),
            'designerPerformance' => $this->reportRepo->getDesignerPerformance($dateFrom, $dateTo),
            'printing'         => $this->reportRepo->getPrintingPerformance(),
            'recentBatches'    => $this->reportRepo->getRecentBatches(8),
            'timeSeries'       => $this->reportRepo->getTimeSeries(14),
        ];
    }

    /**
     * Debounced AJAX search handler.
     * Returns matching ID cards for the live search field.
     */
    public function search(
        string $query,
        ?string $status,
        ?string $dateFrom,
        ?string $dateTo,
        int $page = 1,
        int $perPage = 20
    ): array {
        $query    = trim($query);
        $offset   = ($page - 1) * $perPage;

        $rows  = $this->reportRepo->searchIdCards($query, $status, $dateFrom, $dateTo, $perPage, $offset);
        $total = $this->reportRepo->countSearchIdCards($query, $status, $dateFrom, $dateTo);

        return [
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int)ceil($total / max(1, $perPage)),
        ];
    }

    /**
     * Generate CSV export content.
     *
     * Columns:
     *   Employee Name, Staff ID, Card Reference, Current Status, Version,
     *   Created Date, Last Updated, Approved By, Approved Date,
     *   Printed Date, Collected Date
     */
    public function exportCsv(array $filterParams = []): string
    {
        $filters = $this->resolveDateFilter($filterParams);
        $rows    = $this->reportRepo->getExportRows(
            $filters['status'],
            $filters['date_from'],
            $filters['date_to'],
            $filters['search'] ?? '',
            50000,
            0
        );

        $fp = fopen('php://temp', 'r+');
        if ($fp === false) {
            return '';
        }

        // UTF-8 BOM for Excel compatibility
        fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($fp, [
            'Employee Name',
            'Staff ID',
            'Card Reference',
            'Current Status',
            'Version',
            'Created Date',
            'Last Updated',
            'Approved By',
            'Approved Date',
            'Printed Date',
            'Collected Date',
        ]);

        $sanitize = static function ($val): string {
            $s = (string)($val ?? '');
            if (isset($s[0]) && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'" . $s;
            }
            return $s;
        };

        foreach ($rows as $row) {
            fputcsv($fp, [
                $sanitize($row['employee_name']),
                $sanitize($row['employee_staff_id']),
                $sanitize($row['card_reference']),
                $sanitize($row['current_status']),
                'v' . ((int)($row['current_version_number'] ?? 1)),
                $sanitize($row['created_at']),
                $sanitize($row['updated_at']),
                $sanitize($row['approved_by_name'] ?? ''),
                $sanitize($row['approved_at']       ?? ''),
                $sanitize($row['printed_at']        ?? ''),
                $sanitize($row['collected_at']      ?? ''),
            ]);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv ?: '';
    }

    /**
     * Check the operational status of the notification and email services.
     * Returns a simple health indicator without exposing credentials.
     */
    public function getSystemHealthStatus(): array
    {
        $emailEnabled = (string)Config::get('MAIL_ENABLED', 'false');
        $emailHost    = (string)Config::get('MAIL_HOST', '');

        $inAppOk    = true;  // In-app notifications always operational if DB is up
        $emailOk    = strtolower($emailEnabled) === 'true' && !empty($emailHost);

        // Check email log for recent failures
        $emailLogPath = defined('APP_ROOT') ? APP_ROOT . '/storage/logs/email.log' : null;
        $recentFailure = false;
        if ($emailLogPath && file_exists($emailLogPath)) {
            $logTail = @file_get_contents($emailLogPath, false, null, -4096);
            $recentFailure = $logTail && str_contains($logTail, '[ERROR]');
        }

        return [
            'in_app_operational' => $inAppOk,
            'email_enabled'      => strtolower($emailEnabled) === 'true',
            'email_operational'  => $emailOk && !$recentFailure,
            'email_failure'      => $recentFailure,
        ];
    }

    /**
     * Lightweight summary for real-time polling (SyncController).
     */
    public function getExecutiveSummary(): array
    {
        return $this->reportRepo->getOverviewStats();
    }
}
