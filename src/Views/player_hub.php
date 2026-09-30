<?php
/**
 * src/Views/player_hub.php
 * Player Hub View - Game Spotlight & Random Pick Discovery Experience.
 *
 * Variables provided by PlayerHubController:
 * @var string $pageTitle
 * @var string $activeNav
 * @var array{
 *   platforms: array<int, array{id: int, name: string}>,
 *   categories: array<int, array{id: int, name: string}>,
 *   subcategories: array<int, array{id: int, name: string, category_id: int}>
 * } $taxonomies
 * @var array<string, mixed> $initialFilters
 * @var int $initialCount
 * @var array<string, mixed>|null $initialGame
 * @var int $initialPlatformId
 */

declare(strict_types=1);

use Vault\Services\View;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

$platforms = $taxonomies['platforms'] ?? [];
$categories = $taxonomies['categories'] ?? [];
$subcategories = $taxonomies['subcategories'] ?? [];
?>

<style>
/* --------------------------------------------------------------------------
   Player Hub Scoped Styles
   -------------------------------------------------------------------------- */
.player-hub-wrapper {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 16px 20px 48px;
  max-width: 1400px;
  margin: 0 auto;
  box-sizing: border-box;
}

/* Master Filter Panel */
.hub-filter-card {
  background: rgba(15, 23, 42, 0.75);
  border: 1px solid var(--border, #1e293b);
  border-radius: 8px;
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
}

/* Top Action Toolbar */
.hub-toolbar-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}

.hub-btn-group {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.btn-hub-action {
  height: 34px;
  padding: 0 16px;
  font-size: 12px;
  font-weight: 600;
  border-radius: 4px;
  border: 1px solid var(--border, #1e293b);
  background: #0c121e;
  color: #cbd5e1;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  cursor: pointer;
  outline: none;
  transition: all var(--transition-fast);
  user-select: none;
}
.btn-hub-action:hover {
  background: var(--panel-hover, #1c273e);
  color: #f8fafc;
  border-color: #38bdf8;
}
.btn-hub-action.active {
  background: rgba(56, 189, 248, 0.15);
  border-color: #38bdf8;
  color: #38bdf8;
}

.btn-pick-me {
  background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
  border: 1px solid #38bdf8;
  color: #ffffff;
  box-shadow: 0 0 12px rgba(56, 189, 248, 0.25);
}
.btn-pick-me:hover {
  background: linear-gradient(135deg, #0369a1 0%, #0284c7 100%);
  box-shadow: 0 0 16px rgba(56, 189, 248, 0.45);
}

/* Right-side Count Badge */
.hub-count-pill {
  background: rgba(2, 132, 199, 0.12);
  border: 1px solid rgba(56, 189, 248, 0.35);
  color: #38bdf8;
  font-size: 11px;
  font-weight: 700;
  padding: 5px 14px;
  border-radius: 9999px;
  letter-spacing: 0.03em;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all var(--transition-fast);
}

/* Contains Input Row (Permanently Visible Above Dropdowns) */
.hub-contains-row {
  display: flex;
  align-items: center;
  width: 100%;
}

.criteria-col-contains {
  width: 100%;
}

.contains-input-wrap {
  position: relative;
  flex: 1;
  display: flex;
  align-items: center;
}

.contains-search-icon {
  position: absolute;
  left: 11px;
  top: 50%;
  transform: translateY(-50%);
  color: #64748b;
  font-size: 13px;
  pointer-events: none;
}

.contains-text-input {
  width: 100%;
  height: 36px;
  padding: 0 34px 0 34px;
  font-size: 12.5px;
  font-weight: 500;
  color: #f1f5f9;
  background: var(--surface-alt, #0c121e);
  border: 1px solid var(--border, #1e293b);
  border-radius: 4px;
  outline: none;
  box-sizing: border-box;
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast), background var(--transition-fast);
}

.contains-text-input::placeholder {
  color: #64748b;
  font-weight: 400;
}

.contains-text-input:focus {
  border-color: var(--border-focus, #38bdf8);
  box-shadow: 0 0 0 1px var(--border-focus, #38bdf8), 0 0 12px rgba(56, 189, 248, 0.2);
  background: rgba(12, 18, 30, 0.95);
}

.contains-clear-btn {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: transparent;
  border: none;
  color: #94a3b8;
  font-size: 14px;
  cursor: pointer;
  padding: 2px 6px;
  border-radius: 50%;
  transition: all var(--transition-fast);
}

.contains-clear-btn:hover {
  color: #ffffff;
  background: rgba(255, 255, 255, 0.1);
}

/* Criteria Dropdowns Row */
.hub-criteria-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  align-items: center;
}
@media (max-width: 900px) {
  .hub-criteria-row {
    grid-template-columns: 1fr;
  }
}

.criteria-col {
  display: flex;
  align-items: center;
  gap: 10px;
}
.criteria-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #94a3b8;
  white-space: nowrap;
  min-width: 75px;
}
.criteria-select-wrap {
  position: relative;
  flex: 1;
}
.criteria-select {
  width: 100%;
  height: 34px;
  padding: 0 28px 0 10px;
  font-size: 12px;
  font-weight: 600;
  color: #f1f5f9;
  background: var(--surface-alt, #0c121e);
  border: 1px solid var(--border, #1e293b);
  border-radius: 4px;
  appearance: none;
  -webkit-appearance: none;
  cursor: pointer;
  outline: none;
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}
.criteria-select:focus {
  border-color: var(--border-focus, #38bdf8);
  box-shadow: 0 0 0 1px var(--border-focus, #38bdf8);
}
.criteria-arrow {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
  color: #64748b;
  font-size: 9px;
}

/* Showcase Header Bar */
.showcase-header-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 10px;
  border-top: 1px solid rgba(30, 41, 59, 0.6);
  font-size: 11px;
}
.showcase-header-title {
  font-weight: 700;
  color: #cbd5e1;
  letter-spacing: 0.03em;
}
.showcase-header-breadcrumbs {
  color: #64748b;
  font-family: var(--font-mono, monospace);
  font-size: 10.5px;
}

/* --------------------------------------------------------------------------
   Game Pick Section: Spotlight Game Card
   -------------------------------------------------------------------------- */
.picked-display-area {
  min-height: 380px;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Empty Showcase State */
.showcase-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 60px 20px;
  color: #64748b;
  text-align: center;
}
.showcase-empty-icon {
  font-size: 36px;
  opacity: 0.6;
}
.showcase-empty-text {
  font-size: 12px;
  max-width: 450px;
  line-height: 1.5;
}

/* Spotlight Card Container */
.picked-card-container {
  width: 100%;
  max-width: 980px;
  margin: 10px auto;
  background: linear-gradient(145deg, #131d2e, #0c1421);
  border: 1px solid rgba(56, 189, 248, 0.22);
  border-radius: 12px;
  box-shadow: 0 14px 35px rgba(0, 0, 0, 0.55), 0 0 25px rgba(14, 165, 233, 0.12);
  overflow: hidden;
  display: flex;
  flex-direction: row;
  align-items: stretch;
}
@media (max-width: 860px) {
  .picked-card-container {
    flex-direction: column;
  }
}

/* Left Media Strip */
.picked-media-strip {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px;
  background: #070b14;
  border-right: 1px solid rgba(255, 255, 255, 0.06);
  flex: 0 0 310px;
  align-items: center;
  justify-content: center;
}
@media (max-width: 860px) {
  .picked-media-strip {
    border-right: none;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    flex: auto;
    width: 100%;
  }
}

/* Fixed Media Box: Box Art */
.fixed-media-box {
  position: relative;
  width: 100%;
  max-width: 275px;
  height: 270px;
  background: #04070e;
  border-radius: 6px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 8px;
  box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.7);
  overflow: hidden;
}
.fixed-media-box img {
  max-width: 100% !important;
  max-height: 100% !important;
  width: auto !important;
  height: auto !important;
  object-fit: contain !important;
  display: block;
  margin: auto;
  filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.6));
}

/* Fixed Media Box: Gameplay Screenshot */
.fixed-media-screenshot {
  position: relative;
  width: 100%;
  max-width: 275px;
  height: 190px;
  background: #03050a;
  border-radius: 6px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 6px;
  box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.85);
  overflow: hidden;
}
.fixed-media-screenshot img {
  max-width: 100% !important;
  max-height: 100% !important;
  width: auto !important;
  height: auto !important;
  object-fit: contain !important;
  display: block;
  margin: auto;
}

/* Media Label Badge (Box Art / Screenshot) */
.media-label-badge {
  position: absolute;
  top: 6px;
  left: 6px;
  background: rgba(11, 15, 25, 0.85);
  backdrop-filter: blur(4px);
  color: var(--text-dim, #64748b);
  font-size: 9px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 3px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  letter-spacing: 0.5px;
  text-transform: uppercase;
  pointer-events: none;
  z-index: 2;
}

/* Right Details Column */
.picked-details {
  padding: 24px;
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 100%;
}

/* Badges Strip */
.picked-badge-strip {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 8px;
}
.tag-pill {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 3px 10px;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.08);
  color: #cbd5e1;
  white-space: nowrap;
}
.tag-platform {
  background: rgba(0, 168, 255, 0.18);
  color: #38bdf8;
  border: 1px solid rgba(56, 189, 248, 0.3);
}
.tag-status-owned {
  background: rgba(16, 185, 129, 0.18);
  color: #34d399;
  border: 1px solid rgba(52, 211, 153, 0.3);
}
.tag-status-backlog {
  background: rgba(148, 163, 184, 0.18);
  color: #cbd5e1;
}
.tag-core {
  background: rgba(147, 51, 234, 0.2);
  color: #d8b4fe;
  border: 1px solid rgba(168, 85, 247, 0.35);
}

/* Centered Title & Metadata Body Container */
.picked-body-center {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 20px 10px;
  width: 100%;
}

.picked-title {
  font-size: 26px;
  font-weight: 700;
  color: #f8fafc;
  margin: 0 0 8px 0;
  line-height: 1.25;
  text-align: center !important;
  width: 100%;
}

.picked-meta {
  font-size: 13px;
  color: #94a3b8;
  line-height: 1.4;
  margin-bottom: 0;
  text-align: center !important;
  width: 100%;
}

.picked-notes {
  font-size: 12px;
  color: #cbd5e1;
  background: rgba(0, 0, 0, 0.25);
  padding: 10px 14px;
  border-radius: 6px;
  border-left: 3px solid #38bdf8;
  margin-top: 14px;
  line-height: 1.5;
  text-align: left;
  max-width: 90%;
}

/* Bottom Bar of Picked Card */
.picked-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 16px;
  border-top: 1px solid rgba(255, 255, 255, 0.05);
  margin-top: auto;
}

.btn-reroll {
  background: #0284c7;
  border: 1px solid #38bdf8;
  color: #ffffff;
  height: 32px;
  padding: 0 14px;
  font-size: 11.5px;
  font-weight: 700;
  border-radius: 4px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all var(--transition-fast);
}
.btn-reroll:hover {
  background: #0369a1;
  box-shadow: 0 0 12px rgba(56, 189, 248, 0.35);
}

.record-id-badge {
  font-size: 11px;
  color: #64748b;
  font-family: var(--font-mono, monospace);
}

/* --------------------------------------------------------------------------
   Others Section: Collapsible Matching Gallery Drawer
   -------------------------------------------------------------------------- */
.others-section-wrapper {
  max-width: 980px;
  width: 100%;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.picked-toggle-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #38bdf8;
  font-size: 12.5px;
  font-weight: 700;
  cursor: pointer;
  background: rgba(15, 23, 42, 0.8);
  border: 1px solid rgba(56, 189, 248, 0.35);
  border-radius: 9999px;
  padding: 7px 20px;
  transition: all var(--transition-fast);
  user-select: none;
}
.picked-toggle-link:hover {
  background: var(--panel-hover, #1c273e);
  color: #ffffff;
  border-color: #38bdf8;
  box-shadow: 0 0 12px rgba(56, 189, 248, 0.25);
}

.picked-results-drawer {
  display: none;
  width: 100%;
  margin-top: 14px;
  background: rgba(12, 18, 30, 0.95);
  border: 1px solid var(--border, #1e293b);
  border-radius: 8px;
  padding: 18px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
}
.picked-results-drawer.open {
  display: block;
}

.picked-gallery-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 12px;
  max-height: 480px;
  overflow-y: auto;
  padding-right: 6px;
}
@media (max-width: 1100px) {
  .picked-gallery-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}
@media (max-width: 768px) {
  .picked-gallery-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}
@media (max-width: 480px) {
  .picked-gallery-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* Gallery Small Card */
.gallery-card-thumb {
  display: flex;
  flex-direction: column;
  background: #090e17;
  border: 1px solid var(--border, #1e293b);
  border-radius: 6px;
  padding: 6px;
  cursor: pointer;
  transition: transform var(--transition-fast), border-color var(--transition-fast);
  user-select: none;
}
.gallery-card-thumb:hover {
  transform: translateY(-2px);
  border-color: #38bdf8;
}
.gallery-card-thumb.active-pick {
  border-color: #38bdf8;
  box-shadow: 0 0 10px rgba(56, 189, 248, 0.3);
  background: rgba(56, 189, 248, 0.08);
}

.thumb-image-wrap {
  width: 100%;
  height: 95px;
  background: #0d1522;
  border-radius: 4px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}
.thumb-image-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
}

.thumb-title {
  font-size: 11px;
  font-weight: 600;
  color: #cbd5e1;
  margin-top: 6px;
  line-height: 1.25;
  text-align: center;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  min-height: 26px;
}
</style>

<div class="player-hub-wrapper">

  <!-- ========================================================================
       TOP SEARCH & CRITERIA FORM
       ======================================================================== -->
  <div class="hub-filter-card">

    <!-- Action Toolbar Row -->
    <div class="hub-toolbar-row">
      <div class="hub-btn-group">
        <button type="button" class="btn-hub-action" onclick="resetCriteria()" title="Clear all search filters">
          ↺ Reset
        </button>
        <button type="button" class="btn-hub-action btn-pick-me" onclick="pickGame()" title="Randomly select game from matching titles">
          🎲 Pick For Me
        </button>
      </div>

      <!-- Live Matching Titles Count Display -->
      <span id="matchCountBadge" class="hub-count-pill">
        <?= number_format($initialCount) ?> Titles Found
      </span>
    </div>

    <!-- Contains Keyword Search Field Row (Permanently Visible Above Dropdowns) -->
    <div class="hub-contains-row">
      <div class="criteria-col criteria-col-contains">
        <label class="criteria-label" for="hubContainsInput">Contains</label>
        <div class="contains-input-wrap">
          <span class="contains-search-icon">🔍</span>
          <input type="text"
                 id="hubContainsInput"
                 class="contains-text-input"
                 placeholder="Filter by name, tags or comments..."
                 value="<?= View::e($initialFilters['q'] ?? '') ?>"
                 oninput="onContainsInput(this.value)"
                 onkeydown="if(event.key === 'Enter'){ event.preventDefault(); onContainsEnter(); }"
                 autocomplete="off">
          <button type="button"
                  id="btnContainsClear"
                  class="contains-clear-btn"
                  onclick="clearContainsInput()"
                  title="Clear text"
                  style="<?= empty($initialFilters['q']) ? 'display: none;' : '' ?>">✕</button>
        </div>
      </div>
    </div>

    <!-- Filter Criteria Row: Platform, Category, Subcategory -->
    <div class="hub-criteria-row">
      <!-- 1. Platform Filter -->
      <div class="criteria-col">
        <label class="criteria-label" for="platformSelect">Platform</label>
        <div class="criteria-select-wrap">
          <select id="platformSelect" class="criteria-select" onchange="onCriteriaChange()">
            <option value="">All Platforms</option>
            <?php foreach ($platforms as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= $initialPlatformId === (int)$p['id'] ? 'selected' : '' ?>>
                <?= View::e($p['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="criteria-arrow">▼</span>
        </div>
      </div>

      <!-- 2. Category Filter -->
      <div class="criteria-col">
        <label class="criteria-label" for="categorySelect">Category</label>
        <div class="criteria-select-wrap">
          <select id="categorySelect" class="criteria-select" onchange="onCategoryChange()">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= (int)$cat['id'] ?>">
                <?= View::e($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="criteria-arrow">▼</span>
        </div>
      </div>

      <!-- 3. Subcategory Filter -->
      <div class="criteria-col">
        <label class="criteria-label" for="subcategorySelect">Subcategory</label>
        <div class="criteria-select-wrap">
          <select id="subcategorySelect" class="criteria-select" onchange="onCriteriaChange()">
            <option value="">All Subcategories</option>
            <?php foreach ($subcategories as $sub): ?>
              <option value="<?= (int)$sub['id'] ?>" data-cat="<?= (int)$sub['category_id'] ?>">
                <?= View::e($sub['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="criteria-arrow">▼</span>
        </div>
      </div>
    </div>

    <!-- Showcase Status Breadcrumbs Bar -->
    <div class="showcase-header-bar">
      <span class="showcase-header-title">Game Library Showcase</span>
      <span id="activeFilterBreadcrumbs" class="showcase-header-breadcrumbs">
        Platform: ALL | Category: ALL | Subcat: ALL
      </span>
    </div>

  </div>

  <!-- ========================================================================
       GAME PICK SECTION (Spotlight Game Card)
       ======================================================================== -->
  <div class="picked-display-area" id="pickedDisplayArea">
    <?php if ($initialGame): ?>
      <div class="picked-card-container" id="spotlightCard" data-game-id="<?= (int)$initialGame['id'] ?>">
        <!-- Left Side: Box Art & Screenshot (Fixed size boxes with fallback) -->
        <div class="picked-media-strip">
          <div class="fixed-media-box">
            <span class="media-label-badge">BOX ART</span>
            <img id="cardBoxArt"
                 src="<?= View::e($initialGame['boxart_path']) ?>"
                 alt="<?= View::e($initialGame['title']) ?>"
                 onerror="this.src='/images/support/no_cover.jpg'">
          </div>

          <div class="fixed-media-screenshot">
            <span class="media-label-badge">SCREENSHOT</span>
            <img id="cardScreenshot"
                 src="<?= View::e($initialGame['screenshot_path']) ?>"
                 alt="<?= View::e($initialGame['title']) ?>"
                 onerror="this.src='/images/support/no_screen.jpg'">
          </div>
        </div>

        <!-- Right Side: Metadata, Badges, Title, Meta String -->
        <div class="picked-details">
          <div class="picked-badge-strip" id="cardBadges">
            <!-- Platform / Console Badge -->
            <?php if (!empty($initialGame['console_name'])): ?>
              <span class="tag-pill tag-platform"><?= View::e($initialGame['console_name']) ?></span>
            <?php endif; ?>

            <!-- Console Type Badge -->
            <?php if (!empty($initialGame['console_type_name'])): ?>
              <span class="tag-pill" style="background-color: <?= View::e($initialGame['badge_bg_color']) ?>; color: <?= View::e($initialGame['badge_font_color']) ?>; border: 1px solid <?= View::e($initialGame['badge_font_color']) ?>44;">
                <?= View::e($initialGame['console_type_name']) ?>
              </span>
            <?php endif; ?>

            <!-- Collection Status Badge -->
            <?php if (!empty($initialGame['in_collection'])): ?>
              <span class="tag-pill tag-status-owned">IN COLLECTION</span>
            <?php else: ?>
              <span class="tag-pill tag-status-backlog">IN BACKLOG</span>
            <?php endif; ?>

            <!-- Language Badge -->
            <?php if (!empty($initialGame['language_name'])): ?>
              <span class="tag-pill"><?= strtoupper(View::e($initialGame['language_name'])) ?></span>
            <?php endif; ?>

            <!-- RetroArch Core or Emulator Badge -->
            <?php if (!empty($initialGame['retroarch_core'])): ?>
              <span class="tag-pill tag-core">CORE: <?= strtoupper(View::e($initialGame['retroarch_core'])) ?></span>
            <?php elseif (!empty($initialGame['emulator'])): ?>
              <span class="tag-pill tag-core">EMULATOR: <?= strtoupper(View::e($initialGame['emulator'])) ?></span>
            <?php endif; ?>
          </div>

          <!-- Centered Body: Title and Meta (Platform, Category, etc.) centered on the given space -->
          <div class="picked-body-center">
            <h2 class="picked-title" id="cardTitle"><?= View::e($initialGame['title']) ?></h2>
            <div class="picked-meta" id="cardMeta"><?= View::e($initialGame['meta_string']) ?></div>

            <?php if (!empty($initialGame['comments'])): ?>
              <div class="picked-notes" id="cardNotes"><?= nl2br(View::e($initialGame['comments'])) ?></div>
            <?php endif; ?>
          </div>

          <!-- Bottom Row: Re-roll button & Record ID -->
          <div class="picked-actions">
            <button type="button" class="btn-reroll" onclick="reRollGame()">
              🎲 Re-roll Another
            </button>
            <span class="record-id-badge" id="cardRecordId">
              Database Record #<?= (int)$initialGame['id'] ?>
            </span>
          </div>
        </div>
      </div>
    <?php else: ?>
      <!-- Empty Showcase State -->
      <div class="showcase-empty-state" id="emptyShowcase">
        <span class="showcase-empty-icon">🎮</span>
        <p class="showcase-empty-text">
          Standard selector initialized via Backend API. Ready to connect the game cover grid.
        </p>
      </div>
    <?php endif; ?>
  </div>

  <!-- ========================================================================
       OTHERS SECTION (Matching Games Gallery Grid)
       ======================================================================== -->
  <div class="others-section-wrapper">
    <!-- Collapsible Toggle Button -->
    <button type="button" id="btnToggleOthers" class="picked-toggle-link" onclick="toggleOthers()">
      ▾ View all matching titles (<?= number_format($initialCount) ?>)
    </button>

    <!-- Collapsible Drawer -->
    <div id="othersDrawer" class="picked-results-drawer">
      <div id="galleryGrid" class="picked-gallery-grid">
        <!-- Dynamically populated via JavaScript -->
      </div>
    </div>
  </div>

</div>

<script>
/**
 * Master Subcategories Data for dynamic category cascade
 */
const ALL_SUBCATEGORIES = <?= json_encode($subcategories, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

// State management
let searchDebounceTimer = null;
let currentSearchQuery = '<?= addslashes($initialFilters['q'] ?? '') ?>';
let isOthersOpen = false;
let galleryLoaded = false;
let currentPickedId = <?= !empty($initialGame['id']) ? (int)$initialGame['id'] : 'null' ?>;

document.addEventListener('DOMContentLoaded', () => {
  updateShowcaseBreadcrumb();
});

/**
 * Returns current criteria filters object from DOM inputs.
 */
function getActiveFilters() {
  const containsInput = document.getElementById('hubContainsInput');
  const qVal = containsInput ? containsInput.value.trim() : currentSearchQuery.trim();

  return {
    platform_id: document.getElementById('platformSelect').value || '',
    category_id: document.getElementById('categorySelect').value || '',
    subcategory_id: document.getElementById('subcategorySelect').value || '',
    q: qVal,
  };
}

/**
 * Resets all search filters and criteria to initial state.
 */
function resetCriteria() {
  document.getElementById('platformSelect').value = '';
  document.getElementById('categorySelect').value = '';

  const containsInput = document.getElementById('hubContainsInput');
  if (containsInput) {
    containsInput.value = '';
  }
  const clearBtn = document.getElementById('btnContainsClear');
  if (clearBtn) {
    clearBtn.style.display = 'none';
  }
  currentSearchQuery = '';

  // Reset subcategory dropdown to all
  onCategoryChange();

  updateMatchCount();
  updateShowcaseBreadcrumb();

  // Re-pick from reset pool
  pickGame();

  if (window.showToast) {
    window.showToast('All search filters reset.', 'info');
  }
}

/**
 * Handles text input in Contains box with debounce and real-time count sync.
 */
function onContainsInput(val) {
  currentSearchQuery = val;

  const clearBtn = document.getElementById('btnContainsClear');
  if (clearBtn) {
    clearBtn.style.display = val.trim() ? 'block' : 'none';
  }

  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    updateMatchCount();
    updateShowcaseBreadcrumb();
    if (isOthersOpen) {
      loadGallery();
    }
  }, 220);
}

/**
 * Immediate search/pick when user hits Enter key in Contains field.
 */
function onContainsEnter() {
  clearTimeout(searchDebounceTimer);
  updateMatchCount();
  updateShowcaseBreadcrumb();
  pickGame();
  if (isOthersOpen) {
    loadGallery();
  }
}

/**
 * Clears Contains search box text.
 */
function clearContainsInput() {
  const input = document.getElementById('hubContainsInput');
  if (input) {
    input.value = '';
    input.focus();
  }
  onContainsInput('');
}

/**
 * Handles change of Category dropdown, cascading to Subcategories.
 */
function onCategoryChange() {
  const catId = parseInt(document.getElementById('categorySelect').value, 10) || 0;
  const subSelect = document.getElementById('subcategorySelect');
  const previousSubVal = subSelect.value;

  subSelect.innerHTML = '<option value="">All Subcategories</option>';

  const filteredSubs = catId > 0
    ? ALL_SUBCATEGORIES.filter(s => parseInt(s.category_id, 10) === catId)
    : ALL_SUBCATEGORIES;

  filteredSubs.forEach(s => {
    const opt = document.createElement('option');
    opt.value = String(s.id);
    opt.textContent = s.name;
    if (String(s.id) === previousSubVal) {
      opt.selected = true;
    }
    subSelect.appendChild(opt);
  });

  onCriteriaChange();
}

/**
 * Fired when any criteria dropdown changes.
 */
function onCriteriaChange() {
  updateMatchCount();
  updateShowcaseBreadcrumb();
  if (isOthersOpen) {
    loadGallery();
  }
}

/**
 * Updates the matching titles count display badge in real-time.
 */
async function updateMatchCount() {
  const filters = getActiveFilters();
  const params = new URLSearchParams(filters);

  try {
    const res = await fetch(`/api/player-hub/count?${params.toString()}`);
    const json = await res.json();
    if (json.success && json.data) {
      const count = json.data.count;
      document.getElementById('matchCountBadge').textContent = `${Number(count).toLocaleString()} Titles Found`;

      // Update toggle button count as well
      const toggleBtn = document.getElementById('btnToggleOthers');
      const arrow = isOthersOpen ? '▴ Hide' : '▾ View';
      toggleBtn.textContent = `${arrow} all matching titles (${Number(count).toLocaleString()})`;
    }
  } catch (err) {
    console.error('Count update error:', err);
  }
}

/**
 * Updates the breadcrumb line on the showcase bar.
 */
function updateShowcaseBreadcrumb() {
  const pSelect = document.getElementById('platformSelect');
  const cSelect = document.getElementById('categorySelect');
  const sSelect = document.getElementById('subcategorySelect');
  const containsInput = document.getElementById('hubContainsInput');

  const pName = pSelect && pSelect.selectedIndex > 0 ? pSelect.options[pSelect.selectedIndex].text : 'ALL';
  const cName = cSelect && cSelect.selectedIndex > 0 ? cSelect.options[cSelect.selectedIndex].text : 'ALL';
  const sName = sSelect && sSelect.selectedIndex > 0 ? sSelect.options[sSelect.selectedIndex].text : 'ALL';
  const qVal = containsInput ? containsInput.value.trim() : '';

  let breadcrumb = `Platform: ${pName} | Category: ${cName} | Subcat: ${sName}`;
  if (qVal) {
    breadcrumb += ` | Contains: "${qVal}"`;
  }

  const el = document.getElementById('activeFilterBreadcrumbs');
  if (el) {
    el.textContent = breadcrumb;
  }
}

/**
 * Picks a random game from the matching pool.
 */
async function pickGame(excludeCurrent = false) {
  const filters = getActiveFilters();
  if (excludeCurrent && currentPickedId) {
    filters.exclude_id = currentPickedId;
  }
  const params = new URLSearchParams(filters);

  try {
    const res = await fetch(`/api/player-hub/pick?${params.toString()}`);
    const json = await res.json();

    if (json.success && json.data) {
      const { game, count } = json.data;
      document.getElementById('matchCountBadge').textContent = `${Number(count).toLocaleString()} Titles Found`;

      if (game) {
        currentPickedId = game.id;
        renderGameCard(game);
        if (isOthersOpen) {
          highlightGalleryCard(game.id);
        }
      } else {
        renderEmptyState();
      }
    }
  } catch (err) {
    console.error('Pick Game Error:', err);
  }
}

/**
 * Re-rolls another game avoiding picking the exact same one if multiple matches exist.
 */
function reRollGame() {
  pickGame(true);
}

/**
 * Spotlights a specific game by ID (e.g., clicked in Others gallery grid).
 */
async function spotlightGame(gameId) {
  try {
    const res = await fetch(`/api/player-hub/game/${encodeURIComponent(gameId)}`);
    const json = await res.json();

    if (json.success && json.data && json.data.game) {
      currentPickedId = json.data.game.id;
      renderGameCard(json.data.game);
      highlightGalleryCard(gameId);

      // Smooth scroll back to showcase card if needed
      document.getElementById('pickedDisplayArea').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  } catch (err) {
    console.error('Spotlight Game Error:', err);
  }
}

/**
 * Dynamically renders the Game Card markup.
 */
function renderGameCard(game) {
  const container = document.getElementById('pickedDisplayArea');

  // Build badges HTML
  let badgesHtml = '';
  if (game.console_name) {
    badgesHtml += `<span class="tag-pill tag-platform">${escapeHtml(game.console_name)}</span>`;
  }
  if (game.console_type_name) {
    badgesHtml += `<span class="tag-pill" style="background-color: ${escapeHtml(game.badge_bg_color)}; color: ${escapeHtml(game.badge_font_color)}; border: 1px solid ${escapeHtml(game.badge_font_color)}44;">${escapeHtml(game.console_type_name)}</span>`;
  }
  if (game.in_collection) {
    badgesHtml += `<span class="tag-pill tag-status-owned">IN COLLECTION</span>`;
  } else {
    badgesHtml += `<span class="tag-pill tag-status-backlog">IN BACKLOG</span>`;
  }
  if (game.language_name) {
    badgesHtml += `<span class="tag-pill">${escapeHtml(game.language_name.toUpperCase())}</span>`;
  }
  if (game.retroarch_core) {
    badgesHtml += `<span class="tag-pill tag-core">CORE: ${escapeHtml(game.retroarch_core.toUpperCase())}</span>`;
  } else if (game.emulator) {
    badgesHtml += `<span class="tag-pill tag-core">EMULATOR: ${escapeHtml(game.emulator.toUpperCase())}</span>`;
  }

  // Comments / Notes
  let notesHtml = '';
  if (game.comments && game.comments.trim() !== '') {
    notesHtml = `<div class="picked-notes" id="cardNotes">${escapeHtml(game.comments).replace(/\n/g, '<br>')}</div>`;
  }

  container.innerHTML = `
    <div class="picked-card-container" id="spotlightCard" data-game-id="${game.id}">
      <!-- Left Media Strip (Fixed size boxes) -->
      <div class="picked-media-strip">
        <div class="fixed-media-box">
          <span class="media-label-badge">BOX ART</span>
          <img id="cardBoxArt"
               src="${escapeHtml(game.boxart_path)}"
               alt="${escapeHtml(game.title)}"
               onerror="this.src='/images/support/no_cover.jpg'">
        </div>

        <div class="fixed-media-screenshot">
          <span class="media-label-badge">SCREENSHOT</span>
          <img id="cardScreenshot"
               src="${escapeHtml(game.screenshot_path)}"
               alt="${escapeHtml(game.title)}"
               onerror="this.src='/images/support/no_screen.jpg'">
        </div>
      </div>

      <!-- Right Details Column -->
      <div class="picked-details">
        <div class="picked-badge-strip" id="cardBadges">
          ${badgesHtml}
        </div>

        <!-- Centered Body: Title and Meta (Platform, Category, etc.) centered on the given space -->
        <div class="picked-body-center">
          <h2 class="picked-title" id="cardTitle">${escapeHtml(game.title)}</h2>
          <div class="picked-meta" id="cardMeta">${escapeHtml(game.meta_string)}</div>
          ${notesHtml}
        </div>

        <!-- Lower Actions Row -->
        <div class="picked-actions">
          <button type="button" class="btn-reroll" onclick="reRollGame()">
            🎲 Re-roll Another
          </button>
          <span class="record-id-badge" id="cardRecordId">
            Database Record #${game.id}
          </span>
        </div>
      </div>
    </div>
  `;
}

/**
 * Renders empty state when 0 games fulfill search criteria.
 */
function renderEmptyState() {
  const container = document.getElementById('pickedDisplayArea');
  container.innerHTML = `
    <div class="showcase-empty-state" id="emptyShowcase">
      <span class="showcase-empty-icon">🎮</span>
      <p class="showcase-empty-text">
        No games match the selected criteria. Try resetting filters or expanding keyword search.
      </p>
    </div>
  `;
}

/**
 * --------------------------------------------------------------------------
 * OTHERS SECTION GALLERY DRAWER
 * --------------------------------------------------------------------------
 */

/**
 * Toggles the Others matching titles gallery drawer.
 */
function toggleOthers() {
  const drawer = document.getElementById('othersDrawer');
  const btn = document.getElementById('btnToggleOthers');
  isOthersOpen = !isOthersOpen;

  drawer.classList.toggle('open', isOthersOpen);

  // Update button text and icon
  const currentCountText = document.getElementById('matchCountBadge').textContent.replace(/[^\d]/g, '');
  const countNum = currentCountText ? Number(currentCountText).toLocaleString() : '0';

  if (isOthersOpen) {
    btn.textContent = `▴ Hide matching titles (${countNum})`;
    loadGallery();
  } else {
    btn.textContent = `▾ View all matching titles (${countNum})`;
  }
}

/**
 * Loads and renders the gallery grid of small cards.
 */
async function loadGallery() {
  const grid = document.getElementById('galleryGrid');
  grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 30px; color: #64748b;">Loading matching titles...</div>';

  const filters = getActiveFilters();
  const params = new URLSearchParams(filters);

  try {
    const res = await fetch(`/api/player-hub/games?${params.toString()}`);
    const json = await res.json();

    if (json.success && json.data && Array.isArray(json.data.games)) {
      renderGalleryItems(json.data.games);
    } else {
      grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 30px; color: #64748b;">No matching games found.</div>';
    }
  } catch (err) {
    console.error('Gallery load error:', err);
    grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 30px; color: #ef4444;">Failed to load gallery.</div>';
  }
}

/**
 * Renders small gallery cards into the Others grid.
 */
function renderGalleryItems(games) {
  const grid = document.getElementById('galleryGrid');
  grid.innerHTML = '';

  if (games.length === 0) {
    grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 30px; color: #64748b;">No matching games found.</div>';
    return;
  }

  games.forEach(g => {
    const card = document.createElement('div');
    card.className = `gallery-card-thumb ${g.id === currentPickedId ? 'active-pick' : ''}`;
    card.dataset.gameId = String(g.id);
    card.title = g.title;
    card.onclick = () => spotlightGame(g.id);

    card.innerHTML = `
      <div class="thumb-image-wrap">
        <img src="${escapeHtml(g.screenshot_path)}"
             alt="${escapeHtml(g.title)}"
             loading="lazy"
             onerror="this.src='/images/support/no_screen.jpg'">
      </div>
      <div class="thumb-title">${escapeHtml(g.title)}</div>
    `;

    grid.appendChild(card);
  });
}

/**
 * Highlights the active picked game in the gallery grid.
 */
function highlightGalleryCard(gameId) {
  document.querySelectorAll('.gallery-card-thumb').forEach(c => {
    c.classList.toggle('active-pick', c.dataset.gameId === String(gameId));
  });
}

/**
 * Helper to escape HTML characters.
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
