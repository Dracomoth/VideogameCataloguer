<?php
/**
 * categories.php - Master-Detail Category Maintenance
 * 
 * Interacts asynchronously with /api/categories.php.
 */
$pageTitle = 'Categories Maintenance';$activeNav = 'categories';

require_once __DIR__ . '/layout_header.php';
?>

<div class="workspace">
  <!-- LEFT PANE: Master-Detail Form Editor -->
  <aside class="editor-card">
    <div class="card-header">
      <span id="formModeTitle" class="card-title">New Category</span>
      <span id="activeIdBadge" class="badge-record">(Auto ID)</span>
    </div>

    <form id="categoryForm" onsubmit="handleSave(event)">
      <input type="hidden" id="catId" value="">

      <div class="form-group">
        <label for="catName">Category Name *</label>
        <input 
          type="text" 
          id="catName" 
          class="form-control" 
          placeholder="e.g. Action, RPG, Simulation..." 
          required 
          autocomplete="off"
          autofocus
        >
      </div>

      <!-- Linked Subcategories Preview Panel -->
      <div id="subcatInspector" class="form-group" style="display: none; margin-top: 8px;">
        <label style="display: flex; justify-content: space-between; align-items: center;">
          <span>Linked Subcategories</span>
          <a href="subcategories.php" style="color: var(--border-focus); font-size: 11px; text-decoration: none;">Manage &rarr;</a>
        </label>
        <div id="subcatChipsList" class="chip-group" style="margin-top: 4px;"></div>
      </div>

      <div class="editor-actions">
        <button type="submit" id="saveBtn" class="btn primary">
          &#128190; Save Category
        </button>
        <div class="action-row">
          <button type="button" class="btn" onclick="resetForm()">
            + New
          </button>
          <button type="button" id="deleteBtn" class="btn danger" onclick="handleDelete()" disabled>
            Delete
          </button>
        </div>
      </div>
    </form>
  </aside>

  <!-- RIGHT PANE: Searchable Datasheet Grid -->
  <section class="grid-card">
    <div class="search-toolbar">
      <input 
        type="text" 
        id="searchInput" 
        class="form-control" 
        placeholder="Type to filter categories or IDs..." 
        oninput="handleSearch(this.value)"
      >
      <span id="recordCountBadge" class="badge-count" style="margin-left: auto;">Loading...</span>
    </div>

    <div class="table-container">
      <table id="categoriesTable">
        <thead>
          <tr>
            <th style="width: 70px;">ID</th>
            <th>Category</th>
            <th style="width: 140px; text-align: center;">Subcategories</th>
            <th style="width: 130px; text-align: right;">Linked Games</th>
          </tr>
        </thead>
        <tbody id="tableBody">
          <tr>
            <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 32px;">
              Connecting to database...
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</div>

<script>
  let categoriesList = [];
  let selectedId = null;

  document.addEventListener('DOMContentLoaded', () => {
    fetchCategories();

    // Global keyboard bindings
    window.addEventListener('keydown', (e) => {
      // Ctrl+S or Cmd+S to submit
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        document.getElementById('categoryForm').requestSubmit();
      }
      // Esc to reset editor to New mode
      if (e.key === 'Escape') {
        resetForm();
      }
    });
  });

  // Fetch all categories with subcategory & game telemetry
  async function fetchCategories() {
    try {
      const res = await fetch('api/categories.php', {
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error || 'Failed to fetch categories.');

      categoriesList = json.data;
      renderTable(getFilteredItems(document.getElementById('searchInput').value));
      document.getElementById('recordCountBadge').textContent = `${categoriesList.length} Categories`;

      if (selectedId) {
        selectCategory(selectedId);
      }
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  // Render Table Rows
  function renderTable(items) {
    const tbody = document.getElementById('tableBody');
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: var(--text-dim); padding: 32px;">No categories match your search.</td></tr>';
      return;
    }

    tbody.innerHTML = items.map(item => `
      <tr class="${item.ID === selectedId ? 'active' : ''}" onclick="selectCategory(${item.ID})">
        <td style="font-weight: 600; color: var(--text-dim);">#${item.ID}</td>
        <td style="font-weight: 600; color: var(--text-main);">${escapeHtml(item.Category)}</td>
        <td style="text-align: center;">
          <span class="usage-badge ${item.subcat_count > 0 ? 'active-use' : ''}">
            ${item.subcat_count} ${item.subcat_count === 1 ? 'sub-genre' : 'sub-genres'}
          </span>
        </td>
        <td style="text-align: right;">
          <span class="usage-badge ${item.game_count > 0 ? 'active-use' : ''}">
            ${item.game_count} ${item.game_count === 1 ? 'title' : 'titles'}
          </span>
        </td>
      </tr>
    `).join('');
  }

  // Populate editor form with clicked category
  function selectCategory(id) {
    const record = categoriesList.find(c => c.ID === id);
    if (!record) return;

    selectedId = id;
    document.getElementById('catId').value = record.ID;
    document.getElementById('catName').value = record.Category;
    document.getElementById('formModeTitle').textContent = 'Editing Category';
    document.getElementById('activeIdBadge').textContent = `#${record.ID}`;

    // Subcategory inspector panel
    const inspector = document.getElementById('subcatInspector');
    const chipsList = document.getElementById('subcatChipsList');
    if (record.subcategories && record.subcategories.length > 0) {
      chipsList.innerHTML = record.subcategories.map(sub => `
        <span class="usage-badge" style="font-size: 11px; padding: 3px 8px;">${escapeHtml(sub)}</span>
      `).join('');
      inspector.style.display = 'flex';
    } else {
      chipsList.innerHTML = '<span style="font-size: 11px; color: var(--text-dim);">No subcategories linked</span>';
      inspector.style.display = 'flex';
    }

    // Safety checks for delete button
    const deleteBtn = document.getElementById('deleteBtn');
    deleteBtn.disabled = false;
    const isLocked = (record.game_count > 0 || record.subcat_count > 0);
    deleteBtn.title = isLocked
      ? `Cannot delete: Has ${record.subcat_count} subcategories and ${record.game_count} games linked`
      : `Delete ${record.Category}`;

    // Re-render table active row highlight
    document.querySelectorAll('#tableBody tr').forEach(tr => tr.classList.remove('active'));
    renderTable(getFilteredItems(document.getElementById('searchInput').value));
  }

  // Reset editor to "New Category" mode
  function resetForm() {
    selectedId = null;
    document.getElementById('catId').value = '';
    document.getElementById('catName').value = '';
    document.getElementById('formModeTitle').textContent = 'New Category';
    document.getElementById('activeIdBadge').textContent = '(Auto ID)';
    document.getElementById('deleteBtn').disabled = true;
    document.getElementById('deleteBtn').removeAttribute('title');
    document.getElementById('subcatInspector').style.display = 'none';
    document.getElementById('subcatChipsList').innerHTML = '';
    document.getElementById('catName').focus();

    renderTable(getFilteredItems(document.getElementById('searchInput').value));
  }

  // Save changes (POST create or update)
  async function handleSave(e) {
    e.preventDefault();
    const id = document.getElementById('catId').value;
    const name = document.getElementById('catName').value.trim();
    const saveBtn = document.getElementById('saveBtn');

    if (!name) return;

    saveBtn.disabled = true;
    try {
      const res = await fetch('api/categories.php', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          id: id ? parseInt(id, 10) : null,
          category: name
        })
      });
      const result = await res.json();

      if (!result.success) throw new Error(result.error);

      showToast(result.data.message, 'success');
      selectedId = result.data.id;
      await fetchCategories();
    } catch (err) {
      showToast(err.message, 'error');
    } finally {
      saveBtn.disabled = false;
    }
  }

  // Delete current record
  async function handleDelete() {
    if (!selectedId) return;

    const record = categoriesList.find(c => c.ID === selectedId);
    if (!record) return;

    if (record.game_count > 0 || record.subcat_count > 0) {
      showToast(`Cannot delete: Category has linked subcategories or games.`, 'error');
      return;
    }

    if (!confirm(`Are you sure you want to permanently delete "${record.Category}"?`)) {
      return;
    }

    try {
      const res = await fetch('api/categories.php', {
        method: 'DELETE',
        headers: { 
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ id: selectedId })
      });
      const result = await res.json();

      if (!result.success) throw new Error(result.error);

      showToast(result.data.message, 'success');
      resetForm();
      await fetchCategories();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  // Instant filter
  function handleSearch(term) {
    renderTable(getFilteredItems(term));
  }

  function getFilteredItems(term) {
    term = term.toLowerCase().trim();
    if (!term) return categoriesList;
    return categoriesList.filter(c => 
      c.Category.toLowerCase().includes(term) || 
      c.ID.toString().includes(term)
    );
  }

  // Toast Notification
  function showToast(msg, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) {
      alert(msg);
      return;
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

  function escapeHtml(str) {
    return String(str).replace(/[&<>'"]/g, tag => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    }[tag] || tag));
  }
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>