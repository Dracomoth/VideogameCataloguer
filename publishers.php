<?php
/**
 * publishers.php - Master-Detail Publisher Maintenance
 * 
 * Interacts asynchronously with /api/publishers.php.
 */
$pageTitle = 'Publishers Maintenance';$activeNav = 'publishers';

require_once __DIR__ . '/layout_header.php';
?>

<div class="workspace">
  <!-- LEFT PANE: Master-Detail Form Editor -->
  <aside class="editor-card">
    <div class="card-header">
      <span id="formModeTitle" class="card-title">New Publisher</span>
      <span id="activeIdBadge" class="badge-record">(Auto ID)</span>
    </div>

    <form id="publisherForm" onsubmit="handleSave(event)">
      <input type="hidden" id="pubId" value="">

      <div class="form-group">
        <label for="pubName">Publisher Name *</label>
        <input 
          type="text" 
          id="pubName" 
          class="form-control" 
          placeholder="e.g. Nintendo, Capcom, Konami..." 
          required 
          autocomplete="off"
          autofocus
        >
      </div>

      <div class="form-group" style="margin-top: 4px;">
        <label>Hardware Classification</label>
        <label class="chip-toggle" style="width: 100%;">
          <input type="checkbox" id="pubConsoleMaker" value="1">
          &#128377; Console / Hardware Maker
        </label>
      </div>

      <!-- Linked Hardware Preview Panel -->
      <div id="hardwareInspector" class="form-group" style="display: none; margin-top: 8px;">
        <label style="display: flex; justify-content: space-between; align-items: center;">
          <span>Consoles Manufactured</span>
          <a href="consoles.php" style="color: var(--border-focus); font-size: 11px; text-decoration: none;">Manage &rarr;</a>
        </label>
        <div id="hardwareChipsList" class="chip-group" style="margin-top: 4px;"></div>
      </div>

      <div class="editor-actions">
        <button type="submit" id="saveBtn" class="btn primary">
          &#128190; Save Publisher
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
        placeholder="Type to filter publishers by name, hardware maker, or ID..." 
        oninput="handleSearch(this.value)"
      >
      <span id="recordCountBadge" class="badge-count" style="margin-left: auto;">Loading...</span>
    </div>

    <div class="table-container">
      <table id="publishersTable">
        <thead>
          <tr>
            <th style="width: 70px;">ID</th>
            <th>Publisher</th>
            <th style="width: 140px; text-align: center;">Console Maker</th>
            <th style="width: 130px; text-align: center;">Consoles</th>
            <th style="width: 130px; text-align: right;">Linked Games</th>
          </tr>
        </thead>
        <tbody id="tableBody">
          <tr>
            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 32px;">
              Connecting to database...
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</div>

<script>
  let publishersList = [];
  let selectedId = null;

  document.addEventListener('DOMContentLoaded', () => {
    fetchPublishers();

    // Global keyboard shortcuts
    window.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        document.getElementById('publisherForm').requestSubmit();
      }
      if (e.key === 'Escape') {
        resetForm();
      }
    });
  });

  // Fetch all publishers with game & console telemetry
  async function fetchPublishers() {
    try {
      const res = await fetch('api/publishers.php', {
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error || 'Failed to fetch publishers.');

      publishersList = json.data;
      renderTable(getFilteredItems(document.getElementById('searchInput').value));
      document.getElementById('recordCountBadge').textContent = `${publishersList.length} Publishers`;

      if (selectedId) {
        selectPublisher(selectedId);
      }
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  // Render Table Rows
  function renderTable(items) {
    const tbody = document.getElementById('tableBody');
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-dim); padding: 32px;">No publishers match your search.</td></tr>';
      return;
    }

    tbody.innerHTML = items.map(item => `
      <tr class="${item.ID === selectedId ? 'active' : ''}" onclick="selectPublisher(${item.ID})">
        <td style="font-weight: 600; color: var(--text-dim);">#${item.ID}</td>
        <td style="font-weight: 600; color: var(--text-main);">${escapeHtml(item.Publisher)}</td>
        <td style="text-align: center;">
          ${item.is_console_maker 
            ? '<span class="status-badge won" style="font-size: 10px;">&#9881; Hardware Maker</span>' 
            : '<span style="color: var(--text-dim);">&mdash;</span>'}
        </td>
        <td style="text-align: center;">
          <span class="usage-badge ${item.console_count > 0 ? 'active-use' : ''}">
            ${item.console_count} ${item.console_count === 1 ? 'platform' : 'platforms'}
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

  // Populate editor form with clicked publisher
  function selectPublisher(id) {
    const record = publishersList.find(p => p.ID === id);
    if (!record) return;

    selectedId = id;
    document.getElementById('pubId').value = record.ID;
    document.getElementById('pubName').value = record.Publisher;
    document.getElementById('pubConsoleMaker').checked = record.is_console_maker === 1;
    document.getElementById('formModeTitle').textContent = 'Editing Publisher';
    document.getElementById('activeIdBadge').textContent = `#${record.ID}`;

    // Hardware inspector panel
    const inspector = document.getElementById('hardwareInspector');
    const chipsList = document.getElementById('hardwareChipsList');
    if (record.consoles && record.consoles.length > 0) {
      chipsList.innerHTML = record.consoles.map(cons => `
        <span class="usage-badge active-use" style="font-size: 11px; padding: 3px 8px;">&#128377; ${escapeHtml(cons)}</span>
      `).join('');
      inspector.style.display = 'flex';
    } else if (record.is_console_maker) {
      chipsList.innerHTML = '<span style="font-size: 11px; color: var(--text-dim);">No platforms registered yet</span>';
      inspector.style.display = 'flex';
    } else {
      inspector.style.display = 'none';
      chipsList.innerHTML = '';
    }

    // Safety checks for delete button
    const deleteBtn = document.getElementById('deleteBtn');
    deleteBtn.disabled = false;
    const isLocked = (record.game_count > 0 || record.console_count > 0);
    deleteBtn.title = isLocked
      ? `Cannot delete: Has ${record.console_count} console(s) and ${record.game_count} game(s) assigned`
      : `Delete ${record.Publisher}`;

    // Re-render table active row highlight
    document.querySelectorAll('#tableBody tr').forEach(tr => tr.classList.remove('active'));
    renderTable(getFilteredItems(document.getElementById('searchInput').value));
  }

  // Reset editor to "New Publisher" mode
  function resetForm() {
    selectedId = null;
    document.getElementById('pubId').value = '';
    document.getElementById('pubName').value = '';
    document.getElementById('pubConsoleMaker').checked = false;
    document.getElementById('formModeTitle').textContent = 'New Publisher';
    document.getElementById('activeIdBadge').textContent = '(Auto ID)';
    document.getElementById('deleteBtn').disabled = true;
    document.getElementById('deleteBtn').removeAttribute('title');
    document.getElementById('hardwareInspector').style.display = 'none';
    document.getElementById('hardwareChipsList').innerHTML = '';
    document.getElementById('pubName').focus();

    renderTable(getFilteredItems(document.getElementById('searchInput').value));
  }

  // Save changes (POST create or update)
  async function handleSave(e) {
    e.preventDefault();
    const id = document.getElementById('pubId').value;
    const name = document.getElementById('pubName').value.trim();
    const isMaker = document.getElementById('pubConsoleMaker').checked ? 1 : 0;
    const saveBtn = document.getElementById('saveBtn');

    if (!name) return;

    saveBtn.disabled = true;
    try {
      const res = await fetch('api/publishers.php', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          id: id ? parseInt(id, 10) : null,
          publisher: name,
          console_maker: isMaker
        })
      });
      const result = await res.json();

      if (!result.success) throw new Error(result.error);

      showToast(result.data.message, 'success');
      selectedId = result.data.id;
      await fetchPublishers();
    } catch (err) {
      showToast(err.message, 'error');
    } finally {
      saveBtn.disabled = false;
    }
  }

  // Delete current record
  async function handleDelete() {
    if (!selectedId) return;

    const record = publishersList.find(p => p.ID === selectedId);
    if (!record) return;

    if (record.game_count > 0 || record.console_count > 0) {
      showToast(`Cannot delete: Publisher has linked games or consoles.`, 'error');
      return;
    }

    if (!confirm(`Are you sure you want to permanently delete "${record.Publisher}"?`)) {
      return;
    }

    try {
      const res = await fetch('api/publishers.php', {
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
      await fetchPublishers();
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
    if (!term) return publishersList;
    return publishersList.filter(p => 
      p.Publisher.toLowerCase().includes(term) || 
      p.ID.toString().includes(term) ||
      (p.is_console_maker && 'maker hardware console'.includes(term))
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