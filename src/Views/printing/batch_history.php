<?php
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Support\Timezone;

$batches = $batches ?? [];
$total = $total ?? count($batches);
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
  <div>
    <h2 style="font-size: 20px; font-weight: 800; color: #0b1329;">Print Batch History</h2>
    <p style="font-size: 13px; color: #64748b; margin-top: 2px;">
      Audit log and archive of all consolidated batch PDF merges, production runs, and manifests.
    </p>
  </div>

  <a href="/printing/ready" class="btn btn-primary btn-sm">
    <i class="fa-solid fa-plus-circle"></i> New Print Batch
  </a>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">
      <i class="fa-solid fa-layer-group" style="color: #2563eb;"></i>
      Generated Batches (<?= number_format($total) ?> Total)
    </div>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Batch Reference</th>
          <th>Created Date</th>
          <th style="text-align: center;">IDs</th>
          <th style="text-align: center;">Pages</th>
          <th>File Size</th>
          <th>Status</th>
          <th>Printing Officer</th>
          <th style="text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($batches as $batch): ?>
          <tr>
            <td data-label="Batch Reference">
              <a href="/printing/batches/<?= $batch->id ?>" style="font-weight: 800; font-family: monospace; color: #c59b27;">
                <?= Sanitizer::escape($batch->batch_reference) ?>
              </a>
            </td>
            <td data-label="Created Date" style="font-size: 12.5px; color: #64748b;">
              <?= Timezone::timeAgo($batch->created_at) ?>
            </td>
            <td data-label="IDs" style="text-align: center; font-weight: 700;">
              <?= $batch->valid_count ?: $batch->total_cards ?>
            </td>
            <td data-label="Pages" style="text-align: center;">
              <?= $batch->page_count ?: $batch->total_cards ?>
            </td>
            <td data-label="File Size" style="font-size: 12px; color: #64748b;">
              <?= $batch->file_size > 0 ? round($batch->file_size / 1024 / 1024, 2) . ' MB' : '—' ?>
            </td>
            <td data-label="Status">
              <span class="badge <?= match($batch->status) {
                'COMPLETED' => 'badge-success',
                'READY', 'PARTIAL_SUCCESS' => 'badge-primary',
                'MERGING', 'VALIDATING' => 'badge-warning',
                'EXPIRED' => 'badge-secondary',
                default => 'badge-info'
              } ?>">
                <?= Sanitizer::escape($batch->status) ?>
              </span>
            </td>
            <td data-label="Officer" style="font-size: 12.5px;">
              <?= Sanitizer::escape($batch->printing_user_name) ?>
            </td>
            <td data-label="Action" style="text-align: right;">
              <div style="display: inline-flex; gap: 6px;">
                <a href="/printing/batches/<?= $batch->id ?>" class="btn btn-outline btn-sm">
                  <i class="fa-solid fa-eye"></i> Details
                </a>
                <?php if ($batch->output_path && file_exists($batch->output_path)): ?>
                  <a href="/printing/batches/<?= $batch->id ?>/download" class="btn btn-outline btn-sm" title="Download PDF">
                    <i class="fa-solid fa-download"></i>
                  </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($batches)): ?>
          <tr>
            <td colspan="8" style="text-align: center; padding: 36px 20px; color: #64748b;">
              <i class="fa-solid fa-folder-open" style="font-size: 32px; color: #94a3b8; display: block; margin-bottom: 8px;"></i>
              <div style="font-weight: 700;">No Print Batches Recorded Yet</div>
              <div style="font-size: 12px; margin-top: 2px;">Merge batches created in "Ready for Printing" will appear here.</div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
