<?php
/**
 * src/Views/subcategories.php
 * Taxonomy: Subcategories Master-Detail Maintenance Workbench.
 *
 * Implements a split two-column workbench:
 * - Left Pane: Persistent Form Editor (Always Visible) with Parent Category selector.
 * - Right Pane: Searchable & Category-filtered <data-grid> with linked title counts.
 *
 * Variables expected from SubcategoryController:
 * @var array<int, array<string, mixed>> $subcategories
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

/* Category Pill Badge */
.category-pill-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 9px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 600;
  background: rgba(56, 189, 248, 0.12);
  color: #38bdf8;
  border: 1px solid rgba(56, 189, 248, 0.25);
}
</style>

<div class="workspace">
  <!-- LEFT PANE: Persistent Form Editor (Always Visible) -->
  <aside class="editor-card">
    <div class="card-header">
      <span id="formModeTitle" class="card-title">NEW SUBCATEGORY</span>
      <span id="activeIdBadge" class="badge-record">(Auto ID)</span>
    </div>

    <form id="subcategoryForm" onsubmit="handleSave(event)">
      <input type="hidden" id="subcategoryId" value="">

      <div class="form-group">
        <label for="parentCategory">Parent Category *</label>
        <select 
          id="parentCategory" 
          class="form-control" 
          required 
          <?= !$canWrite ? 'disabled' : '' ?>
        >
          <option value="">-- Choose Category --</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>">
              <?= htmlspecialchars((string)$cat['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="subcatName">Subcategory Name *</label>
        <input 
          type="text" 
          id="subcatName" 
          class="form-control" 
          placeholder="e.g. Action RPG, Shoot 'Em Up..." 
          required 
          autocomplete="off"
          <?= !$canWrite ? 'disabled' : '' ?>
        >
      </div>

      <div class="editor-actions">
        <?php if ($canWrite): ?>
          <button type="submit" id="saveBtn" class="btn primary">
            💾 Save Subcategory
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

  <!-- RIGHT PANE: Reusable <data-grid> Component with Category Filter -->
  <section class="grid-card">
    <data-grid 
      id="subcategoriesGrid" 
      page-size="25" 
      search-placeholder="Type to filter subcategories..."
    ></data-grid>
  </section>
</div>

<!-- Raw Initial Server Datasets -->
<script id="serverSubcategoriesData" type="application/json">
  <?= json_encode($subcategories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>

<script id="serverCategoriesData" type="application/json">
  <?= json_encode($categories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>

<script>
/**
 * Master-Detail Subcategories Controller Script
 */
let subcategoriesList = [];
let categoriesList = [];
let selectedId = null;
const canWrite = <?= json_encode($canWrite) ?>;

document.addEventListener('DOMContentLoaded', () => {
  // 1. Ingest initial server pre-rendered datasets
  try {
    const rawSubs = document.getElementById('serverSubcategoriesData').textContent;
    subcategoriesList = JSON.parse(rawSubs || '[]');
  } catch (err) {
    console.error('Failed to parse subcategories dataset:', err);
    subcategoriesList = [];
  }

  try {
    const rawCats = document.getElementById('serverCategoriesData').textContent;
    categoriesList = JSON.parse(rawCats || '[]');
  } catch (err) {
    console.error('Failed to parse categories dataset:', err);
    categoriesList = [];
  }

  // 2. Initialize the reusable <data-grid>
  const grid = document.getElementById('subcategoriesGrid');
  if (grid) {
    // Add custom Category filter dropdown to grid toolbar
    grid.customFilters = [
      {
        key: 'category_id',
        label: '',
        allLabel: 'All Categories',
        options: categoriesList.map(c => ({
          value: String(c.id),
          label: c.name
        }))
      }
    ];

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
        label: 'Subcategory',
        sortable: true,
        searchable: true,
        render: (val) => `<span style="font-weight: 600; color: var(--text-main); font-size: 13px;">${escapeHtml(val)}</span>`
      },
      {
        key: 'category_name',
        label: 'Category',
        sortable: true,
        align: 'left',
        width: '160px',
        render: (val) => `<span class="category-pill-badge">${escapeHtml(val || 'Uncategorized')}</span>`
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

    grid.data = subcategoriesList;

    // 3. Row selection listener: populates the persistent left-hand form
    grid.addEventListener('row-click', (e) => {
      const row = e.detail.row;
      if (row && row.id !== undefined) {
        selectSubcategory(Number(row.id));
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
        document.getElementById('subcategoryForm').requestSubmit();
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
 * Populates persistent left editor with selected subcategory record
 */
function selectSubcategory(id) {
  const record = subcategoriesList.find(s => Number(s.id) === Number(id));
  if (!record) return;

  selectedId = Number(record.id);
  document.getElementById('subcategoryId').value = String(record.id);
  document.getElementById('parentCategory').value = String(record.category_id || '');
  document.getElementById('subcatName').value = record.name || '';
  document.getElementById('formModeTitle').textContent = 'EDIT SUBCATEGORY';
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
  document.getElementById('subcatName').focus();
}

/**
 * Resets the persistent form back to "NEW SUBCATEGORY" (Auto ID) state
 */
function resetForm() {
  selectedId = null;
  document.getElementById('subcategoryId').value = '';
  document.getElementById('parentCategory').value = '';
  document.getElementById('subcatName').value = '';
  document.getElementById('formModeTitle').textContent = 'NEW SUBCATEGORY';
  document.getElementById('activeIdBadge').textContent = '(Auto ID)';

  const deleteBtn = document.getElementById('deleteBtn');
  if (deleteBtn) {
    deleteBtn.disabled = true;
    deleteBtn.removeAttribute('title');
  }

  syncRowHighlight();
  const input = document.getElementById('subcatName');
  if (input && !input.disabled) {
    input.focus();
  }
}

/**
 * Synchronizes table row selection highlight in <data-grid>
 */
function syncRowHighlight() {
  const grid = document.getElementById('subcategoriesGrid');
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

  const id = document.getElementById('subcategoryId').value;
  const categoryId = document.getElementById('parentCategory').value;
  const name = document.getElementById('subcatName').value.trim();
  const saveBtn = document.getElementById('saveBtn');

  if (!categoryId) {
    showToast('Please select a parent category.', 'error');
    document.getElementById('parentCategory').focus();
    return;
  }

  if (!name) {
    showToast('Subcategory name is required.', 'error');
    document.getElementById('subcatName').focus();
    return;
  }

  saveBtn.disabled = true;
  saveBtn.textContent = '⏳ Saving...';

  try {
    const isUpdate = Boolean(id);
    const url = isUpdate ? `/subcategories/${id}/update` : '/subcategories/create';

    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        id: isUpdate ? parseInt(id, 10) : null,
        category_id: parseInt(categoryId, 10),
        name: name
      })
    });

    const result = await res.json();
    if (!res.ok || !result.success) {
      throw new Error(result.error || (result.data && result.data.error) || 'Failed to save subcategory record.');
    }

    const payload = result.data || result;
    showToast(payload.message || 'Saved successfully.', 'success');

    // Reload latest dataset from API and re-select record
    await reloadGridData(isUpdate ? parseInt(id, 10) : payload.id);
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    saveBtn.disabled = false;
    saveBtn.textContent = '💾 Save Subcategory';
  }
}

/**
 * Handles Record Deletion
 */
async function handleDelete() {
  if (!canWrite || !selectedId) return;

  const record = subcategoriesList.find(s => Number(s.id) === selectedId);
  if (!record) return;

  const gameCount = Number(record.games_count ?? record.game_count ?? 0);
  if (gameCount > 0) {
    showToast(`Cannot delete: Referenced by ${gameCount} game(s).`, 'error');
    return;
  }

  if (!confirm(`Are you sure you want to permanently delete subcategory "${record.name}"?`)) {
    return;
  }

  const deleteBtn = document.getElementById('deleteBtn');
  deleteBtn.disabled = true;

  try {
    const res = await fetch(`/subcategories/${selectedId}/delete`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ id: selectedId })
    });

    const result = await res.json();
    if (!res.ok || !result.success) {
      throw new Error(result.error || (result.data && result.data.error) || 'Failed to delete subcategory record.');
    }

    const payload = result.data || result;
    showToast(payload.message || 'Subcategory deleted successfully.', 'success');
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
    const res = await fetch('/api/subcategories', {
      headers: { 'Accept': 'application/json' }
    });
    const json = await res.json();
    subcategoriesList = (json.data && json.data.subcategories) ? json.data.subcategories : (json.subcategories || []);

    const grid = document.getElementById('subcategoriesGrid');
    if (grid) {
      grid.data = subcategoriesList;
    }

    if (selectIdAfter) {
      selectSubcategory(selectIdAfter);
    }
  } catch (err) {
    console.error('Failed to reload subcategories dataset:', err);
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
