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
    <h2 style="font-size: 20px; font-weight: 800; color: #0b1329;">Ready for Printing</h2>
    <p style="font-size: 13px; color: #64748b; margin-top: 2px;">
      HR-approved employee ID cards ready for consolidation, high-quality merge, and physical printing.
    </p>
  </div>

  <div style="display: flex; gap: 10px;">
    <a href="/printing/batches" class="btn btn-outline btn-sm">
      <i class="fa-solid fa-clock-rotate-left"></i> Batch History
    </a>
  </div>
</div>

<!-- Instant Search Bar -->
<div class="card" style="margin-bottom: 20px;">
  <div class="card-body" style="padding: 12px 18px;">
    <div style="position: relative; max-width: 480px;">
      <input 
        type="text" 
        id="ready-print-search-input" 
        class="form-control smart-table-search" 
        data-table-id="ready-print-table" 
        placeholder="Search employee name (instant filter)..." 
        value="<?= Sanitizer::escape($filters['search'] ?? '') ?>"
        autocomplete="off"
        style="padding-left: 36px;"
      >
      <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 11px; color: #94a3b8; font-size: 13px;"></i>
    </div>
  </div>
</div>

<!-- Clean Printing Table: [✓] | EMPLOYEE FULL NAME | APPROVED VERSION | APPROVAL TIME | ACTION -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <i class="fa-solid fa-print" style="color: #059669;"></i>
      Approved Production Queue (<?= number_format($total) ?> Total)
    </div>
    <div style="font-size: 12px; color: #64748b;">
      Select cards below to run consolidated PDF merge and physical print runs
    </div>
    <div id="select-all-matching-banner" style="display: none; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 8px 12px; margin-top: 8px; font-size: 12px; color: #92400e;">
      All <?= count($cards) ?> visible IDs on this page are selected. 
      <a href="#" id="btn-select-all-matching" style="font-weight: 700; color: #b45309; text-decoration: underline;">Select all <?= number_format($total) ?> IDs matching current filters</a>
    </div>
    <div id="all-matching-selected-banner" style="display: none; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; padding: 8px 12px; margin-top: 8px; font-size: 12px; color: #065f46;">
      All <?= number_format($total) ?> IDs matching current filters are selected. 
      <a href="#" id="btn-clear-all-matching" style="font-weight: 700; color: #047857; text-decoration: underline;">Clear selection</a>
    </div>
  </div>

  <div class="table-responsive">
    <table id="ready-print-table" class="data-table">
      <thead>
        <tr>
          <th style="width: 40px; text-align: center;">
            <input type="checkbox" id="select-all-cards" title="Select All Visible Approved Cards">
          </th>
          <th>Employee Full Name</th>
          <th style="width: 150px;">Approved Version</th>
          <th style="width: 180px;">Approval Time</th>
          <th style="text-align: right; width: 140px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cards as $card): ?>
          <tr class="searchable-row" data-search="<?= strtolower(Sanitizer::escape($card->employee_name)) ?>">
            <td data-label="Select" style="text-align: center;">
              <input type="checkbox" class="card-select-checkbox" value="<?= $card->id ?>" data-name="<?= Sanitizer::escape($card->employee_name) ?>">
            </td>
            <td data-label="Employee Full Name">
              <div style="font-weight: 700; color: #0b1329; font-size: 14.5px;">
                <?= Sanitizer::escape($card->employee_name) ?>
              </div>
            </td>
            <td data-label="Approved Version">
              <span class="badge badge-success" style="font-weight: 700;">
                v<?= $card->current_version_number ?> (Approved)
              </span>
            </td>
            <td data-label="Approval Time" style="font-size: 13px; color: #64748b;">
              <?= Timezone::timeAgo($card->updated_at) ?>
            </td>
            <td data-label="Action" style="text-align: right;">
              <div style="display: inline-flex; gap: 6px;">
                <a href="/id-cards/<?= $card->id ?>" class="btn btn-outline btn-sm" style="padding: 5px 12px; font-weight: 600;" title="View Verification & PDF">
                  <i class="fa-solid fa-eye"></i> Details
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($cards)): ?>
          <tr class="no-records-row">
            <td colspan="5" style="text-align: center; padding: 48px 20px; color: #64748b;">
              <i class="fa-solid fa-circle-check" style="font-size: 40px; color: #10b981; margin-bottom: 10px; display: block;"></i>
              <div style="font-size: 15px; font-weight: 700; color: #1e293b;">Queue is Clean</div>
              <div style="font-size: 13px; margin-top: 2px;">All approved employee ID cards have been processed and printed.</div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Sticky Batch Action Bar -->
<div id="batch-action-bar" class="batch-action-bar">
  <div class="batch-info-text">
    <span id="batch-selected-count" class="batch-count-pill">0</span>
    <span>IDs selected for batch printing</span>
  </div>
  <div style="display: flex; gap: 10px;">
    <button type="button" class="btn btn-outline btn-sm" style="color: #ffffff; border-color: #475569;" onclick="BatchPrinter.clearSelection()">
      <i class="fa-solid fa-xmark"></i> Clear
    </button>
    <button type="button" class="btn btn-success btn-sm" onclick="BatchPrinter.startBatchReview()">
      <i class="fa-solid fa-layer-group"></i> MERGE &amp; PRINT SELECTED
    </button>
  </div>
</div>

<!-- 1. BATCH REVIEW & VALIDATION MODAL -->
<div id="batchReviewModal" class="modal-overlay">
  <div class="modal-box" style="max-width: 640px;">
    <div class="modal-header">
      <div class="modal-title">
        <i class="fa-solid fa-clipboard-check" style="color: #c59b27;"></i>
        Review Print Batch
      </div>
      <button type="button" onclick="closeModal('batchReviewModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
    </div>

    <div class="modal-body">
      <!-- Loading / Validating State -->
      <div id="batch-validating-spinner" style="text-align: center; padding: 32px 0;">
        <i class="fa-solid fa-spinner fa-spin" style="font-size: 36px; color: #c59b27; margin-bottom: 12px; display: block;"></i>
        <div style="font-weight: 700; color: #1e293b;">Validating Selected Documents...</div>
        <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Checking approved versions, SHA-256 hashes, and PDF structures</div>
      </div>

      <!-- Validated Content Summary -->
      <div id="batch-review-content" style="display: none;">
        <!-- Metrics Grid -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px;">
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Total IDs</div>
            <div id="val-total-count" style="font-size: 20px; font-weight: 800; color: #0b1329;">0</div>
          </div>
          <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 12px; text-align: center;">
            <div style="font-size: 11px; font-weight: 700; color: #065f46; text-transform: uppercase;">Valid PDFs</div>
            <div id="val-valid-count" style="font-size: 20px; font-weight: 800; color: #059669;">0</div>
          </div>
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Pages</div>
            <div id="val-pages-count" style="font-size: 20px; font-weight: 800; color: #2563eb;">0</div>
          </div>
        </div>

        <!-- Failure Report Alert (if any failed) -->
        <div id="val-failures-box" style="display: none; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 8px; padding: 14px; margin-bottom: 18px;">
          <div style="font-weight: 800; color: #991b1b; font-size: 13.5px; margin-bottom: 6px;">
            <i class="fa-solid fa-triangle-exclamation"></i> <span id="val-failed-count">0</span> Document(s) Excluded
          </div>
          <div id="val-failed-list" style="font-size: 12px; color: #881337;"></div>
          <div style="font-size: 11.5px; color: #991b1b; margin-top: 8px; font-weight: 600;">
            The valid PDFs will proceed in the consolidated print batch. Excluded documents will remain untouched.
          </div>
        </div>

        <!-- Page Orientation & Size Info -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-size: 12.5px; font-weight: 700;">Page Orientation</label>
          <select id="batch-orientation-select" class="form-control">
            <option value="ORIGINAL" selected>Preserve Original Orientation (Recommended)</option>
            <option value="PORTRAIT">Force Portrait</option>
            <option value="LANDSCAPE">Force Landscape</option>
          </select>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; padding-top: 14px; border-top: 1px solid var(--border-color);">
          <button type="button" class="btn btn-outline" onclick="closeModal('batchReviewModal')">Cancel</button>
          <button type="button" id="btn-execute-merge" class="btn btn-success" onclick="BatchPrinter.executeMerge()">
            <i class="fa-solid fa-file-pdf"></i> Generate Merged PDF
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 2. MERGE PROGRESS MODAL -->
<div id="batchProgressModal" class="modal-overlay">
  <div class="modal-box" style="max-width: 480px; text-align: center; padding: 24px;">
    <i class="fa-solid fa-gear fa-spin" style="font-size: 40px; color: #c59b27; margin-bottom: 16px; display: block;"></i>
    <h3 style="font-size: 16px; font-weight: 800; color: #0b1329; margin-bottom: 6px;">Merging ID Card PDFs...</h3>
    <p id="merge-progress-status" style="font-size: 13px; color: #64748b; margin-bottom: 18px;">Preparing files and remapping vector streams...</p>
    <div style="background: #f1f5f9; border-radius: 99px; height: 8px; overflow: hidden;">
      <div id="merge-progress-bar" style="background: #c59b27; height: 100%; width: 45%; transition: width 0.3s ease;"></div>
    </div>
  </div>
</div>

<!-- 3. BATCH PREVIEW & CONFIRMATION MODAL -->
<div id="batchPreviewModal" class="modal-overlay">
  <div class="modal-box" style="max-width: 880px; width: 95%;">
    <div class="modal-header">
      <div class="modal-title">
        <i class="fa-solid fa-print" style="color: #059669;"></i>
        Batch Print Preview &amp; Confirmation — <span id="preview-batch-ref" style="font-family: monospace;"></span>
      </div>
      <button type="button" onclick="closeModal('batchPreviewModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
    </div>

    <div class="modal-body" style="padding: 16px;">
      <!-- Top Batch Info Ribbon -->
      <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 16px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
        <div style="font-size: 13px; color: #334155;">
          <strong><span id="preview-total-pages">0</span> Pages</strong> · <span id="preview-file-size">0 MB</span> · Print-Ready PDF
        </div>
        <div style="display: flex; gap: 8px;">
          <a id="preview-download-btn" href="#" target="_blank" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-download"></i> Download Merged PDF
          </a>
        </div>
      </div>

      <!-- PDF Preview Iframe -->
      <div style="height: 480px; background: #334155; border-radius: 8px; overflow: hidden; margin-bottom: 16px;">
        <iframe id="batch-preview-iframe" src="about:blank" style="width: 100%; height: 100%; border: none; background: #ffffff;"></iframe>
      </div>

      <!-- Physical Print Confirmation Section -->
      <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 14px 18px; margin-bottom: 16px;">
        <div style="font-weight: 800; color: #065f46; font-size: 14px; margin-bottom: 4px;">
          <i class="fa-solid fa-shield-check"></i> Physical Printing Handshake
        </div>
        <div style="font-size: 12px; color: #047857;">
          Confirming printing will mark the selected employee IDs as <strong>PRINTED</strong> in the hospital database, notify HR Managers for card handover, and log the batch in the immutable audit trail.
        </div>
      </div>

      <form action="/printing/batches/confirm-print" method="POST">
        <?= CsrfToken::field() ?>
        <input type="hidden" name="batch_id" id="confirm-batch-id" value="">
        <div id="confirm-hidden-card-inputs"></div>

        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-size: 12px;">Printer Equipment / Production Notes (Optional)</label>
          <input type="text" name="print_notes" class="form-control" placeholder="e.g. Zebra ID Card Printer #1 - Front & Back PVC verified">
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px;">
          <button type="button" class="btn btn-outline" onclick="closeModal('batchPreviewModal')">Close</button>
          <button type="submit" class="btn btn-success">
            <i class="fa-solid fa-check-double"></i> CONFIRM PHYSICAL PRINTING
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const BatchPrinter = {
  selectedIds: new Set(),
  currentBatchId: null,
  currentValidation: null,
  selectAllMatching: false,
  totalCount: <?= (int)$total ?>,
  pageCount: <?= count($cards) ?>,

  init() {
    const selectAll = document.getElementById('select-all-cards');
    const checkboxes = document.querySelectorAll('.card-select-checkbox');
    const selectAllBtn = document.getElementById('btn-select-all-matching');
    const clearAllBtn = document.getElementById('btn-clear-all-matching');

    if (selectAll) {
      selectAll.addEventListener('change', (e) => {
        const checked = e.target.checked;
        checkboxes.forEach(cb => {
          cb.checked = checked;
          const id = parseInt(cb.value, 10);
          if (checked) this.selectedIds.add(id);
          else this.selectedIds.delete(id);
        });

        this.selectAllMatching = false;
        const banner = document.getElementById('select-all-matching-banner');
        const selectedBanner = document.getElementById('all-matching-selected-banner');

        if (checked && this.totalCount > this.pageCount) {
          if (banner) banner.style.display = 'block';
        } else {
          if (banner) banner.style.display = 'none';
          if (selectedBanner) selectedBanner.style.display = 'none';
        }

        this.updateUi();
      });
    }

    checkboxes.forEach(cb => {
      cb.addEventListener('change', (e) => {
        const id = parseInt(e.target.value, 10);
        if (e.target.checked) this.selectedIds.add(id);
        else this.selectedIds.delete(id);

        // Turn off select-all-matching if any individual checkbox is unchecked
        this.selectAllMatching = false;
        document.getElementById('select-all-matching-banner').style.display = 'none';
        document.getElementById('all-matching-selected-banner').style.display = 'none';

        if (selectAll) {
          selectAll.checked = this.selectedIds.size === checkboxes.length && checkboxes.length > 0;
        }
        this.updateUi();
      });
    });

    if (selectAllBtn) {
      selectAllBtn.addEventListener('click', (e) => {
        e.preventDefault();
        this.selectAllMatching = true;
        document.getElementById('select-all-matching-banner').style.display = 'none';
        document.getElementById('all-matching-selected-banner').style.display = 'block';
        this.updateUi();
      });
    }

    if (clearAllBtn) {
      clearAllBtn.addEventListener('click', (e) => {
        e.preventDefault();
        this.clearSelection();
      });
    }
  },

  getFilters() {
    const urlParams = new URLSearchParams(window.location.search);
    const searchVal = urlParams.get('search') || '';
    const deptVal = urlParams.get('department_id') || '';
    return { search: searchVal, department_id: deptVal };
  },

  updateUi() {
    const count = this.selectAllMatching ? this.totalCount : this.selectedIds.size;
    const bar = document.getElementById('batch-action-bar');
    const pill = document.getElementById('batch-selected-count');

    if (pill) pill.textContent = count;
    if (bar) {
      if (count > 0) bar.classList.add('visible');
      else bar.classList.remove('visible');
    }
  },

  clearSelection() {
    this.selectedIds.clear();
    this.selectAllMatching = false;
    document.querySelectorAll('.card-select-checkbox').forEach(cb => cb.checked = false);
    const master = document.getElementById('select-all-cards');
    if (master) master.checked = false;
    document.getElementById('select-all-matching-banner').style.display = 'none';
    document.getElementById('all-matching-selected-banner').style.display = 'none';
    this.updateUi();
  },

  async startBatchReview() {
    const count = this.selectAllMatching ? this.totalCount : this.selectedIds.size;
    if (count === 0) {
      alert('Please select at least one approved ID card.');
      return;
    }

    openModal('batchReviewModal');
    document.getElementById('batch-validating-spinner').style.display = 'block';
    document.getElementById('batch-review-content').style.display = 'none';

    try {
      const formData = new FormData();
      if (this.selectAllMatching) {
        const filters = this.getFilters();
        formData.append('select_all_matching', '1');
        formData.append('search', filters.search);
        formData.append('department_id', filters.department_id);
      } else {
        this.selectedIds.forEach(id => formData.append('selected_card_ids[]', id));
      }
      formData.append('create_batch', '1');

      const response = await fetch('/printing/batches/validate', {
        method: 'POST',
        body: formData
      });

      const res = await response.json();
      if (!res.success) {
        throw new Error(res.error || 'Validation failed');
      }

      this.currentBatchId = res.batch_id;
      this.currentValidation = res.validation;

      // Populate review modal
      document.getElementById('val-total-count').textContent = res.validation.total_selected;
      document.getElementById('val-valid-count').textContent = res.validation.valid_count;
      document.getElementById('val-pages-count').textContent = res.validation.total_pages;

      const failBox = document.getElementById('val-failures-box');
      const failList = document.getElementById('val-failed-list');
      const failCount = document.getElementById('val-failed-count');

      if (res.validation.failed_count > 0) {
        failCount.textContent = res.validation.failed_count;
        failList.innerHTML = res.validation.failed_items.map(f => `<div>• <strong>${f.employee_name}</strong>: ${f.reason}</div>`).join('');
        failBox.style.display = 'block';
      } else {
        failBox.style.display = 'none';
      }

      document.getElementById('batch-validating-spinner').style.display = 'none';
      document.getElementById('batch-review-content').style.display = 'block';
    } catch (err) {
      alert('Validation Error: ' + err.message);
      closeModal('batchReviewModal');
    }
  },

  async executeMerge() {
    closeModal('batchReviewModal');
    openModal('batchProgressModal');

    const progressBar = document.getElementById('merge-progress-bar');
    const statusText = document.getElementById('merge-progress-status');
    const orientation = document.getElementById('batch-orientation-select').value;

    progressBar.style.width = '60%';
    statusText.textContent = 'Merging vector streams and building pages catalog...';

    try {
      const formData = new FormData();
      formData.append('batch_id', this.currentBatchId);
      formData.append('orientation', orientation);
      if (this.selectAllMatching) {
        const filters = this.getFilters();
        formData.append('select_all_matching', '1');
        formData.append('search', filters.search);
        formData.append('department_id', filters.department_id);
      }

      const response = await fetch('/printing/batches/merge', {
        method: 'POST',
        body: formData
      });

      const res = await response.json();
      if (!res.success) {
        throw new Error(res.error || 'PDF Merge failed');
      }

      progressBar.style.width = '100%';
      statusText.textContent = 'Consolidated PDF ready!';

      setTimeout(() => {
        closeModal('batchProgressModal');
        this.showPreview(res);
      }, 500);
    } catch (err) {
      closeModal('batchProgressModal');
      alert('Merge Error: ' + err.message);
    }
  },

  showPreview(mergeData) {
    document.getElementById('preview-batch-ref').textContent = mergeData.batch_reference;
    document.getElementById('preview-total-pages').textContent = mergeData.page_count;
    document.getElementById('preview-file-size').textContent = mergeData.file_size_formatted;
    document.getElementById('batch-preview-iframe').src = mergeData.preview_url;
    document.getElementById('preview-download-btn').href = mergeData.download_url;

    document.getElementById('confirm-batch-id').value = mergeData.batch_id;
    const cardInputs = document.getElementById('confirm-hidden-card-inputs');
    cardInputs.innerHTML = '';

    if (this.currentValidation && this.currentValidation.valid_items) {
      this.currentValidation.valid_items.forEach(item => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'confirmed_card_ids[]';
        inp.value = item.id_card_id;
        cardInputs.appendChild(inp);
      });
    }

    openModal('batchPreviewModal');
  }
};

document.addEventListener('DOMContentLoaded', () => {
  BatchPrinter.init();
});
</script>
