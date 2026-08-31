<?php
use Mengo\IdApproval\Security\Sanitizer;

$checks = $checks ?? [];
$allHealthy = $allHealthy ?? true;
$phpVersion = PHP_VERSION;
?>

<div style="display: flex; align-items: center; gap: 14px; margin-bottom: 24px; padding: 18px 20px; background: <?= $allHealthy ? '#ecfdf5' : '#fef2f2' ?>; border-radius: 10px; border: 1px solid <?= $allHealthy ? '#a7f3d0' : '#fecaca' ?>;">
  <i class="fa-solid <?= $allHealthy ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>" style="font-size: 32px; color: <?= $allHealthy ? '#059669' : '#ef4444' ?>;"></i>
  <div>
    <div style="font-size: 16px; font-weight: 800; color: <?= $allHealthy ? '#065f46' : '#991b1b' ?>;">
      <?= $allHealthy ? 'All Mengo Hospital ID Subsystems Operational' : 'WARNING: Diagnostic Issues Detected' ?>
    </div>
    <div style="font-size: 13px; color: <?= $allHealthy ? '#047857' : '#b91c1c' ?>; margin-top: 2px;">
      PHP <?= htmlspecialchars($phpVersion) ?> · SQLite WAL · Africa/Kampala (EAT) · PDF-1.6 High-Fidelity Engine
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">
      <i class="fa-solid fa-heart-pulse" style="color: #c59b27;"></i>
      Core System Health & Component Checks
    </div>
    <a href="/health" class="btn btn-outline btn-sm">
      <i class="fa-solid fa-rotate"></i> Re-run Diagnostics
    </a>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Subsystem / Service</th>
          <th>Status</th>
          <th>Diagnostic Metrics & Detail</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($checks as $check): ?>
          <tr>
            <td>
              <div style="font-weight: 700; color: #0b1329;"><?= Sanitizer::escape($check['name']) ?></div>
              <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;"><?= Sanitizer::escape($check['description'] ?? '') ?></div>
            </td>
            <td>
              <?php if ($check['ok']): ?>
                <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> OPERATIONAL</span>
              <?php else: ?>
                <span class="badge badge-danger"><i class="fa-solid fa-triangle-exclamation"></i> DEGRADED</span>
              <?php endif; ?>
            </td>
            <td style="font-size: 12px; font-family: monospace; color: #334155;">
              <?= Sanitizer::escape($check['detail'] ?? '—') ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
