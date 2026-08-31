<?php
use Mengo\IdApproval\Models\IdStatus;
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Support\Timezone;

$statusCounts = $statusCounts ?? [];
$corrections = $corrections ?? [];
$recentIds = $recentIds ?? [];
?>

<!-- Action Alert if Corrections are Required -->
<?php if (!empty($corrections)): ?>
  <div class="action-required-card" style="background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%); border-color: #fecdd3; margin-bottom: 24px;">
    <div class="action-required-header">
      <i class="fa-solid fa-triangle-exclamation" style="color: #e11d48; font-size: 18px;"></i>
      <div style="font-size: 15px; font-weight: 800; color: #9f1239;">
        <?= count($corrections) ?> ID Submission(s) Require Your Action (Corrections Requested by HR)
      </div>
    </div>
    <p style="font-size: 12.5px; color: #881337; margin-bottom: 12px;">
      HR Managers have reviewed and requested design modifications. Please inspect the remarks and re-upload the corrected PDFs.
    </p>
    <a href="/designer/corrections" class="btn btn-danger btn-sm">
      <i class="fa-solid fa-rotate-left"></i> Review Required Corrections &rarr;
    </a>
  </div>
<?php endif; ?>

<!-- 5-Card Clean Summary -->
<div class="stat-grid" style="margin-bottom: 24px;">
  <div class="stat-card" style="border-left: 4px solid #f59e0b;">
    <div class="stat-title">Pending Approval</div>
    <div class="stat-value" style="color: #d97706;"><?= $statusCounts['PENDING_HR_APPROVAL'] ?? 0 ?></div>
    <div class="stat-subtitle">Awaiting HR Review</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #e11d48;">
    <div class="stat-title">Corrections Required</div>
    <div class="stat-value" style="color: #e11d48;"><?= count($corrections) ?></div>
    <div class="stat-subtitle">Action Required</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #10b981;">
    <div class="stat-title">Approved</div>
    <div class="stat-value" style="color: #059669;"><?= $statusCounts['APPROVED'] ?? 0 ?></div>
    <div class="stat-subtitle">Ready for Printing</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #3b82f6;">
    <div class="stat-title">Printed</div>
    <div class="stat-value" style="color: #2563eb;"><?= $statusCounts['PRINTED'] ?? 0 ?></div>
    <div class="stat-subtitle">In Production / Ready</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #64748b;">
    <div class="stat-title">Collected</div>
    <div class="stat-value" style="color: #334155;"><?= $statusCounts['COLLECTED'] ?? 0 ?></div>
    <div class="stat-subtitle">Handed Over to Staff</div>
  </div>
</div>

<!-- Clean Table: Employee Name | Status | Last Updated | Action -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <i class="fa-solid fa-id-card" style="color: #c59b27;"></i>
      Recent ID Submissions
    </div>
    <div style="display: flex; gap: 10px;">
      <a href="/designer/create" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus-circle"></i> Create New ID
      </a>
      <a href="/designer/my-ids" class="btn btn-outline btn-sm">
        View All Submissions &rarr;
      </a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Employee Name</th>
          <th>Status</th>
          <th>Last Updated</th>
          <th style="text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentIds as $card): ?>
          <tr>
            <td data-label="Employee Name">
              <div style="font-weight: 700; color: #0b1329; font-size: 14px;">
                <?= Sanitizer::escape($card->employee_name) ?>
              </div>
            </td>
            <td data-label="Status">
              <span class="badge <?= IdStatus::badgeClass($card->current_status) ?>">
                <?= IdStatus::label($card->current_status) ?>
              </span>
            </td>
            <td data-label="Last Updated" style="font-size: 12.5px; color: #64748b;">
              <?= Timezone::timeAgo($card->updated_at) ?>
            </td>
            <td data-label="Action" style="text-align: right;">
              <a href="/id-cards/<?= $card->id ?>" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-eye"></i> Details
              </a>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($recentIds)): ?>
          <tr>
            <td colspan="4" style="text-align: center; padding: 36px 20px; color: #64748b;">
              <i class="fa-solid fa-cloud-arrow-up" style="font-size: 32px; color: #94a3b8; display: block; margin-bottom: 8px;"></i>
              <div style="font-weight: 700;">No ID Submissions Yet</div>
              <div style="font-size: 12px; margin-top: 2px;">Click "Create New ID" above to submit your first employee ID design.</div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
