<?php
/**
 * src/Views/consoles.php
 * Hardware & Consoles Master-Detail Maintenance Workbench.
 *
 * Implements a split two-column workbench:
 * - Left Pane: Persistent Form Editor (Always Visible) with visual assets dropzones,
 *   technical emulation details, and hardware flags.
 * - Right Pane: Searchable & Form Factor filtered <data-grid> with linked title counts.
 *
 * Variables expected from ConsoleController:
 * @var array<int, array<string, mixed>> $consoles
 * @var array<int, array<string, mixed>> $makers
 * @var array<int, array<string, mixed>> $consoleTypes
 * @var bool $canWrite
 * @var string|null $flashMessage
 * @var string|null $flashError
 */

declare(strict_types=1);

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}
?>

<style>
/* Custom Workbench Layout for Consoles (Wider Editor Pane for Visual Assets) */
.workspace-consoles {
  display: grid;
  grid-template-columns: 460px 1fr;
  gap: 16px;
  align-items: start;
  padding: 16px;
  min-width: 0;
  max-width: 100%;
  width: 100%;
  box-sizing: border-box;
}
@media (max-width: 960px) {
  .workspace-consoles {
    grid-template-columns: minmax(0, 1fr);
    padding: 12px 8px;
  }
}

.vault-grid-table tbody tr {
  cursor: pointer;
}
.vault-grid-table tbody tr.vault-grid-row-selected td,
.vault-grid-table tbody tr.active td {
  background: var(--row-active, rgba(56, 189, 248, 0.16)) !important;
}
.grid-card {
  border: none;
  background: transparent;
  padding: 0;
  min-width: 0;
  max-width: 100%;
  width: 100%;
  box-sizing: border-box;
}

/* 2-Column Form Fields */
.form-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

/* Hardware Type & Flag Toggles */
.chip-group {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.chip-toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 10px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: rgba(15, 23, 42, 0.5);
  font-size: 12px;
  font-weight: 500;
  color: var(--text-main);
  cursor: pointer;
  user-select: none;
  transition: all var(--transition-fast);
}
.chip-toggle:hover {
  border-color: var(--border-focus);
  background: rgba(56, 189, 248, 0.05);
}
.chip-toggle input[type="checkbox"] {
  cursor: pointer;
  width: 15px;
  height: 15px;
  accent-color: #0284c7;
}

/* Visual Asset Dropzone Cards */
.media-card {
  background: rgba(15, 23, 42, 0.5);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.media-card-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--text-dim);
  letter-spacing: 0.04em;
}
.dropzone-box {
  min-height: 120px;
  height: 120px;
  border: 2px dashed rgba(148, 163, 184, 0.2);
  border-radius: var(--radius-sm);
  background: rgba(7, 12, 22, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  overflow: hidden;
  position: relative;
  transition: border-color var(--transition-fast), background var(--transition-fast);
}
.dropzone-box:hover {
  border-color: var(--border-focus);
  background: rgba(56, 189, 248, 0.04);
}
.dropzone-box.drag-over {
  border-color: #38bdf8;
  background: rgba(56, 189, 248, 0.1);
}
.dropzone-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  color: var(--text-dim);
  font-size: 12px;
}
.dropzone-empty span {
  font-size: 26px;
  opacity: 0.8;
}
.dropzone-preview-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}
.media-actions {
  display: flex;
  gap: 6px;
}
.media-actions .btn {
  flex: 1;
  padding: 4px 8px;
  font-size: 11px;
}

/* Collapsible Emulation Details */
.emulation-details {
  margin-top: 4px;
  background: rgba(0, 0, 0, 0.18);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 8px 10px;
}
.emulation-summary {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-dim);
  cursor: pointer;
  user-select: none;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.emulation-summary:hover {
  color: var(--text-main);
}
.emulation-content {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 10px;
}

/* Collapsible BIOS / Additional Files Section */
.bios-files-details {
  margin-top: 8px;
  background: rgba(0, 0, 0, 0.18);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 8px 10px;
}
.bios-bullet-list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.bios-bullet-list li {
  color: var(--text-main);
  font-size: 12px;
  line-height: 1.4;
  display: flex;
  align-items: center;
  gap: 8px;
}
.bios-file-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  border-radius: 4px;
}
.bios-icon-internal {
  color: #38bdf8;
  background: rgba(56, 189, 248, 0.12);
  border: 1px solid rgba(56, 189, 248, 0.25);
}
.bios-icon-external {
  color: #fbbf24;
  background: rgba(245, 158, 11, 0.12);
  border: 1px solid rgba(245, 158, 11, 0.25);
}
.bios-download-link {
  color: #38bdf8;
  text-decoration: none;
  font-weight: 500;
  cursor: pointer;
  transition: color var(--transition-fast);
}
.bios-download-link:hover {
  color: #7dd3fc;
  text-decoration: underline;
}
.bios-text-only {
  color: var(--text-main);
  font-weight: 500;
}
.bios-edit-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 11px;
}
.bios-edit-table th {
  padding: 5px 6px;
  font-weight: 600;
  color: var(--text-dim);
  border-bottom: 1px solid var(--border);
  background: rgba(30, 41, 59, 0.7);
  text-align: left;
}
.bios-edit-table td {
  padding: 4px;
  vertical-align: middle;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}
.bios-edit-table input,
.bios-edit-table select {
  width: 100%;
  padding: 4px 6px;
  font-size: 11px;
  background: rgba(15, 23, 42, 0.8);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  color: var(--text-main);
  box-sizing: border-box;
}
.bios-edit-table input:focus,
.bios-edit-table select:focus {
  border-color: var(--border-focus);
  outline: none;
}

/* Console Tag Flags in Table */
.tag-flag {
  display: inline-flex;
  align-items: center;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  margin-left: 6px;
}
.tag-handheld {
  background: rgba(56, 189, 248, 0.16);
  color: #38bdf8;
  border: 1px solid rgba(56, 189, 248, 0.3);
}
.tag-computer {
  background: rgba(168, 85, 247, 0.16);
  color: #c084fc;
  border: 1px solid rgba(168, 85, 247, 0.3);
}
.tag-arcade {
  background: rgba(245, 158, 11, 0.16);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.3);
}
.tag-reference {
  background: rgba(239, 68, 68, 0.16);
  color: #f87171;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

.section-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--text-dim);
  letter-spacing: 0.05em;
  margin-top: 2px;
}

/* Custom Header Styling for Consoles Workbench */
.console-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--border);
  padding-bottom: 8px;
}
.console-header-titles {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.console-card-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-main, #f8fafc);
  line-height: 1.2;
}
.record-timestamps {
  font-size: 10px;
  font-weight: 500;
  color: var(--text-muted, #94a3b8);
  line-height: 1.2;
  letter-spacing: -0.01em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* AI Auto-Fill Sparkle Button */
.btn-ai-autofill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 9px;
  font-size: 11px;
  font-weight: 600;
  color: #c084fc;
  background: linear-gradient(135deg, rgba(168, 85, 247, 0.15) 0%, rgba(56, 189, 248, 0.15) 100%);
  border: 1px solid rgba(168, 85, 247, 0.4);
  border-radius: var(--radius-sm, 6px);
  cursor: pointer;
  transition: all var(--transition-fast);
  user-select: none;
}
.btn-ai-autofill:hover:not(:disabled) {
  background: linear-gradient(135deg, rgba(168, 85, 247, 0.28) 0%, rgba(56, 189, 248, 0.28) 100%);
  border-color: #c084fc;
  color: #f3e8ff;
  box-shadow: 0 0 10px rgba(168, 85, 247, 0.3);
  transform: translateY(-1px);
}
.btn-ai-autofill:active:not(:disabled) {
  transform: translateY(0);
}
.btn-ai-autofill:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}
.ai-sparkle-icon {
  font-size: 12px;
  line-height: 1;
  filter: drop-shadow(0 0 2px rgba(168, 85, 247, 0.6));
}
</style>

<div class="workspace-consoles">
  <!-- LEFT PANE: Master-Detail Persistent Form Editor -->
  <aside class="editor-card">
    <div class="card-header console-card-header">
      <div class="console-header-titles">
        <span id="formModeTitle" class="card-title console-card-title">NEW CONSOLE</span>
        <div id="recordTimestamps" class="record-timestamps" style="display: none;"></div>
      </div>
      <span id="activeIdBadge" class="badge-record">(Auto ID)</span>
    </div>

    <form id="consoleForm" onsubmit="handleSave(event)" novalidate>
      <input type="hidden" id="consoleId" name="id" value="">
      <input type="hidden" id="deleteImage" name="delete_image" value="0">
      <input type="hidden" id="deleteLogo" name="delete_logo" value="0">

      <!-- Console Title -->
      <div class="form-group">
        <label for="consoleName">Console Name *</label>
        <input 
          type="text" 
          id="consoleName" 
          name="name" 
          class="form-control" 
          placeholder="e.g. Nintendo Game Boy, Sega Genesis..." 
          required 
          autocomplete="off" 
          autofocus
          <?= !$canWrite ? 'disabled' : '' ?>
        >
      </div>

      <!-- Maker & Release Year -->
      <div class="form-grid-2">
        <div class="form-group">
          <label for="publisherId">Manufacturer / Maker</label>
          <select id="publisherId" name="publisher_id" class="form-control" <?= !$canWrite ? 'disabled' : '' ?>>
            <option value="">-- Select Maker --</option>
            <?php foreach ($makers as $m): ?>
              <option value="<?= (int)$m['id'] ?>">
                <?= htmlspecialchars((string)$m['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="releaseYear">Release Year</label>
          <input 
            type="text" 
            id="releaseYear" 
            name="year" 
            class="form-control" 
            placeholder="e.g. 1989"
            <?= !$canWrite ? 'disabled' : '' ?>
          >
        </div>
      </div>

      <!-- Hardware Generation & Console Type -->
      <div class="form-grid-2">
        <div class="form-group">
          <label for="consoleTypeId">Console Hardware Type *</label>
          <select id="consoleTypeId" name="console_type_id" class="form-control" required <?= !$canWrite ? 'disabled' : '' ?>>
            <option value="">-- Select Type --</option>
            <?php foreach ($consoleTypes as $ct): ?>
              <option value="<?= (int)$ct['id'] ?>">
                <?= htmlspecialchars((string)$ct['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="generation">Hardware Generation</label>
          <input 
            type="text" 
            id="generation" 
            name="generation" 
            class="form-control" 
            placeholder="e.g. 4th Gen, 16-bit"
            <?= !$canWrite ? 'disabled' : '' ?>
          >
        </div>
      </div>

      <!-- Hardware Flags & Conditional Master Platform Reference -->
      <div class="form-group" style="margin-top: 16px; margin-bottom: 12px;">
        <div style="display: flex; flex-direction: column; gap: 8px;">
          <div>
            <label class="chip-toggle" for="isForReference" style="display: inline-flex; width: auto; padding: 7px 12px; margin: 0; cursor: pointer;">
              <input 
                type="checkbox" 
                id="isForReference" 
                name="is_for_reference" 
                value="1" 
                onchange="handleReferenceChange(this.checked)"
                <?= !$canWrite ? 'disabled' : '' ?>
              >
              📌 Reference Only
            </label>
          </div>

          <!-- Master Platform Dropdown (Visible only when Reference Only is checked) -->
          <div id="masterPlatformGroup" style="display: none; background: rgba(15, 23, 42, 0.4); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px 12px;">
            <label for="masterReferenceId" style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-dim); letter-spacing: 0.05em; display: block; margin-bottom: 6px;">
              Master Platform * <span style="font-weight: 400; text-transform: none; color: var(--text-muted);">(Primary console this reference derives from)</span>
            </label>
            <select id="masterReferenceId" name="master_reference_id" class="form-control" <?= !$canWrite ? 'disabled' : '' ?>>
              <option value="">-- Select Master Platform --</option>
              <?php if (!empty($masterConsoles)): ?>
                <?php foreach ($masterConsoles as $mc): ?>
                  <option value="<?= (int)$mc['id'] ?>">
                    <?= htmlspecialchars((string)$mc['name'], ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Visual Assets: Photo & Logo -->
      <div class="section-label">Visual Assets (Photo & Logo)</div>
      <div class="form-grid-2">
        <!-- Hardware Photo Dropzone -->
        <div class="media-card">
          <div class="media-card-title">Hardware Photo</div>
          <input 
            type="file" 
            id="imageFileInput" 
            name="image_file" 
            accept="image/*" 
            style="display: none;" 
            onchange="handleFileSelect(this, 'photoPreviewContainer', 'deletePhotoBtn')"
            <?= !$canWrite ? 'disabled' : '' ?>
          >
          <div 
            class="dropzone-box" 
            id="photoDropzone" 
            onclick="triggerBrowse('imageFileInput')"
          >
            <div id="photoPreviewContainer" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
              <div class="dropzone-empty">
                <span>📷</span>
                <strong>Upload Photo</strong>
                <small>Drop or click</small>
              </div>
            </div>
          </div>
          <div class="media-actions">
            <button 
              type="button" 
              class="btn" 
              onclick="triggerBrowse('imageFileInput')"
              <?= !$canWrite ? 'disabled' : '' ?>
            >Browse</button>
            <button 
              type="button" 
              id="deletePhotoBtn" 
              class="btn danger" 
              onclick="removeAsset('image')" 
              disabled
              <?= !$canWrite ? 'disabled' : '' ?>
            >Remove</button>
          </div>
        </div>

        <!-- Brand Logo Dropzone -->
        <div class="media-card">
          <div class="media-card-title">Brand Logo</div>
          <input 
            type="file" 
            id="logoFileInput" 
            name="logo_file" 
            accept="image/*" 
            style="display: none;" 
            onchange="handleFileSelect(this, 'logoPreviewContainer', 'deleteLogoBtn')"
            <?= !$canWrite ? 'disabled' : '' ?>
          >
          <div 
            class="dropzone-box" 
            id="logoDropzone" 
            onclick="triggerBrowse('logoFileInput')"
          >
            <div id="logoPreviewContainer" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
              <div class="dropzone-empty">
                <span>🖼️</span>
                <strong>Upload Logo</strong>
                <small>Drop or click</small>
              </div>
            </div>
          </div>
          <div class="media-actions">
            <button 
              type="button" 
              class="btn" 
              onclick="triggerBrowse('logoFileInput')"
              <?= !$canWrite ? 'disabled' : '' ?>
            >Browse</button>
            <button 
              type="button" 
              id="deleteLogoBtn" 
              class="btn danger" 
              onclick="removeAsset('logo')" 
              disabled
              <?= !$canWrite ? 'disabled' : '' ?>
            >Remove</button>
          </div>
        </div>
      </div>

      <!-- Personal Notes / Specs -->
      <div class="form-group" style="margin-top: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
          <label for="comments" style="margin: 0;">Personal Notes / Specs</label>
          <?php if ($canWrite): ?>
          <button 
            type="button" 
            id="aiAutoFillBtn" 
            class="btn-ai-autofill" 
            title="Auto-fill Specs & BIOS Notes with Gemini AI"
            onclick="autofillConsoleSpecs()"
          >
            <span class="ai-sparkle-icon">✨</span> AI Auto-Fill
          </button>
          <?php endif; ?>
        </div>
        <textarea 
          id="comments" 
          name="comments" 
          class="form-control" 
          rows="3" 
          placeholder="BIOS requirements, serial numbers, region notes..."
          <?= !$canWrite ? 'disabled' : '' ?>
        ></textarea>
      </div>

      <!-- Collapsible Emulation & Technical Links (Relocated Below Personal Notes) -->
      <details class="emulation-details">
        <summary class="emulation-summary">
          <span>▶ Emulation & Technical Links</span>
          <span style="font-size: 10px;">▾</span>
        </summary>
        <div class="emulation-content">
          <div class="form-grid-2">
            <div class="form-group">
              <label for="retroarchCore">RetroArch Core</label>
              <input type="text" id="retroarchCore" name="retroarch_core" class="form-control" placeholder="e.g. mgba" <?= !$canWrite ? 'disabled' : '' ?>>
            </div>
            <div class="form-group">
              <label for="coreLink">Core URL</label>
              <input type="text" inputmode="url" id="coreLink" name="core_link" class="form-control" placeholder="https://..." <?= !$canWrite ? 'disabled' : '' ?>>
            </div>
          </div>

          <div class="form-grid-2">
            <div class="form-group">
              <label for="emulator">Desktop Emulator</label>
              <input type="text" id="emulator" name="emulator" class="form-control" placeholder="e.g. mGBA, PCSX2" <?= !$canWrite ? 'disabled' : '' ?>>
            </div>
            <div class="form-group">
              <label for="emulatorLink">Desktop URL</label>
              <input type="text" inputmode="url" id="emulatorLink" name="emulator_link" class="form-control" placeholder="https://..." <?= !$canWrite ? 'disabled' : '' ?>>
            </div>
          </div>

          <div class="form-grid-2">
            <div class="form-group">
              <label for="emulatorAndroid">Android Emulator</label>
              <input type="text" id="emulatorAndroid" name="emulator_android" class="form-control" placeholder="e.g. Pizza Boy" <?= !$canWrite ? 'disabled' : '' ?>>
            </div>
            <div class="form-group">
              <label for="emulatorAndroidLink">Android URL</label>
              <input type="text" inputmode="url" id="emulatorAndroidLink" name="emulator_android_link" class="form-control" placeholder="https://..." <?= !$canWrite ? 'disabled' : '' ?>>
            </div>
          </div>
        </div>
      </details>

      <!-- Collapsible BIOS/Additional Files Section -->
      <details class="emulation-details bios-files-details" id="biosFilesSection">
        <summary class="emulation-summary">
          <span>▶ BIOS/Additional Files</span>
          <span style="font-size: 10px;">▾</span>
        </summary>
        <div class="bios-files-content" style="padding-top: 8px;">
          <!-- Hidden input storing serialized JSON of files to be saved with console -->
          <input type="hidden" id="downloadableFilesJson" name="downloadable_files_json" value="">

          <!-- VIEW MODE: Bulleted list of links or text -->
          <div id="biosFilesViewMode">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <span style="font-size: 11px; font-weight: 600; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em;">Available Files</span>
              <?php if ($canWrite): ?>
                <button type="button" id="editBiosFilesBtn" class="btn" style="padding: 2px 8px; font-size: 10px; height: 22px;" onclick="openBiosEditMode()">
                  ✏️ Edit
                </button>
              <?php endif; ?>
            </div>

            <!-- Bulleted List -->
            <ul id="biosFilesList" class="bios-bullet-list">
              <!-- Populated dynamically via JS -->
            </ul>

            <div id="biosFilesEmptyMsg" style="display: none; font-size: 11px; color: var(--text-muted); font-style: italic; padding: 4px 0;">
              No BIOS or additional files linked.
            </div>

            <!-- Message asking to save whole record to persist pending changes -->
            <div id="biosFilesPendingNotice" style="display: none; margin-top: 8px; font-size: 11px; background: rgba(245, 158, 11, 0.12); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); border-radius: var(--radius-sm); padding: 6px 8px;">
              ⚠️ Changes made to file list. Please click <strong>💾 Save Console</strong> below to persist your changes.
            </div>
          </div>

          <!-- EDIT MODE: Small Grid of Editable Files -->
          <div id="biosFilesEditMode" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <span style="font-size: 11px; font-weight: 700; color: var(--text-main); text-transform: uppercase;">Edit Files</span>
              <button type="button" class="btn primary" style="padding: 2px 8px; font-size: 10px; height: 22px;" onclick="addBiosGridRow()">
                + Add File
              </button>
            </div>

            <div style="max-height: 220px; overflow-y: auto; border: 1px solid var(--border); border-radius: var(--radius-sm); background: rgba(15, 23, 42, 0.6); margin-bottom: 8px;">
              <table class="bios-edit-table">
                <thead>
                  <tr>
                    <th style="width: 32%;">Display Name</th>
                    <th style="width: 25%;">Storage</th>
                    <th style="width: 35%;">Path or URL</th>
                    <th style="width: 8%; text-align: center;"></th>
                  </tr>
                </thead>
                <tbody id="biosGridBody">
                  <!-- Populated dynamically -->
                </tbody>
              </table>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 6px;">
              <button type="button" class="btn" style="padding: 4px 10px; font-size: 11px;" onclick="cancelBiosEditMode()">
                ✕ Cancel
              </button>
              <button type="button" class="btn primary" style="padding: 4px 12px; font-size: 11px;" onclick="acceptBiosEditMode()">
                ✓ Accept
              </button>
            </div>
          </div>
        </div>
      </details>

      <!-- Action Buttons -->
      <div class="editor-actions">
        <?php if ($canWrite): ?>
          <button type="submit" id="saveBtn" class="btn primary">
            💾 Save Console
          </button>
          <div class="action-row">
            <button type="button" class="btn" onclick="resetForm()">
              + New
            </button>
            <button type="button" id="deleteBtn" class="btn danger" onclick="handleDelete()" disabled>
              Delete
            </button>
          </div>
        <?php else: ?>
          <div class="banner" style="background: rgba(148, 163, 184, 0.1); color: var(--text-dim); justify-content: center; font-size: 11px; border-radius: var(--radius-sm);">
            🔒 Read-Only Device / Role
          </div>
        <?php endif; ?>
      </div>
    </form>
  </aside>

  <!-- RIGHT PANE: Reusable <data-grid> Component with Form Factors filter -->
  <section class="grid-card">
    <data-grid 
      id="consolesGrid" 
      page-size="25" 
      search-placeholder="Type to filter consoles by title, maker, year, or gen..."
    ></data-grid>
  </section>
</div>

<!-- Raw Initial Server Datasets -->
<script id="serverConsolesData" type="application/json">
  <?= json_encode($consoles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>

<script id="serverMakersData" type="application/json">
  <?= json_encode($makers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>

<script id="serverConsoleTypesData" type="application/json">
  <?= json_encode($consoleTypes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>

<script id="serverMasterConsolesData" type="application/json">
  <?= json_encode($masterConsoles ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>

<script>
/**
 * Master-Detail Consoles Controller Script
 */
let consolesList = [];
let makersList = [];
let consoleTypesList = [];
let masterConsolesList = [];
let selectedId = null;
const canWrite = <?= json_encode($canWrite) ?>;

// BIOS / Additional Files State
let currentConsoleFiles = [];
let workingConsoleFiles = [];
let pendingConsoleFiles = [];
let hasPendingFileChanges = false;

document.addEventListener('DOMContentLoaded', () => {
  // 1. Ingest initial server pre-rendered datasets
  try {
    const rawCons = document.getElementById('serverConsolesData').textContent;
    consolesList = JSON.parse(rawCons || '[]');
  } catch (err) {
    console.error('Failed to parse consoles dataset:', err);
    consolesList = [];
  }

  try {
    const rawMakers = document.getElementById('serverMakersData').textContent;
    makersList = JSON.parse(rawMakers || '[]');
  } catch (err) {
    console.error('Failed to parse makers dataset:', err);
    makersList = [];
  }

  try {
    const rawTypes = document.getElementById('serverConsoleTypesData').textContent;
    consoleTypesList = JSON.parse(rawTypes || '[]');
  } catch (err) {
    console.error('Failed to parse console types dataset:', err);
    consoleTypesList = [];
  }

  try {
    const rawMasters = document.getElementById('serverMasterConsolesData').textContent;
    masterConsolesList = JSON.parse(rawMasters || '[]');
  } catch (err) {
    masterConsolesList = [];
  }

  // Populate master platform candidates initially
  populateMasterDropdown(null);

  // 2. Initialize the reusable <data-grid>
  const grid = document.getElementById('consolesGrid');
  if (grid) {
    // Custom Console Type filter dropdown
    grid.customFilters = [
      {
        key: 'console_type_id',
        label: '',
        allLabel: 'All Console Types',
        options: consoleTypesList.map(ct => ({ value: String(ct.id), label: ct.name }))
      }
    ];

    grid.columns = [
      {
        key: 'id',
        label: 'ID',
        sortable: true,
        width: '65px',
        render: (val) => `<span style="font-weight: 600; color: var(--text-dim);">#${escapeHtml(val)}</span>`
      },
      {
        key: 'name',
        label: 'Console',
        sortable: true,
        searchable: true,
        render: (val, row) => {
          let flagsHtml = '';
          if (row) {
            const typeName = row.console_type_name || '';
            const bg = row.badge_bg_color || '#1e3a8a';
            const font = row.badge_font_color || '#93c5fd';
            if (typeName) {
              flagsHtml += `<span class="tag-flag" style="background-color: ${escapeHtml(bg)}; color: ${escapeHtml(font)}; border: 1px solid ${escapeHtml(font)}44;">${escapeHtml(typeName)}</span>`;
            }
            if (Number(row.is_for_reference) === 1) {
              const masterName = row.master_console_name || (consolesList.find(c => Number(c.id) === Number(row.master_reference_id || row.master_console_id))?.name) || '';
              const masterTip = masterName ? ` title="Master: ${escapeHtml(masterName)}"` : ' title="Reference-only platform"';
              flagsHtml += `<span class="tag-flag tag-reference"${masterTip}>REF</span>`;
            }
          }
          return `<div style="display: flex; align-items: center; flex-wrap: wrap; gap: 4px;">
                    <span style="font-weight: 600; color: var(--text-main); font-size: 13px;">${escapeHtml(val)}</span>
                    ${flagsHtml}
                  </div>`;
        }
      },
      {
        key: 'maker_name',
        label: 'Maker',
        sortable: true,
        searchable: true,
        render: (val) => `<span style="color: var(--text-muted); font-size: 12px;">${escapeHtml(val || '—')}</span>`
      },
      {
        key: 'year',
        label: 'Year',
        sortable: true,
        width: '80px',
        render: (val) => `<span style="color: var(--text-dim); font-size: 12px;">${escapeHtml(val || '—')}</span>`
      },
      {
        key: 'generation',
        label: 'Gen',
        sortable: true,
        width: '80px',
        render: (val) => `<span style="color: var(--text-dim); font-size: 12px;">${escapeHtml(val || '—')}</span>`
      },
      {
        key: 'games_count',
        label: 'Library',
        sortable: true,
        align: 'right',
        width: '140px',
        render: (val, row) => {
          const count = Number(val ?? (row && row.game_count) ?? 0);
          const isActive = count > 0;
          const text = `${count} ${count === 1 ? 'title' : 'titles'}`;
          return `<span class="usage-badge ${isActive ? 'active-use' : ''}">${text}</span>`;
        }
      }
    ];

    // Compute form_factor key on rows for custom filter support
    mapConsolesDataset(consolesList);
    grid.data = consolesList;

    // 3. Row selection listener: populates the persistent left-hand form
    grid.addEventListener('row-click', (e) => {
      const row = e.detail.row;
      if (row && row.id !== undefined) {
        selectConsole(Number(row.id));
      }
    });

    // Re-apply visual row highlight when page changes or sorts occur
    grid.addEventListener('page-change', () => syncRowHighlight());
  }

  // Setup drag & drop on photo and logo dropzones
  setupDropzone('photoDropzone', 'imageFileInput', 'photoPreviewContainer', 'deletePhotoBtn');
  setupDropzone('logoDropzone', 'logoFileInput', 'logoPreviewContainer', 'deleteLogoBtn');

  // 4. Global keyboard shortcut bindings: Ctrl+S to save, Esc to reset/new
  window.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      if (canWrite) {
        document.getElementById('consoleForm').requestSubmit();
      }
    }
    if (e.key === 'Escape') {
      resetForm();
    }
  });

  // Display initial server flash messages if present
  <?php if (!empty($flashMessage)): ?>
    showToast(<?= json_encode($flashMessage) ?>, 'success');
  <?php endif; ?>
  <?php if (!empty($flashError)): ?>
    showToast(<?= json_encode($flashError) ?>, 'error');
  <?php endif; ?>
});

/**
 * Prepares dataset rows with computed filter fields
 */
function mapConsolesDataset(list) {
  list.forEach(row => {
    row.console_type_id = String(row.console_type_id || '1');
  });
}

/**
 * Populates persistent left editor with selected console record
 */
function selectConsole(id) {
  const record = consolesList.find(c => Number(c.id) === Number(id));
  if (!record) return;

  selectedId = Number(record.id);
  document.getElementById('consoleId').value = String(record.id);
  document.getElementById('deleteImage').value = '0';
  document.getElementById('deleteLogo').value = '0';

  document.getElementById('consoleName').value = record.name || '';
  document.getElementById('publisherId').value = String(record.publisher_id || '');
  document.getElementById('releaseYear').value = record.year || '';
  document.getElementById('generation').value = record.generation || '';
  document.getElementById('consoleTypeId').value = String(record.console_type_id || '');
  
  const isRef = Number(record.is_for_reference) === 1;
  document.getElementById('isForReference').checked = isRef;

  const targetMasterId = (isRef && (record.master_reference_id || record.master_console_id))
    ? String(record.master_reference_id || record.master_console_id)
    : null;

  // Rebuild candidate master platform dropdown excluding this console and selecting its master platform
  populateMasterDropdown(selectedId, targetMasterId);
  const masterGroup = document.getElementById('masterPlatformGroup');
  const masterSelect = document.getElementById('masterReferenceId');
  if (isRef) {
    if (masterGroup) masterGroup.style.display = 'block';
    if (masterSelect) {
      masterSelect.required = true;
      if (targetMasterId) {
        masterSelect.value = targetMasterId;
      }
    }
  } else {
    if (masterGroup) masterGroup.style.display = 'none';
    if (masterSelect) {
      masterSelect.required = false;
      masterSelect.value = '';
    }
  }

  document.getElementById('retroarchCore').value = record.retroarch_core || '';
  document.getElementById('coreLink').value = (record.core_link || '').replace(/^#+|#+$/g, '');
  document.getElementById('emulator').value = record.emulator || '';
  document.getElementById('emulatorLink').value = (record.emulator_link || '').replace(/^#+|#+$/g, '');
  document.getElementById('emulatorAndroid').value = record.emulator_android || '';
  document.getElementById('emulatorAndroidLink').value = (record.emulator_android_link || '').replace(/^#+|#+$/g, '');
  document.getElementById('comments').value = record.comments || '';

  // Set Visual Asset Previews (3. Read: retrieves values from fields, doesn't enforce convention)
  setDropzoneImage('photoPreviewContainer', record.image_url, 'deletePhotoBtn', '📷', 'Upload Photo');
  setDropzoneImage('logoPreviewContainer', record.logo_url, 'deleteLogoBtn', '🖼️', 'Upload Logo');

  document.getElementById('formModeTitle').textContent = 'EDIT CONSOLE';
  document.getElementById('activeIdBadge').textContent = `#${record.id}`;

  const timestampsLabel = document.getElementById('recordTimestamps');
  if (timestampsLabel) {
    const createdVal = record.created || '';
    const updatedVal = record.updated || '';
    if (createdVal || updatedVal) {
      timestampsLabel.textContent = `Created: ${createdVal} • Last Modified: ${updatedVal}`;
      timestampsLabel.style.display = 'block';
    } else {
      timestampsLabel.textContent = '';
      timestampsLabel.style.display = 'none';
    }
  }

  const deleteBtn = document.getElementById('deleteBtn');
  if (deleteBtn) {
    deleteBtn.disabled = false;
    const gameCount = Number(record.games_count ?? record.game_count ?? 0);
    const refCount = Number(record.reference_consoles_count ?? 0);
    if (gameCount > 0 && refCount > 0) {
      deleteBtn.title = `Cannot delete: Has ${gameCount} game(s) and is master platform for ${refCount} reference console(s)`;
    } else if (gameCount > 0) {
      deleteBtn.title = `Cannot delete: Has ${gameCount} game(s) in its library`;
    } else if (refCount > 0) {
      deleteBtn.title = `Cannot delete: Master platform for ${refCount} reference console(s)`;
    } else {
      deleteBtn.title = `Delete ${record.name}`;
    }
  }

  // Initialize and render BIOS / Additional Files
  currentConsoleFiles = Array.isArray(record.downloadable_files) ? JSON.parse(JSON.stringify(record.downloadable_files)) : [];
  pendingConsoleFiles = [];
  workingConsoleFiles = [];
  hasPendingFileChanges = false;
  const jsonInput = document.getElementById('downloadableFilesJson');
  if (jsonInput) jsonInput.value = '';
  const editMode = document.getElementById('biosFilesEditMode');
  const viewMode = document.getElementById('biosFilesViewMode');
  if (editMode) editMode.style.display = 'none';
  if (viewMode) viewMode.style.display = 'block';
  renderBiosViewMode();

  syncRowHighlight();
  document.getElementById('consoleName').focus();
}

/**
 * Resets the persistent form back to "NEW CONSOLE" (Auto ID) state
 */
function resetForm() {
  selectedId = null;
  document.getElementById('consoleForm').reset();
  document.getElementById('consoleId').value = '';
  document.getElementById('consoleTypeId').value = '';
  document.getElementById('isForReference').checked = false;

  const masterGroup = document.getElementById('masterPlatformGroup');
  const masterSelect = document.getElementById('masterReferenceId');
  if (masterGroup) masterGroup.style.display = 'none';
  if (masterSelect) {
    masterSelect.required = false;
    masterSelect.value = '';
  }
  populateMasterDropdown(null, null);

  document.getElementById('deleteImage').value = '0';
  document.getElementById('deleteLogo').value = '0';

  resetDropzonePreview('photoPreviewContainer', 'deletePhotoBtn', '📷', 'Upload Photo');
  resetDropzonePreview('logoPreviewContainer', 'deleteLogoBtn', '🖼️', 'Upload Logo');

  document.getElementById('formModeTitle').textContent = 'NEW CONSOLE';
  document.getElementById('activeIdBadge').textContent = '(Auto ID)';

  const timestampsLabel = document.getElementById('recordTimestamps');
  if (timestampsLabel) {
    timestampsLabel.textContent = '';
    timestampsLabel.style.display = 'none';
  }

  const deleteBtn = document.getElementById('deleteBtn');
  if (deleteBtn) {
    deleteBtn.disabled = true;
    deleteBtn.removeAttribute('title');
  }

  // Reset attached downloadable files
  currentConsoleFiles = [];
  pendingConsoleFiles = [];
  workingConsoleFiles = [];
  hasPendingFileChanges = false;
  const jsonInput = document.getElementById('downloadableFilesJson');
  if (jsonInput) jsonInput.value = '';
  const editMode = document.getElementById('biosFilesEditMode');
  const viewMode = document.getElementById('biosFilesViewMode');
  if (editMode) editMode.style.display = 'none';
  if (viewMode) viewMode.style.display = 'block';
  renderBiosViewMode();

  syncRowHighlight();
  const input = document.getElementById('consoleName');
  if (input && !input.disabled) {
    input.focus();
  }
}

/**
 * BIOS / Additional Files Management Functions
 */

/**
 * Opens edit mode for BIOS / Additional Files
 */
function openBiosEditMode() {
  if (!canWrite) return;
  const source = hasPendingFileChanges ? pendingConsoleFiles : currentConsoleFiles;
  workingConsoleFiles = JSON.parse(JSON.stringify(source));
  renderBiosEditGrid();
  document.getElementById('biosFilesViewMode').style.display = 'none';
  document.getElementById('biosFilesEditMode').style.display = 'block';
  const details = document.getElementById('biosFilesSection');
  if (details) details.open = true;
}

/**
 * Renders the small grid in edit mode
 */
function renderBiosEditGrid() {
  const tbody = document.getElementById('biosGridBody');
  if (!tbody) return;
  tbody.innerHTML = '';

  if (workingConsoleFiles.length === 0) {
    tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 12px; font-style: italic;">No files in list. Click "+ Add File" above.</td></tr>';
    return;
  }

  workingConsoleFiles.forEach((file, index) => {
    const tr = document.createElement('tr');
    tr.dataset.index = String(index);

    const displayName = file.display_name || '';
    const storage = file.storage_provider === 'blackblaze' ? 'blackblaze' : 'external';
    const pathOrUrl = file.file_key_or_url || '';

    tr.innerHTML = `
      <td>
        <input type="text" class="bios-input-name" placeholder="Display name" value="${escapeHtml(displayName)}" onchange="updateWorkingFile(${index}, 'display_name', this.value)">
      </td>
      <td>
        <select class="bios-select-storage" onchange="updateWorkingFile(${index}, 'storage_provider', this.value)">
          <option value="blackblaze"${storage === 'blackblaze' ? ' selected' : ''}>blackblaze</option>
          <option value="external"${storage === 'external' ? ' selected' : ''}>external</option>
        </select>
      </td>
      <td>
        <input type="text" class="bios-input-path" placeholder="Key or URL" value="${escapeHtml(pathOrUrl)}" onchange="updateWorkingFile(${index}, 'file_key_or_url', this.value)">
      </td>
      <td style="text-align: center;">
        <button type="button" class="btn danger" style="padding: 2px 6px; font-size: 11px; line-height: 1; min-width: 0;" title="Delete file" onclick="deleteBiosGridRow(${index})">✕</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

/**
 * Updates a field on a working file item
 */
function updateWorkingFile(index, field, value) {
  if (workingConsoleFiles[index]) {
    workingConsoleFiles[index][field] = value;
  }
}

/**
 * Appends a new blank row to the edit grid
 */
function addBiosGridRow() {
  workingConsoleFiles.push({
    id: null,
    display_name: '',
    storage_provider: 'blackblaze',
    file_key_or_url: ''
  });
  renderBiosEditGrid();
  setTimeout(() => {
    const inputs = document.querySelectorAll('.bios-input-name');
    if (inputs.length > 0) {
      inputs[inputs.length - 1].focus();
    }
  }, 20);
}

/**
 * Deletes a row from the edit grid
 */
function deleteBiosGridRow(index) {
  workingConsoleFiles.splice(index, 1);
  renderBiosEditGrid();
}

/**
 * Cancels editing mode without storing changes
 */
function cancelBiosEditMode() {
  workingConsoleFiles = [];
  document.getElementById('biosFilesEditMode').style.display = 'none';
  document.getElementById('biosFilesViewMode').style.display = 'block';
  renderBiosViewMode();
}

/**
 * Accepts edits from the grid
 */
function acceptBiosEditMode() {
  syncWorkingFilesFromDom();

  // Filter out empty rows
  const cleanWorking = workingConsoleFiles.filter(f => 
    (f.display_name && f.display_name.trim() !== '') || 
    (f.file_key_or_url && f.file_key_or_url.trim() !== '')
  );

  const hasChanges = detectFilesChanged(currentConsoleFiles, cleanWorking);

  if (!hasChanges) {
    hasPendingFileChanges = false;
    pendingConsoleFiles = [];
    document.getElementById('downloadableFilesJson').value = '';
  } else {
    hasPendingFileChanges = true;
    pendingConsoleFiles = cleanWorking;
    document.getElementById('downloadableFilesJson').value = JSON.stringify(pendingConsoleFiles);
  }

  document.getElementById('biosFilesEditMode').style.display = 'none';
  document.getElementById('biosFilesViewMode').style.display = 'block';
  renderBiosViewMode();
}

/**
 * Syncs DOM inputs to workingConsoleFiles array
 */
function syncWorkingFilesFromDom() {
  const rows = document.querySelectorAll('#biosGridBody tr');
  rows.forEach((tr, i) => {
    const idx = tr.dataset.index !== undefined ? parseInt(tr.dataset.index, 10) : i;
    if (!isNaN(idx) && workingConsoleFiles[idx]) {
      const nameInput = tr.querySelector('.bios-input-name');
      const storageSelect = tr.querySelector('.bios-select-storage');
      const pathInput = tr.querySelector('.bios-input-path');
      if (nameInput) workingConsoleFiles[idx].display_name = nameInput.value.trim();
      if (storageSelect) workingConsoleFiles[idx].storage_provider = storageSelect.value;
      if (pathInput) workingConsoleFiles[idx].file_key_or_url = pathInput.value.trim();
    }
  });
}

/**
 * Checks whether the files list differs from the canonical saved list
 */
function detectFilesChanged(original, modified) {
  if (original.length !== modified.length) return true;
  for (let i = 0; i < original.length; i++) {
    const o = original[i];
    const m = modified[i];
    if ((o.display_name || '').trim() !== (m.display_name || '').trim()) return true;
    if ((o.storage_provider || 'external') !== (m.storage_provider || 'external')) return true;
    if ((o.file_key_or_url || '').trim() !== (m.file_key_or_url || '').trim()) return true;
  }
  return false;
}

/**
 * Returns icon markup representing internal/owned storage vs external link
 */
function getBiosFileIcon(provider) {
  if (provider === 'blackblaze') {
    return `<span class="bios-file-icon bios-icon-internal" title="Internal File"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg></span>`;
  }
  return `<span class="bios-file-icon bios-icon-external" title="External File"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg></span>`;
}

/**
 * Renders the view mode list (links if saved, text only if pending changes)
 */
function renderBiosViewMode() {
  const ul = document.getElementById('biosFilesList');
  const emptyMsg = document.getElementById('biosFilesEmptyMsg');
  const pendingNotice = document.getElementById('biosFilesPendingNotice');
  if (!ul) return;

  ul.innerHTML = '';
  const filesToDisplay = hasPendingFileChanges ? pendingConsoleFiles : currentConsoleFiles;

  if (!filesToDisplay || filesToDisplay.length === 0) {
    if (emptyMsg) emptyMsg.style.display = 'block';
    if (pendingNotice) pendingNotice.style.display = hasPendingFileChanges ? 'block' : 'none';
    return;
  }

  if (emptyMsg) emptyMsg.style.display = 'none';

  filesToDisplay.forEach(f => {
    const li = document.createElement('li');
    const name = f.display_name || '(Unnamed File)';
    const provider = f.storage_provider || 'external';
    const iconHtml = getBiosFileIcon(provider);

    if (hasPendingFileChanges) {
      // TEXT ONLY (no links) while changes are pending
      li.innerHTML = `${iconHtml}<span class="bios-text-only">${escapeHtml(name)}</span>`;
    } else {
      // LIVE CLICKABLE LINK to server download handler
      if (f.id) {
        li.innerHTML = `${iconHtml}<a href="/download/${f.id}" target="_blank" class="bios-download-link" title="Download ${escapeHtml(name)}">${escapeHtml(name)}</a>`;
      } else {
        li.innerHTML = `${iconHtml}<span class="bios-text-only">${escapeHtml(name)}</span>`;
      }
    }
    ul.appendChild(li);
  });

  if (pendingNotice) {
    pendingNotice.style.display = hasPendingFileChanges ? 'block' : 'none';
  }
}

/**
 * Helper to display image preview in dropzone
 */
function setDropzoneImage(containerId, url, deleteBtnId, icon, label) {
  const container = document.getElementById(containerId);
  const deleteBtn = document.getElementById(deleteBtnId);
  if (!container) return;

  if (url && url.trim() !== '') {
    const cacheBuster = (url.includes('?') ? '&' : '?') + '_t=' + Date.now();
    const displayUrl = url + cacheBuster;
    container.innerHTML = `<img src="${escapeHtml(displayUrl)}" class="dropzone-preview-img" alt="Asset Preview" onerror="this.parentElement.innerHTML='<div class=\\'dropzone-empty\\'><span>⚠️</span><small>Image not found</small></div>';">`;
    if (deleteBtn && canWrite) deleteBtn.disabled = false;
  } else {
    resetDropzonePreview(containerId, deleteBtnId, icon, label);
  }
}

/**
 * Resets dropzone box to empty state
 */
function resetDropzonePreview(containerId, deleteBtnId, icon, label) {
  const container = document.getElementById(containerId);
  const deleteBtn = document.getElementById(deleteBtnId);
  if (container) {
    container.innerHTML = `
      <div class="dropzone-empty">
        <span>${icon}</span>
        <strong>${label}</strong>
        <small>Drop or click</small>
      </div>
    `;
  }
  if (deleteBtn) {
    deleteBtn.disabled = true;
  }
}

/**
 * Handles checking/unchecking the "Reference Only" checkbox.
 * When checked: validates eligibility, shows Master Platform dropdown, and ensures it appears clear.
 * When unchecked: hides Master Platform dropdown and clears selection.
 */
function handleReferenceChange(isChecked) {
  const masterGroup = document.getElementById('masterPlatformGroup');
  const masterSelect = document.getElementById('masterReferenceId');

  if (!isChecked) {
    if (masterGroup) masterGroup.style.display = 'none';
    if (masterSelect) {
      masterSelect.required = false;
      masterSelect.value = '';
    }
    return;
  }

  // If in edit mode, validate business rules before allowing checkbox to remain checked
  if (selectedId !== null) {
    const record = consolesList.find(c => Number(c.id) === selectedId);
    if (record) {
      const gameCount = Number(record.games_count ?? record.game_count ?? 0);
      if (gameCount > 0) {
        showToast(`Cannot mark as Reference Only: Console has ${gameCount} game(s) in its library.`, 'error');
        document.getElementById('isForReference').checked = false;
        if (masterGroup) masterGroup.style.display = 'none';
        return;
      }

      const refCount = Number(record.reference_consoles_count ?? 0);
      if (refCount > 0) {
        showToast(`Cannot mark as Reference Only: This console is already the master platform for ${refCount} reference console(s).`, 'error');
        document.getElementById('isForReference').checked = false;
        if (masterGroup) masterGroup.style.display = 'none';
        return;
      }
    }
  }

  // Re-populate master candidates excluding self and any reference consoles
  populateMasterDropdown(selectedId, null);

  // Each time the checkbox is marked the dropdown must appear but clear
  if (masterSelect) {
    masterSelect.value = '';
    masterSelect.required = true;
  }
  if (masterGroup) {
    masterGroup.style.display = 'block';
  }
}

/**
 * Re-populates the master platform dropdown options dynamically
 */
function populateMasterDropdown(excludeId = null, selectedMasterId = null) {
  const masterSelect = document.getElementById('masterReferenceId');
  if (!masterSelect) return;

  const targetVal = (selectedMasterId !== null && selectedMasterId !== undefined && selectedMasterId !== '')
    ? String(selectedMasterId)
    : (masterSelect.value || '');

  masterSelect.innerHTML = '<option value="">-- Select Master Platform --</option>';

  // Master platform candidates: not marked as reference, and not the console itself
  const candidates = consolesList.filter(c => {
    if (Number(c.is_for_reference) === 1) return false;
    if (excludeId !== null && Number(c.id) === Number(excludeId)) return false;
    return true;
  }).sort((a, b) => (a.name || '').localeCompare(b.name || ''));

  // Ensure targetVal is included in candidates if it represents an existing console in consolesList
  if (targetVal && !candidates.some(c => String(c.id) === targetVal)) {
    const existingMaster = consolesList.find(c => String(c.id) === targetVal);
    if (existingMaster) {
      candidates.push(existingMaster);
      candidates.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
    }
  }

  candidates.forEach(c => {
    const opt = document.createElement('option');
    opt.value = String(c.id);
    opt.textContent = c.name;
    if (targetVal && String(c.id) === targetVal) {
      opt.selected = true;
    }
    masterSelect.appendChild(opt);
  });

  if (targetVal && candidates.some(c => String(c.id) === targetVal)) {
    masterSelect.value = targetVal;
  } else if (!targetVal) {
    masterSelect.value = '';
  }
}

/**
 * Triggers hidden file input
 */
function triggerBrowse(inputId) {
  if (!canWrite) return;
  const input = document.getElementById(inputId);
  if (input) input.click();
}

/**
 * Handles file selection from file input
 */
function handleFileSelect(input, containerId, deleteBtnId) {
  if (input.files && input.files[0]) {
    const file = input.files[0];
    const previewUrl = URL.createObjectURL(file);
    const container = document.getElementById(containerId);
    const deleteBtn = document.getElementById(deleteBtnId);

    if (container) {
      container.innerHTML = `<img src="${previewUrl}" class="dropzone-preview-img" alt="Upload Preview">`;
    }
    if (deleteBtn) {
      deleteBtn.disabled = false;
    }

    if (input.id === 'imageFileInput') {
      document.getElementById('deleteImage').value = '0';
    } else if (input.id === 'logoFileInput') {
      document.getElementById('deleteLogo').value = '0';
    }
  }
}

/**
 * Handles explicit asset removal
 */
function removeAsset(type) {
  if (!canWrite) return;

  if (type === 'image') {
    const fileInput = document.getElementById('imageFileInput');
    if (fileInput) fileInput.value = '';
    document.getElementById('deleteImage').value = '1';
    resetDropzonePreview('photoPreviewContainer', 'deletePhotoBtn', '📷', 'Upload Photo');
  } else if (type === 'logo') {
    const fileInput = document.getElementById('logoFileInput');
    if (fileInput) fileInput.value = '';
    document.getElementById('deleteLogo').value = '1';
    resetDropzonePreview('logoPreviewContainer', 'deleteLogoBtn', '🖼️', 'Upload Logo');
  }
}

/**
 * Configures drag & drop for a dropzone box
 */
function setupDropzone(boxId, inputId, containerId, deleteBtnId) {
  const box = document.getElementById(boxId);
  const input = document.getElementById(inputId);
  if (!box || !input) return;

  box.addEventListener('dragover', (e) => {
    e.preventDefault();
    if (canWrite) box.classList.add('drag-over');
  });

  box.addEventListener('dragleave', () => {
    box.classList.remove('drag-over');
  });

  box.addEventListener('drop', (e) => {
    e.preventDefault();
    box.classList.remove('drag-over');
    if (!canWrite) return;

    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      input.files = e.dataTransfer.files;
      handleFileSelect(input, containerId, deleteBtnId);
    }
  });
}

/**
 * Synchronizes table row selection highlight in <data-grid>
 */
function syncRowHighlight() {
  const grid = document.getElementById('consolesGrid');
  if (!grid) return;

  const rows = grid.querySelectorAll('.vault-grid-tbody tr');
  rows.forEach(tr => {
    tr.classList.remove('vault-grid-row-selected', 'active');
    if (selectedId !== null) {
      const firstTd = tr.querySelector('td');
      if (firstTd && firstTd.textContent.trim() === '#' + selectedId) {
        tr.classList.add('vault-grid-row-selected', 'active');
      }
    }
  });
}

/**
 * Handles Form Submission (Create or Update with Multipart FormData)
 */
async function handleSave(e) {
  e.preventDefault();
  if (!canWrite) return;

  const id = document.getElementById('consoleId').value;
  const name = document.getElementById('consoleName').value.trim();
  const saveBtn = document.getElementById('saveBtn');

  if (!name) {
    showToast('Console name is required.', 'error');
    document.getElementById('consoleName').focus();
    return;
  }

  const isRef = document.getElementById('isForReference').checked;
  const masterId = document.getElementById('masterReferenceId').value;
  if (isRef && (!masterId || masterId === '')) {
    showToast('Please select a Master Platform for this reference-only console.', 'error');
    document.getElementById('masterReferenceId').focus();
    return;
  }

  saveBtn.disabled = true;
  saveBtn.textContent = '⏳ Saving...';

  try {
    const isUpdate = Boolean(id);
    const url = isUpdate ? `/consoles/${id}/update` : '/consoles/create';

    // If user is currently editing files in the grid, auto-accept before submit
    const editMode = document.getElementById('biosFilesEditMode');
    if (editMode && editMode.style.display !== 'none') {
      acceptBiosEditMode();
    }

    const formElement = document.getElementById('consoleForm');
    const formData = new FormData(formElement);

    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Accept': 'application/json'
      },
      body: formData
    });

    const result = await res.json();
    if (!res.ok || !result.success) {
      throw new Error(result.error || (result.data && result.data.error) || 'Failed to save console record.');
    }

    const payload = result.data || result;
    showToast(payload.message || 'Console saved successfully.', 'success');

    const targetId = isUpdate ? parseInt(id, 10) : parseInt(payload.id, 10);
    // Reload latest dataset from API and re-select record
    await reloadGridData(targetId);
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    saveBtn.disabled = false;
    saveBtn.textContent = '💾 Save Console';
  }
}

/**
 * Handles Record Deletion
 */
async function handleDelete() {
  if (!canWrite || !selectedId) return;

  const record = consolesList.find(c => Number(c.id) === selectedId);
  if (!record) return;

  const gameCount = Number(record.games_count ?? record.game_count ?? 0);
  if (gameCount > 0) {
    showToast(`Cannot delete: Referenced by ${gameCount} game(s) in collection library.`, 'error');
    return;
  }

  const refCount = Number(record.reference_consoles_count ?? 0);
  if (refCount > 0) {
    showToast(`Cannot delete: This console is the master platform for ${refCount} reference console(s). Reassign or delete those reference consoles first.`, 'error');
    return;
  }

  if (!confirm(`Are you sure you want to permanently delete console "${record.name}" and any associated visual assets?`)) {
    return;
  }

  const deleteBtn = document.getElementById('deleteBtn');
  deleteBtn.disabled = true;

  try {
    const res = await fetch(`/consoles/${selectedId}/delete`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ id: selectedId })
    });

    const result = await res.json();
    if (!res.ok || !result.success) {
      throw new Error(result.error || (result.data && result.data.error) || 'Failed to delete console record.');
    }

    const payload = result.data || result;
    showToast(payload.message || 'Console deleted successfully.', 'success');
    resetForm();
    await reloadGridData();
  } catch (err) {
    showToast(err.message, 'error');
    deleteBtn.disabled = false;
  }
}

/**
 * Fetches latest records from backend API and refreshes <data-grid>
 */
async function reloadGridData(selectIdAfter = null) {
  try {
    const res = await fetch(`/api/consoles?_t=${Date.now()}`, {
      cache: 'no-store',
      headers: {
        'Accept': 'application/json',
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache'
      }
    });
    const json = await res.json();
    consolesList = (json.data && json.data.consoles) ? json.data.consoles : (json.consoles || []);
    mapConsolesDataset(consolesList);

    if (json.data && Array.isArray(json.data.makers)) {
      makersList = json.data.makers;
      const select = document.getElementById('publisherId');
      if (select) {
        const curVal = select.value;
        select.innerHTML = '<option value="">-- Select Maker --</option>' +
          makersList.map(m => `<option value="${m.id}">${escapeHtml(m.name)}</option>`).join('');
        select.value = curVal;
      }
    }

    const typesArr = (json.data && json.data.console_types) ? json.data.console_types : (json.console_types || []);
    if (Array.isArray(typesArr) && typesArr.length > 0) {
      consoleTypesList = typesArr;
      const typeSelect = document.getElementById('consoleTypeId');
      if (typeSelect) {
        const curVal = typeSelect.value;
        typeSelect.innerHTML = '<option value="">-- Select Type --</option>' +
          consoleTypesList.map(t => `<option value="${t.id}">${escapeHtml(t.name)}</option>`).join('');
        typeSelect.value = curVal;
      }
    }

    const grid = document.getElementById('consolesGrid');
    if (grid) {
      grid.data = consolesList;
    }

    // Refresh master candidates dropdown
    const masterSelect = document.getElementById('masterReferenceId');
    const curMasterVal = masterSelect ? masterSelect.value : null;
    populateMasterDropdown(selectedId, curMasterVal);

    if (selectIdAfter) {
      selectConsole(selectIdAfter);
    }

    if (typeof window.refreshHeaderTelemetry === 'function') {
      window.refreshHeaderTelemetry();
    }
  } catch (err) {
    console.error('Failed to reload consoles dataset:', err);
  }
}

/**
 * Toast Notification Helper
 */
function showToast(msg, type = 'success') {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.textContent = msg;
  container.appendChild(toast);

  setTimeout(() => toast.classList.add('show'), 10);
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 250);
  }, 3200);
}

/**
 * Safe HTML Escaper Helper
 */
function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/**
 * Auto-fills Personal Notes / Specs using Gemini AI
 */
async function autofillConsoleSpecs() {
  if (!canWrite) return;

  const nameInput = document.getElementById('consoleName');
  const name = nameInput ? nameInput.value.trim() : '';

  if (!name) {
    showToast('Please enter a Console Name first before auto-filling specs.', 'error');
    if (nameInput) nameInput.focus();
    return;
  }

  const btn = document.getElementById('aiAutoFillBtn');
  const commentsArea = document.getElementById('comments');
  const publisherSelect = document.getElementById('publisherId');
  const typeSelect = document.getElementById('consoleTypeId');
  const generationInput = document.getElementById('generation');
  const yearInput = document.getElementById('releaseYear');

  const payload = {
    name: name,
    publisher_id: publisherSelect ? publisherSelect.value : '',
    maker: publisherSelect && publisherSelect.selectedIndex > 0 ? publisherSelect.options[publisherSelect.selectedIndex].text : '',
    console_type_id: typeSelect ? typeSelect.value : '',
    type: typeSelect && typeSelect.selectedIndex > 0 ? typeSelect.options[typeSelect.selectedIndex].text : '',
    generation: generationInput ? generationInput.value.trim() : '',
    year: yearInput ? yearInput.value.trim() : ''
  };

  let origBtnHtml = '';
  if (btn) {
    origBtnHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="ai-sparkle-icon">⏳</span> Auto-filling...';
  }

  try {
    const res = await fetch('/api/consoles/autofill-specs', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    });

    const rawText = await res.text();
    let json;
    try {
      json = JSON.parse(rawText);
    } catch (e) {
      console.error('Non-JSON response from AI service:', rawText);
      const cleanErr = rawText.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
      throw new Error(cleanErr || 'Server returned an invalid or non-JSON response.');
    }

    if (!res.ok || json.success === false) {
      throw new Error(json.error || (json.data && json.data.error) || 'Failed to auto-fill console specifications.');
    }

    const respData = json.data || json;
    const specs = respData.specs || '';

    if (!specs) {
      throw new Error('No specifications returned from Gemini API.');
    }

    if (commentsArea) {
      if (commentsArea.value.trim() !== '') {
        commentsArea.value = commentsArea.value.trim() + "\n\n" + specs;
      } else {
        commentsArea.value = specs;
      }
      commentsArea.focus();
      const modelInfo = respData.model_used ? ` (via ${escapeHtml(respData.model_used)})` : '';
      showToast('Personal Notes / Specs filled by Gemini AI' + modelInfo + '.', 'success');
    }
  } catch (err) {
    console.error('AI Auto-Fill Error:', err);
    showToast(err.message || 'Error generating console specs.', 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = origBtnHtml;
    }
  }
}
</script>
