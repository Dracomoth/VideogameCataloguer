<?php
/**
 * src/Views/games_portal.php
 * Games Portal - Read-only discovery catalog for video game titles, boxart archives, and taxonomies.
 *
 * Variables provided by route / View::render():
 * @var string|null $pageTitle
 * @var string|null $activeNav
 * @var array<int, array<string, mixed>>|null $games
 * @var array<int, array<string, mixed>>|null $consoles
 * @var array<int, array<string, mixed>>|null $categories
 * @var array<int, array<string, mixed>>|null $subcategories
 * @var int|null $initialGameId
 * @var array<string, mixed>|null $initialDetail
 * @var string|null $initialLetter
 */

declare(strict_types=1);

use Vault\Repositories\CategoryRepository;
use Vault\Repositories\ConsoleRepository;
use Vault\Repositories\GameRepository;
use Vault\Repositories\SubcategoryRepository;
use Vault\Services\View;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

// 1. Ensure repository data is available self-contained
if (!isset($games) || !is_array($games)) {
    $gameRepo = new GameRepository();
    $games = $gameRepo->getGamePortalList();
} else {
    $gameRepo = new GameRepository();
}

if (!isset($consoles) || !is_array($consoles)) {
    $conRepo = new ConsoleRepository();
    $consoles = $conRepo->getAll();
}

if (!isset($categories) || !is_array($categories)) {
    $catRepo = new CategoryRepository();
    $categories = $catRepo->getAll();
}

if (!isset($subcategories) || !is_array($subcategories)) {
    $subcatRepo = new SubcategoryRepository();
    $subcategories = $subcatRepo->getAll();
}

// Initial detail resolution if deep-linked
$initialGId = (int)($_GET['id'] ?? ($_GET['game_id'] ?? ($initialGameId ?? 0)));
$initialDet = $initialDetail ?? null;
if ($initialGId > 0 && $initialDet === null) {
    $initialDet = $gameRepo->getGamePortalDetail($initialGId);
}

// Initial URL filters
$initialLet = strtoupper(trim((string)($_GET['letter'] ?? ($initialLetter ?? ''))));

$initialCon = 0;
if (!empty($_GET['console_id']) && is_numeric($_GET['console_id'])) {
    $initialCon = (int)$_GET['console_id'];
} elseif (!empty($_GET['console'])) {
    if (is_numeric($_GET['console'])) {
        $initialCon = (int)$_GET['console'];
    } else {
        $conName = trim((string)$_GET['console']);
        foreach ($consoles as $c) {
            if (strcasecmp((string)($c['name'] ?? ''), $conName) === 0) {
                $initialCon = (int)$c['id'];
                break;
            }
        }
    }
}

$initialCat = 0;
if (!empty($_GET['category_id']) && is_numeric($_GET['category_id'])) {
    $initialCat = (int)$_GET['category_id'];
} elseif (!empty($_GET['category']) && is_numeric($_GET['category'])) {
    $initialCat = (int)$_GET['category'];
}

$initialSub = 0;
if (!empty($_GET['subcategory_id']) && is_numeric($_GET['subcategory_id'])) {
    $initialSub = (int)$_GET['subcategory_id'];
} elseif (!empty($_GET['subcategory']) && is_numeric($_GET['subcategory'])) {
    $initialSub = (int)$_GET['subcategory'];
}

$initialQ = trim((string)($_GET['q'] ?? ($_GET['publisher'] ?? '')));
if ($initialQ === '' && !empty($_GET['publisher_id'])) {
    $targetPubId = (int)$_GET['publisher_id'];
    foreach ($games as $g) {
        if (!empty($g['publisher_id']) && (int)$g['publisher_id'] === $targetPubId && !empty($g['publisher_name'])) {
            $initialQ = (string)$g['publisher_name'];
            break;
        }
    }
    if ($initialQ === '') {
        $pRepo = new \Vault\Repositories\PublisherRepository();
        $pObj = $pRepo->getById($targetPubId);
        if ($pObj && !empty($pObj['name'])) {
            $initialQ = (string)$pObj['name'];
        }
    }
}

$initialOrd = strtolower(trim((string)($_GET['order'] ?? 'asc')));

$safeGamesJson         = json_encode($games, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$safeConsolesJson      = json_encode($consoles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$safeCategoriesJson    = json_encode($categories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$safeSubcategoriesJson = json_encode($subcategories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$safeInitialDetailJson = json_encode($initialDet, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>

<style>
/* --------------------------------------------------------------------------
   Games Portal Scoped Styles (Modern UI / UX Glassmorphic System)
   -------------------------------------------------------------------------- */
.game-portal-wrapper {
  max-width: 1440px;
  width: 100%;
  margin: 0 auto;
  padding: 16px 20px 48px;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 16px;
  color: var(--text-main, #f8fafc);
  overflow-x: hidden;
}

/* Master Header */
.game-portal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  padding-bottom: 4px;
}
.game-portal-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 22px;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: #fff;
}
.game-portal-title span.icon {
  font-size: 24px;
  filter: drop-shadow(0 2px 8px rgba(56, 189, 248, 0.4));
}
.game-portal-subtitle {
  font-size: 13px;
  color: var(--text-muted, #94a3b8);
  font-weight: 400;
  margin-top: 2px;
}

/* 1. Filter Bar (Always visible in both List and Detail modes) */
.game-filter-bar {
  background: rgba(21, 29, 48, 0.85);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-lg, 10px);
  padding: 14px 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
  transition: border-color var(--transition-fast);
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  overflow: hidden;
}
.game-filter-bar:focus-within {
  border-color: rgba(56, 189, 248, 0.4);
}
.game-filter-inputs {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  flex: 1 1 600px;
  min-width: 0;
  max-width: 100%;
}
.game-search-box {
  position: relative;
  flex: 1 1 220px;
  min-width: 180px;
  max-width: 320px;
}
.game-search-box input {
  width: 100%;
  height: 38px;
  background: var(--surface-alt, #0c121e);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-md, 6px);
  padding: 0 34px 0 36px;
  color: #fff;
  font-size: 13px;
  font-family: inherit;
  outline: none;
  transition: all var(--transition-fast);
  box-sizing: border-box;
}
.game-search-box input:focus {
  border-color: var(--border-focus, #38bdf8);
  box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
}
.game-search-box .search-icon {
  position: absolute;
  left: 11px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-dim, #64748b);
  pointer-events: none;
  font-size: 13px;
}
.game-search-box .clear-btn {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--text-dim, #64748b);
  cursor: pointer;
  font-size: 13px;
  padding: 4px;
  line-height: 1;
  display: none;
}
.game-search-box .clear-btn:hover {
  color: #fff;
}

/* Dropdown Filters */
.game-select-filter {
  height: 38px;
  background: var(--surface-alt, #0c121e);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-md, 6px);
  color: var(--text-muted, #94a3b8);
  font-size: 12.5px;
  font-weight: 500;
  padding: 0 28px 0 12px;
  outline: none;
  cursor: pointer;
  appearance: none;
  -webkit-appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%2394a3b8' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 8px center;
  background-size: 16px 16px;
  transition: all var(--transition-fast);
  max-width: 200px;
}
.game-select-filter:hover {
  border-color: rgba(56, 189, 248, 0.4);
  color: #fff;
}
.game-select-filter:focus {
  border-color: var(--border-focus, #38bdf8);
  box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
  color: #fff;
}
.game-select-filter.active-filter {
  border-color: #38bdf8;
  color: #38bdf8;
  background-color: rgba(56, 189, 248, 0.08);
  font-weight: 600;
}

.btn-reset-filters {
  height: 38px;
  padding: 0 12px;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: var(--radius-md, 6px);
  color: #ef4444;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  display: none;
  align-items: center;
  gap: 5px;
  transition: all var(--transition-fast);
}
.btn-reset-filters:hover {
  background: #ef4444;
  color: #fff;
}

.filter-summary-stats {
  font-size: 12px;
  color: var(--text-muted, #94a3b8);
  white-space: normal;
  word-break: break-word;
}

/* 2. Sticky Horizontal Alphabet Pill Ray */
.game-sticky-ray-wrapper {
  position: sticky;
  top: 0;
  z-index: 45;
  width: 100%;
  max-width: 100%;
  margin: 0;
  padding: 8px 12px;
  box-sizing: border-box;
  background: rgba(11, 15, 25, 0.94);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-md, 8px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.45);
}
.game-alphabet-ray {
  display: flex;
  align-items: center;
  gap: 6px;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  overflow-x: auto;
  overflow-y: hidden;
  white-space: nowrap;
  scroll-behavior: smooth;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: thin;
  scrollbar-color: rgba(56, 189, 248, 0.3) transparent;
  padding: 4px 2px;
}
.game-alphabet-ray::-webkit-scrollbar {
  height: 4px;
}
.game-alphabet-ray::-webkit-scrollbar-thumb {
  background: rgba(56, 189, 248, 0.25);
  border-radius: 9999px;
}
.game-alphabet-pill {
  flex: 0 0 auto;
  height: 32px;
  min-width: 34px;
  padding: 0 10px;
  border-radius: var(--radius-pill, 9999px);
  border: 1px solid var(--border, #243049);
  background: var(--panel, #151d30);
  color: var(--text-muted, #94a3b8);
  font-size: 12px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  cursor: pointer;
  outline: none;
  transition: all var(--transition-fast);
  user-select: none;
}
.game-alphabet-pill:hover:not(.disabled) {
  background: var(--panel-hover, #1c273e);
  color: #fff;
  border-color: #38bdf8;
  transform: translateY(-1px);
}
.game-alphabet-pill.active {
  background: var(--accent, #0284c7);
  border-color: #38bdf8;
  color: #ffffff;
  box-shadow: 0 0 14px rgba(56, 189, 248, 0.45);
  transform: translateY(-1px);
}
.game-alphabet-pill.disabled {
  opacity: 0.35;
  cursor: default;
  border-color: rgba(255, 255, 255, 0.04);
}
.game-alphabet-pill.pill-all {
  min-width: 48px;
  font-weight: 700;
  letter-spacing: 0.03em;
}
.game-alphabet-pill.pill-num {
  min-width: 36px;
  font-family: var(--font-mono, monospace);
  font-weight: 700;
}

/* 3. Sub-header Navigation / Status Row */
.game-subnav-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 4px 2px;
  min-height: 42px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}
.game-status-line {
  font-size: 14px;
  font-weight: 500;
  color: var(--text-main, #f8fafc);
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.game-status-badge {
  color: #38bdf8;
  font-weight: 700;
}
.game-status-count {
  color: var(--text-muted, #94a3b8);
  font-weight: 400;
  font-size: 13px;
}

/* Breadcrumb in Detail Mode */
.game-breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 500;
  flex-wrap: wrap;
}
.breadcrumb-btn {
  background: none;
  border: none;
  color: #38bdf8;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: color var(--transition-fast);
}
.breadcrumb-btn:hover {
  color: #7dd3fc;
  text-decoration: underline;
}
.breadcrumb-sep {
  color: var(--text-dim, #64748b);
  font-size: 13px;
}
.breadcrumb-current {
  color: #fff;
  font-weight: 700;
}

/* Action Controls (Sort / Back) */
.game-subnav-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}
.btn-sort-toggle {
  height: 34px;
  padding: 0 14px;
  background: var(--panel, #151d30);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-md, 6px);
  color: var(--text-muted, #94a3b8);
  font-size: 12px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  outline: none;
  transition: all var(--transition-fast);
  user-select: none;
}
.btn-sort-toggle:hover {
  background: var(--panel-hover, #1c273e);
  color: #fff;
  border-color: #38bdf8;
}
.btn-sort-toggle .sort-arrow {
  font-size: 12px;
  color: #38bdf8;
  display: inline-block;
  transition: transform 0.2s ease;
}
.btn-go-back {
  height: 34px;
  padding: 0 16px;
  background: rgba(56, 189, 248, 0.1);
  border: 1px solid rgba(56, 189, 248, 0.35);
  border-radius: var(--radius-md, 6px);
  color: #38bdf8;
  font-size: 12px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  outline: none;
  transition: all var(--transition-fast);
}
.btn-go-back:hover {
  background: #0284c7;
  color: #fff;
  border-color: #0284c7;
  box-shadow: 0 2px 12px rgba(56, 189, 248, 0.3);
  transform: translateX(-2px);
}

/* 4. List Mode Cards Grid (Short Cards: Boxart + Name) */
.game-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: 16px;
  width: 100%;
}
.game-card {
  background: var(--panel, #151d30);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-lg, 10px);
  padding: 10px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 10px;
  cursor: pointer;
  position: relative;
  transition: all var(--transition-fast);
  box-shadow: var(--shadow-sm, 0 1px 2px rgba(0,0,0,0.2));
  overflow: hidden;
  user-select: none;
}
.game-card:hover {
  background: var(--panel-hover, #1c273e);
  border-color: rgba(56, 189, 248, 0.5);
  transform: translateY(-3px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4), 0 0 12px rgba(56, 189, 248, 0.15);
}
.game-card-cover-slot {
  width: 100%;
  height: 200px;
  background: var(--surface-alt, #0c121e);
  border-radius: var(--radius-md, 6px);
  border: 1px solid rgba(255, 255, 255, 0.04);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 6px;
  box-sizing: border-box;
  overflow: hidden;
  position: relative;
}
.game-card-cover-img {
  max-width: 100%;
  max-height: 100%;
  width: auto;
  height: auto;
  object-fit: contain;
  transition: transform var(--transition-fast);
  filter: drop-shadow(0 4px 10px rgba(0,0,0,0.45));
}
.game-card:hover .game-card-cover-img {
  transform: scale(1.04);
}
.game-card-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  width: 100%;
}
.game-card-title {
  font-size: 13px;
  font-weight: 600;
  color: #f8fafc;
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  min-height: 35px;
}
.game-card-submeta {
  font-size: 11px;
  color: var(--text-dim, #64748b);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Empty State */
.game-empty-state {
  grid-column: 1 / -1;
  background: var(--surface-alt, #0c121e);
  border: 1px dashed var(--border, #243049);
  border-radius: var(--radius-lg, 10px);
  padding: 48px 24px;
  text-align: center;
  color: var(--text-muted, #94a3b8);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}
.game-empty-state span.empty-icon {
  font-size: 42px;
  opacity: 0.6;
}

/* ==========================================================================
   5. Detail Mode Layout (Matching Console Portal Pattern)
   ========================================================================== */
.game-detail-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  animation: gameFadeIn 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes gameFadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Main Detail Header Card */
.game-detail-header-card {
  background: var(--panel, #151d30);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-lg, 12px);
  padding: 24px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.35);
}

/* Title Above Everything & Aligned to Left + Status Badge */
.game-detail-title-row {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 14px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.game-detail-name {
  font-size: 21px;
  font-weight: 600;
  color: #ffffff;
  letter-spacing: -0.015em;
  line-height: 1.25;
  margin: 0;
  text-align: left;
}

/* Status Badges (Backlog / Pending with proper colors) */
.badge-status {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 3px 10px;
  border-radius: var(--radius-pill, 9999px);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  line-height: 1.3;
}
.badge-collection-red {
  background: #2b1717;
  color: #f87171;
  border: 1.5px solid #dc2626;
  box-shadow: 0 0 6px rgba(220, 38, 38, 0.25);
}
.badge-collection-amber {
  background: #2c241c;
  color: #fbbf24;
  border: 1.5px solid #c4821a;
  box-shadow: 0 0 6px rgba(196, 130, 26, 0.25);
}
.badge-collection-green {
  background: #142a1e;
  color: #4ade80;
  border: 1.5px solid #16a34a;
  box-shadow: 0 0 6px rgba(22, 163, 74, 0.25);
}

/* Flow Content Area: Left Floated Images Box + Right Specs & Wrapping Description */
.game-detail-content-flow {
  display: block;
  position: relative;
  width: 100%;
}
.game-detail-content-flow::after {
  content: "";
  display: table;
  clear: both;
}

/* Left: Boxed Section with the two images, each in its proper imagebox */
.game-detail-images-box {
  float: left;
  width: 270px;
  min-width: 270px;
  max-width: 290px;
  margin: 0 28px 18px 0;
  background: var(--surface-alt, #0c121e);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-lg, 10px);
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  box-sizing: border-box;
  box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.4);
}
@media (max-width: 768px) {
  .game-detail-images-box {
    float: none;
    width: 100%;
    max-width: 100%;
    min-width: auto;
    margin: 0 0 16px 0;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 10px;
  }
  .game-boxart-imagebox {
    flex: 1 1 135px;
    height: 180px;
  }
  .game-screenshot-imagebox {
    flex: 1 1 135px;
    height: 180px;
  }
}
@media (max-width: 420px) {
  .game-detail-images-box {
    flex-direction: column;
  }
  .game-boxart-imagebox {
    height: 190px;
    width: 100%;
  }
  .game-screenshot-imagebox {
    height: 150px;
    width: 100%;
  }
}

.game-imagebox {
  background: rgba(15, 23, 42, 0.7);
  border: 1px solid rgba(51, 65, 85, 0.45);
  border-radius: var(--radius-md, 8px);
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  overflow: hidden;
  position: relative;
  cursor: pointer;
  transition: border-color var(--transition-fast);
}
.game-imagebox:hover {
  border-color: rgba(56, 189, 248, 0.5);
}
.game-boxart-imagebox {
  height: 210px;
  padding: 10px;
}
.game-boxart-imagebox img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  filter: drop-shadow(0 6px 16px rgba(0, 0, 0, 0.6));
  transition: transform 0.2s ease;
}
.game-boxart-imagebox:hover img {
  transform: scale(1.03);
}

.game-screenshot-imagebox {
  height: 160px;
  padding: 8px;
}
.game-screenshot-imagebox img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  border-radius: 4px;
  filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.5));
  transition: transform 0.2s ease;
}
.game-screenshot-imagebox:hover img {
  transform: scale(1.03);
}

/* Right: Listed Details - NO BOXES, aligned directly next to images */
.game-detail-specs-list {
  display: block;
}
.game-detail-spec-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 0;
  margin-bottom: 14px;
  background: transparent !important;
  border: none !important;
}
.spec-label {
  font-size: 13.5px;
  font-weight: 400;
  text-transform: none;
  letter-spacing: normal;
  color: var(--text-dim, #94a3b8);
  min-width: 175px;
  flex-shrink: 0;
}
.spec-value {
  font-size: 14px;
  font-weight: 500;
  color: #f1f5f9;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}
.game-portal-link {
  color: #38bdf8;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-weight: 500;
  transition: color var(--transition-fast);
}
.game-portal-link:hover {
  color: #7dd3fc;
  text-decoration: underline;
}

/* Tags in Badge Form */
.game-tags-container {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}
.game-tag-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 9px;
  border-radius: var(--radius-pill, 9999px);
  font-size: 11px;
  font-weight: 600;
  background: rgba(56, 189, 248, 0.1);
  color: #7dd3fc;
  border: 1px solid rgba(56, 189, 248, 0.25);
  letter-spacing: 0.01em;
}

/* Description: Under the tags, justified, arranged around images if needed */
.game-detail-desc-block {
  margin-top: 18px;
}
.game-detail-desc-label {
  font-size: 13.5px;
  font-weight: 400;
  color: var(--text-dim, #94a3b8);
  margin-bottom: 8px;
}
.game-detail-desc-text {
  font-size: 14px;
  line-height: 1.75;
  color: #cbd5e1;
  text-align: justify;
  text-justify: inter-word;
  hyphens: auto;
  -webkit-hyphens: auto;
  word-break: break-word;
  white-space: pre-line;
  margin: 0;
}
.game-detail-desc-empty {
  font-size: 13px;
  color: var(--text-dim, #64748b);
  font-style: italic;
  margin: 0;
}

/* ==========================================================================
   2. Structured Section Panels: BIOS & Downloadable Files (Console Portal Style)
   ========================================================================== */
.game-section-panel {
  background: var(--panel, #151d30);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-lg, 12px);
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}
.game-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(51, 65, 85, 0.5);
}
.game-panel-title-group {
  display: flex;
  align-items: center;
  gap: 10px;
}
.game-panel-icon {
  color: #38bdf8;
  font-size: 18px;
  flex-shrink: 0;
}
.game-panel-title {
  font-size: 16px;
  font-weight: 700;
  color: #f8fafc;
  letter-spacing: -0.01em;
  margin: 0;
}
.game-panel-badge {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-dim, #94a3b8);
  background: rgba(30, 41, 59, 0.6);
  padding: 3px 8px;
  border-radius: var(--radius-pill, 9999px);
  border: 1px solid rgba(51, 65, 85, 0.5);
}

/* Compact Structured Files Table View */
.game-files-table-wrapper {
  overflow-x: auto;
  border-radius: var(--radius-md, 8px);
  border: 1px solid rgba(51, 65, 85, 0.4);
  background: var(--surface-alt, #0c121e);
}
.game-files-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 13px;
}
.game-files-table th {
  padding: 10px 14px;
  font-size: 12px;
  font-weight: 500;
  text-transform: none;
  letter-spacing: normal;
  color: var(--text-dim, #94a3b8);
  background: rgba(15, 23, 42, 0.7);
  border-bottom: 1px solid rgba(51, 65, 85, 0.5);
}
.game-files-table td {
  padding: 11px 14px;
  border-bottom: 1px solid rgba(51, 65, 85, 0.3);
  color: #cbd5e1;
  vertical-align: middle;
}
.game-files-table tr:last-child td {
  border-bottom: none;
}
.game-files-table tr:hover td {
  background: rgba(30, 41, 59, 0.35);
}
.file-cell-main {
  display: flex;
  align-items: center;
  gap: 10px;
}
.file-svg-icon {
  color: #38bdf8;
  flex-shrink: 0;
}
.file-name-code {
  font-family: var(--font-mono, ui-monospace, monospace);
  font-weight: 600;
  color: #f8fafc;
  font-size: 13px;
}
.file-desc-text {
  font-size: 12px;
  color: #94a3b8;
}
.file-downloads-pill {
  font-size: 11.5px;
  color: #94a3b8;
  font-variant-numeric: tabular-nums;
}
.btn-file-download-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(56, 189, 248, 0.12);
  border: 1px solid rgba(56, 189, 248, 0.35);
  color: #38bdf8;
  padding: 5px 12px;
  border-radius: var(--radius-md, 6px);
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
  transition: all var(--transition-fast);
  white-space: nowrap;
}
.btn-file-download-action:hover {
  background: #0284c7;
  color: #fff;
  border-color: #0284c7;
  box-shadow: 0 2px 8px rgba(56, 189, 248, 0.3);
  transform: translateY(-1px);
}
.btn-file-download-action:active {
  transform: translateY(0);
}

/* ==========================================================================
   3. Dual Grids (Console Games & Similar Games)
   ========================================================================== */
.game-dual-grids-container {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  width: 100%;
}
@media (max-width: 980px) {
  .game-dual-grids-container {
    grid-template-columns: 1fr;
  }
}

.btn-panel-action {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: rgba(56, 189, 248, 0.1);
  border: 1px solid rgba(56, 189, 248, 0.3);
  color: #38bdf8;
  font-size: 12px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 6px;
  cursor: pointer;
  transition: all var(--transition-fast);
}
.btn-panel-action:hover {
  background: #0284c7;
  color: #fff;
  border-color: #0284c7;
}
.btn-panel-action .action-arrow {
  transition: transform var(--transition-fast);
}
.btn-panel-action:hover .action-arrow {
  transform: translateX(2px);
}

/* Showcase Subgrid for Cards */
.game-showcase-subgrid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(115px, 1fr));
  gap: 12px;
}
.game-mini-card {
  background: var(--surface-alt, #0c121e);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-md, 8px);
  padding: 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 6px;
  cursor: pointer;
  transition: all var(--transition-fast);
  text-decoration: none;
}
.game-mini-card:hover {
  background: var(--panel-hover, #1c273e);
  border-color: #38bdf8;
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
}
.game-mini-thumb-slot {
  width: 100%;
  height: 120px;
  background: rgba(15, 23, 42, 0.6);
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  box-sizing: border-box;
  overflow: hidden;
}
.game-mini-thumb-img {
  max-width: 100%;
  max-height: 100%;
  width: auto;
  height: auto;
  object-fit: contain;
  transition: transform var(--transition-fast);
  filter: drop-shadow(0 2px 6px rgba(0,0,0,0.5));
}
.game-mini-card:hover .game-mini-thumb-img {
  transform: scale(1.05);
}
.game-mini-title {
  font-size: 11px;
  font-weight: 600;
  color: #f8fafc;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  min-height: 28px;
}

/* Loading Overlay */
.game-loading-overlay {
  display: none;
  align-items: center;
  justify-content: center;
  padding: 48px 20px;
  color: #38bdf8;
  font-size: 14px;
  font-weight: 600;
  gap: 10px;
}
.game-spinner {
  width: 24px;
  height: 24px;
  border: 3px solid rgba(56, 189, 248, 0.2);
  border-top-color: #38bdf8;
  border-radius: 50%;
  animation: gameSpin 0.8s linear infinite;
}
@keyframes gameSpin {
  to { transform: rotate(360deg); }
}

/* Lightbox Modal */
.game-lightbox-modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(4, 6, 12, 0.9);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  z-index: 9999;
  align-items: center;
  justify-content: center;
  padding: 24px;
  box-sizing: border-box;
  cursor: zoom-out;
}
.game-lightbox-content {
  position: relative;
  max-width: 90vw;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  cursor: default;
}
.game-lightbox-img {
  max-width: 100%;
  max-height: 82vh;
  object-fit: contain;
  border-radius: 8px;
  box-shadow: 0 12px 48px rgba(0, 0, 0, 0.8), 0 0 24px rgba(56, 189, 248, 0.2);
}
.game-lightbox-caption {
  margin-top: 10px;
  color: #e2e8f0;
  font-size: 14px;
  font-weight: 600;
  text-align: center;
}
.game-lightbox-close {
  position: absolute;
  top: -14px;
  right: -14px;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: #fff;
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}
.game-lightbox-close:hover {
  background: #ef4444;
  border-color: #ef4444;
}

/* Mobile responsive adjustments */
@media (max-width: 768px) {
  .game-portal-wrapper {
    padding: 12px 10px 40px;
    gap: 12px;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow-x: hidden;
  }
  .game-portal-title {
    font-size: 20px;
  }
  .game-portal-subtitle {
    font-size: 12px;
  }
  .game-filter-bar {
    padding: 12px;
  }
  .game-filter-inputs {
    flex-direction: column;
    align-items: stretch;
    width: 100%;
    gap: 8px;
    flex: 1 1 100%;
  }
  .game-search-box {
    min-width: 100%;
    max-width: 100%;
    width: 100%;
    flex: 1 1 auto;
  }
  .game-select-filter {
    min-width: 100%;
    max-width: 100%;
    width: 100%;
    flex: 1 1 auto;
    height: 40px;
    font-size: 13px;
  }
  .btn-reset-filters {
    width: 100%;
    justify-content: center;
    height: 38px;
  }
  .filter-summary-stats {
    width: 100%;
    text-align: right;
    font-size: 11.5px;
  }
  .game-sticky-ray-wrapper {
    margin: 0;
    padding: 6px 10px;
    width: 100%;
    max-width: 100%;
  }
  .game-alphabet-pill {
    height: 30px;
    min-width: 32px;
    padding: 0 8px;
    font-size: 12px;
  }
  .game-subnav-row {
    flex-wrap: wrap;
    gap: 8px;
    min-height: auto;
    padding: 6px 0;
  }
  .game-status-line {
    font-size: 13px;
  }
  .game-breadcrumb {
    font-size: 13px;
    gap: 6px;
  }
  .game-cards-grid {
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 10px;
  }
  .game-card-cover-slot {
    height: 160px;
  }
  .game-card-title {
    font-size: 12px;
    min-height: 32px;
  }
  .game-detail-header-card {
    padding: 16px 14px;
  }
  .game-detail-title-row {
    gap: 10px;
    margin-bottom: 14px;
    justify-content: flex-start;
  }
  .game-detail-name {
    font-size: 19px;
  }
  .game-detail-spec-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 3px;
    margin-bottom: 12px;
  }
  .game-detail-spec-row .spec-label {
    min-width: unset;
    font-size: 12px;
  }
  .game-detail-spec-row .spec-value {
    font-size: 13.5px;
  }
  .game-detail-desc-block {
    margin-top: 14px;
  }
  .game-section-panel {
    padding: 16px 14px;
  }
  .game-files-table th, 
  .game-files-table td {
    padding: 8px 10px;
    font-size: 12px;
  }
  .game-showcase-subgrid {
    grid-template-columns: repeat(auto-fill, minmax(95px, 1fr));
    gap: 8px;
  }
  .game-mini-thumb-slot {
    height: 100px;
  }
  .game-lightbox-close {
    top: 12px;
    right: 12px;
    position: fixed;
    width: 36px;
    height: 36px;
  }
}
</style>

<div class="game-portal-wrapper">
  <!-- Top Portal Header -->
  <header class="game-portal-header">
    <div>
      <div class="game-portal-title">
        <span class="icon">🕹️</span>
        <span>Games Portal</span>
      </div>
      <div class="game-portal-subtitle">Explore the complete video game library, box art archives, and platform catalog</div>
    </div>
  </header>

  <!-- 1. Filter Bar (Always visible in both List and Detail modes) -->
  <section class="game-filter-bar" aria-label="Game Catalog Filters">
    <div class="game-filter-inputs">
      <!-- Search Input -->
      <div class="game-search-box">
        <span class="search-icon">🔍</span>
        <input 
          type="text" 
          id="gameSearchInput" 
          placeholder="Filter games by title or keywords..." 
          autocomplete="off"
          spellcheck="false"
          value="<?= View::e($initialQ) ?>"
        >
        <button type="button" id="gameClearSearchBtn" class="clear-btn" title="Clear filter">✕</button>
      </div>

      <!-- Console Filter -->
      <select id="gameConsoleFilter" class="game-select-filter" aria-label="Filter by Console">
        <option value="0">All Consoles</option>
        <?php foreach ($consoles as $con): ?>
          <option value="<?= (int)$con['id'] ?>" <?= $initialCon === (int)$con['id'] ? 'selected' : '' ?>>
            <?= View::e($con['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <!-- Category Filter -->
      <select id="gameCategoryFilter" class="game-select-filter" aria-label="Filter by Category">
        <option value="0">All Categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= (int)$cat['id'] ?>" <?= $initialCat === (int)$cat['id'] ? 'selected' : '' ?>>
            <?= View::e($cat['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <!-- Subcategory Filter -->
      <select id="gameSubcategoryFilter" class="game-select-filter" aria-label="Filter by Subcategory">
        <option value="0">All Subcategories</option>
        <?php foreach ($subcategories as $sub): ?>
          <option 
            value="<?= (int)$sub['id'] ?>" 
            data-category="<?= (int)$sub['category_id'] ?>"
            <?= $initialSub === (int)$sub['id'] ? 'selected' : '' ?>
          >
            <?= View::e($sub['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <!-- Reset Filters Button -->
      <button type="button" id="btnResetFilters" class="btn-reset-filters" title="Reset all filters">
        <span>✕</span>
        <span>Clear Filters</span>
      </button>
    </div>

    <!-- Summary Stats -->
    <div class="filter-summary-stats" id="gameFilterStatsText">
      Loading games library...
    </div>
  </section>

  <!-- 2. Always Visible Sticky Horizontal Alphabet Pill Ray -->
  <nav class="game-sticky-ray-wrapper" aria-label="Alphabetical Index">
    <div class="game-alphabet-ray" id="gameAlphabetPillRay">
      <!-- Filled dynamically by JavaScript -->
    </div>
  </nav>

  <!-- 3. Sub-header Navigation / Status Row -->
  <div class="game-subnav-row" id="gameSubnavRow">
    <!-- Left: Status Line (List Mode) OR Breadcrumb (Detail Mode) -->
    <div id="gameSubnavLeft">
      <div class="game-status-line" id="gameListStatusLine">
        <span>Showing:</span>
        <span class="game-status-badge" id="gameSelectedLetterLabel">All Games</span>
        <span class="game-status-count" id="gameGamesCountLabel">(0 games)</span>
      </div>
      <nav class="game-breadcrumb" id="gameDetailBreadcrumb" style="display: none;" aria-label="Breadcrumb">
        <button type="button" class="breadcrumb-btn" id="breadcrumbLetterBtn">All</button>
        <span class="breadcrumb-sep">›</span>
        <span class="breadcrumb-current" id="breadcrumbGameTitle">Game Detail</span>
      </nav>
    </div>

    <!-- Right: Sort Order Button (List Mode) OR Go Back Button (Detail Mode) -->
    <div class="game-subnav-actions" id="gameSubnavRight">
      <button type="button" class="btn-sort-toggle" id="gameSortOrderToggleBtn" title="Toggle alphabetical sort order">
        <span>Sort:</span>
        <span id="gameSortDirectionLabel"><?= $initialOrd === 'desc' ? 'Z → A' : 'A → Z' ?></span>
        <span class="sort-arrow" id="gameSortArrowIcon"><?= $initialOrd === 'desc' ? '▼' : '▲' ?></span>
      </button>
      <button type="button" class="btn-go-back" id="gameBtnGoBack" style="display: none;" title="Return to games catalog">
        <span>←</span>
        <span>Back to List</span>
      </button>
    </div>
  </div>

  <!-- Loading Indicator -->
  <div class="game-loading-overlay" id="gameLoadingOverlay">
    <div class="game-spinner"></div>
    <span>Loading game details...</span>
  </div>

  <!-- 4. Mode 1: List Mode (Cards Grid) -->
  <section id="gameListContainer" aria-label="Games Catalog">
    <div class="game-cards-grid" id="gamesCardsGrid">
      <!-- Cards rendered dynamically -->
    </div>
  </section>

  <!-- 5. Mode 2: Detail Mode -->
  <section id="gameDetailContainer" class="game-detail-view" style="display: none;" aria-label="Game Details">
    <!-- 1. Header Card (Title Row with Badge, Left Images Box, Right Specs & Wrapping Description) -->
    <div class="game-detail-header-card">
      <!-- Title Row: Title Above Everything, Aligned to the Left + Collection Status Badge -->
      <div class="game-detail-title-row">
        <h1 class="game-detail-name" id="detailGameTitle">Game Title</h1>
        <span class="badge-status badge-collection-green" id="detailGameStatusBadge">BACKLOG</span>
      </div>

      <!-- Main Content Flow: Left Floated Images Box + Right Specs + Description wrapping around images if needed -->
      <div class="game-detail-content-flow">
        <!-- Left: Boxed Section with the two images, each in its proper imagebox -->
        <div class="game-detail-images-box">
          <!-- Imagebox 1: Box Art -->
          <div class="game-imagebox game-boxart-imagebox" id="detailBoxartBox" title="Click to view Box Art full size">
            <img id="detailBoxartImg" src="/images/support/no_cover.jpg" alt="Box Art">
          </div>
          <!-- Imagebox 2: Game Screenshot -->
          <div class="game-imagebox game-screenshot-imagebox" id="detailScreenshotBox" title="Click to view Screenshot full size">
            <img id="detailScreenshotImg" src="/images/support/no_screen.jpg" alt="Screenshot">
          </div>
        </div>

        <!-- Right: The details, listed + Description -->
        <div class="game-detail-specs-list">
          <!-- Maker / Publisher -->
          <div class="game-detail-spec-row">
            <span class="spec-label">Maker / Publisher</span>
            <span class="spec-value">
              <a href="#" class="game-portal-link" id="detailMakerLink" title="View in Publishers Portal">
                <span id="detailMakerValue">—</span>
                <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13">
                  <path fill-rule="evenodd" d="M5.22 14.78a.75.75 0 001.06 0l7.22-7.22v5.69a.75.75 0 001.5 0v-7.5a.75.75 0 00-.75-.75h-7.5a.75.75 0 000 1.5h5.69l-7.22 7.22a.75.75 0 000 1.06z" clip-rule="evenodd"/>
                </svg>
              </a>
            </span>
          </div>

          <!-- Console -->
          <div class="game-detail-spec-row">
            <span class="spec-label">Console</span>
            <span class="spec-value">
              <a href="#" class="game-portal-link" id="detailConsoleLink" title="View in Consoles Portal">
                <span id="detailConsoleValue">—</span>
                <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13">
                  <path fill-rule="evenodd" d="M5.22 14.78a.75.75 0 001.06 0l7.22-7.22v5.69a.75.75 0 001.5 0v-7.5a.75.75 0 00-.75-.75h-7.5a.75.75 0 000 1.5h5.69l-7.22 7.22a.75.75 0 000 1.06z" clip-rule="evenodd"/>
                </svg>
              </a>
            </span>
          </div>

          <!-- Year -->
          <div class="game-detail-spec-row">
            <span class="spec-label">Release Year</span>
            <span class="spec-value" id="detailYearValue">—</span>
          </div>

          <!-- Category • Subcategory -->
          <div class="game-detail-spec-row">
            <span class="spec-label">Category</span>
            <span class="spec-value" id="detailCategoryValue">—</span>
          </div>

          <!-- Tags in badge form -->
          <div class="game-detail-spec-row">
            <span class="spec-label">Tags</span>
            <span class="spec-value">
              <div class="game-tags-container" id="detailTagsContainer">
                <!-- Badges populated dynamically -->
              </div>
            </span>
          </div>

          <!-- Description: Under the tags, justified, arranged around images if needed -->
          <div class="game-detail-desc-block" id="detailDescriptionBlock">
            <div class="game-detail-desc-label">Description</div>
            <p class="game-detail-desc-text" id="detailCommentsText"></p>
            <p class="game-detail-desc-empty" id="detailCommentsEmpty" style="display: none;">No description recorded for this entry.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- 2. Attached Files & Downloads (Separate Section Panel, matching Console Portal) -->
    <section class="game-section-panel" id="detailDownloadsSubsection" style="display: none;">
      <div class="game-panel-header">
        <div class="game-panel-title-group">
          <svg class="game-panel-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
          <h2 class="game-panel-title">Attached Files & Downloads</h2>
        </div>
        <span class="game-panel-badge" id="detailFilesCountBadge">0 files</span>
      </div>

      <!-- Compact Structured File Table -->
      <div class="game-files-table-wrapper">
        <table class="game-files-table">
          <thead>
            <tr>
              <th>File Name</th>
              <th>Description / Type</th>
              <th>Downloads</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody id="detailFilesList">
            <!-- Files rendered dynamically -->
          </tbody>
        </table>
      </div>
    </section>

    <!-- 3. Dual Grids (Console Games & Similar Games) - NO Random... texts, NO 15 titles badges -->
    <div class="game-dual-grids-container">
      <!-- Grid 1: Console Games -->
      <section class="game-section-panel">
        <div class="game-panel-header">
          <div class="game-panel-title-group">
            <span class="game-panel-icon">💻</span>
            <h3 class="game-panel-title" id="consoleGamesPanelTitle">More for this Console</h3>
          </div>
          <button type="button" class="btn-panel-action" id="btnViewAllConsoleGames" title="View all games for this console">
            <span>View all</span>
            <span class="action-arrow">→</span>
          </button>
        </div>
        <div class="game-showcase-subgrid" id="consoleGamesSubgrid">
          <!-- Console cards rendered dynamically -->
        </div>
      </section>

      <!-- Grid 2: Similar Games -->
      <section class="game-section-panel">
        <div class="game-panel-header">
          <div class="game-panel-title-group">
            <span class="game-panel-icon">🎲</span>
            <h3 class="game-panel-title" id="similarGamesPanelTitle">Similar Games</h3>
          </div>
          <button type="button" class="btn-panel-action" id="btnViewAllSimilarGames" title="View all games with this category and subcategory">
            <span>View all</span>
            <span class="action-arrow">→</span>
          </button>
        </div>
        <div class="game-showcase-subgrid" id="similarGamesSubgrid">
          <!-- Similar cards rendered dynamically -->
        </div>
      </section>
    </div>
  </section>
</div>

<!-- Image Lightbox Modal -->
<div class="game-lightbox-modal" id="gameLightboxModal" role="dialog" aria-modal="true" aria-label="Media Preview">
  <div class="game-lightbox-content" id="gameLightboxContent">
    <button type="button" class="game-lightbox-close" id="gameLightboxCloseBtn" aria-label="Close Preview">✕</button>
    <img id="gameLightboxImg" class="game-lightbox-img" src="" alt="Media Preview">
    <div id="gameLightboxCaption" class="game-lightbox-caption"></div>
  </div>
</div>

<script>
/**
 * Games Portal Discovery Catalog Engine
 * Interactive client-side controller supporting Instant Search, Sticky Alphabet Pill Ray,
 * Taxonomy Cascades, Detail Mode, Breadcrumb Navigation, and Dual Recommendation Grids.
 */
(function() {
  'use strict';

  // Injected server-side state
  const ALL_GAMES         = <?= $safeGamesJson ?: '[]' ?>;
  const ALL_CONSOLES      = <?= $safeConsolesJson ?: '[]' ?>;
  const ALL_CATEGORIES    = <?= $safeCategoriesJson ?: '[]' ?>;
  const ALL_SUBCATEGORIES = <?= $safeSubcategoriesJson ?: '[]' ?>;
  const INITIAL_DETAIL    = <?= $safeInitialDetailJson ?: 'null' ?>;

  // Active Controller State
  let currentMode               = 'list'; // 'list' | 'detail'
  let currentLetter             = '<?= View::e($initialLet !== '' ? $initialLet : 'ALL') ?>';
  let currentSearchQuery        = '<?= View::e($initialQ) ?>';
  let currentConsoleFilter      = <?= $initialCon ?>;
  let currentCategoryFilter     = <?= $initialCat ?>;
  let currentSubcategoryFilter  = <?= $initialSub ?>;
  let currentSortAsc            = <?= $initialOrd === 'desc' ? 'false' : 'true' ?>;
  let activeGame                = null;
  let activeDetailData          = null;

  // DOM Elements - Master Controls
  const gameSearchInput         = document.getElementById('gameSearchInput');
  const gameClearSearchBtn      = document.getElementById('gameClearSearchBtn');
  const gameConsoleFilter       = document.getElementById('gameConsoleFilter');
  const gameCategoryFilter      = document.getElementById('gameCategoryFilter');
  const gameSubcategoryFilter   = document.getElementById('gameSubcategoryFilter');
  const btnResetFilters         = document.getElementById('btnResetFilters');
  const gameFilterStatsText     = document.getElementById('gameFilterStatsText');

  // DOM Elements - Pill Ray & Navigation
  const gameAlphabetPillRay     = document.getElementById('gameAlphabetPillRay');
  const gameListStatusLine      = document.getElementById('gameListStatusLine');
  const gameSelectedLetterLabel = document.getElementById('gameSelectedLetterLabel');
  const gameGamesCountLabel     = document.getElementById('gameGamesCountLabel');
  const gameDetailBreadcrumb    = document.getElementById('gameDetailBreadcrumb');
  const breadcrumbLetterBtn     = document.getElementById('breadcrumbLetterBtn');
  const breadcrumbGameTitle     = document.getElementById('breadcrumbGameTitle');
  const gameSortOrderToggleBtn  = document.getElementById('gameSortOrderToggleBtn');
  const gameSortDirectionLabel  = document.getElementById('gameSortDirectionLabel');
  const gameSortArrowIcon       = document.getElementById('gameSortArrowIcon');
  const gameBtnGoBack           = document.getElementById('gameBtnGoBack');
  const gameLoadingOverlay      = document.getElementById('gameLoadingOverlay');

  // DOM Elements - Containers
  const gameListContainer       = document.getElementById('gameListContainer');
  const gamesCardsGrid          = document.getElementById('gamesCardsGrid');
  const gameDetailContainer     = document.getElementById('gameDetailContainer');

  // DOM Elements - Detail Card
  const detailBoxartBox         = document.getElementById('detailBoxartBox');
  const detailBoxartImg         = document.getElementById('detailBoxartImg');
  const detailScreenshotBox     = document.getElementById('detailScreenshotBox');
  const detailScreenshotImg     = document.getElementById('detailScreenshotImg');
  const detailGameTitle         = document.getElementById('detailGameTitle');
  const detailGameStatusBadge   = document.getElementById('detailGameStatusBadge');
  const detailMakerLink         = document.getElementById('detailMakerLink');
  const detailMakerValue        = document.getElementById('detailMakerValue');
  const detailConsoleLink       = document.getElementById('detailConsoleLink');
  const detailConsoleValue      = document.getElementById('detailConsoleValue');
  const detailYearValue         = document.getElementById('detailYearValue');
  const detailCategoryValue     = document.getElementById('detailCategoryValue');
  const detailTagsContainer     = document.getElementById('detailTagsContainer');
  const detailCommentsText      = document.getElementById('detailCommentsText');
  const detailCommentsEmpty     = document.getElementById('detailCommentsEmpty');

  // DOM Elements - Downloads Subsection
  const detailDownloadsSubsection = document.getElementById('detailDownloadsSubsection');
  const detailFilesCountBadge     = document.getElementById('detailFilesCountBadge');
  const detailFilesList           = document.getElementById('detailFilesList');

  // DOM Elements - Dual Recommendation Grids
  const consoleGamesPanelTitle  = document.getElementById('consoleGamesPanelTitle');
  const btnViewAllConsoleGames  = document.getElementById('btnViewAllConsoleGames');
  const consoleGamesSubgrid     = document.getElementById('consoleGamesSubgrid');

  const similarGamesPanelTitle  = document.getElementById('similarGamesPanelTitle');
  const btnViewAllSimilarGames  = document.getElementById('btnViewAllSimilarGames');
  const similarGamesSubgrid     = document.getElementById('similarGamesSubgrid');

  // DOM Elements - Lightbox
  const gameLightboxModal       = document.getElementById('gameLightboxModal');
  const gameLightboxImg         = document.getElementById('gameLightboxImg');
  const gameLightboxCaption     = document.getElementById('gameLightboxCaption');
  const gameLightboxCloseBtn    = document.getElementById('gameLightboxCloseBtn');

  // Constant Asset Fallbacks
  const FALLBACK_BOXART     = '/images/support/no_cover.jpg';
  const FALLBACK_SCREENSHOT = '/images/support/no_screen.jpg';

  /**
   * Initializes the Games Portal application
   */
  function init() {
    setupAlphabetPills();
    setupSubcategoryCascade();
    setupEventListeners();

    // Client-side fallback check from URLSearchParams (e.g. if navigated client-side)
    if (!currentSearchQuery) {
      const urlParams = new URLSearchParams(window.location.search);
      const urlQ = urlParams.get('q') || urlParams.get('publisher');
      if (urlQ) {
        currentSearchQuery = urlQ.trim();
      } else if (urlParams.get('publisher_id')) {
        const pubId = Number(urlParams.get('publisher_id'));
        const foundGame = ALL_GAMES.find(g => Number(g.publisher_id) === pubId && g.publisher_name);
        if (foundGame && foundGame.publisher_name) {
          currentSearchQuery = foundGame.publisher_name;
        }
      }
      if (currentSearchQuery && gameSearchInput) {
        gameSearchInput.value = currentSearchQuery;
      }
    }

    if (gameSearchInput && gameSearchInput.value.trim() !== '') {
      gameClearSearchBtn.style.display = 'block';
    }

    updateActiveFilterStyles();

    // Deep link or initial detail mode check
    if (INITIAL_DETAIL && INITIAL_DETAIL.game) {
      openGameDetail(INITIAL_DETAIL.game, INITIAL_DETAIL);
    } else {
      renderGamesList();
    }

    window.addEventListener('popstate', onPopState);
  }

  /**
   * Extracts uppercase leading index character for pill grouping
   */
  function getLeadingKey(title) {
    if (!title || typeof title !== 'string') return '#';
    const trimmed = title.trim();
    if (!trimmed) return '#';
    const firstChar = trimmed.charAt(0).toUpperCase();
    if (firstChar >= 'A' && firstChar <= 'Z') {
      return firstChar;
    }
    return '#';
  }

  /**
   * Builds the Sticky Horizontal Alphabet Pill Ray [All] [#] [A-Z]
   */
  function setupAlphabetPills() {
    gameAlphabetPillRay.innerHTML = '';

    const letterCounts = {};
    ALL_GAMES.forEach(g => {
      const k = getLeadingKey(g.title);
      letterCounts[k] = (letterCounts[k] || 0) + 1;
    });

    const pills = ['ALL', '#'];
    for (let i = 65; i <= 90; i++) {
      pills.push(String.fromCharCode(i));
    }

    const fragment = document.createDocumentFragment();

    pills.forEach(p => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.dataset.letter = p;

      let label = p;
      if (p === 'ALL') {
        btn.className = 'game-alphabet-pill pill-all';
        label = 'All';
      } else if (p === '#') {
        btn.className = 'game-alphabet-pill pill-num';
      } else {
        btn.className = 'game-alphabet-pill';
      }

      const count = p === 'ALL' ? ALL_GAMES.length : (letterCounts[p] || 0);
      if (p !== 'ALL' && count === 0) {
        btn.classList.add('disabled');
        btn.title = `No games beginning with ${p}`;
      } else {
        btn.title = `${label} (${count} ${count === 1 ? 'game' : 'games'})`;
      }

      if (p === currentLetter) {
        btn.classList.add('active');
      }

      btn.textContent = label;

      btn.addEventListener('click', () => {
        onLetterSelected(p);
      });

      fragment.appendChild(btn);
    });

    gameAlphabetPillRay.appendChild(fragment);
  }

  /**
   * Cascades subcategories select based on selected category
   */
  function setupSubcategoryCascade() {
    const selectedCat = Number(gameCategoryFilter.value);
    const subOptions = gameSubcategoryFilter.querySelectorAll('option');

    let matchingSelectedStillValid = false;

    subOptions.forEach(opt => {
      if (opt.value === '0') {
        opt.style.display = '';
        return;
      }
      const optCat = Number(opt.dataset.category || 0);
      if (selectedCat === 0 || optCat === selectedCat) {
        opt.style.display = '';
        if (Number(opt.value) === currentSubcategoryFilter) {
          matchingSelectedStillValid = true;
        }
      } else {
        opt.style.display = 'none';
      }
    });

    if (!matchingSelectedStillValid && currentSubcategoryFilter > 0) {
      gameSubcategoryFilter.value = '0';
      currentSubcategoryFilter = 0;
    }
  }

  /**
   * Attaches interactive DOM listeners
   */
  function setupEventListeners() {
    // 1. Text Search Input with debouncing
    let searchTimeout = null;
    gameSearchInput.addEventListener('input', (e) => {
      const q = e.target.value;
      gameClearSearchBtn.style.display = q.trim() !== '' ? 'block' : 'none';

      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        currentSearchQuery = q.trim();
        updateActiveFilterStyles();
        if (currentMode === 'detail') {
          setMode('list');
        } else {
          renderGamesList();
          updateUrlState();
        }
      }, 150);
    });

    // 2. Clear Search Button
    gameClearSearchBtn.addEventListener('click', () => {
      gameSearchInput.value = '';
      currentSearchQuery = '';
      gameClearSearchBtn.style.display = 'none';
      updateActiveFilterStyles();
      if (currentMode === 'detail') {
        setMode('list');
      } else {
        renderGamesList();
        updateUrlState();
      }
      gameSearchInput.focus();
    });

    // 3. Console Filter Dropdown
    gameConsoleFilter.addEventListener('change', (e) => {
      currentConsoleFilter = Number(e.target.value);
      updateActiveFilterStyles();
      if (currentMode === 'detail') {
        setMode('list');
      } else {
        renderGamesList();
        updateUrlState();
      }
    });

    // 4. Category Filter Dropdown
    gameCategoryFilter.addEventListener('change', (e) => {
      currentCategoryFilter = Number(e.target.value);
      setupSubcategoryCascade();
      updateActiveFilterStyles();
      if (currentMode === 'detail') {
        setMode('list');
      } else {
        renderGamesList();
        updateUrlState();
      }
    });

    // 5. Subcategory Filter Dropdown
    gameSubcategoryFilter.addEventListener('change', (e) => {
      currentSubcategoryFilter = Number(e.target.value);
      updateActiveFilterStyles();
      if (currentMode === 'detail') {
        setMode('list');
      } else {
        renderGamesList();
        updateUrlState();
      }
    });

    // 6. Reset Filters Button
    btnResetFilters.addEventListener('click', () => {
      currentSearchQuery = '';
      currentConsoleFilter = 0;
      currentCategoryFilter = 0;
      currentSubcategoryFilter = 0;
      currentLetter = 'ALL';

      gameSearchInput.value = '';
      gameClearSearchBtn.style.display = 'none';
      gameConsoleFilter.value = '0';
      gameCategoryFilter.value = '0';
      setupSubcategoryCascade();
      gameSubcategoryFilter.value = '0';

      const pills = gameAlphabetPillRay.querySelectorAll('.game-alphabet-pill');
      pills.forEach(p => p.classList.toggle('active', p.dataset.letter === 'ALL'));

      updateActiveFilterStyles();
      if (currentMode === 'detail') {
        setMode('list');
      } else {
        renderGamesList();
        updateUrlState();
      }
    });

    // 7. Sort Order Toggle Button
    gameSortOrderToggleBtn.addEventListener('click', () => {
      currentSortAsc = !currentSortAsc;
      gameSortDirectionLabel.textContent = currentSortAsc ? 'A → Z' : 'Z → A';
      gameSortArrowIcon.textContent = currentSortAsc ? '▲' : '▼';
      gameSortArrowIcon.style.transform = currentSortAsc ? 'rotate(0deg)' : 'rotate(180deg)';
      renderGamesList();
      updateUrlState();
    });

    // 8. Go Back Button in Detail Mode
    gameBtnGoBack.addEventListener('click', () => {
      setMode('list');
    });

    // 9. Breadcrumb Letter Button in Detail Mode
    breadcrumbLetterBtn.addEventListener('click', () => {
      setMode('list');
    });

    // 10. Lightbox Triggers on Detail Boxart and Screenshot
    detailBoxartBox.addEventListener('click', () => {
      if (detailBoxartImg.src) {
        openLightbox(detailBoxartImg.src, `${activeGame ? activeGame.title : 'Game'} - Box Art`);
      }
    });
    detailScreenshotBox.addEventListener('click', () => {
      if (detailScreenshotImg.src) {
        openLightbox(detailScreenshotImg.src, `${activeGame ? activeGame.title : 'Game'} - Screenshot`);
      }
    });

    // Lightbox Close Handlers
    gameLightboxCloseBtn.addEventListener('click', closeLightbox);
    gameLightboxModal.addEventListener('click', (e) => {
      if (e.target === gameLightboxModal) {
        closeLightbox();
      }
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeLightbox();
      }
    });

    // 11. View All Action for Console Games Grid
    btnViewAllConsoleGames.addEventListener('click', () => {
      if (activeGame && activeGame.console_id) {
        currentConsoleFilter = Number(activeGame.console_id);
        gameConsoleFilter.value = String(currentConsoleFilter);
        currentLetter = 'ALL';
        currentSearchQuery = '';
        gameSearchInput.value = '';
        gameClearSearchBtn.style.display = 'none';

        const pills = gameAlphabetPillRay.querySelectorAll('.game-alphabet-pill');
        pills.forEach(p => p.classList.toggle('active', p.dataset.letter === 'ALL'));

        updateActiveFilterStyles();
        setMode('list');
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });

    // 12. View All Action for Similar Games Grid
    btnViewAllSimilarGames.addEventListener('click', () => {
      if (activeGame && activeGame.category_id) {
        currentCategoryFilter = Number(activeGame.category_id);
        gameCategoryFilter.value = String(currentCategoryFilter);
        setupSubcategoryCascade();

        if (activeGame.subcategory_id) {
          currentSubcategoryFilter = Number(activeGame.subcategory_id);
          gameSubcategoryFilter.value = String(currentSubcategoryFilter);
        } else {
          currentSubcategoryFilter = 0;
          gameSubcategoryFilter.value = '0';
        }

        currentLetter = 'ALL';
        currentSearchQuery = '';
        gameSearchInput.value = '';
        gameClearSearchBtn.style.display = 'none';

        const pills = gameAlphabetPillRay.querySelectorAll('.game-alphabet-pill');
        pills.forEach(p => p.classList.toggle('active', p.dataset.letter === 'ALL'));

        updateActiveFilterStyles();
        setMode('list');
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
  }

  /**
   * Updates styling indicators on filters
   */
  function updateActiveFilterStyles() {
    const hasConsole = currentConsoleFilter > 0;
    const hasCategory = currentCategoryFilter > 0;
    const hasSubcategory = currentSubcategoryFilter > 0;
    const hasQuery = currentSearchQuery !== '';

    gameConsoleFilter.classList.toggle('active-filter', hasConsole);
    gameCategoryFilter.classList.toggle('active-filter', hasCategory);
    gameSubcategoryFilter.classList.toggle('active-filter', hasSubcategory);

    const hasAnyFilter = hasConsole || hasCategory || hasSubcategory || hasQuery;
    btnResetFilters.style.display = hasAnyFilter ? 'inline-flex' : 'none';
  }

  /**
   * Handles user selecting a letter from the Alphabet Pill Ray
   */
  function onLetterSelected(letter) {
    currentLetter = letter;

    const pills = gameAlphabetPillRay.querySelectorAll('.game-alphabet-pill');
    pills.forEach(p => {
      if (p.dataset.letter === letter) {
        p.classList.add('active');
        p.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
      } else {
        p.classList.remove('active');
      }
    });

    updateActiveFilterStyles();

    if (currentMode === 'detail') {
      setMode('list');
    } else {
      renderGamesList();
      updateUrlState();
    }
  }

  /**
   * Filters and sorts games based on active state
   */
  function getFilteredGames() {
    return ALL_GAMES.filter(g => {
      if (currentLetter !== 'ALL') {
        const lead = getLeadingKey(g.title);
        if (lead !== currentLetter) {
          return false;
        }
      }

      if (currentConsoleFilter > 0) {
        if (Number(g.console_id) !== currentConsoleFilter) {
          return false;
        }
      }

      if (currentCategoryFilter > 0) {
        if (Number(g.category_id) !== currentCategoryFilter) {
          return false;
        }
      }

      if (currentSubcategoryFilter > 0) {
        if (Number(g.subcategory_id) !== currentSubcategoryFilter) {
          return false;
        }
      }

      if (currentSearchQuery !== '') {
        const q = currentSearchQuery.toLowerCase();
        const title = (g.title || '').toLowerCase();
        const pub = (g.publisher_name || '').toLowerCase();
        const con = (g.console_name || '').toLowerCase();
        if (!title.includes(q) && !pub.includes(q) && !con.includes(q)) {
          return false;
        }
      }

      return true;
    }).sort((a, b) => {
      const titleA = (a.title || '').toLowerCase();
      const titleB = (b.title || '').toLowerCase();
      const cmp = titleA.localeCompare(titleB, undefined, { numeric: true, sensitivity: 'base' });
      return currentSortAsc ? cmp : -cmp;
    });
  }

  /**
   * Formats image URLs with proper leading slash and fallback
   */
  function formatImageUrl(path, fallback) {
    if (!path || typeof path !== 'string' || path.trim() === '') {
      return fallback;
    }
    const clean = path.trim().replace(/\\/g, '/');
    if (clean.startsWith('http://') || clean.startsWith('https://')) {
      return clean;
    }
    return '/' + clean.replace(/^\/+/, '');
  }

  /**
   * Escapes HTML entities
   */
  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  /**
   * Computes or retrieves game collection badge info (text and color)
   */
  function getGameBadgeInfo(game) {
    const isBacklog = Number(game.in_collection) === 1;
    const text = game.collection_badge_text || (isBacklog ? 'BACKLOG' : 'PENDING');

    let color = game.collection_badge_color;
    if (!color) {
      const hasMain = game.title && game.year && Number(game.console_id) > 0 && Number(game.publisher_id) > 0 && Number(game.category_id) > 0 && Number(game.subcategory_id) > 0;
      const hasSecondary = Boolean(game.screenshot_path && game.boxart_path && game.tags && game.comments);
      if (!hasMain) {
        color = 'red';
      } else if (!hasSecondary) {
        color = 'amber';
      } else {
        color = 'green';
      }
    }

    return { text, color };
  }

  /**
   * Renders the List Mode: cards grid, status counters, and labels
   */
  function renderGamesList() {
    const filtered = getFilteredGames();

    let letterDisplay = currentLetter === 'ALL' ? 'All Games' : (currentLetter === '#' ? 'Letter "#"' : `Letter "${currentLetter}"`);
    if (currentSearchQuery) {
      letterDisplay += ` matching "${escapeHtml(currentSearchQuery)}"`;
    }
    gameSelectedLetterLabel.textContent = letterDisplay;
    gameGamesCountLabel.textContent = `(${filtered.length} ${filtered.length === 1 ? 'game' : 'games'})`;

    gameFilterStatsText.textContent = `Showing ${filtered.length.toLocaleString()} of ${ALL_GAMES.length.toLocaleString()} games`;

    gamesCardsGrid.innerHTML = '';

    if (filtered.length === 0) {
      gamesCardsGrid.innerHTML = `
        <div class="game-empty-state">
          <span class="empty-icon">🔍</span>
          <strong>No matching games found</strong>
          <p style="font-size: 13px; margin: 0;">Try adjusting your keyword search, taxonomy filters, or alphabetical selection.</p>
        </div>
      `;
      return;
    }

    const fragment = document.createDocumentFragment();

    filtered.forEach(game => {
      const card = document.createElement('div');
      card.className = 'game-card';
      card.tabIndex = 0;
      card.role = 'button';
      card.setAttribute('aria-label', `View details for ${game.title}`);

      const boxartUrl = formatImageUrl(game.boxart_path, FALLBACK_BOXART);

      card.innerHTML = `
        <div class="game-card-cover-slot">
          <img 
            src="${escapeHtml(boxartUrl)}" 
            alt="${escapeHtml(game.title)}" 
            class="game-card-cover-img" 
            loading="lazy"
            onerror="this.onerror=null; this.src='${FALLBACK_BOXART}';"
          >
        </div>
        <div class="game-card-info">
          <div class="game-card-title" title="${escapeHtml(game.title)}">${escapeHtml(game.title)}</div>
          <div class="game-card-submeta">${escapeHtml(game.console_name || '')}${game.year ? ` • ${escapeHtml(game.year)}` : ''}</div>
        </div>
      `;

      card.addEventListener('click', () => {
        openGameDetail(game);
      });

      card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          openGameDetail(game);
        }
      });

      fragment.appendChild(card);
    });

    gamesCardsGrid.appendChild(fragment);
  }

  /**
   * Switches view mode: 'list' | 'detail'
   */
  function setMode(mode) {
    currentMode = mode;

    if (mode === 'list') {
      gameListContainer.style.display = 'block';
      gameDetailContainer.style.display = 'none';

      gameListStatusLine.style.display = 'flex';
      gameDetailBreadcrumb.style.display = 'none';

      gameSortOrderToggleBtn.style.display = 'inline-flex';
      gameBtnGoBack.style.display = 'none';
      activeGame = null;

      const pills = gameAlphabetPillRay.querySelectorAll('.game-alphabet-pill');
      pills.forEach(p => p.classList.toggle('active', p.dataset.letter === currentLetter));

      renderGamesList();
    } else {
      gameListContainer.style.display = 'none';
      gameDetailContainer.style.display = 'flex';

      gameListStatusLine.style.display = 'none';
      gameDetailBreadcrumb.style.display = 'flex';

      gameSortOrderToggleBtn.style.display = 'none';
      gameBtnGoBack.style.display = 'inline-flex';
    }

    updateUrlState();
  }

  /**
   * Opens detail mode for a selected game and populates detail views
   */
  async function openGameDetail(gameItem, cachedDetail = null) {
    if (!gameItem || !gameItem.id) return;

    activeGame = gameItem;
    setMode('detail');

    // Breadcrumb: Letter > Game Title
    const letterKey = getLeadingKey(gameItem.title);
    breadcrumbLetterBtn.textContent = currentLetter === 'ALL' ? 'All' : `Letter "${letterKey}"`;
    breadcrumbGameTitle.textContent = gameItem.title;

    window.scrollTo({ top: 0, behavior: 'smooth' });

    // Populate basic info immediately from list record
    populateGameHeader(gameItem);

    if (cachedDetail && Number(cachedDetail.game?.id) === Number(gameItem.id)) {
      populateDetailSubsections(cachedDetail);
      return;
    }

    showLoading(true);
    try {
      const res = await fetch(`/api/games-portal/game/${gameItem.id}`, {
        headers: { 'Accept': 'application/json' }
      });
      if (!res.ok) {
        throw new Error('Failed to load game portal details.');
      }
      const json = await res.json();
      const payload = (json && json.data) ? json.data : json;
      activeDetailData = payload;
      populateDetailSubsections(payload);
      if (payload.game) {
        populateGameHeader(payload.game);
      }
    } catch (err) {
      console.warn('API detail fetch fallback:', err);
      populateDetailSubsections({
        game: gameItem,
        files: gameItem.downloadable_files || [],
        console_games: [],
        similar_games: []
      });
    } finally {
      showLoading(false);
    }
  }

  /**
   * Populates the Top Game Detail Card: Images, Title, Status Badge, Maker, Console, Year, Categories, Tags
   */
  function populateGameHeader(game) {
    // 1. Title
    detailGameTitle.textContent = game.title || 'Untitled Game';

    // 2. Collection Status Badge (Backlog / Pending with proper coloring)
    const badgeInfo = getGameBadgeInfo(game);
    detailGameStatusBadge.textContent = badgeInfo.text;
    detailGameStatusBadge.className = `badge-status badge-collection-${badgeInfo.color}`;
    detailGameStatusBadge.style.display = 'inline-flex';

    // 3. Boxart (top left)
    const boxartUrl = formatImageUrl(game.boxart_path, FALLBACK_BOXART);
    detailBoxartImg.src = boxartUrl;
    detailBoxartImg.onerror = function() {
      this.onerror = null;
      this.src = FALLBACK_BOXART;
    };

    // 4. Screenshot (below boxart)
    const screenshotUrl = formatImageUrl(game.screenshot_path, FALLBACK_SCREENSHOT);
    detailScreenshotImg.src = screenshotUrl;
    detailScreenshotImg.onerror = function() {
      this.onerror = null;
      this.src = FALLBACK_SCREENSHOT;
    };

    // 5. Maker Link (redirects to publishers_portal in detail mode with maker selected)
    const publisherName = game.publisher_name || 'Unknown Publisher';
    detailMakerValue.textContent = publisherName;
    if (game.publisher_id && Number(game.publisher_id) > 0) {
      detailMakerLink.href = `/publishers-portal?id=${game.publisher_id}&mode=detail`;
      detailMakerLink.style.pointerEvents = 'auto';
      detailMakerLink.style.opacity = '1';
    } else {
      detailMakerLink.href = '#';
      detailMakerLink.style.pointerEvents = 'none';
      detailMakerLink.style.opacity = '0.7';
    }

    // 6. Console Link (redirects to consoles_portal in detail mode with console selected)
    const consoleName = game.console_name || 'Unknown Console';
    detailConsoleValue.textContent = consoleName;
    if (game.console_id && Number(game.console_id) > 0) {
      detailConsoleLink.href = `/consoles-portal?id=${game.console_id}&mode=detail`;
      detailConsoleLink.style.pointerEvents = 'auto';
      detailConsoleLink.style.opacity = '1';
    } else {
      detailConsoleLink.href = '#';
      detailConsoleLink.style.pointerEvents = 'none';
      detailConsoleLink.style.opacity = '0.7';
    }

    // 7. Year
    detailYearValue.textContent = game.year ? String(game.year) : '—';

    // 8. Category • Subcategory
    const cat = game.category_name || '';
    const sub = game.subcategory_name || '';
    if (cat && sub) {
      detailCategoryValue.textContent = `${cat} • ${sub}`;
    } else if (cat) {
      detailCategoryValue.textContent = cat;
    } else if (sub) {
      detailCategoryValue.textContent = sub;
    } else {
      detailCategoryValue.textContent = 'Uncategorized';
    }

    // 9. Tags (in badge form)
    detailTagsContainer.innerHTML = '';
    const rawTags = (game.tags || '').toString().trim();
    if (rawTags !== '') {
      const tagList = rawTags.split(',').map(t => t.trim()).filter(Boolean);
      if (tagList.length > 0) {
        tagList.forEach(t => {
          const badge = document.createElement('span');
          badge.className = 'game-tag-badge';
          badge.textContent = t;
          detailTagsContainer.appendChild(badge);
        });
      } else {
        detailTagsContainer.innerHTML = '<span style="color: var(--text-dim); font-size: 13px;">No tags</span>';
      }
    } else {
      detailTagsContainer.innerHTML = '<span style="color: var(--text-dim); font-size: 13px;">No tags</span>';
    }

    // 10. Description text (Under tags, justified and arranged around images)
    const comments = (game.comments || '').toString().trim();
    if (comments !== '') {
      detailCommentsText.textContent = comments;
      detailCommentsText.style.display = 'block';
      detailCommentsEmpty.style.display = 'none';
    } else {
      detailCommentsText.style.display = 'none';
      detailCommentsEmpty.style.display = 'block';
    }
  }

  /**
   * Populates Subsections: Downloadable Files Box and Dual Recommendation Grids
   */
  function populateDetailSubsections(data) {
    const game = data.game || activeGame || {};

    // 1. Files & Downloads Box (Copied from Console Portal style)
    const files = Array.isArray(data.files) ? data.files : (game.downloadable_files || []);

    if (files.length > 0) {
      detailDownloadsSubsection.style.display = 'flex';
      if (detailFilesCountBadge) {
        detailFilesCountBadge.textContent = `${files.length} ${files.length === 1 ? 'file' : 'files'}`;
      }

      detailFilesList.innerHTML = files.map(file => {
        const fileId = file.id;
        const name = file.display_name || file.filename || 'Downloadable Resource';
        const dCount = Number(file.download_count || 0);
        const desc = file.description || file.file_description || (file.file_type ? file.file_type.toUpperCase() : 'Game Asset');

        return `
          <tr>
            <td>
              <div class="file-cell-main">
                <svg class="file-svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                  <polyline points="14 2 14 8 20 8"></polyline>
                  <line x1="16" y1="13" x2="8" y2="13"></line>
                  <line x1="16" y1="17" x2="8" y2="17"></line>
                  <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span class="file-name-code">${escapeHtml(name)}</span>
              </div>
            </td>
            <td>
              <span class="file-desc-text">${escapeHtml(desc)}</span>
            </td>
            <td>
              <span class="file-downloads-pill">${dCount} ${dCount === 1 ? 'download' : 'downloads'}</span>
            </td>
            <td style="text-align: right;">
              <a href="/download/${encodeURIComponent(fileId)}" target="_blank" class="btn-file-download-action" title="Download ${escapeHtml(name)}">
                <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13">
                  <path fill-rule="evenodd" d="M10 3a.75.75 0 01.75.75v8.19l2.72-2.72a.75.75 0 111.06 1.06l-4 4a.75.75 0 01-1.06 0l-4-4a.75.75 0 111.06-1.06l2.72 2.72V3.75A.75.75 0 0110 3zM3.75 14.25a.75.75 0 01.75.75v1.5c0 .138.112.25.25.25h10.5a.25.25 0 00.25-.25v-1.5a.75.75 0 011.5 0v1.5A1.75 1.75 0 0115.25 18H4.75A1.75 1.75 0 013 16.25v-1.5a.75.75 0 01.75-.75z" clip-rule="evenodd"/>
                </svg>
                <span>Download</span>
              </a>
            </td>
          </tr>
        `;
      }).join('');
    } else {
      detailDownloadsSubsection.style.display = 'none';
      detailFilesList.innerHTML = '';
      if (detailFilesCountBadge) {
        detailFilesCountBadge.textContent = '0 files';
      }
    }

    // 2. Dual Grids - More for this Console (NO "Random..." subtitle, NO "15 titles" badge)
    const consoleName = game.console_name || 'Console';
    consoleGamesPanelTitle.textContent = `More for ${consoleName}`;

    const consoleGames = Array.isArray(data.console_games) ? data.console_games : [];
    renderShowcaseGrid(consoleGamesSubgrid, consoleGames);

    // 3. Dual Grids - Similar Games (NO "Random..." subtitle, NO "15 titles" badge)
    similarGamesPanelTitle.textContent = `Similar Games`;

    const similarGames = Array.isArray(data.similar_games) ? data.similar_games : [];
    renderShowcaseGrid(similarGamesSubgrid, similarGames);
  }

  /**
   * Helper to render cards in the recommendation showcase grids
   */
  function renderShowcaseGrid(container, items) {
    container.innerHTML = '';
    if (!items || items.length === 0) {
      container.innerHTML = `
        <div style="grid-column: 1 / -1; padding: 24px; text-align: center; color: var(--text-dim); font-size: 13px;">
          No titles found.
        </div>
      `;
      return;
    }

    const fragment = document.createDocumentFragment();
    items.forEach(item => {
      const card = document.createElement('a');
      card.className = 'game-mini-card';
      card.href = `/games-portal?id=${item.id}&mode=detail`;
      card.title = `View ${item.title}`;

      const coverUrl = formatImageUrl(item.boxart_path, FALLBACK_BOXART);

      card.innerHTML = `
        <div class="game-mini-thumb-slot">
          <img 
            src="${escapeHtml(coverUrl)}" 
            alt="${escapeHtml(item.title)}" 
            class="game-mini-thumb-img" 
            loading="lazy"
            onerror="this.onerror=null; this.src='${FALLBACK_BOXART}';"
          >
        </div>
        <div class="game-mini-title">${escapeHtml(item.title)}</div>
      `;

      card.addEventListener('click', (e) => {
        e.preventDefault();
        openGameDetail(item);
      });

      fragment.appendChild(card);
    });

    container.appendChild(fragment);
  }

  /**
   * Lightbox preview modal handlers
   */
  function openLightbox(src, caption) {
    gameLightboxImg.src = src;
    gameLightboxCaption.textContent = caption || '';
    gameLightboxModal.style.display = 'flex';
  }
  function closeLightbox() {
    gameLightboxModal.style.display = 'none';
    gameLightboxImg.src = '';
  }

  /**
   * Loading overlay display handler
   */
  function showLoading(show) {
    gameLoadingOverlay.style.display = show ? 'flex' : 'none';
  }

  /**
   * Updates browser history URL parameters smoothly
   */
  function updateUrlState() {
    const params = new URLSearchParams();

    if (currentMode === 'detail' && activeGame) {
      params.set('id', activeGame.id);
      params.set('mode', 'detail');
      if (currentLetter !== 'ALL') {
        params.set('letter', currentLetter);
      }
    } else {
      if (currentLetter !== 'ALL') {
        params.set('letter', currentLetter);
      }
      if (currentSearchQuery) {
        params.set('q', currentSearchQuery);
      }
      if (currentConsoleFilter > 0) {
        params.set('console', currentConsoleFilter);
      }
      if (currentCategoryFilter > 0) {
        params.set('category', currentCategoryFilter);
      }
      if (currentSubcategoryFilter > 0) {
        params.set('subcategory', currentSubcategoryFilter);
      }
      if (!currentSortAsc) {
        params.set('order', 'desc');
      }
    }

    const queryStr = params.toString();
    const newUrl = window.location.pathname + (queryStr ? '?' + queryStr : '');
    window.history.replaceState({
      mode: currentMode,
      gameId: activeGame ? activeGame.id : null,
      letter: currentLetter,
      q: currentSearchQuery,
      console: currentConsoleFilter,
      category: currentCategoryFilter,
      subcategory: currentSubcategoryFilter,
      sortAsc: currentSortAsc
    }, '', newUrl);
  }

  /**
   * Handles browser back/forward history navigation
   */
  function onPopState(e) {
    const state = e.state;
    if (state) {
      currentLetter = state.letter || 'ALL';
      currentSearchQuery = state.q || '';
      currentConsoleFilter = state.console || 0;
      currentCategoryFilter = state.category || 0;
      currentSubcategoryFilter = state.subcategory || 0;
      currentSortAsc = state.sortAsc !== false;

      gameSearchInput.value = currentSearchQuery;
      gameClearSearchBtn.style.display = currentSearchQuery ? 'block' : 'none';
      gameConsoleFilter.value = String(currentConsoleFilter);
      gameCategoryFilter.value = String(currentCategoryFilter);
      setupSubcategoryCascade();
      gameSubcategoryFilter.value = String(currentSubcategoryFilter);

      const pills = gameAlphabetPillRay.querySelectorAll('.game-alphabet-pill');
      pills.forEach(p => p.classList.toggle('active', p.dataset.letter === currentLetter));

      updateActiveFilterStyles();

      if (state.mode === 'detail' && state.gameId) {
        const found = ALL_GAMES.find(g => Number(g.id) === Number(state.gameId));
        if (found) {
          openGameDetail(found);
          return;
        }
      }
      setMode('list');
    } else {
      const urlParams = new URLSearchParams(window.location.search);
      currentSearchQuery = urlParams.get('q') || urlParams.get('publisher') || '';
      gameSearchInput.value = currentSearchQuery;
      gameClearSearchBtn.style.display = currentSearchQuery ? 'block' : 'none';
      updateActiveFilterStyles();
      setMode('list');
      renderGamesList();
    }
  }

  // Initialize once DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>
