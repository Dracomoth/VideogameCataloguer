<?php
/**
 * src/Views/profile_modal.php
 * Interactive Self-Service User Profile & Avatar Customization Modal Dialog.
 * Included seamlessly in header.php for authenticated users.
 */

declare(strict_types=1);

use Vault\Auth\Auth;
use Vault\Services\View;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

$activeUser = Auth::user();
if ($activeUser === null) {
    return;
}

$initAvatarUrl = !empty($activeUser['avatar_path'])
    ? View::assetUrl((string)$activeUser['avatar_path'], $activeUser['updated_at'] ?? null)
    : null;

$first = trim((string)($activeUser['first_name'] ?? ''));
$last  = trim((string)($activeUser['last_name'] ?? ''));
$fullName = trim($first . ' ' . $last);
if ($fullName === '') {
    $fullName = 'User';
}

$initials = '';
if ($first !== '') $initials .= substr($first, 0, 1);
if ($last !== '')  $initials .= substr($last, 0, 1);
$initials = strtoupper($initials ?: 'U');
?>

<!-- ============================================================================
     User Profile & Avatar Management Modal Dialog
     ============================================================================ -->
<div 
  id="userProfileModal" 
  class="profile-modal-backdrop" 
  style="display: none;" 
  role="dialog" 
  aria-modal="true" 
  aria-labelledby="profileModalTitle"
  onclick="handleProfileBackdropClick(event)"
>
  <div class="profile-modal-dialog" onclick="event.stopPropagation()">
    <!-- Modal Header -->
    <div class="profile-modal-header">
      <div class="profile-modal-title-group">
        <div class="profile-modal-header-icon">👤</div>
        <div>
          <h2 id="profileModalTitle" class="profile-modal-title">My User Profile</h2>
          <p class="profile-modal-subtitle">View and update your personal information, credentials, and avatar</p>
        </div>
      </div>
      <button 
        type="button" 
        class="profile-modal-close-btn" 
        onclick="closeUserProfileModal()" 
        aria-label="Close Profile Dialog" 
        title="Close (Esc)"
      >
        ✕
      </button>
    </div>

    <!-- Modal Dynamic Alert / Message Box -->
    <div id="profileModalAlert" class="profile-alert" style="display: none;"></div>

    <!-- Profile Form -->
    <form id="userProfileForm" onsubmit="handleProfileFormSubmit(event)" enctype="multipart/form-data" novalidate>
      <input type="hidden" id="profileDeleteAvatar" name="delete_avatar" value="0">

      <!-- Section 1: Avatar Upload & Management Box -->
      <div class="profile-section">
        <label class="profile-section-title">Profile Avatar</label>
        <div class="profile-avatar-row">
          <!-- Circular Avatar Preview -->
          <div class="profile-avatar-preview-wrap">
            <div id="profileAvatarPreviewCircle" class="profile-avatar-preview-circle">
              <?php if (!empty($initAvatarUrl)): ?>
                <img 
                  id="profileModalAvatarImg" 
                  src="<?= View::e($initAvatarUrl) ?>" 
                  alt="Avatar Preview" 
                  class="profile-avatar-preview-img"
                >
              <?php else: ?>
                <div id="profileModalAvatarInitials" class="profile-avatar-preview-initials">
                  <?= View::e($initials) ?>
                </div>
              <?php endif; ?>
            </div>
            <div id="profileAvatarBadge" class="profile-avatar-status-badge" title="Avatar loaded">✓</div>
          </div>

          <!-- Interactive Upload Dropzone Box -->
          <div 
            id="profileAvatarDropzone" 
            class="profile-avatar-dropzone" 
            onclick="triggerAvatarFileInput()"
            ondragover="handleAvatarDragOver(event)"
            ondragleave="handleAvatarDragLeave(event)"
            ondrop="handleAvatarDrop(event)"
          >
            <input 
              type="file" 
              id="profileAvatarInput" 
              name="avatar" 
              accept="image/jpeg,image/png,image/webp,image/gif" 
              style="display: none;" 
              onchange="handleAvatarFileSelect(this.files)"
            >
            <div class="dropzone-icon">📷</div>
            <div class="dropzone-text-block">
              <span class="dropzone-primary-text">Click or drag & drop an image here to upload</span>
              <span class="dropzone-secondary-text">Supported formats: JPG, PNG, WEBP, GIF (Saved as <code>&lt;id&gt;_avatar.&lt;ext&gt;</code>)</span>
            </div>
            <div id="profileSelectedFileName" class="profile-selected-file" style="display: none;"></div>
          </div>
        </div>

        <!-- Avatar Controls (Remove / Undo) -->
        <div class="profile-avatar-actions">
          <button 
            type="button" 
            id="profileRemoveAvatarBtn" 
            class="profile-action-btn profile-action-btn-danger" 
            onclick="toggleRemoveAvatar()"
            style="<?= empty($initAvatarUrl) ? 'display: none;' : '' ?>"
          >
            🗑 Remove Avatar
          </button>
          <button 
            type="button" 
            id="profileResetFileBtn" 
            class="profile-action-btn profile-action-btn-secondary" 
            onclick="resetStagedAvatarFile()" 
            style="display: none;"
          >
            ↩ Cancel Image Selection
          </button>
          <span id="profileAvatarNote" class="profile-avatar-note"></span>
        </div>
      </div>

      <!-- Section 2: Personal Details Grid -->
      <div class="profile-section">
        <label class="profile-section-title">Personal Information</label>
        
        <!-- First & Last Name -->
        <div class="profile-form-grid">
          <div class="profile-field-group">
            <label for="profileFirstName" class="profile-field-label">First Name <span class="required-star">*</span></label>
            <input 
              type="text" 
              id="profileFirstName" 
              name="first_name" 
              value="<?= View::e($activeUser['first_name'] ?? '') ?>" 
              required 
              maxlength="50"
              class="profile-input" 
              placeholder="e.g. John"
            >
          </div>
          <div class="profile-field-group">
            <label for="profileLastName" class="profile-field-label">Last Name <span class="required-star">*</span></label>
            <input 
              type="text" 
              id="profileLastName" 
              name="last_name" 
              value="<?= View::e($activeUser['last_name'] ?? '') ?>" 
              required 
              maxlength="50"
              class="profile-input" 
              placeholder="e.g. Doe"
            >
          </div>
        </div>

        <!-- Username & Email Address -->
        <div class="profile-form-grid" style="margin-top: 14px;">
          <div class="profile-field-group">
            <label for="profileUsername" class="profile-field-label">Username <span class="required-star">*</span></label>
            <div class="profile-input-with-prefix">
              <span class="profile-prefix">@</span>
              <input 
                type="text" 
                id="profileUsername" 
                name="username" 
                value="<?= View::e($activeUser['username'] ?? '') ?>" 
                required 
                pattern="[a-zA-Z0-9_.-]{3,50}"
                title="3 to 50 characters: letters, numbers, underscores, dashes, dots"
                class="profile-input profile-input-prefixed" 
                placeholder="username"
              >
            </div>
            <span class="profile-field-help">Used to sign in along with your email.</span>
          </div>
          <div class="profile-field-group">
            <label for="profileEmail" class="profile-field-label">Email Address <span class="required-star">*</span></label>
            <input 
              type="email" 
              id="profileEmail" 
              name="email" 
              value="<?= View::e($activeUser['email'] ?? '') ?>" 
              required 
              class="profile-input" 
              placeholder="user@example.com"
            >
            <span class="profile-field-help">Account notifications & login identifier.</span>
          </div>
        </div>

        <!-- Role & Status Badges (Read-Only) -->
        <div class="profile-badges-row">
          <div class="profile-badge-item">
            <span class="profile-badge-label">Assigned Role:</span>
            <span id="profileRoleBadge" class="profile-pill-badge role-badge">
              🛡 <?= View::e($activeUser['role_name'] ?? 'User') ?><?= !empty($activeUser['is_super']) ? ' (Super Admin)' : '' ?>
            </span>
          </div>
          <div class="profile-badge-item">
            <span class="profile-badge-label">Account Status:</span>
            <span id="profileStatusBadge" class="profile-pill-badge status-active-badge">
              ● Active Account
            </span>
          </div>
        </div>
      </div>

      <!-- Section 3: Password Change Box -->
      <div class="profile-section password-section">
        <div class="password-section-header">
          <div>
            <label class="profile-section-title" style="margin-bottom: 2px;">Change Password</label>
            <p class="profile-field-help" style="margin: 0;">Leave both fields empty to keep your existing password unchanged.</p>
          </div>
          <button 
            type="button" 
            class="profile-generate-pwd-btn" 
            onclick="generateProfilePassword()"
            title="Generate a secure random password"
          >
            🎲 Generate Password
          </button>
        </div>

        <div class="profile-form-grid" style="margin-top: 12px;">
          <!-- New Password -->
          <div class="profile-field-group">
            <label for="profilePassword" class="profile-field-label">New Password</label>
            <div class="password-input-wrapper">
              <input 
                type="password" 
                id="profilePassword" 
                name="password" 
                autocomplete="new-password"
                minlength="8"
                class="profile-input password-input" 
                placeholder="Minimum 8 characters"
                oninput="validatePasswordMatch()"
              >
              <button 
                type="button" 
                class="password-toggle-btn" 
                onclick="togglePasswordVisibility('profilePassword', this)" 
                aria-label="Toggle password visibility"
                title="Show / Hide"
              >
                👁️
              </button>
            </div>
          </div>

          <!-- Password Confirmation -->
          <div class="profile-field-group">
            <label for="profilePasswordConfirmation" class="profile-field-label">Confirm New Password</label>
            <div class="password-input-wrapper">
              <input 
                type="password" 
                id="profilePasswordConfirmation" 
                name="password_confirmation" 
                autocomplete="new-password"
                class="profile-input password-input" 
                placeholder="Repeat new password"
                oninput="validatePasswordMatch()"
              >
              <button 
                type="button" 
                class="password-toggle-btn" 
                onclick="togglePasswordVisibility('profilePasswordConfirmation', this)" 
                aria-label="Toggle password confirmation visibility"
                title="Show / Hide"
              >
                👁️
              </button>
            </div>
          </div>
        </div>

        <!-- Live Password Match Status Message -->
        <div id="passwordMatchStatus" class="password-match-status" style="display: none;"></div>
      </div>

      <!-- Modal Footer Action Buttons -->
      <div class="profile-modal-footer">
        <button 
          type="button" 
          class="profile-btn profile-btn-secondary" 
          onclick="closeUserProfileModal()"
        >
          Cancel
        </button>
        <button 
          type="submit" 
          id="profileSaveBtn" 
          class="profile-btn profile-btn-primary"
        >
          <span class="btn-icon">💾</span>
          <span id="profileSaveBtnText">Save Changes</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ============================================================================
     User Profile Modal CSS Styles
     ============================================================================ -->
<style>
.profile-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(3, 7, 18, 0.78);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  overflow-y: auto;
  animation: profileFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes profileFadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.profile-modal-dialog {
  background: #111827;
  border: 1px solid rgba(56, 189, 248, 0.28);
  border-radius: 12px;
  width: 100%;
  max-width: 580px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.75), 0 0 20px rgba(56, 189, 248, 0.15);
  overflow: hidden;
  max-height: 92vh;
  display: flex;
  flex-direction: column;
  animation: profileSlideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes profileSlideUp {
  from {
    transform: translateY(18px) scale(0.97);
    opacity: 0;
  }
  to {
    transform: translateY(0) scale(1);
    opacity: 1;
  }
}

.profile-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 22px;
  background: linear-gradient(135deg, rgba(15, 23, 42, 0.95) 0%, rgba(30, 41, 59, 0.85) 100%);
  border-bottom: 1px solid rgba(56, 189, 248, 0.2);
}

.profile-modal-title-group {
  display: flex;
  align-items: center;
  gap: 12px;
}

.profile-modal-header-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: rgba(56, 189, 248, 0.15);
  border: 1px solid rgba(56, 189, 248, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: #38bdf8;
}

.profile-modal-title {
  font-size: 17px;
  font-weight: 700;
  color: #f8fafc;
  margin: 0;
  line-height: 1.2;
}

.profile-modal-subtitle {
  font-size: 12px;
  color: #94a3b8;
  margin: 3px 0 0 0;
}

.profile-modal-close-btn {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #94a3b8;
  font-size: 16px;
  width: 32px;
  height: 32px;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}

.profile-modal-close-btn:hover {
  background: rgba(239, 68, 68, 0.2);
  border-color: rgba(239, 68, 68, 0.4);
  color: #f87171;
}

#userProfileForm {
  padding: 22px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.profile-alert {
  margin: 16px 22px 0 22px;
  padding: 10px 14px;
  border-radius: 6px;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.profile-alert.error {
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid #ef4444;
  color: #fca5a5;
}
.profile-alert.success {
  background: rgba(16, 185, 129, 0.15);
  border: 1px solid #10b981;
  color: #6ee7b7;
}

.profile-section {
  background: rgba(17, 24, 39, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 8px;
  padding: 16px;
}

.profile-section-title {
  display: block;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #38bdf8;
  margin-bottom: 12px;
}

.profile-avatar-row {
  display: flex;
  align-items: center;
  gap: 16px;
}

.profile-avatar-preview-wrap {
  position: relative;
  flex-shrink: 0;
}

.profile-avatar-preview-circle {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  border: 2px solid #38bdf8;
  box-shadow: 0 0 16px rgba(56, 189, 248, 0.35);
  background: #0f172a;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  user-select: none;
}

.profile-avatar-preview-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.profile-avatar-preview-initials {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #0284c7 0%, #2563eb 50%, #4f46e5 100%);
  color: #ffffff;
  font-size: 24px;
  font-weight: 800;
  letter-spacing: 1px;
  text-transform: uppercase;
}

.profile-avatar-status-badge {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: #10b981;
  color: #ffffff;
  font-size: 12px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #111827;
}

.profile-avatar-dropzone {
  flex: 1;
  border: 2px dashed rgba(56, 189, 248, 0.35);
  border-radius: 8px;
  background: rgba(15, 23, 42, 0.6);
  padding: 14px;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
  min-height: 72px;
  box-sizing: border-box;
}

.profile-avatar-dropzone:hover,
.profile-avatar-dropzone.drag-active {
  border-color: #38bdf8;
  background: rgba(56, 189, 248, 0.1);
  transform: translateY(-1px);
}

.dropzone-icon {
  font-size: 22px;
  flex-shrink: 0;
}

.dropzone-text-block {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.dropzone-primary-text {
  font-size: 13px;
  font-weight: 600;
  color: #f8fafc;
}

.dropzone-secondary-text {
  font-size: 11px;
  color: #94a3b8;
  line-height: 1.3;
}

.profile-selected-file {
  margin-top: 6px;
  font-size: 12px;
  color: #38bdf8;
  font-weight: 600;
  word-break: break-all;
}

.profile-avatar-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 10px;
}

.profile-action-btn {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.15);
  color: #e2e8f0;
  padding: 5px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.profile-action-btn:hover {
  background: rgba(255, 255, 255, 0.1);
  border-color: #38bdf8;
  color: #ffffff;
}

.profile-action-btn-danger:hover {
  background: rgba(239, 68, 68, 0.2);
  border-color: #ef4444;
  color: #fca5a5;
}

.profile-avatar-note {
  font-size: 11px;
  color: #f59e0b;
  font-weight: 600;
}

.profile-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

@media (max-width: 520px) {
  .profile-form-grid {
    grid-template-columns: 1fr;
  }
  .profile-avatar-row {
    flex-direction: column;
    align-items: flex-start;
  }
}

.profile-field-group {
  display: flex;
  flex-direction: column;
}

.profile-field-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #94a3b8;
  margin-bottom: 6px;
}

.required-star {
  color: #f87171;
}

.profile-input {
  width: 100%;
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 6px;
  color: #f8fafc;
  padding: 8px 12px;
  font-size: 13px;
  outline: none;
  transition: all 0.15s ease;
  box-sizing: border-box;
}

.profile-input:focus {
  border-color: #38bdf8;
  box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
  background: #0c1427;
}

.profile-input-with-prefix {
  display: flex;
  align-items: center;
  position: relative;
}

.profile-prefix {
  position: absolute;
  left: 10px;
  color: #38bdf8;
  font-weight: 700;
  font-size: 13px;
  pointer-events: none;
}

.profile-input-prefixed {
  padding-left: 26px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.profile-field-help {
  font-size: 11px;
  color: #64748b;
  margin-top: 4px;
}

.profile-badges-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.profile-badge-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
}

.profile-badge-label {
  color: #94a3b8;
  font-weight: 600;
}

.profile-pill-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 9999px;
  line-height: 1.3;
}

.role-badge {
  background: rgba(56, 189, 248, 0.15);
  border: 1px solid rgba(56, 189, 248, 0.35);
  color: #38bdf8;
}

.status-active-badge {
  background: rgba(16, 185, 129, 0.15);
  border: 1px solid rgba(16, 185, 129, 0.35);
  color: #34d399;
}

.password-section-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
  flex-wrap: wrap;
}

.profile-generate-pwd-btn {
  background: rgba(56, 189, 248, 0.15);
  border: 1px solid rgba(56, 189, 248, 0.3);
  color: #38bdf8;
  padding: 3px 10px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.profile-generate-pwd-btn:hover {
  background: rgba(56, 189, 248, 0.25);
  border-color: #38bdf8;
}

.password-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.password-input {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  padding-right: 36px;
}

.password-toggle-btn {
  position: absolute;
  right: 6px;
  background: none;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  font-size: 14px;
  padding: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
}

.password-toggle-btn:hover {
  color: #38bdf8;
}

.password-match-status {
  margin-top: 8px;
  font-size: 11px;
  font-weight: 600;
  padding: 4px 8px;
  border-radius: 4px;
}
.password-match-status.match {
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
}
.password-match-status.mismatch {
  background: rgba(239, 68, 68, 0.15);
  color: #f87171;
}

.profile-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 14px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.profile-btn {
  padding: 9px 20px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.15s ease;
  border: 1px solid transparent;
}

.profile-btn-secondary {
  background: rgba(255, 255, 255, 0.06);
  border-color: rgba(255, 255, 255, 0.12);
  color: #e2e8f0;
}

.profile-btn-secondary:hover {
  background: rgba(255, 255, 255, 0.12);
  color: #ffffff;
}

.profile-btn-primary {
  background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
  border-color: rgba(56, 189, 248, 0.4);
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(2, 132, 199, 0.35);
}

.profile-btn-primary:hover:not(:disabled) {
  background: linear-gradient(135deg, #0369a1 0%, #0284c7 100%);
  border-color: #38bdf8;
  box-shadow: 0 4px 16px rgba(56, 189, 248, 0.45);
  transform: translateY(-1px);
}

.profile-btn-primary:disabled {
  opacity: 0.65;
  cursor: not-allowed;
  transform: none;
}
</style>

<!-- ============================================================================
     User Profile Interactive JavaScript Controller
     ============================================================================ -->
<script>
(function() {
  let stagedFile = null;
  let stagedObjectUrl = null;
  let currentUserData = null;

  /**
   * Opens the user profile modal and loads fresh data from the server.
   */
  async function openUserProfileModal() {
    // If sidebar is open, close it cleanly first
    if (typeof window.closeSidebar === 'function') {
      window.closeSidebar();
    }

    const modal = document.getElementById('userProfileModal');
    if (!modal) return;

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // Clear alert and reset staged file state
    hideProfileAlert();
    resetStagedAvatarFile();
    document.getElementById('profileDeleteAvatar').value = '0';
    document.getElementById('profilePassword').value = '';
    document.getElementById('profilePasswordConfirmation').value = '';
    document.getElementById('passwordMatchStatus').style.display = 'none';

    // Fetch fresh profile data from /api/profile
    try {
      const res = await fetch('/api/profile', {
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      });

      if (!res.ok) {
        if (res.status === 401) {
          window.location.href = '/login';
          return;
        }
        throw new Error('Failed to load profile details (HTTP ' + res.status + ')');
      }

      const json = await res.json();
      const user = json.data?.user || json.user;

      if (!user) {
        throw new Error('Invalid user profile payload received.');
      }

      currentUserData = user;
      populateProfileFields(user);
    } catch (err) {
      console.error('Profile fetch error:', err);
      showProfileAlert(err.message || 'Failed to load user profile.', 'error');
    }
  }

  /**
   * Populates form fields with received user data.
   */
  function populateProfileFields(user) {
    document.getElementById('profileFirstName').value = user.first_name || '';
    document.getElementById('profileLastName').value = user.last_name || '';
    document.getElementById('profileUsername').value = user.username || '';
    document.getElementById('profileEmail').value = user.email || '';

    // Badges
    const roleBadge = document.getElementById('profileRoleBadge');
    if (roleBadge) {
      const isSuper = user.is_super ? ' (Super Admin)' : '';
      roleBadge.textContent = '🛡 ' + (user.role_name || 'User') + isSuper;
    }

    // Avatar preview
    renderAvatarPreview(user.avatar_url, user.initials, user.first_name + ' ' + user.last_name);

    // Remove Avatar button visibility
    const removeBtn = document.getElementById('profileRemoveAvatarBtn');
    if (removeBtn) {
      removeBtn.style.display = user.avatar_url ? 'inline-block' : 'none';
      removeBtn.textContent = '🗑 Remove Avatar';
    }
  }

  /**
   * Renders the avatar circle preview with image or fallback initials.
   */
  function renderAvatarPreview(avatarUrl, initials, name) {
    const circle = document.getElementById('profileAvatarPreviewCircle');
    if (!circle) return;

    if (avatarUrl) {
      circle.innerHTML = `<img id="profileModalAvatarImg" src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(name || 'Avatar')}" class="profile-avatar-preview-img">`;
    } else {
      const initText = initials || computeInitials(
        document.getElementById('profileFirstName').value,
        document.getElementById('profileLastName').value
      );
      circle.innerHTML = `<div id="profileModalAvatarInitials" class="profile-avatar-preview-initials">${escapeHtml(initText)}</div>`;
    }
  }

  /**
   * Closes the user profile modal.
   */
  function closeUserProfileModal() {
    const modal = document.getElementById('userProfileModal');
    if (!modal) return;

    modal.style.display = 'none';
    document.body.style.overflow = '';

    resetStagedAvatarFile();
    hideProfileAlert();
  }

  /**
   * Closes modal when clicking on the blurred background overlay.
   */
  function handleProfileBackdropClick(event) {
    if (event.target && event.target.id === 'userProfileModal') {
      closeUserProfileModal();
    }
  }

  /**
   * Triggers the hidden file input element.
   */
  function triggerAvatarFileInput() {
    const input = document.getElementById('profileAvatarInput');
    if (input) {
      input.click();
    }
  }

  /**
   * Handles file selection from the file dialog.
   */
  function handleAvatarFileSelect(files) {
    if (!files || files.length === 0) return;
    stageAvatarFile(files[0]);
  }

  /**
   * Drag & drop event handlers.
   */
  function handleAvatarDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    const dropzone = document.getElementById('profileAvatarDropzone');
    if (dropzone) dropzone.classList.add('drag-active');
  }

  function handleAvatarDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    const dropzone = document.getElementById('profileAvatarDropzone');
    if (dropzone) dropzone.classList.remove('drag-active');
  }

  function handleAvatarDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    const dropzone = document.getElementById('profileAvatarDropzone');
    if (dropzone) dropzone.classList.remove('drag-active');

    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      stageAvatarFile(e.dataTransfer.files[0]);
    }
  }

  /**
   * Validates and stages an avatar image file for upload with client-side preview.
   */
  function stageAvatarFile(file) {
    const validMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!validMimes.includes(file.type)) {
      showProfileAlert('Invalid file format. Please upload a JPG, PNG, WEBP, or GIF image.', 'error');
      return;
    }

    if (file.size > 8 * 1024 * 1024) {
      showProfileAlert('Image file is too large. Maximum allowed size is 8MB.', 'error');
      return;
    }

    stagedFile = file;
    if (stagedObjectUrl) {
      URL.revokeObjectURL(stagedObjectUrl);
    }
    stagedObjectUrl = URL.createObjectURL(file);

    // Preview staged image
    renderAvatarPreview(stagedObjectUrl, null, file.name);

    // Cancel staged button visibility
    const resetBtn = document.getElementById('profileResetFileBtn');
    if (resetBtn) resetBtn.style.display = 'inline-block';

    // Show selected filename
    const fileLabel = document.getElementById('profileSelectedFileName');
    if (fileLabel) {
      fileLabel.textContent = `Selected: ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
      fileLabel.style.display = 'block';
    }

    // Reset delete avatar flag since a new image was chosen
    document.getElementById('profileDeleteAvatar').value = '0';
    const note = document.getElementById('profileAvatarNote');
    if (note) note.textContent = '✨ New image ready to save.';

    hideProfileAlert();
  }

  /**
   * Resets any staged file selection back to active user state.
   */
  function resetStagedAvatarFile() {
    stagedFile = null;
    if (stagedObjectUrl) {
      URL.revokeObjectURL(stagedObjectUrl);
      stagedObjectUrl = null;
    }

    const input = document.getElementById('profileAvatarInput');
    if (input) input.value = '';

    const resetBtn = document.getElementById('profileResetFileBtn');
    if (resetBtn) resetBtn.style.display = 'none';

    const fileLabel = document.getElementById('profileSelectedFileName');
    if (fileLabel) {
      fileLabel.textContent = '';
      fileLabel.style.display = 'none';
    }

    const note = document.getElementById('profileAvatarNote');
    if (note) note.textContent = '';

    if (currentUserData) {
      const isPendingDelete = document.getElementById('profileDeleteAvatar').value === '1';
      if (isPendingDelete) {
        renderAvatarPreview(null, currentUserData.initials, currentUserData.first_name);
      } else {
        renderAvatarPreview(currentUserData.avatar_url, currentUserData.initials, currentUserData.first_name);
      }
    }
  }

  /**
   * Toggles avatar removal flag.
   */
  function toggleRemoveAvatar() {
    const deleteInput = document.getElementById('profileDeleteAvatar');
    const removeBtn = document.getElementById('profileRemoveAvatarBtn');
    const note = document.getElementById('profileAvatarNote');

    if (deleteInput.value === '1') {
      // Undo remove
      deleteInput.value = '0';
      if (removeBtn) removeBtn.textContent = '🗑 Remove Avatar';
      if (note) note.textContent = '';
      if (currentUserData) {
        renderAvatarPreview(currentUserData.avatar_url, currentUserData.initials, currentUserData.first_name);
      }
    } else {
      // Flag for removal
      resetStagedAvatarFile();
      deleteInput.value = '1';
      if (removeBtn) removeBtn.textContent = '↩ Undo Remove';
      if (note) note.textContent = '⚠️ Avatar will be removed upon saving.';
      const initials = computeInitials(
        document.getElementById('profileFirstName').value,
        document.getElementById('profileLastName').value
      );
      renderAvatarPreview(null, initials, 'Initials');
    }
  }

  /**
   * Validates matching password and password confirmation inputs.
   */
  function validatePasswordMatch() {
    const pwd = document.getElementById('profilePassword').value;
    const confirm = document.getElementById('profilePasswordConfirmation').value;
    const status = document.getElementById('passwordMatchStatus');
    if (!status) return;

    if (!pwd && !confirm) {
      status.style.display = 'none';
      return;
    }

    status.style.display = 'block';
    if (pwd.length < 8) {
      status.className = 'password-match-status mismatch';
      status.textContent = '⚠️ Password must be at least 8 characters long.';
    } else if (pwd !== confirm) {
      status.className = 'password-match-status mismatch';
      status.textContent = '⚠️ Password confirmation does not match.';
    } else {
      status.className = 'password-match-status match';
      status.textContent = '✓ Passwords match successfully.';
    }
  }

  /**
   * Generates a cryptographically strong 16-character random password.
   */
  function generateProfilePassword() {
    const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
    const array = new Uint32Array(16);
    window.crypto.getRandomValues(array);

    let password = '';
    for (let i = 0; i < array.length; i++) {
      password += chars[array[i] % chars.length];
    }

    const pwdInput = document.getElementById('profilePassword');
    const confirmInput = document.getElementById('profilePasswordConfirmation');
    
    pwdInput.value = password;
    confirmInput.value = password;
    pwdInput.type = 'text';
    confirmInput.type = 'text';

    validatePasswordMatch();
    showProfileAlert('A secure random password has been generated. Please record it before saving.', 'success');
  }

  /**
   * Toggles password field visibility between text and password.
   */
  function togglePasswordVisibility(fieldId, btn) {
    const input = document.getElementById(fieldId);
    if (!input) return;
    if (input.type === 'password') {
      input.type = 'text';
      btn.textContent = '🙈';
    } else {
      input.type = 'password';
      btn.textContent = '👁️';
    }
  }

  /**
   * Submits the updated profile form via AJAX.
   */
  async function handleProfileFormSubmit(event) {
    event.preventDefault();
    hideProfileAlert();

    const firstName = document.getElementById('profileFirstName').value.trim();
    const lastName  = document.getElementById('profileLastName').value.trim();
    const username  = document.getElementById('profileUsername').value.trim();
    const email     = document.getElementById('profileEmail').value.trim();
    const password  = document.getElementById('profilePassword').value;
    const confirm   = document.getElementById('profilePasswordConfirmation').value;

    if (!firstName || !lastName) {
      showProfileAlert('First and last name are required.', 'error');
      return;
    }

    if (!username) {
      showProfileAlert('Username is required.', 'error');
      return;
    }

    if (!/^[a-zA-Z0-9_.-]{3,50}$/.test(username)) {
      showProfileAlert('Username must be 3-50 characters (letters, numbers, underscores, dashes, dots).', 'error');
      return;
    }

    if (!email || !email.includes('@')) {
      showProfileAlert('A valid email address is required.', 'error');
      return;
    }

    if (password || confirm) {
      if (password !== confirm) {
        showProfileAlert('Password confirmation does not match.', 'error');
        return;
      }
      if (password.length < 8) {
        showProfileAlert('New password must be at least 8 characters.', 'error');
        return;
      }
    }

    const saveBtn = document.getElementById('profileSaveBtn');
    const saveBtnText = document.getElementById('profileSaveBtnText');
    const originalText = saveBtnText ? saveBtnText.textContent : 'Save Changes';

    if (saveBtn) saveBtn.disabled = true;
    if (saveBtnText) saveBtnText.textContent = 'Saving...';

    try {
      const form = document.getElementById('userProfileForm');
      const formData = new FormData(form);

      // If an avatar file was staged via drag & drop, attach it explicitly
      if (stagedFile) {
        formData.set('avatar', stagedFile, stagedFile.name);
      }

      const res = await fetch('/api/profile', {
        method: 'POST',
        headers: {
          'Accept': 'application/json'
        },
        body: formData
      });

      const json = await res.json();

      if (!res.ok || json.success === false) {
        const errorMsg = json.error || json.data?.error || 'Failed to update profile.';
        throw new Error(errorMsg);
      }

      const updatedUser = json.data?.user || json.user;
      if (!updatedUser) {
        throw new Error('Updated profile data missing from server response.');
      }

      // Update current state
      currentUserData = updatedUser;

      // Update Header Avatar Circle & Tooltip
      updateHeaderUserAvatar(updatedUser);

      // Update Sidebar user elements if in DOM
      updateSidebarUserElements(updatedUser);

      // Display global toast notification
      if (typeof window.showToast === 'function') {
        window.showToast(json.data?.message || json.message || 'Profile updated successfully!', 'success');
      }

      closeUserProfileModal();
    } catch (err) {
      console.error('Profile update failed:', err);
      showProfileAlert(err.message || 'An unexpected error occurred while saving profile.', 'error');
    } finally {
      if (saveBtn) saveBtn.disabled = false;
      if (saveBtnText) saveBtnText.textContent = originalText;
    }
  }

  /**
   * Updates the top-right header avatar button circle with image or initials.
   */
  function updateHeaderUserAvatar(user) {
    const slot = document.getElementById('headerUserAvatarSlot');
    const btn  = document.getElementById('headerUserAvatarBtn');
    const fullName = `${user.first_name || ''} ${user.last_name || ''}`.trim() || 'User';
    const tooltip = `${fullName} (${user.role_name || 'User'})`;

    if (btn) {
      btn.title = tooltip;
      btn.setAttribute('aria-label', tooltip);
    }

    if (!slot) return;

    if (user.avatar_url) {
      slot.innerHTML = `<img src="${escapeHtml(user.avatar_url)}" alt="${escapeHtml(fullName)}" class="header-avatar-img" id="headerUserAvatarImg">`;
    } else {
      const initials = user.initials || computeInitials(user.first_name, user.last_name);
      slot.innerHTML = `<div class="header-avatar-initials" id="headerUserAvatarInitials">${escapeHtml(initials)}</div>`;
    }
  }

  /**
   * Updates the sidebar user card if present in DOM.
   */
  function updateSidebarUserElements(user) {
    const nameEl = document.querySelector('.sidebar-user-name');
    if (nameEl) {
      nameEl.textContent = `${user.first_name || ''} ${user.last_name || ''}`.trim();
    }

    const avatarSlot = document.querySelector('.sidebar-user-avatar');
    if (avatarSlot) {
      if (user.avatar_url) {
        avatarSlot.innerHTML = `<img src="${escapeHtml(user.avatar_url)}" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">`;
      } else {
        const initials = user.initials || computeInitials(user.first_name, user.last_name);
        avatarSlot.innerHTML = `<span class="sidebar-avatar-initials">${escapeHtml(initials)}</span>`;
      }
    }
  }

  /**
   * Computes two-letter initials from names.
   */
  function computeInitials(first, last) {
    const f = (first || '').trim();
    const l = (last || '').trim();
    let res = '';
    if (f) res += f.charAt(0);
    if (l) res += l.charAt(0);
    return (res || 'U').toUpperCase();
  }

  /**
   * Escapes HTML for safe dynamic interpolation.
   */
  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  /**
   * Shows error or success message banner in the modal.
   */
  function showProfileAlert(msg, type = 'error') {
    const alertBox = document.getElementById('profileModalAlert');
    if (!alertBox) return;
    alertBox.className = `profile-alert ${type}`;
    alertBox.textContent = (type === 'error' ? '⚠️ ' : '✓ ') + msg;
    alertBox.style.display = 'flex';
  }

  function hideProfileAlert() {
    const alertBox = document.getElementById('profileModalAlert');
    if (alertBox) alertBox.style.display = 'none';
  }

  // Keyboard accessibility: ESC closes modal
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      const modal = document.getElementById('userProfileModal');
      if (modal && modal.style.display === 'flex') {
        closeUserProfileModal();
      }
    }
  });

  // Expose global controller functions to window
  window.openUserProfileModal = openUserProfileModal;
  window.closeUserProfileModal = closeUserProfileModal;
  window.handleProfileBackdropClick = handleProfileBackdropClick;
  window.triggerAvatarFileInput = triggerAvatarFileInput;
  window.handleAvatarFileSelect = handleAvatarFileSelect;
  window.handleAvatarDragOver = handleAvatarDragOver;
  window.handleAvatarDragLeave = handleAvatarDragLeave;
  window.handleAvatarDrop = handleAvatarDrop;
  window.resetStagedAvatarFile = resetStagedAvatarFile;
  window.toggleRemoveAvatar = toggleRemoveAvatar;
  window.validatePasswordMatch = validatePasswordMatch;
  window.generateProfilePassword = generateProfilePassword;
  window.togglePasswordVisibility = togglePasswordVisibility;
  window.handleProfileFormSubmit = handleProfileFormSubmit;
})();
</script>
