<?php
use Mengo\IdApproval\Security\CsrfToken;
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Support\Timezone;

$notifications = $notifications ?? [];
$unreadCount = $unreadCount ?? 0;
?>

<div class="card">
  <div class="card-header">
    <div class="card-title">
      <i class="fa-solid fa-bell"></i> Notifications
      <?php if ($unreadCount > 0): ?>
        <span class="badge badge-danger" style="margin-left: 8px;"><?= $unreadCount ?> Unread</span>
      <?php endif; ?>
    </div>
    <?php if (!empty($notifications)): ?>
      <form action="/notifications/mark-all-read" method="POST" style="margin: 0;">
        <?= CsrfToken::field() ?>
        <button type="submit" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-check-double"></i> Mark All as Read
        </button>
      </form>
    <?php endif; ?>
  </div>

  <div style="padding: 0;">
    <?php if (empty($notifications)): ?>
      <div style="text-align: center; padding: 48px 20px; color: #94a3b8;">
        <i class="fa-regular fa-bell" style="font-size: 36px; display: block; margin-bottom: 12px;"></i>
        <div style="font-size: 14px; font-weight: 600;">No notifications yet.</div>
        <div style="font-size: 12px; margin-top: 4px;">Workflow events, approvals, and corrections will appear here.</div>
      </div>
    <?php else: ?>
      <?php foreach ($notifications as $n): ?>
        <div class="notification-row <?= $n->is_read ? '' : 'unread' ?>" style="padding: 14px 20px; border-bottom: 1px solid var(--border-color); display: flex; gap: 12px; align-items: flex-start; <?= !$n->is_read ? 'background: #eff6ff;' : '' ?>">
          <div style="flex-shrink: 0; width: 36px; height: 36px; border-radius: 50%; background: <?= match($n->type ?? '') {
            'APPROVAL' => '#ecfdf5',
            'CORRECTION' => '#fff7ed',
            'UPLOADED' => '#eff6ff',
            'PRINTED' => '#e0e7ff',
            'COLLECTED' => '#f8fafc',
            default => '#f1f5f9'
          } ?>; display: flex; align-items: center; justify-content: center;">
            <i class="fa-solid <?= match($n->type ?? '') {
              'APPROVAL' => 'fa-stamp text-success',
              'CORRECTION' => 'fa-rotate-left text-warning',
              'UPLOADED' => 'fa-cloud-arrow-up',
              'PRINTED' => 'fa-print',
              'COLLECTED' => 'fa-signature',
              default => 'fa-bell'
            } ?>" style="font-size: 15px;"></i>
          </div>
          <div style="flex: 1; min-width: 0;">
            <div style="font-size: 13.5px; font-weight: <?= $n->is_read ? '400' : '700' ?>; color: #1e293b;">
              <?= Sanitizer::escape($n->message) ?>
            </div>
            <?php if (!empty($n->id_card_id)): ?>
              <div style="margin-top: 4px;">
                <a href="/id-cards/<?= $n->id_card_id ?>" style="font-size: 12px; color: #2563eb; font-weight: 600;">
                  <i class="fa-solid fa-arrow-right"></i> View ID Details &rarr;
                </a>
              </div>
            <?php endif; ?>
            <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">
              <?= Timezone::timeAgo($n->created_at) ?>
            </div>
          </div>
          <?php if (!$n->is_read): ?>
            <div style="flex-shrink: 0;">
              <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #2563eb;"></span>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
