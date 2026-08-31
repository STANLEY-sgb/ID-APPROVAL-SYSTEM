<?php
use Mengo\IdApproval\Models\IdStatus;
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Support\Timezone;

$cardsList = $cards ?? [];
$currentPage = $page ?? 1;
$pages = $totalPages ?? 1;
?>

<!-- Instant Search Bar -->
<div class="card" style="margin-bottom: 20px;">
  <div class="card-body" style="padding: 12px 18px;">
    <div style="position: relative; max-width: 480px;">
      <input 
        type="text" 
        id="printed-search-input" 
        class="form-control smart-table-search" 
        data-table-id="printed-table" 
        placeholder="Search employee name (instant filter)..." 
        autocomplete="off"
        style="padding-left: 36px;"
      >
      <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 11px; color: #94a3b8; font-size: 13px;"></i>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">
      <i class="fa-solid fa-check-circle" style="color: #2563eb;"></i>
      Printed ID Cards (<?= number_format($total ?? count($cardsList)) ?> Total)
    </div>
    <a href="/printing/ready" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-print"></i> Ready Queue
    </a>
  </div>

  <div class="table-responsive">
    <table id="printed-table" class="data-table">
      <thead>
        <tr>
          <th>Employee Full Name</th>
          <th style="width: 120px;">Version</th>
          <th style="width: 160px;">Printed Date</th>
          <th style="width: 140px;">Status</th>
          <th style="text-align: right; width: 120px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cardsList as $c): ?>
          <tr class="searchable-row" data-search="<?= strtolower(Sanitizer::escape($c->employee_name)) ?>">
            <td data-label="Employee Full Name">
              <div style="font-weight: 700; color: #0b1329; font-size: 14.5px;">
                <?= Sanitizer::escape($c->employee_name) ?>
              </div>
            </td>
            <td data-label="Version">
              <span class="badge badge-secondary">v<?= $c->current_version_number ?></span>
            </td>
            <td data-label="Printed Date" style="font-size: 12.5px; color: #64748b;">
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

        <?php if (empty($cardsList)): ?>
          <tr class="no-records-row">
            <td colspan="5" style="text-align: center; color: #64748b; padding: 36px 20px;">
              <i class="fa-solid fa-print" style="font-size: 32px; color: #94a3b8; display: block; margin-bottom: 8px;"></i>
              <div style="font-weight: 700;">No Printed Cards Recorded Yet</div>
              <div style="font-size: 12px; margin-top: 2px;">Completed print runs from the Ready for Printing queue will appear here.</div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
