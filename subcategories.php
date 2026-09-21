<?php
/**
 * subcategories.php - Master-Detail Subcategories Maintenance
 * 
 * Communicates asynchronously with /api/subcategories.php.
 */
$pageTitle = 'Subcategories Maintenance';$activeNav = 'subcategories';

require_once __DIR__ . '/layout_header.php';
?>

<div class="workspace">
  <!-- LEFT PANE: Master-Detail Form Editor -->
  <aside class="editor-card">
    <div class="card-header">
      <span id="formModeTitle" class="card-title">New Subcategory</span>
      <span id="activeIdBadge" class="badge-record">(Auto ID)</span>
    </div>

    <form id="subcategoryForm" onsubmit="handleSave(event)">
      <input type="hidden" id="subId" value="">

      <div class="form-group">
        <label for="parentCategoryId">Parent Category *</label>
        <select id="parentCategoryId" class="form-control" required autofocus>
          <option value="">-- Choose Category --</option>
        </select>
      </div>

      <div class="form-group">
        <label for="subName">Subcategory Name *</label>
        <input 
          type="text" 
          id="subName" 
          class="form-control" 
          placeholder="e.g. Action RPG, Shoot 'Em Up..." 
          required 
          autocomplete="off"
        >
      </div>

      <div class="editor-actions">
        <button type="submit" id="saveBtn" class="btn primary">
          &#128190; Save Subcategory
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

  <!-- RIGHT PANE: Searchable & Filterable Datasheet Grid -->
  <section class="grid-card">
    <div class="search-toolbar">
      <input 
        type="text" 
        id="searchInput" 
        class="form-control" 
        style="flex: 2; min-width: 180px;"
        placeholder="Type to filter subcategories..." 
        oninput="handleSearch()"
      >

      <!-- Category Filter Dropdown -->
      <select 
        id="filterCategorySelect" 
        class="form-control" 
        style="flex: 1; min-width: 160px;"
        onchange="handleSearch()"
      >
        <option value="">All Categories</option>
      </select>

      <span id="recordCountBadge" class="badge-count" style="margin-left: auto;">Loading...</span>
    </div>

    <div class="table-container">
      <table id="subcategoriesTable">
        <thead>
          <tr>
            <th style="width: 70px;">ID</th>
            <th>Subcategory</th>
            <th style="width: 180px;">Category</th>
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
  let subcategoriesList = [];
  let categoriesList = [];
  let selectedId = null;

  document.addEventListener('DOMContentLoaded', () => {
    fetchData();

    // Global keyboard shortcuts
    window.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        document.getElementById('subcategoryForm').requestSubmit();
      }
      if (e.key === 'Escape') {
        resetForm();
      }
    });
  });

  // Fetch subcategories and parent categories
  async function fetchData() {
    try {
      const res = await fetch('api/subcategories.php', {
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error || 'Failed to fetch subcategories.');

      subcategoriesList = json.data.subcategories;
      categoriesList    = json.data.categories;

      populateCategoryDropdowns();
      renderTable(getFilteredItems());

      if (selectedId) {
        selectSubcategory(selectedId);
      }
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  // Populate category options in both editor form and toolbar filter
  function populateCategoryDropdowns() {
    const formSelect = document.getElementById('parentCategoryId');
    const currentVal = formSelect.value;
    formSelect.innerHTML = '<option value="">-- Choose Category --</option>';

    const filterSelect = document.getElementById('filterCategorySelect');
    const currentFilterVal = filterSelect.value;
    filterSelect.innerHTML = '<option value="">All Categories</option>';

    categoriesList.forEach(c => {
      // Editor dropdown
      const opt = document.createElement('option');
      opt.value = c.ID;
      opt.textContent = c.Category;
      formSelect.appendChild(opt);

      // Filter toolbar dropdown
      const filterOpt = document.createElement('option');
      filterOpt.value = c.ID;
      filterOpt.textContent = c.Category;
      filterSelect.appendChild(filterOpt);
    });

    if (currentVal) formSelect.value = currentVal;
    if (currentFilterVal) filterSelect.value = currentFilterVal;
  }

  // Render Table Rows
  function renderTable(items) {
    const tbody = document.getElementById('tableBody');
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: var(--text-dim); padding: 32px;">No subcategories match your criteria.</td></tr>';
      document.getElementById('recordCountBadge').textContent = '0 Records';
      return;
    }

    tbody.innerHTML = items.map(item => `
      <tr class="${item.ID === selectedId ? 'active' : ''}" onclick="selectSubcategory(${item.ID})">
        <td style="font-weight: 600; color: var(--text-dim);">#${item.ID}</td>
        <td style="font-weight: 600; color: var(--text-main);">${escapeHtml(item.subcategory)}</td>
        <td>
          <span class="usage-badge" style="background: rgba(56, 189, 248, 0.08); color: var(--border-focus); border: 1px solid rgba(56, 189, 248, 0.2);">
            ${escapeHtml(item.category_name)}
          </span>
        </td>
        <td style="text-align: right;">
          <span class="usage-badge ${item.game_count > 0 ? 'active-use' : ''}">
            ${item.game_count} ${item.game_count === 1 ? 'title' : 'titles'}
          </span>
        </td>
      </tr>
    `).join('');

    document.getElementById('recordCountBadge').textContent = `${items.length} of ${subcategoriesList.length} Records`;
  }

  // Populate editor form with clicked subcategory
  function selectSubcategory(id) {
    const record = subcategoriesList.find(s => s.ID === id);
    if (!record) return;

    selectedId = id;
    document.getElementById('subId').value = record.ID;
    document.getElementById('subName').value = record.subcategory;
    document.getElementById('parentCategoryId').value = record.category_id;
    document.getElementById('formModeTitle').textContent = 'Editing Subcategory';
    document.getElementById('activeIdBadge').textContent = `#${record.ID}`;

    // Safety checks for delete button
    const deleteBtn = document.getElementById('deleteBtn');
    deleteBtn.disabled = false;
    deleteBtn.title = record.game_count > 0
      ? `Cannot delete: Assigned to ${record.game_count} game(s)`
      : `Delete ${record.subcategory}`;

    // Re-highlight active row
    document.querySelectorAll('#tableBody tr').forEach(tr => tr.classList.remove('active'));
    renderTable(getFilteredItems());
  }

  // Reset editor to "New Subcategory" mode (CLEARS ALL INPUTS & RESTORES FOCUS)
  function resetForm() {
    selectedId = null;
    document.getElementById('subId').value = '';
    document.getElementById('subName').value = '';
    document.getElementById('parentCategoryId').value = ''; // Clean reset to "-- Choose Category --"

    document.getElementById('formModeTitle').textContent = 'New Subcategory';
    document.getElementById('activeIdBadge').textContent = '(Auto ID)';
    document.getElementById('deleteBtn').disabled = true;
    document.getElementById('deleteBtn').removeAttribute('title');
    
    // Set focus to the first logical form control
    document.getElementById('parentCategoryId').focus();

    renderTable(getFilteredItems());
  }

  // Save changes (POST create or update)
  async function handleSave(e) {
    e.preventDefault();
    const id       = document.getElementById('subId').value;
    const name     = document.getElementById('subName').value.trim();
    const catId    = document.getElementById('parentCategoryId').value;
    const saveBtn  = document.getElementById('saveBtn');

    if (!name || !catId) return;

    saveBtn.disabled = true;
    try {
      const res = await fetch('api/subcategories.php', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          id: id ? parseInt(id, 10) : null,
          subcategory: name,
          category_id: parseInt(catId, 10)
        })
      });
      const result = await res.json();

      if (!result.success) throw new Error(result.error);

      showToast(result.data.message, 'success');
      selectedId = result.data.id;
      await fetchData();
    } catch (err) {
      showToast(err.message, 'error');
    } finally {
      saveBtn.disabled = false;
    }
  }

  // Delete active record
  async function handleDelete() {
    if (!selectedId) return;

    const record = subcategoriesList.find(s => s.ID === selectedId);
    if (!record) return;

    if (record.game_count > 0) {
      showToast(`Cannot delete: Subcategory is assigned to ${record.game_count} game(s).`, 'error');
      return;
    }

    if (!confirm(`Are you sure you want to permanently delete "${record.subcategory}"?`)) {
      return;
    }

    try {
      const res = await fetch('api/subcategories.php', {
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
      resetForm(); // Completely clears form fields, including category dropdown
      await fetchData();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  // Instant multi-facet filter (search term + parent category)
  function handleSearch() {
    renderTable(getFilteredItems());
  }

  function getFilteredItems() {
    const term = document.getElementById('searchInput').value.toLowerCase().trim();
    const filterCat = document.getElementById('filterCategorySelect').value;

    return subcategoriesList.filter(s => {
      const matchesCategory = !filterCat || s.category_id === parseInt(filterCat, 10);
      const matchesSearch = !term || 
        s.subcategory.toLowerCase().includes(term) || 
        s.category_name.toLowerCase().includes(term) ||
        s.ID.toString().includes(term);

      return matchesCategory && matchesSearch;
    });
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