<?php
/**
 * src/Views/users.php
 * User Management Workbench & Access Directory View.
 * Injected into the <main> dynamic body slot of layout.php.
 *
 * Variables provided by UserController:
 * @var array<int, array<string, mixed>> $users
 * @var array<int, array<string, mixed>> $roles
 * @var bool $canWrite
 * @var string|null $flashMessage
 * @var string|null $flashError
 */

declare(strict_types=1);

use Vault\Services\View;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}
?>
<div class="users-view-container" style="display: flex; flex-direction: column; gap: 20px; padding: 24px;">
  <!-- Flash Message Banners -->
  <?php if (!empty($flashMessage)): ?>
    <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid var(--success); color: #6ee7b7; padding: 12px 16px; border-radius: var(--radius-md); font-size: 13px; display: flex; align-items: center; gap: 8px;">
      <span>✓</span>
      <div><?= View::e($flashMessage) ?></div>
    </div>
  <?php endif; ?>

  <?php if (!empty($flashError)): ?>
    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5; padding: 12px 16px; border-radius: var(--radius-md); font-size: 13px; display: flex; align-items: center; gap: 8px;">
      <span>⚠️</span>
      <div><?= View::e($flashError) ?></div>
    </div>
  <?php endif; ?>

  <!-- Top Action & Telemetry Header -->
  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    <div>
      <h2 style="font-size: 20px; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 8px;">
        <span>👥</span> User Management Directory
      </h2>
      <p style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
        Authorized access list &bull; <?= count($users) ?> registered user accounts
      </p>
    </div>

    <div>
      <?php if ($canWrite): ?>
        <button 
          type="button" 
          onclick="openCreateUserModal()" 
          style="background: linear-gradient(135deg, var(--accent) 0%, #0369a1 100%); color: #fff; border: 1px solid var(--border-focus); border-radius: var(--radius-md); padding: 9px 18px; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px var(--accent-glow); transition: all 0.15s ease;"
        >
          <span style="font-size: 15px; line-height: 1;">+</span> Create User
        </button>
      <?php else: ?>
        <button 
          type="button" 
          disabled 
          style="background: var(--surface-alt); color: var(--text-dim); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 9px 18px; font-size: 13px; cursor: not-allowed; opacity: 0.6;" 
          title="Read-only permissions on this device"
        >
          🔒 Read-Only Mode
        </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Users Table Card -->
  <div class="grid-card" style="border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--panel); overflow: hidden;">
    <div class="table-container" style="overflow-x: auto;">
      <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
        <thead>
          <tr style="border-bottom: 1px solid var(--border); background: var(--surface-alt); color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em;">
            <th style="padding: 12px 16px;">User Profile</th>
            <th style="padding: 12px 16px;">Email Address</th>
            <th style="padding: 12px 16px;">Assigned Role</th>
            <th style="padding: 12px 16px;">Account Status</th>
            <th style="padding: 12px 16px;">Last Login</th>
            <th style="padding: 12px 16px; text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($users)): ?>
            <?php foreach ($users as $u): ?>
              <?php 
                $isSuperAdmin = !empty($u['is_super']) || (int)$u['id'] === 1;
                $initials = strtoupper(substr((string)$u['first_name'], 0, 1) . substr((string)$u['last_name'], 0, 1));
              ?>
              <tr style="border-bottom: 1px solid var(--border); transition: background 0.15s ease;">
                <!-- Profile & Name -->
                <td style="padding: 12px 16px;">
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <?php if (!empty($u['avatar_path'])): ?>
                      <img src="<?= View::e($u['avatar_path']) ?>" alt="Avatar" style="width: 34px; height: 34px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border-focus);">
                    <?php else: ?>
                      <div style="width: 34px; height: 34px; border-radius: 50%; background: rgba(56, 189, 248, 0.15); border: 1px solid var(--border-focus); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #38bdf8;">
                        <?= View::e($initials) ?>
                      </div>
                    <?php endif; ?>
                    <div>
                      <div style="font-weight: 700; color: #fff;">
                        <?= View::e($u['first_name']) ?> <?= View::e($u['last_name']) ?>
                      </div>
                      <div style="font-size: 10px; color: var(--text-dim);">ID #<?= (int)$u['id'] ?></div>
                    </div>
                  </div>
                </td>

                <!-- Email -->
                <td style="padding: 12px 16px; color: var(--text-main);">
                  <?= View::e($u['email']) ?>
                </td>

                <!-- Role -->
                <td style="padding: 12px 16px;">
                  <?php if ($isSuperAdmin): ?>
                    <span style="font-size: 11px; padding: 3px 8px; border-radius: var(--radius-pill); background: rgba(56, 189, 248, 0.15); border: 1px solid var(--border-focus); color: #38bdf8; font-weight: 700;">
                      🛡 Super Admin
                    </span>
                  <?php else: ?>
                    <span style="font-size: 11px; padding: 3px 8px; border-radius: var(--radius-pill); background: rgba(129, 140, 248, 0.15); border: 1px solid rgba(129, 140, 248, 0.4); color: #a5b4fc; font-weight: 600;">
                      <?= View::e($u['role_name']) ?>
                    </span>
                  <?php endif; ?>
                </td>

                <!-- Status -->
                <td style="padding: 12px 16px;">
                  <?php if (!empty($u['is_active'])): ?>
                    <span style="font-size: 11px; color: var(--success); font-weight: 600; display: flex; align-items: center; gap: 5px;">
                      <span style="width: 7px; height: 7px; border-radius: 50%; background: var(--success);"></span>
                      Active
                    </span>
                  <?php else: ?>
                    <span style="font-size: 11px; color: var(--danger); font-weight: 600; display: flex; align-items: center; gap: 5px;">
                      <span style="width: 7px; height: 7px; border-radius: 50%; background: var(--danger);"></span>
                      Inactive
                    </span>
                  <?php endif; ?>
                </td>

                <!-- Last Login -->
                <td style="padding: 12px 16px; color: var(--text-dim); font-size: 11px;">
                  <?= !empty($u['last_login_at']) ? date('M j, Y H:i', strtotime((string)$u['last_login_at'])) : 'Never' ?>
                </td>

                <!-- Actions -->
                <td style="padding: 12px 16px; text-align: right;">
                  <?php if ($canWrite): ?>
                    <div style="display: inline-flex; align-items: center; justify-content: flex-end; gap: 6px;">
                      <!-- Edit Button -->
                      <button 
                        type="button" 
                        onclick="openEditUserModal(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)" 
                        style="display: inline-flex; align-items: center; gap: 5px; height: 28px; background: var(--surface-alt); border: 1px solid var(--border); color: #fff; padding: 0 10px; border-radius: var(--radius-sm); font-size: 11px; font-weight: 600; cursor: pointer; line-height: 1; box-sizing: border-box; transition: all 0.15s ease;"
                      >
                        <span style="font-size: 12px;">✏️</span> Edit
                      </button>

                      <!-- Toggle Active Button (Protected on Super Admin) -->
                      <?php if (!$isSuperAdmin): ?>
                        <form method="POST" action="/users/<?= (int)$u['id'] ?>/toggle" style="margin: 0; display: inline-flex;">
                          <button 
                            type="submit" 
                            style="display: inline-flex; align-items: center; justify-content: center; height: 28px; background: var(--surface-alt); border: 1px solid var(--border); color: <?= !empty($u['is_active']) ? '#f87171' : 'var(--success)' ?>; padding: 0 10px; border-radius: var(--radius-sm); font-size: 11px; font-weight: 600; cursor: pointer; line-height: 1; box-sizing: border-box; transition: all 0.15s ease;"
                            title="<?= !empty($u['is_active']) ? 'Deactivate account' : 'Activate account' ?>"
                          >
                            <?= !empty($u['is_active']) ? 'Disable' : 'Enable' ?>
                          </button>
                        </form>

                        <!-- Delete Button -->
                        <form method="POST" action="/users/<?= (int)$u['id'] ?>/delete" onsubmit="return confirm('Are you sure you want to delete user <?= View::e($u['email']) ?>?');" style="margin: 0; display: inline-flex;">
                          <button 
                            type="submit" 
                            style="display: inline-flex; align-items: center; justify-content: center; height: 28px; width: 28px; background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: #fca5a5; padding: 0; border-radius: var(--radius-sm); font-size: 12px; cursor: pointer; line-height: 1; box-sizing: border-box; transition: all 0.15s ease;"
                            title="Delete user"
                          >
                            <span>🗑</span>
                          </button>
                        </form>
                      <?php else: ?>
                        <span style="display: inline-flex; align-items: center; height: 28px; font-size: 11px; font-weight: 600; color: var(--text-dim); padding: 0 8px; border: 1px solid transparent; box-sizing: border-box; line-height: 1;">
                          Locked
                        </span>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <span style="display: inline-flex; align-items: center; height: 28px; font-size: 11px; color: var(--text-dim);">Read Only</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" style="text-align: center; padding: 30px; color: var(--text-muted);">
                No users found.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ============================================================================
     User Modal Dialog (Create & Edit)
     ============================================================================ -->
<div id="userModal" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 16px;">
  <div style="background: var(--panel); border: 1px solid var(--border); border-radius: var(--radius-lg); width: 100%; max-width: 520px; box-shadow: var(--shadow-lg); overflow: hidden;">
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--border); background: var(--surface-alt);">
      <h3 id="modalTitle" style="font-size: 16px; font-weight: 700; color: #fff;">Create User Account</h3>
      <button type="button" onclick="closeUserModal()" style="background: none; border: none; color: var(--text-muted); font-size: 18px; cursor: pointer;">&times;</button>
    </div>

    <form id="userForm" method="POST" action="/users" style="padding: 20px; display: flex; flex-direction: column; gap: 14px;">
      <input type="hidden" id="userId" name="id" value="">

      <!-- Full Name (First & Last) -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
        <div>
          <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px;">First Name *</label>
          <input type="text" id="userFirstName" name="first_name" required style="width: 100%; background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-md); color: #fff; padding: 8px 12px; font-size: 13px;">
        </div>
        <div>
          <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px;">Last Name *</label>
          <input type="text" id="userLastName" name="last_name" required style="width: 100%; background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-md); color: #fff; padding: 8px 12px; font-size: 13px;">
        </div>
      </div>

      <!-- Email Address -->
      <div>
        <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px;">Email Address *</label>
        <input type="email" id="userEmail" name="email" required style="width: 100%; background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-md); color: #fff; padding: 8px 12px; font-size: 13px;">
      </div>

      <!-- Role Selection -->
      <div>
        <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px;">Assigned Role *</label>
        <select id="userRoleId" name="role_id" required style="width: 100%; background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-md); color: #fff; padding: 8px 12px; font-size: 13px;">
          <?php foreach ($roles as $r): ?>
            <option value="<?= (int)$r['id'] ?>">
              <?= View::e($r['name']) ?><?= !empty($r['is_super']) ? ' (Super Admin)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Password & Generator Button -->
      <div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
          <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">
            Password <span id="passwordHintText">*</span>
          </label>
          <button 
            type="button" 
            onclick="generateSecurePassword()" 
            style="background: rgba(56, 189, 248, 0.15); border: 1px solid var(--border-focus); color: #38bdf8; padding: 2px 8px; border-radius: var(--radius-sm); font-size: 10px; font-weight: 700; cursor: pointer;"
          >
            🎲 Generate Random Password
          </button>
        </div>
        <div style="position: relative;">
          <input 
            type="text" 
            id="userPassword" 
            name="password" 
            style="width: 100%; background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-md); color: #38bdf8; font-family: var(--font-mono); padding: 8px 12px; font-size: 13px;"
            placeholder="Minimum 8 characters"
          >
        </div>
        <div id="passwordEditNote" style="display: none; font-size: 11px; color: var(--text-dim); margin-top: 4px;">
          Leave empty to preserve existing password.
        </div>
      </div>

      <!-- Avatar Path -->
      <div>
        <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px;">Profile Image / Avatar URL (Optional)</label>
        <input type="text" id="userAvatarPath" name="avatar_path" placeholder="/images/avatars/user.jpg" style="width: 100%; background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-md); color: #fff; padding: 8px 12px; font-size: 13px;">
      </div>

      <!-- Active Checkbox -->
      <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
        <input type="checkbox" id="userIsActive" name="is_active" value="1" checked style="accent-color: var(--accent); width: 16px; height: 16px;">
        <label for="userIsActive" style="font-size: 12px; color: #fff; font-weight: 600;">Account is active & permitted to log in</label>
      </div>

      <!-- Modal Footer Controls -->
      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border);">
        <button type="button" onclick="closeUserModal()" style="background: var(--surface-alt); border: 1px solid var(--border); color: #fff; padding: 8px 16px; border-radius: var(--radius-md); font-size: 13px; cursor: pointer;">
          Cancel
        </button>
        <button type="submit" style="background: linear-gradient(135deg, var(--accent) 0%, #0369a1 100%); border: 1px solid var(--border-focus); color: #fff; padding: 8px 20px; border-radius: var(--radius-md); font-size: 13px; font-weight: 700; cursor: pointer;">
          Save User Record
        </button>
      </div>
    </form>
  </div>
</div>

<script>
/**
 * Opens modal configured for creating a new user record.
 */
function openCreateUserModal() {
  document.getElementById('modalTitle').textContent = 'Create User Account';
  document.getElementById('userForm').action = '/users';
  document.getElementById('userId').value = '';
  document.getElementById('userFirstName').value = '';
  document.getElementById('userLastName').value = '';
  document.getElementById('userEmail').value = '';
  document.getElementById('userPassword').value = '';
  document.getElementById('userPassword').required = true;
  document.getElementById('passwordHintText').textContent = '*';
  document.getElementById('passwordEditNote').style.display = 'none';
  document.getElementById('userAvatarPath').value = '';
  document.getElementById('userIsActive').checked = true;

  // Auto-generate initial secure password for convenience
  generateSecurePassword();

  const modal = document.getElementById('userModal');
  modal.style.display = 'flex';
}

/**
 * Opens modal configured for editing an existing user record.
 */
function openEditUserModal(user) {
  document.getElementById('modalTitle').textContent = 'Edit User #' + user.id + ' (' + user.email + ')';
  document.getElementById('userForm').action = '/users/' + user.id + '/update';
  document.getElementById('userId').value = user.id;
  document.getElementById('userFirstName').value = user.first_name || '';
  document.getElementById('userLastName').value = user.last_name || '';
  document.getElementById('userEmail').value = user.email || '';
  document.getElementById('userRoleId').value = user.role_id || 1;
  document.getElementById('userPassword').value = '';
  document.getElementById('userPassword').required = false;
  document.getElementById('passwordHintText').textContent = '(Optional)';
  document.getElementById('passwordEditNote').style.display = 'block';
  document.getElementById('userAvatarPath').value = user.avatar_path || '';
  document.getElementById('userIsActive').checked = parseInt(user.is_active, 10) === 1;

  const modal = document.getElementById('userModal');
  modal.style.display = 'flex';
}

/**
 * Closes the user modal.
 */
function closeUserModal() {
  document.getElementById('userModal').style.display = 'none';
}

/**
 * Generates a cryptographically strong 16-character random password.
 */
function generateSecurePassword() {
  const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
  const array = new Uint32Array(16);
  window.crypto.getRandomValues(array);
  
  let password = '';
  for (let i = 0; i < array.length; i++) {
    password += chars[array[i] % chars.length];
  }
  
  const passwordInput = document.getElementById('userPassword');
  passwordInput.value = password;
  passwordInput.type = 'text'; // Make it visible so the admin can copy/save it
  passwordInput.select();
}
</script>
