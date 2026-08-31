<?php
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Support\Timezone;

$ready = $readyCards ?? [];
$printed = $printedCards ?? [];
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
  <!-- Ready to Print -->
  <div class="card">
    <div class="card-header" style="background-color: #ecfdf5; border-bottom: 1px solid #a7f3d0;">
      <div class="card-title" style="color: #065f46;">
        <i class="fa-solid fa-hourglass-half"></i> Approved & Ready to Print (<?= count($ready) ?>)
      </div>
    </div>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Employee</th>
            <th>Approved By</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($ready)): ?>
            <tr><td colspan="3" style="text-align: center; color: #64748b; padding: 24px;">No cards waiting for printing.</td></tr>
          <?php else: ?>
            <?php foreach (array_slice($ready, 0, 15) as $c): ?>
              <tr>
                <td data-label="Employee"><strong style="color: #0b1329;"><?= Sanitizer::escape($c->employee_name) ?></strong></td>
                <td data-label="Approved By">
                  <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> <?= Sanitizer::escape($c->approved_by_name ?? 'HR Manager') ?></span>
                </td>
                <td data-label="Action" style="text-align: right;"><a href="/id-cards/<?= $c->id ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-eye"></i> Details</a></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Printed & Waiting Collection -->
  <div class="card">
    <div class="card-header" style="background-color: #eff6ff; border-bottom: 1px solid #bfdbfe;">
      <div class="card-title" style="color: #1e40af;">
        <i class="fa-solid fa-print"></i> Printed IDs (<?= count($printed) ?>)
      </div>
      <a href="/hr/collection" class="btn btn-primary btn-sm">Collection Center &rarr;</a>
    </div>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Employee</th>
            <th>Printed</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($printed)): ?>
            <tr><td colspan="3" style="text-align: center; color: #64748b; padding: 24px;">No printed cards awaiting collection.</td></tr>
          <?php else: ?>
            <?php foreach (array_slice($printed, 0, 15) as $c): ?>
              <tr>
                <td data-label="Employee"><strong style="color: #0b1329;"><?= Sanitizer::escape($c->employee_name) ?></strong></td>
                <td data-label="Printed" style="font-size: 12.5px; color: #64748b;"><?= Timezone::timeAgo($c->updated_at) ?></td>
                <td data-label="Action" style="text-align: right;"><a href="/id-cards/<?= $c->id ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-hand-holding-medical"></i> Handover</a></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
