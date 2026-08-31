<?php
use Mengo\IdApproval\Models\Role;
use Mengo\IdApproval\Security\CsrfToken;
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Support\Timezone;

$allUsers = $hrManagers ?? [];
$activeCount = count(array_filter($allUsers, fn($u) => ($u['status'] ?? '') === 'ACTIVE'));
$inactiveCount = count($allUsers) - $activeCount;

$roleColors = [
    'ADMINISTRATOR'   => '#8b5cf6',
    'HR_MANAGER'      => '#059669',
    'DESIGNER'        => '#2563eb',
    'PRINTING_OFFICER'=> '#c59b27',
];
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
  <div>
    <h2 style="font-size: 20px; font-weight: 800; color: #0b1329;">System User Administration</h2>
    <p style="font-size: 13px; color: #64748b; margin-top: 2px;">
      Manage hospital system user accounts, assigned roles, and authentication credentials.
    </p>
  </div>
  <button type="button" class="btn btn-primary btn-sm" onclick="openModal('createUserModal')">
    <i class="fa-solid fa-user-plus"></i> Create User Account
  </button>
</div>

<!-- KPI Cards -->
<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
  <div class="stat-card" style="border-left: 4px solid #c59b27;">
    <div class="stat-title">Total System Users</div>
    <div class="stat-value" style="color: #c59b27;"><?= count($allUsers) ?></div>
    <div class="stat-subtitle">Configured Accounts</div>
  </div>
  <div class="stat-card" style="border-left: 4px solid #059669;">
    <div class="stat-title">Active Accounts</div>
    <div class="stat-value" style="color: #059669;"><?= $activeCount ?></div>
    <div class="stat-subtitle">Can Access System</div>
  </div>
  <div class="stat-card" style="border-left: 4px solid #94a3b8;">
    <div class="stat-title">Inactive / Suspended</div>
    <div class="stat-value" style="color: #475569;"><?= $inactiveCount ?></div>
    <div class="stat-subtitle">Access Disabled</div>
  </div>
  <div class="stat-card" style="border-left: 4px solid #8b5cf6;">
    <div class="stat-title">System Administrators</div>
    <div class="stat-value" style="color: #7c3aed;">
      <?= count(array_filter($allUsers, fn($u) => ($u['role'] ?? '') === 'ADMINISTRATOR')) ?>
    </div>
    <div class="stat-subtitle">Full System Control</div>
  </div>
</div>

<!-- Search Bar -->
<div class="card" style="margin-bottom: 20px;">
  <div class="card-body" style="padding: 12px 18px;">
    <div style="position: relative; max-width: 480px;">
      <input
        type="text"
        id="user-admin-search-input"
        class="form-control smart-table-search"
        data-table-id="users-admin-table"
        placeholder="Search name, username, or role..."
        autocomplete="off"
        style="padding-left: 36px;"
      >
      <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 11px; color: #94a3b8; font-size: 13px;"></i>
    </div>
  </div>
</div>

<!-- Users Table -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <i class="fa-solid fa-users-gear" style="color: #c59b27;"></i>
      Registered System User Accounts
    </div>
    <div style="font-size: 12px; color: #64748b;">Only active accounts with assigned roles can access their portals</div>
  </div>

  <div class="table-responsive">
    <table id="users-admin-table" class="data-table">
      <thead>
        <tr>
          <th>User Full Name</th>
          <th>Username</th>
          <th>Email</th>
          <th>Role</th>
          <th>Status</th>
          <th>Last Login</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($allUsers as $u): ?>
          <tr class="searchable-row" data-search="<?= strtolower(Sanitizer::escape(($u['name'] ?? '') . ' ' . ($u['username'] ?? '') . ' ' . ($u['role'] ?? ''))) ?>">
            <td data-label="User Full Name">
              <div style="font-weight: 700; color: #0b1329; font-size: 14px;"><?= Sanitizer::escape($u['name'] ?? '') ?></div>
              <div style="font-size: 11px; color: #94a3b8; font-family: monospace;"><?= Sanitizer::escape($u['staff_id'] ?? '') ?></div>
            </td>
            <td data-label="Username">
              <span style="font-weight: 700; font-family: monospace; color: #1e293b; font-size: 13px; background: #f1f5f9; padding: 3px 8px; border-radius: 4px;">
                <?= Sanitizer::escape($u['username'] ?? '') ?>
              </span>
            </td>
            <td data-label="Email" style="font-size: 12.5px; color: #475569;">
              <?= Sanitizer::escape($u['email'] ?? '—') ?>
            </td>
            <td data-label="Role">
              <?php $roleKey = $u['role'] ?? ''; ?>
              <span class="badge" style="font-size: 11px; font-weight: 700; background: <?= $roleColors[$roleKey] ?? '#64748b' ?>; color: #fff; border-radius: 4px;">
                <?= Sanitizer::escape(str_replace('_', ' ', $roleKey)) ?>
              </span>
            </td>
            <td data-label="Status">
              <?php if (($u['status'] ?? '') === 'ACTIVE'): ?>
                <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> ACTIVE</span>
              <?php else: ?>
                <span class="badge badge-secondary"><i class="fa-solid fa-circle-xmark"></i> INACTIVE</span>
              <?php endif; ?>
            </td>
            <td data-label="Last Login" style="font-size: 12px; color: #64748b;">
              <?= !empty($u['last_login_at']) ? Timezone::timeAgo($u['last_login_at']) : '<span style="color:#94a3b8;">Never</span>' ?>
            </td>
            <td data-label="Actions" style="text-align: right;">
              <div style="display: inline-flex; gap: 6px; align-items: center; flex-wrap: wrap; justify-content: flex-end;">
                <button type="button" class="btn btn-outline btn-sm" title="Edit User Account"
                  onclick="openEditUserModal(<?= (int)$u['id'] ?>, '<?= Sanitizer::escape(addslashes($u['name'] ?? '')) ?>', '<?= Sanitizer::escape(addslashes($u['username'] ?? '')) ?>', '<?= Sanitizer::escape(addslashes($u['email'] ?? '')) ?>', '<?= Sanitizer::escape($u['role'] ?? '') ?>', '<?= Sanitizer::escape(addslashes($u['department'] ?? '')) ?>', '<?= Sanitizer::escape(addslashes($u['phone'] ?? '')) ?>')">
                  <i class="fa-solid fa-pen-to-square"></i> Edit
                </button>
                <button type="button" class="btn btn-outline btn-sm" title="Reset Password"
                  onclick="openResetPasswordModal(<?= (int)$u['id'] ?>, '<?= Sanitizer::escape(addslashes($u['name'] ?? '')) ?>')">
                  <i class="fa-solid fa-key"></i> Password
                </button>
                <form action="/admin/hr-accounts/toggle-status" method="POST" style="display: inline;" onsubmit="return confirm('Change account status for <?= Sanitizer::escape(addslashes($u['name'] ?? '')) ?>?');">
                  <?= CsrfToken::field() ?>
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <?php if (($u['status'] ?? '') === 'ACTIVE'): ?>
                    <button type="submit" class="btn btn-danger btn-sm" title="Deactivate Account">
                      <i class="fa-solid fa-user-slash"></i> Deactivate
                    </button>
                  <?php else: ?>
                    <button type="submit" class="btn btn-success btn-sm" title="Activate Account">
                      <i class="fa-solid fa-user-check"></i> Activate
                    </button>
                  <?php endif; ?>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($allUsers)): ?>
          <tr class="no-records-row">
            <td colspan="7" style="text-align: center; padding: 40px 20px; color: #64748b;">
              <i class="fa-solid fa-users" style="font-size: 32px; color: #94a3b8; display: block; margin-bottom: 8px;"></i>
              <div style="font-weight: 700;">No Users Found</div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ─── Modal: Create System User Account ──────────────────── -->
<div id="createUserModal" class="modal-overlay">
  <div class="modal-box" style="max-width: 540px;">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-user-plus" style="color: #c59b27;"></i> Create System User Account</div>
      <button type="button" onclick="closeModal('createUserModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
    </div>
    <form action="/admin/hr-accounts/create" method="POST">
      <?= CsrfToken::field() ?>
      <div class="modal-body" style="display: grid; gap: 14px;">
        <div class="form-group">
          <label class="form-label">User Full Name *</label>
          <input type="text" name="name" class="form-control" required placeholder="e.g. Dr. Arthur Kiggundu">
        </div>
        <div class="form-group">
          <label class="form-label">Username * (used for login)</label>
          <input type="text" name="username" class="form-control" required placeholder="e.g. arthur.kiggundu">
        </div>
        <div class="form-group">
          <label class="form-label">Email Address *</label>
          <input type="email" name="email" class="form-control" required placeholder="e.g. arthur@mengohospital.org">
        </div>
        <div class="form-group">
          <label class="form-label">Assigned Role *</label>
          <select name="role" class="form-control" required>
            <option value="HR_MANAGER">HR MANAGER</option>
            <option value="DESIGNER">ID DESIGNER</option>
            <option value="PRINTING_OFFICER">ID PRINTING OFFICER</option>
            <option value="ADMINISTRATOR">SYSTEM ADMINISTRATOR</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Initial Password * (min 8 characters)</label>
          <input type="password" name="password" class="form-control" required placeholder="Enter strong initial password" autocomplete="new-password">
        </div>
      </div>
      <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
        <button type="button" class="btn btn-outline" onclick="closeModal('createUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check-circle"></i> Create Account</button>
      </div>
    </form>
  </div>
</div>

<!-- ─── Modal: Edit User Account ──────────────────────────── -->
<div id="editUserModal" class="modal-overlay">
  <div class="modal-box" style="max-width: 540px;">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-pen-to-square" style="color: #c59b27;"></i> Edit User Account</div>
      <button type="button" onclick="closeModal('editUserModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
    </div>
    <form action="/admin/hr-accounts/update" method="POST">
      <?= CsrfToken::field() ?>
      <input type="hidden" name="user_id" id="editUserId">
      <div class="modal-body" style="display: grid; gap: 14px;">
        <div class="form-group">
          <label class="form-label">User Full Name *</label>
          <input type="text" name="name" id="editUserName" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Username * (login identifier)</label>
          <input type="text" name="username" id="editUserUsername" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address *</label>
          <input type="email" name="email" id="editUserEmail" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Assigned Role *</label>
          <select name="role" id="editUserRole" class="form-control" required>
            <option value="HR_MANAGER">HR MANAGER</option>
            <option value="DESIGNER">ID DESIGNER</option>
            <option value="PRINTING_OFFICER">ID PRINTING OFFICER</option>
            <option value="ADMINISTRATOR">SYSTEM ADMINISTRATOR</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Department</label>
          <input type="text" name="department" id="editUserDept" class="form-control" placeholder="e.g. Human Resources">
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" id="editUserPhone" class="form-control" placeholder="e.g. +256 700 000000">
        </div>
      </div>
      <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
        <button type="button" class="btn btn-outline" onclick="closeModal('editUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ─── Modal: Reset Password ──────────────────────────────── -->
<div id="resetPasswordModal" class="modal-overlay">
  <div class="modal-box" style="max-width: 480px;">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-key" style="color: #c59b27;"></i> Reset User Password</div>
      <button type="button" onclick="closeModal('resetPasswordModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
    </div>
    <form action="/admin/hr-accounts/reset-password" method="POST">
      <?= CsrfToken::field() ?>
      <input type="hidden" name="user_id" id="resetUserId">
      <div class="modal-body">
        <p style="font-size: 14px; color: #475569; margin-bottom: 16px;">
          Setting new temporary password for: <strong id="resetUserName" style="color: #0b1329;"></strong>
        </p>
        <div class="form-group">
          <label class="form-label">New Password * (min 8 characters)</label>
          <input type="password" name="new_password" class="form-control" required placeholder="Enter new secure password" autocomplete="new-password">
        </div>
        <p style="font-size: 11.5px; color: #94a3b8; margin-top: 8px;">
          <i class="fa-solid fa-shield-halved"></i> Passwords are securely hashed and never stored in plaintext.
        </p>
      </div>
      <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
        <button type="button" class="btn btn-outline" onclick="closeModal('resetPasswordModal')">Cancel</button>
        <button type="submit" class="btn btn-warning"><i class="fa-solid fa-key"></i> Update Password</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditUserModal(id, name, username, email, role, dept, phone) {
  document.getElementById('editUserId').value = id;
  document.getElementById('editUserName').value = name;
  document.getElementById('editUserUsername').value = username;
  document.getElementById('editUserEmail').value = email;
  document.getElementById('editUserDept').value = dept;
  document.getElementById('editUserPhone').value = phone;
  const sel = document.getElementById('editUserRole');
  for (let i = 0; i < sel.options.length; i++) {
    sel.options[i].selected = (sel.options[i].value === role);
  }
  openModal('editUserModal');
}

function openResetPasswordModal(id, name) {
  document.getElementById('resetUserId').value = id;
  document.getElementById('resetUserName').textContent = name;
  openModal('resetPasswordModal');
}
</script>
