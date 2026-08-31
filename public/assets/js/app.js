/**
 * Mengo Hospital Employee ID Card Management System
 * Production Vanilla JavaScript Engine
 *
 * Capabilities:
 * - Real-time Background Synchronization (/api/sync)
 * - Multi-select Checkbox & Bulk Print Batch Manager
 * - Progressive Disclosure Accordion Panels
 * - Interactive PDF Preview Controls (Zoom, Pan, Fullscreen)
 * - Offline / Connection Resilience Indicator
 * - Accessible Modal Management & Form Safety
 */

'use strict';

// ─────────────────────────────────────────────────────────────
// 1. MODAL MANAGEMENT
// ─────────────────────────────────────────────────────────────
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
    const firstInput = modal.querySelector('input:not([type=hidden]), textarea, button');
    if (firstInput) firstInput.focus();
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('open');
    document.body.style.overflow = '';
  }
}

// Close modals when clicking backdrop or pressing Escape
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
    document.body.style.overflow = '';
  }
});

document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
});

// ─────────────────────────────────────────────────────────────
// 2. PROGRESSIVE DISCLOSURE ACCORDIONS
// ─────────────────────────────────────────────────────────────
function toggleAccordion(sectionId) {
  const header = document.querySelector(`[data-accordion="${sectionId}"]`);
  const body = document.getElementById(sectionId);
  if (header && body) {
    const isOpen = body.classList.contains('open');
    body.classList.toggle('open', !isOpen);
    header.classList.toggle('active', !isOpen);
  }
}

// ─────────────────────────────────────────────────────────────
// 3. BULK PRINT SELECTION MANAGER
// ─────────────────────────────────────────────────────────────
const BulkPrintManager = {
  selectedIds: new Set(),

  init() {
    const masterCheckbox = document.getElementById('select-all-cards');
    const cardCheckboxes = document.querySelectorAll('.card-select-checkbox');
    const batchBar = document.getElementById('batch-action-bar');
    const batchCountBadge = document.getElementById('batch-selected-count');

    if (!masterCheckbox && cardCheckboxes.length === 0) return;

    if (masterCheckbox) {
      masterCheckbox.addEventListener('change', (e) => {
        const isChecked = e.target.checked;
        cardCheckboxes.forEach(cb => {
          cb.checked = isChecked;
          const id = parseInt(cb.value, 10);
          if (isChecked) {
            this.selectedIds.add(id);
          } else {
            this.selectedIds.delete(id);
          }
        });
        this.updateUi();
      });
    }

    cardCheckboxes.forEach(cb => {
      cb.addEventListener('change', (e) => {
        const id = parseInt(e.target.value, 10);
        if (e.target.checked) {
          this.selectedIds.add(id);
        } else {
          this.selectedIds.delete(id);
        }

        if (masterCheckbox) {
          masterCheckbox.checked = this.selectedIds.size === cardCheckboxes.length && cardCheckboxes.length > 0;
        }
        this.updateUi();
      });
    });
  },

  updateUi() {
    const count = this.selectedIds.size;
    const batchBar = document.getElementById('batch-action-bar');
    const countPill = document.getElementById('batch-selected-count');

    if (countPill) countPill.textContent = count;

    if (batchBar) {
      if (count > 0) {
        batchBar.classList.add('visible');
      } else {
        batchBar.classList.remove('visible');
      }
    }
  },

  openConfirmationModal() {
    if (this.selectedIds.size === 0) {
      alert('Please select at least one approved ID card to print.');
      return;
    }

    const container = document.getElementById('bulk-hidden-inputs');
    if (container) {
      container.innerHTML = '';
      this.selectedIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_card_ids[]';
        input.value = id;
        container.appendChild(input);
      });
    }

    const modalCount = document.getElementById('modal-bulk-count');
    if (modalCount) modalCount.textContent = this.selectedIds.size;

    openModal('bulkPrintModal');
  },

  clearSelection() {
    this.selectedIds.clear();
    document.querySelectorAll('.card-select-checkbox').forEach(cb => cb.checked = false);
    const master = document.getElementById('select-all-cards');
    if (master) master.checked = false;
    this.updateUi();
  }
};

// ─────────────────────────────────────────────────────────────
// 4. REAL-TIME LIVE SYNC & OFFLINE RESILIENCE
// ─────────────────────────────────────────────────────────────
const LiveSyncEngine = {
  syncInterval: 5000,
  timer: null,
  isOnline: true,
  lastSyncTime: null,

  init() {
    this.setupNetworkListeners();
    this.startPolling();
  },

  setupNetworkListeners() {
    const banner = document.getElementById('offline-banner');

    window.addEventListener('online', () => {
      this.isOnline = true;
      if (banner) banner.classList.remove('visible');
      this.poll(); // Sync immediately upon reconnect
    });

    window.addEventListener('offline', () => {
      this.isOnline = false;
      if (banner) {
        banner.textContent = 'Connection interrupted. Reconnecting...';
        banner.classList.add('visible');
      }
    });
  },

  startPolling() {
    this.timer = setInterval(() => this.poll(), this.syncInterval);
  },

  async poll() {
    if (!this.isOnline) return;

    try {
      const url = '/api/sync' + (this.lastSyncTime ? `?since=${encodeURIComponent(this.lastSyncTime)}` : '');
      const response = await fetch(url, { credentials: 'same-origin' });
      if (response.status === 200) {
        const data = await response.json();
        this.applySyncData(data);
        this.lastSyncTime = data.server_time;
      }
    } catch (err) {
      // Quiet background failure; retry next tick
    }
  },

  applySyncData(data) {
    if (!data || !data.authenticated) return;

    // Update unread notification count badge in topbar
    const unreadCount = data.unread_notifications || 0;
    const badge = document.querySelector('.badge-unread-count');
    if (badge) {
      badge.textContent = unreadCount;
      badge.style.display = unreadCount > 0 ? 'flex' : 'none';
    }

    // Update stat cards dynamically if present on page
    if (data.status_counts) {
      const pendingEl = document.querySelector('[data-stat="PENDING_HR_APPROVAL"]');
      if (pendingEl) pendingEl.textContent = data.status_counts.PENDING_HR_APPROVAL || 0;

      const readyEl = document.querySelector('[data-stat="APPROVED"]');
      if (readyEl) readyEl.textContent = data.status_counts.APPROVED || 0;

      const printedEl = document.querySelector('[data-stat="PRINTED"]');
      if (printedEl) printedEl.textContent = data.status_counts.PRINTED || 0;
    }
  }
};

// ─────────────────────────────────────────────────────────────
// 5. PDF PREVIEW CONTROLS
// ─────────────────────────────────────────────────────────────
let currentPdfZoom = 1.0;

function zoomPdf(delta) {
  currentPdfZoom = Math.min(Math.max(currentPdfZoom + delta, 0.5), 2.5);
  const frame = document.getElementById('pdf-viewer-frame');
  const label = document.getElementById('zoom-level');
  if (frame) {
    frame.style.transform = `scale(${currentPdfZoom})`;
    frame.style.transformOrigin = 'top left';
    frame.style.width = `${100 / currentPdfZoom}%`;
  }
  if (label) {
    label.textContent = Math.round(currentPdfZoom * 100) + '%';
  }
}

function toggleFullscreen(elementId) {
  const el = document.getElementById(elementId);
  if (!el) return;
  if (!document.fullscreenElement) {
    el.requestFullscreen?.() || el.webkitRequestFullscreen?.();
  } else {
    document.exitFullscreen?.();
  }
}

// ─────────────────────────────────────────────────────────────
// 6. GLOBAL SMART INSTANT SEARCH ENGINE
// ─────────────────────────────────────────────────────────────
const SmartSearchEngine = {
  init() {
    const searchInputs = document.querySelectorAll('.smart-table-search, input[data-table-id]');
    searchInputs.forEach(input => {
      this.bindInput(input);
    });
  },

  bindInput(input) {
    const tableId = input.getAttribute('data-table-id');
    if (!tableId) return;

    const table = document.getElementById(tableId);
    if (!table) return;

    let debounceTimer = null;

    input.addEventListener('input', () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        this.filterTable(table, input.value.trim().toLowerCase());
      }, 50); // fast 50ms response for instantaneous feel
    });

    input.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        input.value = '';
        this.filterTable(table, '');
      }
    });

    // Run initial filter if input has value on page load
    if (input.value.trim()) {
      this.filterTable(table, input.value.trim().toLowerCase());
    }
  },

  filterTable(table, query) {
    const tbody = table.querySelector('tbody');
    if (!tbody) return;

    const rows = tbody.querySelectorAll('tr.searchable-row, tr:not(.no-records-row):not(.smart-no-results)');
    let visibleCount = 0;

    // Remove existing no-results placeholder if present
    const existingPlaceholder = tbody.querySelector('.smart-no-results');
    if (existingPlaceholder) existingPlaceholder.remove();

    rows.forEach(row => {
      const searchAttr = row.getAttribute('data-search') || '';
      const textContent = row.textContent.toLowerCase();
      const matches = !query || searchAttr.includes(query) || textContent.includes(query);

      if (matches) {
        row.style.display = '';
        visibleCount++;
      } else {
        row.style.display = 'none';
      }
    });

    // Handle empty matching state
    if (query && visibleCount === 0 && rows.length > 0) {
      const tr = document.createElement('tr');
      tr.className = 'smart-no-results';
      tr.innerHTML = `
        <td colspan="100%" style="text-align: center; padding: 36px 20px; color: #64748b;">
          <i class="fa-solid fa-magnifying-glass" style="font-size: 28px; color: #94a3b8; display: block; margin-bottom: 8px;"></i>
          <div style="font-weight: 700; color: #1e293b; font-size: 14px;">No matching records found for "${this.escapeHtml(query)}"</div>
          <div style="font-size: 12px; margin-top: 4px; color: #94a3b8;">Check spelling or press <kbd style="background:#e2e8f0; padding:2px 6px; border-radius:4px; font-size:11px;">Esc</kbd> to clear search.</div>
        </td>
      `;
      tbody.appendChild(tr);
    }
  },

  escapeHtml(str) {
    return str.replace(/[&<>"']/g, m => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    })[m]);
  }
};

// ─────────────────────────────────────────────────────────────
// 7. INITIALIZATION
// ─────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  BulkPrintManager.init();
  LiveSyncEngine.init();
  SmartSearchEngine.init();

  // Mobile sidebar toggle
  const toggleBtn = document.getElementById('btn-toggle-sidebar');
  const sidebar = document.querySelector('.app-sidebar');
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('open');
    });
  }
});
