<?php
use Mengo\IdApproval\Models\Role;
use Mengo\IdApproval\Repositories\NotificationRepository;
use Mengo\IdApproval\Security\CsrfToken;
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Support\Config;

$user = $currentUser ?? [];
$role = $user['role'] ?? '';
$userId = (int)($user['id'] ?? 0);
$notifRepo = new NotificationRepository();
$unreadCount = ($userId && $role) ? $notifRepo->countUnreadForUser($userId, $role) : 0;
$uri = $_SERVER['REQUEST_URI'] ?? '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= Sanitizer::escape($pageTitle ?? 'Mengo Hospital ID Management System') ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <!-- Offline Network Banner -->
  <div id="offline-banner" class="offline-banner">
    <i class="fa-solid fa-triangle-exclamation" style="color: #f59e0b;"></i>
    <span>Connection interrupted. Reconnecting...</span>
  </div>

  <div class="app-container">
    <!-- Sidebar Navigation -->
    <aside class="app-sidebar">
      <?php
        $variant = 'sidebar';
        include APP_ROOT . '/src/Views/components/branding.php';
      ?>

      <ul class="sidebar-menu">
        <div class="menu-section-label">Main Navigation</div>

        <?php if ($role === Role::DESIGNER): ?>
          <li class="<?= str_starts_with($uri, '/designer/dashboard') ? 'active' : '' ?>">
            <a href="/designer/dashboard"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
          </li>
          <li class="<?= str_starts_with($uri, '/designer/create') ? 'active' : '' ?>">
            <a href="/designer/create"><i class="fa-solid fa-plus-circle"></i> Create & Upload ID</a>
          </li>
          <li class="<?= str_starts_with($uri, '/designer/my-ids') ? 'active' : '' ?>">
            <a href="/designer/my-ids"><i class="fa-solid fa-id-card"></i> My ID Submissions</a>
          </li>
          <li class="<?= str_starts_with($uri, '/designer/corrections') ? 'active' : '' ?>">
            <a href="/designer/corrections">
              <i class="fa-solid fa-triangle-exclamation"></i> Corrections Required
            </a>
          </li>
        <?php elseif ($role === Role::HR_MANAGER): ?>
          <li class="<?= str_starts_with($uri, '/hr/dashboard') ? 'active' : '' ?>">
            <a href="/hr/dashboard"><i class="fa-solid fa-gauge-high"></i> HR Dashboard</a>
          </li>
          <li class="<?= str_starts_with($uri, '/hr/pending') ? 'active' : '' ?>">
            <a href="/hr/pending"><i class="fa-solid fa-clock"></i> Pending Approvals</a>
          </li>
          <li class="<?= str_starts_with($uri, '/hr/all-ids') ? 'active' : '' ?>">
            <a href="/hr/all-ids"><i class="fa-solid fa-address-card"></i> All Employee IDs</a>
          </li>
          <li class="<?= str_starts_with($uri, '/hr/corrections') ? 'active' : '' ?>">
            <a href="/hr/corrections"><i class="fa-solid fa-rotate-left"></i> Correction History</a>
          </li>
          <li class="<?= str_starts_with($uri, '/hr/printing') ? 'active' : '' ?>">
            <a href="/hr/printing"><i class="fa-solid fa-print"></i> Printing Status</a>
          </li>
          <li class="<?= str_starts_with($uri, '/hr/collection') ? 'active' : '' ?>">
            <a href="/hr/collection"><i class="fa-solid fa-hand-holding-medical"></i> Collection Center</a>
          </li>

          <div class="menu-section-label">Hospital Administration</div>
          <li class="<?= str_starts_with($uri, '/reports') ? 'active' : '' ?>">
            <a href="/reports"><i class="fa-solid fa-chart-line"></i> Reports & KPIs</a>
          </li>
          <li class="<?= str_starts_with($uri, '/audit-logs') ? 'active' : '' ?>">
            <a href="/audit-logs"><i class="fa-solid fa-shield-halved"></i> Audit Trails</a>
          </li>
          <li class="<?= str_starts_with($uri, '/backups') ? 'active' : '' ?>">
            <a href="/backups"><i class="fa-solid fa-database"></i> Database Backups</a>
          </li>
        <?php elseif ($role === Role::PRINTING_OFFICER): ?>
          <li class="<?= str_starts_with($uri, '/printing/dashboard') ? 'active' : '' ?>">
            <a href="/printing/dashboard"><i class="fa-solid fa-gauge-high"></i> Production Dashboard</a>
          </li>
          <li class="<?= str_starts_with($uri, '/printing/ready') ? 'active' : '' ?>">
            <a href="/printing/ready"><i class="fa-solid fa-print"></i> Ready for Printing</a>
          </li>
          <li class="<?= str_starts_with($uri, '/printing/printed') ? 'active' : '' ?>">
            <a href="/printing/printed"><i class="fa-solid fa-check-circle"></i> Printed IDs</a>
          </li>
          <li class="<?= str_starts_with($uri, '/printing/batches') ? 'active' : '' ?>">
            <a href="/printing/batches"><i class="fa-solid fa-layer-group"></i> Print Batches</a>
          </li>
          <li class="<?= str_starts_with($uri, '/printing/awaiting-collection') ? 'active' : '' ?>">
            <a href="/printing/awaiting-collection"><i class="fa-solid fa-box-archive"></i> Awaiting Collection</a>
          </li>
        <?php elseif ($role === Role::ADMINISTRATOR): ?>
          <li class="<?= str_starts_with($uri, '/admin/hr-accounts') ? 'active' : '' ?>">
            <a href="/admin/hr-accounts"><i class="fa-solid fa-users-gear"></i> HR Manager Accounts</a>
          </li>
          <li class="<?= str_starts_with($uri, '/audit-logs') ? 'active' : '' ?>">
            <a href="/audit-logs"><i class="fa-solid fa-shield-halved"></i> Audit Trails</a>
          </li>
          <li class="<?= str_starts_with($uri, '/reports') ? 'active' : '' ?>">
            <a href="/reports"><i class="fa-solid fa-chart-line"></i> Reports &amp; KPIs</a>
          </li>
          <li class="<?= str_starts_with($uri, '/backups') ? 'active' : '' ?>">
            <a href="/backups"><i class="fa-solid fa-database"></i> Database Backups</a>
          </li>
        <?php endif; ?>

        <div class="menu-section-label">Account</div>
        <li class="<?= str_starts_with($uri, '/notifications') ? 'active' : '' ?>">
          <a href="/notifications">
            <i class="fa-solid fa-bell"></i> Notifications
            <span class="badge badge-danger badge-unread-count" style="<?= $unreadCount > 0 ? '' : 'display:none;' ?>">
              <?= $unreadCount ?>
            </span>
          </a>
        </li>
        <?php if ($role === Role::ADMINISTRATOR): ?>
          <li class="<?= str_starts_with($uri, '/admin/diagnostics') ? 'active' : '' ?>">
            <a href="/admin/diagnostics"><i class="fa-solid fa-heart-pulse"></i> Detailed Diagnostics</a>
          </li>
        <?php else: ?>
          <li>
            <a href="/health"><i class="fa-solid fa-heart-pulse"></i> System Status</a>
          </li>
        <?php endif; ?>
      </ul>

      <!-- User footer -->
      <div class="sidebar-user-footer">
        <div class="user-badge-pill">
          <div class="user-avatar">
            <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
          </div>
          <div class="user-info">
            <div class="user-name"><?= Sanitizer::escape($user['name'] ?? 'Staff') ?></div>
            <div class="user-role-badge"><?= Role::label($role) ?></div>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="app-main">
      <header class="app-topbar">
        <div class="topbar-left">
          <button id="btn-toggle-sidebar" class="btn btn-outline btn-sm" style="display: none;">
            <i class="fa-solid fa-bars"></i>
          </button>
          <div class="topbar-title"><?= Sanitizer::escape($pageTitle ?? 'Mengo Hospital ID System') ?></div>
        </div>

        <div class="topbar-right" style="display: flex; align-items: center; gap: 14px;">
          <a href="/notifications" class="notification-bell-btn" title="Notification Center" style="position: relative; width: 38px; height: 38px; border-radius: 50%; background: #f8fafc; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; color: #475569; transition: all 0.2s ease;">
            <i class="fa-solid fa-bell" style="font-size: 15px;"></i>
            <span class="badge-unread-count" style="position: absolute; top: -2px; right: -2px; background: #dc2626; color: #ffffff; font-size: 10px; font-weight: 800; min-width: 18px; height: 18px; border-radius: 99px; display: flex; align-items: center; justify-content: center; padding: 0 4px; border: 2px solid #ffffff; <?= $unreadCount > 0 ? '' : 'display:none;' ?>">
              <?= $unreadCount ?>
            </span>
          </a>

          <form action="/logout" method="POST" style="margin: 0;">
            <?= CsrfToken::field() ?>
            <button type="submit" class="btn btn-outline btn-sm" style="border-radius: 20px; padding: 6px 14px; font-weight: 700; font-size: 12.5px; border-color: #cbd5e1; color: #334155; display: inline-flex; align-items: center; gap: 6px; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);" title="Sign Out of Portal">
              <i class="fa-solid fa-right-from-bracket" style="color: #dc2626;"></i> Logout
            </button>
          </form>
        </div>
      </header>

      <main class="app-content">
        <!-- Flash messages -->
        <?php if (!empty($flashes)): ?>
          <div class="flash-container" style="margin-bottom: 20px;">
            <?php foreach ($flashes as $flash): ?>
              <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : ($flash['type'] === 'warning' ? 'warning' : ($flash['type'] === 'info' ? 'info' : 'success')) ?>" style="padding: 12px 16px; border-radius: 8px; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; border: 1px solid <?= $flash['type'] === 'error' ? '#fecdd3' : ($flash['type'] === 'warning' ? '#fde68a' : '#a7f3d0') ?>; background-color: <?= $flash['type'] === 'error' ? '#fff1f2' : ($flash['type'] === 'warning' ? '#fffbeb' : '#ecfdf5') ?>; color: <?= $flash['type'] === 'error' ? '#991b1b' : ($flash['type'] === 'warning' ? '#92400e' : '#065f46') ?>;">
                <span>
                  <i class="fa-solid <?= $flash['type'] === 'error' ? 'fa-circle-exclamation' : ($flash['type'] === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-check') ?> mr-2"></i>
                  <?= Sanitizer::escape($flash['message']) ?>
                </span>
                <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer; color:inherit; font-size:16px;">&times;</button>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- View Body Content -->
        <?= $content ?? '' ?>
      </main>
    </div>
  </div>

  <script src="/assets/js/app.js"></script>
</body>
</html>
