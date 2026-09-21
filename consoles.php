<?php
/**
 * consoles.php - Master-Detail Console Maintenance & Asset Manager
 * 
 * Communicates asynchronously with /api/consoles.php.
 */
$pageTitle = 'Consoles Maintenance';$activeNav = 'consoles';

require_once __DIR__ . '/layout_header.php';
?>

<div class="workspace" style="grid-template-columns: 460px 1fr;">
  <!-- LEFT PANE: Master-Detail Form Editor -->
  <aside class="editor-card">
    <div class="card-header">
      <span id="formModeTitle" class="card-title">New Console</span>
      <span id="activeIdBadge" class="badge-record">(Auto ID)</span>
    </div>

    <form id="consoleForm" onsubmit="handleSave(event)">
      <input type="hidden" id="consoleId" name="id" value="">
      <input type="hidden" id="deleteImage" name="delete_image" value="0">
      <input type="hidden" id="deleteLogo" name="delete_logo" value="0">

      <!-- Section 1: Core Specifications -->
      <div class="form-group">
        <label for="consoleName">Console Name *</label>
        <input 
          type="text" 
          id="consoleName" 
          name="console" 
          class="form-control" 
          placeholder="e.g. Nintendo Game Boy, Sega Genesis..." 
          required 
          autocomplete="off" 
          autofocus
        >
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label for="publisherId">Manufacturer / Maker</label>
          <select id="publisherId" name="publisher_id" class="form-control">
            <option value="">-- Select Maker --</option>
          </select>
        </div>

        <div class="form-group">
          <label for="releaseYear">Release Year</label>
          <input type="text" id="releaseYear" name="year" class="form-control" placeholder="e.g. 1989">
        </div>
      </div>

      <div class="form-group">
        <label for="generation">Hardware Generation</label>
        <input type="text" id="generation" name="generation" class="form-control" placeholder="e.g. 4th Gen, 16-bit">
      </div>

      <!-- Section 2: Hardware Type Flags -->
      <div class="form-section-title">Hardware Type & Flags</div>
      <div class="chip-group">
        <label class="chip-toggle">
          <input type="checkbox" id="isHandheld" name="is_handheld" value="1">
          &#128377; Handheld / Portable
        </label>
        <label class="chip-toggle">
          <input type="checkbox" id="isComputer" name="is_computer" value="1">
          &#128187; Microcomputer
        </label>
        <label class="chip-toggle">
          <input type="checkbox" id="isArcade" name="is_arcade" value="1">
          &#128126; Arcade Board
        </label>
        <label class="chip-toggle">
          <input type="checkbox" id="isForReference" name="is_for_reference" value="1">
          &#128204; Reference Only
        </label>
      </div>

      <!-- Section 3: Photo & Logo Asset Dropzones -->
      <div class="form-section-title">Visual Assets (Photo & Logo)</div>
      
      <div class="form-grid-2">
        <!-- Photo Dropzone -->
        <div class="media-card" style="padding: 10px;">
          <div class="media-card-title" style="font-size: 11px;">
            <span>Hardware Photo</span>
          </div>

          <input type="file" id="imageFileInput" name="image_file" class="hidden-file-input" accept="image/*" onchange="previewFile(this, 'photoPreviewContainer')">

          <div class="dropzone" id="photoDropzone" style="min-height: 140px; height: 140px;" onclick="document.getElementById('imageFileInput').click()">
            <div id="photoPreviewContainer" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
              <div class="dropzone-empty">
                <span>&#128247;</span>
                <p><strong>Upload Photo</strong></p>
                <small>Drop or click</small>
              </div>
            </div>
          </div>

          <div class="media-actions" style="margin-top: 4px;">
            <button type="button" class="btn btn-sm" onclick="document.getElementById('imageFileInput').click()">Browse</button>
            <button type="button" id="deletePhotoBtn" class="btn btn-sm danger" onclick="markAssetDelete('image')" disabled>Remove</button>
          </div>
        </div>

        <!-- Logo Dropzone -->
        <div class="media-card" style="padding: 10px;">
          <div class="media-card-title" style="font-size: 11px;">
            <span>Brand Logo</span>
          </div>

          <input type="file" id="logoFileInput" name="logo_file" class="hidden-file-input" accept="image/*" onchange="previewFile(this, 'logoPreviewContainer')">

          <div class="dropzone" id="logoDropzone" style="min-height: 140px; height: 140px; background: #070c16;" onclick="document.getElementById('logoFileInput').click()">
            <div id="logoPreviewContainer" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
              <div class="dropzone-empty">
                <span>&#128444;</span>
                <p><strong>Upload Logo</strong></p>
                <small>Drop or click</small>
              </div>
            </div>
          </div>

          <div class="media-actions" style="margin-top: 4px;">
            <button type="button" class="btn btn-sm" onclick="document.getElementById('logoFileInput').click()">Browse</button>
            <button type="button" id="deleteLogoBtn" class="btn btn-sm danger" onclick="markAssetDelete('logo')" disabled>Remove</button>
          </div>
        </div>
      </div>

      <!-- Section 4: Emulation Specs (Collapsible) -->
      <details style="margin-top: 6px; background: rgba(0,0,0,0.18); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 8px 12px;">
        <summary style="font-size: 12px; font-weight: 600; color: var(--text-muted); cursor: pointer; user-select: none;">
          Emulation & Technical Links &#9662;
        </summary>
        <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 8px;">
          <div class="form-grid-2">
            <div class="form-group">
              <label for="retroArchCore">RetroArch Core</label>
              <input type="text" id="retroArchCore" name="retroarch_core" class="form-control" placeholder="e.g. mgba">
            </div>
            <div class="form-group">
              <label for="coreLink">Core URL</label>
              <input type="url" id="coreLink" name="core_link" class="form-control" placeholder="https://...">
            </div>
          </div>
          <div class="form-grid-2">
            <div class="form-group">
              <label for="emulator">Desktop Emulator</label>
              <input type="text" id="emulator" name="emulator" class="form-control" placeholder="e.g. mGBA, PCSX2">
            </div>
            <div class="form-group">
              <label for="emulatorLink">Desktop URL</label>
              <input type="url" id="emulatorLink" name="emulator_link" class="form-control" placeholder="https://...">
            </div>
          </div>
          <div class="form-grid-2">
            <div class="form-group">
              <label for="emulatorAndroid">Android Emulator</label>
              <input type="text" id="emulatorAndroid" name="emulator_android" class="form-control" placeholder="e.g. Pizza Boy">
            </div>
            <div class="form-group">
              <label for="emulatorAndroidLink">Android URL</label>
              <input type="url" id="emulatorAndroidLink" name="emulator_android_link" class="form-control" placeholder="https://...">
            </div>
          </div>
        </div>
      </details>

      <!-- Section 5: Comments -->
      <div class="form-group" style="margin-top: 4px;">
        <label for="comments">Personal Notes / Specs</label>
        <textarea id="comments" name="comments" class="form-control" placeholder="BIOS requirements, serial numbers, region notes..."></textarea>
      </div>

      <!-- Action Buttons -->
      <div class="editor-actions">
        <button type="submit" id="saveBtn" class="btn primary">&#128190; Save Console</button>
        <div class="action-row">
          <button type="button" class="btn" onclick="resetForm()">+ New</button>
          <button type="button" id="deleteBtn" class="btn danger" onclick="handleDelete()" disabled>Delete</button>
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
        placeholder="Type to filter consoles by title, maker, year, or gen..." 
        oninput="handleSearch()"
      >

      <select id="filterTypeSelect" class="form-control" style="flex: 1; min-width: 140px;" onchange="handleSearch()">
        <option value="">All Form Factors</option>
        <option value="handheld">Portable Handhelds</option>
        <option value="computer">Microcomputers</option>
        <option value="arcade">Arcade Hardware</option>
      </select>

      <span id="recordCountBadge" class="badge-count" style="margin-left: auto;">Loading...</span>
    </div>

    <div class="table-container">
      <table id="consolesTable">
        <thead>
          <tr>
            <th style="width: 60px;">ID</th>
            <th>Console</th>
            <th>Maker</th>
            <th style="width: 70px;">Year</th>
            <th style="width: 80px;">Gen</th>
            <th style="width: 120px; text-align: right;">Library</th>
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
  </section>
</div>

<script>
  let consolesList = [];
  let makersList = [];
  let selectedId = null;

  document.addEventListener('DOMContentLoaded', () => {
    fetchConsoles();
    setupDropZones();

    // Global keyboard bindings
    window.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        document.getElementById('consoleForm').requestSubmit();
      }
      if (e.key === 'Escape') resetForm();
    });
  });

  // Load consoles dataset and maker lookups
  /*async function fetchConsoles() {
    try {
      const res = await fetch('api/consoles.php', { headers: { 'Accept': 'application/json' } });
      const json = await res.json();
      if (!json.success) throw new Error(json.error || 'Failed to fetch consoles.');

      consolesList = json.data.consoles;
      makersList   = json.data.makers;

      populateMakerDropdown();
      renderTable(getFilteredItems());

      if (selectedId) {
        selectConsole(selectedId);
      }
    } catch (err) {
      showToast(err.message, 'error');
    }
  }*/
  
  async function fetchConsoles() {
    try {
      const res = await fetch('api/consoles.php', { 
        headers: { 'Accept': 'application/json' } 
      });

      const rawText = await res.text();

      if (!rawText || rawText.trim() === '') {
        alert("CRITICAL: Server returned an EMPTY response.\n\nHTTP Status: " + res.status + " " + res.statusText + "\n\nThis means api/consoles.php or db.php encountered a fatal PHP error before outputting anything.");
        return;
      }

      let json;
      try {
        json = JSON.parse(rawText);
      } catch (parseErr) {
        alert("SERVER RETURNED NON-JSON:\n\n" + rawText);
        return;
      }

      if (!json.success) {
        throw new Error(json.error || 'Failed to fetch consoles.');
      }

      consolesList = json.data.consoles;
      makersList   = json.data.makers;

      populateMakerDropdown();
      renderTable(getFilteredItems());

      if (selectedId) {
        selectConsole(selectedId);
      }
    } catch (err) {
      alert("Error: " + err.message);
      showToast(err.message, 'error');
    }
  }

  function populateMakerDropdown() {
    const select = document.getElementById('publisherId');
    const current = select.value;
    select.innerHTML = '<option value="">-- Select Maker --</option>';

    makersList.forEach(m => {
      const opt = document.createElement('option');
      opt.value = m.ID;
      opt.textContent = m.Publisher;
      select.appendChild(opt);
    });

    if (current) select.value = current;
  }

  // Render Table Rows (Streamlined text-first datasheet)
  function renderTable(items) {
    const tbody = document.getElementById('tableBody');
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-dim); padding: 32px;">No consoles match your search.</td></tr>';
      document.getElementById('recordCountBadge').textContent = '0 Records';
      return;
    }

    tbody.innerHTML = items.map(c => `
      <tr class="${c.ID === selectedId ? 'active' : ''}" onclick="selectConsole(${c.ID})">
        <td style="font-weight: 600; color: var(--text-dim);">#${c.ID}</td>
        <td style="font-weight: 600; color: var(--text-main);">
          ${escapeHtml(c.Console)}
          ${c.IsHandheld ? '<span class="tag handheld" style="margin-left: 6px; font-size: 9px; padding: 1px 4px;">Portable</span>' : ''}
        </td>
        <td>${escapeHtml(c.maker_name || '—')}</td>
        <td>${escapeHtml(c.Year || '—')}</td>
        <td><span style="font-size: 11px; color: var(--text-muted);">${escapeHtml(c.Generation || '—')}</span></td>
        <td style="text-align: right;">
          <span class="usage-badge ${c.game_count > 0 ? 'active-use' : ''}">
            ${c.game_count} ${c.game_count === 1 ? 'title' : 'titles'}
          </span>
        </td>
      </tr>
    `).join('');

    document.getElementById('recordCountBadge').textContent = `${items.length} of ${consolesList.length} Consoles`;
  }

  // Populate editor form with clicked console
  function selectConsole(id) {
    const c = consolesList.find(item => item.ID === id);
    if (!c) return;

    selectedId = id;
    document.getElementById('consoleId').value = c.ID;
    document.getElementById('consoleName').value = c.Console;
    document.getElementById('publisherId').value = c['Publisher ID'] || '';
    document.getElementById('releaseYear').value = c.Year || '';
    document.getElementById('generation').value = c.Generation || '';

    // Checkboxes
    document.getElementById('isHandheld').checked = c.IsHandheld === 1;
    document.getElementById('isComputer').checked = c.IsComputer === 1;
    document.getElementById('isArcade').checked = c.IsArcade === 1;
    document.getElementById('isForReference').checked = c.IsForReference === 1;

    // Emulation & Notes
    document.getElementById('retroArchCore').value = c.RetroArchCore || '';
    document.getElementById('coreLink').value = c['Core Link'] || '';
    document.getElementById('emulator').value = c.Emulator || '';
    document.getElementById('emulatorLink').value = c['Emulator Link'] || '';
    document.getElementById('emulatorAndroid').value = c.EmulatorAndroid || '';
    document.getElementById('emulatorAndroidLink').value = c['EmulatorAndroid Link'] || '';
    document.getElementById('comments').value = c.Comments || '';

    // Visual Previews (Loaded in editor pane)
    document.getElementById('deleteImage').value = '0';
    document.getElementById('deleteLogo').value = '0';
    document.getElementById('imageFileInput').value = '';
    document.getElementById('logoFileInput').value = '';

    const photoCont = document.getElementById('photoPreviewContainer');
    if (c.image_url) {
      photoCont.innerHTML = `<img src="${c.image_url}" alt="Console Photo" style="max-width: 100%; max-height: 130px; object-fit: contain;">`;
      document.getElementById('deletePhotoBtn').disabled = false;
    } else {
      photoCont.innerHTML = `<div class="dropzone-empty"><span>&#128247;</span><p>No Photo</p><small>Drop or click</small></div>`;
      document.getElementById('deletePhotoBtn').disabled = true;
    }

    const logoCont = document.getElementById('logoPreviewContainer');
    if (c.logo_url) {
      logoCont.innerHTML = `<img src="${c.logo_url}" alt="Console Logo" style="max-width: 100%; max-height: 130px; object-fit: contain;">`;
      document.getElementById('deleteLogoBtn').disabled = false;
    } else {
      logoCont.innerHTML = `<div class="dropzone-empty"><span>&#128444;</span><p>No Logo</p><small>Drop or click</small></div>`;
      document.getElementById('deleteLogoBtn').disabled = true;
    }

    document.getElementById('formModeTitle').textContent = 'Editing Console';
    document.getElementById('activeIdBadge').textContent = `#${c.ID}`;

    // Safety checks for delete button
    const deleteBtn = document.getElementById('deleteBtn');
    deleteBtn.disabled = false;
    deleteBtn.title = c.game_count > 0 
      ? `Cannot delete: Has ${c.game_count} games assigned` 
      : `Delete ${c.Console}`;

    // Re-highlight active row
    document.querySelectorAll('#tableBody tr').forEach(tr => tr.classList.remove('active'));
    renderTable(getFilteredItems());
  }

  // Reset editor to "New Console" mode
  function resetForm() {
    selectedId = null;
    document.getElementById('consoleForm').reset();
    document.getElementById('consoleId').value = '';
    document.getElementById('publisherId').value = '';
    document.getElementById('deleteImage').value = '0';
    document.getElementById('deleteLogo').value = '0';

    document.getElementById('photoPreviewContainer').innerHTML = `
      <div class="dropzone-empty"><span>&#128247;</span><p><strong>Upload Photo</strong></p><small>Drop or click</small></div>
    `;
    document.getElementById('logoPreviewContainer').innerHTML = `
      <div class="dropzone-empty"><span>&#128444;</span><p><strong>Upload Logo</strong></p><small>Drop or click</small></div>
    `;

    document.getElementById('deletePhotoBtn').disabled = true;
    document.getElementById('deleteLogoBtn').disabled = true;
    document.getElementById('formModeTitle').textContent = 'New Console';
    document.getElementById('activeIdBadge').textContent = '(Auto ID)';
    document.getElementById('deleteBtn').disabled = true;
    document.getElementById('deleteBtn').removeAttribute('title');

    document.getElementById('consoleName').focus();
    renderTable(getFilteredItems());
  }

  // File Preview Handler
  function previewFile(input, containerId) {
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = (e) => {
        document.getElementById(containerId).innerHTML = `
          <img src="${e.target.result}" style="max-width: 100%; max-height: 130px; object-fit: contain;">
        `;
        if (containerId === 'photoPreviewContainer') {
          document.getElementById('deletePhotoBtn').disabled = false;
          document.getElementById('deleteImage').value = '0';
        } else {
          document.getElementById('deleteLogoBtn').disabled = false;
          document.getElementById('deleteLogo').value = '0';
        }
      };
      reader.readAsDataURL(input.files[0]);
    }
  }

  // Mark Image or Logo for Deletion on Edit
  function markAssetDelete(type) {
    if (confirm(`Remove this console ${type}? The file will be permanently deleted upon saving.`)) {
      if (type === 'image') {
        document.getElementById('deleteImage').value = '1';
        document.getElementById('imageFileInput').value = '';
        document.getElementById('photoPreviewContainer').innerHTML = `
          <div class="dropzone-empty" style="color: var(--danger);">
            <span>&#10005;</span>
            <p>Photo Marked for Deletion</p>
            <small>Will be removed on Save</small>
          </div>
        `;
        document.getElementById('deletePhotoBtn').disabled = true;
      } else {
        document.getElementById('deleteLogo').value = '1';
        document.getElementById('logoFileInput').value = '';
        document.getElementById('logoPreviewContainer').innerHTML = `
          <div class="dropzone-empty" style="color: var(--danger);">
            <span>&#10005;</span>
            <p>Logo Marked for Deletion</p>
            <small>Will be removed on Save</small>
          </div>
        `;
        document.getElementById('deleteLogoBtn').disabled = true;
      }
    }
  }

  // Setup Drag & Drop Handlers
  function setupDropZones() {
    [['photoDropzone', 'imageFileInput', 'photoPreviewContainer', 'deleteImage'],
     ['logoDropzone', 'logoFileInput', 'logoPreviewContainer', 'deleteLogo']].forEach(([dzId, inputId, contId, delId]) => {
      const dz = document.getElementById(dzId);
      const fi = document.getElementById(inputId);

      ['dragenter', 'dragover'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.add('dragover'); }));
      ['dragleave', 'drop'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.remove('dragover'); }));

      dz.addEventListener('drop', ev => {
        if (ev.dataTransfer.files && ev.dataTransfer.files[0]) {
          fi.files = ev.dataTransfer.files;
          previewFile(fi, contId);
          document.getElementById(delId).value = '0';
        }
      });
    });

    // Paste Image from Clipboard (Ctrl+V)
    window.addEventListener('paste', (e) => {
      const items = (e.clipboardData || window.clipboardData).items;
      for (let item of items) {
        if (item.type.indexOf('image') !== -1) {
          const file = item.getAsFile();
          const target = prompt("Image pasted from clipboard! Choose target:\n1 = Hardware Photo\n2 = Brand Logo", "1");
          const dt = new DataTransfer();
          dt.items.add(file);

          if (target === '1') {
            const input = document.getElementById('imageFileInput');
            input.files = dt.files;
            previewFile(input, 'photoPreviewContainer');
          } else if (target === '2') {
            const input = document.getElementById('logoFileInput');
            input.files = dt.files;
            previewFile(input, 'logoPreviewContainer');
          }
          break;
        }
      }
    });
  }

  // Save Console (Create or Update with FormData Multipart)
  async function handleSave(e) {
    e.preventDefault();
    const form = document.getElementById('consoleForm');
    const saveBtn = document.getElementById('saveBtn');
    const name = document.getElementById('consoleName').value.trim();

    if (!name) return;

    saveBtn.disabled = true;
    const formData = new FormData(form);

    try {
      const res = await fetch('api/consoles.php', {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: formData
      });
      const result = await res.json();

      if (!result.success) throw new Error(result.error);

      showToast(result.data.message, 'success');
      selectedId = result.data.id;
      await fetchConsoles();
    } catch (err) {
      showToast(err.message, 'error');
    } finally {
      saveBtn.disabled = false;
    }
  }

  // Delete Console
  async function handleDelete() {
    if (!selectedId) return;

    const record = consolesList.find(c => c.ID === selectedId);
    if (!record) return;

    if (record.game_count > 0) {
      showToast(`Cannot delete: Console is referenced by ${record.game_count} game(s).`, 'error');
      return;
    }

    if (!confirm(`Permanently delete "${record.Console}" and all its uploaded image files?`)) {
      return;
    }

    try {
      const res = await fetch('api/consoles.php', {
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
      await fetchConsoles();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  // Live Multi-Facet Filtering
  function handleSearch() {
    renderTable(getFilteredItems());
  }

  function getFilteredItems() {
    const term = document.getElementById('searchInput').value.toLowerCase().trim();
    const typeFilter = document.getElementById('filterTypeSelect').value;

    return consolesList.filter(c => {
      let matchesType = true;
      if (typeFilter === 'handheld') matchesType = c.IsHandheld === 1;
      if (typeFilter === 'computer') matchesType = c.IsComputer === 1;
      if (typeFilter === 'arcade')   matchesType = c.IsArcade === 1;

      const matchesTerm = !term ||
        c.Console.toLowerCase().includes(term) ||
        (c.maker_name && c.maker_name.toLowerCase().includes(term)) ||
        (c.Year && c.Year.toLowerCase().includes(term)) ||
        (c.Generation && c.Generation.toLowerCase().includes(term)) ||
        c.ID.toString().includes(term);

      return matchesType && matchesTerm;
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