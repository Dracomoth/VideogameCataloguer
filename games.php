<?php
/**
 * games.php - Master-Detail Game Cataloguer & Asset Hub
 * (Cleaned: NoCover and NoScreen removed)
 */
$pageTitle = 'Games Cataloguer';$activeNav = 'games';

require_once __DIR__ . '/layout_header.php';
?>

<div class="workspace" style="grid-template-columns: 520px 1fr;">
  <!-- LEFT PANE: Master-Detail Game Editor -->
  <aside class="editor-card">
    <div class="card-header">
      <span id="formModeTitle" class="card-title">New Game Entry</span>
      <span id="activeIdBadge" class="badge-record">(Auto ID)</span>
    </div>

    <form id="gameForm" onsubmit="handleSave(event)">
      <input type="hidden" id="gameId" name="id" value="">
      <input type="hidden" id="deleteBoxArt" name="delete_boxart" value="0">
      <input type="hidden" id="deleteScreen" name="delete_screen" value="0">

      <!-- Section 1: Title & Platform -->
      <div class="form-group">
        <label for="gameTitle">Game Title *</label>
        <input 
          type="text" 
          id="gameTitle" 
          name="game" 
          class="form-control" 
          placeholder="e.g. Super Mario World, Chrono Trigger..." 
          required 
          autocomplete="off" 
          autofocus
        >
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label for="consoleId">Platform / Console *</label>
          <select id="consoleId" name="console_id" class="form-control" required>
            <option value="">-- Choose Console --</option>
          </select>
        </div>

        <div class="form-group">
          <label for="releaseYear">Release Year</label>
          <input type="number" id="releaseYear" name="year" class="form-control" placeholder="YYYY">
        </div>
      </div>

      <!-- Section 2: Taxonomy & Classification -->
      <div class="form-grid-2">
        <div class="form-group">
          <label for="categoryId">Category</label>
          <select id="categoryId" name="category_id" class="form-control" onchange="filterSubcategorySelect(this.value, null)">
            <option value="">-- Choose Category --</option>
          </select>
        </div>

        <div class="form-group">
          <label for="subcategoryId">Subcategory</label>
          <select id="subcategoryId" name="subcategory_id" class="form-control">
            <option value="">-- Choose Subcategory --</option>
          </select>
        </div>
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label for="publisherId">Publisher</label>
          <select id="publisherId" name="publisher_id" class="form-control">
            <option value="">-- Choose Publisher --</option>
          </select>
        </div>

        <div class="form-group">
          <label for="languageId">Language</label>
          <select id="languageId" name="language_id" class="form-control">
            <option value="">-- Choose Language --</option>
          </select>
        </div>
      </div>

      <!-- Section 3: Collection Status Toggles -->
      <div class="form-section-title">Collection & Play Status</div>
      <div class="chip-group">
        <label class="chip-toggle">
          <input type="checkbox" id="inCollection" name="in_collection" value="1">
          &#128230; In Collection
        </label>
        <label class="chip-toggle">
          <input type="checkbox" id="playedStatus" name="played" value="1">
          &#127918; Played
        </label>
        <label class="chip-toggle">
          <input type="checkbox" id="wonStatus" name="won" value="1">
          &#127942; Cleared / Won
        </label>
      </div>

      <!-- Section 4: Visual Asset Dropzones -->
      <div class="form-section-title">Visual Media (Cover & Screenshot)</div>
      <div class="form-grid-2">
        <!-- Box Art Dropzone -->
        <div class="media-card" style="padding: 10px;">
          <div class="media-card-title" style="font-size: 11px;">
            <span>Box Art Cover</span>
          </div>

          <input type="file" id="boxArtFileInput" name="boxart_file" class="hidden-file-input" accept="image/*" onchange="previewMedia(this, 'boxArtContainer')">

          <div class="dropzone" id="boxArtDropzone" style="min-height: 150px; height: 150px;" onclick="document.getElementById('boxArtFileInput').click()">
            <div id="boxArtContainer" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
              <div class="dropzone-empty">
                <span>&#128444;</span>
                <p><strong>Upload Box Art</strong></p>
                <small>&lt;ID&gt;_Box.ext</small>
              </div>
            </div>
          </div>

          <div class="media-actions" style="margin-top: 4px;">
            <button type="button" class="btn btn-sm" onclick="document.getElementById('boxArtFileInput').click()">Browse</button>
            <button type="button" id="deleteBoxArtBtn" class="btn btn-sm danger" onclick="markAssetDelete('boxart')" disabled>Remove</button>
          </div>
        </div>

        <!-- In-Game Screenshot Dropzone -->
        <div class="media-card" style="padding: 10px;">
          <div class="media-card-title" style="font-size: 11px;">
            <span>Game Screenshot</span>
          </div>

          <input type="file" id="screenFileInput" name="screen_file" class="hidden-file-input" accept="image/*" onchange="previewMedia(this, 'screenContainer')">

          <div class="dropzone" id="screenDropzone" style="min-height: 150px; height: 150px; background: #070c16;" onclick="document.getElementById('screenFileInput').click()">
            <div id="screenContainer" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
              <div class="dropzone-empty">
                <span>&#128247;</span>
                <p><strong>Upload Screenshot</strong></p>
                <small>&lt;ID&gt;_Img.ext</small>
              </div>
            </div>
          </div>

          <div class="media-actions" style="margin-top: 4px;">
            <button type="button" class="btn btn-sm" onclick="document.getElementById('screenFileInput').click()">Browse</button>
            <button type="button" id="deleteScreenBtn" class="btn btn-sm danger" onclick="markAssetDelete('screen')" disabled>Remove</button>
          </div>
        </div>
      </div>

      <!-- Section 5: Tags & Comments -->
      <div class="form-group" style="margin-top: 4px;">
        <label for="tags">Tags (Comma-separated)</label>
        <input type="text" id="tags" name="tags" class="form-control" placeholder="e.g. 2-player, handheld-favorite, metroidvania">
      </div>

      <div class="form-group">
        <label for="comments">Personal Notes / Comments</label>
        <textarea id="comments" name="comments" class="form-control" placeholder="Condition, cartridge location, save battery status..."></textarea>
      </div>

      <!-- Action Buttons -->
      <div class="editor-actions">
        <button type="submit" id="saveBtn" class="btn primary">&#128190; Save Game</button>
        <div class="action-row">
          <button type="button" class="btn" onclick="resetForm()">+ New</button>
          <button type="button" id="deleteBtn" class="btn danger" onclick="handleDelete()" disabled>Delete</button>
        </div>
      </div>
    </form>
  </aside>

  <!-- RIGHT PANE: High-Density Datasheet Grid -->
  <section class="grid-card">
    <div class="search-toolbar">
      <input 
        type="text" 
        id="searchInput" 
        class="form-control" 
        style="flex: 2; min-width: 170px;"
        placeholder="Search games by title, tags, comments..." 
        oninput="debounceSearch()"
      >

      <select id="filterConsoleSelect" class="form-control" style="flex: 1; min-width: 130px;" onchange="fetchGames(1)">
        <option value="">All Consoles</option>
      </select>

      <select id="filterCategorySelect" class="form-control" style="flex: 1; min-width: 130px;" onchange="fetchGames(1)">
        <option value="">All Categories</option>
      </select>

      <span id="recordCountBadge" class="badge-count" style="margin-left: auto;">Loading...</span>
    </div>

    <div class="table-container" style="max-height: calc(100vh - 280px);">
      <table id="gamesTable">
        <thead>
          <tr>
            <th style="width: 55px;">ID</th>
            <th>Title</th>
            <th>Platform</th>
            <th>Genre</th>
            <th style="width: 60px;">Year</th>
            <th style="width: 100px; text-align: center;">Collection</th>
          </tr>
        </thead>
        <tbody id="tableBody">
          <tr>
            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 32px;">
              Connecting to database...
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Server-Side Pagination Bar -->
    <div class="pagination-footer">
      <div style="font-size: 12px; color: var(--text-muted);">
        Page <span id="currentPageNum">1</span> of <span id="totalPagesNum">1</span>
      </div>
      <div class="pagination-nav">
        <button type="button" class="page-btn" id="prevPageBtn" onclick="changePage(-1)">&larr; Prev</button>
        <button type="button" class="page-btn" id="nextPageBtn" onclick="changePage(1)">Next &rarr;</button>
      </div>
    </div>
  </section>
</div>

<script>
  let gamesList     = [];
  let lookups       = {};
  let selectedId    = null;
  let currentPage   = 1;
  let totalPages    = 1;
  let searchTimer   = null;

  document.addEventListener('DOMContentLoaded', () => {
    fetchGames(1);
    setupDropZones();

    window.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        document.getElementById('gameForm').requestSubmit();
      }
      if (e.key === 'Escape') resetForm();
    });
  });

  function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      fetchGames(1);
    }, 280);
  }

  async function fetchGames(page = 1) {
    currentPage = page;
    const q          = document.getElementById('searchInput').value.trim();
    const consoleId  = document.getElementById('filterConsoleSelect').value;
    const categoryId = document.getElementById('filterCategorySelect').value;

    const queryParams = new URLSearchParams({
      page: currentPage,
      limit: 25,
      q: q,
      console_id: consoleId,
      category_id: categoryId
    });

    try {
      const res = await fetch(`api/games.php?${queryParams.toString()}`, {
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error || 'Failed to fetch games data.');

      gamesList   = json.data.games;
      totalPages  = json.data.total_pages;
      lookups     = json.data.lookups;

      populateLookups();
      renderTable(gamesList);
      updatePaginationControls(json.data.total, json.data.page, totalPages);

      if (selectedId) {
        selectGame(selectedId);
      }
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  function populateLookups() {
    const consoleSelect = document.getElementById('consoleId');
    const filterConsole = document.getElementById('filterConsoleSelect');
    if (consoleSelect.children.length <= 1) {
      lookups.consoles.forEach(c => {
        const opt = new Option(c.Console + (c.IsHandheld ? ' (Portable)' : ''), c.ID);
        consoleSelect.add(opt);
        filterConsole.add(new Option(c.Console, c.ID));
      });
    }

    const catSelect = document.getElementById('categoryId');
    const filterCat = document.getElementById('filterCategorySelect');
    if (catSelect.children.length <= 1) {
      lookups.categories.forEach(cat => {
        catSelect.add(new Option(cat.Category, cat.ID));
        filterCat.add(new Option(cat.Category, cat.ID));
      });
    }

    const pubSelect = document.getElementById('publisherId');
    if (pubSelect.children.length <= 1) {
      lookups.publishers.forEach(p => pubSelect.add(new Option(p.Publisher, p.ID)));
    }

    const langSelect = document.getElementById('languageId');
    if (langSelect.children.length <= 1) {
      lookups.languages.forEach(l => langSelect.add(new Option(l.Language, l.ID)));
    }
  }

  function filterSubcategorySelect(categoryId, selectedSubId) {
    const subSelect = document.getElementById('subcategoryId');
    subSelect.innerHTML = '<option value="">-- Choose Subcategory --</option>';

    if (!categoryId || !lookups.subcategories) return;

    const filtered = lookups.subcategories.filter(s => s.category_id == categoryId);
    filtered.forEach(s => {
      const opt = new Option(s.subcategory, s.ID);
      if (selectedSubId && s.ID == selectedSubId) opt.selected = true;
      subSelect.add(opt);
    });
  }

  function renderTable(items) {
    const tbody = document.getElementById('tableBody');
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-dim); padding: 32px;">No game records found.</td></tr>';
      return;
    }

    tbody.innerHTML = items.map(g => `
      <tr class="${g.ID === selectedId ? 'active' : ''}" onclick="selectGame(${g.ID})">
        <td style="font-weight: 600; color: var(--text-dim);">#${g.ID}</td>
        <td style="font-weight: 600; color: var(--text-main);">${escapeHtml(g.Game)}</td>
        <td>${escapeHtml(g.console_name || '—')}</td>
        <td>${escapeHtml(g.category_name || '—')}</td>
        <td>${escapeHtml(g.Year || '—')}</td>
        <td style="text-align: center;">
          ${g.Won 
            ? '<span class="status-badge won">&#9733; Won</span>' 
            : (g.InCollection 
                ? (g.Played ? '<span class="status-badge" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8;">Played</span>' : '<span class="status-badge backlog">Backlog</span>') 
                : '<span style="color: var(--text-dim); font-size: 11px;">&mdash;</span>')}
        </td>
      </tr>
    `).join('');
  }

  function selectGame(id) {
    const g = gamesList.find(item => item.ID === id);
    if (!g) return;

    selectedId = id;
    document.getElementById('gameId').value          = g.ID;
    document.getElementById('gameTitle').value       = g.Game;
    document.getElementById('consoleId').value       = g['Console ID'] || '';
    document.getElementById('releaseYear').value     = g.Year || '';
    document.getElementById('publisherId').value     = g['Publisher ID'] || '';
    document.getElementById('languageId').value      = g['Language ID'] || '';
    document.getElementById('tags').value            = g.Tags || '';
    document.getElementById('comments').value        = g.Comments || '';

    // Checkboxes
    document.getElementById('inCollection').checked  = g.InCollection === 1;
    document.getElementById('playedStatus').checked  = g.Played === 1;
    document.getElementById('wonStatus').checked     = g.Won === 1;

    // Cascading Category & Subcategory
    document.getElementById('categoryId').value      = g['Category ID'] || '';
    filterSubcategorySelect(g['Category ID'], g['Subcategory ID']);

    // Reset upload inputs
    document.getElementById('deleteBoxArt').value    = '0';
    document.getElementById('deleteScreen').value    = '0';
    document.getElementById('boxArtFileInput').value = '';
    document.getElementById('screenFileInput').value = '';

    // Box Art Preview
    const boxContainer = document.getElementById('boxArtContainer');
    if (g.boxart_url) {
      boxContainer.innerHTML = `<img src="${g.boxart_url}" alt="Cover" style="max-width: 100%; max-height: 140px; object-fit: contain;">`;
      document.getElementById('deleteBoxArtBtn').disabled = false;
    } else {
      boxContainer.innerHTML = `<div class="dropzone-empty"><span>&#128444;</span><p>No Cover</p><small>&lt;ID&gt;_Box.ext</small></div>`;
      document.getElementById('deleteBoxArtBtn').disabled = true;
    }

    // Screenshot Preview
    const screenContainer = document.getElementById('screenContainer');
    if (g.screen_url) {
      screenContainer.innerHTML = `<img src="${g.screen_url}" alt="Screenshot" style="max-width: 100%; max-height: 140px; object-fit: contain;">`;
      document.getElementById('deleteScreenBtn').disabled = false;
    } else {
      screenContainer.innerHTML = `<div class="dropzone-empty"><span>&#128247;</span><p>No Screenshot</p><small>&lt;ID&gt;_Img.ext</small></div>`;
      document.getElementById('deleteScreenBtn').disabled = true;
    }

    document.getElementById('formModeTitle').textContent = 'Editing Game';
    document.getElementById('activeIdBadge').textContent = `#${g.ID}`;
    document.getElementById('deleteBtn').disabled = false;

    document.querySelectorAll('#tableBody tr').forEach(tr => tr.classList.remove('active'));
    renderTable(gamesList);
  }

  function resetForm() {
    selectedId = null;
    document.getElementById('gameForm').reset();
    document.getElementById('gameId').value          = '';
    document.getElementById('deleteBoxArt').value    = '0';
    document.getElementById('deleteScreen').value    = '0';
    document.getElementById('categoryId').value      = '';
    document.getElementById('consoleId').value       = '';
    document.getElementById('publisherId').value     = '';
    document.getElementById('languageId').value      = '';
    document.getElementById('subcategoryId').innerHTML = '<option value="">-- Choose Subcategory --</option>';

    document.getElementById('boxArtContainer').innerHTML = `
      <div class="dropzone-empty"><span>&#128444;</span><p><strong>Upload Box Art</strong></p><small>&lt;ID&gt;_Box.ext</small></div>
    `;
    document.getElementById('screenContainer').innerHTML = `
      <div class="dropzone-empty"><span>&#128247;</span><p><strong>Upload Screenshot</strong></p><small>&lt;ID&gt;_Img.ext</small></div>
    `;

    document.getElementById('deleteBoxArtBtn').disabled = true;
    document.getElementById('deleteScreenBtn').disabled = true;
    document.getElementById('formModeTitle').textContent = 'New Game Entry';
    document.getElementById('activeIdBadge').textContent = '(Auto ID)';
    document.getElementById('deleteBtn').disabled = true;

    document.getElementById('gameTitle').focus();
    renderTable(gamesList);
  }

  function previewMedia(input, containerId) {
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = (e) => {
        document.getElementById(containerId).innerHTML = `
          <img src="${e.target.result}" style="max-width: 100%; max-height: 140px; object-fit: contain;">
        `;
        if (containerId === 'boxArtContainer') {
          document.getElementById('deleteBoxArtBtn').disabled = false;
          document.getElementById('deleteBoxArt').value = '0';
        } else {
          document.getElementById('deleteScreenBtn').disabled = false;
          document.getElementById('deleteScreen').value = '0';
        }
      };
      reader.readAsDataURL(input.files[0]);
    }
  }

  function markAssetDelete(type) {
    if (confirm(`Remove this ${type === 'boxart' ? 'box art cover' : 'screenshot'}? The file will be removed from the server upon saving.`)) {
      if (type === 'boxart') {
        document.getElementById('deleteBoxArt').value = '1';
        document.getElementById('boxArtFileInput').value = '';
        document.getElementById('boxArtContainer').innerHTML = `
          <div class="dropzone-empty" style="color: var(--danger);">
            <span>&#10005;</span>
            <p>Cover Marked for Deletion</p>
            <small>Will be removed on Save</small>
          </div>
        `;
        document.getElementById('deleteBoxArtBtn').disabled = true;
      } else {
        document.getElementById('deleteScreen').value = '1';
        document.getElementById('screenFileInput').value = '';
        document.getElementById('screenContainer').innerHTML = `
          <div class="dropzone-empty" style="color: var(--danger);">
            <span>&#10005;</span>
            <p>Screenshot Marked for Deletion</p>
            <small>Will be removed on Save</small>
          </div>
        `;
        document.getElementById('deleteScreenBtn').disabled = true;
      }
    }
  }

  function setupDropZones() {
    [['boxArtDropzone', 'boxArtFileInput', 'boxArtContainer', 'deleteBoxArt'],
     ['screenDropzone', 'screenFileInput', 'screenContainer', 'deleteScreen']].forEach(([dzId, inputId, contId, delId]) => {
      const dz = document.getElementById(dzId);
      const fi = document.getElementById(inputId);

      ['dragenter', 'dragover'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.add('dragover'); }));
      ['dragleave', 'drop'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.remove('dragover'); }));

      dz.addEventListener('drop', ev => {
        if (ev.dataTransfer.files && ev.dataTransfer.files[0]) {
          fi.files = ev.dataTransfer.files;
          previewMedia(fi, contId);
          document.getElementById(delId).value = '0';
        }
      });
    });

    window.addEventListener('paste', (e) => {
      const items = (e.clipboardData || window.clipboardData).items;
      for (let item of items) {
        if (item.type.indexOf('image') !== -1) {
          const file   = item.getAsFile();
          const target = prompt("Image pasted from clipboard! Choose target:\n1 = Box Art Cover\n2 = Screenshot", "1");
          const dt     = new DataTransfer();
          dt.items.add(file);

          if (target === '1') {
            const input = document.getElementById('boxArtFileInput');
            input.files = dt.files;
            previewMedia(input, 'boxArtContainer');
          } else if (target === '2') {
            const input = document.getElementById('screenFileInput');
            input.files = dt.files;
            previewMedia(input, 'screenContainer');
          }
          break;
        }
      }
    });
  }

  async function handleSave(e) {
    e.preventDefault();
    const form    = document.getElementById('gameForm');
    const saveBtn = document.getElementById('saveBtn');
    const title   = document.getElementById('gameTitle').value.trim();

    if (!title) return;

    saveBtn.disabled = true;
    const formData = new FormData(form);

    try {
      const res = await fetch('api/games.php', {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: formData
      });
      const result = await res.json();

      if (!result.success) throw new Error(result.error);

      showToast(result.data.message, 'success');
      selectedId = result.data.id;
      await fetchGames(currentPage);
    } catch (err) {
      showToast(err.message, 'error');
    } finally {
      saveBtn.disabled = false;
    }
  }

  async function handleDelete() {
    if (!selectedId) return;

    const record = gamesList.find(g => g.ID === selectedId);
    if (!record) return;

    if (!confirm(`Permanently delete "${record.Game}" and its uploaded cover & screenshot files?`)) {
      return;
    }

    try {
      const res = await fetch('api/games.php', {
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
      await fetchGames(currentPage);
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  function changePage(direction) {
    const targetPage = currentPage + direction;
    if (targetPage >= 1 && targetPage <= totalPages) {
      fetchGames(targetPage);
    }
  }

  function updatePaginationControls(total, page, totalPages) {
    document.getElementById('recordCountBadge').textContent = `${Number(total).toLocaleString()} Titles`;
    document.getElementById('currentPageNum').textContent   = page;
    document.getElementById('totalPagesNum').textContent    = totalPages;

    document.getElementById('prevPageBtn').disabled = (page <= 1);
    document.getElementById('nextPageBtn').disabled = (page >= totalPages);
  }

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