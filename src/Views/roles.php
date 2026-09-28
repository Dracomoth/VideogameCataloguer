<?php
/**
 * src/Views/roles.php
 * Role Management & Dual-Device Permissions Matrix View.
 * Injected into the <main> dynamic body slot of layout.php.
 *
 * Variables provided by RoleController:
 * @var array<int, array<string, mixed>> $roles
 * @var int $selectedId
 * @var array<string, mixed>|null $selectedRole
 * @var array<int, array<string, mixed>> $screensWithPermissions
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

$isSuper = !empty($selectedRole['is_super']) || $selectedId === 1;
?>
<div class="roles-view-container" style="display: flex; flex-direction: column; gap: 20px; padding: 24px;">
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

  <!-- Top Action Header -->
  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    <div>
      <h2 style="font-size: 20px; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 8px;">
        <span>🛡</span> Roles & Dual-Device Permission Matrix
      </h2>
      <p style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
        Define granular access privileges per role with separate rules for desktop PCs and other devices (handhelds, mobiles, tablets).
      </p>
    </div>

    <div>
      <?php if ($canWrite): ?>
        <button 
          type="button" 
          onclick="openCreateRoleModal()" 
          style="background: linear-gradient(135deg, var(--accent) 0%, #0369a1 100%); color: #fff; border: 1px solid var(--border-focus); border-radius: var(--radius-md); padding: 9px 18px; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px var(--accent-glow); transition: all 0.15s ease;"
        >
          <span style="font-size: 15px; line-height: 1;">+</span> Create Role
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

  <!-- Two-Column Workbench Layout -->
  <div style="display: grid; grid-template-columns: minmax(280px, 340px) 1fr; gap: 20px; align-items: start;">
    <!-- LEFT COLUMN: Roles List Navigation -->
    <div style="display: flex; flex-direction: column; gap: 10px;">
      <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 2px;">
        Configured Roles (<?= count($roles) ?>)
      </div>

      <?php foreach ($roles as $r): ?>
        <?php 
          $isActive = (int)$r['id'] === $selectedId;
          $rIsSuper = !empty($r['is_super']) || (int)$r['id'] === 1;
        ?>
        <a 
          href="/roles?role_id=<?= (int)$r['id'] ?>" 
          style="display: block; padding: 14px 16px; background: <?= $isActive ? 'var(--panel-hover)' : 'var(--panel)' ?>; border: 1px solid <?= $isActive ? 'var(--border-focus)' : 'var(--border)' ?>; border-radius: var(--radius-md); text-decoration: none; color: inherit; transition: all 0.15s ease; box-shadow: <?= $isActive ? '0 0 12px var(--accent-glow)' : 'none' ?>;"
        >
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <div style="font-weight: 700; font-size: 14px; color: <?= $isActive ? '#38bdf8' : '#fff' ?>; display: flex; align-items: center; gap: 6px;">
              <span><?= $rIsSuper ? '👑' : '🛡' ?></span>
              <?= View::e($r['name']) ?>
            </div>
            <div>
              <?php if ($rIsSuper): ?>
                <span style="font-size: 10px; padding: 2px 8px; border-radius: var(--radius-pill); background: rgba(56, 189, 248, 0.18); color: #38bdf8; font-weight: 700; border: 1px solid var(--border-focus);">
                  Super User
                </span>
              <?php else: ?>
                <span style="font-size: 10px; padding: 2px 8px; border-radius: var(--radius-pill); background: rgba(56, 189, 248, 0.18); color: #38bdf8; font-weight: 700; border: 1px solid var(--border-focus);">
                  <?= (int)$r['user_count'] ?> user(s)
                </span>
              <?php endif; ?>
            </div>
          </div>

          <div style="font-size: 11px; color: var(--text-muted); line-height: 1.4;">
            <?= View::e($r['description'] ?? 'No description provided.') ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- RIGHT COLUMN: Dual-Device Permissions Matrix -->
    <div class="grid-card" style="border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--panel); padding: 24px;">
      <?php if ($selectedRole): ?>
        <form method="POST" action="/roles/<?= (int)$selectedRole['id'] ?>/update">
          <!-- Role Details Header Bar -->
          <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border); flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px;">
              <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px;">Role Title</label>
              <input 
                type="text" 
                name="name" 
                value="<?= View::e($selectedRole['name']) ?>" 
                <?= ($isSuper || !$canWrite) ? 'readonly' : '' ?>
                required 
                style="width: 100%; background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 9px 12px; font-size: 14px; font-weight: 700; color: <?= ($isSuper || !$canWrite) ? '#94a3b8' : '#fff' ?>; <?= ($isSuper || !$canWrite) ? 'cursor: not-allowed;' : '' ?> outline: none; transition: border-color 0.15s ease;"
              >
            </div>

            <div style="flex: 2; min-width: 250px;">
              <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px;">Description</label>
              <input 
                type="text" 
                name="description" 
                value="<?= View::e($selectedRole['description'] ?? '') ?>" 
                <?= ($isSuper || !$canWrite) ? 'readonly' : '' ?>
                placeholder="Operational purpose of this role" 
                style="width: 100%; background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 9px 12px; font-size: 13px; color: <?= ($isSuper || !$canWrite) ? '#94a3b8' : '#fff' ?>; <?= ($isSuper || !$canWrite) ? 'cursor: not-allowed;' : '' ?> outline: none; transition: border-color 0.15s ease;"
              >
            </div>

            <?php if (!$isSuper && $canWrite && (int)$selectedRole['user_count'] === 0): ?>
              <div style="display: flex; align-items: flex-end; height: 100%; padding-top: 22px;">
                <button 
                  type="button" 
                  onclick="deleteRole(<?= (int)$selectedRole['id'] ?>, '<?= View::e($selectedRole['name']) ?>')" 
                  style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: #fca5a5; padding: 9px 14px; border-radius: var(--radius-md); font-size: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;"
                >
                  <span>🗑</span> Delete Role
                </button>
              </div>
            <?php endif; ?>
          </div>

          <!-- Super Admin Explanatory Notice -->
          <?php if ($isSuper): ?>
            <div style="background: rgba(56, 189, 248, 0.1); border: 1px solid var(--border-focus); border-radius: var(--radius-md); padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
              <span style="font-size: 24px;">🛡</span>
              <div style="font-size: 13px; color: #bae6fd; line-height: 1.4;">
                <strong>Super User Bypass Active:</strong> Accounts assigned to <strong>Super Admin</strong> automatically possess unrestricted read and write privileges across every screen and all devices. Matrix configuration is locked.
              </div>
            </div>
          <?php else: ?>
            <!-- Quick Preset Tools -->
            <?php if ($canWrite): ?>
              <div style="display: flex; justify-content: space-between; align-items: center; background: var(--surface-alt); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--border); margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">
                  ⚡ Batch Quick-Set Presets:
                </span>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                  <button type="button" onclick="setAll('pc', 'write')" class="preset-btn">PC: All Write</button>
                  <button type="button" onclick="setAll('pc', 'read')" class="preset-btn">PC: All Read</button>
                  <button type="button" onclick="setAll('pc', 'none')" class="preset-btn">PC: All None</button>
                  <span style="color: var(--border); margin: 0 4px;">|</span>
                  <button type="button" onclick="setAll('other', 'write')" class="preset-btn">Other: All Write</button>
                  <button type="button" onclick="setAll('other', 'read')" class="preset-btn">Other: All Read</button>
                  <button type="button" onclick="setAll('other', 'none')" class="preset-btn">Other: All None</button>
                </div>
              </div>
            <?php endif; ?>
          <?php endif; ?>

          <!-- Permissions Matrix Table -->
          <div style="overflow-x: auto; border: 1px solid var(--border); border-radius: var(--radius-md);">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
              <thead>
                <tr style="background: var(--surface-alt); border-bottom: 1px solid var(--border); color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em;">
                  <th style="padding: 12px 16px;">Application Screen</th>
                  <th style="padding: 12px 16px; width: 280px; text-align: center;">🖥 PC Access (Desktop)</th>
                  <th style="padding: 12px 16px; width: 280px; text-align: center;">📱 Other Devices (Mobile / Handheld)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($screensWithPermissions as $s): ?>
                  <?php 
                    $key = $s['screen_key'];
                    $accessPc = $s['access_pc'];
                    $accessOther = $s['access_other'];
                  ?>
                  <tr style="border-bottom: 1px solid var(--border); transition: background 0.15s ease;">
                    <!-- Screen Identifier -->
                    <td style="padding: 12px 16px;">
                      <div style="font-weight: 700; color: #fff; font-size: 13px;">
                        <?= View::e($s['name']) ?>
                      </div>
                      <div style="display: flex; align-items: center; gap: 6px; margin-top: 2px;">
                        <code style="font-size: 10px; color: var(--border-focus);"><?= View::e($key) ?></code>
                        <span style="font-size: 9px; color: var(--text-dim); background: var(--surface-alt); padding: 1px 5px; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                          <?= View::e($s['category']) ?>
                        </span>
                      </div>
                    </td>

                    <!-- PC Access Segmented Radios -->
                    <td style="padding: 10px 16px; text-align: center;">
                      <?php if ($isSuper): ?>
                        <span style="font-size: 11px; color: var(--success); font-weight: 700;">✓ Full Write (Super)</span>
                      <?php else: ?>
                        <div class="perm-segment-group">
                          <label class="perm-segment <?= $accessPc === 'none' ? 'active-none' : '' ?>">
                            <input type="radio" name="permissions[<?= View::e($key) ?>][access_pc]" value="none" <?= $accessPc === 'none' ? 'checked' : '' ?> <?= !$canWrite ? 'disabled' : '' ?>>
                            None
                          </label>
                          <label class="perm-segment <?= $accessPc === 'read' ? 'active-read' : '' ?>">
                            <input type="radio" name="permissions[<?= View::e($key) ?>][access_pc]" value="read" <?= $accessPc === 'read' ? 'checked' : '' ?> <?= !$canWrite ? 'disabled' : '' ?>>
                            Read
                          </label>
                          <label class="perm-segment <?= $accessPc === 'write' ? 'active-write' : '' ?>">
                            <input type="radio" name="permissions[<?= View::e($key) ?>][access_pc]" value="write" <?= $accessPc === 'write' ? 'checked' : '' ?> <?= !$canWrite ? 'disabled' : '' ?>>
                            Write
                          </label>
                        </div>
                      <?php endif; ?>
                    </td>

                    <!-- Other Devices Segmented Radios -->
                    <td style="padding: 10px 16px; text-align: center;">
                      <?php if ($isSuper): ?>
                        <span style="font-size: 11px; color: var(--success); font-weight: 700;">✓ Full Write (Super)</span>
                      <?php else: ?>
                        <div class="perm-segment-group">
                          <label class="perm-segment <?= $accessOther === 'none' ? 'active-none' : '' ?>">
                            <input type="radio" name="permissions[<?= View::e($key) ?>][access_other]" value="none" <?= $accessOther === 'none' ? 'checked' : '' ?> <?= !$canWrite ? 'disabled' : '' ?>>
                            None
                          </label>
                          <label class="perm-segment <?= $accessOther === 'read' ? 'active-read' : '' ?>">
                            <input type="radio" name="permissions[<?= View::e($key) ?>][access_other]" value="read" <?= $accessOther === 'read' ? 'checked' : '' ?> <?= !$canWrite ? 'disabled' : '' ?>>
                            Read
                          </label>
                          <label class="perm-segment <?= $accessOther === 'write' ? 'active-write' : '' ?>">
                            <input type="radio" name="permissions[<?= View::e($key) ?>][access_other]" value="write" <?= $accessOther === 'write' ? 'checked' : '' ?> <?= !$canWrite ? 'disabled' : '' ?>>
                            Write
                          </label>
                        </div>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Bottom Sticky Save Bar -->
          <?php if (!$isSuper && $canWrite): ?>
            <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
              <button 
                type="submit" 
                style="background: linear-gradient(135deg, var(--accent) 0%, #0369a1 100%); border: 1px solid var(--border-focus); color: #fff; padding: 10px 24px; border-radius: var(--radius-md); font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px var(--accent-glow);"
              >
                <span>💾</span> Save Permissions Matrix
              </button>
            </div>
          <?php endif; ?>
        </form>
      <?php else: ?>
        <div style="text-align: center; color: var(--text-dim); padding: 40px;">
          Select a role from the left menu to view and configure its permissions matrix.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ============================================================================
     Create Role Modal Dialog
     ============================================================================ -->
<div id="roleModal" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 16px;">
  <div style="background: var(--panel); border: 1px solid var(--border); border-radius: var(--radius-lg); width: 100%; max-width: 480px; box-shadow: var(--shadow-lg); overflow: hidden;">
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--border); background: var(--surface-alt);">
      <h3 style="font-size: 16px; font-weight: 700; color: #fff;">Create New Custom Role</h3>
      <button type="button" onclick="closeCreateRoleModal()" style="background: none; border: none; color: var(--text-muted); font-size: 18px; cursor: pointer;">&times;</button>
    </div>

    <form method="POST" action="/roles" style="padding: 20px; display: flex; flex-direction: column; gap: 14px;">
      <div>
        <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px;">Role Title *</label>
        <input type="text" name="name" placeholder="e.g. Cataloguer, Inventory Lead" required style="width: 100%; background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-md); color: #fff; padding: 8px 12px; font-size: 13px;">
      </div>

      <div>
        <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px;">Role Description</label>
        <textarea name="description" rows="3" placeholder="Describe the operational responsibilities of this role..." style="width: 100%; background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-md); color: #fff; padding: 8px 12px; font-size: 13px; resize: vertical;"></textarea>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border);">
        <button type="button" onclick="closeCreateRoleModal()" style="background: var(--surface-alt); border: 1px solid var(--border); color: #fff; padding: 8px 16px; border-radius: var(--radius-md); font-size: 13px; cursor: pointer;">
          Cancel
        </button>
        <button type="submit" style="background: linear-gradient(135deg, var(--accent) 0%, #0369a1 100%); border: 1px solid var(--border-focus); color: #fff; padding: 8px 20px; border-radius: var(--radius-md); font-size: 13px; font-weight: 700; cursor: pointer;">
          Create & Configure Matrix
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteRoleForm" method="POST" style="display: none;"></form>

<style>
.perm-segment-group {
  display: inline-flex;
  background: var(--surface-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 2px;
  gap: 2px;
}
.perm-segment {
  padding: 4px 10px;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  border-radius: 3px;
  color: var(--text-dim);
  transition: all 0.15s ease;
  user-select: none;
}
.perm-segment input[type="radio"] {
  display: none;
}
.perm-segment:hover {
  color: #fff;
}
.perm-segment.active-none, .perm-segment:has(input[value="none"]:checked) {
  background: rgba(148, 163, 184, 0.18);
  color: #94a3b8;
}
.perm-segment.active-read, .perm-segment:has(input[value="read"]:checked) {
  background: rgba(56, 189, 248, 0.22);
  color: #38bdf8;
  font-weight: 700;
}
.perm-segment.active-write, .perm-segment:has(input[value="write"]:checked) {
  background: rgba(16, 185, 129, 0.25);
  color: #34d399;
  font-weight: 700;
}
.preset-btn {
  background: var(--panel);
  border: 1px solid var(--border);
  color: var(--text-muted);
  font-size: 10px;
  font-weight: 600;
  padding: 3px 7px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: all 0.15s ease;
}
.preset-btn:hover {
  color: #fff;
  border-color: var(--border-focus);
}
</style>

<script>
function openCreateRoleModal() {
  document.getElementById('roleModal').style.display = 'flex';
}

function closeCreateRoleModal() {
  document.getElementById('roleModal').style.display = 'none';
}

function deleteRole(roleId, roleName) {
  if (confirm('Are you sure you want to delete the role "' + roleName + '"? All matrix configurations will be removed.')) {
    const form = document.getElementById('deleteRoleForm');
    form.action = '/roles/' + roleId + '/delete';
    form.submit();
  }
}

/**
 * Batch quick-sets all screens for a device target ('pc' or 'other') to a level ('none', 'read', 'write').
 */
function setAll(device, level) {
  const inputs = document.querySelectorAll(`input[name*="[access_${device}]"][value="${level}"]`);
  inputs.forEach(input => {
    input.checked = true;
    // Update visual classes on segments
    const group = input.closest('.perm-segment-group');
    if (group) {
      group.querySelectorAll('.perm-segment').forEach(seg => {
        seg.classList.remove('active-none', 'active-read', 'active-write');
      });
      const segment = input.closest('.perm-segment');
      if (segment) {
        segment.classList.add(`active-${level}`);
      }
    }
  });
}

// Add dynamic click styling for radio labels
document.addEventListener('change', function(e) {
  if (e.target.matches('.perm-segment input[type="radio"]')) {
    const group = e.target.closest('.perm-segment-group');
    if (group) {
      group.querySelectorAll('.perm-segment').forEach(seg => {
        seg.classList.remove('active-none', 'active-read', 'active-write');
      });
      const activeSegment = e.target.closest('.perm-segment');
      if (activeSegment) {
        activeSegment.classList.add(`active-${e.target.value}`);
      }
    }
  }
});
</script>
