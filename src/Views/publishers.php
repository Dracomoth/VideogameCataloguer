<?php
/**
 * src/Views/publishers.php
 * Taxonomy: Publishers & Hardware Manufacturers Master-Detail Maintenance Workbench.
 *
 * Implements a split two-column workbench:
 * - Left Pane: Persistent Form Editor (Always Visible) with Hardware Maker classification and platform inspector.
 * - Right Pane: Searchable & Sortable <data-grid> with consoles and game title linkages.
 *
 * Variables expected from PublisherController:
 * @var array<int, array<string, mixed>> $publishers
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
  border-radius: var(--radius-md);
  border: 1px solid var(--border);
  background: var(--panel);
  overflow: hidden;
}

/* Hardware Maker & Console Badges */
.hardware-maker-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  background: rgba(16, 185, 129, 0.16);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.consoles-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 600;
  background: rgba(16, 185, 129, 0.12);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.25);
}
.consoles-badge.empty-use {
  background: rgba(148, 163, 184, 0.08);
  color: var(--text-dim);
  border-color: rgba(148, 163, 184, 0.15);
}

/* Custom Styled Checkbox */
.checkbox-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: rgba(15, 23, 42, 0.5);
  cursor: pointer;
  user-select: none;
  transition: border-color var(--transition-fast), background var(--transition-fast);
}
.checkbox-card:hover {
  border-color: var(--border-focus);
  background: rgba(56, 189, 248, 0.05);
}
.checkbox-card input[type="checkbox"] {
  cursor: pointer;
  width: 16px;
  height: 16px;
  accent-color: #0284c7;
}

.consoles-inspector {
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid var(--border);
}
.consoles-inspector-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--text-dim);
  margin-bottom: 8px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.consoles-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  max-height: 140px;
  overflow-y: auto;
}
.console-chip {
  font-size: 11px;
  background: rgba(16, 185, 129, 0.1);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.25);
  border-radius: var(--radius-sm);
  padding: 2px 7px;
}
</style>

<div class="workspace">
  <!-- LEFT PANE: Persistent Form Editor (Always Visible) -->
  <aside class="editor-card">
    <div class="card-header">
      <span id="formModeTitle" class="card-title">NEW PUBLISHER</span>
      <span id="activeIdBadge" class="badge-record">(Auto ID)</span>
    </div>

    <form id="publisherForm" onsubmit="handleSave(event)">
      <input type="hidden" id="publisherId" value="">

      <div class="form-group">
        <label for="publisherName">Publisher Name *</label>
        <input 
          type="text" 
          id="publisherName" 
          class="form-control" 
          placeholder="e.g. Nintendo, Capcom, Konami..." 
          required 
          autocomplete="off"
          autofocus
          <?= !$canWrite ? 'disabled' : '' ?>
        >
      </div>

      <div class="form-group">
        <label style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--text-dim); letter-spacing: 0.04em;">
          Hardware Classification
        </label>
        <label class="checkbox-card" for="isConsoleMaker">
          <input 
            type="checkbox" 
            id="isConsoleMaker" 
            <?= !$canWrite ? 'disabled' : '' ?>
          >
          <span style="font-size: 13px; font-weight: 500; color: var(--text-main);">
            🕹️ Console / Hardware Maker
          </span>
        </label>
      </div>

      <div class="editor-actions">
        <?php if ($canWrite): ?>
          <button type="submit" id="saveBtn" class="btn primary">
            💾 Save Publisher
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

      <!-- Attached Hardware Platforms Inspector (Visible during Edit) -->
      <div id="consolesInspector" class="consoles-inspector" style="display: none;">
        <div class="consoles-inspector-title">
          <span>Registered Platforms</span>
          <span id="consolesCountBadge" class="consoles-badge" style="font-size: 10px;">0</span>
        </div>
        <div id="consolesChipsContainer" class="consoles-chips">
          <!-- Filled dynamically by selectPublisher() -->
        </div>
      </div>
    </form>
  </aside>

  <!-- RIGHT PANE: Reusable <data-grid> Component -->
  <section class="grid-card">
    <data-grid 
      id="publishersGrid" 
      page-size="25" 
      search-placeholder="Type to filter publishers by name, hardware maker, or ID..."
    ></data-grid>
  </section>
</div>

<!-- Raw Initial Server Dataset -->
<script id="serverPublishersData" type="application/json">
  <?= json_encode($publishers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>

<script>
/**
 * Master-Detail Publishers Controller Script
 */
let publishersList = [];
let selectedId = null;
const canWrite = <?= json_encode($canWrite) ?>;

document.addEventListener('DOMContentLoaded', () => {
  // 1. Ingest initial server pre-rendered dataset
  try {
    const rawData = document.getElementById('serverPublishersData').textContent;
    publishersList = JSON.parse(rawData || '[]');
  } catch (err) {
    console.error('Failed to parse server publishers dataset:', err);
    publishersList = [];
  }

  // 2. Initialize the reusable <data-grid>
  const grid = document.getElementById('publishersGrid');
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
        label: 'Publisher',
        sortable: true,
        searchable: true,
        render: (val) => `<span style="font-weight: 600; color: var(--text-main); font-size: 13px;">${escapeHtml(val)}</span>`
      },
      {
        key: 'is_console_maker',
        label: 'Console Maker',
        sortable: true,
        align: 'center',
        width: '160px',
        render: (val) => {
          const isMaker = Number(val) === 1;
          if (isMaker) {
            return `<span class="hardware-maker-badge">🕹️ HARDWARE MAKER</span>`;
          }
          return `<span style="color: var(--text-dim); font-size: 14px;">—</span>`;
        }
      },
      {
        key: 'consoles_count',
        label: 'Consoles',
        sortable: true,
        align: 'right',
        width: '150px',
        render: (val, row) => {
          const count = Number(val ?? (row && row.console_count) ?? 0);
          const hasPlatforms = count > 0;
          const text = `${count} ${count === 1 ? 'platform' : 'platforms'}`;
          return `<span class="consoles-badge ${!hasPlatforms ? 'empty-use' : ''}">${text}</span>`;
        }
      },
      {
        key: 'games_count',
        label: 'Linked Games',
        sortable: true,
        align: 'right',
        width: '150px',
        render: (val, row) => {
          const count = Number(val ?? (row && row.game_count) ?? 0);
          const isActive = count > 0;
          const text = `${count} ${count === 1 ? 'title' : 'titles'}`;
          return `<span class="usage-badge ${isActive ? 'active-use' : ''}">${text}</span>`;
        }
      }
    ];

    grid.data = publishersList;

    // 3. Row selection listener: populates the persistent left-hand form
    grid.addEventListener('row-click', (e) => {
      const row = e.detail.row;
      if (row && row.id !== undefined) {
        selectPublisher(Number(row.id));
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
        document.getElementById('publisherForm').requestSubmit();
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
 * Populates persistent left editor with selected publisher record
 */
function selectPublisher(id) {
  const record = publishersList.find(p => Number(p.id) === Number(id));
  if (!record) return;

  selectedId = Number(record.id);
  document.getElementById('publisherId').value = String(record.id);
  document.getElementById('publisherName').value = record.name || '';
  document.getElementById('isConsoleMaker').checked = Number(record.is_console_maker) === 1;
  document.getElementById('formModeTitle').textContent = 'EDIT PUBLISHER';
  document.getElementById('activeIdBadge').textContent = `#${record.id}`;

  const deleteBtn = document.getElementById('deleteBtn');
  if (deleteBtn) {
    deleteBtn.disabled = false;
    const consolesCount = Number(record.consoles_count ?? record.console_count ?? 0);
    const gameCount = Number(record.games_count ?? record.game_count ?? 0);

    if (consolesCount > 0 && gameCount > 0) {
      deleteBtn.title = `Cannot delete: Has ${consolesCount} consoles and ${gameCount} games attached`;
    } else if (consolesCount > 0) {
      deleteBtn.title = `Cannot delete: Has ${consolesCount} consoles attached`;
    } else if (gameCount > 0) {
      deleteBtn.title = `Cannot delete: Used by ${gameCount} game(s)`;
    } else {
      deleteBtn.title = `Delete ${record.name}`;
    }
  }

  // Update attached consoles inspector
  const inspector = document.getElementById('consolesInspector');
  const countBadge = document.getElementById('consolesCountBadge');
  const chipsContainer = document.getElementById('consolesChipsContainer');

  if (inspector && chipsContainer) {
    const platforms = Array.isArray(record.consoles) ? record.consoles : [];
    if (platforms.length > 0) {
      countBadge.textContent = `${platforms.length} platforms`;
      chipsContainer.innerHTML = platforms.map(c => `<span class="console-chip">${escapeHtml(c)}</span>`).join('');
      inspector.style.display = 'block';
    } else {
      countBadge.textContent = '0';
      chipsContainer.innerHTML = '<span style="font-size: 11px; color: var(--text-dim); font-style: italic;">No console hardware linked.</span>';
      inspector.style.display = 'block';
    }
  }

  syncRowHighlight();
  document.getElementById('publisherName').focus();
}

/**
 * Resets the persistent form back to "NEW PUBLISHER" (Auto ID) state
 */
function resetForm() {
  selectedId = null;
  document.getElementById('publisherId').value = '';
  document.getElementById('publisherName').value = '';
  document.getElementById('isConsoleMaker').checked = false;
  document.getElementById('formModeTitle').textContent = 'NEW PUBLISHER';
  document.getElementById('activeIdBadge').textContent = '(Auto ID)';

  const deleteBtn = document.getElementById('deleteBtn');
  if (deleteBtn) {
    deleteBtn.disabled = true;
    deleteBtn.removeAttribute('title');
  }

  const inspector = document.getElementById('consolesInspector');
  if (inspector) {
    inspector.style.display = 'none';
  }

  syncRowHighlight();
  const input = document.getElementById('publisherName');
  if (input && !input.disabled) {
    input.focus();
  }
}

/**
 * Synchronizes table row selection highlight in <data-grid>
 */
function syncRowHighlight() {
  const grid = document.getElementById('publishersGrid');
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

  const id = document.getElementById('publisherId').value;
  const name = document.getElementById('publisherName').value.trim();
  const isConsoleMaker = document.getElementById('isConsoleMaker').checked;
  const saveBtn = document.getElementById('saveBtn');

  if (!name) return;

  saveBtn.disabled = true;
  saveBtn.textContent = '⏳ Saving...';

  try {
    const isUpdate = Boolean(id);
    const url = isUpdate ? `/publishers/${id}/update` : '/publishers/create';

    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        id: isUpdate ? parseInt(id, 10) : null,
        name: name,
        is_console_maker: isConsoleMaker ? 1 : 0
      })
    });

    const result = await res.json();
    if (!res.ok || !result.success) {
      throw new Error(result.error || (result.data && result.data.error) || 'Failed to save publisher record.');
    }

    const payload = result.data || result;
    showToast(payload.message || 'Saved successfully.', 'success');

    // Reload latest dataset from API and re-select record
    await reloadGridData(isUpdate ? parseInt(id, 10) : payload.id);
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    saveBtn.disabled = false;
    saveBtn.textContent = '💾 Save Publisher';
  }
}

/**
 * Handles Record Deletion
 */
async function handleDelete() {
  if (!canWrite || !selectedId) return;

  const record = publishersList.find(p => Number(p.id) === selectedId);
  if (!record) return;

  const consolesCount = Number(record.consoles_count ?? record.console_count ?? 0);
  const gameCount = Number(record.games_count ?? record.game_count ?? 0);

  if (consolesCount > 0) {
    showToast(`Cannot delete: Associated with ${consolesCount} console hardware platform(s).`, 'error');
    return;
  }

  if (gameCount > 0) {
    showToast(`Cannot delete: Referenced by ${gameCount} game(s).`, 'error');
    return;
  }

  if (!confirm(`Are you sure you want to permanently delete publisher "${record.name}"?`)) {
    return;
  }

  const deleteBtn = document.getElementById('deleteBtn');
  deleteBtn.disabled = true;

  try {
    const res = await fetch(`/publishers/${selectedId}/delete`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ id: selectedId })
    });

    const result = await res.json();
    if (!res.ok || !result.success) {
      throw new Error(result.error || (result.data && result.data.error) || 'Failed to delete publisher record.');
    }

    const payload = result.data || result;
    showToast(payload.message || 'Publisher deleted successfully.', 'success');
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
    const res = await fetch('/api/publishers', {
      headers: { 'Accept': 'application/json' }
    });
    const json = await res.json();
    publishersList = (json.data && json.data.publishers) ? json.data.publishers : (json.publishers || []);

    const grid = document.getElementById('publishersGrid');
    if (grid) {
      grid.data = publishersList;
    }

    if (selectIdAfter) {
      selectPublisher(selectIdAfter);
    }
  } catch (err) {
    console.error('Failed to reload publishers dataset:', err);
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
