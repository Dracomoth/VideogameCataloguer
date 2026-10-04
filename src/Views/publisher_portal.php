<?php
/**
 * src/Views/publisher_portal.php
 * Publisher Portal - Read-only discovery catalog for game publishers, hardware, and titles.
 *
 * Variables provided by route / View::render():
 * @var string|null $pageTitle
 * @var string|null $activeNav
 * @var array<int, array<string, mixed>>|null $publishers
 * @var int|null $initialPublisherId
 * @var array<string, mixed>|null $initialDetail
 * @var string|null $initialLetter
 */

declare(strict_types=1);

use Vault\Repositories\PublisherRepository;
use Vault\Services\View;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

// Ensure repository data is available self-contained even if View::render was called without data
if (!isset($publishers) || !is_array($publishers)) {
    $repo = new PublisherRepository();
    $publishers = $repo->getAll();
}

$initialPubId = (int)($_GET['id'] ?? ($_GET['publisher_id'] ?? ($initialPublisherId ?? 0)));
$initialDet = $initialDetail ?? null;
if ($initialPubId > 0 && $initialDet === null) {
    if (!isset($repo)) {
        $repo = new PublisherRepository();
    }
    $initialDet = $repo->getPublisherPortalDetail($initialPubId);
}

$initialLet = strtoupper(trim((string)($_GET['letter'] ?? ($initialLetter ?? ''))));

$safePublishersJson = json_encode($publishers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$safeInitialDetailJson = json_encode($initialDet, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>

<style>
/* --------------------------------------------------------------------------
   Publisher Portal Scoped Styles
   -------------------------------------------------------------------------- */
.pub-portal-wrapper {
  max-width: 1440px;
  margin: 0 auto;
  padding: 16px 20px 48px;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 16px;
  color: var(--text-main, #f8fafc);
}

/* Master Header */
.pub-portal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  padding-bottom: 4px;
}
.pub-portal-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 22px;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: #fff;
}
.pub-portal-title span.icon {
  font-size: 24px;
  filter: drop-shadow(0 2px 8px rgba(56, 189, 248, 0.4));
}
.pub-portal-subtitle {
  font-size: 13px;
  color: var(--text-muted, #94a3b8);
  font-weight: 400;
  margin-top: 2px;
}

/* 1. Filter Bar (Always visible in both List and Detail modes) */
.pub-filter-bar {
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
  gap: 14px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
  transition: border-color var(--transition-fast);
}
.pub-filter-bar:focus-within {
  border-color: rgba(56, 189, 248, 0.4);
}
.pub-filter-inputs {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  flex: 1 1 320px;
}
.pub-search-box {
  position: relative;
  flex: 1 1 260px;
  max-width: 440px;
}
.pub-search-box input {
  width: 100%;
  height: 38px;
  background: var(--surface-alt, #0c121e);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-md, 6px);
  padding: 0 36px 0 38px;
  color: #fff;
  font-size: 13px;
  font-family: inherit;
  outline: none;
  transition: all var(--transition-fast);
  box-sizing: border-box;
}
.pub-search-box input:focus {
  border-color: var(--border-focus, #38bdf8);
  box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
}
.pub-search-box .search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-dim, #64748b);
  pointer-events: none;
  font-size: 14px;
}
.pub-search-box .clear-btn {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--text-dim, #64748b);
  cursor: pointer;
  font-size: 14px;
  padding: 4px;
  line-height: 1;
  display: none;
}
.pub-search-box .clear-btn:hover {
  color: #fff;
}
.pub-filter-types {
  display: flex;
  align-items: center;
  gap: 6px;
  background: var(--surface-alt, #0c121e);
  padding: 3px;
  border-radius: var(--radius-md, 6px);
  border: 1px solid var(--border, #243049);
}
.filter-type-btn {
  padding: 6px 12px;
  font-size: 12px;
  font-weight: 500;
  border: none;
  background: transparent;
  color: var(--text-muted, #94a3b8);
  border-radius: 4px;
  cursor: pointer;
  transition: all var(--transition-fast);
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.filter-type-btn:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.05);
}
.filter-type-btn.active {
  background: var(--accent, #0284c7);
  color: #fff;
  font-weight: 600;
  box-shadow: 0 2px 8px rgba(2, 132, 199, 0.35);
}
.filter-summary-stats {
  font-size: 12px;
  color: var(--text-muted, #94a3b8);
  white-space: nowrap;
}

/* 2. Sticky Horizontal Alphabet Pill Ray */
.pub-sticky-ray-wrapper {
  position: sticky;
  top: 0;
  z-index: 45;
  margin: 0 -20px;
  padding: 8px 20px;
  background: rgba(11, 15, 25, 0.94);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-top: 1px solid rgba(255, 255, 255, 0.04);
  border-bottom: 1px solid var(--border, #243049);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45);
}
.pub-alphabet-ray {
  display: flex;
  align-items: center;
  gap: 6px;
  overflow-x: auto;
  overflow-y: hidden;
  white-space: nowrap;
  scroll-behavior: smooth;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: thin;
  scrollbar-color: rgba(56, 189, 248, 0.3) transparent;
  padding: 4px 2px;
}
.pub-alphabet-ray::-webkit-scrollbar {
  height: 4px;
}
.pub-alphabet-ray::-webkit-scrollbar-thumb {
  background: rgba(56, 189, 248, 0.25);
  border-radius: 9999px;
}
.alphabet-pill {
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
.alphabet-pill:hover:not(.disabled) {
  background: var(--panel-hover, #1c273e);
  color: #fff;
  border-color: #38bdf8;
  transform: translateY(-1px);
}
.alphabet-pill.active {
  background: var(--accent, #0284c7);
  border-color: #38bdf8;
  color: #ffffff;
  box-shadow: 0 0 14px rgba(56, 189, 248, 0.45);
  transform: translateY(-1px);
}
.alphabet-pill.disabled {
  opacity: 0.35;
  cursor: default;
  border-color: rgba(255, 255, 255, 0.04);
}
.alphabet-pill.pill-all {
  min-width: 48px;
  font-weight: 700;
  letter-spacing: 0.03em;
}
.alphabet-pill.pill-num {
  min-width: 36px;
  font-family: var(--font-mono, monospace);
  font-weight: 700;
}

/* 3. Sub-header Navigation / Status Row */
.pub-subnav-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 4px 2px;
  min-height: 42px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}
.pub-status-line {
  font-size: 14px;
  font-weight: 500;
  color: var(--text-main, #f8fafc);
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.pub-status-badge {
  color: #38bdf8;
  font-weight: 700;
}
.pub-status-count {
  color: var(--text-muted, #94a3b8);
  font-weight: 400;
  font-size: 13px;
}

/* Breadcrumb in Detail Mode */
.pub-breadcrumb {
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
.pub-subnav-actions {
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
  font-size: 13px;
  color: #38bdf8;
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

/* 4. List Mode Cards Grid */
.pub-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 14px;
  width: 100%;
}
.pub-card {
  background: var(--panel, #151d30);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-lg, 10px);
  padding: 14px;
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
.pub-card:hover {
  background: var(--panel-hover, #1c273e);
  border-color: rgba(56, 189, 248, 0.5);
  transform: translateY(-3px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4), 0 0 12px rgba(56, 189, 248, 0.15);
}
.pub-card-logo-slot {
  width: 100%;
  height: 80px;
  background: var(--surface-alt, #0c121e);
  border-radius: var(--radius-md, 6px);
  border: 1px solid rgba(255, 255, 255, 0.04);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 8px;
  box-sizing: border-box;
  overflow: hidden;
}
.pub-card-logo-img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}
.pub-card-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  width: 100%;
}
.pub-card-name {
  font-size: 13px;
  font-weight: 600;
  color: #f8fafc;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  min-height: 34px;
}
.pub-card-meta {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: 11px;
  color: var(--text-dim, #64748b);
  margin-top: 2px;
}
.badge-maker-pill {
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.3);
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
  gap: 3px;
}

/* Empty State */
.pub-empty-state {
  grid-column: 1 / -1;
  background: var(--panel, #151d30);
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
.pub-empty-state span.empty-icon {
  font-size: 42px;
  opacity: 0.6;
}

/* 5. Detail Mode Layout */
.pub-detail-view {
  display: flex;
  flex-direction: column;
  gap: 24px;
  animation: fadeIn 0.2s ease-in-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Publisher Detail Header Card */
.pub-detail-header-card {
  background: var(--panel, #151d30);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-lg, 12px);
  padding: 24px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.35);
}

/* Title Above Everything & Aligned to Left + Badge */
.pub-detail-title-row {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 14px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.pub-detail-name {
  font-size: 21px;
  font-weight: 600;
  color: #ffffff;
  letter-spacing: -0.015em;
  line-height: 1.25;
  margin: 0;
  text-align: left;
}
.badge-console-maker-lg {
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.35);
  font-size: 11.5px;
  font-weight: 500;
  padding: 3px 10px;
  border-radius: 9999px;
  text-transform: none;
  letter-spacing: normal;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
}

/* Main Info: Left Boxed Logo + Right Listed Details (No boxes, aligned next to logo) */
.pub-detail-main-info {
  display: flex;
  align-items: flex-start;
  gap: 32px;
  width: 100%;
}
@media (max-width: 768px) {
  .pub-detail-main-info {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
  }
}
.pub-detail-logo-box {
  width: 160px;
  height: 120px;
  min-width: 160px;
  background: var(--surface-alt, #0c121e);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-lg, 10px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 12px;
  box-sizing: border-box;
  overflow: hidden;
  box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.4);
}
.pub-detail-logo-img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.5));
}

/* Right: The details, listed - NO BOXES, aligned directly next to logo */
.pub-detail-specs-list {
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  gap: 14px;
  flex: 0 1 auto;
  padding: 2px 0 0 0;
}
.pub-detail-spec-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 0;
  background: transparent !important;
  border: none !important;
  border-radius: 0 !important;
  box-shadow: none !important;
}
.pub-detail-spec-row .spec-label {
  font-size: 13.5px;
  font-weight: 400;
  text-transform: none;
  letter-spacing: normal;
  color: var(--text-dim, #94a3b8);
  min-width: 175px;
  flex-shrink: 0;
}
.pub-detail-spec-row .spec-value {
  font-size: 14px;
  font-weight: 500;
  color: #f1f5f9;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.pub-games-link {
  color: #38bdf8;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-weight: 500;
  transition: color var(--transition-fast);
}
.pub-games-link:hover {
  color: #7dd3fc;
  text-decoration: underline;
}

/* Separator & Justified Comments / History */
.pub-detail-separator {
  height: 1px;
  background: var(--border, #243049);
  margin: 22px 0 16px;
  width: 100%;
}
.pub-detail-comments-block {
  width: 100%;
}
.pub-detail-comments-text {
  font-size: 14px;
  line-height: 1.75;
  color: #cbd5e1;
  text-align: justify;
  white-space: pre-line;
  margin: 0;
}

/* 6. Dual Grids Section */
.pub-grids-container {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  align-items: start;
}
.pub-grids-container.single-grid {
  grid-template-columns: 1fr;
}

.pub-section-card {
  background: var(--panel, #151d30);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-lg, 10px);
  padding: 20px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.pub-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--border, #243049);
}
.pub-section-title {
  font-size: 16px;
  font-weight: 700;
  color: #fff;
  display: flex;
  align-items: center;
  gap: 8px;
}
.btn-view-all-games {
  font-size: 12px;
  font-weight: 600;
  color: #38bdf8;
  background: rgba(56, 189, 248, 0.1);
  border: 1px solid rgba(56, 189, 248, 0.3);
  padding: 5px 12px;
  border-radius: var(--radius-pill, 9999px);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: all var(--transition-fast);
}
.btn-view-all-games:hover {
  background: #0284c7;
  color: #fff;
  border-color: #0284c7;
  box-shadow: 0 2px 8px rgba(56, 189, 248, 0.3);
}

/* Consoles Subgrid */
.consoles-subgrid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 12px;
}
.console-item-card {
  background: var(--surface-alt, #0c121e);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-md, 6px);
  padding: 10px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 8px;
  cursor: pointer;
  transition: all var(--transition-fast);
  text-decoration: none;
  color: inherit;
}
.console-item-card:hover {
  border-color: #38bdf8;
  background: var(--panel-hover, #1c273e);
  transform: translateY(-2px);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4);
}
.console-thumb-slot {
  width: 100%;
  height: 85px;
  background: rgba(0, 0, 0, 0.35);
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 6px;
  box-sizing: border-box;
  overflow: hidden;
}
.console-thumb-img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}
.console-item-name {
  font-size: 12px;
  font-weight: 600;
  color: #fff;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
}
.console-item-year {
  font-size: 11px;
  color: var(--text-dim, #64748b);
}

/* Games Subgrid (9 Random Titles) */
.games-subgrid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 12px;
}
.game-item-card {
  background: var(--surface-alt, #0c121e);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-md, 6px);
  padding: 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 8px;
  cursor: pointer;
  transition: all var(--transition-fast);
  text-decoration: none;
  color: inherit;
}
.game-item-card:hover {
  border-color: #38bdf8;
  background: var(--panel-hover, #1c273e);
  transform: translateY(-2px);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4);
}
.game-thumb-slot {
  width: 100%;
  height: 160px;
  background: rgba(0, 0, 0, 0.45);
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  position: relative;
  padding: 4px;
  box-sizing: border-box;
}
.game-thumb-img {
  max-width: 100%;
  max-height: 100%;
  width: auto;
  height: auto;
  object-fit: contain;
  transition: transform var(--transition-fast);
}
.game-item-card:hover .game-thumb-img {
  transform: scale(1.04);
}
.game-item-title {
  font-size: 11px;
  font-weight: 600;
  color: #fff;
  line-height: 1.25;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  min-height: 28px;
}
.game-item-meta {
  font-size: 10px;
  color: var(--text-dim, #64748b);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  width: 100%;
}

/* Loading Overlay */
.pub-loading-overlay {
  display: none;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  color: #38bdf8;
  font-size: 14px;
  font-weight: 600;
  gap: 10px;
}
.pub-spinner {
  width: 24px;
  height: 24px;
  border: 3px solid rgba(56, 189, 248, 0.2);
  border-top-color: #38bdf8;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Responsive adjustments */
@media (max-width: 900px) {
  .pub-grids-container {
    grid-template-columns: 1fr;
    gap: 16px;
  }
  .pub-detail-header-card {
    padding: 16px;
  }
  .pub-detail-main-info {
    flex-direction: column;
    align-items: flex-start;
    text-align: left;
    gap: 16px;
  }
  .pub-detail-logo-box {
    width: 110px;
    height: 110px;
    min-width: 110px;
  }
  .pub-detail-title-row {
    justify-content: center;
  }
  .pub-detail-stats-row {
    justify-content: center;
  }
  .pub-detail-history {
    text-align: left;
  }
}

@media (max-width: 600px) {
  .pub-portal-wrapper {
    padding: 12px 14px 40px;
    gap: 12px;
  }
  .pub-cards-grid {
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 10px;
  }
  .pub-card {
    padding: 10px;
  }
  .pub-card-logo-slot {
    height: 65px;
  }
  .pub-filter-bar {
    padding: 12px;
  }
  .pub-filter-types {
    width: 100%;
    justify-content: space-between;
  }
  .filter-type-btn {
    flex: 1 1 auto;
    justify-content: center;
    padding: 6px 8px;
    font-size: 11px;
  }
  .consoles-subgrid {
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 8px;
  }
  .games-subgrid {
    grid-template-columns: repeat(auto-fill, minmax(105px, 1fr));
    gap: 8px;
  }
  .game-thumb-slot {
    height: 140px;
  }
}
</style>

<div class="pub-portal-wrapper">
  <!-- Top Portal Header -->
  <header class="pub-portal-header">
    <div>
      <div class="pub-portal-title">
        <span class="icon">🏷️</span>
        <span>Publishers Portal</span>
      </div>
      <div class="pub-portal-subtitle">Browse and explore gaming publishers, hardware platforms, and game catalogs</div>
    </div>
  </header>

  <!-- 1. Filter Bar (Always visible in both List and Detail modes) -->
  <section class="pub-filter-bar" aria-label="Publisher Filters">
    <div class="pub-filter-inputs">
      <!-- Search Input -->
      <div class="pub-search-box">
        <span class="search-icon">🔍</span>
        <input 
          type="text" 
          id="portalSearchInput" 
          placeholder="Filter publishers by name..." 
          autocomplete="off"
          spellcheck="false"
        >
        <button type="button" id="clearSearchBtn" class="clear-btn" title="Clear filter">✕</button>
      </div>

      <!-- Type Filter Chips -->
      <div class="pub-filter-types" role="radiogroup" aria-label="Manufacturer classification">
        <button type="button" class="filter-type-btn active" data-type="all">All</button>
        <button type="button" class="filter-type-btn" data-type="makers">🎮 Console Makers</button>
        <button type="button" class="filter-type-btn" data-type="software">💿 Software Only</button>
      </div>
    </div>

    <!-- Summary Stats -->
    <div class="filter-summary-stats" id="filterStatsText">
      Loading publishers...
    </div>
  </section>

  <!-- 2. Always Visible Sticky Horizontal Alphabet Pill Ray -->
  <nav class="pub-sticky-ray-wrapper" aria-label="Alphabetical Index">
    <div class="pub-alphabet-ray" id="alphabetPillRay">
      <!-- Filled dynamically by JavaScript -->
    </div>
  </nav>

  <!-- 3. Sub-header Navigation / Status Row -->
  <div class="pub-subnav-row" id="subnavRow">
    <!-- Left: Status Line (List Mode) OR Breadcrumb (Detail Mode) -->
    <div id="subnavLeft">
      <div class="pub-status-line" id="listStatusLine">
        <span>Showing:</span>
        <span class="pub-status-badge" id="selectedLetterLabel">All Publishers</span>
        <span class="pub-status-count" id="publishersCountLabel">(0 publishers)</span>
      </div>
      <nav class="pub-breadcrumb" id="detailBreadcrumb" style="display: none;">
        <button type="button" class="breadcrumb-btn" id="breadcrumbLetterBtn">Letter "All"</button>
        <span class="breadcrumb-sep">›</span>
        <span class="breadcrumb-current" id="breadcrumbPublisherName">Publisher Name</span>
      </nav>
    </div>

    <!-- Right: Sort Order Button (List Mode) OR Go Back Button (Detail Mode) -->
    <div class="pub-subnav-actions" id="subnavRight">
      <button type="button" class="btn-sort-toggle" id="sortOrderToggleBtn" title="Toggle alphabetical sort order">
        <span>Sort:</span>
        <span id="sortDirectionLabel">A → Z</span>
        <span class="sort-arrow" id="sortArrowIcon">▲</span>
      </button>
      <button type="button" class="btn-go-back" id="btnGoBack" style="display: none;">
        <span>←</span>
        <span>Back to List</span>
      </button>
    </div>
  </div>

  <!-- Loading Indicator -->
  <div class="pub-loading-overlay" id="portalLoadingOverlay">
    <div class="pub-spinner"></div>
    <span>Loading publisher details...</span>
  </div>

  <!-- 4. Mode 1: List Mode (Cards Grid) -->
  <section id="portalListContainer" aria-label="Publishers Catalog">
    <div class="pub-cards-grid" id="publishersCardsGrid">
      <!-- Cards rendered dynamically -->
    </div>
  </section>

  <!-- 5. Mode 2: Detail Mode (Publisher Header & Dual Grids) -->
  <section id="portalDetailContainer" class="pub-detail-view" style="display: none;" aria-label="Publisher Details">
    <!-- Top Publisher Info Card -->
    <div class="pub-detail-header-card">
      <!-- Title Row: Above everything, aligned to the left + Badge -->
      <div class="pub-detail-title-row">
        <h1 class="pub-detail-name" id="detailPublisherName">Publisher Name</h1>
        <span class="badge-console-maker-lg" id="detailConsoleMakerBadge" style="display: none;">
          <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13">
            <path d="M4 11a1 1 0 011-1h1v-1a1 1 0 112 0v1h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1H5a1 1 0 01-1-1zm10.5-2a1 1 0 100-2 1 1 0 000 2zm1 3a1 1 0 100-2 1 1 0 000 2zm-2 1a1 1 0 100-2 1 1 0 000 2z"/>
          </svg>
          <span>Console Maker</span>
        </span>
      </div>

      <!-- Main Info Row: Left Logo Box + Right Listed Details (No boxes, aligned next to logo) -->
      <div class="pub-detail-main-info">
        <!-- Left: Logo Boxed Section -->
        <div class="pub-detail-logo-box" id="detailLogoBox">
          <!-- Rendered dynamically -->
        </div>

        <!-- Right: The details, listed (No boxes, aligned directly next to logo) -->
        <div class="pub-detail-specs-list">
          <div class="pub-detail-spec-row">
            <span class="spec-label">Publisher Role</span>
            <span class="spec-value" id="detailRoleValue">Software Publisher</span>
          </div>
          <div class="pub-detail-spec-row">
            <span class="spec-label">Published Games</span>
            <span class="spec-value">
              <a href="#" class="pub-games-link" id="detailGamesBadgeLink" title="View all games from this publisher">
                <span id="detailGamesCount">0 Games</span>
                <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13">
                  <path fill-rule="evenodd" d="M5.22 14.78a.75.75 0 001.06 0l7.22-7.22v5.69a.75.75 0 001.5 0v-7.5a.75.75 0 00-.75-.75h-7.5a.75.75 0 000 1.5h5.69l-7.22 7.22a.75.75 0 000 1.06z" clip-rule="evenodd"/>
                </svg>
              </a>
            </span>
          </div>
          <div class="pub-detail-spec-row" id="detailConsolesRow" style="display: none;">
            <span class="spec-label">Consoles Released</span>
            <span class="spec-value">
              <span id="detailConsolesCount" style="color: #38bdf8; font-weight: 500;">0</span>
              <span id="detailConsolesText" style="color: #cbd5e1; font-weight: 500; margin-left: 4px;">Platforms</span>
            </span>
          </div>
        </div>
      </div>

      <!-- Separator & Description with Justified Text -->
      <div class="pub-detail-separator" id="detailDescSeparator" style="display: none;"></div>
      <div class="pub-detail-comments-block" id="detailNotesSection" style="display: none;">
        <p class="pub-detail-comments-text" id="detailDescriptionBox"></p>
      </div>
    </div>

    <!-- Dual Grids: Consoles Released & 9 Random Games -->
    <div class="pub-grids-container" id="pubGridsContainer">
      <!-- Grid 1: Consoles Released (Visible only if is_console_maker is true) -->
      <div class="pub-section-card" id="consolesSectionCard" style="display: none;">
        <div class="pub-section-header">
          <div class="pub-section-title">
            <span>🕹️</span>
            <span>Consoles Released</span>
            <span id="consolesCountBadge" style="font-size: 13px; color: var(--text-muted); font-weight: 500;"></span>
          </div>
        </div>
        <div class="consoles-subgrid" id="consolesSubgrid">
          <!-- Rendered dynamically -->
        </div>
      </div>

      <!-- Grid 2: 9 Random Games from that Publisher -->
      <div class="pub-section-card" id="gamesSectionCard">
        <div class="pub-section-header">
          <div class="pub-section-title">
            <span>🎲</span>
            <span>Games Showcase</span>            
          </div>
          <a href="#" class="btn-view-all-games" id="viewAllGamesBtn">
            <span>View All Games</span>
            <span id="viewAllCountLabel"></span>
            <span>→</span>
          </a>
        </div>
        <div class="games-subgrid" id="gamesSubgrid">
          <!-- Rendered dynamically -->
        </div>
      </div>
    </div>
  </section>
</div>

<script>
/**
 * Publisher Portal Client-Side Controller
 */
(function() {
  'use strict';

  // 1. Initial State from Server
  const ALL_PUBLISHERS = <?= $safePublishersJson ?: '[]' ?>;
  const INITIAL_DETAIL = <?= $safeInitialDetailJson ?: 'null' ?>;
  const INITIAL_PUB_ID = <?= (int)($initialPubId ?? 0) ?>;
  const INITIAL_LETTER = <?= json_encode($initialLet ?? '') ?>;

  // Runtime State
  let currentMode = 'list'; // 'list' | 'detail'
  let currentLetter = 'ALL'; // 'ALL' | '#' | 'A'...'Z'
  let currentSortAsc = true; // true: A-Z, false: Z-A
  let currentSearchQuery = '';
  let currentTypeFilter = 'all'; // 'all' | 'makers' | 'software'
  let activePublisher = null; // object when in detail mode
  let activeDetailData = null; // { publisher, consoles, random_games }

  // DOM Element References
  const searchInput = document.getElementById('portalSearchInput');
  const clearSearchBtn = document.getElementById('clearSearchBtn');
  const filterTypeBtns = document.querySelectorAll('.filter-type-btn');
  const filterStatsText = document.getElementById('filterStatsText');
  const alphabetPillRay = document.getElementById('alphabetPillRay');

  // Subnav DOM
  const listStatusLine = document.getElementById('listStatusLine');
  const selectedLetterLabel = document.getElementById('selectedLetterLabel');
  const publishersCountLabel = document.getElementById('publishersCountLabel');
  const detailBreadcrumb = document.getElementById('detailBreadcrumb');
  const breadcrumbLetterBtn = document.getElementById('breadcrumbLetterBtn');
  const breadcrumbPublisherName = document.getElementById('breadcrumbPublisherName');
  const sortOrderToggleBtn = document.getElementById('sortOrderToggleBtn');
  const sortDirectionLabel = document.getElementById('sortDirectionLabel');
  const sortArrowIcon = document.getElementById('sortArrowIcon');
  const btnGoBack = document.getElementById('btnGoBack');

  // Containers
  const portalListContainer = document.getElementById('portalListContainer');
  const portalDetailContainer = document.getElementById('portalDetailContainer');
  const publishersCardsGrid = document.getElementById('publishersCardsGrid');
  const portalLoadingOverlay = document.getElementById('portalLoadingOverlay');

  // Detail View Elements
  const detailLogoBox = document.getElementById('detailLogoBox');
  const detailPublisherName = document.getElementById('detailPublisherName');
  const detailConsoleMakerBadge = document.getElementById('detailConsoleMakerBadge');
  const detailRoleValue = document.getElementById('detailRoleValue');
  const detailGamesCount = document.getElementById('detailGamesCount');
  const detailGamesBadgeLink = document.getElementById('detailGamesBadgeLink');
  const detailConsolesRow = document.getElementById('detailConsolesRow');
  const detailConsolesCount = document.getElementById('detailConsolesCount');
  const detailNotesSection = document.getElementById('detailNotesSection');
  const detailDescriptionBox = document.getElementById('detailDescriptionBox');
  const detailDescSeparator = document.getElementById('detailDescSeparator');
  const pubGridsContainer = document.getElementById('pubGridsContainer');
  const consolesSectionCard = document.getElementById('consolesSectionCard');
  const consolesCountBadge = document.getElementById('consolesCountBadge');
  const consolesSubgrid = document.getElementById('consolesSubgrid');
  const gamesSectionCard = document.getElementById('gamesSectionCard');
  const gamesSubgrid = document.getElementById('gamesSubgrid');
  const viewAllGamesBtn = document.getElementById('viewAllGamesBtn');
  const viewAllCountLabel = document.getElementById('viewAllCountLabel');

  /**
   * Helper: Extracts leading character key ('#', or 'A'-'Z')
   */
  function getLeadingKey(name) {
    if (!name) return '#';
    const trimmed = name.trim().toUpperCase();
    if (!trimmed) return '#';
    const firstChar = trimmed[0];
    if (firstChar >= 'A' && firstChar <= 'Z') {
      return firstChar;
    }
    return '#';
  }

  /**
   * Helper: Generates SVG placeholder data URL for missing logos
   */
  function getLogoFallbackSvg(name) {
    const initials = (name || 'PB')
      .split(/\s+/)
      .map(w => w[0])
      .filter(Boolean)
      .slice(0, 2)
      .join('')
      .toUpperCase();

    const svg = `
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" width="100%" height="100%">
        <rect width="120" height="120" rx="10" fill="#0f172a"/>
        <circle cx="60" cy="60" r="44" fill="#1e293b" stroke="#334155" stroke-width="2"/>
        <text x="60" y="67" font-family="-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif" font-size="28" font-weight="700" fill="#38bdf8" text-anchor="middle" dominant-baseline="middle">${initials}</text>
      </svg>
    `.trim();

    return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
  }

  /**
   * Helper: Generates SVG placeholder for consoles
   */
  function getConsoleFallbackSvg(name) {
    const label = (name || 'Console').substring(0, 14);
    const svg = `
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 100" width="100%" height="100%">
        <rect width="160" height="100" rx="6" fill="#0c121e"/>
        <rect x="20" y="24" width="120" height="52" rx="4" fill="#1e293b" stroke="#334155" stroke-width="2"/>
        <circle cx="45" cy="50" r="8" fill="#38bdf8"/>
        <rect x="100" y="44" width="22" height="12" rx="2" fill="#64748b"/>
        <text x="80" y="88" font-family="-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif" font-size="10" fill="#94a3b8" text-anchor="middle">${escapeHtml(label)}</text>
      </svg>
    `.trim();
    return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
  }

  /**
   * Helper: Generates SVG placeholder for game covers
   */
  function getGameFallbackSvg(title) {
    const label = (title || 'Game').substring(0, 16);
    const svg = `
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 140 180" width="100%" height="100%">
        <defs>
          <linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#1e293b"/>
            <stop offset="100%" stop-color="#0f172a"/>
          </linearGradient>
        </defs>
        <rect width="140" height="180" rx="6" fill="url(#g)"/>
        <rect x="10" y="10" width="120" height="160" rx="4" fill="none" stroke="#334155" stroke-dasharray="4,4"/>
        <circle cx="70" cy="70" r="28" fill="#0284c7" opacity="0.3"/>
        <text x="70" y="78" font-family="sans-serif" font-size="28" fill="#38bdf8" text-anchor="middle">🕹️</text>
        <text x="70" y="130" font-family="-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif" font-size="11" font-weight="600" fill="#e2e8f0" text-anchor="middle">${escapeHtml(label)}</text>
      </svg>
    `.trim();
    return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
  }

  /**
   * Helper: Normalizes relative or absolute image URLs
   */
  function formatImageUrl(path, fallbackUrl) {
    if (!path || typeof path !== 'string' || path.trim() === '') {
      return fallbackUrl;
    }
    const clean = path.trim().replace(/^\/+/, '');
    return '/' + clean;
  }

  /**
   * Helper: Escapes HTML strings
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
   * Builds the Sticky Alphabet Pill Ray: [All], [#], [A]...[Z]
   */
  function renderAlphabetPillRay() {
    alphabetPillRay.innerHTML = '';

    const counts = { ALL: ALL_PUBLISHERS.length, '#': 0 };
    for (let i = 65; i <= 90; i++) {
      counts[String.fromCharCode(i)] = 0;
    }

    ALL_PUBLISHERS.forEach(pub => {
      const k = getLeadingKey(pub.name);
      if (counts[k] !== undefined) {
        counts[k]++;
      } else {
        counts['#']++;
      }
    });

    const items = ['ALL', '#'];
    for (let i = 65; i <= 90; i++) {
      items.push(String.fromCharCode(i));
    }

    items.forEach(key => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'alphabet-pill';
      btn.dataset.letter = key;

      if (key === 'ALL') {
        btn.classList.add('pill-all');
        btn.textContent = 'All';
      } else if (key === '#') {
        btn.classList.add('pill-num');
        btn.textContent = '#';
      } else {
        btn.textContent = key;
      }

      const count = counts[key] || 0;
      if (count === 0 && key !== 'ALL') {
        btn.classList.add('disabled');
        btn.title = `No publishers start with ${key}`;
      } else {
        btn.title = `Show publishers (${count})`;
      }

      if (key === currentLetter) {
        btn.classList.add('active');
      }

      btn.addEventListener('click', () => {
        onLetterSelected(key);
      });

      alphabetPillRay.appendChild(btn);
    });
  }

  /**
   * Handles user clicking a letter in the Alphabet Pill Ray
   */
  function onLetterSelected(letter) {
    currentLetter = letter;

    const pills = alphabetPillRay.querySelectorAll('.alphabet-pill');
    pills.forEach(p => {
      if (p.dataset.letter === letter) {
        p.classList.add('active');
        p.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
      } else {
        p.classList.remove('active');
      }
    });

    if (currentMode === 'detail') {
      setMode('list');
    }

    updateUrlState();
    renderPublishersList();
  }

  /**
   * Filters and sorts publishers based on active state
   */
  function getFilteredPublishers() {
    return ALL_PUBLISHERS.filter(pub => {
      if (currentLetter !== 'ALL') {
        const lead = getLeadingKey(pub.name);
        if (lead !== currentLetter) {
          return false;
        }
      }

      if (currentTypeFilter === 'makers' && Number(pub.is_console_maker) !== 1) {
        return false;
      }
      if (currentTypeFilter === 'software' && Number(pub.is_console_maker) === 1) {
        return false;
      }

      if (currentSearchQuery !== '') {
        const q = currentSearchQuery.toLowerCase();
        const name = (pub.name || '').toLowerCase();
        if (!name.includes(q)) {
          return false;
        }
      }

      return true;
    }).sort((a, b) => {
      const nameA = (a.name || '').toLowerCase();
      const nameB = (b.name || '').toLowerCase();
      const cmp = nameA.localeCompare(nameB);
      return currentSortAsc ? cmp : -cmp;
    });
  }

  /**
   * Renders the List Mode: cards, counts, status line
   */
  function renderPublishersList() {
    const filtered = getFilteredPublishers();

    let letterDisplay = currentLetter === 'ALL' ? 'All' : (currentLetter === '#' ? '"#"' : `Letter "${currentLetter}"`);
    if (currentSearchQuery) {
      letterDisplay += ` matching "${escapeHtml(currentSearchQuery)}"`;
    }
    selectedLetterLabel.textContent = letterDisplay;
    publishersCountLabel.textContent = `(${filtered.length} ${filtered.length === 1 ? 'publisher' : 'publishers'})`;

    filterStatsText.textContent = `Showing ${filtered.length} of ${ALL_PUBLISHERS.length} publishers`;

    publishersCardsGrid.innerHTML = '';

    if (filtered.length === 0) {
      publishersCardsGrid.innerHTML = `
        <div class="pub-empty-state">
          <span class="empty-icon">🔍</span>
          <strong>No publishers found</strong>
          <p style="font-size: 13px; margin: 0;">Try adjusting your search criteria, manufacturer filter, or alphabetical selection.</p>
        </div>
      `;
      return;
    }

    const fragment = document.createDocumentFragment();

    filtered.forEach(pub => {
      const card = document.createElement('div');
      card.className = 'pub-card';
      card.tabIndex = 0;
      card.role = 'button';
      card.setAttribute('aria-label', `View details for publisher ${pub.name}`);

      const isMaker = Number(pub.is_console_maker) === 1;
      const gamesCount = Number(pub.games_count ?? pub.game_count ?? 0);
      const fallbackUrl = getLogoFallbackSvg(pub.name);
      const logoUrl = formatImageUrl(pub.logo_path, fallbackUrl);

      card.innerHTML = `
        <div class="pub-card-logo-slot">
          <img 
            src="${escapeHtml(logoUrl)}" 
            alt="${escapeHtml(pub.name)} logo" 
            class="pub-card-logo-img" 
            loading="lazy"
            onerror="this.onerror=null; this.src='${fallbackUrl}';"
          >
        </div>
        <div class="pub-card-info">
          <div class="pub-card-name" title="${escapeHtml(pub.name)}">${escapeHtml(pub.name)}</div>
          <div class="pub-card-meta">
            ${isMaker ? '<span class="badge-maker-pill">🎮 Hardware</span>' : ''}
            <span>${gamesCount} ${gamesCount === 1 ? 'game' : 'games'}</span>
          </div>
        </div>
      `;

      card.addEventListener('click', () => {
        openPublisherDetail(pub);
      });

      card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          openPublisherDetail(pub);
        }
      });

      fragment.appendChild(card);
    });

    publishersCardsGrid.appendChild(fragment);
  }

  /**
   * Switches view mode: 'list' | 'detail'
   */
  function setMode(mode) {
    currentMode = mode;

    if (mode === 'list') {
      portalListContainer.style.display = 'block';
      portalDetailContainer.style.display = 'none';

      listStatusLine.style.display = 'flex';
      detailBreadcrumb.style.display = 'none';

      sortOrderToggleBtn.style.display = 'inline-flex';
      btnGoBack.style.display = 'none';
      activePublisher = null;

      // Sync active state on alphabet pills
      const pills = alphabetPillRay.querySelectorAll('.alphabet-pill');
      pills.forEach(p => p.classList.toggle('active', p.dataset.letter === currentLetter));

      renderPublishersList();
    } else {
      portalListContainer.style.display = 'none';
      portalDetailContainer.style.display = 'flex';

      listStatusLine.style.display = 'none';
      detailBreadcrumb.style.display = 'flex';

      sortOrderToggleBtn.style.display = 'none';
      btnGoBack.style.display = 'inline-flex';
    }

    updateUrlState();
  }

  /**
   * Loads and displays the Detail Mode for a publisher
   */
  async function openPublisherDetail(publisher, cachedDetail = null) {
    if (!publisher || !publisher.id) return;

    activePublisher = publisher;
    setMode('detail');

    const letterKey = getLeadingKey(publisher.name);
    breadcrumbLetterBtn.textContent = currentLetter === 'ALL' ? 'All' : `Letter "${letterKey}"`;
    breadcrumbPublisherName.textContent = publisher.name;

    window.scrollTo({ top: 0, behavior: 'smooth' });

    populatePublisherHeader(publisher);

    if (cachedDetail && Number(cachedDetail.publisher?.id) === Number(publisher.id)) {
      populateDetailGrids(cachedDetail);
      return;
    }

    showLoading(true);
    try {
      const res = await fetch(`/api/publishers-portal/publisher/${publisher.id}`, {
        headers: { 'Accept': 'application/json' }
      });
      if (!res.ok) {
        throw new Error('Failed to load publisher details.');
      }
      const json = await res.json();
      const payload = (json && json.data) ? json.data : json;
      activeDetailData = payload;
      populateDetailGrids(payload);
      if (payload.publisher) {
        populatePublisherHeader(payload.publisher);
      }
    } catch (err) {
      console.warn('API detail fetch fallback to basic details:', err);
      // Fallback: render grids from publisher record if endpoint is not reachable
      populateDetailGrids({
        publisher: publisher,
        consoles: Array.isArray(publisher.consoles) ? publisher.consoles.map(name => ({ id: 0, name: name })) : [],
        random_games: []
      });
    } finally {
      showLoading(false);
    }
  }

  /**
   * Populates top publisher card info in Detail Mode
   */
  function populatePublisherHeader(publisher) {
    detailPublisherName.textContent = publisher.name;

    const fallbackUrl = getLogoFallbackSvg(publisher.name);
    const logoUrl = formatImageUrl(publisher.logo_path, fallbackUrl);

    detailLogoBox.innerHTML = `
      <img 
        src="${escapeHtml(logoUrl)}" 
        alt="${escapeHtml(publisher.name)} logo" 
        class="pub-detail-logo-img"
        onerror="this.onerror=null; this.src='${fallbackUrl}';"
      >
    `;

    const isMaker = Number(publisher.is_console_maker) === 1;
    if (isMaker) {
      if (detailConsoleMakerBadge) detailConsoleMakerBadge.style.display = 'inline-flex';
      if (detailConsolesRow) detailConsolesRow.style.display = 'flex';
      if (detailRoleValue) detailRoleValue.textContent = 'Hardware Manufacturer & Publisher';
    } else {
      if (detailConsoleMakerBadge) detailConsoleMakerBadge.style.display = 'none';
      if (detailConsolesRow) detailConsolesRow.style.display = 'none';
      if (detailRoleValue) detailRoleValue.textContent = 'Software Publisher';
    }

    const gamesCount = Number(publisher.games_count ?? publisher.game_count ?? 0);
    const consolesCount = Number(publisher.consoles_count ?? publisher.console_count ?? 0);
    if (detailGamesCount) {
      detailGamesCount.textContent = `${gamesCount} ${gamesCount === 1 ? 'Game' : 'Games'}`;
    }
    if (detailConsolesCount) {
      detailConsolesCount.textContent = String(consolesCount);
    }

    const gamesCatalogUrl = `/games-portal?publisher_id=${publisher.id}&publisher=${encodeURIComponent(publisher.name)}&mode=list`;
    if (detailGamesBadgeLink) {
      detailGamesBadgeLink.href = gamesCatalogUrl;
    }

    if (publisher.description && publisher.description.trim() !== '') {
      detailDescriptionBox.textContent = publisher.description.trim();
      if (detailNotesSection) detailNotesSection.style.display = 'block';
      if (detailDescSeparator) detailDescSeparator.style.display = 'block';
    } else {
      detailDescriptionBox.textContent = '';
      if (detailNotesSection) detailNotesSection.style.display = 'none';
      if (detailDescSeparator) detailDescSeparator.style.display = 'none';
    }

    viewAllGamesBtn.href = gamesCatalogUrl;
    viewAllCountLabel.textContent = `(${gamesCount})`;
  }

  /**
   * Populates the two bottom grids: Consoles Released & 9 Random Games
   */
  function populateDetailGrids(data) {
    const publisher = data.publisher || activePublisher;
    const consoles = Array.isArray(data.consoles) ? data.consoles : [];
    const games = Array.isArray(data.random_games) ? data.random_games : [];
    const isMaker = Number(publisher.is_console_maker) === 1;

    // Grid 1: Consoles Released
    if (isMaker) {
      consolesSectionCard.style.display = 'flex';
      pubGridsContainer.classList.remove('single-grid');
      consolesCountBadge.textContent = `(${consoles.length})`;

      consolesSubgrid.innerHTML = '';
      if (consoles.length === 0) {
        consolesSubgrid.innerHTML = `
          <div style="grid-column: 1 / -1; padding: 24px; text-align: center; color: var(--text-dim); font-size: 13px;">
            No hardware platforms catalogued for this company yet.
          </div>
        `;
      } else {
        const cFragment = document.createDocumentFragment();
        consoles.forEach(con => {
          const fallback = getConsoleFallbackSvg(con.name);
          const imgUrl = formatImageUrl(con.image_path, fallback);

          const card = document.createElement('a');
          card.className = 'console-item-card';
          card.href = con.id ? `/consoles-portal?id=${con.id}&mode=detail` : '#';
          card.title = `View ${con.name} on Consoles Portal`;

          card.innerHTML = `
            <div class="console-thumb-slot">
              <img 
                src="${escapeHtml(imgUrl)}" 
                alt="${escapeHtml(con.name)}" 
                class="console-thumb-img" 
                loading="lazy"
                onerror="this.onerror=null; this.src='${fallback}';"
              >
            </div>
            <div class="console-item-name">${escapeHtml(con.name)}</div>
            ${con.year ? `<div class="console-item-year">${escapeHtml(con.year)}</div>` : ''}
          `;

          cFragment.appendChild(card);
        });
        consolesSubgrid.appendChild(cFragment);
      }
    } else {
      consolesSectionCard.style.display = 'none';
      pubGridsContainer.classList.add('single-grid');
    }

    // Grid 2: Nine Random Games
    gamesSubgrid.innerHTML = '';
    if (games.length === 0) {
      gamesSubgrid.innerHTML = `
        <div style="grid-column: 1 / -1; padding: 24px; text-align: center; color: var(--text-dim); font-size: 13px;">
          No published games catalogued for this publisher yet.
        </div>
      `;
    } else {
      const gFragment = document.createDocumentFragment();
      games.forEach(game => {
        const fallback = getGameFallbackSvg(game.title);
        const artPath = game.boxart_path || game.screenshot_path;
        const imgUrl = formatImageUrl(artPath, fallback);

        const card = document.createElement('a');
        card.className = 'game-item-card';
        card.href = `/games-portal?id=${game.id}&mode=detail`;
        card.title = `View ${game.title} on Games Portal`;

        card.innerHTML = `
          <div class="game-thumb-slot">
            <img 
              src="${escapeHtml(imgUrl)}" 
              alt="${escapeHtml(game.title)}" 
              class="game-thumb-img" 
              loading="lazy"
              onerror="this.onerror=null; this.src='${fallback}';"
            >
          </div>
          <div class="game-item-title">${escapeHtml(game.title)}</div>
          <div class="game-item-meta">${escapeHtml(game.console_name || '')}${game.year ? ` • ${escapeHtml(game.year)}` : ''}</div>
        `;

        gFragment.appendChild(card);
      });
      gamesSubgrid.appendChild(gFragment);
    }
  }

  /**
   * Helper: Show or hide loading spinner
   */
  function showLoading(show) {
    portalLoadingOverlay.style.display = show ? 'flex' : 'none';
  }

  /**
   * Updates browser URL history cleanly
   */
  function updateUrlState() {
    const params = new URLSearchParams();
    if (currentMode === 'detail' && activePublisher) {
      params.set('id', activePublisher.id);
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
      if (currentTypeFilter !== 'all') {
        params.set('type', currentTypeFilter);
      }
    }
    const newRelativePathQuery = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
    history.replaceState({ mode: currentMode, id: activePublisher?.id, letter: currentLetter }, '', newRelativePathQuery);
  }

  // --------------------------------------------------------------------------
  // Event Bindings
  // --------------------------------------------------------------------------

  // Search input typing
  let searchDebounceTimer = null;
  searchInput.addEventListener('input', (e) => {
    currentSearchQuery = e.target.value.trim();
    clearSearchBtn.style.display = currentSearchQuery ? 'block' : 'none';

    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
      if (currentMode === 'detail' && currentSearchQuery !== '') {
        setMode('list');
      }
      renderPublishersList();
      updateUrlState();
    }, 180);
  });

  // Clear search input
  clearSearchBtn.addEventListener('click', () => {
    searchInput.value = '';
    currentSearchQuery = '';
    clearSearchBtn.style.display = 'none';
    searchInput.focus();
    renderPublishersList();
    updateUrlState();
  });

  // Type filter buttons (All / Console Makers / Software Only)
  filterTypeBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterTypeBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentTypeFilter = btn.dataset.type || 'all';

      if (currentMode === 'detail') {
        setMode('list');
      }

      renderPublishersList();
      updateUrlState();
    });
  });

  // Sort Order Toggle (Ascending A-Z vs Descending Z-A)
  sortOrderToggleBtn.addEventListener('click', () => {
    currentSortAsc = !currentSortAsc;
    sortDirectionLabel.textContent = currentSortAsc ? 'A → Z' : 'Z → A';
    sortArrowIcon.textContent = currentSortAsc ? '▲' : '▼';
    sortOrderToggleBtn.title = currentSortAsc ? 'Sorted Ascending (A to Z). Click to reverse.' : 'Sorted Descending (Z to A). Click to reverse.';
    renderPublishersList();
  });

  // Go Back button from Detail Mode
  btnGoBack.addEventListener('click', () => {
    setMode('list');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  // Breadcrumb Letter button
  breadcrumbLetterBtn.addEventListener('click', () => {
    setMode('list');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  // Browser back / forward buttons (popstate)
  window.addEventListener('popstate', (e) => {
    const urlParams = new URLSearchParams(window.location.search);
    const pubId = parseInt(urlParams.get('id') || '0', 10);
    const letter = urlParams.get('letter') || 'ALL';

    currentLetter = letter;
    const pills = alphabetPillRay.querySelectorAll('.alphabet-pill');
    pills.forEach(p => p.classList.toggle('active', p.dataset.letter === currentLetter));

    if (pubId > 0) {
      const pub = ALL_PUBLISHERS.find(p => Number(p.id) === pubId);
      if (pub) {
        openPublisherDetail(pub);
        return;
      }
    }

    setMode('list');
    renderPublishersList();
  });

  // --------------------------------------------------------------------------
  // Initialization
  // --------------------------------------------------------------------------
  function init() {
    renderAlphabetPillRay();

    if (INITIAL_LETTER && (INITIAL_LETTER === '#' || (INITIAL_LETTER >= 'A' && INITIAL_LETTER <= 'Z'))) {
      currentLetter = INITIAL_LETTER;
      const pills = alphabetPillRay.querySelectorAll('.alphabet-pill');
      pills.forEach(p => p.classList.toggle('active', p.dataset.letter === currentLetter));
    }

    renderPublishersList(); // Pre-render list so returning to list works seamlessly

    if (INITIAL_PUB_ID > 0) {
      const pub = ALL_PUBLISHERS.find(p => Number(p.id) === INITIAL_PUB_ID);
      if (pub) {
        openPublisherDetail(pub, INITIAL_DETAIL);
        return;
      }
    }

    setMode('list');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>
