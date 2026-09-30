<?php
/**
 * src/Views/games.php
 * Games Cataloguer & Asset Hub Master-Detail Maintenance Workbench.
 *
 * Implements a split two-column workbench:
 * - Left Pane: Persistent Form Editor (Always Visible) with visual assets dropzones,
 *   collection status flags, AI auto-fill button, tags and comments.
 * - Right Pane: Searchable & Platform/Category filtered <data-grid> with collection status badges.
 *
 * Variables expected from GameController:
 * @var array<int, array<string, mixed>> $games
 * @var array<int, array<string, mixed>> $consoles
 * @var array<int, array<string, mixed>> $categories
 * @var array<int, array<string, mixed>> $subcategories
 * @var array<int, array<string, mixed>> $publishers
 * @var array<int, array<string, mixed>> $languages
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
/* Custom Workbench Layout for Games (Persistent Left Form, High-Density Right Grid) */
.workspace-games {
  display: grid;
  grid-template-columns: 500px 1fr;
  gap: 16px;
  align-items: start;
  padding: 16px;
  min-width: 0;
  max-width: 100%;
  width: 100%;
  box-sizing: border-box;
}
@media (max-width: 960px) {
  .workspace-games {
    grid-template-columns: minmax(0, 1fr);
    padding: 12px 8px;
  }
}

.vault-grid-table tbody tr {
  cursor: pointer;
}
.vault-grid-table tbody tr.vault-grid-row-selected td,
.vault-grid-table tbody tr.active td {
  background: var(--row-active, rgba(56, 189, 248, 0.16)) !important;
}
.grid-card {
  border: none;
  background: transparent;
  padding: 0;
  min-width: 0;
  max-width: 100%;
  width: 100%;
  box-sizing: border-box;
}

/* 2-Column Form Fields */
.form-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

/* Section Title */
.form-section-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--text-dim);
  letter-spacing: 0.05em;
  margin-top: 14px;
  margin-bottom: 8px;
}

/* Collection Status Chip Toggles */
.chip-group {
  display: flex;
  gap: 8px;
}
.chip-group-3 {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 8px;
}
@media (max-width: 520px) {
  .chip-group-3 {
    grid-template-columns: 1fr 1fr;
  }
}
@media (max-width: 360px) {
  .chip-group-3 {
    grid-template-columns: 1fr;
  }
}
.chip-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 12px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: rgba(15, 23, 42, 0.5);
  font-size: 11px;
  font-weight: 500;
  color: var(--text-main);
  cursor: pointer;
  user-select: none;
  transition: all var(--transition-fast);
}
.chip-toggle:hover {
  border-color: var(--border-focus);
  background: rgba(56, 189, 248, 0.05);
}
.chip-toggle input[type="checkbox"] {
  cursor: pointer;
  width: 14px;
  height: 14px;
  accent-color: #0284c7;
}

/* Visual Asset Dropzone Cards */
.media-card {
  background: rgba(15, 23, 42, 0.5);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.media-card-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--text-dim);
  letter-spacing: 0.04em;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.dropzone-box {
  min-height: 130px;
  height: 130px;
  border: 2px dashed rgba(148, 163, 184, 0.2);
  border-radius: var(--radius-sm);
  background: rgba(7, 12, 22, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  overflow: hidden;
  position: relative;
  transition: border-color var(--transition-fast), background var(--transition-fast);
}
.dropzone-box:hover {
  border-color: var(--border-focus);
  background: rgba(56, 189, 248, 0.04);
}
.dropzone-box.drag-over {
  border-color: #38bdf8;
  background: rgba(56, 189, 248, 0.1);
}
.dropzone-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  color: var(--text-dim);
  font-size: 11px;
  text-align: center;
}
.dropzone-empty span {
  font-size: 24px;
  opacity: 0.8;
}
.dropzone-empty strong {
  color: var(--text-main);
  font-size: 12px;
}
.dropzone-empty small {
  color: var(--text-dim);
  font-family: var(--font-mono, monospace);
  font-size: 10px;
  opacity: 0.7;
}
.dropzone-preview-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}
.media-actions {
  display: flex;
  gap: 6px;
}
.media-actions .btn {
  flex: 1;
  padding: 4px 8px;
  font-size: 11px;
}

/* AI Auto-Fill Sparkle Button */
.btn-ai-autofill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 9px;
  font-size: 11px;
  font-weight: 600;
  color: #c084fc;
  background: linear-gradient(135deg, rgba(168, 85, 247, 0.15) 0%, rgba(56, 189, 248, 0.15) 100%);
  border: 1px solid rgba(168, 85, 247, 0.4);
  border-radius: var(--radius-sm, 6px);
  cursor: pointer;
  transition: all var(--transition-fast);
  user-select: none;
}
.btn-ai-autofill:hover {
  background: linear-gradient(135deg, rgba(168, 85, 247, 0.28) 0%, rgba(56, 189, 248, 0.28) 100%);
  border-color: #c084fc;
  color: #f3e8ff;
  box-shadow: 0 0 10px rgba(168, 85, 247, 0.3);
  transform: translateY(-1px);
}
.btn-ai-autofill:active {
  transform: translateY(0);
}
.ai-sparkle-icon {
  font-size: 12px;
  line-height: 1;
  filter: drop-shadow(0 0 2px rgba(168, 85, 247, 0.6));
}

/* Collection Status Badges in Grid - 3 Completeness Tiers */
.badge-status {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 3px 10px;
  border-radius: 5px;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  line-height: 1.3;
}
/* Red: Any main field missing (name, year, language, publisher, category, subcategory) */
.badge-collection-red {
  background: #2b1717;
  color: #f87171;
  border: 1.5px solid #dc2626;
  box-shadow: 0 0 6px rgba(220, 38, 38, 0.25);
}
/* Amber / Gold: Base info complete, missing media or tags/comments (matches reference image) */
.badge-collection-amber {
  background: #2c241c;
  color: #fbbf24;
  border: 1.5px solid #c4821a;
  box-shadow: 0 0 6px rgba(196, 130, 26, 0.25);
}
/* Green: Everything properly filled up */
.badge-collection-green {
  background: #142a1e;
  color: #4ade80;
  border: 1.5px solid #16a34a;
  box-shadow: 0 0 6px rgba(22, 163, 74, 0.25);
}
/* Backward compatibility styles */
.badge-backlog {
  background: #2c241c;
  color: #fbbf24;
  border: 1.5px solid #c4821a;
}
.badge-collection {
  background: rgba(56, 189, 248, 0.16);
  color: #38bdf8;
  border: 1px solid rgba(56, 189, 248, 0.35);
}
.badge-played {
  background: rgba(168, 85, 247, 0.16);
  color: #c084fc;
  border: 1px solid rgba(168, 85, 247, 0.35);
}
.badge-cleared {
  background: #142a1e;
  color: #4ade80;
  border: 1.5px solid #16a34a;
}
</style>

<div class="workspace-games">
  <!-- LEFT PANE: Master-Detail Persistent Form Editor -->
  <aside class="editor-card">
    <div class="card-header">
      <span id="formModeTitle" class="card-title">NEW GAME ENTRY</span>
      <span id="activeIdBadge" class="badge-record">(Auto ID)</span>
    </div>

    <form id="gameForm" onsubmit="handleSave(event)">
      <input type="hidden" id="gameId" name="id" value="">
      <input type="hidden" id="deleteBoxart" name="delete_boxart" value="0">
      <input type="hidden" id="deleteScreenshot" name="delete_screenshot" value="0">

      <!-- Section 1: Title & Platform -->
      <div class="form-group">
        <label for="gameTitle">Game Title *</label>
        <input 
          type="text" 
          id="gameTitle" 
          name="title" 
          class="form-control" 
          placeholder="e.g. Super Mario World, Chrono Trigger..." 
          required 
          autocomplete="off" 
          autofocus
          <?= !$canWrite ? 'disabled' : '' ?>
        >
      </div>

      <!-- Platform / Console & Release Year -->
      <div class="form-grid-2">
        <div class="form-group">
          <label for="consoleId">Platform / Console *</label>
          <select id="consoleId" name="console_id" class="form-control" required <?= !$canWrite ? 'disabled' : '' ?>>
            <option value="">-- Choose Console --</option>
            <?php foreach ($consoles as $c): ?>
              <option value="<?= (int)$c['id'] ?>">
                <?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="releaseYear">Release Year</label>
          <input 
            type="text" 
            id="releaseYear" 
            name="year" 
            class="form-control" 
            placeholder="YYYY"
            <?= !$canWrite ? 'disabled' : '' ?>
          >
        </div>
      </div>

      <!-- Section 2: Taxonomy & Classification -->
      <div class="form-grid-2">
        <div class="form-group">
          <label for="categoryId">Category</label>
          <select id="categoryId" name="category_id" class="form-control" onchange="handleCategoryChange(this.value)" <?= !$canWrite ? 'disabled' : '' ?>>
            <option value="">-- Choose Category --</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= (int)$cat['id'] ?>">
                <?= htmlspecialchars((string)$cat['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="subcategoryId">Subcategory</label>
          <select id="subcategoryId" name="subcategory_id" class="form-control" <?= !$canWrite ? 'disabled' : '' ?>>
            <option value="">-- Choose Subcategory --</option>
          </select>
        </div>
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label for="publisherId">Publisher</label>
          <select id="publisherId" name="publisher_id" class="form-control" <?= !$canWrite ? 'disabled' : '' ?>>
            <option value="">-- Choose Publisher --</option>
            <?php foreach ($publishers as $p): ?>
              <option value="<?= (int)$p['id'] ?>">
                <?= htmlspecialchars((string)$p['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="languageId">Language</label>
          <select id="languageId" name="language_id" class="form-control" <?= !$canWrite ? 'disabled' : '' ?>>
            <option value="">-- Choose Language --</option>
            <?php foreach ($languages as $l): ?>
              <option value="<?= (int)$l['id'] ?>">
                <?= htmlspecialchars((string)$l['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Section 3: Collection Status -->
      <div class="form-section-title">Collection Status</div>
      <div class="chip-group">
        <label class="chip-toggle">
          <input type="checkbox" id="inCollection" name="in_collection" value="1" <?= !$canWrite ? 'disabled' : '' ?>>
          📦 In Collection
        </label>
      </div>

      <!-- Section 4: Visual Asset Dropzones -->
      <div class="form-section-title">Visual Media (Cover & Screenshot)</div>
      <div class="form-grid-2">
        <!-- Box Art Dropzone (<ID>_Box.<ext>) -->
        <div class="media-card">
          <div class="media-card-title">
            <span>Box Art Cover</span>
          </div>

          <input 
            type="file" 
            id="boxartFileInput" 
            name="boxart_file" 
            class="hidden-file-input" 
            accept="image/*" 
            onchange="handleFileSelect(this, 'boxartPreviewContainer', 'deleteBoxartBtn', 'boxart')"
            <?= !$canWrite ? 'disabled' : '' ?>
          >

          <div 
            class="dropzone-box" 
            id="boxartDropzone" 
            onclick="triggerBrowse('boxartFileInput')"
            title="Drop Box Art or click to browse"
          >
            <div id="boxartPreviewContainer" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
              <div class="dropzone-empty">
                <span>🖼️</span>
                <strong>Upload Box Art</strong>
                <small>&lt;ID&gt;_Box.ext</small>
              </div>
            </div>
          </div>

          <div class="media-actions">
            <button 
              type="button" 
              class="btn btn-sm" 
              onclick="triggerBrowse('boxartFileInput')"
              <?= !$canWrite ? 'disabled' : '' ?>
            >
              Browse
            </button>
            <button 
              type="button" 
              id="deleteBoxartBtn" 
              class="btn btn-sm danger" 
              onclick="removeAsset('boxart')" 
              disabled
            >
              Remove
            </button>
          </div>
        </div>

        <!-- Screenshot Dropzone (<ID>_Img.<ext>) -->
        <div class="media-card">
          <div class="media-card-title">
            <span>Game Screenshot</span>
          </div>

          <input 
            type="file" 
            id="screenshotFileInput" 
            name="screenshot_file" 
            class="hidden-file-input" 
            accept="image/*" 
            onchange="handleFileSelect(this, 'screenshotPreviewContainer', 'deleteScreenshotBtn', 'screenshot')"
            <?= !$canWrite ? 'disabled' : '' ?>
          >

          <div 
            class="dropzone-box" 
            id="screenshotDropzone" 
            onclick="triggerBrowse('screenshotFileInput')"
            title="Drop In-Game Screenshot or click to browse"
          >
            <div id="screenshotPreviewContainer" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
              <div class="dropzone-empty">
                <span>📷</span>
                <strong>Upload Screenshot</strong>
                <small>&lt;ID&gt;_Img.ext</small>
              </div>
            </div>
          </div>

          <div class="media-actions">
            <button 
              type="button" 
              class="btn btn-sm" 
              onclick="triggerBrowse('screenshotFileInput')"
              <?= !$canWrite ? 'disabled' : '' ?>
            >
              Browse
            </button>
            <button 
              type="button" 
              id="deleteScreenshotBtn" 
              class="btn btn-sm danger" 
              onclick="removeAsset('screenshot')" 
              disabled
            >
              Remove
            </button>
          </div>
        </div>
      </div>

      <!-- Section 5: Metadata, AI Button & Notes -->
      <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 14px; margin-bottom: 6px;">
        <label for="tags" class="form-label" style="margin: 0; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-dim); letter-spacing: 0.04em;">
          Tags (Comma-separated)
        </label>
        <!-- AI Assistant Action Button (Prepared for future API activation) -->
        <button 
          type="button" 
          id="aiAutoFillBtn" 
          class="btn-ai-autofill" 
          title="Auto-fill Tags and Comments with AI (API Key integration)"
        >
          <span class="ai-sparkle-icon">✨</span> AI Auto-Fill
        </button>
      </div>
      <div class="form-group" style="margin-bottom: 10px;">
        <input 
          type="text" 
          id="tags" 
          name="tags" 
          class="form-control" 
          placeholder="e.g. 2-player, handheld-favorite, metroidvania"
          <?= !$canWrite ? 'disabled' : '' ?>
        >
      </div>

      <div class="form-group">
        <label for="comments">Personal Notes / Comments</label>
        <textarea 
          id="comments" 
          name="comments" 
          class="form-control" 
          rows="3" 
          placeholder="Condition, cartridge location, save battery status..."
          <?= !$canWrite ? 'disabled' : '' ?>
        ></textarea>
      </div>

      <!-- Action Buttons -->
      <div class="editor-actions">
        <?php if ($canWrite): ?>
          <button type="submit" id="saveBtn" class="btn primary">
            💾 Save Game
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

  <!-- RIGHT PANE: Reusable <data-grid> Component with Consoles and Categories filter -->
  <section class="grid-card">
    <data-grid 
      id="gamesGrid" 
      page-size="25" 
      search-placeholder="Search games by title, tags, comments..."
    ></data-grid>
  </section>
</div>

<!-- Raw Initial Server Datasets -->
<script id="serverGamesData" type="application/json">
  <?= json_encode($games, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>

<script id="serverConsolesData" type="application/json">
  <?= json_encode($consoles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>

<script id="serverCategoriesData" type="application/json">
  <?= json_encode($categories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>

<script id="serverSubcategoriesData" type="application/json">
  <?= json_encode($subcategories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>

<script>
/**
 * Master-Detail Games Cataloguer Controller Script
 */
let gamesList = [];
let consolesList = [];
let categoriesList = [];
let subcategoriesList = [];
let selectedId = null;
const canWrite = <?= json_encode($canWrite) ?>;

document.addEventListener('DOMContentLoaded', () => {
  // 1. Ingest initial server pre-rendered datasets
  try {
    const rawGames = document.getElementById('serverGamesData').textContent;
    gamesList = JSON.parse(rawGames || '[]');
  } catch (err) {
    console.error('Failed to parse games dataset:', err);
    gamesList = [];
  }

  try {
    const rawConsoles = document.getElementById('serverConsolesData').textContent;
    consolesList = JSON.parse(rawConsoles || '[]');
  } catch (err) {
    consolesList = [];
  }

  try {
    const rawCategories = document.getElementById('serverCategoriesData').textContent;
    categoriesList = JSON.parse(rawCategories || '[]');
  } catch (err) {
    categoriesList = [];
  }

  try {
    const rawSubcategories = document.getElementById('serverSubcategoriesData').textContent;
    subcategoriesList = JSON.parse(rawSubcategories || '[]');
  } catch (err) {
    subcategoriesList = [];
  }

  // 2. Initialize the reusable <data-grid>
  const grid = document.getElementById('gamesGrid');
  if (grid) {
    // Custom platform and category filter dropdowns matching user design
    grid.customFilters = [
      {
        key: 'console_id',
        label: '',
        allLabel: 'All Consoles',
        options: consolesList.map(c => ({
          value: String(c.id),
          label: c.name
        }))
      },
      {
        key: 'category_id',
        label: '',
        allLabel: 'All Categories',
        options: categoriesList.map(cat => ({
          value: String(cat.id),
          label: cat.name
        }))
      }
    ];

    grid.columns = [
      {
        key: 'id',
        label: 'ID',
        sortable: true,
        width: '65px',
        render: (val) => `<span style="font-weight: 600; color: var(--text-dim);">#${escapeHtml(val)}</span>`
      },
      {
        key: 'title',
        label: 'Title',
        sortable: true,
        searchable: true,
        render: (val) => `<span style="font-weight: 600; color: var(--text-main); font-size: 13px;">${escapeHtml(val || 'Untitled')}</span>`
      },
      {
        key: 'console_name',
        label: 'Platform',
        sortable: true,
        searchable: true,
        render: (val) => `<span style="color: var(--text-muted); font-size: 12px;">${escapeHtml(val || '—')}</span>`
      },
      {
        key: 'category_name',
        label: 'Genre',
        sortable: true,
        searchable: true,
        render: (val) => `<span style="color: var(--text-dim); font-size: 12px;">${escapeHtml(val || '—')}</span>`
      },
      {
        key: 'year',
        label: 'Year',
        sortable: true,
        width: '65px',
        render: (val) => `<span style="color: var(--text-dim); font-size: 12px;">${escapeHtml(val || '—')}</span>`
      },
      {
        key: 'collection_status',
        label: 'Collection',
        sortable: true,
        align: 'center',
        width: '130px',
        render: (val, row) => computeCollectionBadge(row)
      }
    ];

    grid.data = gamesList;

    // 3. Row selection listener: populates persistent left-hand form
    grid.addEventListener('row-click', (e) => {
      const row = e.detail.row;
      if (row && row.id !== undefined) {
        selectGame(Number(row.id));
      }
    });

    // Re-apply visual row highlight when page changes, sorts occur, or grid updates
    grid.addEventListener('page-change', () => syncRowHighlight());
    grid.addEventListener('grid-updated', () => syncRowHighlight());
  }

  // Setup drag & drop on box art and screenshot dropzones
  setupDropzone('boxartDropzone', 'boxartFileInput', 'boxartPreviewContainer', 'deleteBoxartBtn', 'boxart');
  setupDropzone('screenshotDropzone', 'screenshotFileInput', 'screenshotPreviewContainer', 'deleteScreenshotBtn', 'screenshot');

  // Setup AI button behavior
  const aiBtn = document.getElementById('aiAutoFillBtn');
  if (aiBtn) {
    aiBtn.addEventListener('click', (e) => {
      e.preventDefault();
      // At this moment button does nothing destructive, inform user about upcoming integration
      showToast('AI Assistant: API key integration will be enabled in an upcoming release.', 'info');
    });
  }

  // Ensure subcategory dropdown starts empty when no category is chosen
  handleCategoryChange(null);

  // Global keyboard shortcut bindings: Ctrl+S to save, Esc to reset/new
  window.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      if (canWrite) {
        document.getElementById('gameForm').requestSubmit();
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
 * Filters the subcategory dropdown based on the chosen category.
 * If no category is chosen, the subcategory list must be empty.
 */
function handleCategoryChange(categoryId, selectedSubId = null) {
  const subSelect = document.getElementById('subcategoryId');
  if (!subSelect) return;

  subSelect.innerHTML = '<option value="">-- Choose Subcategory --</option>';

  const catIdNum = categoryId ? parseInt(String(categoryId), 10) : null;
  if (!catIdNum || isNaN(catIdNum) || catIdNum <= 0) {
    // If no category is chosen, subcategory list must be empty
    subSelect.value = '';
    return;
  }

  const currentVal = selectedSubId !== null ? String(selectedSubId) : '';
  const filtered = subcategoriesList.filter(s => {
    return parseInt(String(s.category_id), 10) === catIdNum;
  });

  filtered.forEach(s => {
    const opt = document.createElement('option');
    opt.value = String(s.id);
    opt.textContent = s.name;
    if (currentVal && String(s.id) === currentVal) {
      opt.selected = true;
    }
    subSelect.appendChild(opt);
  });

  if (currentVal) {
    subSelect.value = currentVal;
  }
}

/**
 * Populates persistent left editor with selected game record
 */
function selectGame(id) {
  const record = gamesList.find(g => Number(g.id) === Number(id));
  if (!record) return;

  selectedId = Number(record.id);
  document.getElementById('gameId').value = String(record.id);
  document.getElementById('deleteBoxart').value = '0';
  document.getElementById('deleteScreenshot').value = '0';

  document.getElementById('gameTitle').value = record.title || '';
  document.getElementById('consoleId').value = String(record.console_id || '');
  document.getElementById('releaseYear').value = record.year || '';

  // Update Category and filter Subcategories dynamically
  document.getElementById('categoryId').value = String(record.category_id || '');
  handleCategoryChange(record.category_id, record.subcategory_id);

  document.getElementById('publisherId').value = String(record.publisher_id || '');
  document.getElementById('languageId').value = String(record.language_id || '');

  document.getElementById('inCollection').checked = Number(record.in_collection) === 1;

  document.getElementById('tags').value = record.tags || '';
  document.getElementById('comments').value = record.comments || '';

  // Set Visual Asset Previews (3. Read: retrieves values from fields, doesn't enforce convention)
  setDropzoneImage('boxartPreviewContainer', record.boxart_url, 'deleteBoxartBtn', '🖼️', 'Upload Box Art', '<ID>_Box.ext');
  setDropzoneImage('screenshotPreviewContainer', record.screenshot_url, 'deleteScreenshotBtn', '📷', 'Upload Screenshot', '<ID>_Img.ext');

  document.getElementById('formModeTitle').textContent = `EDIT GAME #${record.id}`;
  document.getElementById('activeIdBadge').textContent = `#${record.id}`;

  const deleteBtn = document.getElementById('deleteBtn');
  if (deleteBtn) {
    deleteBtn.disabled = false;
    deleteBtn.title = `Delete ${record.title}`;
  }

  syncRowHighlight();
  document.getElementById('gameTitle').focus();
}

/**
 * Resets the persistent form back to "NEW GAME ENTRY" state
 */
function resetForm() {
  selectedId = null;
  document.getElementById('gameForm').reset();
  document.getElementById('gameId').value = '';
  document.getElementById('deleteBoxart').value = '0';
  document.getElementById('deleteScreenshot').value = '0';

  handleCategoryChange(null, null);

  resetDropzonePreview('boxartPreviewContainer', 'deleteBoxartBtn', '🖼️', 'Upload Box Art', '<ID>_Box.ext');
  resetDropzonePreview('screenshotPreviewContainer', 'deleteScreenshotBtn', '📷', 'Upload Screenshot', '<ID>_Img.ext');

  document.getElementById('formModeTitle').textContent = 'NEW GAME ENTRY';
  document.getElementById('activeIdBadge').textContent = '(Auto ID)';

  const deleteBtn = document.getElementById('deleteBtn');
  if (deleteBtn) {
    deleteBtn.disabled = true;
    deleteBtn.removeAttribute('title');
  }

  syncRowHighlight();
  const input = document.getElementById('gameTitle');
  if (input && !input.disabled) {
    input.focus();
  }
}

/**
 * Helper to display image preview in dropzone with cache-busting
 */
function setDropzoneImage(containerId, url, deleteBtnId, icon, label, extHint) {
  const container = document.getElementById(containerId);
  const deleteBtn = document.getElementById(deleteBtnId);
  if (!container) return;

  if (url && url.trim() !== '') {
    const cacheBuster = (url.includes('?') ? '&' : '?') + '_t=' + Date.now();
    const displayUrl = url + cacheBuster;
    container.innerHTML = `<img src="${escapeHtml(displayUrl)}" class="dropzone-preview-img" alt="Media Preview" onerror="this.parentElement.innerHTML='<div class=\\'dropzone-empty\\'><span>⚠️</span><small>Image not found</small></div>';">`;
    if (deleteBtn && canWrite) deleteBtn.disabled = false;
  } else {
    resetDropzonePreview(containerId, deleteBtnId, icon, label, extHint);
  }
}

/**
 * Resets dropzone box to empty state
 */
function resetDropzonePreview(containerId, deleteBtnId, icon, label, extHint) {
  const container = document.getElementById(containerId);
  const deleteBtn = document.getElementById(deleteBtnId);
  if (container) {
    container.innerHTML = `
      <div class="dropzone-empty">
        <span>${icon}</span>
        <strong>${label}</strong>
        <small>${extHint}</small>
      </div>
    `;
  }
  if (deleteBtn) {
    deleteBtn.disabled = true;
  }
}

/**
 * Triggers hidden file input
 */
function triggerBrowse(inputId) {
  if (!canWrite) return;
  const input = document.getElementById(inputId);
  if (input) input.click();
}

/**
 * Handles file selection from file input
 */
function handleFileSelect(input, containerId, deleteBtnId, type) {
  if (input.files && input.files[0]) {
    const file = input.files[0];
    const previewUrl = URL.createObjectURL(file);
    const container = document.getElementById(containerId);
    const deleteBtn = document.getElementById(deleteBtnId);

    if (container) {
      container.innerHTML = `<img src="${previewUrl}" class="dropzone-preview-img" alt="Upload Preview">`;
    }
    if (deleteBtn) {
      deleteBtn.disabled = false;
    }

    if (type === 'boxart') {
      document.getElementById('deleteBoxart').value = '0';
    } else if (type === 'screenshot') {
      document.getElementById('deleteScreenshot').value = '0';
    }
  }
}

/**
 * Handles explicit asset removal
 */
function removeAsset(type) {
  if (!canWrite) return;

  if (type === 'boxart') {
    const fileInput = document.getElementById('boxartFileInput');
    if (fileInput) fileInput.value = '';
    document.getElementById('deleteBoxart').value = '1';
    resetDropzonePreview('boxartPreviewContainer', 'deleteBoxartBtn', '🖼️', 'Upload Box Art', '<ID>_Box.ext');
  } else if (type === 'screenshot') {
    const fileInput = document.getElementById('screenshotFileInput');
    if (fileInput) fileInput.value = '';
    document.getElementById('deleteScreenshot').value = '1';
    resetDropzonePreview('screenshotPreviewContainer', 'deleteScreenshotBtn', '📷', 'Upload Screenshot', '<ID>_Img.ext');
  }
}

/**
 * Configures drag & drop for a dropzone box
 */
function setupDropzone(boxId, inputId, containerId, deleteBtnId, type) {
  const box = document.getElementById(boxId);
  const input = document.getElementById(inputId);
  if (!box || !input) return;

  box.addEventListener('dragover', (e) => {
    e.preventDefault();
    if (canWrite) box.classList.add('drag-over');
  });

  box.addEventListener('dragleave', () => {
    box.classList.remove('drag-over');
  });

  box.addEventListener('drop', (e) => {
    e.preventDefault();
    box.classList.remove('drag-over');
    if (!canWrite) return;

    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      input.files = e.dataTransfer.files;
      handleFileSelect(input, containerId, deleteBtnId, type);
    }
  });
}

/**
 * Synchronizes table row selection highlight in <data-grid>
 */
function syncRowHighlight() {
  const grid = document.getElementById('gamesGrid');
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
 * Handles Form Submission (Create or Update with Multipart FormData)
 */
async function handleSave(e) {
  e.preventDefault();
  if (!canWrite) return;

  const id = document.getElementById('gameId').value;
  const title = document.getElementById('gameTitle').value.trim();
  const consoleId = document.getElementById('consoleId').value;
  const saveBtn = document.getElementById('saveBtn');

  if (!title) {
    showToast('Game title is required.', 'error');
    document.getElementById('gameTitle').focus();
    return;
  }

  if (!consoleId) {
    showToast('Please select a platform/console.', 'error');
    document.getElementById('consoleId').focus();
    return;
  }

  saveBtn.disabled = true;
  saveBtn.textContent = '⏳ Saving...';

  try {
    const isUpdate = Boolean(id);
    const url = isUpdate ? `/games/${id}/update` : '/games/create';

    const formElement = document.getElementById('gameForm');
    const formData = new FormData(formElement);

    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Accept': 'application/json'
      },
      body: formData
    });

    const result = await res.json();
    if (!res.ok || !result.success) {
      throw new Error(result.error || (result.data && result.data.error) || 'Failed to save game record.');
    }

    const payload = result.data || result;
    showToast(payload.message || 'Game saved successfully.', 'success');

    // Reset file input controls and deletion flags after successful save
    const boxInput = document.getElementById('boxartFileInput');
    if (boxInput) boxInput.value = '';
    const screenInput = document.getElementById('screenshotFileInput');
    if (screenInput) screenInput.value = '';
    document.getElementById('deleteBoxart').value = '0';
    document.getElementById('deleteScreenshot').value = '0';

    // Reload latest dataset from API with cache-busting and re-select record
    const targetId = isUpdate ? parseInt(id, 10) : parseInt(payload.id, 10);
    await reloadGridData(targetId);
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    saveBtn.disabled = false;
    saveBtn.textContent = '💾 Save Game';
  }
}

/**
 * Handles Record Deletion
 */
async function handleDelete() {
  if (!canWrite || !selectedId) return;

  const record = gamesList.find(g => Number(g.id) === selectedId);
  if (!record) return;

  if (!confirm(`Are you sure you want to permanently delete game "${record.title}" and any associated visual assets?`)) {
    return;
  }

  const deleteBtn = document.getElementById('deleteBtn');
  deleteBtn.disabled = true;

  try {
    const res = await fetch(`/games/${selectedId}/delete`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ id: selectedId })
    });

    const result = await res.json();
    if (!res.ok || !result.success) {
      throw new Error(result.error || (result.data && result.data.error) || 'Failed to delete game.');
    }

    const payload = result.data || result;
    showToast(payload.message || 'Game deleted successfully.', 'success');

    resetForm();
    await reloadGridData(null);
  } catch (err) {
    showToast(err.message, 'error');
    deleteBtn.disabled = false;
  }
}

/**
 * Computes Collection status badge according to user requirements:
 * - Text: PENDING if game is not in collection, BACKLOG if in collection
 * - Colors:
 *   - Red: if ANY main field is missing (name, year, language, publisher, category, subcategory)
 *   - Amber (matches user image): if base info complete, but missing media (boxart/screenshot) or tags/comments
 *   - Green: if everything is properly filled up
 */
function computeCollectionBadge(row) {
  if (!row) return '';

  const inCollection = Number(row.in_collection) === 1;
  const text = inCollection ? 'BACKLOG' : 'PENDING';

  // Check main fields: name/title, year, language, publisher, category, subcategory, console
  const title = (row.title || row.game || '').toString().trim();
  const year = (row.year !== null && row.year !== undefined) ? String(row.year).trim() : '';
  const langId = row.language_id ? parseInt(String(row.language_id), 10) : 0;
  const pubId = row.publisher_id ? parseInt(String(row.publisher_id), 10) : 0;
  const catId = row.category_id ? parseInt(String(row.category_id), 10) : 0;
  const subId = row.subcategory_id ? parseInt(String(row.subcategory_id), 10) : 0;
  const consoleId = row.console_id ? parseInt(String(row.console_id), 10) : 0;

  const hasMainInfo = title !== '' && year !== '' && langId > 0 && pubId > 0 && catId > 0 && subId > 0 && consoleId > 0;

  if (!hasMainInfo) {
    return `<span class="badge-status badge-collection-red" title="Missing required info: name, year, language, publisher, category, subcategory or console">${escapeHtml(text)}</span>`;
  }

  // Check secondary fields: screenshot, boxart, tags, comments
  const hasScreenshot = Boolean((row.screenshot_path || row.screenshot_url || '').toString().trim());
  const hasBoxart = Boolean((row.boxart_path || row.boxart_url || '').toString().trim());
  const hasTags = Boolean((row.tags || '').toString().trim());
  const hasComments = Boolean((row.comments || '').toString().trim());

  const hasCompleteSecondary = hasScreenshot && hasBoxart && hasTags && hasComments;

  if (!hasCompleteSecondary) {
    return `<span class="badge-status badge-collection-amber" title="Base info complete, missing media (screenshot/boxart) or tags/comments">${escapeHtml(text)}</span>`;
  }

  return `<span class="badge-status badge-collection-green" title="All information complete">${escapeHtml(text)}</span>`;
}

/**
 * Reloads games list from API with anti-caching headers and query timestamp,
 * updates the <data-grid>, and focuses/selects the target record.
 */
async function reloadGridData(selectTargetId = null) {
  try {
    const res = await fetch(`/api/games?_t=${Date.now()}`, {
      cache: 'no-store',
      headers: {
        'Accept': 'application/json',
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache'
      }
    });
    if (!res.ok) throw new Error('Failed to refresh games list.');

    const json = await res.json();
    const data = json.data || json;

    gamesList = Array.isArray(data.games) ? data.games : [];
    if (Array.isArray(data.consoles)) consolesList = data.consoles;
    if (Array.isArray(data.categories)) categoriesList = data.categories;
    if (Array.isArray(data.subcategories)) subcategoriesList = data.subcategories;

    const grid = document.getElementById('gamesGrid');
    if (grid) {
      // If a target record was saved/updated, ensure active filters don't hide it
      if (selectTargetId !== null && selectTargetId > 0 && grid._activeFilters) {
        const targetItem = gamesList.find(g => Number(g.id) === Number(selectTargetId));
        if (targetItem) {
          for (const [key, filterVal] of Object.entries(grid._activeFilters)) {
            if (filterVal && filterVal !== 'all' && String(targetItem[key]) !== String(filterVal)) {
              grid._activeFilters[key] = 'all';
              const sel = grid.querySelector(`.vault-grid-select-filter[data-filter-key="${key}"]`);
              if (sel) sel.value = 'all';
            }
          }
        }
      }
      grid.data = [...gamesList];
    }

    if (selectTargetId !== null && selectTargetId > 0) {
      if (grid && typeof grid.goToPage === 'function') {
        const listToSearch = (grid._filteredData && grid._filteredData.length > 0) ? grid._filteredData : gamesList;
        const itemIndex = listToSearch.findIndex(g => Number(g.id) === Number(selectTargetId));
        if (itemIndex >= 0) {
          const pageSize = grid.pageSize || 25;
          const targetPage = Math.floor(itemIndex / pageSize) + 1;
          grid.goToPage(targetPage);
        }
      }
      selectGame(selectTargetId);
      syncRowHighlight();
    } else {
      syncRowHighlight();
    }
  } catch (err) {
    console.error('Error reloading grid data:', err);
  }
}

/**
 * Escapes HTML entities for safe dynamic DOM insertion
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

/**
 * Toast Notification Helper
 */
function showToast(msg, type = 'success') {
  if (!msg) return;
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
</script>
