<?php
/**
 * src/Views/console_types.php
 * Taxonomy: Console Hardware Types Master-Detail Maintenance Workbench.
 *
 * Implements a split two-column workbench:
 * - Left Pane: Persistent Form Editor with color pickers & live badge preview card.
 * - Right Pane: Searchable & Sortable <data-grid> displaying badges and console counts.
 *
 * Variables expected from ConsoleTypeController:
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
/* Scoped Workbench & Row Highlight Overrides */
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

/* Color Picker Container */
.color-picker-row {
  display: flex;
  gap: 10px;
  align-items: center;
}
.color-hex-input {
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  flex: 1;
}
.color-swatch-picker {
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  width: 44px;
  height: 38px;
  padding: 2px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: rgba(15, 23, 42, 0.6);
  cursor: pointer;
  outline: none;
  transition: border-color var(--transition-fast);
}
.color-swatch-picker:hover {
  border-color: var(--border-focus);
}
.color-swatch-picker::-webkit-color-swatch-wrapper {
  padding: 0;
}
.color-swatch-picker::-webkit-color-swatch {
  border: none;
  border-radius: 3px;
}
.color-swatch-picker::-moz-color-swatch {
  border: none;
  border-radius: 3px;
}

/* Live Badge Preview Card */
.preview-box {
  min-height: 58px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: rgba(15, 23, 42, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 12px;
  box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
}
.badge-sample {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
  transition: all 0.15s ease;
}
.tag-flag-grid {
  display: inline-flex;
  align-items: center;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
</style>

<div class="workspace">
  <!-- LEFT PANE: Persistent Form Editor (Always Visible) -->
  <aside class="editor-card">
    <div class="card-header">
      <span id="formModeTitle" class="card-title">NEW CONSOLE TYPE</span>
      <span id="activeIdBadge" class="badge-record">(Auto ID)</span>
    </div>

    <form id="consoleTypeForm" onsubmit="handleSave(event)">
      <input type="hidden" id="typeId" value="">

      <!-- Console Type Name -->
      <div class="form-group">
        <label for="typeName">Type Name *</label>
        <input 
          type="text" 
          id="typeName" 
          class="form-control" 
          placeholder="e.g. Home, Arcade, Microcomputer, Handheld..." 
          required 
          autocomplete="off"
          autofocus
          <?= !$canWrite ? 'disabled' : '' ?>
        >
      </div>

      <!-- Badge Background Color -->
      <div class="form-group">
        <label for="badgeBgColorText">Badge Background Color *</label>
        <div class="color-picker-row">
          <input 
            type="text" 
            id="badgeBgColorText" 
            class="form-control color-hex-input" 
            value="#1E3A8A" 
            maxlength="7" 
            placeholder="#1E3A8A" 
            pattern="^#[0-9a-fA-F]{6}$" 
            required 
            autocomplete="off"
            <?= !$canWrite ? 'disabled' : '' ?>
          >
          <input 
            type="color" 
            id="badgeBgColorPicker" 
            class="color-swatch-picker" 
            value="#1e3a8a" 
            title="Pick background color"
            <?= !$canWrite ? 'disabled' : '' ?>
          >
        </div>
      </div>

      <!-- Badge Font / Text Color -->
      <div class="form-group">
        <label for="badgeFontColorText">Badge Font / Text Color *</label>
        <div class="color-picker-row">
          <input 
            type="text" 
            id="badgeFontColorText" 
            class="form-control color-hex-input" 
            value="#93C5FD" 
            maxlength="7" 
            placeholder="#93C5FD" 
            pattern="^#[0-9a-fA-F]{6}$" 
            required 
            autocomplete="off"
            <?= !$canWrite ? 'disabled' : '' ?>
          >
          <input 
            type="color" 
            id="badgeFontColorPicker" 
            class="color-swatch-picker" 
            value="#93c5fd" 
            title="Pick font / text color"
            <?= !$canWrite ? 'disabled' : '' ?>
          >
        </div>
      </div>

      <!-- Live Badge Preview Rectangle -->
      <div class="form-group">
        <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-dim); letter-spacing: 0.05em;">
          Live Badge Preview
        </label>
        <div class="preview-box">
          <span id="liveBadgePreview" class="badge-sample" style="background-color: #1e3a8a; color: #93c5fd; border: 1px solid rgba(147, 197, 253, 0.3);">
            HOME
          </span>
        </div>
      </div>

      <!-- Form Action Controls -->
      <div class="editor-actions">
        <?php if ($canWrite): ?>
          <button type="submit" id="saveBtn" class="btn primary">
            💾 Save Console Type
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

  <!-- RIGHT PANE: Reusable <data-grid> Component -->
  <section class="grid-card">
    <data-grid 
      id="consoleTypesGrid" 
      page-size="25" 
      search-placeholder="Type to filter console types instantly..."
    ></data-grid>
  </section>
</div>

<!-- Raw Initial Server Dataset -->
<script id="serverConsoleTypesData" type="application/json">
  <?= json_encode($consoleTypes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>

<script>
/**
 * Master-Detail Console Types Controller Script
 */
let consoleTypesList = [];
let selectedId = null;
const canWrite = <?= json_encode($canWrite) ?>;

// Default palette fallback
const DEFAULT_BG_COLOR   = '#1E3A8A';
const DEFAULT_FONT_COLOR = '#93C5FD';

document.addEventListener('DOMContentLoaded', () => {
  // 1. Ingest initial server pre-rendered dataset
  try {
    const rawData = document.getElementById('serverConsoleTypesData').textContent;
    consoleTypesList = JSON.parse(rawData || '[]');
  } catch (err) {
    console.error('Failed to parse server console types dataset:', err);
    consoleTypesList = [];
  }

  // 2. Initialize the reusable <data-grid>
  const grid = document.getElementById('consoleTypesGrid');
  if (grid) {
    grid.columns = [
      {
        key: 'id',
        label: 'ID',
        sortable: true,
        width: '70px',
        render: (val) => `<span style="font-weight: 600; color: var(--text-dim);">#${escapeHtml(val)}</span>`
      },
      {
        key: 'name',
        label: 'Type Name',
        sortable: true,
        searchable: true,
        render: (val) => `<span style="font-weight: 600; color: var(--text-main); font-size: 13px;">${escapeHtml(val)}</span>`
      },
      {
        key: 'badge_preview',
        label: 'BADGE',
        sortable: false,
        width: '140px',
        render: (val, row) => {
          const bg = escapeHtml(row.badge_bg_color || DEFAULT_BG_COLOR);
          const font = escapeHtml(row.badge_font_color || DEFAULT_FONT_COLOR);
          const name = escapeHtml(row.name || 'TYPE');
          return `<span class="tag-flag-grid" style="background-color: ${bg}; color: ${font}; border: 1px solid ${font}44;">${name}</span>`;
        }
      },
      {
        key: 'consoles_count',
        label: 'Linked Consoles',
        sortable: true,
        align: 'right',
        width: '160px',
        render: (val, row) => {
          const count = Number(val ?? (row && row.console_count) ?? 0);
          const isActive = count > 0;
          const text = `${count} ${count === 1 ? 'console' : 'consoles'}`;
          return `<span class="usage-badge ${isActive ? 'active-use' : ''}">${text}</span>`;
        }
      }
    ];

    grid.data = consoleTypesList;

    // 3. Row selection listener: populates the persistent left-hand form
    grid.addEventListener('row-click', (e) => {
      const row = e.detail.row;
      if (row && row.id !== undefined) {
        selectConsoleType(Number(row.id));
      }
    });

    // Re-apply visual row highlight when page changes or sorts occur
    grid.addEventListener('page-change', () => syncRowHighlight());
  }

  // 4. Color sync listeners & Live Preview updates
  setupColorSync('badgeBgColorText', 'badgeBgColorPicker');
  setupColorSync('badgeFontColorText', 'badgeFontColorPicker');

  const typeNameInput = document.getElementById('typeName');
  if (typeNameInput) {
    typeNameInput.addEventListener('input', updateLiveBadge);
  }

  // Initial live badge render
  updateLiveBadge();

  // 5. Global keyboard shortcut bindings: Ctrl+S to save, Esc to reset/new
  window.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      if (canWrite) {
        document.getElementById('consoleTypeForm').requestSubmit();
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
 * Sets up bidirectional synchronization between hex text input and HTML5 color swatch picker
 */
function setupColorSync(textId, pickerId) {
  const textInput = document.getElementById(textId);
  const pickerInput = document.getElementById(pickerId);
  if (!textInput || !pickerInput) return;

  // Swatch -> Text
  pickerInput.addEventListener('input', () => {
    textInput.value = pickerInput.value.toUpperCase();
    updateLiveBadge();
  });

  // Text -> Swatch
  textInput.addEventListener('input', () => {
    let val = textInput.value.trim();
    if (!val.startsWith('#') && val.length > 0) {
      val = '#' + val;
      textInput.value = val;
    }
    if (/^#[0-9a-fA-F]{6}$/.test(val)) {
      pickerInput.value = val;
    }
    updateLiveBadge();
  });
}

/**
 * Updates the live badge preview box in real-time
 */
function updateLiveBadge() {
  const preview = document.getElementById('liveBadgePreview');
  const nameInput = document.getElementById('typeName');
  const bgInput = document.getElementById('badgeBgColorText');
  const fontInput = document.getElementById('badgeFontColorText');

  if (!preview) return;

  const name = (nameInput && nameInput.value.trim()) || 'PREVIEW';
  const bg = (bgInput && bgInput.value.trim()) || DEFAULT_BG_COLOR;
  const font = (fontInput && fontInput.value.trim()) || DEFAULT_FONT_COLOR;

  preview.textContent = name.toUpperCase();
  if (/^#[0-9a-fA-F]{6}$/.test(bg)) {
    preview.style.backgroundColor = bg;
  }
  if (/^#[0-9a-fA-F]{6}$/.test(font)) {
    preview.style.color = font;
    preview.style.borderColor = font + '44';
  }
}

/**
 * Populates persistent left editor with selected console type record
 */
function selectConsoleType(id) {
  const record = consoleTypesList.find(t => Number(t.id) === Number(id));
  if (!record) return;

  selectedId = Number(record.id);
  document.getElementById('typeId').value = String(record.id);
  document.getElementById('typeName').value = record.name || '';
  
  const bg = record.badge_bg_color || DEFAULT_BG_COLOR;
  const font = record.badge_font_color || DEFAULT_FONT_COLOR;

  document.getElementById('badgeBgColorText').value = bg.toUpperCase();
  document.getElementById('badgeBgColorPicker').value = bg;
  document.getElementById('badgeFontColorText').value = font.toUpperCase();
  document.getElementById('badgeFontColorPicker').value = font;

  document.getElementById('formModeTitle').textContent = 'EDIT CONSOLE TYPE';
  document.getElementById('activeIdBadge').textContent = `#${record.id}`;

  const deleteBtn = document.getElementById('deleteBtn');
  if (deleteBtn) {
    deleteBtn.disabled = false;
    const consolesCount = Number(record.consoles_count ?? record.console_count ?? 0);
    if (consolesCount > 0) {
      deleteBtn.title = `Cannot delete: Used by ${consolesCount} console(s)`;
    } else {
      deleteBtn.title = `Delete ${record.name}`;
    }
  }

  updateLiveBadge();
  syncRowHighlight();
  document.getElementById('typeName').focus();
}

/**
 * Resets the persistent form back to "NEW CONSOLE TYPE" (Auto ID) state
 */
function resetForm() {
  selectedId = null;
  document.getElementById('typeId').value = '';
  document.getElementById('typeName').value = '';
  document.getElementById('badgeBgColorText').value = DEFAULT_BG_COLOR;
  document.getElementById('badgeBgColorPicker').value = DEFAULT_BG_COLOR;
  document.getElementById('badgeFontColorText').value = DEFAULT_FONT_COLOR;
  document.getElementById('badgeFontColorPicker').value = DEFAULT_FONT_COLOR;

  document.getElementById('formModeTitle').textContent = 'NEW CONSOLE TYPE';
  document.getElementById('activeIdBadge').textContent = '(Auto ID)';

  const deleteBtn = document.getElementById('deleteBtn');
  if (deleteBtn) {
    deleteBtn.disabled = true;
    deleteBtn.removeAttribute('title');
  }

  updateLiveBadge();
  syncRowHighlight();
  const input = document.getElementById('typeName');
  if (input && !input.disabled) {
    input.focus();
  }
}

/**
 * Synchronizes table row selection highlight in <data-grid>
 */
function syncRowHighlight() {
  const grid = document.getElementById('consoleTypesGrid');
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
 * Handles Form Submission (Create or Update) via fetch API
 */
async function handleSave(event) {
  event.preventDefault();
  if (!canWrite) return;

  const id = document.getElementById('typeId').value.trim();
  const name = document.getElementById('typeName').value.trim();
  const bg = document.getElementById('badgeBgColorText').value.trim();
  const font = document.getElementById('badgeFontColorText').value.trim();

  if (!name) {
    showToast('Type name is required.', 'error');
    return;
  }

  if (!/^#[0-9a-fA-F]{6}$/.test(bg)) {
    showToast('Background color must be a valid 6-digit hex code (e.g. #1E3A8A).', 'error');
    return;
  }

  if (!/^#[0-9a-fA-F]{6}$/.test(font)) {
    showToast('Font color must be a valid 6-digit hex code (e.g. #93C5FD).', 'error');
    return;
  }

  const isEdit = id !== '';
  const url = isEdit ? `/console-types/${id}/update` : '/console-types/create';

  const payload = {
    name: name,
    badge_bg_color: bg,
    badge_font_color: font
  };
  if (isEdit) {
    payload.id = Number(id);
  }

  const saveBtn = document.getElementById('saveBtn');
  const origText = saveBtn ? saveBtn.textContent : '';
  if (saveBtn) {
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';
  }

  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    });

    const result = await res.json();

    if (!res.ok) {
      throw new Error(result.error || (result.data && result.data.message) || result.message || 'Operation failed');
    }

    const payload = result.data || result;
    showToast(payload.message || 'Saved successfully', 'success');

    // Refresh grid dataset
    await reloadDataset(isEdit ? Number(id) : Number(payload.id));
  } catch (err) {
    console.error('Save failed:', err);
    showToast(err.message || 'Failed to save console type', 'error');
  } finally {
    if (saveBtn) {
      saveBtn.disabled = false;
      saveBtn.textContent = origText;
    }
  }
}

/**
 * Handles Deletion of the currently selected console type
 */
async function handleDelete() {
  if (!selectedId || !canWrite) return;

  const record = consoleTypesList.find(t => Number(t.id) === selectedId);
  const name = record ? record.name : `Console Type #${selectedId}`;
  const consolesCount = Number(record ? (record.consoles_count ?? record.console_count ?? 0) : 0);

  if (consolesCount > 0) {
    showToast(`Cannot delete: ${consolesCount} console(s) are currently assigned to '${name}'.`, 'error');
    return;
  }

  if (!confirm(`Are you sure you want to delete '${name}'? This action cannot be undone.`)) {
    return;
  }

  const deleteBtn = document.getElementById('deleteBtn');
  if (deleteBtn) {
    deleteBtn.disabled = true;
  }

  try {
    const res = await fetch(`/console-types/${selectedId}/delete`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ id: selectedId })
    });

    const result = await res.json();

    if (!res.ok) {
      throw new Error(result.error || (result.data && result.data.message) || result.message || 'Delete failed');
    }

    const payload = result.data || result;
    showToast(payload.message || 'Console type deleted successfully', 'success');
    resetForm();
    await reloadDataset(null);
  } catch (err) {
    console.error('Delete error:', err);
    showToast(err.message || 'Failed to delete console type', 'error');
    if (deleteBtn) {
      deleteBtn.disabled = false;
    }
  }
}

/**
 * Fetches fresh dataset from API and updates <data-grid>
 */
async function reloadDataset(targetSelectId = null) {
  try {
    const res = await fetch('/api/console-types', {
      headers: { 'Accept': 'application/json' }
    });
    const json = await res.json();
    const payload = json.data || json;
    consoleTypesList = payload.console_types || [];

    const grid = document.getElementById('consoleTypesGrid');
    if (grid) {
      grid.data = consoleTypesList;
    }

    if (targetSelectId !== null) {
      selectConsoleType(targetSelectId);
    } else {
      syncRowHighlight();
    }
  } catch (err) {
    console.error('Failed to reload dataset:', err);
  }
}

/**
 * Helper to escape HTML characters in strings
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
