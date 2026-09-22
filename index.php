<?php
/**
 * index.php - Main Command Center & Portfolio Dashboard
 */
$pageTitle = 'Command Center';
$activeNav  = 'dashboard';
require_once __DIR__ . '/layout_header.php';
?>

<div class="dashboard-container">

  <!-- SECTION 1: Top 4 KPI Metric Tiles -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; width: 100%;">
    
    <!-- 1. Total Games & Owned -->
    <div class="editor-card" style="padding: 18px; position: static;">
      <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Total Games</div>
      <div id="statTotalGames" style="font-size: 32px; font-weight: 800; color: #fff; margin-top: 4px; line-height: 1.1;">--</div>
      <div style="font-size: 12px; color: var(--text-dim); margin-top: 6px;">
        <span id="statOwnedGames" style="color: var(--border-focus); font-weight: 700;">--</span> in physical library
      </div>
    </div>

    <!-- 2. Completion Rate -->
    <div class="editor-card" style="padding: 18px; position: static;">
      <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Completion Rate</div>
      <div id="statCompletionPct" style="font-size: 32px; font-weight: 800; color: var(--success); margin-top: 4px; line-height: 1.1;">--%</div>
      <div style="font-size: 12px; color: var(--text-dim); margin-top: 6px;">
        <span id="statWonGames" style="color: #fff; font-weight: 600;">--</span> beaten &bull; 
        <span id="statBacklogGames" style="color: var(--warning); font-weight: 600;">--</span> in backlog
      </div>
    </div>

    <!-- 3. Active Backlog Queue -->
    <div class="editor-card" style="padding: 18px; position: static;">
      <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Active Backlog</div>
      <div id="statActiveBacklog" style="font-size: 32px; font-weight: 800; color: var(--warning); margin-top: 4px; line-height: 1.1;">--</div>
      <div style="font-size: 12px; color: var(--text-dim); margin-top: 6px;">
        Titles waiting to be played (<span id="statPlayingCount" style="color: var(--border-focus); font-weight: 600;">--</span> currently active)
      </div>
    </div>

    <!-- 4. Catalog Health -->
    <div class="editor-card" style="padding: 18px; position: static;">
      <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Catalog Health</div>
      <div id="statHealthPct" style="font-size: 32px; font-weight: 800; color: var(--border-focus); margin-top: 4px; line-height: 1.1;">--%</div>
      <div style="font-size: 12px; color: var(--text-dim); margin-top: 6px; line-height: 1.4;">
        <span id="statMissingCovers" style="color: #f87171; font-weight: 600;">--</span> games with no cover<br>
        <span id="statMissingScreens" style="color: #f87171; font-weight: 600;">--</span> games with no screenshot
      </div>
    </div>

  </div>

  <!-- SECTION 2: Launchpad Banner & Now Playing (Responsive Class Applied) -->
  <div class="dashboard-hero-row">
    
    <!-- Player Mode Launchpad Hero -->
    <a href="collection.php" class="nav-card" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-color: var(--border-focus); text-decoration: none; padding: 24px;">
      <div class="card-top">
        <span style="font-size: 36px;">&#127918;</span>
        <span class="badge" style="background: rgba(56, 189, 248, 0.18); color: #38bdf8; font-size: 12px;">Visual Deck</span>
      </div>
      <div>
        <h2 style="font-size: 20px; font-weight: 700; color: #fff; margin-bottom: 6px;">Launch Collection & Backlog Hub</h2>
        <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5;">
          Touch-friendly card deck optimized for portables, handhelds, and mobile screens. Filter by platforms, review cover art, and update play status.
        </p>
      </div>
      <div class="card-action" style="margin-top: 20px; font-size: 14px; font-weight: 700;">
        Open Player Deck &rarr;
      </div>
    </a>

    <!-- Currently Playing Mini-Queue -->
    <div class="grid-card" style="border: 1px solid var(--border); border-radius: var(--radius-md);">
      <div class="grid-header">
        <span style="font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Currently Playing</span>
        <span id="playingBadge" class="badge-count">0 Titles</span>
      </div>
      <div id="nowPlayingContainer" style="padding: 12px; display: flex; flex-direction: column; gap: 8px; overflow-y: auto; max-height: 220px;">
        <div style="text-align: center; color: var(--text-dim); font-size: 13px; padding: 20px;">No games currently in-progress.</div>
      </div>
    </div>

  </div>

  <!-- SECTION 3: Consoles Breakdown & Administrative Modules (Responsive Class Applied) -->
  <div class="dashboard-main-row">

    <!-- Top Hardware Breakdown -->
    <div class="grid-card" style="border: 1px solid var(--border); border-radius: var(--radius-md);">
      <div class="grid-header">
        <span style="font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Top Systems (Owned)</span>
        <a href="consoles.php" style="color: var(--border-focus); font-size: 11px; text-decoration: none;">View All &rarr;</a>
      </div>
      <div class="table-container" style="max-height: 280px;">
        <table>
          <thead>
            <tr>
              <th>System</th>
              <th style="width: 80px; text-align: right;">Owned</th>
              <th style="width: 80px; text-align: right;">Library</th>
            </tr>
          </thead>
          <tbody id="topConsolesBody">
            <tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 20px;">Loading hardware...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Management Portals Directory -->
    <div class="dashboard-portals-grid">
      
      <a href="games.php" class="nav-card" style="padding: 16px; border-radius: var(--radius-md);">
        <div class="card-top" style="margin-bottom: 8px;">
          <span style="font-size: 22px;">&#127918;</span>
          <span id="countGamesBadge" class="badge-count">--</span>
        </div>
        <div style="font-size: 15px; font-weight: 700; color: #fff;">Games</div>
        <div class="card-desc" style="font-size: 11px;">Data entry workbench & media uploader.</div>
      </a>

      <a href="consoles.php" class="nav-card" style="padding: 16px; border-radius: var(--radius-md);">
        <div class="card-top" style="margin-bottom: 8px;">
          <span style="font-size: 22px;">&#128377;</span>
          <span id="countConsolesBadge" class="badge-count">--</span>
        </div>
        <div style="font-size: 15px; font-weight: 700; color: #fff;">Consoles</div>
        <div class="card-desc" style="font-size: 11px;">Hardware specs, logos, photos & emulators.</div>
      </a>

      <a href="publishers.php" class="nav-card" style="padding: 16px; border-radius: var(--radius-md);">
        <div class="card-top" style="margin-bottom: 8px;">
          <span style="font-size: 22px;">&#127970;</span>
          <span id="countPubsBadge" class="badge-count">--</span>
        </div>
        <div style="font-size: 15px; font-weight: 700; color: #fff;">Publishers</div>
        <div class="card-desc" style="font-size: 11px;">Game studios and console manufacturers.</div>
      </a>

      <a href="categories.php" class="nav-card" style="padding: 16px; border-radius: var(--radius-md);">
        <div class="card-top" style="margin-bottom: 8px;">
          <span style="font-size: 22px;">&#128193;</span>
          <span id="countCatsBadge" class="badge-count">--</span>
        </div>
        <div style="font-size: 15px; font-weight: 700; color: #fff;">Categories</div>
        <div class="card-desc" style="font-size: 11px;">Primary genre taxonomies.</div>
      </a>

      <a href="subcategories.php" class="nav-card" style="padding: 16px; border-radius: var(--radius-md);">
        <div class="card-top" style="margin-bottom: 8px;">
          <span style="font-size: 22px;">&#128194;</span>
          <span id="countSubcatsBadge" class="badge-count">--</span>
        </div>
        <div style="font-size: 15px; font-weight: 700; color: #fff;">Subcategories</div>
        <div class="card-desc" style="font-size: 11px;">Detailed sub-genre classification.</div>
      </a>

      <a href="languages.php" class="nav-card" style="padding: 16px; border-radius: var(--radius-md);">
        <div class="card-top" style="margin-bottom: 8px;">
          <span style="font-size: 22px;">&#127760;</span>
          <span id="countLangsBadge" class="badge-count">--</span>
        </div>
        <div style="font-size: 15px; font-weight: 700; color: #fff;">Languages</div>
        <div class="card-desc" style="font-size: 11px;">Localization and language flags.</div>
      </a>

    </div>

  </div>

</div>

<script>
  document.addEventListener('DOMContentLoaded', fetchDashboardData);

  async function fetchDashboardData() {
    try {
      const res = await fetch('api/dashboard.php', {
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error);

      const d = json.data;

      // 1. Render Top KPI Metrics
      document.getElementById('statTotalGames').textContent    = d.kpi.total_games.toLocaleString();
      document.getElementById('statOwnedGames').textContent    = d.kpi.owned_games.toLocaleString();
      document.getElementById('statCompletionPct').textContent = `${d.kpi.completion_pct}%`;
      document.getElementById('statWonGames').textContent      = d.kpi.won_games.toLocaleString();
      document.getElementById('statBacklogGames').textContent  = d.kpi.backlog_games.toLocaleString();
      
      // Active Backlog
      document.getElementById('statActiveBacklog').textContent = d.kpi.backlog_games.toLocaleString();
      document.getElementById('statPlayingCount').textContent  = d.kpi.playing_games.toLocaleString();

      // Catalog Health % and Clean Sub-Labels
      const healthElem = document.getElementById('statHealthPct');
      healthElem.textContent = `${d.kpi.health_pct}%`;
      if (d.kpi.health_pct >= 90) {
        healthElem.style.color = 'var(--success)';
      } else if (d.kpi.health_pct >= 70) {
        healthElem.style.color = 'var(--warning)';
      } else {
        healthElem.style.color = '#f87171';
      }

      document.getElementById('statMissingCovers').textContent  = `${d.kpi.missing_covers.toLocaleString()}`;
      document.getElementById('statMissingScreens').textContent = `${d.kpi.missing_screens.toLocaleString()}`;

      // 2. Render Secondary Module Badges
      document.getElementById('countGamesBadge').textContent    = `${d.kpi.total_games}`;
      document.getElementById('countConsolesBadge').textContent = `${d.counts.consoles}`;
      document.getElementById('countPubsBadge').textContent     = `${d.counts.publishers}`;
      document.getElementById('countCatsBadge').textContent     = `${d.counts.categories}`;
      document.getElementById('countSubcatsBadge').textContent  = `${d.counts.subcategories}`;
      document.getElementById('countLangsBadge').textContent    = `${d.counts.languages}`;

      // 3. Render Now Playing Cards
      const nowPlayingCont = document.getElementById('nowPlayingContainer');
      document.getElementById('playingBadge').textContent = `${d.kpi.playing_games} Active`;

      if (d.now_playing.length > 0) {
        nowPlayingCont.innerHTML = d.now_playing.map(g => `
          <a href="games.php?id=${g.ID}" style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-sm); text-decoration: none; color: inherit;">
            <div>
              <div style="font-weight: 600; font-size: 13px; color: #fff;">${escapeHtml(g.Game)}</div>
              <div style="font-size: 11px; color: var(--text-muted);">${escapeHtml(g.Console)} &bull; ${g.Year || 'N/A'}</div>
            </div>
            <span class="status-badge" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8;">Resume &rarr;</span>
          </a>
        `).join('');
      } else {
        nowPlayingCont.innerHTML = `<div style="text-align: center; color: var(--text-dim); font-size: 13px; padding: 20px;">No games currently in-progress.</div>`;
      }

      // 4. Render Top Consoles Table
      const topConsolesBody = document.getElementById('topConsolesBody');
      topConsolesBody.innerHTML = d.top_consoles.map(c => `
        <tr onclick="window.location.href='collection.php?console_id=${c.ID}'">
          <td style="font-weight: 600;">
            ${escapeHtml(c.Console)}
            ${c.IsHandheld ? '<span class="tag handheld" style="font-size: 9px; padding: 1px 4px; margin-left: 4px;">Portable</span>' : ''}
          </td>
          <td style="text-align: right; font-weight: 700; color: var(--border-focus);">${c.owned_titles}</td>
          <td style="text-align: right; color: var(--text-dim);">${c.total_titles}</td>
        </tr>
      `).join('');

    } catch (err) {
      console.error(err);
    }
  }

  function escapeHtml(str) {
    return String(str).replace(/[&<>'"]/g, tag => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    }[tag] || tag));
  }
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>