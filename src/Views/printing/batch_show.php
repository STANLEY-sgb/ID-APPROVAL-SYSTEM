<?php
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Support\Timezone;

$batch = $batch ?? null;
$items = $items ?? [];
$auditLogs = $auditLogs ?? [];
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
  <div>
    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">
      <a href="/"><i class="fa-solid fa-house"></i> Home</a> &rsaquo; 
      <a href="/printing/batches">Print Batches</a> &rsaquo; 
      <strong><?= Sanitizer::escape($batch->batch_reference) ?></strong>
    </div>
    <h2 style="font-size: 20px; font-weight: 800; color: #0b1329;">
      Batch Manifest: <?= Sanitizer::escape($batch->batch_reference) ?>
      <span class="badge <?= match($batch->status) {
        'COMPLETED' => 'badge-success',
        'READY', 'PARTIAL_SUCCESS' => 'badge-primary',
        'MERGING', 'VALIDATING' => 'badge-warning',
        'EXPIRED' => 'badge-secondary',
        default => 'badge-info'
      } ?>" style="font-size: 12px; margin-left: 8px; vertical-align: middle;">
        <?= Sanitizer::escape($batch->status) ?>
      </span>
    </h2>
  </div>

  <div style="display: flex; gap: 8px;">
    <?php if ($batch->output_path && file_exists($batch->output_path)): ?>
      <a href="/printing/batches/<?= $batch->id ?>/download" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-download"></i> Download Merged PDF
      </a>
    <?php endif; ?>
    <a href="/printing/batches" class="btn btn-outline btn-sm">
      <i class="fa-solid fa-arrow-left"></i> Back to History
    </a>
  </div>
</div>

<!-- Manifest Summary Grid -->
<div class="stat-grid" style="margin-bottom: 24px;">
  <div class="stat-card" style="border-left: 4px solid #c59b27;">
    <div class="stat-title">Included IDs</div>
    <div class="stat-value" style="color: #a17c1b;"><?= $batch->valid_count ?: $batch->total_cards ?></div>
    <div class="stat-subtitle"><?= $batch->selected_count ?> Selected</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #2563eb;">
    <div class="stat-title">Output Pages</div>
    <div class="stat-value" style="color: #1d4ed8;"><?= $batch->page_count ?: $batch->total_cards ?></div>
    <div class="stat-subtitle"><?= $batch->file_size > 0 ? round($batch->file_size / 1024 / 1024, 2) . ' MB' : '—' ?></div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #10b981;">
    <div class="stat-title">Printing Officer</div>
    <div style="font-size: 15px; font-weight: 800; color: #065f46; margin-top: 4px;">
      <?= Sanitizer::escape($batch->printing_user_name) ?>
    </div>
    <div class="stat-subtitle"><?= Timezone::formatDetailed($batch->created_at) ?></div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #e11d48;">
    <div class="stat-title">Excluded / Failed</div>
    <div class="stat-value" style="color: <?= $batch->failed_count > 0 ? '#e11d48' : '#64748b' ?>;"><?= $batch->failed_count ?></div>
    <div class="stat-subtitle"><?= $batch->failed_count > 0 ? 'Document check failed' : 'All Valid' ?></div>
  </div>
</div>

<!-- Progressive Disclosure Sections -->
<div class="card" style="margin-bottom: 20px;">
  <div class="card-header">
    <div class="card-title"><i class="fa-solid fa-list-check" style="color: #c59b27;"></i> Batch Manifest Items (<?= count($items) ?> Records)</div>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width: 40px; text-align: center;">#</th>
          <th>Employee Name</th>
          <th>Status in Batch</th>
          <th>Physical Print Status</th>
          <th style="text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $idx => $item): ?>
          <tr>
            <td style="text-align: center; font-weight: 700; color: #64748b;"><?= $idx + 1 ?></td>
            <td>
              <div style="font-weight: 700; color: #0b1329; font-size: 14px;"><?= Sanitizer::escape($item->employee_name) ?></div>
            </td>
            <td>
              <?php if ($item->validation_status === 'VALID'): ?>
                <span class="badge badge-success"><i class="fa-solid fa-check"></i> Merged</span>
              <?php else: ?>
                <span class="badge badge-danger"><i class="fa-solid fa-xmark"></i> Excluded (<?= Sanitizer::escape($item->failure_reason ?? 'Invalid') ?>)</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($item->is_printed): ?>
                <span class="badge badge-primary"><i class="fa-solid fa-print"></i> Printed (<?= Timezone::timeAgo($item->printed_at) ?>)</span>
              <?php else: ?>
                <span class="badge badge-secondary">Pending Handshake</span>
              <?php endif; ?>
            </td>
            <td style="text-align: right;">
              <a href="/id-cards/<?= $item->id_card_id ?>" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-eye"></i> View Card
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
