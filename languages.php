<?php
/**
 * languages.php - Master-Detail Language Maintenance
 * 
 * Rendered inside the master layout framework.
 * Communicates asynchronously with /api/languages.php.
 */
$pageTitle = 'Languages Maintenance';$activeNav = 'languages';

require_once __DIR__ . '/layout_header.php';
?>

<div class="workspace">
  <!-- LEFT PANE: Master-Detail Form Editor -->
  <aside class="editor-card">
    <div class="card-header">
      <span id="formModeTitle" class="card-title">New Language</span>
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
        >
      </div>

      <div class="editor-actions">
        <button type="submit" id="saveBtn" class="btn primary">
          &#128190; Save Language
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
        placeholder="Type to filter languages instantly..." 
        oninput="handleSearch(this.value)"
      >
      <span id="recordCountBadge" class="badge-count" style="margin-left: auto;">Loading...</span>
    </div>

    <div class="table-container">
      <table id="languagesTable">
        <thead>
          <tr>
            <th style="width: 70px;">ID</th>
            <th>Language</th>
            <th style="width: 150px; text-align: right;">Linked Titles</th>
          </tr>
        </thead>
        <tbody id="tableBody">
          <tr>
            <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 32px;">
              Connecting to database...
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</div>

<script>
  let languagesList = [];
  let selectedId = null;

  document.addEventListener('DOMContentLoaded', () => {
    fetchLanguages();

    // Global shortcut bindings
    window.addEventListener('keydown', (e) => {
      // Ctrl+S or Cmd+S to submit
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        document.getElementById('languageForm').requestSubmit();
      }
      // Esc to clear editor and enter New mode
      if (e.key === 'Escape') {
        resetForm();
      }
    });
  });

  // Load all languages & game usage telemetry via API
  async function fetchLanguages() {
    try {
      const res = await fetch('api/languages.php', {
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error || 'Failed to fetch languages data.');

      languagesList = json.data;
      renderTable(getFilteredItems(document.getElementById('searchInput').value));
      document.getElementById('recordCountBadge').textContent = `${languagesList.length} Records`;

      if (selectedId) {
        selectLanguage(selectedId);
      }
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  // Render Table Rows with usage counts
  function renderTable(items) {
    const tbody = document.getElementById('tableBody');
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--text-dim); padding: 32px;">No languages match your search.</td></tr>';
      return;
    }

    tbody.innerHTML = items.map(item => `
      <tr class="${item.ID === selectedId ? 'active' : ''}" onclick="selectLanguage(${item.ID})">
        <td style="font-weight: 600; color: var(--text-dim);">#${item.ID}</td>
        <td style="font-weight: 600; color: var(--text-main);">${escapeHtml(item.Language)}</td>
        <td style="text-align: right;">
          <span class="usage-badge ${item.game_count > 0 ? 'active-use' : ''}">
            ${item.game_count} ${item.game_count === 1 ? 'title' : 'titles'}
          </span>
        </td>
      </tr>
    `).join('');
  }

  // Populate form with clicked record
  function selectLanguage(id) {
    const record = languagesList.find(l => l.ID === id);
    if (!record) return;

    selectedId = id;
    document.getElementById('langId').value = record.ID;
    document.getElementById('langName').value = record.Language;
    document.getElementById('formModeTitle').textContent = 'Editing Language';
    document.getElementById('activeIdBadge').textContent = `#${record.ID}`;
    
    // Enable delete only if not referenced by existing titles
    const deleteBtn = document.getElementById('deleteBtn');
    deleteBtn.disabled = false;
    deleteBtn.title = record.game_count > 0 
      ? `Cannot delete: Used by ${record.game_count} game(s)` 
      : `Delete ${record.Language}`;

    // Highlight row
    document.querySelectorAll('#tableBody tr').forEach(tr => tr.classList.remove('active'));
    renderTable(getFilteredItems(document.getElementById('searchInput').value));
  }

  // Reset form to New Record state
  function resetForm() {
    selectedId = null;
    document.getElementById('langId').value = '';
    document.getElementById('langName').value = '';
    document.getElementById('formModeTitle').textContent = 'New Language';
    document.getElementById('activeIdBadge').textContent = '(Auto ID)';
    document.getElementById('deleteBtn').disabled = true;
    document.getElementById('deleteBtn').removeAttribute('title');
    document.getElementById('langName').focus();

    renderTable(getFilteredItems(document.getElementById('searchInput').value));
  }

  // Save changes (POST create or update)
  async function handleSave(e) {
    e.preventDefault();
    const id = document.getElementById('langId').value;
    const name = document.getElementById('langName').value.trim();
    const saveBtn = document.getElementById('saveBtn');

    if (!name) return;

    saveBtn.disabled = true;
    try {
      const res = await fetch('api/languages.php', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          id: id ? parseInt(id, 10) : null,
          language: name
        })
      });
      const result = await res.json();

      if (!result.success) throw new Error(result.error);

      showToast(result.data.message, 'success');
      selectedId = result.data.id;
      await fetchLanguages();
    } catch (err) {
      showToast(err.message, 'error');
    } finally {
      saveBtn.disabled = false;
    }
  }

  // Delete current record
  async function handleDelete() {
    if (!selectedId) return;

    const record = languagesList.find(l => l.ID === selectedId);
    if (!record) return;

    if (record.game_count > 0) {
      showToast(`Cannot delete: Referenced by ${record.game_count} game(s).`, 'error');
      return;
    }

    if (!confirm(`Are you sure you want to permanently delete "${record.Language}"?`)) {
      return;
    }

    try {
      const res = await fetch('api/languages.php', {
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
      await fetchLanguages();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  // Search filter
  function handleSearch(term) {
    renderTable(getFilteredItems(term));
  }

  function getFilteredItems(term) {
    term = term.toLowerCase().trim();
    if (!term) return languagesList;
    return languagesList.filter(l => 
      l.Language.toLowerCase().includes(term) || 
      l.ID.toString().includes(term)
    );
  }

  // Toast Notification Trigger
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