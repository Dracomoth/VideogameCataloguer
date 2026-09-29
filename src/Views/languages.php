<?php
/**
 * src/Views/languages.php
 * Taxonomy: Languages & Regions Master-Detail Maintenance Workbench.
 *
 * Implements a split two-column workbench:
 * - Left Pane: Persistent Form Editor (Always Visible) for rapid data entry.
 * - Right Pane: Searchable & Sortable <data-grid> with pagination.
 *
 * Variables expected from LanguageController:
 * @var array<int, array<string, mixed>> $languages
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
</style>

<div class="workspace">
  <!-- LEFT PANE: Persistent Form Editor (Always Visible) -->
  <aside class="editor-card">
    <div class="card-header">
      <span id="formModeTitle" class="card-title">NEW LANGUAGE</span>
      <span id="activeIdBadge" class="badge-record">(Auto ID)</span>
    </div>

    <form id="languageForm" onsubmit="handleSave(event)">
      <input type="hidden" id="langId" value="">

      <div class="form-group">
        <label for="langName">Language Name *</label>
        <input 
          type="text" 
          id="langName" 
          class="form-control" 
          placeholder="e.g. English, Japanese..." 
          required 
          autocomplete="off"
          autofocus
          <?= !$canWrite ? 'disabled' : '' ?>
        >
      </div>

      <div class="editor-actions">
        <?php if ($canWrite): ?>
          <button type="submit" id="saveBtn" class="btn primary">
            💾 Save Language
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
      id="languagesGrid" 
      page-size="25" 
      search-placeholder="Type to filter languages instantly..."
    ></data-grid>
  </section>
</div>

<!-- Raw Initial Server Dataset -->
<script id="serverLanguagesData" type="application/json">
  <?= json_encode($languages, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>

<script>
/**
 * Master-Detail Languages Controller Script
 */
let languagesList = [];
let selectedId = null;
const canWrite = <?= json_encode($canWrite) ?>;

document.addEventListener('DOMContentLoaded', () => {
  // 1. Ingest initial server pre-rendered dataset
  try {
    const rawData = document.getElementById('serverLanguagesData').textContent;
    languagesList = JSON.parse(rawData || '[]');
  } catch (err) {
    console.error('Failed to parse server languages dataset:', err);
    languagesList = [];
  }

  // 2. Initialize the reusable <data-grid>
  const grid = document.getElementById('languagesGrid');
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
        label: 'Language',
        sortable: true,
        searchable: true,
        render: (val) => `<span style="font-weight: 600; color: var(--text-main);">${escapeHtml(val)}</span>`
      },
      {
        key: 'games_count',
        label: 'Linked Titles',
        sortable: true,
        align: 'right',
        width: '160px',
        render: (val, row) => {
          const count = Number(val ?? (row && row.game_count) ?? 0);
          const isActive = count > 0;
          const text = `${count} ${count === 1 ? 'title' : 'titles'}`;
          return `<span class="usage-badge ${isActive ? 'active-use' : ''}">${text}</span>`;
        }
      }
    ];

    grid.data = languagesList;

    // 3. Row selection listener: populates the persistent left-hand form
    grid.addEventListener('row-click', (e) => {
      const row = e.detail.row;
      if (row && row.id !== undefined) {
        selectLanguage(Number(row.id));
      }
    });

    // Re-apply visual row highlight when page changes or sorts occur
    grid.addEventListener('page-change', () => syncRowHighlight());
  }

  // 4. Global keyboard shortcut bindings: Ctrl+S to save, Esc to reset/new
  window.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      if (canWrite) {
        document.getElementById('languageForm').requestSubmit();
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
 * Populates persistent left editor with selected language record
 */
function selectLanguage(id) {
  const record = languagesList.find(l => Number(l.id) === Number(id));
  if (!record) return;

  selectedId = Number(record.id);
  document.getElementById('langId').value = String(record.id);
  document.getElementById('langName').value = record.name || '';
  document.getElementById('formModeTitle').textContent = 'EDIT LANGUAGE';
  document.getElementById('activeIdBadge').textContent = `#${record.id}`;

  const deleteBtn = document.getElementById('deleteBtn');
  if (deleteBtn) {
    deleteBtn.disabled = false;
    const gameCount = Number(record.games_count ?? record.game_count ?? 0);
    if (gameCount > 0) {
      deleteBtn.title = `Cannot delete: Used by ${gameCount} game(s)`;
    } else {
      deleteBtn.title = `Delete ${record.name}`;
    }
  }

  syncRowHighlight();
  document.getElementById('langName').focus();
}

/**
 * Resets the persistent form back to "NEW LANGUAGE" (Auto ID) state
 */
function resetForm() {
  selectedId = null;
  document.getElementById('langId').value = '';
  document.getElementById('langName').value = '';
  document.getElementById('formModeTitle').textContent = 'NEW LANGUAGE';
  document.getElementById('activeIdBadge').textContent = '(Auto ID)';

  const deleteBtn = document.getElementById('deleteBtn');
  if (deleteBtn) {
    deleteBtn.disabled = true;
    deleteBtn.removeAttribute('title');
  }

  syncRowHighlight();
  const input = document.getElementById('langName');
  if (input && !input.disabled) {
    input.focus();
  }
}

/**
 * Synchronizes table row selection highlight in <data-grid>
 */
function syncRowHighlight() {
  const grid = document.getElementById('languagesGrid');
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
 * Handles Form Submission (Create or Update)
 */
async function handleSave(e) {
  e.preventDefault();
  if (!canWrite) return;

  const id = document.getElementById('langId').value;
  const name = document.getElementById('langName').value.trim();
  const saveBtn = document.getElementById('saveBtn');

  if (!name) return;

  saveBtn.disabled = true;
  saveBtn.textContent = '⏳ Saving...';

  try {
    const isUpdate = Boolean(id);
    const url = isUpdate ? `/languages/${id}/update` : '/languages/create';

    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        id: isUpdate ? parseInt(id, 10) : null,
        name: name
      })
    });

    const result = await res.json();
    if (!res.ok || !result.success) {
      throw new Error(result.error || (result.data && result.data.error) || 'Failed to save language record.');
    }

    const payload = result.data || result;
    showToast(payload.message || 'Saved successfully.', 'success');

    // Reload latest dataset from API and re-select record
    await reloadGridData(isUpdate ? parseInt(id, 10) : payload.id);
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    saveBtn.disabled = false;
    saveBtn.textContent = '💾 Save Language';
  }
}

/**
 * Handles Record Deletion
 */
async function handleDelete() {
  if (!canWrite || !selectedId) return;

  const record = languagesList.find(l => Number(l.id) === selectedId);
  if (!record) return;

  const gameCount = Number(record.games_count ?? record.game_count ?? 0);
  if (gameCount > 0) {
    showToast(`Cannot delete: Referenced by ${gameCount} game(s).`, 'error');
    return;
  }

  if (!confirm(`Are you sure you want to permanently delete "${record.name}"?`)) {
    return;
  }

  const deleteBtn = document.getElementById('deleteBtn');
  deleteBtn.disabled = true;

  try {
    const res = await fetch(`/languages/${selectedId}/delete`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ id: selectedId })
    });

    const result = await res.json();
    if (!res.ok || !result.success) {
      throw new Error(result.error || (result.data && result.data.error) || 'Failed to delete language record.');
    }

    const payload = result.data || result;
    showToast(payload.message || 'Language deleted successfully.', 'success');
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
    const res = await fetch('/api/languages', {
      headers: { 'Accept': 'application/json' }
    });
    const json = await res.json();
    languagesList = (json.data && json.data.languages) ? json.data.languages : (json.languages || []);

    const grid = document.getElementById('languagesGrid');
    if (grid) {
      grid.data = languagesList;
    }

    if (selectIdAfter) {
      selectLanguage(selectIdAfter);
    }
  } catch (err) {
    console.error('Failed to reload languages dataset:', err);
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
