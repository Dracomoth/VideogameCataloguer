<?php
/**
 * src/Views/consoles_portal.php
 * Consoles Portal - Read-only discovery catalog for gaming systems and hardware platforms.
 *
 * Variables provided by route / View::render():
 * @var string|null $pageTitle
 * @var string|null $activeNav
 * @var array<int, array<string, mixed>>|null $consoles
 * @var array<int, array<string, mixed>>|null $consoleTypes
 * @var int|null $initialConsoleId
 * @var array<string, mixed>|null $initialDetail
 * @var string|null $initialLetter
 */

declare(strict_types=1);

use Vault\Repositories\ConsoleRepository;
use Vault\Repositories\ConsoleTypeRepository;
use Vault\Services\View;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

// 1. Ensure repository data is available self-contained even if View::render was called without data
if (!isset($consoles) || !is_array($consoles)) {
    $repo = new ConsoleRepository();
    $consoles = $repo->getAll();
}

if (!isset($consoleTypes) || !is_array($consoleTypes)) {
    $typeRepo = new ConsoleTypeRepository();
    $consoleTypes = $typeRepo->getAll();
}

$initialConId = (int)($_GET['id'] ?? ($_GET['console_id'] ?? ($initialConsoleId ?? 0)));
$initialDet = $initialDetail ?? null;
if ($initialConId > 0 && $initialDet === null) {
    if (!isset($repo)) {
        $repo = new ConsoleRepository();
    }
    $initialDet = $repo->getConsolePortalDetail($initialConId);
}

$initialLet = strtoupper(trim((string)($_GET['letter'] ?? ($initialLetter ?? ''))));

$safeConsolesJson = json_encode($consoles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$safeConsoleTypesJson = json_encode($consoleTypes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$safeInitialDetailJson = json_encode($initialDet, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>

<style>
/* --------------------------------------------------------------------------
   Consoles Portal Scoped Styles
   -------------------------------------------------------------------------- */
.con-portal-wrapper {
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
.con-portal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  padding-bottom: 4px;
}
.con-portal-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 22px;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: #fff;
}
.con-portal-title span.icon {
  font-size: 24px;
  filter: drop-shadow(0 2px 8px rgba(56, 189, 248, 0.4));
}
.con-portal-subtitle {
  font-size: 13px;
  color: var(--text-muted, #94a3b8);
  font-weight: 400;
  margin-top: 2px;
}

/* 1. Filter Bar (Always visible in both List and Detail modes) */
.con-filter-bar {
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
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  overflow: hidden;
}
.con-filter-bar:focus-within {
  border-color: rgba(56, 189, 248, 0.4);
}
.con-filter-inputs {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  flex: 1 1 320px;
  min-width: 0;
  max-width: 100%;
}
.con-search-box {
  position: relative;
  flex: 1 1 240px;
  max-width: 400px;
}
.con-search-box input {
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
.con-search-box input:focus {
  border-color: var(--border-focus, #38bdf8);
  box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
}
.con-search-box .search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-dim, #64748b);
  pointer-events: none;
  font-size: 14px;
}
.con-search-box .clear-btn {
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
.con-search-box .clear-btn:hover {
  color: #fff;
}
.con-filter-types {
  display: flex;
  align-items: center;
  gap: 6px;
  background: var(--surface-alt, #0c121e);
  padding: 3px;
  border-radius: var(--radius-md, 6px);
  border: 1px solid var(--border, #243049);
}
.filter-type-pill,
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
.filter-type-pill:hover,
.filter-type-btn:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.05);
}
.filter-type-pill.active,
.filter-type-btn.active {
  background: var(--accent, #0284c7);
  color: #fff;
  font-weight: 600;
  box-shadow: 0 2px 8px rgba(2, 132, 199, 0.35);
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
.con-sticky-ray-wrapper {
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
.con-alphabet-ray {
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
.con-alphabet-ray::-webkit-scrollbar {
  height: 4px;
}
.con-alphabet-ray::-webkit-scrollbar-thumb {
  background: rgba(56, 189, 248, 0.25);
  border-radius: 9999px;
}
.con-alphabet-pill {
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
.con-alphabet-pill:hover:not(.disabled) {
  background: var(--panel-hover, #1c273e);
  color: #fff;
  border-color: #38bdf8;
  transform: translateY(-1px);
}
.con-alphabet-pill.active {
  background: var(--accent, #0284c7);
  border-color: #38bdf8;
  color: #ffffff;
  box-shadow: 0 0 14px rgba(56, 189, 248, 0.45);
  transform: translateY(-1px);
}
.con-alphabet-pill.disabled {
  opacity: 0.35;
  cursor: default;
  border-color: rgba(255, 255, 255, 0.04);
}
.con-alphabet-pill.pill-all {
  min-width: 48px;
  font-weight: 700;
  letter-spacing: 0.03em;
}
.con-alphabet-pill.pill-num {
  min-width: 36px;
  font-family: var(--font-mono, monospace);
  font-weight: 700;
}

/* 3. Sub-header Navigation / Status Row */
.con-subnav-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 4px 2px;
  min-height: 42px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}
.con-status-line {
  font-size: 14px;
  font-weight: 500;
  color: var(--text-main, #f8fafc);
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.con-status-badge {
  color: #38bdf8;
  font-weight: 700;
}
.con-status-count {
  color: var(--text-muted, #94a3b8);
  font-weight: 400;
  font-size: 13px;
}

/* Breadcrumb in Detail Mode */
.con-breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 500;
  flex-wrap: wrap;
}
.con-breadcrumb-btn {
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
.con-breadcrumb-btn:hover {
  color: #7dd3fc;
  text-decoration: underline;
}
.con-breadcrumb-sep {
  color: var(--text-dim, #64748b);
  font-size: 13px;
}
.con-breadcrumb-current {
  color: #fff;
  font-weight: 700;
}

/* Action Controls (Sort / Back) */
.con-subnav-actions {
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
}

/* 4. List Mode Layout & Cards */
.con-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 16px;
}
.con-card {
  background: var(--panel, #151d30);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-lg, 10px);
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  cursor: pointer;
  transition: all var(--transition-fast);
  user-select: none;
  position: relative;
  overflow: hidden;
}
.con-card:hover {
  border-color: #38bdf8;
  background: var(--panel-hover, #1c273e);
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
}
.con-card-image-slot {
  width: 100%;
  height: 125px;
  background: var(--surface-alt, #0c121e);
  border-radius: var(--radius-md, 6px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 8px;
  box-sizing: border-box;
  overflow: hidden;
}
.con-card-img {
  max-width: 100%;
  max-height: 100%;
  width: auto;
  height: auto;
  object-fit: contain;
  transition: transform var(--transition-fast);
}
.con-card:hover .con-card-img {
  transform: scale(1.04);
}
.con-card-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.con-card-name {
  font-size: 14px;
  font-weight: 700;
  color: #fff;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  min-height: 36px;
}
.con-card-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 11px;
  color: var(--text-dim, #64748b);
  gap: 6px;
}
.badge-type-tag {
  display: inline-flex;
  align-items: center;
  padding: 2px 7px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.02em;
  white-space: nowrap;
}
.con-card-games-count {
  font-weight: 600;
  color: #94a3b8;
}

/* Empty State */
.con-empty-state {
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
.con-empty-state span.empty-icon {
  font-size: 42px;
  opacity: 0.6;
}

/* ==========================================================================
   5. Detail Mode Layout & Modern UI/UX Design System
   ========================================================================== */
.con-detail-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  animation: conFadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes conFadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

/* 1. Header Card */
.con-detail-header-card {
  background: var(--panel, #151d30);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-lg, 12px);
  padding: 24px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.35);
}

/* Title Above Everything & Aligned to Left + Console Type Badge */
.con-detail-title-row {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 14px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.con-detail-name {
  font-size: 21px;
  font-weight: 600;
  color: #ffffff;
  letter-spacing: -0.015em;
  line-height: 1.25;
  margin: 0;
  text-align: left;
}
.badge-type-pill-lg {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 9999px;
  font-size: 11.5px;
  font-weight: 500;
  text-transform: none;
  letter-spacing: normal;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

/* Main Info: Left Floated Images Box + Right Specs & Wrapping Description */
.con-detail-main-info {
  display: block;
  position: relative;
  width: 100%;
}
.con-detail-main-info::after {
  content: "";
  display: table;
  clear: both;
}

/* Left: Boxed Section with the two images */
.con-detail-images-box {
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
  .con-detail-images-box {
    float: none;
    width: 100%;
    max-width: 100%;
    min-width: auto;
    margin: 0 0 16px 0;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 10px;
  }
  .con-logo-imagebox {
    flex: 1 1 120px;
    height: 80px;
  }
  .con-hardware-imagebox {
    flex: 1 1 150px;
    height: 160px;
  }
}
@media (max-width: 420px) {
  .con-detail-images-box {
    flex-direction: column;
  }
  .con-logo-imagebox {
    height: 68px;
    width: 100%;
  }
  .con-hardware-imagebox {
    height: 160px;
    width: 100%;
  }
}
.con-imagebox {
  background: rgba(15, 23, 42, 0.7);
  border: 1px solid rgba(51, 65, 85, 0.45);
  border-radius: var(--radius-md, 8px);
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  overflow: hidden;
}
.con-logo-imagebox {
  height: 72px;
  padding: 8px 12px;
}
.con-logo-imagebox img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.5));
}
.con-hardware-imagebox {
  height: 175px;
  padding: 10px;
}
.con-hardware-imagebox img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  filter: drop-shadow(0 6px 16px rgba(0, 0, 0, 0.55));
  transition: transform 0.2s ease;
}
.con-hardware-imagebox:hover img {
  transform: scale(1.03);
}

/* Right: The details, listed - NO BOXES, aligned directly next to images */
.con-detail-specs-list {
  display: block;
}
.con-detail-spec-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 0;
  margin-bottom: 14px;
  background: transparent !important;
  border: none !important;
  border-radius: 0 !important;
  box-shadow: none !important;
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
}
.con-maker-link {
  color: #38bdf8;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-weight: 500;
  transition: color var(--transition-fast);
}
.con-maker-link:hover {
  color: #7dd3fc;
  text-decoration: underline;
}
.con-games-link {
  color: #38bdf8;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-weight: 500;
  transition: color var(--transition-fast);
}
.con-games-link:hover {
  color: #7dd3fc;
  text-decoration: underline;
}

/* Justified Comments / Specs under base info */
.con-detail-comments-block {
  margin-top: 18px;
}
.con-detail-comments-text {
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

/* 2. Structured Section Panels (Shared Base) */
.con-section-panel {
  background: var(--panel, #151d30);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-lg, 12px);
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}
.con-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(51, 65, 85, 0.5);
}
.con-panel-title-group {
  display: flex;
  align-items: center;
  gap: 10px;
}
.con-panel-icon {
  color: #38bdf8;
  flex-shrink: 0;
}
.con-panel-title {
  font-size: 16px;
  font-weight: 700;
  color: #f8fafc;
  letter-spacing: -0.01em;
  margin: 0;
}
.con-panel-subtitle {
  font-size: 12px;
  color: var(--text-muted, #94a3b8);
  font-weight: 400;
}
.con-panel-badge {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-dim, #94a3b8);
  background: rgba(30, 41, 59, 0.6);
  padding: 3px 8px;
  border-radius: var(--radius-pill, 9999px);
  border: 1px solid rgba(51, 65, 85, 0.5);
}

/* Compact BIOS / Downloadable Files Table View */
.con-files-table-wrapper {
  overflow-x: auto;
  border-radius: var(--radius-md, 8px);
  border: 1px solid rgba(51, 65, 85, 0.4);
  background: var(--surface-alt, #0c121e);
}
.con-files-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 13px;
}
.con-files-table th {
  padding: 10px 14px;
  font-size: 12px;
  font-weight: 500;
  text-transform: none;
  letter-spacing: normal;
  color: var(--text-dim, #94a3b8);
  background: rgba(15, 23, 42, 0.7);
  border-bottom: 1px solid rgba(51, 65, 85, 0.5);
}
.con-files-table td {
  padding: 11px 14px;
  border-bottom: 1px solid rgba(51, 65, 85, 0.3);
  color: #cbd5e1;
  vertical-align: middle;
}
.con-files-table tr:last-child td {
  border-bottom: none;
}
.con-files-table tr:hover td {
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

/* 3. Emulation Section (Single Box) */
.con-emu-single-box {
  background: var(--surface-alt, #0c121e);
  border: 1px solid var(--border, #243049);
  border-radius: var(--radius-md, 8px);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.con-emu-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 13px 18px;
  border-bottom: 1px solid rgba(51, 65, 85, 0.35);
  transition: background var(--transition-fast);
  flex-wrap: wrap;
}
.con-emu-row:last-child {
  border-bottom: none;
}
.con-emu-row:hover {
  background: rgba(30, 41, 59, 0.35);
}
.emu-row-left {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}
.emu-type-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  font-weight: 500;
  text-transform: none;
  letter-spacing: normal;
  color: #38bdf8;
  min-width: 175px;
}
.emu-name-title {
  font-size: 14px;
  font-weight: 500;
  color: #f8fafc;
}
.emu-row-right {
  display: flex;
  align-items: center;
  flex-shrink: 0;
}
.btn-emu-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: rgba(56, 189, 248, 0.12);
  border: 1px solid rgba(56, 189, 248, 0.35);
  border-radius: var(--radius-md, 6px);
  color: #38bdf8;
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
  transition: all var(--transition-fast);
  white-space: nowrap;
}
.btn-emu-link:hover {
  background: #0284c7;
  color: #fff;
  border-color: #0284c7;
  box-shadow: 0 2px 8px rgba(56, 189, 248, 0.3);
  transform: translateY(-1px);
}

/* 4. Games Showcase Panel */
.con-games-panel {
  gap: 18px;
}
.btn-panel-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: #38bdf8;
  background: rgba(56, 189, 248, 0.1);
  border: 1px solid rgba(56, 189, 248, 0.25);
  padding: 6px 12px;
  border-radius: var(--radius-pill, 9999px);
  text-decoration: none;
  transition: all var(--transition-fast);
}
.btn-panel-action:hover {
  background: #0284c7;
  color: #fff;
  border-color: #0284c7;
  box-shadow: 0 2px 8px rgba(56, 189, 248, 0.3);
}
.con-games-subgrid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(136px, 1fr));
  gap: 14px;
}
.con-game-card {
  background: rgba(11, 15, 25, 0.6);
  border: 1px solid rgba(51, 65, 85, 0.5);
  border-radius: var(--radius-md, 8px);
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
.con-game-card:hover {
  border-color: #38bdf8;
  background: rgba(30, 41, 59, 0.7);
  transform: translateY(-3px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4), 0 0 12px rgba(56, 189, 248, 0.2);
}
.con-game-thumb-slot {
  width: 100%;
  height: 165px;
  background: rgba(0, 0, 0, 0.45);
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  position: relative;
  padding: 4px;
  box-sizing: border-box;
}
.con-game-thumb-img {
  max-width: 100%;
  max-height: 100%;
  width: auto;
  height: auto;
  object-fit: contain;
  transition: transform var(--transition-fast);
}
.con-game-card:hover .con-game-thumb-img {
  transform: scale(1.04);
}
.con-game-title {
  font-size: 11.5px;
  font-weight: 600;
  color: #f8fafc;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  min-height: 30px;
}
.con-game-meta {
  font-size: 10.5px;
  color: var(--text-dim, #94a3b8);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  width: 100%;
}

/* Loading Overlay */
.con-loading-overlay {
  display: none;
  align-items: center;
  justify-content: center;
  padding: 48px 20px;
  color: #38bdf8;
  font-size: 14px;
  font-weight: 600;
  gap: 10px;
}
.con-spinner {
  width: 24px;
  height: 24px;
  border: 3px solid rgba(56, 189, 248, 0.2);
  border-top-color: #38bdf8;
  border-radius: 50%;
  animation: conSpin 0.8s linear infinite;
}
@keyframes conSpin {
  to { transform: rotate(360deg); }
}

/* Responsive Breakpoints */
@media (max-width: 860px) {
  .con-hero-card {
    padding: 20px;
  }
  .con-hero-body {
    grid-template-columns: 1fr;
    justify-items: center;
  }
  .con-hero-meta-panel {
    align-items: stretch;
  }
}

@media (max-width: 768px) {
  .con-portal-wrapper {
    padding: 12px 10px 40px;
    gap: 12px;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow-x: hidden;
  }
  .con-portal-title {
    font-size: 20px;
  }
  .con-portal-subtitle {
    font-size: 12px;
  }
  .con-filter-bar {
    padding: 12px;
  }
  .con-filter-inputs {
    flex-direction: column;
    align-items: stretch;
    width: 100%;
    gap: 8px;
    flex: 1 1 100%;
  }
  .con-search-box {
    min-width: 100%;
    max-width: 100%;
    width: 100%;
    flex: 1 1 auto;
  }
  .con-filter-types {
    width: 100%;
    justify-content: flex-start;
    gap: 6px;
  }
  .filter-type-pill,
  .filter-type-btn {
    flex: 1 1 auto;
    justify-content: center;
    font-size: 11px;
    padding: 6px 8px;
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
  .con-sticky-ray-wrapper {
    margin: 0;
    padding: 6px 10px;
    width: 100%;
    max-width: 100%;
  }
  .con-alphabet-pill {
    height: 30px;
    min-width: 32px;
    padding: 0 8px;
    font-size: 12px;
  }
  .con-subnav-row {
    flex-wrap: wrap;
    gap: 8px;
    min-height: auto;
    padding: 6px 0;
  }
  .con-status-line {
    font-size: 13px;
  }
  .con-breadcrumb {
    font-size: 13px;
    gap: 6px;
  }
  .con-cards-grid {
    grid-template-columns: repeat(auto-fill, minmax(135px, 1fr));
    gap: 10px;
  }
  .con-card-image-slot {
    height: 105px;
  }
  .con-card-name {
    font-size: 12.5px;
    min-height: 32px;
  }
  .con-detail-header-card {
    padding: 16px 14px;
  }
  .con-detail-title-row {
    gap: 10px;
    margin-bottom: 14px;
    justify-content: flex-start;
  }
  .con-detail-name {
    font-size: 20px;
  }
  .con-detail-spec-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 3px;
    margin-bottom: 12px;
  }
  .con-detail-spec-row .spec-label {
    min-width: unset;
    font-size: 12px;
  }
  .con-detail-spec-row .spec-value {
    font-size: 13.5px;
  }
  .con-detail-comments-block {
    margin-top: 14px;
  }
  .con-section-panel {
    padding: 16px 14px;
  }
  .con-files-table th,
  .con-files-table td {
    padding: 8px 10px;
    font-size: 12px;
  }
  .con-emu-row {
    padding: 10px 12px;
  }
  .emu-type-tag {
    min-width: unset;
  }
  .con-games-subgrid {
    grid-template-columns: repeat(auto-fill, minmax(105px, 1fr));
    gap: 8px;
  }
  .con-game-thumb-slot {
    height: 130px;
  }
  .con-lightbox-close {
    top: 12px;
    right: 12px;
    position: fixed;
    width: 36px;
    height: 36px;
  }
}
</style>

<div class="con-portal-wrapper">
  <!-- Top Portal Header -->
  <header class="con-portal-header">
    <div>
      <div class="con-portal-title">
        <span class="icon">💻</span>
        <span>Consoles Portal</span>
      </div>
      <div class="con-portal-subtitle">Explore gaming hardware platforms, technical specifications, and catalogued libraries</div>
    </div>
  </header>

  <!-- 1. Filter Bar (Always visible in both List and Detail modes) -->
  <section class="con-filter-bar" aria-label="Console Filters">
    <div class="con-filter-inputs">
      <!-- Search Input -->
      <div class="con-search-box">
        <span class="search-icon">🔍</span>
        <input 
          type="text" 
          id="conSearchInput" 
          placeholder="Filter consoles by name or maker..." 
          autocomplete="off"
          spellcheck="false"
        >
        <button type="button" id="conClearSearchBtn" class="clear-btn" title="Clear filter">✕</button>
      </div>

      <!-- Type Filter Pills -->
      <div class="con-filter-types" id="conFilterTypesContainer" role="radiogroup" aria-label="Hardware type classification">
        <!-- Rendered dynamically -->
      </div>

      <!-- Clear / Reset Filters Button (visible only when filters active) -->
      <button type="button" id="conBtnResetFilters" class="btn-reset-filters" title="Reset all filters">
        <span>✕</span>
        <span>Clear Filters</span>
      </button>
    </div>

    <!-- Summary Stats -->
    <div class="filter-summary-stats" id="conFilterStatsText">
      Loading platforms...
    </div>
  </section>

  <!-- 2. Always Visible Sticky Horizontal Alphabet Pill Ray -->
  <nav class="con-sticky-ray-wrapper" aria-label="Alphabetical Index">
    <div class="con-alphabet-ray" id="conAlphabetPillRay">
      <!-- Filled dynamically by JavaScript -->
    </div>
  </nav>

  <!-- 3. Sub-header Navigation / Status Row -->
  <div class="con-subnav-row" id="conSubnavRow">
    <!-- Left: Status Line (List Mode) OR Breadcrumb (Detail Mode) -->
    <div id="conSubnavLeft">
      <div class="con-status-line" id="conListStatusLine">
        <span>Showing:</span>
        <span class="con-status-badge" id="conSelectedLetterLabel">All Platforms</span>
        <span class="con-status-count" id="conConsolesCountLabel">(0 consoles)</span>
      </div>
      <!-- Breadcrumb removed per spec; handled externally -->
    </div>

    <!-- Right: Sort Order Button (List Mode) OR Go Back Button (Detail Mode) -->
    <div class="con-subnav-actions" id="conSubnavRight">
      <button type="button" class="btn-sort-toggle" id="conSortOrderToggleBtn" title="Toggle alphabetical sort order">
        <span>Sort:</span>
        <span id="conSortDirectionLabel">A → Z</span>
        <span class="sort-arrow" id="conSortArrowIcon">▲</span>
      </button>
      <button type="button" class="btn-go-back" id="conBtnGoBack" style="display: none;">
        <span>←</span>
        <span>Back to List</span>
      </button>
    </div>
  </div>

  <!-- Loading Indicator -->
  <div class="con-loading-overlay" id="conLoadingOverlay">
    <div class="con-spinner"></div>
    <span>Loading console details...</span>
  </div>

  <!-- 4. Mode 1: List Mode (Cards Grid) -->
  <section id="conListContainer" aria-label="Consoles Catalog">
    <div class="con-cards-grid" id="consolesCardsGrid">
      <!-- Cards rendered dynamically -->
    </div>
  </section>

  <!-- 5. Mode 2: Detail Mode -->
  <section id="conDetailContainer" class="con-detail-view" style="display: none;" aria-label="Console Details">
    <!-- 1. Header Card (Title, Images Box, Details List, Comments/Specs) -->
    <div class="con-detail-header-card">
      <!-- Title Row: Title Above Everything, Aligned to the Left + Console Type Badge -->
      <div class="con-detail-title-row">
        <h1 class="con-detail-name" id="detailConsoleName">Console Name</h1>
        <span class="badge-type-pill-lg" id="detailConsoleTypeBadge">Home</span>
      </div>

      <!-- Main Info Row: Left Images Boxed Section + Right Details Listed -->
      <div class="con-detail-main-info">
        <!-- Left: Boxed Section with the two images, each in its own imagebox -->
        <div class="con-detail-images-box">
          <!-- Imagebox 1: Logo -->
          <div class="con-imagebox con-logo-imagebox" id="detailConLogoBox">
            <!-- Logo rendered dynamically -->
          </div>
          <!-- Imagebox 2: Console Hardware -->
          <div class="con-imagebox con-hardware-imagebox" id="detailConImageBox">
            <!-- Console hardware image rendered dynamically -->
          </div>
        </div>

        <!-- Right: The details, listed + Description -->
        <div class="con-detail-specs-list">
          <div class="con-detail-spec-row">
            <span class="spec-label">Maker / Manufacturer</span>
            <span class="spec-value" id="detailMakerValue">Atari</span>
          </div>
          <div class="con-detail-spec-row">
            <span class="spec-label">Release Year</span>
            <span class="spec-value" id="detailYearValue">—</span>
          </div>
          <div class="con-detail-spec-row">
            <span class="spec-label">Generation</span>
            <span class="spec-value" id="detailGenValue">—</span>
          </div>
          <div class="con-detail-spec-row">
            <span class="spec-label">Catalogued Games</span>
            <span class="spec-value">
              <a href="#" class="con-games-link" id="detailGamesBadgeLink" title="View all games for this console">
                <span id="detailGamesCountValue">0 Games</span>
                <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13">
                  <path fill-rule="evenodd" d="M5.22 14.78a.75.75 0 001.06 0l7.22-7.22v5.69a.75.75 0 001.5 0v-7.5a.75.75 0 00-.75-.75h-7.5a.75.75 0 000 1.5h5.69l-7.22 7.22a.75.75 0 000 1.06z" clip-rule="evenodd"/>
                </svg>
              </a>
            </span>
          </div>

          <!-- Description / Comments: Under base info, justified, arranged around images if needed -->
          <div class="con-detail-comments-block" id="detailNotesSection" style="display: none;">
            <p class="con-detail-comments-text" id="detailSpecsText"></p>
          </div>
        </div>
      </div>
    </div>

    <!-- 2. BIOS & Downloadable Files (Placed immediately below) -->
    <section class="con-section-panel" id="detailDownloadsSubsection" style="display: none;">
      <div class="con-panel-header">
        <div class="con-panel-title-group">
          <svg class="con-panel-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
          <h2 class="con-panel-title">BIOS & System Files</h2>
        </div>
        <span class="con-panel-badge" id="detailFilesCountBadge">0 files</span>
      </div>

      <!-- Compact Structured File Table -->
      <div class="con-files-table-wrapper">
        <table class="con-files-table">
          <thead>
            <tr>
              <th>File Name</th>
              <th>Description / Type</th>
              <th>Downloads</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody id="detailFilesList">
            <!-- Rendered dynamically -->
          </tbody>
        </table>
      </div>
    </section>

    <!-- 3. Emulation Info (Single Box: RetroArch Core with link, PC emulator with link, Android emulator with link) -->
    <section class="con-section-panel" id="detailEmulationSection" style="display: none;">
      <div class="con-panel-header">
        <div class="con-panel-title-group">
          <svg class="con-panel-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
            <rect x="2" y="3" width="20" height="14" rx="2"/>
            <line x1="8" y1="21" x2="16" y2="21"/>
            <line x1="12" y1="17" x2="12" y2="21"/>
          </svg>
          <h2 class="con-panel-title">Emulation & Software</h2>
        </div>
      </div>

      <!-- Single Box Container with Emulator Rows -->
      <div class="con-emu-single-box" id="detailEmuGrid">
        <!-- Rendered dynamically -->
      </div>
    </section>

    <!-- 4. Games Showcase (15 Random Titles) -->
    <section class="con-section-panel con-games-panel" id="conGamesSectionCard">
      <div class="con-panel-header">
        <div class="con-panel-title-group">
          <svg class="con-panel-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
            <line x1="6" y1="12" x2="10" y2="12"/>
            <line x1="8" y1="10" x2="8" y2="14"/>
            <line x1="15" y1="13" x2="15.01" y2="13"/>
            <line x1="18" y1="11" x2="18.01" y2="11"/>
            <rect x="2" y="6" width="20" height="12" rx="2"/>
          </svg>
          <h2 class="con-panel-title">Games Showcase</h2>
        </div>
        <a href="#" class="btn-panel-action" id="conViewAllGamesBtn">
          <span>View All Games</span>
          <span id="conViewAllCountLabel"></span>
          <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14">
            <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd" />
          </svg>
        </a>
      </div>
      <div class="con-games-subgrid" id="conGamesSubgrid">
        <!-- Rendered dynamically -->
      </div>
    </section>
  </section>
</div>

<script>
/**
 * Consoles Portal Client-Side Controller
 */
(function() {
  'use strict';

  // 1. Initial State from Server
  const ALL_CONSOLES = <?= $safeConsolesJson ?: '[]' ?>;
  const CONSOLE_TYPES = <?= $safeConsoleTypesJson ?: '[]' ?>;
  const INITIAL_DETAIL = <?= $safeInitialDetailJson ?: 'null' ?>;
  const INITIAL_CON_ID = <?= (int)($initialConId ?? 0) ?>;
  const INITIAL_LETTER = <?= json_encode($initialLet ?? '') ?>;

  // Runtime State
  let currentMode = 'list'; // 'list' | 'detail'
  let currentLetter = 'ALL'; // 'ALL' | '#' | 'A'...'Z'
  let currentSortAsc = true; // true: A-Z, false: Z-A
  let currentSearchQuery = '';
  let currentTypeFilter = 'all'; // 'all' | type_id as string
  let activeConsole = null; // object when in detail mode
  let activeDetailData = null; // { console, files, random_games }

  // DOM References: Filters & Alphabet
  const searchInput = document.getElementById('conSearchInput');
  const clearSearchBtn = document.getElementById('conClearSearchBtn');
  const filterTypesContainer = document.getElementById('conFilterTypesContainer');
  const btnResetFilters = document.getElementById('conBtnResetFilters');
  const filterStatsText = document.getElementById('conFilterStatsText');
  const alphabetPillRay = document.getElementById('conAlphabetPillRay');

  // Subnav DOM
  const listStatusLine = document.getElementById('conListStatusLine');
  const selectedLetterLabel = document.getElementById('conSelectedLetterLabel');
  const consolesCountLabel = document.getElementById('conConsolesCountLabel');
  const detailBreadcrumb = document.getElementById('conDetailBreadcrumb');
  const breadcrumbLetterBtn = document.getElementById('conBreadcrumbLetterBtn');
  const breadcrumbConsoleName = document.getElementById('conBreadcrumbConsoleName');
  const sortOrderToggleBtn = document.getElementById('conSortOrderToggleBtn');
  const sortDirectionLabel = document.getElementById('conSortDirectionLabel');
  const sortArrowIcon = document.getElementById('conSortArrowIcon');
  const btnGoBack = document.getElementById('conBtnGoBack');

  // Containers
  const conListContainer = document.getElementById('conListContainer');
  const conDetailContainer = document.getElementById('conDetailContainer');
  const consolesCardsGrid = document.getElementById('consolesCardsGrid');
  const loadingOverlay = document.getElementById('conLoadingOverlay');

  // Detail View Header & Metadata
  const detailConLogoBox = document.getElementById('detailConLogoBox');
  const detailConImageBox = document.getElementById('detailConImageBox');
  const detailConsoleName = document.getElementById('detailConsoleName');
  const detailConsoleTypeBadge = document.getElementById('detailConsoleTypeBadge');
  const detailMakerValue = document.getElementById('detailMakerValue');
  const detailYearValue = document.getElementById('detailYearValue');
  const detailGenValue = document.getElementById('detailGenValue');
  const detailGamesCountValue = document.getElementById('detailGamesCountValue');
  const detailGamesBadgeLink = document.getElementById('detailGamesBadgeLink');

  // Detail View Subsections
  const detailNotesSeparator = document.getElementById('detailNotesSeparator');
  const detailNotesSection = document.getElementById('detailNotesSection');
  const detailSpecsText = document.getElementById('detailSpecsText');
  const detailDownloadsSubsection = document.getElementById('detailDownloadsSubsection');
  const detailFilesCountBadge = document.getElementById('detailFilesCountBadge');
  const detailFilesList = document.getElementById('detailFilesList');
  const detailEmulationSection = document.getElementById('detailEmulationSection');
  const detailEmuGrid = document.getElementById('detailEmuGrid');

  // Games Showcase Grid
  const conGamesSubgrid = document.getElementById('conGamesSubgrid');
  const conViewAllGamesBtn = document.getElementById('conViewAllGamesBtn');
  const conViewAllCountLabel = document.getElementById('conViewAllCountLabel');

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
   * Helper: Generates SVG placeholder for missing console logos
   */
  function getLogoFallbackSvg(name) {
    const initials = (name || 'SYS')
      .split(/[\s-]+/)
      .map(w => w[0])
      .filter(Boolean)
      .slice(0, 3)
      .join('')
      .toUpperCase();

    const svg = `
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 60" width="100%" height="100%">
        <rect width="160" height="60" rx="6" fill="#0f172a"/>
        <text x="80" y="36" font-family="-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif" font-size="20" font-weight="700" fill="#38bdf8" text-anchor="middle" dominant-baseline="middle">${escapeHtml(initials)}</text>
      </svg>
    `.trim();

    return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
  }

  /**
   * Helper: Generates SVG placeholder for missing console hardware images
   */
  function getConsoleFallbackSvg(name) {
    const label = (name || 'Console').substring(0, 16);
    const svg = `
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 110" width="100%" height="100%">
        <rect width="160" height="110" rx="8" fill="#0c121e"/>
        <rect x="18" y="24" width="124" height="60" rx="6" fill="#1e293b" stroke="#334155" stroke-width="2"/>
        <circle cx="45" cy="54" r="10" fill="#38bdf8"/>
        <rect x="95" y="48" width="28" height="12" rx="3" fill="#64748b"/>
        <text x="80" y="98" font-family="-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif" font-size="10" fill="#94a3b8" text-anchor="middle">${escapeHtml(label)}</text>
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
   * Builds the Console Type Filter buttons
   */
  function renderTypeFilterButtons() {
    filterTypesContainer.innerHTML = '';

    const allBtn = document.createElement('button');
    allBtn.type = 'button';
    allBtn.className = 'filter-type-btn active';
    allBtn.dataset.type = 'all';
    allBtn.textContent = 'All Types';
    allBtn.addEventListener('click', () => onTypeSelected('all', allBtn));
    filterTypesContainer.appendChild(allBtn);

    CONSOLE_TYPES.forEach(type => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'filter-type-btn';
      btn.dataset.type = String(type.id);
      btn.textContent = type.name;
      btn.addEventListener('click', () => onTypeSelected(String(type.id), btn));
      filterTypesContainer.appendChild(btn);
    });
  }

  function onTypeSelected(typeId, activeBtn) {
    currentTypeFilter = typeId;
    const btns = filterTypesContainer.querySelectorAll('.filter-type-btn, .filter-type-pill');
    btns.forEach(b => b.classList.remove('active'));
    activeBtn.classList.add('active');

    updateActiveFilterStyles();

    if (currentMode === 'detail') {
      setMode('list');
    }

    renderConsolesList();
    updateUrlState();
  }

  /**
   * Builds the Sticky Alphabet Pill Ray: [All], [#], [A]...[Z]
   */
  function renderAlphabetPillRay() {
    alphabetPillRay.innerHTML = '';

    const counts = { ALL: ALL_CONSOLES.length, '#': 0 };
    for (let i = 65; i <= 90; i++) {
      counts[String.fromCharCode(i)] = 0;
    }

    ALL_CONSOLES.forEach(con => {
      const k = getLeadingKey(con.name);
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
      btn.className = 'con-alphabet-pill';
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
        btn.title = `No consoles start with ${key}`;
      } else {
        btn.title = `Show platforms (${count})`;
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

    const pills = alphabetPillRay.querySelectorAll('.con-alphabet-pill');
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
    renderConsolesList();
  }

  /**
   * Filters and sorts consoles based on active state
   */
  function getFilteredConsoles() {
    return ALL_CONSOLES.filter(con => {
      if (currentLetter !== 'ALL') {
        const lead = getLeadingKey(con.name);
        if (lead !== currentLetter) {
          return false;
        }
      }

      if (currentTypeFilter !== 'all') {
        if (String(con.console_type_id) !== currentTypeFilter) {
          return false;
        }
      }

      if (currentSearchQuery !== '') {
        const q = currentSearchQuery.toLowerCase();
        const name = (con.name || '').toLowerCase();
        const maker = (con.maker_name || '').toLowerCase();
        if (!name.includes(q) && !maker.includes(q)) {
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
  function renderConsolesList() {
    const filtered = getFilteredConsoles();

    let letterDisplay = currentLetter === 'ALL' ? 'All' : (currentLetter === '#' ? '"#"' : `Letter "${currentLetter}"`);
    if (currentSearchQuery) {
      letterDisplay += ` matching "${escapeHtml(currentSearchQuery)}"`;
    }
    selectedLetterLabel.textContent = letterDisplay;
    consolesCountLabel.textContent = `(${filtered.length} ${filtered.length === 1 ? 'console' : 'consoles'})`;

    filterStatsText.textContent = `Showing ${filtered.length} of ${ALL_CONSOLES.length} platforms`;

    consolesCardsGrid.innerHTML = '';

    if (filtered.length === 0) {
      consolesCardsGrid.innerHTML = `
        <div class="con-empty-state">
          <span class="empty-icon">🔍</span>
          <strong>No platforms found</strong>
          <p style="font-size: 13px; margin: 0;">Try adjusting your search criteria, platform type filter, or alphabetical selection.</p>
        </div>
      `;
      return;
    }

    const fragment = document.createDocumentFragment();

    filtered.forEach(con => {
      const card = document.createElement('div');
      card.className = 'con-card';
      card.tabIndex = 0;
      card.role = 'button';
      card.setAttribute('aria-label', `View details for console ${con.name}`);

      const gamesCount = Number(con.games_count ?? con.game_count ?? 0);
      const fallbackUrl = getConsoleFallbackSvg(con.name);
      const imgUrl = formatImageUrl(con.image_path, fallbackUrl);

      const typeBg = con.badge_bg_color || '#1e3a8a';
      const typeColor = con.badge_font_color || '#93c5fd';
      const typeName = con.console_type_name || 'Home';

      card.innerHTML = `
        <div class="con-card-image-slot">
          <img 
            src="${escapeHtml(imgUrl)}" 
            alt="${escapeHtml(con.name)}" 
            class="con-card-img" 
            loading="lazy"
            onerror="this.onerror=null; this.src='${fallbackUrl}';"
          >
        </div>
        <div class="con-card-info">
          <div class="con-card-name" title="${escapeHtml(con.name)}">${escapeHtml(con.name)}</div>
          <div class="con-card-meta">
            <span class="badge-type-tag" style="background-color: ${escapeHtml(typeBg)}; color: ${escapeHtml(typeColor)};">
              ${escapeHtml(typeName)}
            </span>
            <span class="con-card-games-count">${gamesCount} ${gamesCount === 1 ? 'game' : 'games'}</span>
          </div>
        </div>
      `;

      card.addEventListener('click', () => {
        openConsoleDetail(con);
      });

      card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          openConsoleDetail(con);
        }
      });

      fragment.appendChild(card);
    });

    consolesCardsGrid.appendChild(fragment);
  }

  /**
   * Switches view mode: 'list' | 'detail'
   */
  function setMode(mode) {
    currentMode = mode;

    if (mode === 'list') {
      conListContainer.style.display = 'block';
      conDetailContainer.style.display = 'none';

      listStatusLine.style.display = 'flex';
      if (detailBreadcrumb) detailBreadcrumb.style.display = 'none';

      sortOrderToggleBtn.style.display = 'inline-flex';
      btnGoBack.style.display = 'none';
      activeConsole = null;

      // Sync active state on alphabet pills
      const pills = alphabetPillRay.querySelectorAll('.con-alphabet-pill');
      pills.forEach(p => p.classList.toggle('active', p.dataset.letter === currentLetter));

      renderConsolesList();
    } else {
      conListContainer.style.display = 'none';
      conDetailContainer.style.display = 'flex';

      listStatusLine.style.display = 'none';
      if (detailBreadcrumb) detailBreadcrumb.style.display = 'flex';

      sortOrderToggleBtn.style.display = 'none';
      btnGoBack.style.display = 'inline-flex';
    }

    updateUrlState();
  }

  /**
   * Loads and displays the Detail Mode for a console
   */
  async function openConsoleDetail(consoleItem, cachedDetail = null) {
    if (!consoleItem || !consoleItem.id) return;

    activeConsole = consoleItem;
    setMode('detail');

    const letterKey = getLeadingKey(consoleItem.name);
    if (breadcrumbLetterBtn) {
      breadcrumbLetterBtn.textContent = currentLetter === 'ALL' ? 'All' : `Letter "${letterKey}"`;
    }
    if (breadcrumbConsoleName) {
      breadcrumbConsoleName.textContent = consoleItem.name;
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });

    populateConsoleHeader(consoleItem);

    if (cachedDetail && Number(cachedDetail.console?.id) === Number(consoleItem.id)) {
      populateDetailSubsections(cachedDetail);
      return;
    }

    showLoading(true);
    try {
      const res = await fetch(`/api/consoles-portal/console/${consoleItem.id}`, {
        headers: { 'Accept': 'application/json' }
      });
      if (!res.ok) {
        throw new Error('Failed to load console details.');
      }
      const json = await res.json();
      const payload = (json && json.data) ? json.data : json;
      activeDetailData = payload;
      populateDetailSubsections(payload);
      if (payload.console) {
        populateConsoleHeader(payload.console);
      }
    } catch (err) {
      console.warn('API detail fetch fallback to basic details:', err);
      // Fallback: render subsections from console record if endpoint is not reachable
      populateDetailSubsections({
        console: consoleItem,
        files: consoleItem.downloadable_files || [],
        random_games: []
      });
    } finally {
      showLoading(false);
    }
  }

  /**
   * Populates the Top Console Detail Card (Logo, Image, Name, Maker, Year, Gen, Games Badge)
   */
  function populateConsoleHeader(con) {
    detailConsoleName.textContent = con.name;

    // Type Badge (if element exists)
    if (detailConsoleTypeBadge) {
      const typeBg = con.badge_bg_color || '#1e3a8a';
      const typeColor = con.badge_font_color || '#93c5fd';
      const typeName = con.console_type_name || 'Home';
      detailConsoleTypeBadge.textContent = typeName;
      detailConsoleTypeBadge.style.backgroundColor = typeBg;
      detailConsoleTypeBadge.style.color = typeColor;
      detailConsoleTypeBadge.style.display = 'inline-flex';
    }

    // Logo Image
    const logoFallback = getLogoFallbackSvg(con.name);
    const logoUrl = formatImageUrl(con.logo_path, logoFallback);
    detailConLogoBox.innerHTML = `
      <img 
        src="${escapeHtml(logoUrl)}" 
        alt="${escapeHtml(con.name)} logo" 
        class="con-detail-logo-img"
        onerror="this.onerror=null; this.src='${logoFallback}';"
      >
    `;

    // Console Hardware Image
    const conImgFallback = getConsoleFallbackSvg(con.name);
    const conImgUrl = formatImageUrl(con.image_path, conImgFallback);
    detailConImageBox.innerHTML = `
      <img 
        src="${escapeHtml(conImgUrl)}" 
        alt="${escapeHtml(con.name)}" 
        class="con-detail-hardware-img"
        onerror="this.onerror=null; this.src='${conImgFallback}';"
      >
    `;

    // Maker (Clickable, redirects to publishers_portal on detail mode with maker selected)
    const makerName = con.maker_name || '';
    const publisherId = con.publisher_id ? Number(con.publisher_id) : 0;
    if (publisherId > 0 && makerName) {
      detailMakerValue.innerHTML = `
        <a href="/publishers-portal?id=${publisherId}&mode=detail" class="con-maker-link" title="View ${escapeHtml(makerName)} in Publishers Portal">
          <span>${escapeHtml(makerName)}</span>
          <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13">
            <path fill-rule="evenodd" d="M5.22 14.78a.75.75 0 001.06 0l7.22-7.22v5.69a.75.75 0 001.5 0v-7.5a.75.75 0 00-.75-.75h-7.5a.75.75 0 000 1.5h5.69l-7.22 7.22a.75.75 0 000 1.06z" clip-rule="evenodd"/>
          </svg>
        </a>
      `;
    } else if (makerName) {
      detailMakerValue.textContent = makerName;
    } else {
      detailMakerValue.textContent = 'Unknown';
    }

    // Year
    detailYearValue.textContent = con.year ? String(con.year) : '—';

    // Generation
    let genDisplay = '—';
    if (con.generation) {
      const g = String(con.generation).trim();
      if (/^\d+$/.test(g)) {
        const n = parseInt(g, 10);
        const suffix = (n === 1) ? '1st' : (n === 2) ? '2nd' : (n === 3) ? '3rd' : `${n}th`;
        genDisplay = `${suffix} Generation`;
      } else {
        genDisplay = g;
      }
    }
    detailGenValue.textContent = genDisplay;

    // Games count & Distinct Interactive Pill / Link
    const gamesCount = Number(con.games_count ?? con.game_count ?? 0);
    detailGamesCountValue.textContent = `${gamesCount} ${gamesCount === 1 ? 'Game' : 'Games'}`;

    const gamesCatalogUrl = `/games-portal?console_id=${con.id}&console=${encodeURIComponent(con.name)}&mode=list`;
    if (detailGamesBadgeLink) {
      detailGamesBadgeLink.href = gamesCatalogUrl;
    }

    // "View All Games" Button Link in Games Showcase
    if (conViewAllGamesBtn) {
      conViewAllGamesBtn.href = gamesCatalogUrl;
    }
    if (conViewAllCountLabel) {
      conViewAllCountLabel.textContent = `(${gamesCount})`;
    }
  }

  /**
   * Helper: Resolves clean description/category for BIOS and system files
   */
  function getFileDescription(name) {
    const lower = (name || '').toLowerCase();
    if (lower === 'exec.bin') return 'Executive ROM / System BIOS';
    if (lower === 'grom.bin') return 'Graphics ROM / Character Generator';
    if (lower.endsWith('.bin') || lower.endsWith('.rom')) return 'System BIOS / ROM Image';
    if (lower.endsWith('.zip') || lower.endsWith('.7z')) return 'Compressed System Archive';
    if (lower.endsWith('.pdf')) return 'Documentation / Manual';
    return 'System Resource File';
  }

  /**
   * Populates Personal Notes / Specs, BIOS & Downloadable Files, Emulation Hub, and 15 Random Games
   */
  function populateDetailSubsections(data) {
    const con = data.console || activeConsole;
    const files = Array.isArray(data.files) ? data.files : (con.downloadable_files || []);
    const games = Array.isArray(data.random_games) ? data.random_games : [];

    // 1. Personal Notes / Specs (Clean typography without decorative banners)
    if (con.comments && con.comments.trim() !== '') {
      detailSpecsText.textContent = con.comments.trim();
      detailNotesSection.style.display = 'block';
      if (detailNotesSeparator) detailNotesSeparator.style.display = 'block';
    } else {
      detailSpecsText.textContent = '';
      detailNotesSection.style.display = 'none';
      if (detailNotesSeparator) detailNotesSeparator.style.display = 'none';
    }

    // 2. BIOS & Downloadable Files (Compact List / Table View immediately below Hero)
    if (files.length > 0) {
      detailDownloadsSubsection.style.display = 'flex';
      if (detailFilesCountBadge) {
        detailFilesCountBadge.textContent = `${files.length} ${files.length === 1 ? 'file' : 'files'}`;
      }
      detailFilesList.innerHTML = files.map(file => {
        const fileId = file.id;
        const name = file.display_name || 'Downloadable Resource';
        const dCount = Number(file.download_count || 0);
        const desc = getFileDescription(name);

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

    // 3. Emulation Info (Single Box: RetroArch core, PC emulator, Android emulator)
    const emuItems = [];

    // Known RetroArch core fallbacks if missing or unconfigured in DB
    const coreFallbacks = {
      83: { name: 'FreeIntv', link: 'https://docs.libretro.com/library/freeintv/' },
      79: { name: 'Gearcoleco', link: 'https://docs.libretro.com/library/gearcoleco/' },
      7:  { name: 'Atari800', link: 'https://docs.libretro.com/library/atari800/' },
      1:  { name: 'Stella 2023', link: 'https://docs.libretro.com/library/stella_2023/' },
      2:  { name: 'Mesen', link: 'https://docs.libretro.com/library/mesen/' },
      3:  { name: 'Snes9x', link: 'https://docs.libretro.com/library/snes9x/' },
      4:  { name: 'Gambatte', link: 'https://docs.libretro.com/library/gambatte/' },
      13: { name: 'ProSystem', link: 'https://docs.libretro.com/library/prosystem/' },
      16: { name: 'Genesis Plus GX', link: 'https://docs.libretro.com/library/genesis_plus_gx/' },
      17: { name: 'Beetle PCE Fast', link: 'https://docs.libretro.com/library/beetle_pce_fast/' },
      19: { name: 'Genesis Plus GX', link: 'https://docs.libretro.com/library/genesis_plus_gx/' },
      21: { name: 'FinalBurn Neo', link: 'https://docs.libretro.com/library/fbneo/' },
      25: { name: 'Beetle Handy', link: 'https://docs.libretro.com/library/beetle_handy/' },
      27: { name: 'Genesis Plus GX', link: 'https://docs.libretro.com/library/genesis_plus_gx/' },
      28: { name: 'Beetle Saturn', link: 'https://docs.libretro.com/library/beetle_saturn/' },
      29: { name: 'Mupen64Plus-Next', link: 'https://docs.libretro.com/library/mupen64plus/' },
      31: { name: 'DuckStation', link: 'https://docs.libretro.com/library/duckstation/' },
      37: { name: 'Gambatte', link: 'https://docs.libretro.com/library/gambatte/' },
      46: { name: 'Flycast', link: 'https://docs.libretro.com/library/flycast/' },
      48: { name: 'Dolphin', link: 'https://docs.libretro.com/library/dolphin/' },
      51: { name: 'mGBA', link: 'https://docs.libretro.com/library/mgba/' },
      56: { name: 'melonDS', link: 'https://docs.libretro.com/library/melonds/' },
      57: { name: 'PPSSPP', link: 'https://docs.libretro.com/library/ppsspp/' },
      89: { name: 'O2EM', link: 'https://docs.libretro.com/library/o2em/' }
    };

    // 1. RetroArch Core (Always shown with link button)
    let coreName = (con.retroarch_core && con.retroarch_core.trim() !== '') ? con.retroarch_core.trim() : null;
    let coreLink = (con.core_link && con.core_link.trim() !== '') ? con.core_link.trim() : null;

    if (!coreName && coreFallbacks[con.id]) {
      coreName = coreFallbacks[con.id].name;
      coreLink = coreLink || coreFallbacks[con.id].link;
    }
    if (!coreName) {
      coreName = (con.name ? con.name + ' Core' : 'RetroArch Core');
    }
    if (!coreLink) {
      coreLink = 'https://docs.libretro.com';
    }

    emuItems.push({
      platformLabel: 'RetroArch Core',
      iconSvg: `
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
          <line x1="6" y1="12" x2="10" y2="12"></line>
          <line x1="8" y1="10" x2="8" y2="14"></line>
          <line x1="15" y1="13" x2="15.01" y2="13"></line>
          <line x1="18" y1="11" x2="18.01" y2="11"></line>
          <rect x="2" y="6" width="20" height="12" rx="2"></rect>
        </svg>
      `.trim(),
      name: coreName,
      link: coreLink,
      linkText: 'Link'
    });

    // 2. PC / Standalone Emulator (Always shown with link button)
    let pcName = (con.emulator && con.emulator.trim() !== '') ? con.emulator.trim() : 'RetroArch / Standalone';
    let pcLink = (con.emulator_link && con.emulator_link.trim() !== '') 
      ? con.emulator_link.trim() 
      : `https://www.google.com/search?q=${encodeURIComponent((con.name || '') + ' PC emulator')}`;

    emuItems.push({
      platformLabel: 'PC Emulator',
      iconSvg: `
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
          <rect x="2" y="3" width="20" height="14" rx="2"></rect>
          <line x1="8" y1="21" x2="16" y2="21"></line>
          <line x1="12" y1="17" x2="12" y2="21"></line>
        </svg>
      `.trim(),
      name: pcName,
      link: pcLink,
      linkText: 'Link'
    });

    // 3. Android Emulator (Always shown with link button)
    let androidName = (con.emulator_android && con.emulator_android.trim() !== '') ? con.emulator_android.trim() : 'RetroArch (Android)';
    let androidLink = (con.emulator_android_link && con.emulator_android_link.trim() !== '') 
      ? con.emulator_android_link.trim() 
      : 'https://play.google.com/store/apps/details?id=com.retroarch.aarch64';

    emuItems.push({
      platformLabel: 'Android Emulator',
      iconSvg: `
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
          <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
          <line x1="12" y1="18" x2="12.01" y2="18"></line>
        </svg>
      `.trim(),
      name: androidName,
      link: androidLink,
      linkText: 'Link'
    });

    // Master Platform Variant info (if applicable)
    if (con.master_reference_id && con.master_console_name) {
      emuItems.push({
        platformLabel: 'Master Platform',
        iconSvg: `
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
            <rect x="4" y="4" width="16" height="16" rx="2"></rect>
            <rect x="9" y="9" width="6" height="6"></rect>
            <line x1="9" y1="1" x2="9" y2="4"></line>
            <line x1="15" y1="1" x2="15" y2="4"></line>
            <line x1="9" y1="20" x2="9" y2="23"></line>
            <line x1="15" y1="20" x2="15" y2="23"></line>
            <line x1="20" y1="9" x2="23" y2="9"></line>
            <line x1="20" y1="14" x2="23" y2="14"></line>
            <line x1="1" y1="9" x2="4" y2="9"></line>
            <line x1="1" y1="14" x2="4" y2="14"></line>
          </svg>
        `.trim(),
        name: con.master_console_name,
        link: `/consoles-portal?id=${con.master_reference_id}&mode=detail`,
        linkText: 'View Master'
      });
    }

    if (emuItems.length > 0) {
      detailEmulationSection.style.display = 'flex';
      detailEmuGrid.innerHTML = emuItems.map(item => `
        <div class="con-emu-row">
          <div class="emu-row-left">
            <div class="emu-type-tag">
              ${item.iconSvg}
              <span>${escapeHtml(item.platformLabel)}</span>
            </div>
            <div class="emu-name-title">${escapeHtml(item.name)}</div>
          </div>
          <div class="emu-row-right">
            ${item.link ? `
              <a href="${escapeHtml(item.link)}" ${item.link.startsWith('/') ? '' : 'target="_blank" rel="noopener noreferrer"'} class="btn-emu-link" title="Open ${escapeHtml(item.linkText || 'Link')}">
                <span>${escapeHtml(item.linkText || 'Link')}</span>
                <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13">
                  <path fill-rule="evenodd" d="M5.22 14.78a.75.75 0 001.06 0l7.22-7.22v5.69a.75.75 0 001.5 0v-7.5a.75.75 0 00-.75-.75h-7.5a.75.75 0 000 1.5h5.69l-7.22 7.22a.75.75 0 000 1.06z" clip-rule="evenodd"/>
                </svg>
              </a>
            ` : `
              <span style="font-size: 12px; color: var(--text-muted, #94a3b8); font-style: italic;">No link available</span>
            `}
          </div>
        </div>
      `).join('');
    } else {
      detailEmulationSection.style.display = 'none';
      detailEmuGrid.innerHTML = '';
    }

    // 4. Random Games Showcase Grid (15 Titles)
    conGamesSubgrid.innerHTML = '';
    if (games.length === 0) {
      conGamesSubgrid.innerHTML = `
        <div style="grid-column: 1 / -1; padding: 28px; text-align: center; color: var(--text-dim); font-size: 13px;">
          No catalogued games for this platform yet.
        </div>
      `;
    } else {
      const gFragment = document.createDocumentFragment();
      games.forEach(game => {
        const fallback = getGameFallbackSvg(game.title);
        const artPath = game.boxart_path || game.screenshot_path;
        const imgUrl = formatImageUrl(artPath, fallback);

        const card = document.createElement('a');
        card.className = 'con-game-card';
        card.href = `/games-portal?id=${game.id}&mode=detail`;
        card.title = `View ${game.title} on Games Portal`;

        card.innerHTML = `
          <div class="con-game-thumb-slot">
            <img 
              src="${escapeHtml(imgUrl)}" 
              alt="${escapeHtml(game.title)}" 
              class="con-game-thumb-img" 
              loading="lazy"
              onerror="this.onerror=null; this.src='${fallback}';"
            >
          </div>
          <div class="con-game-title">${escapeHtml(game.title)}</div>
          <div class="con-game-meta">${escapeHtml(game.publisher_name || '')}${game.year ? ` • ${escapeHtml(game.year)}` : ''}</div>
        `;

        gFragment.appendChild(card);
      });
      conGamesSubgrid.appendChild(gFragment);
    }
  }

  /**
   * Helper: Show or hide loading spinner
   */
  function showLoading(show) {
    loadingOverlay.style.display = show ? 'flex' : 'none';
  }

  /**
   * Updates browser URL history cleanly
   */
  function updateUrlState() {
    const params = new URLSearchParams();
    if (currentMode === 'detail' && activeConsole) {
      params.set('id', activeConsole.id);
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
    history.replaceState({ mode: currentMode, id: activeConsole?.id, letter: currentLetter }, '', newRelativePathQuery);
  }

  // --------------------------------------------------------------------------
  // Event Bindings
  // --------------------------------------------------------------------------

  function updateActiveFilterStyles() {
    const hasQuery = currentSearchQuery !== '';
    const hasType = currentTypeFilter !== 'all';
    const hasAnyFilter = hasQuery || hasType;
    if (btnResetFilters) {
      btnResetFilters.style.display = hasAnyFilter ? 'inline-flex' : 'none';
    }
  }

  // Search input typing
  let searchDebounceTimer = null;
  searchInput.addEventListener('input', (e) => {
    currentSearchQuery = e.target.value.trim();
    clearSearchBtn.style.display = currentSearchQuery ? 'block' : 'none';
    updateActiveFilterStyles();

    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
      if (currentMode === 'detail' && currentSearchQuery !== '') {
        setMode('list');
      }
      renderConsolesList();
      updateUrlState();
    }, 180);
  });

  // Clear search input
  clearSearchBtn.addEventListener('click', () => {
    searchInput.value = '';
    currentSearchQuery = '';
    clearSearchBtn.style.display = 'none';
    searchInput.focus();
    updateActiveFilterStyles();
    renderConsolesList();
    updateUrlState();
  });

  // Clear / Reset Filters button (resets search and type filter, preserves or shows list)
  if (btnResetFilters) {
    btnResetFilters.addEventListener('click', () => {
      currentSearchQuery = '';
      currentTypeFilter = 'all';
      searchInput.value = '';
      clearSearchBtn.style.display = 'none';

      const typeBtns = filterTypesContainer.querySelectorAll('.filter-type-btn, .filter-type-pill');
      typeBtns.forEach(b => b.classList.toggle('active', b.dataset.type === 'all'));

      updateActiveFilterStyles();

      if (currentMode === 'detail') {
        setMode('list');
      } else {
        renderConsolesList();
        updateUrlState();
      }
    });
  }

  // Sort Order Toggle (Ascending A-Z vs Descending Z-A)
  sortOrderToggleBtn.addEventListener('click', () => {
    currentSortAsc = !currentSortAsc;
    sortDirectionLabel.textContent = currentSortAsc ? 'A → Z' : 'Z → A';
    sortArrowIcon.textContent = currentSortAsc ? '▲' : '▼';
    sortOrderToggleBtn.title = currentSortAsc ? 'Sorted Ascending (A to Z). Click to reverse.' : 'Sorted Descending (Z to A). Click to reverse.';
    renderConsolesList();
  });

  // Go Back button from Detail Mode
  btnGoBack.addEventListener('click', () => {
    setMode('list');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  // Breadcrumb Letter button (if present)
  if (breadcrumbLetterBtn) {
    breadcrumbLetterBtn.addEventListener('click', () => {
      setMode('list');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // Browser back / forward buttons (popstate)
  window.addEventListener('popstate', (e) => {
    const urlParams = new URLSearchParams(window.location.search);
    const conId = parseInt(urlParams.get('id') || '0', 10);
    const letter = urlParams.get('letter') || 'ALL';

    currentLetter = letter;
    const pills = alphabetPillRay.querySelectorAll('.con-alphabet-pill');
    pills.forEach(p => p.classList.toggle('active', p.dataset.letter === currentLetter));

    if (conId > 0) {
      const con = ALL_CONSOLES.find(c => Number(c.id) === conId);
      if (con) {
        openConsoleDetail(con);
        return;
      }
    }

    setMode('list');
    renderConsolesList();
  });

  // --------------------------------------------------------------------------
  // Initialization
  // --------------------------------------------------------------------------
  function init() {
    renderTypeFilterButtons();
    renderAlphabetPillRay();

    if (INITIAL_LETTER && (INITIAL_LETTER === '#' || (INITIAL_LETTER >= 'A' && INITIAL_LETTER <= 'Z'))) {
      currentLetter = INITIAL_LETTER;
      const pills = alphabetPillRay.querySelectorAll('.con-alphabet-pill');
      pills.forEach(p => p.classList.toggle('active', p.dataset.letter === currentLetter));
    }

    renderConsolesList(); // Pre-render list so returning to list works seamlessly
    updateActiveFilterStyles();

    if (INITIAL_CON_ID > 0) {
      const con = ALL_CONSOLES.find(c => Number(c.id) === INITIAL_CON_ID);
      if (con) {
        openConsoleDetail(con, INITIAL_DETAIL);
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
