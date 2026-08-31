<?php
use Mengo\IdApproval\Security\CsrfToken;
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Support\Timezone;

$backupList = $backups ?? [];
$diskFree = $diskFreeGb ?? '?';
$diskTotal = $diskTotalGb ?? '?';
$dbSize = $dbSizeMb ?? '?';
?>

<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 28px;">
  <div class="stat-card">
    <div class="stat-title">Database Size</div>
    <div class="stat-value" style="font-size: 22px;"><?= $dbSize ?> MB</div>
    <div class="stat-subtitle">SQLite WAL mode — production database</div>
  </div>
  <div class="stat-card">
    <div class="stat-title">Available Disk</div>
    <div class="stat-value" style="font-size: 22px; color: #059669;"><?= $diskFree ?> GB</div>
    <div class="stat-subtitle">of <?= $diskTotal ?> GB total disk space</div>
  </div>
  <div class="stat-card">
    <div class="stat-title">Total Backups</div>
    <div class="stat-value"><?= count($backupList) ?></div>
    <div class="stat-subtitle">Stored in storage/backups/</div>
  </div>
</div>

<!-- Create Backup -->
<div class="card" style="margin-bottom: 28px;">
  <div class="card-header">
    <div class="card-title"><i class="fa-solid fa-database"></i> Create New Database Backup</div>
  </div>
  <div class="card-body">
    <p style="font-size: 13.5px; color: #374151; margin-bottom: 16px;">
      Creates a safe copy of the entire SQLite database using WAL checkpoint mode. The backup is timestamped and stored in <code>storage/backups/</code>.
    </p>
    <form action="/backups/create" method="POST">
      <?= CsrfToken::field() ?>
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-download"></i> Create Backup Now
      </button>
    </form>
  </div>
</div>

<!-- Backup List -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><i class="fa-solid fa-hard-drive"></i> Existing Backup Files (<?= count($backupList) ?>)</div>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Filename</th>
          <th>File Size</th>
          <th>Created At</th>
          <th>SHA-256 Hash</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($backupList)): ?>
          <tr>
            <td colspan="5" style="text-align: center; color: #64748b; padding: 24px;">No backups found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($backupList as $b): ?>
            <tr>
              <td><strong><?= Sanitizer::escape($b['filename']) ?></strong></td>
              <td><?= number_format($b['size'] / 1024 / 1024, 2) ?> MB</td>
              <td><?= Timezone::format($b['created_at'], 'd M Y H:i:s') ?></td>
              <td>
                <code style="font-size: 10px; color: #475569;">
                  <?= Sanitizer::escape(substr($b['sha256'], 0, 16)) ?>...
                </code>
              </td>
              <td>
                <a href="/backups/download?file=<?= urlencode($b['filename']) ?>" class="btn btn-outline btn-sm">
                  <i class="fa-solid fa-download"></i> Download
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
