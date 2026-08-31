<?php
use Mengo\IdApproval\Models\IdStatus;
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Support\Timezone;

$cardsList = $cards ?? [];
$filtersList = $filters ?? [];
$deptList = $departments ?? [];
$currentPage = $page ?? 1;
$pages = $totalPages ?? 1;
?>

<!-- Search & Filters -->
<div class="card" style="margin-bottom: 20px;">
  <div class="card-body" style="padding: 14px 18px;">
    <form method="GET" action="/designer/my-ids" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
      <div style="flex: 2; min-width: 200px;">
        <input 
          type="text" 
          name="search" 
          id="my-ids-search-input"
          class="form-control smart-table-search" 
          data-table-id="my-ids-table"
          placeholder="Search employee name (instant filter)..." 
          value="<?= Sanitizer::escape($filtersList['search'] ?? '') ?>"
          autocomplete="off"
        >
      </div>

      <div style="flex: 1; min-width: 160px;">
        <select name="status" class="form-control" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          <?php foreach (IdStatus::all() as $st): ?>
            <option value="<?= $st ?>" <?= ($filtersList['status'] ?? '') === $st ? 'selected' : '' ?>>
              <?= IdStatus::label($st) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display: flex; gap: 8px;">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="/designer/my-ids" class="btn btn-outline btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">
      <i class="fa-solid fa-id-card" style="color: #c59b27;"></i>
      My ID Submissions (<?= number_format($total ?? count($cardsList)) ?> Total)
    </div>
    <a href="/designer/create" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-plus-circle"></i> Create New ID
    </a>
  </div>

  <div class="table-responsive">
    <table id="my-ids-table" class="data-table">
      <thead>
        <tr>
          <th>Employee Full Name</th>
          <th>Status</th>
          <th>Last Updated</th>
          <th style="text-align: right;">Action</th>
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
            <td data-label="Status">
              <span class="badge <?= IdStatus::badgeClass($c->current_status) ?>">
                <?= IdStatus::label($c->current_status) ?>
              </span>
            </td>
            <td data-label="Last Updated" style="font-size: 12.5px; color: #64748b;">
              <?= Timezone::timeAgo($c->updated_at) ?>
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
            <td colspan="4" style="text-align: center; color: #64748b; padding: 36px 20px;">
              <i class="fa-solid fa-cloud-arrow-up" style="font-size: 32px; color: #94a3b8; display: block; margin-bottom: 8px;"></i>
              <div style="font-weight: 700;">No Submissions Found</div>
              <div style="font-size: 12px; margin-top: 2px;">Upload an ID card design to get started.</div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
