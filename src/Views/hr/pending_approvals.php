<?php
use Mengo\IdApproval\Models\IdStatus;
use Mengo\IdApproval\Security\CsrfToken;
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Support\Timezone;

$cards = $cards ?? [];
$departments = $departments ?? [];
$filters = $filters ?? [];
$total = $total ?? count($cards);
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
  <div>
    <h2 style="font-size: 20px; font-weight: 800; color: #0b1329;">Pending HR Approvals</h2>
    <p style="font-size: 13px; color: #64748b; margin-top: 2px;">
      Review and verify submitted employee ID card designs before approving for printing production.
    </p>
  </div>
</div>

<!-- Instant Search Bar -->
<div class="card" style="margin-bottom: 20px;">
  <div class="card-body" style="padding: 12px 18px;">
    <div style="position: relative; max-width: 480px;">
      <input 
        type="text" 
        id="pending-search-input" 
        class="form-control smart-table-search" 
        data-table-id="pending-table" 
        placeholder="Search employee name (instant filter)..." 
        value="<?= Sanitizer::escape($filters['search'] ?? '') ?>"
        autocomplete="off"
        style="padding-left: 36px;"
      >
      <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 11px; color: #94a3b8; font-size: 13px;"></i>
    </div>
  </div>
</div>

<!-- Clean HR Table: EMPLOYEE FULL NAME | VERSION | SUBMITTED | ACTION -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <i class="fa-solid fa-clock" style="color: #d97706;"></i>
      Pending Approvals Queue (<?= number_format($total) ?> Total)
    </div>
  </div>

  <div class="table-responsive">
    <table id="pending-table" class="data-table">
      <thead>
        <tr>
          <th>Employee Full Name</th>
          <th style="width: 140px;">Version</th>
          <th style="width: 180px;">Submitted</th>
          <th style="text-align: right; width: 140px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cards as $card): ?>
          <tr class="searchable-row" data-search="<?= strtolower(Sanitizer::escape($card->employee_name)) ?>">
            <td data-label="Employee Full Name">
              <div style="font-weight: 700; color: #0b1329; font-size: 14.5px;">
                <?= Sanitizer::escape($card->employee_name) ?>
              </div>
            </td>
            <td data-label="Version">
              <span class="badge badge-warning" style="font-weight: 700;">
                v<?= $card->current_version_number ?>
              </span>
            </td>
            <td data-label="Submitted" style="font-size: 13px; color: #64748b;">
              <?= Timezone::timeAgo($card->updated_at) ?>
            </td>
            <td data-label="Action" style="text-align: right;">
              <a href="/id-cards/<?= $card->id ?>" class="btn btn-primary btn-sm" style="padding: 6px 14px; font-weight: 600;">
                <i class="fa-solid fa-circle-check"></i> Review
              </a>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($cards)): ?>
          <tr class="no-records-row">
            <td colspan="4" style="text-align: center; padding: 40px 20px; color: #64748b;">
              <i class="fa-solid fa-circle-check" style="font-size: 36px; color: #10b981; margin-bottom: 8px; display: block;"></i>
              <div style="font-weight: 700; color: #1e293b;">All Approvals Up to Date</div>
              <div style="font-size: 12.5px; margin-top: 2px;">No employee ID cards are currently waiting for HR review.</div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
