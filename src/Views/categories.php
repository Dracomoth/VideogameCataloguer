<?php
/**
 * src/Views/categories.php
 * Taxonomy: Categories Master-Detail Maintenance Workbench.
 *
 * Implements a split two-column workbench:
 * - Left Pane: Persistent Form Editor (Always Visible) for rapid category entry and subcategory inspection.
 * - Right Pane: Searchable & Sortable <data-grid> with hierarchy counts.
 *
 * Variables expected from CategoryController:
 * @var array<int, array<string, mixed>> $categories
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

/* Category & Subcategory Pill Badges */
.subgenre-badge {
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
.subgenre-badge.empty-use {
  background: rgba(148, 163, 184, 0.08);
  color: var(--text-dim);
  border-color: rgba(148, 163, 184, 0.15);
}

.subcategories-inspector {
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid var(--border);
}
.subcategories-inspector-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--text-dim);
  margin-bottom: 8px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.subcategories-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  max-height: 140px;
  overflow-y: auto;
}
.subcat-chip {
  font-size: 11px;
  background: rgba(56, 189, 248, 0.1);
  color: #38bdf8;
  border: 1px solid rgba(56, 189, 248, 0.2);
  border-radius: var(--radius-sm);
  padding: 2px 7px;
}
</style>

<div class="workspace">
  <!-- LEFT PANE: Persistent Form Editor (Always Visible) -->
  <aside class="editor-card">
    <div class="card-header">
      <span id="formModeTitle" class="card-title">NEW CATEGORY</span>
      <span id="activeIdBadge" class="badge-record">(Auto ID)</span>
    </div>

    <form id="categoryForm" onsubmit="handleSave(event)">
      <input type="hidden" id="categoryId" value="">

      <div class="form-group">
        <label for="categoryName">Category Name *</label>
        <input 
          type="text" 
          id="categoryName" 
          class="form-control" 
          placeholder="e.g. Action, RPG, Simulation..." 
          required 
          autocomplete="off"
          autofocus
          <?= !$canWrite ? 'disabled' : '' ?>
        >
      </div>

      <div class="editor-actions">
        <?php if ($canWrite): ?>
          <button type="submit" id="saveBtn" class="btn primary">
            💾 Save Category
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

      <!-- Attached Subcategories Inspector (Visible during Edit) -->
      <div id="subcatInspector" class="subcategories-inspector" style="display: none;">
        <div class="subcategories-inspector-title">
          <span>Attached Subcategories</span>
          <span id="subcatCountBadge" class="subgenre-badge" style="font-size: 10px;">0</span>
        </div>
        <div id="subcatChipsContainer" class="subcategories-chips">
          <!-- Filled dynamically by selectCategory() -->
        </div>
      </div>
    </form>
  </aside>

  <!-- RIGHT PANE: Reusable <data-grid> Component -->
  <section class="grid-card">
    <data-grid 
      id="categoriesGrid" 
      page-size="25" 
      search-placeholder="Type to filter categories or IDs..."
    ></data-grid>
  </section>
</div>

<!-- Raw Initial Server Dataset -->
<script id="serverCategoriesData" type="application/json">
  <?= json_encode($categories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>

<script>
/**
 * Master-Detail Categories Controller Script
 */
let categoriesList = [];
let selectedId = null;
const canWrite = <?= json_encode($canWrite) ?>;

document.addEventListener('DOMContentLoaded', () => {
  // 1. Ingest initial server pre-rendered dataset
  try {
    const rawData = document.getElementById('serverCategoriesData').textContent;
    categoriesList = JSON.parse(rawData || '[]');
  } catch (err) {
    console.error('Failed to parse server categories dataset:', err);
    categoriesList = [];
  }

  // 2. Initialize the reusable <data-grid>
  const grid = document.getElementById('categoriesGrid');
  if (grid) {
    grid.columns = [
      {
        key: 'id',
        label: 'ID',
        sortable: true,
        width: '80px',
        render: (val) => `<span style="font-weight: 600; color: var(--text-dim);">#${escapeHtml(val)}</span>`
      },
      {
        key: 'name',
        label: 'Category',
        sortable: true,
        searchable: true,
        render: (val) => `<span style="font-weight: 600; color: var(--text-main); font-size: 13px;">${escapeHtml(val)}</span>`
      },
      {
        key: 'subcat_count',
        label: 'Subcategories',
        sortable: true,
        align: 'right',
        width: '180px',
        render: (val, row) => {
          const count = Number(val ?? (row && row.subcategories_count) ?? 0);
          const hasSubs = count > 0;
          const text = `${count} ${count === 1 ? 'sub-genre' : 'sub-genres'}`;
          return `<span class="subgenre-badge ${!hasSubs ? 'empty-use' : ''}">${text}</span>`;
        }
      },
      {
        key: 'games_count',
        label: 'Linked Games',
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

    grid.data = categoriesList;

    // 3. Row selection listener: populates the persistent left-hand form
    grid.addEventListener('row-click', (e) => {
      const row = e.detail.row;
      if (row && row.id !== undefined) {
        selectCategory(Number(row.id));
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
        document.getElementById('categoryForm').requestSubmit();
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
 * Populates persistent left editor with selected category record
 */
function selectCategory(id) {
  const record = categoriesList.find(c => Number(c.id) === Number(id));
  if (!record) return;

  selectedId = Number(record.id);
  document.getElementById('categoryId').value = String(record.id);
  document.getElementById('categoryName').value = record.name || '';
  document.getElementById('formModeTitle').textContent = 'EDIT CATEGORY';
  document.getElementById('activeIdBadge').textContent = `#${record.id}`;

  const deleteBtn = document.getElementById('deleteBtn');
  if (deleteBtn) {
    deleteBtn.disabled = false;
    const subcatCount = Number(record.subcat_count ?? record.subcategories_count ?? 0);
    const gameCount = Number(record.games_count ?? record.game_count ?? 0);

    if (subcatCount > 0 && gameCount > 0) {
      deleteBtn.title = `Cannot delete: Has ${subcatCount} subcategories and ${gameCount} games attached`;
    } else if (subcatCount > 0) {
      deleteBtn.title = `Cannot delete: Has ${subcatCount} subcategories attached`;
    } else if (gameCount > 0) {
      deleteBtn.title = `Cannot delete: Used by ${gameCount} game(s)`;
    } else {
      deleteBtn.title = `Delete ${record.name}`;
    }
  }

  // Update attached subcategories inspector
  const inspector = document.getElementById('subcatInspector');
  const countBadge = document.getElementById('subcatCountBadge');
  const chipsContainer = document.getElementById('subcatChipsContainer');

  if (inspector && chipsContainer) {
    const subcats = Array.isArray(record.subcategories) ? record.subcategories : [];
    if (subcats.length > 0) {
      countBadge.textContent = `${subcats.length} sub-genres`;
      chipsContainer.innerHTML = subcats.map(s => `<span class="subcat-chip">${escapeHtml(s)}</span>`).join('');
      inspector.style.display = 'block';
    } else {
      countBadge.textContent = '0';
      chipsContainer.innerHTML = '<span style="font-size: 11px; color: var(--text-dim); font-style: italic;">No subcategories assigned.</span>';
      inspector.style.display = 'block';
    }
  }

  syncRowHighlight();
  document.getElementById('categoryName').focus();
}

/**
 * Resets the persistent form back to "NEW CATEGORY" (Auto ID) state
 */
function resetForm() {
  selectedId = null;
  document.getElementById('categoryId').value = '';
  document.getElementById('categoryName').value = '';
  document.getElementById('formModeTitle').textContent = 'NEW CATEGORY';
  document.getElementById('activeIdBadge').textContent = '(Auto ID)';

  const deleteBtn = document.getElementById('deleteBtn');
  if (deleteBtn) {
    deleteBtn.disabled = true;
    deleteBtn.removeAttribute('title');
  }

  const inspector = document.getElementById('subcatInspector');
  if (inspector) {
    inspector.style.display = 'none';
  }

  syncRowHighlight();
  const input = document.getElementById('categoryName');
  if (input && !input.disabled) {
    input.focus();
  }
}

/**
 * Synchronizes table row selection highlight in <data-grid>
 */
function syncRowHighlight() {
  const grid = document.getElementById('categoriesGrid');
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

  const id = document.getElementById('categoryId').value;
  const name = document.getElementById('categoryName').value.trim();
  const saveBtn = document.getElementById('saveBtn');

  if (!name) return;

  saveBtn.disabled = true;
  saveBtn.textContent = '⏳ Saving...';

  try {
    const isUpdate = Boolean(id);
    const url = isUpdate ? `/categories/${id}/update` : '/categories/create';

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
      throw new Error(result.error || (result.data && result.data.error) || 'Failed to save category record.');
    }

    const payload = result.data || result;
    showToast(payload.message || 'Saved successfully.', 'success');

    // Reload latest dataset from API and re-select record
    await reloadGridData(isUpdate ? parseInt(id, 10) : payload.id);
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    saveBtn.disabled = false;
    saveBtn.textContent = '💾 Save Category';
  }
}

/**
 * Handles Record Deletion
 */
async function handleDelete() {
  if (!canWrite || !selectedId) return;

  const record = categoriesList.find(c => Number(c.id) === selectedId);
  if (!record) return;

  const subcatCount = Number(record.subcat_count ?? record.subcategories_count ?? 0);
  const gameCount = Number(record.games_count ?? record.game_count ?? 0);

  if (subcatCount > 0) {
    showToast(`Cannot delete: Has ${subcatCount} subcategories attached. Remove or reassign them first.`, 'error');
    return;
  }

  if (gameCount > 0) {
    showToast(`Cannot delete: Referenced by ${gameCount} game(s).`, 'error');
    return;
  }

  if (!confirm(`Are you sure you want to permanently delete category "${record.name}"?`)) {
    return;
  }

  const deleteBtn = document.getElementById('deleteBtn');
  deleteBtn.disabled = true;

  try {
    const res = await fetch(`/categories/${selectedId}/delete`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ id: selectedId })
    });

    const result = await res.json();
    if (!res.ok || !result.success) {
      throw new Error(result.error || (result.data && result.data.error) || 'Failed to delete category record.');
    }

    const payload = result.data || result;
    showToast(payload.message || 'Category deleted successfully.', 'success');
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
    const res = await fetch('/api/categories', {
      headers: { 'Accept': 'application/json' }
    });
    const json = await res.json();
    categoriesList = (json.data && json.data.categories) ? json.data.categories : (json.categories || []);

    const grid = document.getElementById('categoriesGrid');
    if (grid) {
      grid.data = categoriesList;
    }

    if (selectIdAfter) {
      selectCategory(selectIdAfter);
    }
  } catch (err) {
    console.error('Failed to reload categories dataset:', err);
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
