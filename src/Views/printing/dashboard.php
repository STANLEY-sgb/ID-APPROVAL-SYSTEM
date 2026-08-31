<?php
use Mengo\IdApproval\Models\IdStatus;
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Support\Timezone;

$sum = $summary ?? [];
$readyQueue = $readyCards ?? [];
$recentPrinted = $recentPrinted ?? [];
?>

<!-- Stat Grid -->
<div class="stat-grid">
  <div class="stat-card" style="border-left: 4px solid #059669;">
    <div class="stat-title">Approved & Ready to Print</div>
    <div class="stat-value" style="color: #059669;"><?= $sum['ready_count'] ?? 0 ?></div>
    <div class="stat-subtitle">Approved by HR, awaiting production</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #2563eb;">
    <div class="stat-title">Printed Today</div>
    <div class="stat-value" style="color: #2563eb;"><?= $sum['printed_today'] ?? 0 ?></div>
    <div class="stat-subtitle"><?= $sum['printed_total'] ?? 0 ?> total printed all time</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #6366f1;">
    <div class="stat-title">Awaiting Collection</div>
    <div class="stat-value" style="color: #6366f1;"><?= $sum['awaiting_collection'] ?? 0 ?></div>
    <div class="stat-subtitle">Printed cards not yet collected</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #475569;">
    <div class="stat-title">Collected Today</div>
    <div class="stat-value" style="color: #475569;"><?= $sum['collected_today'] ?? 0 ?></div>
    <div class="stat-subtitle"><?= $sum['collected_total'] ?? 0 ?> total collected</div>
  </div>
</div>

<!-- Ready to Print Queue -->
<div class="card">
  <div class="card-header" style="background-color: #ecfdf5; border-bottom: 1px solid #a7f3d0;">
    <div class="card-title" style="color: #065f46;">
      <i class="fa-solid fa-print"></i> Priority Print Queue — Approved IDs Ready for Production (<?= count($readyQueue) ?>)
    </div>
    <a href="/printing/ready" class="btn btn-success btn-sm">Full Print Queue</a>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Employee Full Name</th>
          <th style="width: 150px;">Approved Version</th>
          <th style="width: 180px;">Approved Date</th>
          <th style="text-align: right; width: 150px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($readyQueue)): ?>
          <tr>
            <td colspan="4" style="text-align: center; color: #64748b; padding: 36px 20px;">
              <i class="fa-solid fa-check-double" style="font-size: 28px; display: block; margin-bottom: 8px; color: #059669;"></i>
              No approved IDs currently awaiting printing.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($readyQueue as $c): ?>
            <tr>
              <td data-label="Employee Full Name">
                <div style="font-weight: 700; color: #0b1329; font-size: 14.5px;"><?= Sanitizer::escape($c->employee_name) ?></div>
              </td>
              <td data-label="Approved Version">
                <span class="badge badge-success" style="font-weight: 700;">
                  v<?= $c->current_version_number ?> (Approved)
                </span>
              </td>
              <td data-label="Approved Date" style="font-size: 13px; color: #64748b;">
                <?= Timezone::timeAgo($c->updated_at) ?>
              </td>
              <td data-label="Action" style="text-align: right;">
                <a href="/id-cards/<?= $c->id ?>" class="btn btn-primary btn-sm" style="padding: 6px 12px; font-weight: 600;">
                  <i class="fa-solid fa-print"></i> Review & Print
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Recently Printed Cards -->
<?php if (!empty($recentPrinted)): ?>
  <div class="card" style="margin-top: 24px;">
    <div class="card-header">
      <div class="card-title"><i class="fa-solid fa-check-circle" style="color: #2563eb;"></i> Recently Printed IDs</div>
    </div>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Employee Full Name</th>
            <th style="width: 120px;">Version</th>
            <th style="width: 160px;">Printed</th>
            <th style="width: 140px;">Status</th>
            <th style="text-align: right; width: 120px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentPrinted as $c): ?>
            <tr>
              <td data-label="Employee Full Name">
                <div style="font-weight: 700; color: #0b1329; font-size: 14.5px;"><?= Sanitizer::escape($c->employee_name) ?></div>
              </td>
              <td data-label="Version">
                <span class="badge badge-secondary">v<?= $c->current_version_number ?></span>
              </td>
              <td data-label="Printed" style="font-size: 13px; color: #64748b;">
                <?= Timezone::timeAgo($c->updated_at) ?>
              </td>
              <td data-label="Status">
                <span class="badge badge-primary">
                  <i class="fa-solid fa-print"></i> PRINTED
                </span>
              </td>
              <td data-label="Action" style="text-align: right;">
                <a href="/id-cards/<?= $c->id ?>" class="btn btn-outline btn-sm" style="padding: 5px 12px; font-weight: 600;">
                  <i class="fa-solid fa-eye"></i> Details
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
