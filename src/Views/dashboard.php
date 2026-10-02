<?php
/**
 * src/Views/dashboard.php
 * Command Center Dashboard View with Tabbed General Stats and Player Stats.
 *
 * Variables provided by DashboardController:
 * @var array<string, mixed> $kpi
 * @var array<string, int> $counts
 * @var array<int, array<string, mixed>> $topConsoles
 * @var array<int, array<string, mixed>> $topDownloads
 * @var array<int, array<string, mixed>> $playedConsoles
 * @var array<int, array<string, mixed>> $latestAdditions
 * @var array<string, mixed> $playerData
 */

declare(strict_types=1);

use Vault\Auth\Auth;
use Vault\Services\View;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

$canConsoles = Auth::can('consoles', 'read');

$healthPct     = (float)($kpi['health_pct'] ?? 0);
$completePct   = (float)($kpi['complete_pct'] ?? 0);
$secondaryPct  = (float)($kpi['secondary_pct'] ?? 0);
$basicPct      = (float)($kpi['basic_pct'] ?? 0);
$growthPct     = (float)($kpi['catalog_growth_pct'] ?? 0);
$addedMonth    = (int)($kpi['catalog_added_month'] ?? 0);

$myCollection     = $playerData['my_collection'] ?? [];
$currentlyPlaying = $playerData['currently_playing'] ?? [];
$summaryOfLife    = $playerData['summary'] ?? [];
$pieData          = $playerData['pie'] ?? [];

// Collect distinct consoles in downloaded collection for filtering
$collectionConsoles = [];
foreach ($myCollection as $item) {
    if (!empty($item['console_name'])) {
        $collectionConsoles[$item['console_name']] = true;
    }
}
ksort($collectionConsoles);
?>

<style>
/* --------------------------------------------------------------------------
   Dashboard Scoped Styles & Tab Architecture
   -------------------------------------------------------------------------- */
.dashboard-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
  width: 100%;
  max-width: 1400px;
  margin: 0 auto;
  padding: 16px 20px 48px;
  box-sizing: border-box;
}

/* Full-Width Master Tabs Header */
.dashboard-tabs-bar {
  display: flex;
  width: 100%;
  background: rgba(15, 23, 42, 0.7);
  border: 1px solid var(--border, #1e293b);
  border-radius: 8px;
  padding: 4px;
  gap: 4px;
  box-sizing: border-box;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
}

.dashboard-tab-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 9px;
  padding: 12px 20px;
  font-size: 14px;
  font-weight: 700;
  color: #94a3b8;
  background: transparent;
  border: 1px solid transparent;
  border-radius: 6px;
  cursor: pointer;
  transition: all var(--transition-fast, 0.15s ease);
  user-select: none;
  outline: none;
}

.dashboard-tab-btn:hover {
  color: #f1f5f9;
  background: rgba(255, 255, 255, 0.04);
}

.dashboard-tab-btn.active {
  color: #38bdf8;
  background: rgba(14, 165, 233, 0.14);
  border-color: rgba(56, 189, 248, 0.35);
  box-shadow: 0 0 14px rgba(56, 189, 248, 0.2);
}

.dashboard-tab-btn .tab-badge {
  font-size: 11px;
  padding: 2px 7px;
  border-radius: 9999px;
  background: rgba(255, 255, 255, 0.08);
  color: inherit;
}

/* Tab Panes */
.dashboard-pane {
  display: none;
  flex-direction: column;
  gap: 20px;
  width: 100%;
  animation: fadeInPane 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.dashboard-pane.active {
  display: flex;
}

@keyframes fadeInPane {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Stat Cards Grid (Top 3 Boxes) */
.dashboard-stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  width: 100%;
  box-sizing: border-box;
}

.dash-stat-card {
  background: linear-gradient(145deg, #131d2e, #0c1421);
  border: 1px solid rgba(56, 189, 248, 0.18);
  border-radius: 10px;
  padding: 18px 20px;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.35);
  display: flex;
  flex-direction: column;
  gap: 4px;
  box-sizing: border-box;
  transition: transform var(--transition-fast), border-color var(--transition-fast);
}

.dash-stat-card:hover {
  border-color: rgba(56, 189, 248, 0.4);
  transform: translateY(-2px);
}

.dash-stat-label {
  font-size: 11px;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.dash-stat-value {
  font-size: 32px;
  font-weight: 800;
  line-height: 1.15;
  color: #f8fafc;
}

.dash-stat-subtext {
  font-size: 12px;
  color: #64748b;
  margin-top: 4px;
}

/* Catalog Health Bar Card */
.catalog-health-card {
  background: linear-gradient(145deg, #131d2e, #0c1421);
  border: 1px solid rgba(56, 189, 248, 0.18);
  border-radius: 10px;
  padding: 18px 20px;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.35);
  display: flex;
  flex-direction: column;
  gap: 12px;
  box-sizing: border-box;
}

.health-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.health-title {
  font-size: 12px;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.health-total-badge {
  font-size: 12px;
  font-weight: 700;
  color: #38bdf8;
}

.health-multi-bar {
  display: flex;
  width: 100%;
  height: 16px;
  border-radius: 8px;
  overflow: hidden;
  background: #090e17;
  border: 1px solid rgba(255, 255, 255, 0.05);
}

.health-segment {
  height: 100%;
  transition: width 0.4s ease;
}

.health-seg-complete {
  background: linear-gradient(90deg, #059669, #10b981);
}

.health-seg-secondary {
  background: linear-gradient(90deg, #d97706, #f59e0b);
}

.health-seg-basic {
  background: linear-gradient(90deg, #dc2626, #ef4444);
}

.health-legend-row {
  display: flex;
  align-items: center;
  gap: 18px;
  flex-wrap: wrap;
  font-size: 12px;
}

.health-legend-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #cbd5e1;
}

.health-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  flex-shrink: 0;
}

/* Two-Column Responsive Row */
.dashboard-two-col-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
  width: 100%;
  box-sizing: border-box;
}

/* Card Wrapper for Grids & Lists */
.dash-card {
  background: #0c1421;
  border: 1px solid var(--border, #1e293b);
  border-radius: 10px;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.35);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
}

.dash-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  background: rgba(15, 23, 42, 0.6);
  border-bottom: 1px solid var(--border, #1e293b);
}

.dash-card-title {
  font-size: 12.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #e2e8f0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.dash-card-body {
  padding: 14px 18px;
  flex: 1;
}

/* Clean Data Tables */
.dash-table-wrap {
  width: 100%;
  overflow-x: auto;
}

.dash-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12.5px;
}

.dash-table th {
  text-align: left;
  padding: 8px 10px;
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.dash-table td {
  padding: 10px 10px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  color: #cbd5e1;
}

.dash-table tr:last-child td {
  border-bottom: none;
}

.dash-table tr:hover td {
  background: rgba(56, 189, 248, 0.04);
}

/* Console Badge */
.dash-console-pill {
  font-size: 9.5px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 4px;
  display: inline-block;
  letter-spacing: 0.3px;
  text-transform: uppercase;
  margin-left: 6px;
  vertical-align: middle;
}

/* Most Played Consoles Podium */
.podium-container {
  display: flex;
  align-items: flex-end;
  justify-content: center;
  gap: 12px;
  padding: 20px 10px 10px;
  min-height: 180px;
}

.podium-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 30%;
  max-width: 160px;
  text-align: center;
}

.podium-pedestal {
  width: 100%;
  border-radius: 8px 8px 0 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 12px 8px;
  box-sizing: border-box;
}

.pedestal-1 {
  height: 110px;
  background: linear-gradient(180deg, rgba(234, 179, 8, 0.25) 0%, rgba(234, 179, 8, 0.08) 100%);
  border: 1px solid rgba(234, 179, 8, 0.45);
  border-bottom: none;
  order: 2;
}

.pedestal-2 {
  height: 85px;
  background: linear-gradient(180deg, rgba(148, 163, 184, 0.25) 0%, rgba(148, 163, 184, 0.08) 100%);
  border: 1px solid rgba(148, 163, 184, 0.45);
  border-bottom: none;
  order: 1;
}

.pedestal-3 {
  height: 65px;
  background: linear-gradient(180deg, rgba(180, 83, 9, 0.25) 0%, rgba(180, 83, 9, 0.08) 100%);
  border: 1px solid rgba(180, 83, 9, 0.45);
  border-bottom: none;
  order: 3;
}

.podium-medal {
  font-size: 26px;
  line-height: 1;
  margin-bottom: 6px;
}

.podium-name {
  font-size: 12px;
  font-weight: 700;
  color: #f1f5f9;
  margin-bottom: 4px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 100%;
}

.podium-count {
  font-size: 11px;
  font-weight: 600;
  color: #38bdf8;
}

/* Latest Additions List */
.latest-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.latest-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  border-radius: 6px;
  background: rgba(15, 23, 42, 0.5);
  border: 1px solid rgba(255, 255, 255, 0.05);
}

.latest-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  overflow: hidden;
}

.latest-title {
  font-weight: 600;
  color: #f1f5f9;
  font-size: 12.5px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.latest-date {
  font-size: 11px;
  color: #64748b;
  white-space: nowrap;
}

/* --------------------------------------------------------------------------
   Player Stats Components: Pie Chart & Summary of Life
   -------------------------------------------------------------------------- */
.player-overview-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  width: 100%;
  box-sizing: border-box;
}

/* Donut Chart Card */
.donut-card-body {
  display: flex;
  align-items: center;
  justify-content: space-around;
  gap: 20px;
  padding: 16px 20px;
  flex-wrap: wrap;
}

.donut-svg-wrap {
  position: relative;
  width: 170px;
  height: 170px;
  flex-shrink: 0;
}

.donut-svg {
  transform: rotate(-90deg);
  width: 100%;
  height: 100%;
}

.donut-center-info {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  pointer-events: none;
}

.donut-center-val {
  font-size: 22px;
  font-weight: 800;
  color: #f8fafc;
  line-height: 1;
}

.donut-center-lbl {
  font-size: 9.5px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-top: 3px;
}

.donut-legend {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
  min-width: 160px;
}

.donut-legend-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
  color: #cbd5e1;
}

.legend-color-dot {
  width: 10px;
  height: 10px;
  border-radius: 3px;
  flex-shrink: 0;
}

/* Summary of Life Grid */
.summary-life-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  height: 100%;
}

.summary-pill-box {
  background: rgba(15, 23, 42, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 8px;
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 4px;
}

.summary-pill-label {
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #64748b;
}

.summary-pill-val {
  font-size: 14px;
  font-weight: 700;
  color: #f1f5f9;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Currently in Play Grid */
.currently-playing-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 12px;
}

.in-play-card {
  display: flex;
  align-items: center;
  gap: 12px;
  background: rgba(15, 23, 42, 0.5);
  border: 1px solid rgba(56, 189, 248, 0.25);
  border-radius: 8px;
  padding: 10px;
  transition: all var(--transition-fast);
}

.in-play-card:hover {
  border-color: #38bdf8;
  background: rgba(15, 23, 42, 0.8);
}

.in-play-thumb {
  width: 48px;
  height: 60px;
  border-radius: 4px;
  overflow: hidden;
  background: #030712;
  flex-shrink: 0;
}

.in-play-thumb img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.in-play-info {
  flex: 1;
  min-width: 0;
}

.in-play-title {
  font-size: 13px;
  font-weight: 700;
  color: #f8fafc;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.in-play-meta {
  font-size: 11px;
  color: #64748b;
  margin-top: 3px;
}

/* --------------------------------------------------------------------------
   My Collection (Downloaded Games) Section
   -------------------------------------------------------------------------- */
.my-collection-section {
  display: flex;
  flex-direction: column;
  gap: 14px;
  width: 100%;
  box-sizing: border-box;
}

.collection-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  background: rgba(15, 23, 42, 0.6);
  border: 1px solid var(--border, #1e293b);
  border-radius: 8px;
  padding: 12px 16px;
  box-sizing: border-box;
}

.toolbar-search-input {
  flex: 1;
  min-width: 180px;
  height: 34px;
  padding: 0 12px;
  font-size: 12.5px;
  background: #070b14;
  border: 1px solid var(--border, #1e293b);
  border-radius: 4px;
  color: #f1f5f9;
  outline: none;
}

.toolbar-search-input:focus {
  border-color: #38bdf8;
}

.toolbar-select {
  height: 34px;
  padding: 0 24px 0 10px;
  font-size: 12px;
  font-weight: 600;
  background: #070b14;
  border: 1px solid var(--border, #1e293b);
  border-radius: 4px;
  color: #f1f5f9;
  outline: none;
  cursor: pointer;
}

/* Downloaded Game Cards Grid */
.downloaded-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
  gap: 14px;
  width: 100%;
  box-sizing: border-box;
}

.my-game-card {
  background: linear-gradient(145deg, #131d2e, #0c1421);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3);
  display: flex;
  flex-direction: column;
  transition: all var(--transition-fast);
  box-sizing: border-box;
}

.my-game-card:hover {
  border-color: rgba(56, 189, 248, 0.4);
  transform: translateY(-2px);
}

.my-card-header {
  display: flex;
  gap: 12px;
  padding: 12px;
}

.my-card-thumb {
  width: 60px;
  height: 75px;
  border-radius: 4px;
  background: #03050a;
  overflow: hidden;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.my-card-thumb img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.my-card-meta {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.my-card-title {
  font-size: 13.5px;
  font-weight: 700;
  color: #f8fafc;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.my-card-date {
  font-size: 11px;
  color: #64748b;
}

.my-card-tags {
  display: flex;
  align-items: center;
  gap: 5px;
  flex-wrap: wrap;
  margin-top: 4px;
}

.my-tag {
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 3px;
  background: rgba(255, 255, 255, 0.06);
  color: #94a3b8;
}

/* Card Interactive Actions (Checkboxes) */
.my-card-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: rgba(10, 16, 26, 0.8);
  border-top: 1px solid rgba(255, 255, 255, 0.05);
  margin-top: auto;
}

.game-status-label {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-size: 12px;
  font-weight: 600;
  color: #cbd5e1;
  cursor: pointer;
  user-select: none;
}

.game-status-label input[type="checkbox"] {
  width: 15px;
  height: 15px;
  cursor: pointer;
  accent-color: #38bdf8;
}

.game-status-label.disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.game-status-label.disabled input[type="checkbox"] {
  cursor: not-allowed;
}

/* Empty State */
.dash-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 40px 20px;
  color: #64748b;
  text-align: center;
}

.dash-empty-icon {
  font-size: 32px;
  opacity: 0.6;
}

/* --------------------------------------------------------------------------
   Mobile Responsive Rules
   -------------------------------------------------------------------------- */
@media (max-width: 860px) {
  .dashboard-container {
    padding: 12px 10px 36px;
    gap: 16px;
  }

  .dashboard-stats-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .dashboard-two-col-row,
  .player-overview-row {
    grid-template-columns: 1fr;
    gap: 14px;
  }

  .summary-life-grid {
    grid-template-columns: 1fr;
    gap: 8px;
  }

  .donut-card-body {
    flex-direction: column;
    align-items: center;
  }

  .downloaded-cards-grid {
    grid-template-columns: 1fr;
  }

  .podium-container {
    gap: 8px;
  }
}
</style>

<div class="dashboard-container">

  <!-- ========================================================================
       TOP MASTER TABS (General Stats vs Player Stats - Full Width)
       ======================================================================== -->
  <div class="dashboard-tabs-bar" role="tablist">
    <button type="button"
            id="tabGeneralBtn"
            class="dashboard-tab-btn active"
            onclick="switchDashboardTab('general')"
            role="tab"
            aria-selected="true"
            aria-controls="paneGeneral">
      <span>📊 General Stats</span>
    </button>
    <button type="button"
            id="tabPlayerBtn"
            class="dashboard-tab-btn"
            onclick="switchDashboardTab('player')"
            role="tab"
            aria-selected="false"
            aria-controls="panePlayer">
      <span>🎮 Player Stats</span>
      <span class="tab-badge" id="playerTabDownloadedBadge"><?= count($myCollection) ?> Downloaded</span>
    </button>
  </div>

  <!-- ========================================================================
       TAB 1: GENERAL STATS
       ======================================================================== -->
  <div class="dashboard-pane active" id="paneGeneral" role="tabpanel">

    <!-- Top 3 Metric Cards: Total Games, In Collection, Catalog Growth -->
    <div class="dashboard-stats-grid">
      <!-- 1. Total Games & Owned Count -->
      <div class="dash-stat-card">
        <div class="dash-stat-label">Total Games</div>
        <div class="dash-stat-value" id="kpiTotalGames"><?= number_format((int)($kpi['total_games'] ?? 0)) ?></div>
        <div class="dash-stat-subtext">
          <span style="color: #38bdf8; font-weight: 700;"><?= number_format((int)($kpi['owned_games'] ?? 0)) ?></span> in physical library
        </div>
      </div>

      <!-- 2. In Collection (% of total) -->
      <div class="dash-stat-card">
        <div class="dash-stat-label">In Collection</div>
        <div class="dash-stat-value" style="color: var(--warning, #f59e0b);" id="kpiOwnedPct"><?= View::e((string)($kpi['owned_pct'] ?? 0)) ?>%</div>
        <div class="dash-stat-subtext">
          <span style="color: #f1f5f9; font-weight: 600;"><?= number_format((int)($kpi['owned_games'] ?? 0)) ?></span> of <?= number_format((int)($kpi['total_games'] ?? 0)) ?> titles in collection
        </div>
      </div>

      <!-- 3. Catalog Growth (Last Month % and count) -->
      <div class="dash-stat-card">
        <div class="dash-stat-label">Catalog Growth</div>
        <div class="dash-stat-value" style="color: #10b981;" id="kpiGrowthPct">+<?= View::e((string)$growthPct) ?>%</div>
        <div class="dash-stat-subtext" id="kpiAddedMonth">
          <span style="color: #38bdf8; font-weight: 700;"><?= number_format($addedMonth) ?></span> games added in last 30 days
        </div>
      </div>
    </div>

    <!-- Catalog Health Bar (Full width bar showing Complete, Secondary, Basic) -->
    <div class="catalog-health-card">
      <div class="health-header-row">
        <div class="health-title">Catalog Health</div>
        <div class="health-total-badge" id="healthPctBadge"><?= View::e((string)$healthPct) ?>% Complete</div>
      </div>

      <!-- Segmented Bar -->
      <div class="health-multi-bar" title="Catalog Health Distribution">
        <div class="health-segment health-seg-complete" id="healthSegComplete" style="width: <?= $completePct ?>%;"></div>
        <div class="health-segment health-seg-secondary" id="healthSegSecondary" style="width: <?= $secondaryPct ?>%;"></div>
        <div class="health-segment health-seg-basic" id="healthSegBasic" style="width: <?= $basicPct ?>%;"></div>
      </div>

      <!-- Legend Line with Numbers and Percentages -->
      <div class="health-legend-row" id="healthLegend">
        <div class="health-legend-item">
          <span class="health-dot" style="background: #10b981;"></span>
          <span><strong style="color: #10b981;"><?= number_format((int)($kpi['complete_games'] ?? 0)) ?></strong> complete (<?= $completePct ?>%)</span>
        </div>
        <div class="health-legend-item">
          <span class="health-dot" style="background: #f59e0b;"></span>
          <span><strong style="color: #f59e0b;"><?= number_format((int)($kpi['missing_secondary'] ?? 0)) ?></strong> missing secondary (<?= $secondaryPct ?>%)</span>
        </div>
        <div class="health-legend-item">
          <span class="health-dot" style="background: #ef4444;"></span>
          <span><strong style="color: #ef4444;"><?= number_format((int)($kpi['missing_basic'] ?? 0)) ?></strong> missing basic (<?= $basicPct ?>%)</span>
        </div>
      </div>
    </div>

    <!-- Two-Column Row: Top Systems (Owned) & Top Downloads -->
    <div class="dashboard-two-col-row">
      <!-- 1. Top Systems (Owned) -->
      <div class="dash-card">
        <div class="dash-card-header">
          <span class="dash-card-title">🕹️ Top Systems (Owned)</span>
          <?php if ($canConsoles): ?>
            <a href="/consoles" style="color: #38bdf8; font-size: 11px; text-decoration: none; font-weight: 600;">View All &rarr;</a>
          <?php endif; ?>
        </div>
        <div class="dash-table-wrap">
          <table class="dash-table">
            <thead>
              <tr>
                <th>System</th>
                <th style="width: 80px; text-align: right;">Owned</th>
                <th style="width: 80px; text-align: right;">Library</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($topConsoles)): ?>
                <?php foreach ($topConsoles as $c): ?>
                  <tr>
                    <td style="font-weight: 600;">
                      <?= View::e($c['console'] ?? '') ?>
                      <?php if (!empty($c['console_type_name'])): ?>
                        <span class="dash-console-pill" style="background-color: <?= View::e($c['badge_bg_color'] ?? '#1e3a8a') ?>; color: <?= View::e($c['badge_font_color'] ?? '#93c5fd') ?>; border: 1px solid <?= View::e($c['badge_font_color'] ?? '#93c5fd') ?>44;">
                          <?= View::e($c['console_type_name']) ?>
                        </span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align: right; font-weight: 700; color: #38bdf8;"><?= number_format((int)$c['owned_titles']) ?></td>
                    <td style="text-align: right; color: #64748b;"><?= number_format((int)$c['total_titles']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="3" style="text-align: center; color: #64748b; padding: 20px;">No system data available.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 2. Top Downloads Grid -->
      <div class="dash-card">
        <div class="dash-card-header">
          <span class="dash-card-title">⬇️ Top Downloads (All-Time)</span>
          <span style="font-size: 11px; color: #64748b;">Top 10</span>
        </div>
        <div class="dash-table-wrap">
          <table class="dash-table" id="topDownloadsTable">
            <thead>
              <tr>
                <th style="width: 32px;">#</th>
                <th>Game</th>
                <th style="width: 90px; text-align: right;">Downloads</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($topDownloads)): ?>
                <?php foreach ($topDownloads as $idx => $g): ?>
                  <tr>
                    <td style="color: #64748b; font-weight: 700;"><?= $idx + 1 ?></td>
                    <td style="font-weight: 600;">
                      <?= View::e($g['title']) ?>
                      <?php if (!empty($g['console_name'])): ?>
                        <span class="dash-console-pill" style="background-color: <?= View::e($g['badge_bg_color'] ?? '#1e3a8a') ?>; color: <?= View::e($g['badge_font_color'] ?? '#93c5fd') ?>; border: 1px solid <?= View::e($g['badge_font_color'] ?? '#93c5fd') ?>44;">
                          <?= View::e($g['console_name']) ?>
                        </span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align: right; font-weight: 700; color: #10b981;">
                      <?= number_format((int)$g['download_count']) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="3" style="text-align: center; color: #64748b; padding: 24px;">No downloads recorded yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Two-Column Row: Most Played Consoles Podium & Latest Additions -->
    <div class="dashboard-two-col-row">
      <!-- 3. Most Played Consoles (Podium Ranking) -->
      <div class="dash-card">
        <div class="dash-card-header">
          <span class="dash-card-title">🏆 Most Played Consoles Ranking</span>
        </div>
        <div class="dash-card-body" id="podiumCardBody">
          <?php if (!empty($playedConsoles)): ?>
            <div class="podium-container">
              <!-- 2nd Place: Silver -->
              <?php if (isset($playedConsoles[1])): ?>
                <div class="podium-col">
                  <div class="podium-name" title="<?= View::e($playedConsoles[1]['console_name']) ?>"><?= View::e($playedConsoles[1]['console_name']) ?></div>
                  <div class="podium-pedestal pedestal-2">
                    <span class="podium-medal">🥈</span>
                    <span class="podium-count"><?= number_format((int)$playedConsoles[1]['played_count']) ?> plays</span>
                  </div>
                </div>
              <?php endif; ?>

              <!-- 1st Place: Gold -->
              <?php if (isset($playedConsoles[0])): ?>
                <div class="podium-col">
                  <div class="podium-name" title="<?= View::e($playedConsoles[0]['console_name']) ?>"><?= View::e($playedConsoles[0]['console_name']) ?></div>
                  <div class="podium-pedestal pedestal-1">
                    <span class="podium-medal">🥇</span>
                    <span class="podium-count"><?= number_format((int)$playedConsoles[0]['played_count']) ?> plays</span>
                  </div>
                </div>
              <?php endif; ?>

              <!-- 3rd Place: Bronze -->
              <?php if (isset($playedConsoles[2])): ?>
                <div class="podium-col">
                  <div class="podium-name" title="<?= View::e($playedConsoles[2]['console_name']) ?>"><?= View::e($playedConsoles[2]['console_name']) ?></div>
                  <div class="podium-pedestal pedestal-3">
                    <span class="podium-medal">🥉</span>
                    <span class="podium-count"><?= number_format((int)$playedConsoles[2]['played_count']) ?> plays</span>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="dash-empty-state">
              <span class="dash-empty-icon">🎮</span>
              <p style="font-size: 12px; max-width: 320px; line-height: 1.5; margin: 0;">
                No gameplay activity recorded yet. Check games as <strong>Played</strong> in the Player Stats tab to build your console ranking podium!
              </p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 4. Latest Additions -->
      <div class="dash-card">
        <div class="dash-card-header">
          <span class="dash-card-title">✨ Latest Additions</span>
          <span style="font-size: 11px; color: #64748b;">Recent Catalog Updates</span>
        </div>
        <div class="dash-card-body">
          <div class="latest-list" id="latestAdditionsList">
            <?php if (!empty($latestAdditions)): ?>
              <?php foreach ($latestAdditions as $add): ?>
                <div class="latest-item">
                  <div class="latest-title-wrap">
                    <span class="latest-title" title="<?= View::e($add['title']) ?>"><?= View::e($add['title']) ?></span>
                    <?php if (!empty($add['console_name'])): ?>
                      <span class="dash-console-pill" style="background-color: <?= View::e($add['badge_bg_color'] ?? '#1e3a8a') ?>; color: <?= View::e($add['badge_font_color'] ?? '#93c5fd') ?>; border: 1px solid <?= View::e($add['badge_font_color'] ?? '#93c5fd') ?>44;">
                        <?= View::e($add['console_name']) ?>
                      </span>
                    <?php endif; ?>
                  </div>
                  <span class="latest-date"><?= date('M j, Y', strtotime($add['created'])) ?></span>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="dash-empty-state">
                <span class="dash-empty-icon">📦</span>
                <p style="font-size: 12px; margin: 0;">No recently added games found.</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- ========================================================================
       TAB 2: PLAYER STATS
       ======================================================================== -->
  <div class="dashboard-pane" id="panePlayer" role="tabpanel">

    <!-- Top 3 Player Metric Boxes -->
    <div class="dashboard-stats-grid">
      <!-- 1. Games Downloaded -->
      <div class="dash-stat-card">
        <div class="dash-stat-label">Games Downloaded</div>
        <div class="dash-stat-value" style="color: #a855f7;" id="playerDownloadedPct"><?= View::e((string)($playerData['downloaded_pct'] ?? 0)) ?>%</div>
        <div class="dash-stat-subtext" id="playerDownloadedCount">
          <span style="color: #f1f5f9; font-weight: 700;"><?= number_format((int)($playerData['downloaded_count'] ?? 0)) ?></span> of <?= number_format((int)($kpi['total_games'] ?? 0)) ?> catalog games
        </div>
      </div>

      <!-- 2. Games Played -->
      <div class="dash-stat-card">
        <div class="dash-stat-label">Games Played</div>
        <div class="dash-stat-value" style="color: #38bdf8;" id="playerPlayedPct"><?= View::e((string)($playerData['played_pct'] ?? 0)) ?>%</div>
        <div class="dash-stat-subtext" id="playerPlayedCount">
          <span style="color: #f1f5f9; font-weight: 700;"><?= number_format((int)($playerData['played_count'] ?? 0)) ?></span> of <?= number_format((int)($playerData['downloaded_count'] ?? 0)) ?> downloaded games
        </div>
      </div>

      <!-- 3. Games Won -->
      <div class="dash-stat-card">
        <div class="dash-stat-label">Games Won</div>
        <div class="dash-stat-value" style="color: #10b981;" id="playerWonPct"><?= View::e((string)($playerData['won_pct'] ?? 0)) ?>%</div>
        <div class="dash-stat-subtext" id="playerWonCount">
          <span style="color: #f1f5f9; font-weight: 700;"><?= number_format((int)($playerData['won_count'] ?? 0)) ?></span> of <?= number_format((int)($playerData['played_count'] ?? 0)) ?> played games
        </div>
      </div>
    </div>

    <!-- Pie Chart & Summary of Life Row -->
    <div class="player-overview-row">
      <!-- Pie Chart: Catalog Interaction Breakdown -->
      <div class="dash-card">
        <div class="dash-card-header">
          <span class="dash-card-title">🥧 Collection Interaction Breakdown</span>
          <span style="font-size: 11px; color: #64748b;">100% of Catalog</span>
        </div>
        <div class="donut-card-body">
          <!-- SVG Donut Chart -->
          <div class="donut-svg-wrap">
            <svg viewBox="0 0 200 200" class="donut-svg" id="donutSvg">
              <!-- Background Ring -->
              <circle cx="100" cy="100" r="70" fill="transparent" stroke="#1e293b" stroke-width="26" />
              <!-- Segments injected via JavaScript for reactive recalculation -->
            </svg>
            <div class="donut-center-info">
              <div class="donut-center-val" id="donutCenterVal"><?= number_format((int)($kpi['total_games'] ?? 0)) ?></div>
              <div class="donut-center-lbl">TOTAL</div>
            </div>
          </div>

          <!-- Donut Legend -->
          <div class="donut-legend" id="donutLegend">
            <div class="donut-legend-item">
              <div style="display: flex; align-items: center; gap: 7px;">
                <span class="legend-color-dot" style="background: #10b981;"></span>
                <span>Won</span>
              </div>
              <span style="font-weight: 700; color: #10b981;" id="legWon"><?= number_format((int)($pieData['won'] ?? 0)) ?> (<?= $pieData['won_pct'] ?? 0 ?>%)</span>
            </div>

            <div class="donut-legend-item">
              <div style="display: flex; align-items: center; gap: 7px;">
                <span class="legend-color-dot" style="background: #38bdf8;"></span>
                <span>In Play</span>
              </div>
              <span style="font-weight: 700; color: #38bdf8;" id="legPlaying"><?= number_format((int)($pieData['playing'] ?? 0)) ?> (<?= $pieData['playing_pct'] ?? 0 ?>%)</span>
            </div>

            <div class="donut-legend-item">
              <div style="display: flex; align-items: center; gap: 7px;">
                <span class="legend-color-dot" style="background: #a855f7;"></span>
                <span>Downloaded</span>
              </div>
              <span style="font-weight: 700; color: #a855f7;" id="legDownloaded"><?= number_format((int)($pieData['downloaded'] ?? 0)) ?> (<?= $pieData['downloaded_pct'] ?? 0 ?>%)</span>
            </div>

            <div class="donut-legend-item">
              <div style="display: flex; align-items: center; gap: 7px;">
                <span class="legend-color-dot" style="background: #334155;"></span>
                <span>Not Interacted</span>
              </div>
              <span style="font-weight: 700; color: #64748b;" id="legUntouched"><?= number_format((int)($pieData['untouched'] ?? 0)) ?> (<?= $pieData['untouched_pct'] ?? 0 ?>%)</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Summary of Life -->
      <div class="dash-card">
        <div class="dash-card-header">
          <span class="dash-card-title">🌟 Summary of Life</span>
          <span style="font-size: 11px; color: #64748b;">Player Profile Highlights</span>
        </div>
        <div class="dash-card-body">
          <div class="summary-life-grid">
            <div class="summary-pill-box">
              <div class="summary-pill-label">Favorite Console</div>
              <div class="summary-pill-val" id="favConsoleVal" title="<?= View::e($summaryOfLife['favorite_console'] ?? 'None yet') ?>">
                🎮 <?= View::e($summaryOfLife['favorite_console'] ?? 'None yet') ?>
              </div>
            </div>

            <div class="summary-pill-box">
              <div class="summary-pill-label">Favorite Category</div>
              <div class="summary-pill-val" id="favCategoryVal" title="<?= View::e($summaryOfLife['favorite_category'] ?? 'None yet') ?>">
                📁 <?= View::e($summaryOfLife['favorite_category'] ?? 'None yet') ?>
              </div>
            </div>

            <div class="summary-pill-box">
              <div class="summary-pill-label">Favorite Subcategory</div>
              <div class="summary-pill-val" id="favSubcategoryVal" title="<?= View::e($summaryOfLife['favorite_subcategory'] ?? 'None yet') ?>">
                📂 <?= View::e($summaryOfLife['favorite_subcategory'] ?? 'None yet') ?>
              </div>
            </div>

            <div class="summary-pill-box">
              <div class="summary-pill-label">Play / Win Ratio</div>
              <div class="summary-pill-val" id="playWinRatioVal" style="color: #10b981;">
                🎯 <?= View::e($summaryOfLife['play_win_ratio'] ?? '0.0%') ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Games Currently in Play -->
    <div class="dash-card">
      <div class="dash-card-header">
        <span class="dash-card-title">🕹️ Games Currently in Play</span>
        <span class="badge" style="background: rgba(56, 189, 248, 0.18); color: #38bdf8;" id="currentlyPlayingBadge">
          <?= count($currentlyPlaying) ?> active
        </span>
      </div>
      <div class="dash-card-body" id="currentlyPlayingContainer">
        <?php if (!empty($currentlyPlaying)): ?>
          <div class="currently-playing-grid">
            <?php foreach ($currentlyPlaying as $play): ?>
              <div class="in-play-card">
                <div class="in-play-thumb">
                  <img src="<?= View::e($play['boxart_path'] ?: '/images/support/no_cover.jpg') ?>"
                       alt="<?= View::e($play['title']) ?>"
                       onerror="this.src='/images/support/no_cover.jpg'">
                </div>
                <div class="in-play-info">
                  <div class="in-play-title" title="<?= View::e($play['title']) ?>"><?= View::e($play['title']) ?></div>
                  <div class="in-play-meta">
                    <span class="dash-console-pill" style="margin-left: 0; background-color: <?= View::e($play['badge_bg_color'] ?? '#1e3a8a') ?>; color: <?= View::e($play['badge_font_color'] ?? '#93c5fd') ?>;">
                      <?= View::e($play['console_name'] ?? 'System') ?>
                    </span>
                    <?php if (!empty($play['category_name'])): ?>
                      <span style="margin-left: 4px;"><?= View::e($play['category_name']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="dash-empty-state">
            <span class="dash-empty-icon">☕</span>
            <p style="font-size: 12px; margin: 0;">
              No games currently in play. Mark any downloaded game as <strong>Played</strong> below to track your active session!
            </p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- My Collection (Downloaded Games Workbench) -->
    <div class="my-collection-section">
      <!-- Section Title & Filter Toolbar -->
      <div class="collection-toolbar">
        <div style="display: flex; align-items: center; gap: 8px;">
          <span style="font-size: 15px; font-weight: 800; color: #f8fafc; letter-spacing: 0.03em;">📥 My Collection</span>
          <span style="font-size: 11px; color: #64748b;" id="collectionFilteredCount">(<?= count($myCollection) ?> games)</span>
        </div>

        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
          <!-- Search Input -->
          <input type="text"
                 id="colSearchInput"
                 class="toolbar-search-input"
                 placeholder="Search downloaded games..."
                 oninput="filterMyCollection()">

          <!-- Console Filter -->
          <select id="colConsoleFilter" class="toolbar-select" onchange="filterMyCollection()">
            <option value="">All Consoles</option>
            <?php foreach (array_keys($collectionConsoles) as $cName): ?>
              <option value="<?= View::e($cName) ?>"><?= View::e($cName) ?></option>
            <?php endforeach; ?>
          </select>

          <!-- Status Filter -->
          <select id="colStatusFilter" class="toolbar-select" onchange="filterMyCollection()">
            <option value="">All Statuses</option>
            <option value="in_play">Currently in Play</option>
            <option value="won">Won / Completed</option>
            <option value="not_played">Not Played Yet</option>
          </select>
        </div>
      </div>

      <!-- Card-like Records Grid -->
      <div class="downloaded-cards-grid" id="myCollectionGrid">
        <?php if (!empty($myCollection)): ?>
          <?php foreach ($myCollection as $game): ?>
            <?php 
              $isPlayed = !empty($game['is_played']);
              $isWon    = !empty($game['is_won']);
              $statusTag = $isWon ? 'won' : ($isPlayed ? 'in_play' : 'not_played');
              $downloadDate = !empty($game['last_download_date']) ? date('M j, Y', strtotime($game['last_download_date'])) : 'Unknown';
            ?>
            <div class="my-game-card"
                 data-game-id="<?= (int)$game['game_id'] ?>"
                 data-title="<?= strtolower(View::e($game['title'])) ?>"
                 data-console="<?= View::e($game['console_name'] ?? '') ?>"
                 data-status="<?= $statusTag ?>">
              <div class="my-card-header">
                <div class="my-card-thumb">
                  <img src="<?= View::e($game['boxart_path'] ?: '/images/support/no_cover.jpg') ?>"
                       alt="<?= View::e($game['title']) ?>"
                       onerror="this.src='/images/support/no_cover.jpg'">
                </div>
                <div class="my-card-meta">
                  <div class="my-card-title" title="<?= View::e($game['title']) ?>"><?= View::e($game['title']) ?></div>
                  <div class="my-card-date">Downloaded: <?= $downloadDate ?></div>
                  <div class="my-card-tags">
                    <?php if (!empty($game['console_name'])): ?>
                      <span class="dash-console-pill" style="margin-left: 0; background-color: <?= View::e($game['badge_bg_color'] ?? '#1e3a8a') ?>; color: <?= View::e($game['badge_font_color'] ?? '#93c5fd') ?>;">
                        <?= View::e($game['console_name']) ?>
                      </span>
                    <?php endif; ?>
                    <?php if (!empty($game['category_name'])): ?>
                      <span class="my-tag"><?= View::e($game['category_name']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($game['subcategory_name'])): ?>
                      <span class="my-tag"><?= View::e($game['subcategory_name']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <!-- Interactive Checkboxes -->
              <div class="my-card-actions">
                <label class="game-status-label" title="Mark game as played">
                  <input type="checkbox"
                         class="chk-played"
                         onchange="onGameStatusToggle(<?= (int)$game['game_id'] ?>, this, 'played')"
                         <?= $isPlayed ? 'checked' : '' ?>>
                  <span>Played</span>
                </label>

                <label class="game-status-label <?= !$isPlayed ? 'disabled' : '' ?>"
                       id="wonLabel_<?= (int)$game['game_id'] ?>"
                       title="<?= !$isPlayed ? 'Must be marked as played first' : 'Mark game as won' ?>">
                  <input type="checkbox"
                         class="chk-won"
                         id="wonChk_<?= (int)$game['game_id'] ?>"
                         onchange="onGameStatusToggle(<?= (int)$game['game_id'] ?>, this, 'won')"
                         <?= $isWon ? 'checked' : '' ?>
                         <?= !$isPlayed ? 'disabled' : '' ?>>
                  <span>Won 🏆</span>
                </label>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="dash-empty-state" style="grid-column: 1 / -1;">
            <span class="dash-empty-icon">📥</span>
            <p style="font-size: 13px; margin: 0;">No games downloaded yet.</p>
            <p style="font-size: 11px; margin: 4px 0 0; color: #64748b;">
              Use the <a href="/collection" style="color: #38bdf8;">Player Hub</a> to discover and download ROMs to build your collection!
            </p>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

</div>

<script>
/**
 * Master Tab Switcher with URL Hash Persistence
 */
function switchDashboardTab(tabName) {
  const tabGeneralBtn = document.getElementById('tabGeneralBtn');
  const tabPlayerBtn  = document.getElementById('tabPlayerBtn');
  const paneGeneral   = document.getElementById('paneGeneral');
  const panePlayer    = document.getElementById('panePlayer');

  if (tabName === 'player') {
    tabPlayerBtn.classList.add('active');
    tabGeneralBtn.classList.remove('active');
    tabPlayerBtn.setAttribute('aria-selected', 'true');
    tabGeneralBtn.setAttribute('aria-selected', 'false');

    panePlayer.classList.add('active');
    paneGeneral.classList.remove('active');
    window.location.hash = '#player';
    renderDonutChart(currentPieData);
  } else {
    tabGeneralBtn.classList.add('active');
    tabPlayerBtn.classList.remove('active');
    tabGeneralBtn.setAttribute('aria-selected', 'true');
    tabPlayerBtn.setAttribute('aria-selected', 'false');

    paneGeneral.classList.add('active');
    panePlayer.classList.remove('active');
    window.location.hash = '#general';
  }
}

// Initial state from URL Hash
window.addEventListener('DOMContentLoaded', () => {
  if (window.location.hash === '#player') {
    switchDashboardTab('player');
  }
  renderDonutChart(currentPieData);
});

/**
 * Global reactive pie data cache
 */
let currentPieData = <?= json_encode($pieData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

/**
 * Render reactive SVG Donut Chart
 */
function renderDonutChart(pie) {
  const svg = document.getElementById('donutSvg');
  if (!svg || !pie) return;

  const total = (pie.won || 0) + (pie.playing || 0) + (pie.downloaded || 0) + (pie.untouched || 0);
  const radius = 70;
  const circumference = 2 * Math.PI * radius; // ~439.82

  const slices = [
    { key: 'won', val: pie.won || 0, color: '#10b981' },
    { key: 'playing', val: pie.playing || 0, color: '#38bdf8' },
    { key: 'downloaded', val: pie.downloaded || 0, color: '#a855f7' },
    { key: 'untouched', val: pie.untouched || 0, color: '#334155' }
  ];

  let offset = 0;
  let circlesHtml = `<circle cx="100" cy="100" r="${radius}" fill="transparent" stroke="#1e293b" stroke-width="26" />`;

  if (total > 0) {
    slices.forEach(slice => {
      if (slice.val <= 0) return;
      const fraction = slice.val / total;
      const dash = (fraction * circumference).toFixed(2);
      const gap = (circumference - dash).toFixed(2);
      const off = (-offset).toFixed(2);

      circlesHtml += `<circle cx="100" cy="100" r="${radius}" fill="transparent" stroke="${slice.color}" stroke-width="26" stroke-dasharray="${dash} ${gap}" stroke-dashoffset="${off}" />`;
      offset += parseFloat(dash);
    });
  }

  svg.innerHTML = circlesHtml;
}

/**
 * Filter My Collection Grid (Search text, Console, Status)
 */
function filterMyCollection() {
  const search = (document.getElementById('colSearchInput')?.value || '').toLowerCase().trim();
  const consoleFilter = (document.getElementById('colConsoleFilter')?.value || '').trim();
  const statusFilter  = (document.getElementById('colStatusFilter')?.value || '').trim();

  const cards = document.querySelectorAll('#myCollectionGrid .my-game-card');
  let visibleCount = 0;

  cards.forEach(card => {
    const title   = card.getAttribute('data-title') || '';
    const console = card.getAttribute('data-console') || '';
    const status  = card.getAttribute('data-status') || '';

    const matchSearch  = !search || title.includes(search);
    const matchConsole = !consoleFilter || console === consoleFilter;
    const matchStatus  = !statusFilter || status === statusFilter;

    if (matchSearch && matchConsole && matchStatus) {
      card.style.display = 'flex';
      visibleCount++;
    } else {
      card.style.display = 'none';
    }
  });

  const countBadge = document.getElementById('collectionFilteredCount');
  if (countBadge) {
    countBadge.textContent = `(${visibleCount} of ${cards.length} games)`;
  }
}

/**
 * Checkbox Toggle Handler for Played and Won Status
 */
async function onGameStatusToggle(gameId, chkElem, actionType) {
  const card = document.querySelector(`.my-game-card[data-game-id="${gameId}"]`);
  if (!card) return;

  const playedChk = card.querySelector('.chk-played');
  const wonChk    = card.querySelector('.chk-won');
  const wonLabel  = document.getElementById(`wonLabel_${gameId}`);

  let isPlayed = playedChk ? playedChk.checked : false;
  let isWon    = wonChk ? wonChk.checked : false;

  // Business Rule: If played is unchecked, won MUST also be unchecked and disabled
  if (actionType === 'played' && !isPlayed) {
    isWon = false;
    if (wonChk) {
      wonChk.checked = false;
      wonChk.disabled = true;
    }
    if (wonLabel) {
      wonLabel.classList.add('disabled');
      wonLabel.title = 'Must be marked as played first';
    }
  } else if (actionType === 'played' && isPlayed) {
    if (wonChk) wonChk.disabled = false;
    if (wonLabel) {
      wonLabel.classList.remove('disabled');
      wonLabel.title = 'Mark game as won';
    }
  }

  // Business Rule: Cannot be won if not played
  if (!isPlayed && isWon) {
    isWon = false;
    if (wonChk) wonChk.checked = false;
  }

  // Update card dataset for instant filter compatibility
  const newStatus = isWon ? 'won' : (isPlayed ? 'in_play' : 'not_played');
  card.setAttribute('data-status', newStatus);

  try {
    const res = await fetch('/api/dashboard/update-game-status', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        game_id: gameId,
        is_played: isPlayed,
        is_won: isWon
      })
    });

    const resData = await res.json();
    if (!resData.success) {
      throw new Error(resData.error || (resData.data && resData.data.error) || 'Failed to update game status.');
    }

    const payload = resData.data || resData;

    // Refresh Player Stats UI with newly calculated figures
    applyPlayerUpdates(payload.player_data, payload.played_consoles);
    showToast('Game status updated successfully.', 'success');
  } catch (err) {
    console.error('Error updating game status:', err);
    showToast(err.message || 'Error updating status', 'error');
  }
}

/**
 * Seamlessly update UI elements with fresh player metrics
 */
function applyPlayerUpdates(playerData, playedConsoles) {
  if (!playerData) return;

  // 1. Top Player Stat Boxes
  const pDownPct = document.getElementById('playerDownloadedPct');
  if (pDownPct) pDownPct.textContent = `${playerData.downloaded_pct}%`;

  const pPlayPct = document.getElementById('playerPlayedPct');
  if (pPlayPct) pPlayPct.textContent = `${playerData.played_pct}%`;

  const pWonPct = document.getElementById('playerWonPct');
  if (pWonPct) pWonPct.textContent = `${playerData.won_pct}%`;

  const pPlayCnt = document.getElementById('playerPlayedCount');
  if (pPlayCnt) {
    pPlayCnt.innerHTML = `<span style="color: #f1f5f9; font-weight: 700;">${playerData.played_count}</span> of ${playerData.downloaded_count} downloaded games`;
  }

  const pWonCnt = document.getElementById('playerWonCount');
  if (pWonCnt) {
    pWonCnt.innerHTML = `<span style="color: #f1f5f9; font-weight: 700;">${playerData.won_count}</span> of ${playerData.played_count} played games`;
  }

  // 2. Summary of Life
  if (playerData.summary) {
    const s = playerData.summary;
    const fc = document.getElementById('favConsoleVal');
    if (fc) fc.textContent = `🎮 ${s.favorite_console || 'None yet'}`;
    const fcat = document.getElementById('favCategoryVal');
    if (fcat) fcat.textContent = `📁 ${s.favorite_category || 'None yet'}`;
    const fsub = document.getElementById('favSubcategoryVal');
    if (fsub) fsub.textContent = `📂 ${s.favorite_subcategory || 'None yet'}`;
    const pwr = document.getElementById('playWinRatioVal');
    if (pwr) pwr.textContent = `🎯 ${s.play_win_ratio || '0.0%'}`;
  }

  // 3. Donut Chart & Legend
  if (playerData.pie) {
    currentPieData = playerData.pie;
    renderDonutChart(currentPieData);

    const legWon = document.getElementById('legWon');
    if (legWon) legWon.textContent = `${playerData.pie.won} (${playerData.pie.won_pct}%)`;

    const legPlaying = document.getElementById('legPlaying');
    if (legPlaying) legPlaying.textContent = `${playerData.pie.playing} (${playerData.pie.playing_pct}%)`;

    const legDownloaded = document.getElementById('legDownloaded');
    if (legDownloaded) legDownloaded.textContent = `${playerData.pie.downloaded} (${playerData.pie.downloaded_pct}%)`;

    const legUntouched = document.getElementById('legUntouched');
    if (legUntouched) legUntouched.textContent = `${playerData.pie.untouched} (${playerData.pie.untouched_pct}%)`;
  }

  // 4. Currently in Play Grid
  const playContainer = document.getElementById('currentlyPlayingContainer');
  const playBadge = document.getElementById('currentlyPlayingBadge');
  const inPlayList = playerData.currently_playing || [];

  if (playBadge) {
    playBadge.textContent = `${inPlayList.length} active`;
  }

  if (playContainer) {
    if (inPlayList.length > 0) {
      let html = '<div class="currently-playing-grid">';
      inPlayList.forEach(item => {
        html += `
          <div class="in-play-card">
            <div class="in-play-thumb">
              <img src="${item.boxart_path || '/images/support/no_cover.jpg'}"
                   alt="${escapeHtml(item.title)}"
                   onerror="this.src='/images/support/no_cover.jpg'">
            </div>
            <div class="in-play-info">
              <div class="in-play-title" title="${escapeHtml(item.title)}">${escapeHtml(item.title)}</div>
              <div class="in-play-meta">
                <span class="dash-console-pill" style="margin-left: 0; background-color: ${item.badge_bg_color || '#1e3a8a'}; color: ${item.badge_font_color || '#93c5fd'};">
                  ${escapeHtml(item.console_name || 'System')}
                </span>
                ${item.category_name ? `<span style="margin-left: 4px;">${escapeHtml(item.category_name)}</span>` : ''}
              </div>
            </div>
          </div>
        `;
      });
      html += '</div>';
      playContainer.innerHTML = html;
    } else {
      playContainer.innerHTML = `
        <div class="dash-empty-state">
          <span class="dash-empty-icon">☕</span>
          <p style="font-size: 12px; margin: 0;">
            No games currently in play. Mark any downloaded game as <strong>Played</strong> below to track your active session!
          </p>
        </div>
      `;
    }
  }

  // 5. Update Podium if provided
  if (playedConsoles && Array.isArray(playedConsoles)) {
    const podiumBody = document.getElementById('podiumCardBody');
    if (podiumBody && playedConsoles.length > 0) {
      let pCol2 = playedConsoles[1] ? `
        <div class="podium-col">
          <div class="podium-name" title="${escapeHtml(playedConsoles[1].console_name)}">${escapeHtml(playedConsoles[1].console_name)}</div>
          <div class="podium-pedestal pedestal-2">
            <span class="podium-medal">🥈</span>
            <span class="podium-count">${playedConsoles[1].played_count} plays</span>
          </div>
        </div>
      ` : '';

      let pCol1 = playedConsoles[0] ? `
        <div class="podium-col">
          <div class="podium-name" title="${escapeHtml(playedConsoles[0].console_name)}">${escapeHtml(playedConsoles[0].console_name)}</div>
          <div class="podium-pedestal pedestal-1">
            <span class="podium-medal">🥇</span>
            <span class="podium-count">${playedConsoles[0].played_count} plays</span>
          </div>
        </div>
      ` : '';

      let pCol3 = playedConsoles[2] ? `
        <div class="podium-col">
          <div class="podium-name" title="${escapeHtml(playedConsoles[2].console_name)}">${escapeHtml(playedConsoles[2].console_name)}</div>
          <div class="podium-pedestal pedestal-3">
            <span class="podium-medal">🥉</span>
            <span class="podium-count">${playedConsoles[2].played_count} plays</span>
          </div>
        </div>
      ` : '';

      podiumBody.innerHTML = `<div class="podium-container">${pCol2}${pCol1}${pCol3}</div>`;
    }
  }
}

/**
 * Safe HTML Escape Helper
 */
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
