<?php
use Mengo\IdApproval\Security\CsrfToken;
use Mengo\IdApproval\Security\Sanitizer;
?>

<div class="card" style="max-width: 680px; margin: 0 auto;">
  <div class="card-header">
    <div class="card-title">
      <i class="fa-solid fa-cloud-arrow-up" style="color: #c59b27;"></i>
      Create Employee ID Card
    </div>
    <span class="badge" style="background-color: #fef3c7; color: #92400e; font-weight: 700;">Auto Status: Pending HR Approval</span>
  </div>

  <div class="card-body" style="padding: 28px;">
    <form action="/designer/create" method="POST" enctype="multipart/form-data">
      <?= CsrfToken::field() ?>

      <!-- 1. Employee Full Name -->
      <div class="form-group" style="margin-bottom: 24px;">
        <label class="form-label" for="full_name" style="font-size: 14px; font-weight: 700; color: #0b1329;">
          Employee Full Name <span style="color: #dc2626;">*</span>
        </label>
        <input 
          type="text" 
          id="full_name" 
          name="full_name" 
          class="form-control" 
          placeholder="Enter employee full name (e.g. Flavia Nakityo)" 
          required 
          autofocus
          style="font-size: 15px; padding: 10px 14px;"
        >
        <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">
          Staff number, versioning, and department linkage are system-managed automatically.
        </div>
      </div>

      <!-- 2. PDF File Drag & Drop Box -->
      <div class="form-group" style="margin-bottom: 28px;">
        <label class="form-label" style="font-size: 14px; font-weight: 700; color: #0b1329;">
          ID Card PDF <span style="color: #dc2626;">*</span>
        </label>

        <div id="pdf-drop-zone" style="background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 10px; padding: 32px 20px; text-align: center; cursor: pointer; transition: all 0.2s ease;">
          <i class="fa-solid fa-file-pdf" style="font-size: 44px; color: #dc2626; margin-bottom: 12px; display: block;"></i>
          <div style="font-size: 15px; font-weight: 700; color: #1e293b;" id="pdf-file-label">
            Drag &amp; Drop PDF here or <span style="color: #c59b27; text-decoration: underline;">Choose PDF</span>
          </div>
          <div style="font-size: 12px; color: #64748b; margin-top: 6px;">
            Supported: High-quality PDF exported from Adobe Illustrator / Photoshop
          </div>
          <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">
            Maximum file size: 30 MB
          </div>
          <input 
            type="file" 
            id="id_pdf_input" 
            name="id_pdf" 
            accept="application/pdf" 
            required 
            style="display: none;"
            onchange="handlePdfSelected(this)"
          >
        </div>
      </div>

      <!-- Submit Action -->
      <div style="display: flex; justify-content: flex-end; gap: 12px; padding-top: 16px; border-top: 1px solid var(--border-color);">
        <a href="/designer/dashboard" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary" style="padding: 10px 24px; font-size: 14px;">
          <i class="fa-solid fa-cloud-arrow-up"></i> Upload &amp; Submit for HR Approval
        </button>
      </div>
    </form>
  </div>
</div>

<script>
const dropZone = document.getElementById('pdf-drop-zone');
const fileInput = document.getElementById('id_pdf_input');
const fileLabel = document.getElementById('pdf-file-label');

if (dropZone && fileInput) {
  dropZone.addEventListener('click', () => fileInput.click());

  dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.style.borderColor = '#c59b27';
    dropZone.style.backgroundColor = '#fef3c7';
  });

  dropZone.addEventListener('dragleave', () => {
    dropZone.style.borderColor = '#cbd5e1';
    dropZone.style.backgroundColor = '#f8fafc';
  });

  dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.style.borderColor = '#cbd5e1';
    dropZone.style.backgroundColor = '#f8fafc';
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      fileInput.files = e.dataTransfer.files;
      handlePdfSelected(fileInput);
    }
  });
}

function handlePdfSelected(input) {
  if (input.files && input.files[0]) {
    const file = input.files[0];
    const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
    fileLabel.innerHTML = `<span style="color:#059669; font-weight:800;"><i class="fa-solid fa-circle-check"></i> ${file.name}</span> <span style="font-size:12px; color:#64748b;">(${sizeMb} MB)</span>`;
    dropZone.style.borderColor = '#10b981';
    dropZone.style.backgroundColor = '#ecfdf5';
  }
}
</script>
