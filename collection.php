<?php
/**
 * collection.php - Player Hub & Library Browser
 * 
 * Interacts asynchronously with /api/collection.php.
 */
$pageTitle = 'Player Hub';$activeNav = 'collection';

require_once __DIR__ . '/layout_header.php';
?>

<div class="wrapper" style="margin-bottom: 24px;">
  <!-- 1. Top Action Toolbar -->
  <div class="toolbar">
    <button type="button" class="btn" onclick="resetSelectorFilters()" title="Reset Filters">&#8634; Reset</button>
    <button type="button" class="btn" onclick="toggleSearch()" title="Find by Title">&#128269; Search</button>
    <button 
      type="button" 
      id="pickForMeBtn" 
      class="btn" 
      style="color: var(--warning); cursor: pointer;" 
      disabled 
      onclick="pickRandomGame()" 
      title="Pick a random game based on current filters"
    >
      &#127922; Pick For Me
    </button>
    <span id="recordCountBadge" class="badge-count" style="margin-left: auto;">Loading Library...</span>
  </div>

  <!-- 2. Expandable Search Box -->
  <div class="search-box" id="searchBox">
    <input 
      type="text" 
      id="hubSearchInput" 
      class="form-control" 
      placeholder="Type title, tags, or notes to filter instantly..." 
      oninput="debounceSearch()"
    >
  </div>

  <!-- 3. Standard Filter Panel -->
  <div class="form-pane" style="background: var(--panel);">
    <div class="form-grid-3">
      
      <!-- Platform Dropdown -->
      <div class="form-row">
        <label for="hubConsoleSelect">Platform</label>
        <select id="hubConsoleSelect" class="form-control" onchange="handleSelectorChange()">
          <option value="">All Platforms</option>
        </select>
      </div>

      <!-- Category Dropdown -->
      <div class="form-row">
        <label for="hubCategorySelect">Category</label>
        <select id="hubCategorySelect" class="form-control" onchange="handleCategoryChange()">
          <option value="">All Categories</option>
        </select>
      </div>

      <!-- Cascading Subcategory Dropdown -->
      <div class="form-row">
        <label for="hubSubcatSelect">Subcategory</label>
        <select id="hubSubcatSelect" class="form-control" onchange="handleSelectorChange()">
          <option value="">All Subcategories</option>
        </select>
      </div>

    </div>

    <!-- Status & Sorting Row -->
    <div class="form-grid-2" style="margin-top: 6px; align-items: center;">
      <div class="form-row">
        <label for="hubStatusSelect">Play Status</label>
        <select id="hubStatusSelect" class="form-control" onchange="handleSelectorChange()">
          <option value="all">All Titles</option>
          <option value="backlog">In Backlog (Unplayed)</option>
          <option value="playing">Currently Playing</option>
          <option value="won">&#9733; Cleared / Beaten</option>
        </select>
      </div>

      <div class="form-row">
        <label for="hubSortSelect">Sort Order</label>
        <select id="hubSortSelect" class="form-control" onchange="handleSelectorChange()">
          <option value="name_asc">Title (A &rarr; Z)</option>
          <option value="name_desc">Title (Z &rarr; A)</option>
          <option value="year_desc">Year (Newest First)</option>
          <option value="year_asc">Year (Oldest First)</option>
          <option value="id_desc">Recently Added</option>
        </select>
      </div>
    </div>
  </div>

  <!-- 4. Showcase Target Area -->
  <div class="datasheet-section">
    <div class="grid-header">
      <span style="font-weight: 600;">Game Library Showcase</span>
      <span id="activeFilterSummary" style="color: var(--text-dim); font-size: 11px;">Filter: Initializing...</span>
    </div>

    <div id="deckPlaceholder" style="padding: 40px 16px; text-align: center; color: var(--text-dim);">
      <div style="font-size: 28px; margin-bottom: 6px;">&#127918;</div>
      <p style="font-size: 13px; color: var(--text-muted);">
        Standard selector initialized via Backend API. Ready to connect the game cover grid.
      </p>
    </div>
  </div>
</div>

<script>
  let lookups = {
    consoles: [],
    categories: [],
    subcategories: []
  };

  const filterState = {
    search: '',
    status: 'all',
    consoleId: '',
    categoryId: '',
    subcategoryId: '',
    sort: 'name_asc'
  };

  let searchTimer = null;
  let currentTotalMatches = 0;
  let cachedMatches = []; // Cached full list for the gallery
  let activePickedId = null;
  let isGalleryOpen = false;

  const FALLBACK_COVER  = 'images/support/no_cover.jpg';
  const FALLBACK_SCREEN = 'images/support/no_screen.jpg';

  document.addEventListener('DOMContentLoaded', () => {
    bootstrapSelector();
  });

  async function bootstrapSelector() {
    try {
      const res = await fetch('api/collection.php?mode=bootstrap', {
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error || 'Failed to initialize lookups.');
      lookups = json.data;
      populateDropdowns();
      handleSelectorChange();
    } catch (err) {
      document.getElementById('recordCountBadge').textContent = 'Error Loading Lookups';
      console.error(err);
    }
  }

  function populateDropdowns() {
    const consoleSelect = document.getElementById('hubConsoleSelect');
    lookups.consoles.forEach(c => {
      consoleSelect.add(new Option(c.Console + (c.IsHandheld ? ' (Portable)' : ''), c.ID));
    });

    const categorySelect = document.getElementById('hubCategorySelect');
    lookups.categories.forEach(cat => {
      categorySelect.add(new Option(cat.Category, cat.ID));
    });
  }

  function toggleSearch() {
    const box = document.getElementById('searchBox');
    box.classList.toggle('open');
    if (box.classList.contains('open')) {
      document.getElementById('hubSearchInput').focus();
    }
  }

  function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      handleSelectorChange();
    }, 280);
  }

  function handleCategoryChange() {
    const catId = document.getElementById('hubCategorySelect').value;
    filterState.categoryId = catId;
    filterState.subcategoryId = '';

    const subSelect = document.getElementById('hubSubcatSelect');
    subSelect.innerHTML = '<option value="">All Subcategories</option>';

    if (catId) {
      const filtered = lookups.subcategories.filter(s => s.category_id == catId);
      filtered.forEach(s => {
        subSelect.add(new Option(s.subcategory, s.ID));
      });
    }

    handleSelectorChange();
  }

  function resetSelectorFilters() {
    document.getElementById('hubSearchInput').value = '';
    document.getElementById('hubConsoleSelect').value = '';
    document.getElementById('hubCategorySelect').value = '';
    document.getElementById('hubSubcatSelect').innerHTML = '<option value="">All Subcategories</option>';
    document.getElementById('hubStatusSelect').value = 'all';
    document.getElementById('hubSortSelect').value = 'name_asc';

    const box = document.getElementById('searchBox');
    box.classList.remove('open');

    handleSelectorChange();
  }

  function getActiveFilterParams(mode = 'list') {
    return new URLSearchParams({
      mode: mode,
      q: document.getElementById('hubSearchInput').value.trim(),
      status: document.getElementById('hubStatusSelect').value,
      console_id: document.getElementById('hubConsoleSelect').value,
      category_id: document.getElementById('hubCategorySelect').value,
      subcategory_id: document.getElementById('hubSubcatSelect').value,
      sort: document.getElementById('hubSortSelect').value
    });
  }

  async function handleSelectorChange() {
    filterState.search        = document.getElementById('hubSearchInput').value.trim();
    filterState.consoleId     = document.getElementById('hubConsoleSelect').value;
    filterState.categoryId    = document.getElementById('hubCategorySelect').value;
    filterState.subcategoryId = document.getElementById('hubSubcatSelect').value;
    filterState.status        = document.getElementById('hubStatusSelect').value;
    filterState.sort          = document.getElementById('hubSortSelect').value;

    document.getElementById('activeFilterSummary').textContent = 
      `Status: ${filterState.status.toUpperCase()} | Platform: ${filterState.consoleId || 'ALL'} | Category: ${filterState.categoryId || 'ALL'} | Subcat: ${filterState.subcategoryId || 'ALL'}`;

    document.getElementById('recordCountBadge').textContent = 'Querying...';

    try {
      const params = getActiveFilterParams('list');
      const res = await fetch(`api/collection.php?${params.toString()}`, {
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error);

      currentTotalMatches = Number(json.data.count) || 0;
      cachedMatches = json.data.games || [];
      document.getElementById('recordCountBadge').textContent = `${currentTotalMatches.toLocaleString()} Titles Found`;

      const pickBtn = document.getElementById('pickForMeBtn');
      pickBtn.disabled = (currentTotalMatches === 0);

      // If a game card was already showing, re-render the gallery underneath it
      if (activePickedId) {
        const stillInResults = cachedMatches.find(g => g.ID === activePickedId);
        if (stillInResults) {
          renderGallery(cachedMatches);
        } else {
          activePickedId = null;
        }
      }
    } catch (err) {
      document.getElementById('recordCountBadge').textContent = 'Query Failed';
      console.error(err);
    }
  }

  async function pickRandomGame() {
    const pickBtn = document.getElementById('pickForMeBtn');
    const showcase = document.getElementById('deckPlaceholder');
    
    // Auto-collapse the drawer whenever a new roll is triggered
    isGalleryOpen = false;

    pickBtn.disabled = true;
    pickBtn.innerHTML = '&#127922; Rolling...';

    showcase.innerHTML = `
      <div style="padding: 50px 16px; text-align: center; color: var(--warning);">
        <div style="font-size: 38px; display: inline-block; animation: spin 0.75s linear infinite;">&#127922;</div>
        <p style="font-size: 14px; margin-top: 12px; color: #cbd5e1;">Rolling a title from your selection...</p>
      </div>
    `;

    try {
      const params = getActiveFilterParams('random');
      const res = await fetch(`api/collection.php?${params.toString()}`, {
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error);

      const game = json.data.game;
      if (!game) {
        showcase.innerHTML = `
          <div style="padding: 40px 16px; text-align: center; color: var(--text-dim);">
            <div style="font-size: 32px; margin-bottom: 8px;">&#128533;</div>
            <p>No titles matched the selected criteria.</p>
          </div>
        `;
        return;
      }

      activePickedId = game.ID;
      renderPickedGameCard(game);
    } catch (err) {
      console.error(err);
      showcase.innerHTML = `<div style="padding: 24px; color: var(--danger); text-align: center;">Error selecting game. Please try again.</div>`;
    } finally {
      pickBtn.disabled = (currentTotalMatches === 0);
      pickBtn.innerHTML = '&#127922; Pick For Me';
    }
  }

  function renderPickedGameCard(game) {
    const showcase = document.getElementById('deckPlaceholder');
    activePickedId = game.ID;

    // Status Badge
    let statusBadge = '<span class="tag-pill tag-status-backlog">In Backlog</span>';
    if (game.Won) {
      statusBadge = '<span class="tag-pill tag-status-won">&#9733; Cleared</span>';
    } else if (game.Played) {
      statusBadge = '<span class="tag-pill tag-status-playing">&#9654; Playing</span>';
    }

    // Box Art Container (Fixed 3:4 aspect)
    const boxArtSrc = (game.BoxArt && game.BoxArt.trim() !== '') ? game.BoxArt : FALLBACK_COVER;
    const boxArtHtml = `
      <div class="fixed-media-box">
        <img 
          src="${escapeHtml(boxArtSrc)}" 
          alt="${escapeHtml(game.Game)} Box Art" 
          onerror="this.onerror=null; this.src='${FALLBACK_COVER}';"
        >
        <span class="media-label-badge">Box Art</span>
      </div>
    `;

    // Screenshot Container (Fixed 4:3 aspect)
    const screenshotSrc = (game.Image && game.Image.trim() !== '') ? game.Image : FALLBACK_SCREEN;
    const screenshotHtml = `
      <div class="fixed-media-screenshot">
        <img 
          src="${escapeHtml(screenshotSrc)}" 
          alt="${escapeHtml(game.Game)} Screenshot" 
          onerror="this.onerror=null; this.src='${FALLBACK_SCREEN}';"
        >
        <span class="media-label-badge">Screenshot</span>
      </div>
    `;

    const genreText = [game.category_name, game.subcategory_name]
      .filter(Boolean)
      .map(escapeHtml)
      .join(' &rsaquo; ') || 'General';
    const publisherText = game.publisher_name ? ` &bull; ${escapeHtml(game.publisher_name)}` : '';
    const yearText = game.Year ? ` (${escapeHtml(game.Year)})` : '';
    
    const commentsHtml = game.Comments 
      ? `<div class="picked-notes">&ldquo;${escapeHtml(game.Comments)}&rdquo;</div>` 
      : '';
    const tagsHtml = game.Tags
      ? `<div style="font-size: 11px; color: #94a3b8; margin-bottom: 12px;"><strong>Tags:</strong> ${escapeHtml(game.Tags)}</div>`
      : '';
    const languageHtml = game.language_name
      ? `<span class="tag-pill" style="background: rgba(100, 116, 139, 0.2); color: #94a3b8;">${escapeHtml(game.language_name)}</span>`
      : '';
    const emulatorInfo = game.RetroArchCore || game.Emulator
      ? `<span class="tag-pill" style="background: rgba(168, 85, 247, 0.2); color: #c084fc; border: 1px solid rgba(192, 132, 252, 0.3);">Core: ${escapeHtml(game.RetroArchCore || game.Emulator)}</span>`
      : '';

    // Main Showcase + Expandable Drawer Markup
    showcase.innerHTML = `
      <div class="picked-card-container">
        <!-- Visual Column (Box Art & Screenshot) -->
        <div class="picked-media-strip">
          ${boxArtHtml}
          ${screenshotHtml}
        </div>

        <!-- Metadata Column -->
        <div class="picked-details">
          <div class="picked-badge-strip">
            <span class="tag-pill tag-platform">${escapeHtml(game.console_name || 'Platform')}</span>
            ${statusBadge}
            ${languageHtml}
            ${emulatorInfo}
          </div>

          <h2 class="picked-title">${escapeHtml(game.Game)}</h2>
          
          <div class="picked-meta">
            <span>${genreText}</span>${yearText}${publisherText}
          </div>

          ${commentsHtml}
          ${tagsHtml}

          <div class="picked-actions">
            <button type="button" class="btn" onclick="pickRandomGame()" style="background: #0284c7; color: #ffffff; border: none; padding: 7px 16px; cursor: pointer; font-weight: 500;">
              &#127922; Re-roll Another
            </button>
            <span style="font-size: 11px; color: var(--text-dim); margin-left: auto;">
              Database Record #${game.ID}
            </span>
          </div>
        </div>
      </div>

      <!-- Collapsible Additional Findings Strip -->
      <div class="picked-results-wrapper">
        <button type="button" class="picked-toggle-link" onclick="toggleFindingsDrawer()">
          <span id="drawerToggleIcon">${isGalleryOpen ? '&#9652;' : '&#9662;'}</span>
          <span id="drawerToggleLabel">${isGalleryOpen ? 'Hide matching titles' : 'View all matching titles'} (${cachedMatches.length})</span>
        </button>

        <div id="findingsDrawer" class="picked-results-drawer ${isGalleryOpen ? 'open' : ''}">
          <div id="galleryContainer" class="picked-gallery-grid"></div>
        </div>
      </div>
    `;

    renderGallery(cachedMatches);
  }

  function toggleFindingsDrawer() {
    isGalleryOpen = !isGalleryOpen;
    const drawer = document.getElementById('findingsDrawer');
    const icon = document.getElementById('drawerToggleIcon');
    const label = document.getElementById('drawerToggleLabel');

    if (!drawer) return;

    if (isGalleryOpen) {
      drawer.classList.add('open');
      icon.innerHTML = '&#9652;';
      label.textContent = `Hide matching titles (${cachedMatches.length})`;
    } else {
      drawer.classList.remove('open');
      icon.innerHTML = '&#9662;';
      label.textContent = `View all matching titles (${cachedMatches.length})`;
    }
  }

  function renderGallery(games) {
    const container = document.getElementById('galleryContainer');
    if (!container) return;

    if (!games || games.length === 0) {
      container.innerHTML = '<div style="color: var(--text-dim); font-size: 12px; grid-column: 1/-1; text-align: center;">No additional titles found.</div>';
      return;
    }

    container.innerHTML = games.map(g => {
      const screenSrc = (g.Image && g.Image.trim() !== '') ? g.Image : FALLBACK_SCREEN;
      const isSelected = (g.ID === activePickedId);

      return `
        <div class="gallery-card-thumb ${isSelected ? 'active-pick' : ''}" onclick="selectGameFromGallery(${g.ID})" title="${escapeHtml(g.Game)}">
          <div class="thumb-image-wrap">
            <img 
              src="${escapeHtml(screenSrc)}" 
              alt="${escapeHtml(g.Game)}" 
              loading="lazy"
              onerror="this.onerror=null; this.src='${FALLBACK_SCREEN}';"
            >
          </div>
          <div class="thumb-title">${escapeHtml(g.Game)}</div>
        </div>
      `;
    }).join('');
  }

  function selectGameFromGallery(gameId) {
    const selected = cachedMatches.find(g => g.ID === gameId);
    if (!selected) return;

    // Smooth scroll back to top of the card if user was scrolled down in a large list
    const card = document.querySelector('.picked-card-container');
    if (card) {
      card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    renderPickedGameCard(selected);
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>