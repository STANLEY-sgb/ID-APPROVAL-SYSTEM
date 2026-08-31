<?php
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Support\Timezone;

$logs = $auditLogs ?? [];
$filtersList = $filters ?? [];
$currentPage = $page ?? 1;
$pages = $totalPages ?? 1;
?>

<div class="card" style="margin-bottom: 20px;">
  <div class="card-body" style="padding: 16px 20px;">
    <form method="GET" action="/audit-logs" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
      <div style="flex: 1; min-width: 220px; position: relative;">
        <input 
          type="text" 
          name="search" 
          id="audit-search-input"
          class="form-control smart-table-search" 
          data-table-id="audit-log-table"
          placeholder="Search by action, actor, or description (instant)..." 
          value="<?= Sanitizer::escape($filtersList['search'] ?? '') ?>"
          autocomplete="off"
          style="padding-left: 36px;"
        >
        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 11px; color: #94a3b8; font-size: 13px;"></i>
      </div>

      <div style="width: 200px;">
        <select name="action" class="form-control" onchange="this.form.submit()">
          <option value="">-- All Actions --</option>
          <?php
          $actions = ['LOGIN', 'LOGOUT', 'ID_UPLOADED', 'ID_APPROVED', 'CORRECTION_REQUESTED', 'ID_REUPLOADED', 'ID_PRINTED', 'ID_COLLECTED', 'BACKUP_CREATED', 'PASSWORD_CHANGED'];
          foreach ($actions as $a): ?>
            <option value="<?= $a ?>" <?= ($filtersList['action'] ?? '') === $a ? 'selected' : '' ?>><?= $a ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="width: 180px;">
        <input type="date" name="date_from" class="form-control" value="<?= Sanitizer::escape($filtersList['date_from'] ?? '') ?>" placeholder="From date">
      </div>
      <div style="width: 180px;">
        <input type="date" name="date_to" class="form-control" value="<?= Sanitizer::escape($filtersList['date_to'] ?? '') ?>" placeholder="To date">
      </div>

      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
      <a href="/audit-logs" class="btn btn-outline btn-sm">Reset</a>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">
      <i class="fa-solid fa-shield-halved"></i> System Audit Log (<?= $total ?? count($logs) ?> Events)
    </div>
    <span style="font-size: 12px; color: #64748b;">Immutable, tamper-proof event ledger</span>
  </div>

  <div class="table-responsive">
    <table id="audit-log-table" class="data-table">
      <thead>
        <tr>
          <th style="width: 160px;">Timestamp (EAT)</th>
          <th style="width: 180px;">Action</th>
          <th style="width: 160px;">Actor</th>
          <th style="width: 120px;">Role</th>
          <th>Description</th>
          <th style="width: 130px;">IP Address</th>
          <th style="text-align: right; width: 90px;">Record</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr class="no-records-row">
            <td colspan="7" style="text-align: center; color: #64748b; padding: 32px;">No audit events found matching filter.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($logs as $log): ?>
            <?php $searchData = strtolower(($log->action ?? '') . ' ' . ($log->actor_name ?? '') . ' ' . ($log->actor_role ?? '') . ' ' . ($log->description ?? '') . ' ' . ($log->ip_address ?? '')); ?>
            <tr class="searchable-row" data-search="<?= Sanitizer::escape($searchData) ?>">
              <td style="white-space: nowrap; font-size: 12px; font-family: monospace;">
                <?= Timezone::format($log->created_at, 'd M Y H:i:s') ?>
              </td>
              <td>
                <span class="badge <?php
                  echo match(true) {
                    str_contains($log->action ?? '', 'APPROVE') => 'badge-success',
                    str_contains($log->action ?? '', 'CORRECTION') => 'badge-warning',
                    str_contains($log->action ?? '', 'PRINT') => 'badge-info',
                    str_contains($log->action ?? '', 'COLLECT') => 'badge-secondary',
                    default => 'badge-secondary'
                  };
                ?>"><?= Sanitizer::escape($log->action ?? '—') ?></span>
              </td>
              <td style="font-weight: 600;"><?= Sanitizer::escape($log->actor_name ?? '—') ?></td>
              <td style="font-size: 12px;"><?= Sanitizer::escape($log->actor_role ?? '—') ?></td>
              <td style="font-size: 12.5px; max-width: 280px;"><?= Sanitizer::escape($log->description ?? '—') ?></td>
              <td style="font-size: 12px; font-family: monospace; color: #64748b;"><?= Sanitizer::escape($log->ip_address ?? '—') ?></td>
              <td style="text-align: right;">
                <?php if ($log->id_card_id): ?>
                  <a href="/id-cards/<?= $log->id_card_id ?>" class="btn btn-outline btn-sm" style="font-size: 11px; padding: 3px 8px;">
                    <i class="fa-solid fa-eye"></i> View
                  </a>
                <?php else: ?>
                  <span style="color: #94a3b8;">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
    <div style="padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color);">
      <div style="font-size: 12px; color: #64748b;">Page <?= $currentPage ?> of <?= $pages ?></div>
      <div style="display: flex; gap: 6px;">
        <?php if ($currentPage > 1): ?>
          <a href="?page=<?= $currentPage - 1 ?>&search=<?= urlencode($filtersList['search'] ?? '') ?>&action=<?= urlencode($filtersList['action'] ?? '') ?>" class="btn btn-outline btn-sm">&laquo; Prev</a>
        <?php endif; ?>
        <?php if ($currentPage < $pages): ?>
          <a href="?page=<?= $currentPage + 1 ?>&search=<?= urlencode($filtersList['search'] ?? '') ?>&action=<?= urlencode($filtersList['action'] ?? '') ?>" class="btn btn-outline btn-sm">Next &raquo;</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
