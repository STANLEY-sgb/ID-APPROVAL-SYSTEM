<?php
use Mengo\IdApproval\Models\IdStatus;
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Support\Timezone;

$summary = $summary ?? [];
$smartAlerts = $smartAlerts ?? ['total_alerts' => 0];
$pendingQueue = $pendingQueue ?? [];
$overdueList = $overdueList ?? [];
$awaitingCollection = $awaitingCollection ?? [];
?>

<!-- Action Required / Smart Follow-up Alerts -->
<?php if (($smartAlerts['total_alerts'] ?? 0) > 0): ?>
  <div class="action-required-card">
    <div class="action-required-header">
      <div class="action-badge-pulse"></div>
      <div style="font-size: 15px; font-weight: 800; color: #9a3412;">
        ACTION REQUIRED: <?= $smartAlerts['total_alerts'] ?> Operational Follow-up Item(s)
      </div>
    </div>

    <div class="action-items-grid">
      <?php if (!empty($smartAlerts['overdue_approvals']['count'])): ?>
        <a href="/hr/pending" class="action-stat-pill" style="border-left: 4px solid #ef4444;">
          <div>
            <div style="font-size: 11px; font-weight: 700; color: #b91c1c;">OVERDUE APPROVALS</div>
            <div style="font-size: 12px; color: #64748b;">Pending HR > 24h</div>
          </div>
          <span class="badge badge-danger"><?= $smartAlerts['overdue_approvals']['count'] ?></span>
        </a>
      <?php endif; ?>

      <?php if (!empty($smartAlerts['stale_corrections']['count'])): ?>
        <a href="/hr/corrections" class="action-stat-pill" style="border-left: 4px solid #f59e0b;">
          <div>
            <div style="font-size: 11px; font-weight: 700; color: #b45309;">STALE CORRECTIONS</div>
            <div style="font-size: 12px; color: #64748b;">Awaiting Designer > 48h</div>
          </div>
          <span class="badge badge-warning"><?= $smartAlerts['stale_corrections']['count'] ?></span>
        </a>
      <?php endif; ?>

      <?php if (!empty($smartAlerts['printing_delays']['count'])): ?>
        <a href="/hr/printing" class="action-stat-pill" style="border-left: 4px solid #3b82f6;">
          <div>
            <div style="font-size: 11px; font-weight: 700; color: #1d4ed8;">PRINTING DELAY</div>
            <div style="font-size: 12px; color: #64748b;">Approved > 24h Unprinted</div>
          </div>
          <span class="badge badge-primary"><?= $smartAlerts['printing_delays']['count'] ?></span>
        </a>
      <?php endif; ?>

      <?php if (!empty($smartAlerts['collection_delays']['count'])): ?>
        <a href="/hr/collection" class="action-stat-pill" style="border-left: 4px solid #059669;">
          <div>
            <div style="font-size: 11px; font-weight: 700; color: #047857;">COLLECTION DELAY</div>
            <div style="font-size: 12px; color: #64748b;">Printed > 7 Days Unclaimed</div>
          </div>
          <span class="badge badge-success"><?= $smartAlerts['collection_delays']['count'] ?></span>
        </a>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<!-- 6-Stat KPI Grid -->
<div class="stat-grid">
  <div class="stat-card" style="border-left: 4px solid #f59e0b;">
    <div class="stat-title">Pending Approvals</div>
    <div class="stat-value" data-stat="PENDING_HR_APPROVAL" style="color: #d97706;"><?= $summary['pending'] ?? 0 ?></div>
    <div class="stat-subtitle">Awaiting HR Review</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #10b981;">
    <div class="stat-title">Approved Cards</div>
    <div class="stat-value" data-stat="APPROVED" style="color: #059669;"><?= $summary['approved'] ?? 0 ?></div>
    <div class="stat-subtitle">Cleared for Printing</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #3b82f6;">
    <div class="stat-title">Printed Cards</div>
    <div class="stat-value" data-stat="PRINTED" style="color: #2563eb;"><?= $summary['printed'] ?? 0 ?></div>
    <div class="stat-subtitle">In Production / Ready</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #ef4444;">
    <div class="stat-title">Corrections</div>
    <div class="stat-value" data-stat="CORRECTION_REQUESTED" style="color: #e11d48;"><?= $summary['corrections'] ?? 0 ?></div>
    <div class="stat-subtitle">Requires Designer Action</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #64748b;">
    <div class="stat-title">Collected / Archived</div>
    <div class="stat-value" data-stat="COLLECTED" style="color: #334155;"><?= $summary['collected'] ?? 0 ?></div>
    <div class="stat-subtitle">Handed Over to Staff</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #c59b27;">
    <div class="stat-title">Total Staff IDs</div>
    <div class="stat-value" style="color: #a17c1b;"><?= $summary['total_cards'] ?? 0 ?></div>
    <div class="stat-subtitle"><?= $summary['departments_count'] ?? 11 ?> Hospital Depts</div>
  </div>
</div>

<!-- Pending HR Approval Queue -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <i class="fa-solid fa-clock" style="color: #d97706;"></i>
      Pending HR Approval Queue (<?= count($pendingQueue) ?> Recent)
    </div>
    <a href="/hr/pending" class="btn btn-outline btn-sm">
      View All Pending Approvals &rarr;
    </a>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Employee Full Name</th>
          <th style="width: 140px;">Version</th>
          <th style="width: 180px;">Submitted</th>
          <th style="text-align: right; width: 140px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pendingQueue as $card): ?>
          <tr>
            <td data-label="Employee Full Name">
              <div style="font-weight: 700; color: #0b1329; font-size: 14.5px;"><?= Sanitizer::escape($card->employee_name) ?></div>
            </td>
            <td data-label="Version">
              <span class="badge badge-warning" style="font-weight: 700;">
                v<?= $card->current_version_number ?>
              </span>
            </td>
            <td data-label="Submitted">
              <div style="font-size: 13px; color: #475569;"><?= Timezone::timeAgo($card->updated_at) ?></div>
            </td>
            <td data-label="Action" style="text-align: right;">
              <a href="/id-cards/<?= $card->id ?>" class="btn btn-primary btn-sm" style="padding: 6px 14px; font-weight: 600;">
                <i class="fa-solid fa-circle-check"></i> Review
              </a>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($pendingQueue)): ?>
          <tr>
            <td colspan="4" style="text-align: center; padding: 40px 20px; color: #64748b;">
              <i class="fa-solid fa-circle-check" style="font-size: 36px; color: #10b981; margin-bottom: 8px; display: block;"></i>
              <div style="font-weight: 700; color: #1e293b;">No IDs Awaiting HR Approval</div>
              <div style="font-size: 12.5px;">All submissions are up to date. New designer uploads will appear here automatically.</div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
