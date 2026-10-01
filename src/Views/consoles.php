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

    <form id="consoleForm" onsubmit="handleSave(event)">
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

      <!-- Collapsible Emulation & Technical Links -->
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
              <input type="url" id="coreLink" name="core_link" class="form-control" placeholder="https://..." <?= !$canWrite ? 'disabled' : '' ?>>
            </div>
          </div>

          <div class="form-grid-2">
            <div class="form-group">
              <label for="emulator">Desktop Emulator</label>
              <input type="text" id="emulator" name="emulator" class="form-control" placeholder="e.g. mGBA, PCSX2" <?= !$canWrite ? 'disabled' : '' ?>>
            </div>
            <div class="form-group">
              <label for="emulatorLink">Desktop URL</label>
              <input type="url" id="emulatorLink" name="emulator_link" class="form-control" placeholder="https://..." <?= !$canWrite ? 'disabled' : '' ?>>
            </div>
          </div>

          <div class="form-grid-2">
            <div class="form-group">
              <label for="emulatorAndroid">Android Emulator</label>
              <input type="text" id="emulatorAndroid" name="emulator_android" class="form-control" placeholder="e.g. Pizza Boy" <?= !$canWrite ? 'disabled' : '' ?>>
            </div>
            <div class="form-group">
              <label for="emulatorAndroidLink">Android URL</label>
              <input type="url" id="emulatorAndroidLink" name="emulator_android_link" class="form-control" placeholder="https://..." <?= !$canWrite ? 'disabled' : '' ?>>
            </div>
          </div>
        </div>
      </details>

      <!-- Personal Notes / Specs -->
      <div class="form-group">
        <label for="comments">Personal Notes / Specs</label>
        <textarea 
          id="comments" 
          name="comments" 
          class="form-control" 
          rows="2" 
          placeholder="BIOS requirements, serial numbers, region notes..."
          <?= !$canWrite ? 'disabled' : '' ?>
        ></textarea>
      </div>

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
  document.getElementById('coreLink').value = record.core_link || '';
  document.getElementById('emulator').value = record.emulator || '';
  document.getElementById('emulatorLink').value = record.emulator_link || '';
  document.getElementById('emulatorAndroid').value = record.emulator_android || '';
  document.getElementById('emulatorAndroidLink').value = record.emulator_android_link || '';
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

  syncRowHighlight();
  const input = document.getElementById('consoleName');
  if (input && !input.disabled) {
    input.focus();
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
</script>
