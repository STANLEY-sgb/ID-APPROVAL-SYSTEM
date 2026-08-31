<?php
use Mengo\IdApproval\Models\IdStatus;
use Mengo\IdApproval\Models\Role;
use Mengo\IdApproval\Security\CsrfToken;
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Support\Timezone;

$card = $card ?? $idCard ?? null;
$employee = $employee ?? null;
$versions = $versions ?? $versionList ?? [];
$activeVersion = $activeVersion ?? $currentVersion ?? null;
$approval = $approval ?? $latestApproval ?? null;
$corrections = $corrections ?? $correctionList ?? [];
$pendingCorrection = $pendingCorrection ?? null;
$printRecord = $printRecord ?? $latestPrint ?? null;
$collectionRecord = $collectionRecord ?? $latestCollection ?? null;
$auditLogs = $auditLogs ?? $auditEvents ?? [];
$user = $currentUser ?? null;
$userRole = $user instanceof \Mengo\IdApproval\Models\User ? $user->role : ($user['role'] ?? '');

$status = $card->current_status ?? IdStatus::DRAFT;
$stepIndex = IdStatus::stepIndex($status);
?>

<!-- Breadcrumb & Top Bar -->
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
  <div>
    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">
      <a href="/"><i class="fa-solid fa-house"></i> Home</a> &rsaquo; 
      <span>Employee ID Cards</span> &rsaquo; 
      <strong><?= Sanitizer::escape($card->card_reference) ?></strong>
    </div>
    <h2 style="font-size: 20px; font-weight: 800; color: #0b1329;">
      <?= Sanitizer::escape($card->employee_name) ?>
      <span class="badge <?= IdStatus::badgeClass($status) ?>" style="font-size: 12px; margin-left: 8px; vertical-align: middle;">
        <?= IdStatus::label($status) ?>
      </span>
    </h2>
  </div>

  <div style="display: flex; gap: 8px; flex-wrap: wrap;">
    <a href="/id-cards/<?= $card->id ?>/pdf?download=1" class="btn btn-outline btn-sm" title="Download PDF File">
      <i class="fa-solid fa-download"></i> Download PDF
    </a>

    <?php if ($userRole === Role::HR_MANAGER && $status === IdStatus::PENDING_HR_APPROVAL): ?>
      <button type="button" class="btn btn-danger btn-sm" onclick="openModal('correctionModal')">
        <i class="fa-solid fa-rotate-left"></i> Request Correction
      </button>
      <button type="button" class="btn btn-success btn-sm" onclick="openModal('approvalModal')">
        <i class="fa-solid fa-circle-check"></i> Verify & Approve ID
      </button>
    <?php elseif ($userRole === Role::DESIGNER && $status === IdStatus::CORRECTION_REQUESTED): ?>
      <button type="button" class="btn btn-warning btn-sm" onclick="openModal('reuploadModal')">
        <i class="fa-solid fa-upload"></i> Re-Upload Corrected Design (v<?= $card->current_version_number + 1 ?>)
      </button>
    <?php elseif ($userRole === Role::PRINTING_OFFICER && $status === IdStatus::APPROVED): ?>
      <button type="button" class="btn btn-success btn-sm" onclick="openModal('printModal')">
        <i class="fa-solid fa-print"></i> Mark as Printed
      </button>
    <?php elseif ($userRole === Role::HR_MANAGER && $status === IdStatus::PRINTED): ?>
      <button type="button" class="btn btn-primary btn-sm" onclick="openModal('collectionModal')">
        <i class="fa-solid fa-hand-holding-medical"></i> Record Handover / Collection
      </button>
    <?php endif; ?>
  </div>
</div>

<!-- Visual Lifecycle Timeline -->
<div class="card" style="margin-bottom: 24px; padding: 16px 20px;">
  <div class="timeline-bar">
    <div class="timeline-step <?= $stepIndex >= 2 ? 'done' : ($stepIndex == 1 ? 'current' : '') ?>">
      <div class="timeline-dot"><i class="fa-solid fa-upload"></i></div>
      <div class="timeline-label">1. Uploaded</div>
    </div>
    <div class="timeline-connector <?= $stepIndex >= 3 ? 'done' : '' ?>"></div>

    <div class="timeline-step <?= $stepIndex >= 4 ? 'done' : ($stepIndex == 3 ? ($status === IdStatus::CORRECTION_REQUESTED ? 'current' : 'current') : '') ?>">
      <div class="timeline-dot">
        <?php if ($status === IdStatus::CORRECTION_REQUESTED): ?>
          <i class="fa-solid fa-triangle-exclamation" style="color: #e11d48;"></i>
        <?php else: ?>
          <i class="fa-solid fa-user-check"></i>
        <?php endif; ?>
      </div>
      <div class="timeline-label"><?= $status === IdStatus::CORRECTION_REQUESTED ? 'Correction Req.' : '2. HR Review' ?></div>
    </div>
    <div class="timeline-connector <?= $stepIndex >= 4 ? 'done' : '' ?>"></div>

    <div class="timeline-step <?= $stepIndex >= 4 ? 'done' : '' ?>">
      <div class="timeline-dot"><i class="fa-solid fa-check-double"></i></div>
      <div class="timeline-label">3. Approved</div>
    </div>
    <div class="timeline-connector <?= $stepIndex >= 5 ? 'done' : '' ?>"></div>

    <div class="timeline-step <?= $stepIndex >= 5 ? 'done' : '' ?>">
      <div class="timeline-dot"><i class="fa-solid fa-print"></i></div>
      <div class="timeline-label">4. Printed</div>
    </div>
    <div class="timeline-connector <?= $stepIndex >= 6 ? 'done' : '' ?>"></div>

    <div class="timeline-step <?= $stepIndex >= 6 ? 'done' : '' ?>">
      <div class="timeline-dot"><i class="fa-solid fa-hand-holding-medical"></i></div>
      <div class="timeline-label">5. Collected</div>
    </div>
  </div>
</div>

<!-- Main Split Layout: PDF Viewer (Left) + Progressive Disclosure Details (Right) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 24px;">

  <!-- Left: PDF Previewer Component -->
  <div>
    <div class="card" id="pdf-viewer-card">
      <div class="card-header">
        <div class="card-title">
          <i class="fa-solid fa-file-pdf" style="color: #ef4444;"></i>
          PDF Preview (v<?= $activeVersion->version_number ?? $card->current_version_number ?>)
        </div>
        <div style="display: flex; align-items: center; gap: 6px;">
          <button type="button" class="btn btn-outline btn-sm" onclick="zoomPdf(-0.15)" title="Zoom Out"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
          <span id="zoom-level" style="font-size: 11px; font-weight: 700; min-width: 36px; text-align: center;">100%</span>
          <button type="button" class="btn btn-outline btn-sm" onclick="zoomPdf(0.15)" title="Zoom In"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
          <button type="button" class="btn btn-outline btn-sm" onclick="toggleFullscreen('pdf-viewer-card')" title="Fullscreen"><i class="fa-solid fa-expand"></i></button>
        </div>
      </div>

      <!-- Version Lineage Tabs -->
      <?php if (count($versions) > 1): ?>
        <div style="background-color: #f1f5f9; padding: 6px 12px; display: flex; gap: 6px; border-bottom: 1px solid var(--border-color); overflow-x: auto;">
          <?php foreach ($versions as $ver): ?>
            <a href="/id-cards/<?= $card->id ?>?v=<?= $ver->version_number ?>" class="btn btn-sm <?= ($activeVersion && $activeVersion->version_number == $ver->version_number) ? 'btn-primary' : 'btn-outline' ?>" style="font-size: 11px;">
              Version <?= $ver->version_number ?> <?= $ver->is_approved ? '★ (Approved)' : '' ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="card-body" style="padding: 0; background-color: #334155; position: relative; overflow: hidden; height: 480px; display: flex; align-items: center; justify-content: center;">
        <iframe id="pdf-viewer-frame" src="/id-cards/<?= $card->id ?>/pdf?v=<?= $activeVersion->version_number ?? $card->current_version_number ?>#toolbar=0" style="width: 100%; height: 100%; border: none; background: #ffffff;"></iframe>
      </div>

      <div class="card-header" style="background-color: #f8fafc; font-size: 11.5px; color: #64748b;">
        <div><strong>SHA-256:</strong> <code style="color: #0f172a;"><?= substr($activeVersion->file_sha256 ?? '—', 0, 24) ?>...</code></div>
        <div><strong>Size:</strong> <?= round(($activeVersion->file_size ?? 0) / 1024, 1) ?> KB</div>
      </div>
    </div>
  </div>

  <!-- Right: Progressive Disclosure Accordions -->
  <div>
    <!-- Accordion 1: Employee ID Record Details (Simplified) -->
    <div class="accordion-section">
      <div class="accordion-header active" data-accordion="acc-employee-info" onclick="toggleAccordion('acc-employee-info')">
        <span><i class="fa-solid fa-id-card" style="color: #c59b27; margin-right: 8px;"></i> Employee ID Record Details</span>
        <i class="fa-solid fa-chevron-down accordion-icon"></i>
      </div>
      <div id="acc-employee-info" class="accordion-body open">
        <div style="font-size: 13px;">
          <div style="margin-bottom: 12px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Employee Name</div>
            <div style="font-size: 16px; font-weight: 800; color: #0b1329; margin-top: 2px;">
              <?= Sanitizer::escape($card->employee_name) ?>
            </div>
          </div>

          <div style="display: flex; gap: 16px; align-items: center; padding-top: 10px; border-top: 1px solid var(--border-color);">
            <div>
              <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Status</div>
              <span class="badge <?= IdStatus::badgeClass($status) ?>" style="margin-top: 2px;">
                <?= IdStatus::label($status) ?>
              </span>
            </div>
            <div>
              <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Version</div>
              <div style="font-weight: 700; color: #0b1329; margin-top: 2px;">Version <?= $card->current_version_number ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Accordion 2: HR Verification Checklist & Approval History -->
    <div class="accordion-section">
      <div class="accordion-header active" data-accordion="acc-approval-info" onclick="toggleAccordion('acc-approval-info')">
        <span><i class="fa-solid fa-clipboard-check" style="color: #059669; margin-right: 8px;"></i> HR Verification & Approval Details</span>
        <i class="fa-solid fa-chevron-down accordion-icon"></i>
      </div>
      <div id="acc-approval-info" class="accordion-body open">
        <?php if ($approval): ?>
          <div style="background-color: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
            <div style="font-weight: 800; color: #065f46; font-size: 13.5px;">
              <i class="fa-solid fa-circle-check"></i> Approved by <?= Sanitizer::escape($approval->hr_name) ?>
            </div>
            <div style="font-size: 12px; color: #047857; margin-top: 2px;">
              Approved on <?= Timezone::formatDetailed($approval->approved_at) ?> (Version <?= $approval->version_id ?>)
            </div>
            <?php if (!empty($approval->approval_notes)): ?>
              <div style="font-size: 12px; color: #065f46; margin-top: 6px; padding-top: 6px; border-top: 1px dashed #a7f3d0;">
                <strong>Notes:</strong> <?= Sanitizer::escape($approval->approval_notes) ?>
              </div>
            <?php endif; ?>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12px; color: #334155;">
            <div><i class="fa-solid fa-check" style="color: #059669;"></i> Photo Verified</div>
            <div><i class="fa-solid fa-check" style="color: #059669;"></i> Staff Name Verified</div>
            <div><i class="fa-solid fa-check" style="color: #059669;"></i> Staff ID Format Verified</div>
            <div><i class="fa-solid fa-check" style="color: #059669;"></i> Department & Designation</div>
            <div><i class="fa-solid fa-check" style="color: #059669;"></i> Hospital Logo & Layout</div>
            <div><i class="fa-solid fa-check" style="color: #059669;"></i> Expiry Date Verified</div>
          </div>
        <?php else: ?>
          <div style="color: #64748b; font-size: 13px;">
            <i class="fa-solid fa-clock" style="color: #f59e0b;"></i> Awaiting formal review and verification by an authorized HR Manager.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Accordion 3: Correction History -->
    <?php if (!empty($corrections)): ?>
      <div class="accordion-section">
        <div class="accordion-header active" data-accordion="acc-corrections" onclick="toggleAccordion('acc-corrections')">
          <span><i class="fa-solid fa-rotate-left" style="color: #e11d48; margin-right: 8px;"></i> Correction History (<?= count($corrections) ?>)</span>
          <i class="fa-solid fa-chevron-down accordion-icon"></i>
        </div>
        <div id="acc-corrections" class="accordion-body open">
          <?php foreach ($corrections as $corr): ?>
            <div style="background-color: #fff1f2; border: 1px solid #fecdd3; border-radius: 6px; padding: 10px 14px; margin-bottom: 8px;">
              <div style="display: flex; justify-content: space-between; font-size: 11px; color: #991b1b; font-weight: 700;">
                <span>Requested by <?= Sanitizer::escape($corr->requested_by_name) ?></span>
                <span><?= Timezone::timeAgo($corr->requested_at) ?></span>
              </div>
              <div style="font-size: 12.5px; color: #881337; margin-top: 4px;">
                &ldquo;<?= Sanitizer::escape($corr->reason) ?>&rdquo;
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Accordion 4: Production & Handover Details -->
    <?php if ($printRecord || $collectionRecord): ?>
      <div class="accordion-section">
        <div class="accordion-header active" data-accordion="acc-production" onclick="toggleAccordion('acc-production')">
          <span><i class="fa-solid fa-print" style="color: #2563eb; margin-right: 8px;"></i> Printing & Handover Records</span>
          <i class="fa-solid fa-chevron-down accordion-icon"></i>
        </div>
        <div id="acc-production" class="accordion-body open">
          <?php if ($printRecord): ?>
            <div style="margin-bottom: 12px; font-size: 13px;">
              <div style="font-weight: 700; color: #1e40af;"><i class="fa-solid fa-print"></i> Printed by <?= Sanitizer::escape($printRecord->printing_user_name) ?></div>
              <div style="font-size: 11.5px; color: #64748b;">Printed at: <?= Timezone::formatDetailed($printRecord->printed_at) ?></div>
              <?php if ($printRecord->print_notes): ?>
                <div style="font-size: 12px; color: #475569; margin-top: 2px;">Notes: <?= Sanitizer::escape($printRecord->print_notes) ?></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if ($collectionRecord): ?>
            <div style="padding-top: 10px; border-top: 1px solid var(--border-color); font-size: 13px;">
              <div style="font-weight: 700; color: #065f46;"><i class="fa-solid fa-hand-holding-medical"></i> Collected by <?= Sanitizer::escape($collectionRecord->collected_by_name) ?> (<?= Sanitizer::escape($collectionRecord->collected_by_relationship) ?>)</div>
              <div style="font-size: 11.5px; color: #64748b;">Handed over by HR: <?= Sanitizer::escape($collectionRecord->hr_name) ?> on <?= Timezone::formatDetailed($collectionRecord->collected_at) ?></div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Accordion 5: Complete Immutable Audit Trail -->
    <div class="accordion-section">
      <div class="accordion-header" data-accordion="acc-audit-trail" onclick="toggleAccordion('acc-audit-trail')">
        <span><i class="fa-solid fa-shield-halved" style="color: #64748b; margin-right: 8px;"></i> Immutable Audit Trail (<?= count($auditLogs) ?> Events)</span>
        <i class="fa-solid fa-chevron-down accordion-icon"></i>
      </div>
      <div id="acc-audit-trail" class="accordion-body">
        <div class="audit-timeline">
          <?php foreach ($auditLogs as $log): ?>
            <div class="audit-event">
              <div class="audit-dot" style="background-color: <?= $log->action === 'ID_APPROVED' ? '#059669' : ($log->action === 'CORRECTION_REQUESTED' ? '#e11d48' : ($log->action === 'ID_PRINTED' ? '#2563eb' : '#64748b')) ?>;"></div>
              <div class="audit-content">
                <div style="display: flex; justify-content: space-between; font-size: 11px; color: #64748b;">
                  <strong><?= Sanitizer::escape($log->user_name) ?> (<?= Sanitizer::escape($log->user_role) ?>)</strong>
                  <span><?= Timezone::timeAgo($log->created_at) ?></span>
                </div>
                <div style="font-size: 12.5px; font-weight: 700; color: #0f172a; margin-top: 2px;">
                  <?= Sanitizer::escape($log->action) ?>
                </div>
                <div style="font-size: 12px; color: #475569; margin-top: 2px;">
                  <?= Sanitizer::escape($log->details) ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ACTION MODALS -->

<!-- HR Approval Modal -->
<div id="approvalModal" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-circle-check" style="color: #059669;"></i> Confirm HR Verification & Approval</div>
      <button type="button" onclick="closeModal('approvalModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
    </div>
    <form action="/hr/approve" method="POST">
      <?= CsrfToken::field() ?>
      <input type="hidden" name="id_card_id" value="<?= $card->id ?>">

      <div class="modal-body">
        <p style="font-size: 13px; color: #334155; margin-bottom: 14px;">
          As an authorized HR Manager, verify each element on the ID card before issuing formal approval:
        </p>

        <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px;">
          <label style="display:flex; align-items:center; gap:8px; font-size:13px;">
            <input type="checkbox" name="check_photo" value="1" checked required>
            <span>Employee photograph and name layout verified</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:13px;">
            <input type="checkbox" name="check_name" value="1" checked required>
            <span>Employee Name spelling matches official hospital records</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:13px;">
            <input type="checkbox" name="check_layout" value="1" checked required>
            <span>Mengo Hospital card layout and branding confirmed</span>
          </label>
        </div>

        <div class="form-group">
          <label class="form-label">Approval Notes / Verification Remarks (Optional)</label>
          <input type="text" name="approval_notes" class="form-control" placeholder="e.g. Verified against HR master file">
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
          <button type="button" class="btn btn-outline" onclick="closeModal('approvalModal')">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="fa-solid fa-check"></i> Grant Approval</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- HR Correction Modal -->
<div id="correctionModal" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-triangle-exclamation" style="color: #e11d48;"></i> Request Design Correction</div>
      <button type="button" onclick="closeModal('correctionModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
    </div>
    <form action="/hr/request-correction" method="POST">
      <?= CsrfToken::field() ?>
      <input type="hidden" name="id_card_id" value="<?= $card->id ?>">

      <div class="modal-body">
        <p style="font-size: 13px; color: #64748b; margin-bottom: 14px;">
          Provide specific instructions for the ID Designer regarding necessary changes (e.g. typo in name, wrong designation, expired validity):
        </p>

        <div class="form-group">
          <label class="form-label">Correction Remarks (Mandatory) *</label>
          <textarea name="correction_remarks" class="form-control" rows="4" placeholder="Describe precisely what needs to be amended..." required></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
          <button type="button" class="btn btn-outline" onclick="closeModal('correctionModal')">Cancel</button>
          <button type="submit" class="btn btn-danger"><i class="fa-solid fa-paper-plane"></i> Submit Correction Request</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Designer Re-Upload Modal -->
<div id="reuploadModal" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-upload" style="color: #d97706;"></i> Re-Upload Corrected Design PDF</div>
      <button type="button" onclick="closeModal('reuploadModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
    </div>
    <form action="/designer/reupload" method="POST" enctype="multipart/form-data">
      <?= CsrfToken::field() ?>
      <input type="hidden" name="id_card_id" value="<?= $card->id ?>">

      <div class="modal-body">
        <div style="background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px; margin-bottom: 16px; font-size: 12.5px; color: #92400e;">
          <strong>Previous Version:</strong> v<?= $card->current_version_number ?> is preserved immutably. Uploading will create <strong>Version <?= $card->current_version_number + 1 ?></strong> and reset status to <strong>Pending HR Approval</strong>.
        </div>

        <div class="form-group">
          <label class="form-label">Corrected PDF File *</label>
          <input type="file" name="id_pdf" class="form-control" accept="application/pdf" required>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
          <button type="button" class="btn btn-outline" onclick="closeModal('reuploadModal')">Cancel</button>
          <button type="submit" class="btn btn-warning"><i class="fa-solid fa-upload"></i> Submit Version <?= $card->current_version_number + 1 ?></button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Printing Officer Modal -->
<div id="printModal" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-print"></i> Confirm ID Card Printing</div>
      <button type="button" onclick="closeModal('printModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
    </div>
    <form action="/printing/mark-printed" method="POST">
      <?= CsrfToken::field() ?>
      <input type="hidden" name="id_card_id" value="<?= $card->id ?>">

      <div class="modal-body">
        <p style="font-size: 13.5px; color: #334155; margin-bottom: 16px;">
          Confirm that employee ID card for <strong><?= Sanitizer::escape($card->employee_name) ?></strong> has been physically printed.
        </p>

        <div class="form-group">
          <label class="form-label">Printer / Equipment Notes</label>
          <input type="text" name="print_notes" class="form-control" placeholder="e.g. Card Zebra Printer #1">
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
          <button type="button" class="btn btn-outline" onclick="closeModal('printModal')">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="fa-solid fa-check"></i> Mark as Printed</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Collection Handover Modal -->
<div id="collectionModal" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-hand-holding-medical"></i> Record Employee ID Handover</div>
      <button type="button" onclick="closeModal('collectionModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
    </div>
    <form action="/hr/mark-collected" method="POST">
      <?= CsrfToken::field() ?>
      <input type="hidden" name="id_card_id" value="<?= $card->id ?>">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Recipient Full Name *</label>
          <input type="text" name="collected_by_name" class="form-control" value="<?= Sanitizer::escape($card->employee_name) ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label">Relationship to Staff Member</label>
          <select name="collected_by_relationship" class="form-control">
            <option value="SELF">Employee In-Person (Self)</option>
            <option value="DEPARTMENT_REP">Department Representative / Head</option>
            <option value="AUTHORIZED_PERSON">Authorized Third Party</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Recipient Contact / Phone</label>
          <input type="text" name="recipient_contact" class="form-control" placeholder="e.g. +256 700 123 456">
        </div>

        <div class="form-group">
          <label class="form-label">Handover Reference / Notes</label>
          <input type="text" name="collection_notes" class="form-control" placeholder="e.g. Collected at HR front desk">
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
          <button type="button" class="btn btn-outline" onclick="closeModal('collectionModal')">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-box-archive"></i> Mark as Collected</button>
        </div>
      </div>
    </form>
  </div>
</div>
