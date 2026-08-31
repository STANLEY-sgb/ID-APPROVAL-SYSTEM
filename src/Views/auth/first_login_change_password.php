<?php
use Mengo\IdApproval\Security\CsrfToken;
?>

<div style="margin-bottom: 20px; text-align: center;">
  <div style="font-size: 15px; font-weight: 700; color: #0EA5E9;">Password Update Required</div>
  <p style="font-size: 12.5px; color: var(--text-muted); margin-top: 4px;">
    For hospital security compliance, please change your initial default password before proceeding.
  </p>
</div>

<form action="/change-password" method="POST">
  <?= CsrfToken::field() ?>

  <div class="form-group">
    <label class="form-label" for="current_password">Current Password</label>
    <input type="password" id="current_password" name="current_password" class="form-control" required placeholder="Enter current default password">
  </div>

  <div class="form-group">
    <label class="form-label" for="new_password">New Password (min. 8 characters)</label>
    <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8" placeholder="Enter new strong password">
  </div>

  <div class="form-group">
    <label class="form-label" for="confirm_password">Confirm New Password</label>
    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8" placeholder="Repeat new password">
  </div>

  <div style="margin-top: 24px;">
    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px;">
      <i class="fa-solid fa-key"></i> Update Password & Continue
    </button>
  </div>
</form>
