<?php
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Support\Timezone;

$cardsList = $cards ?? [];
$filtersList = $filters ?? [];
$deptList = $departments ?? [];
?>

<!-- Instant Search Bar -->
<div class="card" style="margin-bottom: 20px;">
  <div class="card-body" style="padding: 12px 18px;">
    <div style="position: relative; max-width: 480px;">
      <input 
        type="text" 
        id="hr-corrections-search-input" 
        class="form-control smart-table-search" 
        data-table-id="hr-corrections-table" 
        placeholder="Search employee name (instant filter)..." 
        autocomplete="off"
        style="padding-left: 36px;"
      >
      <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 11px; color: #94a3b8; font-size: 13px;"></i>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header" style="background-color: #fff7ed; border-bottom: 1px solid #fed7aa;">
    <div class="card-title" style="color: #c2410c;">
      <i class="fa-solid fa-rotate-left"></i> IDs in Correction Stage (<?= count($cardsList) ?> Total)
    </div>
  </div>

  <div class="table-responsive">
    <table id="hr-corrections-table" class="data-table">
      <thead>
        <tr>
          <th>Employee Full Name</th>
          <th style="width: 140px;">Version</th>
          <th style="width: 180px;">Last Updated</th>
          <th style="text-align: right; width: 140px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($cardsList)): ?>
          <tr class="no-records-row">
            <td colspan="4" style="text-align: center; color: #64748b; padding: 36px 20px;">
              <i class="fa-solid fa-circle-check" style="font-size: 32px; color: #10b981; margin-bottom: 8px; display: block;"></i>
              <div style="font-weight: 700;">No IDs in Correction Stage</div>
              <div style="font-size: 12px; margin-top: 2px;">All ID submissions are either approved or pending review.</div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($cardsList as $c): ?>
            <tr class="searchable-row" data-search="<?= strtolower(Sanitizer::escape($c->employee_name)) ?>">
              <td data-label="Employee Full Name">
                <div style="font-weight: 700; color: #0b1329; font-size: 14.5px;">
                  <?= Sanitizer::escape($c->employee_name) ?>
                </div>
              </td>
              <td data-label="Version">
                <span class="badge badge-danger" style="font-weight: 700;">
                  v<?= $c->current_version_number ?>
                </span>
              </td>
              <td data-label="Last Updated" style="font-size: 13px; color: #64748b;">
                <?= Timezone::timeAgo($c->updated_at) ?>
              </td>
              <td data-label="Action" style="text-align: right;">
                <a href="/id-cards/<?= $c->id ?>" class="btn btn-outline btn-sm" style="padding: 5px 12px; font-weight: 600;">
                  <i class="fa-solid fa-eye"></i> Details
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
