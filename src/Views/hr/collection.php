<?php
use Mengo\IdApproval\Security\CsrfToken;
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Support\Timezone;

$cardsList = $cards ?? [];
?>

<!-- Instant Search Bar -->
<div class="card" style="margin-bottom: 20px;">
  <div class="card-body" style="padding: 12px 18px;">
    <div style="position: relative; max-width: 480px;">
      <input 
        type="text" 
        id="hr-collection-search-input" 
        class="form-control smart-table-search" 
        data-table-id="hr-collection-table" 
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
      <i class="fa-solid fa-hand-holding-medical" style="color: #059669;"></i>
      ID Collection Center (<?= count($cardsList) ?> Awaiting Handover)
    </div>
    <div style="font-size: 12px; color: #64748b;">Verify staff member identity and signature before confirming handover</div>
  </div>

  <div class="table-responsive">
    <table id="hr-collection-table" class="data-table">
      <thead>
        <tr>
          <th>Employee Full Name</th>
          <th style="width: 180px;">Printed Date</th>
          <th style="width: 180px;">Status</th>
          <th style="text-align: right; width: 220px;">Action</th>
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
            <td data-label="Printed Date" style="font-size: 13px; color: #64748b;">
              <?= Timezone::timeAgo($c->updated_at) ?>
            </td>
            <td data-label="Status">
              <span class="badge badge-info">
                <i class="fa-solid fa-box-archive"></i> Ready for Handover
              </span>
            </td>
            <td data-label="Action" style="text-align: right;">
              <div style="display: inline-flex; gap: 6px;">
                <a href="/id-cards/<?= $c->id ?>" class="btn btn-outline btn-sm" style="padding: 5px 10px; font-weight: 600;">
                  <i class="fa-solid fa-eye"></i> Details
                </a>
                <button type="button" class="btn btn-success btn-sm" style="padding: 5px 12px; font-weight: 600;"
                  onclick="openCollectionModal(<?= $c->id ?>, '<?= Sanitizer::escape(addslashes($c->employee_name)) ?>')">
                  <i class="fa-solid fa-signature"></i> Mark Collected
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($cardsList)): ?>
          <tr class="no-records-row">
            <td colspan="4" style="text-align: center; color: #64748b; padding: 36px 20px;">
              <i class="fa-solid fa-circle-check" style="font-size: 32px; color: #10b981; display: block; margin-bottom: 8px;"></i>
              <div style="font-weight: 700;">No ID Cards Currently Awaiting Collection</div>
              <div style="font-size: 12px; margin-top: 2px;">Newly printed employee ID cards will appear here.</div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Confirm Handover / Collection -->
<div id="collectionModal" class="modal-overlay">
  <div class="modal-box" style="max-width: 500px;">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-signature" style="color: #059669;"></i> Confirm Employee ID Card Handover</div>
      <button type="button" onclick="closeModal('collectionModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
    </div>
    <form action="/hr/collection/mark-collected" method="POST">
      <?= CsrfToken::field() ?>
      <input type="hidden" name="id_card_id" id="collectCardId">
      <div class="modal-body">
        <p style="font-size: 14px; margin-bottom: 16px;">
          Confirming ID card handover for: <strong id="collectEmployeeName"></strong>
        </p>

        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label">Collected By (Full Name / Staff Representative)</label>
          <input type="text" name="collected_by_name" class="form-control" placeholder="e.g. Employee self or authorized rep">
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label">Collector Phone / Contact</label>
          <input type="text" name="collected_by_phone" class="form-control" placeholder="e.g. +256 700 000 000">
        </div>

        <div class="form-group">
          <label class="form-label">Handover Notes / Physical Signature Reference</label>
          <textarea name="collection_notes" class="form-control" rows="2" placeholder="Signed physically in hospital register book #4, Page 12"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('collectionModal')">Cancel</button>
        <button type="submit" class="btn btn-success"><i class="fa-solid fa-check"></i> Complete Handover</button>
      </div>
    </form>
  </div>
</div>

<script>
function openCollectionModal(id, name) {
  document.getElementById('collectCardId').value = id;
  document.getElementById('collectEmployeeName').textContent = name;
  openModal('collectionModal');
}
</script>
