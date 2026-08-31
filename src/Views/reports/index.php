<?php
/**
 * System Reports — Mengo Hospital HR ID Approval System
 * Simple, professional, data-driven administrative reporting view.
 * All values are sourced from the live database via ReportService.
 */
use Mengo\IdApproval\Models\IdStatus;
use Mengo\IdApproval\Models\Role;
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Security\SessionManager;

$kpis           = $kpis           ?? [];
$needsAttention = $needsAttention ?? [];
$recentActivity = $recentActivity ?? [];
$systemHealth   = $systemHealth   ?? [];
$filters        = $filters        ?? [];
$generatedAt    = $generatedAt    ?? date('d M Y, H:i');
$errorMsg       = $error          ?? null;

$currentPeriod = $filters['period']    ?? 'all_time';
$currentStatus = $filters['status']    ?? '';
$currentSearch = $filters['search']    ?? '';
$currentFrom   = $filters['date_from'] ?? '';
$currentTo     = $filters['date_to']   ?? '';

$user     = SessionManager::getUser();
$userRole = $user['role'] ?? '';
$isAdmin  = $userRole === Role::ADMINISTRATOR;

$totalAlerts = (int)($needsAttention['total_alerts'] ?? 0);

// Status → action link mapping
$statusLinks = [
    IdStatus::PENDING_HR_APPROVAL    => '/hr/pending',
    IdStatus::CORRECTION_REQUESTED   => '/hr/corrections',
    IdStatus::APPROVED               => '/printing/ready',
    IdStatus::PRINTED                => '/printing/printed',
    IdStatus::COLLECTED              => '/hr/collection',
];

// ─ Helper: format action label for Recent Activity ────────────────────────
function reportActionLabel(string $action): string
{
    return match ($action) {
        'PDF_UPLOADED'         => 'ID Uploaded',
        'PDF_REUPLOADED'       => 'ID Re-uploaded (Correction)',
        'CORRECTION_REQUESTED' => 'Correction Requested',
        'ID_APPROVED'          => 'ID Approved',
        'ID_PRINTED'           => 'ID Printed',
        'ID_COLLECTED'         => 'ID Collected',
        'PRINT_CONFIRMED'      => 'Batch Print Confirmed',
        'DATA_EXPORTED'        => 'Report Exported',
        'USER_CREATED'         => 'User Account Created',
        'USER_UPDATED'         => 'User Account Updated',
        'USER_ACTIVATED'       => 'User Activated',
        'USER_DEACTIVATED'     => 'User Deactivated',
        'PASSWORD_CHANGED'     => 'Password Changed',
        'REPORT_VIEWED'        => 'Report Viewed',
        default                => str_replace('_', ' ', ucfirst(strtolower($action))),
    };
}

function reportActionIcon(string $action): string
{
    return match ($action) {
        'PDF_UPLOADED', 'PDF_REUPLOADED' => 'fa-file-arrow-up',
        'CORRECTION_REQUESTED'           => 'fa-rotate-left',
        'ID_APPROVED'                    => 'fa-circle-check',
        'ID_PRINTED', 'PRINT_CONFIRMED'  => 'fa-print',
        'ID_COLLECTED'                   => 'fa-handshake',
        'DATA_EXPORTED'                  => 'fa-file-csv',
        'USER_CREATED', 'USER_UPDATED'   => 'fa-user-gear',
        'PASSWORD_CHANGED'               => 'fa-lock',
        default                          => 'fa-circle-dot',
    };
}

function reportActionColor(string $action): string
{
    return match ($action) {
        'ID_APPROVED'                    => '#059669',
        'ID_PRINTED', 'PRINT_CONFIRMED'  => '#2563eb',
        'ID_COLLECTED'                   => '#7c3aed',
        'CORRECTION_REQUESTED'           => '#ea580c',
        'DATA_EXPORTED'                  => '#c59b27',
        default                          => '#64748b',
    };
}
?>

<style>
/* ── Reports Page Styles ─────────────────────────────────────────────────── */
.rp-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
}
.rp-header-title { font-size: 22px; font-weight: 800; color: #0b1329; margin: 0 0 4px; }
.rp-header-sub   { font-size: 13px; color: #64748b; margin: 0; }

/* Filter bar */
.rp-filter-bar {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.rp-filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end;
}
.rp-filter-group { display: flex; flex-direction: column; gap: 5px; }
.rp-filter-label {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    color: #475569; letter-spacing: .3px;
}
.rp-filter-input,
.rp-filter-select {
    height: 38px; border: 1px solid #cbd5e1; border-radius: 6px;
    font-size: 13px; padding: 0 10px; background: #fff; color: #0f172a;
    min-width: 140px;
}
.rp-filter-input:focus,
.rp-filter-select:focus { outline: none; border-color: #c59b27; box-shadow: 0 0 0 3px rgba(197,155,39,.12); }
.rp-search-wrap { position: relative; flex: 1; min-width: 220px; }
.rp-search-wrap .rp-filter-input { width: 100%; padding-left: 36px; }
.rp-search-icon { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px; pointer-events: none; }
.rp-filter-actions { display: flex; gap: 8px; }

/* Search results dropdown */
#rp-search-results {
    display: none;
    position: absolute;
    top: calc(100% + 4px);
    left: 0; right: 0;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,.10);
    max-height: 320px;
    overflow-y: auto;
    z-index: 200;
}
.rp-search-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    cursor: pointer;
    transition: background .15s;
}
.rp-search-row:hover { background: #f8fafc; }
.rp-search-row:last-child { border-bottom: none; }
.rp-search-name { font-weight: 600; color: #0b1329; }
.rp-search-ref  { font-size: 11px; color: #64748b; margin-top: 2px; }
.rp-search-status { font-size: 11px; padding: 2px 8px; border-radius: 99px; font-weight: 700; }

/* KPI Cards */
.rp-kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
@media (max-width: 900px) { .rp-kpi-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 540px) { .rp-kpi-grid { grid-template-columns: 1fr; } }

.rp-kpi-card {
    background: #fff;
    border-radius: 10px;
    padding: 20px;
    border: 1px solid #e2e8f0;
    border-left: 4px solid var(--kpi-color, #0b1329);
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
    transition: box-shadow .2s, transform .2s;
}
.rp-kpi-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); transform: translateY(-1px); }
.rp-kpi-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
.rp-kpi-label  { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: .3px; }
.rp-kpi-icon   { font-size: 18px; color: var(--kpi-color, #0b1329); }
.rp-kpi-value  { font-size: 32px; font-weight: 900; color: var(--kpi-color, #0b1329); line-height: 1; margin-bottom: 5px; }
.rp-kpi-sub    { font-size: 11px; color: #94a3b8; }

/* Status Table */
.rp-section { margin-bottom: 24px; }
.rp-section-title {
    font-size: 14px; font-weight: 800; color: #0b1329;
    margin: 0 0 12px;
    display: flex; align-items: center; gap: 8px;
}
.rp-section-title i { color: #c59b27; }
.rp-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}

.rp-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.rp-table th {
    background: #f8fafc;
    padding: 11px 16px;
    font-weight: 700; font-size: 11px; text-transform: uppercase;
    color: #475569; letter-spacing: .3px;
    border-bottom: 2px solid #e2e8f0;
    text-align: left;
}
.rp-table th:not(:first-child) { text-align: center; }
.rp-table td { padding: 13px 16px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.rp-table tr:last-child td { border-bottom: none; }
.rp-table td:not(:first-child) { text-align: center; }
.rp-table tr:hover td { background: #fafbfc; }

.rp-status-dot {
    display: inline-block; width: 8px; height: 8px; border-radius: 50%;
    margin-right: 8px; vertical-align: middle;
}
.rp-status-label { font-weight: 600; color: #1e293b; vertical-align: middle; }
.rp-count-cell   { font-size: 16px; font-weight: 800; color: #0b1329; }

/* Needs Attention */
.rp-attention-list { list-style: none; padding: 0; margin: 0; }
.rp-attention-item {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
}
.rp-attention-item:last-child { border-bottom: none; }
.rp-attention-icon {
    width: 28px; height: 28px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; margin-top: 1px;
    font-size: 12px;
}
.rp-attention-icon.danger  { background: #fef2f2; color: #dc2626; }
.rp-attention-icon.warning { background: #fffbeb; color: #d97706; }
.rp-attention-icon.info    { background: #eff6ff; color: #2563eb; }
.rp-attention-body { flex: 1; min-width: 0; }
.rp-attention-text { font-size: 13px; color: #1e293b; line-height: 1.4; }
.rp-attention-count { font-weight: 800; }
.rp-attention-link {
    font-size: 12px; color: #c59b27; font-weight: 600;
    text-decoration: none; margin-left: 8px;
    white-space: nowrap;
}
.rp-attention-link:hover { color: #9a7a1d; }
.rp-no-attention {
    padding: 24px 16px; text-align: center;
    color: #059669; font-size: 13px; font-weight: 600;
}
.rp-no-attention i { margin-right: 6px; }

/* Recent Activity */
.rp-activity-row { display: flex; align-items: center; gap: 12px; padding: 10px 16px; border-bottom: 1px solid #f1f5f9; }
.rp-activity-row:last-child { border-bottom: none; }
.rp-activity-icon-wrap {
    width: 32px; height: 32px; border-radius: 50%;
    background: #f1f5f9;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 13px;
}
.rp-activity-body { flex: 1; min-width: 0; }
.rp-activity-action { font-size: 13px; font-weight: 600; color: #0b1329; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rp-activity-detail { font-size: 11px; color: #64748b; margin-top: 1px; }
.rp-activity-time { font-size: 11px; color: #94a3b8; white-space: nowrap; text-align: right; min-width: 80px; }

/* System Health */
.rp-health-row { display: flex; align-items: center; gap: 8px; padding: 10px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
.rp-health-row:last-child { border-bottom: none; }
.rp-health-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

/* Export footer */
.rp-footer { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
.rp-generated { font-size: 11px; color: #94a3b8; }

/* Error banner */
.rp-error {
    background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
    padding: 12px 16px; color: #b91c1c; font-size: 13px;
    margin-bottom: 20px; display: flex; align-items: center; gap: 8px;
}

/* Responsive tables */
.table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* Utility */
.badge-status {
    display: inline-block; padding: 2px 9px; border-radius: 99px;
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px;
}
.badge-PENDING_HR_APPROVAL  { background:#fef3c7; color:#92400e; }
.badge-CORRECTION_REQUESTED { background:#fee2e2; color:#991b1b; }
.badge-APPROVED             { background:#d1fae5; color:#065f46; }
.badge-PRINTED              { background:#dbeafe; color:#1e3a8a; }
.badge-COLLECTED            { background:#ede9fe; color:#4c1d95; }

@media (max-width: 640px) {
    .rp-header { flex-direction: column; }
    .rp-filter-row { flex-direction: column; }
    .rp-filter-group { width: 100%; }
    .rp-filter-input, .rp-filter-select { min-width: unset; width: 100%; }
    .rp-filter-actions { width: 100%; justify-content: flex-end; }
    .rp-footer { flex-direction: column; align-items: flex-start; }
}
</style>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!--  PAGE HEADER                                                          -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="rp-header">
  <div>
    <h2 class="rp-header-title">
      <i class="fa-solid fa-chart-pie" style="color:#c59b27;margin-right:8px;"></i>
      System Reports
    </h2>
    <p class="rp-header-sub">
      Operational overview of the Employee ID Approval workflow.
      Data as of <strong><?= Sanitizer::escape($generatedAt) ?></strong>.
    </p>
  </div>

  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <a href="/reports/export-csv<?= !empty($filters['status']) ? '?status=' . urlencode($filters['status']) : '' ?>"
       class="btn btn-outline"
       style="font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;border-color:#c59b27;color:#c59b27;"
       title="Export all ID workflow data to CSV">
      <i class="fa-solid fa-file-csv"></i> Export CSV
    </a>
    <a href="/audit-logs"
       class="btn btn-outline"
       style="font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;"
       title="View full audit trail">
      <i class="fa-solid fa-shield-halved"></i> Audit Log
    </a>
    <?php if ($isAdmin): ?>
    <a href="/admin/hr-accounts"
       class="btn btn-outline"
       style="font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;"
       title="Manage HR user accounts">
      <i class="fa-solid fa-users-gear"></i> Manage Users
    </a>
    <?php endif; ?>
  </div>
</div>

<?php if ($errorMsg): ?>
<div class="rp-error">
  <i class="fa-solid fa-triangle-exclamation"></i>
  <?= Sanitizer::escape($errorMsg) ?>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!--  FILTER BAR                                                           -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="rp-filter-bar">
  <form method="GET" action="/reports" id="reportFilterForm">
    <div class="rp-filter-row">

      <!-- Search -->
      <div class="rp-filter-group rp-search-wrap" style="flex:1;min-width:200px;">
        <label class="rp-filter-label">
          <i class="fa-solid fa-magnifying-glass" style="color:#c59b27;margin-right:4px;"></i>
          Search Employee
        </label>
        <div style="position:relative;">
          <i class="fa-solid fa-magnifying-glass rp-search-icon"></i>
          <input
            type="text"
            name="search"
            id="rpSearchInput"
            class="rp-filter-input"
            placeholder="Type employee name or card reference…"
            value="<?= Sanitizer::escape($currentSearch) ?>"
            autocomplete="off"
            style="padding-right:10px;"
          >
          <div id="rp-search-results"></div>
        </div>
      </div>

      <!-- Status -->
      <div class="rp-filter-group">
        <label class="rp-filter-label">
          <i class="fa-solid fa-filter" style="color:#c59b27;margin-right:4px;"></i>
          Status
        </label>
        <select name="status" class="rp-filter-select" id="rpStatusFilter">
          <option value="">All Statuses</option>
          <option value="PENDING_HR_APPROVAL"  <?= $currentStatus === 'PENDING_HR_APPROVAL'  ? 'selected' : '' ?>>Pending HR Approval</option>
          <option value="CORRECTION_REQUESTED" <?= $currentStatus === 'CORRECTION_REQUESTED' ? 'selected' : '' ?>>Correction Requested</option>
          <option value="APPROVED"             <?= $currentStatus === 'APPROVED'             ? 'selected' : '' ?>>Approved / Ready to Print</option>
          <option value="PRINTED"              <?= $currentStatus === 'PRINTED'              ? 'selected' : '' ?>>Printed</option>
          <option value="COLLECTED"            <?= $currentStatus === 'COLLECTED'            ? 'selected' : '' ?>>Collected</option>
        </select>
      </div>

      <!-- Date Range -->
      <div class="rp-filter-group">
        <label class="rp-filter-label">
          <i class="fa-solid fa-calendar-days" style="color:#c59b27;margin-right:4px;"></i>
          Date Range
        </label>
        <select name="period" class="rp-filter-select" id="rpPeriodFilter" onchange="rpToggleCustomDates(this.value)">
          <option value="all_time"   <?= $currentPeriod === 'all_time'   ? 'selected' : '' ?>>All Time</option>
          <option value="today"      <?= $currentPeriod === 'today'      ? 'selected' : '' ?>>Today</option>
          <option value="last_7_days" <?= $currentPeriod === 'last_7_days' ? 'selected' : '' ?>>Last 7 Days</option>
          <option value="last_30_days" <?= $currentPeriod === 'last_30_days' ? 'selected' : '' ?>>Last 30 Days</option>
          <option value="this_month"  <?= $currentPeriod === 'this_month'  ? 'selected' : '' ?>>This Month</option>
          <option value="custom"      <?= $currentPeriod === 'custom'      ? 'selected' : '' ?>>Custom Range</option>
        </select>
      </div>

      <!-- Custom Date From/To — shown only when period=custom -->
      <div class="rp-filter-group" id="rpCustomFrom" style="display:<?= $currentPeriod === 'custom' ? 'flex' : 'none' ?>;">
        <label class="rp-filter-label">From</label>
        <input type="date" name="date_from" class="rp-filter-input" value="<?= Sanitizer::escape($currentFrom) ?>" style="min-width:130px;">
      </div>
      <div class="rp-filter-group" id="rpCustomTo" style="display:<?= $currentPeriod === 'custom' ? 'flex' : 'none' ?>;">
        <label class="rp-filter-label">To</label>
        <input type="date" name="date_to" class="rp-filter-input" value="<?= Sanitizer::escape($currentTo) ?>" style="min-width:130px;">
      </div>

      <!-- Actions -->
      <div class="rp-filter-actions">
        <button type="submit" class="btn btn-primary" style="height:38px;font-weight:600;font-size:13px;padding:0 16px;background:#0b1329;border-color:#0b1329;">
          <i class="fa-solid fa-filter"></i> Apply
        </button>
        <a href="/reports" class="btn btn-outline" style="height:38px;font-weight:600;font-size:13px;padding:0 12px;display:inline-flex;align-items:center;gap:5px;">
          <i class="fa-solid fa-rotate-left"></i> Reset
        </a>
      </div>
    </div>
  </form>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!--  KPI CARDS (6 Essential Cards — all from live DB)                    -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="rp-kpi-grid" id="rpKpiGrid">

  <!-- Total IDs -->
  <div class="rp-kpi-card" style="--kpi-color:#0b1329;">
    <div class="rp-kpi-header">
      <span class="rp-kpi-label">Total IDs</span>
      <i class="fa-solid fa-id-badge rp-kpi-icon"></i>
    </div>
    <div class="rp-kpi-value" id="kpi_total"><?= number_format((int)($kpis['total_ids'] ?? 0)) ?></div>
    <div class="rp-kpi-sub">All registered ID records</div>
  </div>

  <!-- Pending HR Approval -->
  <div class="rp-kpi-card" style="--kpi-color:#d97706;">
    <div class="rp-kpi-header">
      <span class="rp-kpi-label">Pending HR Approval</span>
      <i class="fa-solid fa-clock rp-kpi-icon"></i>
    </div>
    <div class="rp-kpi-value" id="kpi_pending"><?= number_format((int)($kpis['pending_approval'] ?? 0)) ?></div>
    <div class="rp-kpi-sub">Awaiting HR Manager review</div>
  </div>

  <!-- Correction Requested -->
  <div class="rp-kpi-card" style="--kpi-color:#ea580c;">
    <div class="rp-kpi-header">
      <span class="rp-kpi-label">Correction Requested</span>
      <i class="fa-solid fa-pen-ruler rp-kpi-icon"></i>
    </div>
    <div class="rp-kpi-value" id="kpi_correction"><?= number_format((int)($kpis['correction_requested'] ?? 0)) ?></div>
    <div class="rp-kpi-sub">Returned to designer</div>
  </div>

  <!-- Approved / Ready to Print -->
  <div class="rp-kpi-card" style="--kpi-color:#059669;">
    <div class="rp-kpi-header">
      <span class="rp-kpi-label">Approved / Ready to Print</span>
      <i class="fa-solid fa-circle-check rp-kpi-icon"></i>
    </div>
    <div class="rp-kpi-value" id="kpi_approved"><?= number_format((int)($kpis['approved_ready'] ?? 0)) ?></div>
    <div class="rp-kpi-sub">Cleared for the print queue</div>
  </div>

  <!-- Printed -->
  <div class="rp-kpi-card" style="--kpi-color:#2563eb;">
    <div class="rp-kpi-header">
      <span class="rp-kpi-label">Printed</span>
      <i class="fa-solid fa-print rp-kpi-icon"></i>
    </div>
    <div class="rp-kpi-value" id="kpi_printed"><?= number_format((int)($kpis['printed'] ?? 0)) ?></div>
    <div class="rp-kpi-sub">Physically printed, awaiting handover</div>
  </div>

  <!-- Collected -->
  <div class="rp-kpi-card" style="--kpi-color:#7c3aed;">
    <div class="rp-kpi-header">
      <span class="rp-kpi-label">Collected</span>
      <i class="fa-solid fa-handshake rp-kpi-icon"></i>
    </div>
    <div class="rp-kpi-value" id="kpi_collected"><?= number_format((int)($kpis['collected'] ?? 0)) ?></div>
    <div class="rp-kpi-sub">Handed over to employees</div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!--  ID PROCESSING STATUS TABLE                                           -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="rp-section">
  <h3 class="rp-section-title">
    <i class="fa-solid fa-table-list"></i>
    ID Processing Status
  </h3>
  <div class="rp-card">
    <div class="table-responsive">
      <table class="rp-table">
        <thead>
          <tr>
            <th>Status</th>
            <th>Total</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $statusRows = [
              [
                  'label'  => 'Pending HR Approval',
                  'status' => IdStatus::PENDING_HR_APPROVAL,
                  'count'  => (int)($kpis['pending_approval'] ?? 0),
                  'color'  => '#d97706',
                  'icon'   => 'fa-clock',
                  'link'   => '/hr/pending',
                  'btn'    => 'View Queue',
              ],
              [
                  'label'  => 'Correction Requested',
                  'status' => IdStatus::CORRECTION_REQUESTED,
                  'count'  => (int)($kpis['correction_requested'] ?? 0),
                  'color'  => '#ea580c',
                  'icon'   => 'fa-rotate-left',
                  'link'   => '/hr/corrections',
                  'btn'    => 'View',
              ],
              [
                  'label'  => 'Approved / Ready to Print',
                  'status' => IdStatus::APPROVED,
                  'count'  => (int)($kpis['approved_ready'] ?? 0),
                  'color'  => '#059669',
                  'icon'   => 'fa-circle-check',
                  'link'   => '/printing/ready',
                  'btn'    => 'Open Print Queue',
              ],
              [
                  'label'  => 'Printed',
                  'status' => IdStatus::PRINTED,
                  'count'  => (int)($kpis['printed'] ?? 0),
                  'color'  => '#2563eb',
                  'icon'   => 'fa-print',
                  'link'   => '/printing/printed',
                  'btn'    => 'View',
              ],
              [
                  'label'  => 'Collected',
                  'status' => IdStatus::COLLECTED,
                  'count'  => (int)($kpis['collected'] ?? 0),
                  'color'  => '#7c3aed',
                  'icon'   => 'fa-handshake',
                  'link'   => '/hr/collection',
                  'btn'    => 'View',
              ],
          ];
          ?>
          <?php foreach ($statusRows as $row): ?>
          <tr>
            <td>
              <span class="rp-status-dot" style="background:<?= $row['color'] ?>;"></span>
              <span class="rp-status-label"><?= Sanitizer::escape($row['label']) ?></span>
            </td>
            <td>
              <span class="rp-count-cell" style="color:<?= $row['color'] ?>;">
                <?= number_format($row['count']) ?>
              </span>
            </td>
            <td>
              <?php if ($row['count'] > 0): ?>
              <a href="<?= $row['link'] ?>"
                 class="btn btn-outline btn-sm"
                 style="font-size:12px;font-weight:600;padding:4px 12px;">
                <i class="fa-solid <?= $row['icon'] ?>" style="color:<?= $row['color'] ?>;margin-right:5px;"></i>
                <?= Sanitizer::escape($row['btn']) ?>
              </a>
              <?php else: ?>
              <span style="font-size:12px;color:#94a3b8;">No records</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!--  TWO-COLUMN LAYOUT: Needs Attention + Recent Activity                 -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">

  <!-- NEEDS ATTENTION -->
  <div>
    <h3 class="rp-section-title">
      <i class="fa-solid fa-triangle-exclamation"></i>
      Needs Attention
      <?php if ($totalAlerts > 0): ?>
      <span style="background:#dc2626;color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:99px;"><?= $totalAlerts ?></span>
      <?php endif; ?>
    </h3>
    <div class="rp-card">
      <?php
      $attentionCategories = [
          'overdue_approvals'  => ['icon' => 'fa-clock',           'color_class' => 'danger'],
          'printing_delays'    => ['icon' => 'fa-print',           'color_class' => 'warning'],
          'stale_corrections'  => ['icon' => 'fa-rotate-left',     'color_class' => 'warning'],
          'collection_delays'  => ['icon' => 'fa-box-archive',     'color_class' => 'info'],
      ];
      $hasAny = false;
      foreach ($attentionCategories as $key => $meta):
          $cat   = $needsAttention[$key] ?? [];
          $count = (int)($cat['total'] ?? 0);
          if ($count <= 0) continue;
          $hasAny = true;
          $label  = $cat['label']  ?? $key;
          $link   = $cat['link']   ?? '#';
      ?>
      <div class="rp-attention-item">
        <div class="rp-attention-icon <?= $meta['color_class'] ?>">
          <i class="fa-solid <?= $meta['icon'] ?>"></i>
        </div>
        <div class="rp-attention-body">
          <div class="rp-attention-text">
            <span class="rp-attention-count"><?= $count ?></span>
            <?php // Strip HTML entities like &gt; for display ?>
            <?= Sanitizer::escape(html_entity_decode($label, ENT_QUOTES, 'UTF-8')) ?>
            <a href="<?= Sanitizer::escape($link) ?>" class="rp-attention-link">View All →</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

      <?php if (!$hasAny): ?>
      <div class="rp-no-attention">
        <i class="fa-solid fa-circle-check"></i>
        All systems normal — no attention required.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- RECENT ACTIVITY -->
  <div>
    <h3 class="rp-section-title">
      <i class="fa-solid fa-clock-rotate-left"></i>
      Recent Activity
    </h3>
    <div class="rp-card">
      <?php if (empty($recentActivity)): ?>
        <div style="padding:24px 16px;text-align:center;color:#94a3b8;font-size:13px;">
          <i class="fa-solid fa-inbox" style="font-size:20px;margin-bottom:8px;display:block;color:#cbd5e1;"></i>
          No recent system activity.
        </div>
      <?php else: ?>
        <?php foreach (array_slice($recentActivity, 0, 8) as $event): ?>
        <?php
          $action       = (string)($event['action']        ?? '');
          $userName     = (string)($event['user_name']     ?? 'System');
          $employeeName = (string)($event['employee_name'] ?? '');
          $cardRef      = (string)($event['card_reference'] ?? '');
          $createdAt    = (string)($event['created_at']    ?? '');
          $actionLabel  = reportActionLabel($action);
          $icon         = reportActionIcon($action);
          $color        = reportActionColor($action);

          // Format time
          $timeAgo = '';
          if ($createdAt) {
              $ts      = strtotime($createdAt);
              $diff    = time() - $ts;
              if ($diff < 60)             $timeAgo = 'just now';
              elseif ($diff < 3600)       $timeAgo = floor($diff / 60) . 'm ago';
              elseif ($diff < 86400)      $timeAgo = floor($diff / 3600) . 'h ago';
              else                        $timeAgo = date('d M', $ts);
          }

          // Sub-detail line
          $detail = $userName;
          if ($employeeName)  $detail .= ' · ' . $employeeName;
          elseif ($cardRef)   $detail .= ' · ' . $cardRef;
        ?>
        <div class="rp-activity-row">
          <div class="rp-activity-icon-wrap">
            <i class="fa-solid <?= Sanitizer::escape($icon) ?>" style="color:<?= Sanitizer::escape($color) ?>;"></i>
          </div>
          <div class="rp-activity-body">
            <div class="rp-activity-action"><?= Sanitizer::escape($actionLabel) ?></div>
            <div class="rp-activity-detail"><?= Sanitizer::escape($detail) ?></div>
          </div>
          <div class="rp-activity-time"><?= Sanitizer::escape($timeAgo) ?></div>
        </div>
        <?php endforeach; ?>

        <div style="padding:10px 16px;border-top:1px solid #f1f5f9;text-align:right;">
          <a href="/audit-logs" style="font-size:12px;color:#c59b27;font-weight:600;text-decoration:none;">
            View Full Audit Log →
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!--  SYSTEM STATUS & NOTIFICATION HEALTH                                  -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="rp-section">
  <h3 class="rp-section-title">
    <i class="fa-solid fa-heart-pulse"></i>
    System Notifications
  </h3>
  <div class="rp-card">
    <?php
    $inAppOk      = (bool)($systemHealth['in_app_operational'] ?? true);
    $emailEnabled = (bool)($systemHealth['email_enabled']      ?? false);
    $emailOk      = (bool)($systemHealth['email_operational']  ?? false);
    $emailFail    = (bool)($systemHealth['email_failure']      ?? false);
    ?>
    <div class="rp-health-row">
      <div class="rp-health-dot" style="background:<?= $inAppOk ? '#059669' : '#dc2626' ?>;"></div>
      <span style="font-weight:600;color:#0b1329;">In-App Notifications</span>
      <span style="margin-left:8px;font-size:12px;color:<?= $inAppOk ? '#059669' : '#dc2626' ?>;">
        <?= $inAppOk ? '✓ Operational' : '✗ Unavailable' ?>
      </span>
    </div>
    <div class="rp-health-row">
      <?php if (!$emailEnabled): ?>
        <div class="rp-health-dot" style="background:#94a3b8;"></div>
        <span style="font-weight:600;color:#0b1329;">Email Notifications</span>
        <span style="margin-left:8px;font-size:12px;color:#94a3b8;">Disabled in configuration</span>
      <?php elseif ($emailFail): ?>
        <div class="rp-health-dot" style="background:#dc2626;"></div>
        <span style="font-weight:600;color:#0b1329;">Email Notifications</span>
        <span style="margin-left:8px;font-size:12px;color:#dc2626;">⚠ Delivery failures detected</span>
        <a href="/audit-logs" style="margin-left:12px;font-size:12px;color:#c59b27;font-weight:600;">View Logs →</a>
      <?php else: ?>
        <div class="rp-health-dot" style="background:#059669;"></div>
        <span style="font-weight:600;color:#0b1329;">Email Notifications</span>
        <span style="margin-left:8px;font-size:12px;color:#059669;">✓ Operational</span>
      <?php endif; ?>
    </div>
    <div class="rp-health-row">
      <div class="rp-health-dot" style="background:#c59b27;"></div>
      <span style="font-weight:600;color:#0b1329;">System Diagnostics</span>
      <a href="/health" style="margin-left:8px;font-size:12px;color:#c59b27;font-weight:600;text-decoration:none;">
        Open Health Check →
      </a>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!--  FOOTER: SEARCH RESULTS TABLE (Shown when search is active)           -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div id="rpSearchResultsTable" style="display:none;" class="rp-section">
  <h3 class="rp-section-title" id="rpSearchResultsTitle">
    <i class="fa-solid fa-magnifying-glass"></i>
    Search Results
  </h3>
  <div class="rp-card">
    <div class="table-responsive">
      <table class="rp-table">
        <thead>
          <tr>
            <th>Employee Name</th>
            <th>Card Reference</th>
            <th>Status</th>
            <th>Version</th>
            <th>Last Updated</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="rpSearchTableBody">
        </tbody>
      </table>
    </div>
    <div id="rpSearchPager" style="padding:12px 16px;text-align:center;border-top:1px solid #f1f5f9;display:none;">
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!--  PAGE FOOTER                                                          -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="rp-footer">
  <span class="rp-generated">
    <i class="fa-solid fa-clock" style="color:#c59b27;margin-right:5px;"></i>
    Generated: <?= Sanitizer::escape($generatedAt) ?> EAT
  </span>
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <a href="/reports/export-csv<?= !empty($currentStatus) ? '?status=' . urlencode($currentStatus) : '' ?>"
       style="font-size:12px;font-weight:600;color:#c59b27;text-decoration:none;display:flex;align-items:center;gap:5px;">
      <i class="fa-solid fa-file-csv"></i> Export CSV
    </a>
    <span style="color:#e2e8f0;">|</span>
    <a href="/audit-logs" style="font-size:12px;font-weight:600;color:#64748b;text-decoration:none;">
      Audit Logs
    </a>
    <span style="color:#e2e8f0;">|</span>
    <a href="/health" style="font-size:12px;font-weight:600;color:#64748b;text-decoration:none;">
      Health Check
    </a>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!--  JAVASCRIPT: Live Search, Auto-refresh, Filter Toggle                 -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<script>
(function () {
  'use strict';

  // ─ Custom date toggle ────────────────────────────────────────────────
  window.rpToggleCustomDates = function (val) {
    const d = val === 'custom' ? 'flex' : 'none';
    document.getElementById('rpCustomFrom').style.display = d;
    document.getElementById('rpCustomTo').style.display   = d;
  };

  // ─ Debounce helper ───────────────────────────────────────────────────
  function debounce(fn, ms) {
    let timer;
    return function (...args) {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), ms);
    };
  }

  // ─ Badge CSS class for status strings ────────────────────────────────
  function statusBadge(s) {
    const map = {
      PENDING_HR_APPROVAL:  ['#fef3c7','#92400e'],
      CORRECTION_REQUESTED: ['#fee2e2','#991b1b'],
      APPROVED:             ['#d1fae5','#065f46'],
      PRINTED:              ['#dbeafe','#1e3a8a'],
      COLLECTED:            ['#ede9fe','#4c1d95'],
    };
    const [bg, fg] = map[s] || ['#f1f5f9', '#475569'];
    const label = s.replace(/_/g, ' ');
    return `<span style="background:${bg};color:${fg};padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700;text-transform:uppercase;">${label}</span>`;
  }

  // ─ Format relative time ──────────────────────────────────────────────
  function relTime(dateStr) {
    if (!dateStr) return '';
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60)     return 'just now';
    if (diff < 3600)   return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400)  return Math.floor(diff / 3600) + 'h ago';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
  }

  // ─ Live Search ───────────────────────────────────────────────────────
  const searchInput       = document.getElementById('rpSearchInput');
  const resultsTable      = document.getElementById('rpSearchResultsTable');
  const resultsTitleEl    = document.getElementById('rpSearchResultsTitle');
  const resultsBody       = document.getElementById('rpSearchTableBody');
  const resultsPager      = document.getElementById('rpSearchPager');
  const statusFilter      = document.getElementById('rpStatusFilter');
  let currentPage = 1;
  let lastQuery   = '';
  let lastStatus  = '';
  let isSearching = false;

  function buildSearchUrl(q, status, page) {
    const params = new URLSearchParams();
    params.set('search', q);
    params.set('format', 'json');
    if (status)    params.set('status', status);
    if (page > 1)  params.set('page', String(page));
    const period = document.getElementById('rpPeriodFilter')?.value || '';
    if (period && period !== 'all_time') params.set('period', period);
    return '/reports/search?' + params.toString();
  }

  function renderRow(row) {
    const name   = row.employee_name    || '—';
    const ref    = row.card_reference   || '—';
    const status = statusBadge(row.current_status || '');
    const ver    = 'v' + (row.current_version_number || 1);
    const time   = relTime(row.updated_at);
    const link   = `/id-cards/${row.id}`;
    return `<tr>
      <td><strong style="color:#0b1329;">${esc(name)}</strong></td>
      <td><code style="font-size:11px;background:#f1f5f9;padding:2px 6px;border-radius:4px;">${esc(ref)}</code></td>
      <td>${status}</td>
      <td style="color:#64748b;font-size:12px;">${esc(ver)}</td>
      <td style="color:#64748b;font-size:12px;">${esc(time)}</td>
      <td><a href="${link}" class="btn btn-outline btn-sm" style="font-size:12px;padding:3px 10px;">View</a></td>
    </tr>`;
  }

  function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function doSearch(q, status, page) {
    if (isSearching) return;
    isSearching = true;

    fetch(buildSearchUrl(q, status, page), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json())
      .then(json => {
        if (!json.ok || !json.search) { isSearching = false; return; }
        const { rows, total, pages } = json.search;

        if (!rows || rows.length === 0) {
          resultsBody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:24px;">No matching records found.</td></tr>`;
        } else {
          resultsBody.innerHTML = rows.map(renderRow).join('');
        }

        // Update title
        if (resultsTitleEl) {
          const icon = `<i class="fa-solid fa-magnifying-glass"></i>`;
          resultsTitleEl.innerHTML = `${icon} Search Results — ${total} record${total !== 1 ? 's' : ''} found`;
        }

        // Show/hide pager
        if (pages > 1) {
          let pagerHtml = `<div style="display:flex;gap:8px;align-items:center;justify-content:center;flex-wrap:wrap;">`;
          if (page > 1)   pagerHtml += `<button onclick="rpSearch('${esc(q)}','${esc(status||'')}',${page-1})" class="btn btn-outline btn-sm" style="font-size:12px;">← Prev</button>`;
          pagerHtml += `<span style="font-size:12px;color:#64748b;">Page ${page} of ${pages}</span>`;
          if (page < pages) pagerHtml += `<button onclick="rpSearch('${esc(q)}','${esc(status||'')}',${page+1})" class="btn btn-outline btn-sm" style="font-size:12px;">Next →</button>`;
          pagerHtml += '</div>';
          resultsPager.innerHTML = pagerHtml;
          resultsPager.style.display = 'block';
        } else {
          resultsPager.style.display = 'none';
        }

        resultsTable.style.display = 'block';
        isSearching = false;
      })
      .catch(() => { isSearching = false; });
  }

  // Expose for pager buttons
  window.rpSearch = function (q, status, page) {
    currentPage = page;
    doSearch(q, status, page);
    resultsTable.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  const triggerSearch = debounce(function () {
    const q      = (searchInput?.value || '').trim();
    const status = statusFilter?.value || '';

    if (q.length === 0 && !status) {
      resultsTable.style.display = 'none';
      return;
    }
    lastQuery  = q;
    lastStatus = status;
    currentPage = 1;
    doSearch(q, status, 1);
  }, 300);

  if (searchInput)  searchInput.addEventListener('input', triggerSearch);
  if (statusFilter) statusFilter.addEventListener('change', function () {
    // Only auto-search on status change if there's already a query
    if ((searchInput?.value || '').trim() || this.value) triggerSearch();
  });

  // If page loaded with a search query, run it
  const initQuery  = <?= json_encode($currentSearch) ?>;
  const initStatus = <?= json_encode($currentStatus) ?>;
  if (initQuery || initStatus) {
    setTimeout(() => doSearch(initQuery, initStatus, 1), 100);
  }

  // ─ Auto-refresh KPIs every 60 seconds ───────────────────────────────
  function refreshKpis() {
    fetch('/reports?format=json&period=<?= urlencode($currentPeriod) ?>',
          { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json())
      .then(json => {
        if (!json.ok || !json.kpis) return;
        const k = json.kpis;
        const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = Number(val).toLocaleString(); };
        set('kpi_total',      k.total_ids);
        set('kpi_pending',    k.pending_approval);
        set('kpi_correction', k.correction_requested);
        set('kpi_approved',   k.approved_ready);
        set('kpi_printed',    k.printed);
        set('kpi_collected',  k.collected);
      })
      .catch(() => {}); // Silent fail — UI retains last values
  }

  setInterval(refreshKpis, 60000);

})();
</script>
