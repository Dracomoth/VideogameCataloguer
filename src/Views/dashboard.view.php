<?php
/**
 * src/Views/dashboard/dashboard.view.php
 * Command Center Dashboard View (Verification Template).
 *
 * Variables provided by DashboardController:
 * @var array<string, mixed> $kpi
 * @var array<string, int> $counts
 * @var array<int, array<string, mixed>> $topConsoles
 * @var array<int, array<string, mixed>> $nowPlaying
 */

declare(strict_types=1);

use Vault\Services\View;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

$healthPct = (float)($kpi['health_pct'] ?? 0);
$healthColor = $healthPct >= 90 ? 'var(--success)' : ($healthPct >= 70 ? 'var(--warning)' : 'var(--danger)');
?>
<div class="dashboard-container">
  <!-- SECTION 1: Top 4 KPI Metric Tiles -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; width: 100%;">
    <!-- 1. Total Games & Owned -->
    <div class="editor-card" style="padding: 18px; position: static;">
      <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Total Games</div>
      <div style="font-size: 32px; font-weight: 800; color: #fff; margin-top: 4px; line-height: 1.1;">
        <?= number_format((int)$kpi['total_games']) ?>
      </div>
      <div style="font-size: 12px; color: var(--text-dim); margin-top: 6px;">
        <span style="color: var(--border-focus); font-weight: 700;"><?= number_format((int)$kpi['owned_games']) ?></span> in physical library
      </div>
    </div>

    <!-- 2. Completion Rate -->
    <div class="editor-card" style="padding: 18px; position: static;">
      <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Completion Rate</div>
      <div style="font-size: 32px; font-weight: 800; color: var(--success); margin-top: 4px; line-height: 1.1;">
        <?= View::e((string)$kpi['completion_pct']) ?>%
      </div>
      <div style="font-size: 12px; color: var(--text-dim); margin-top: 6px;">
        <span style="color: #fff; font-weight: 600;"><?= number_format((int)$kpi['won_games']) ?></span> beaten &bull;
        <span style="color: var(--warning); font-weight: 600;"><?= number_format((int)$kpi['backlog_games']) ?></span> in backlog
      </div>
    </div>

    <!-- 3. Active Backlog Queue -->
    <div class="editor-card" style="padding: 18px; position: static;">
      <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Active Backlog</div>
      <div style="font-size: 32px; font-weight: 800; color: var(--warning); margin-top: 4px; line-height: 1.1;">
        <?= number_format((int)$kpi['backlog_games']) ?>
      </div>
      <div style="font-size: 12px; color: var(--text-dim); margin-top: 6px;">
        Titles waiting to be played (<span style="color: var(--border-focus); font-weight: 600;"><?= number_format((int)$kpi['playing_games']) ?></span> currently active)
      </div>
    </div>

    <!-- 4. Catalog Health -->
    <div class="editor-card" style="padding: 18px; position: static;">
      <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Catalog Health</div>
      <div style="font-size: 32px; font-weight: 800; color: <?= $healthColor ?>; margin-top: 4px; line-height: 1.1;">
        <?= View::e((string)$kpi['health_pct']) ?>%
      </div>
      <div style="font-size: 12px; color: var(--text-dim); margin-top: 6px; line-height: 1.4;">
        <span style="color: var(--danger); font-weight: 600;"><?= number_format((int)$kpi['missing_covers']) ?></span> games with no cover<br>
        <span style="color: var(--danger); font-weight: 600;"><?= number_format((int)$kpi['missing_screens']) ?></span> games with no screenshot
      </div>
    </div>
  </div>

  <!-- SECTION 2: Launchpad Banner & Now Playing -->
  <div class="dashboard-hero-row">
    <!-- Player Mode Launchpad Hero -->
    <a href="/collection" class="nav-card" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-color: var(--border-focus); text-decoration: none; padding: 24px;">
      <div class="card-top">
        <span style="font-size: 36px;">🎮</span>
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
        <span class="badge-count"><?= count($nowPlaying) ?> Active</span>
      </div>
      <div style="padding: 12px; display: flex; flex-direction: column; gap: 8px; overflow-y: auto; max-height: 220px;">
        <?php if (!empty($nowPlaying)): ?>
          <?php foreach ($nowPlaying as $game): ?>
            <a href="/games?id=<?= (int)$game['id'] ?>" style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-sm); text-decoration: none; color: inherit;">
              <div>
                <div style="font-weight: 600; font-size: 13px; color: #fff;"><?= View::e($game['game'] ?? 'Untitled') ?></div>
                <div style="font-size: 11px; color: var(--text-muted);"><?= View::e($game['console'] ?? 'Unknown') ?> &bull; <?= View::e($game['year'] ?? 'N/A') ?></div>
              </div>
              <span class="status-badge" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8;">Resume &rarr;</span>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="text-align: center; color: var(--text-dim); font-size: 13px; padding: 20px;">No games currently in-progress.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- SECTION 3: Consoles Breakdown & Management Portals -->
  <div class="dashboard-main-row">
    <!-- Top Hardware Breakdown -->
    <div class="grid-card" style="border: 1px solid var(--border); border-radius: var(--radius-md);">
      <div class="grid-header">
        <span style="font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Top Systems (Owned)</span>
        <a href="/consoles" style="color: var(--border-focus); font-size: 11px; text-decoration: none;">View All &rarr;</a>
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
          <tbody>
            <?php if (!empty($topConsoles)): ?>
              <?php foreach ($topConsoles as $c): ?>
                <tr onclick="window.location.href='/collection?console_id=<?= (int)$c['id'] ?>'">
                  <td style="font-weight: 600;">
                    <?= View::e($c['console'] ?? '') ?>
                    <?php if (!empty($c['is_handheld'])): ?>
                      <span class="tag handheld" style="font-size: 9px; padding: 1px 4px; margin-left: 4px;">Portable</span>
                    <?php endif; ?>
                  </td>
                  <td style="text-align: right; font-weight: 700; color: var(--border-focus);"><?= number_format((int)$c['owned_titles']) ?></td>
                  <td style="text-align: right; color: var(--text-dim);"><?= number_format((int)$c['total_titles']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 20px;">No system data registered.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Management Portals Directory -->
    <div class="dashboard-portals-grid">
      <a href="/games" class="nav-card" style="padding: 16px; border-radius: var(--radius-md);">
        <div class="card-top" style="margin-bottom: 8px;">
          <span style="font-size: 22px;">🎮</span>
          <span class="badge-count"><?= number_format((int)$kpi['total_games']) ?></span>
        </div>
        <div style="font-size: 15px; font-weight: 700; color: #fff;">Games</div>
        <div class="card-desc" style="font-size: 11px;">Data entry workbench & media uploader.</div>
      </a>

      <a href="/consoles" class="nav-card" style="padding: 16px; border-radius: var(--radius-md);">
        <div class="card-top" style="margin-bottom: 8px;">
          <span style="font-size: 22px;">🕹</span>
          <span class="badge-count"><?= number_format((int)$counts['consoles']) ?></span>
        </div>
        <div style="font-size: 15px; font-weight: 700; color: #fff;">Consoles</div>
        <div class="card-desc" style="font-size: 11px;">Hardware specs, logos, photos & emulators.</div>
      </a>

      <a href="/publishers" class="nav-card" style="padding: 16px; border-radius: var(--radius-md);">
        <div class="card-top" style="margin-bottom: 8px;">
          <span style="font-size: 22px;">🏢</span>
          <span class="badge-count"><?= number_format((int)$counts['publishers']) ?></span>
        </div>
        <div style="font-size: 15px; font-weight: 700; color: #fff;">Publishers</div>
        <div class="card-desc" style="font-size: 11px;">Game studios and console manufacturers.</div>
      </a>

      <a href="/categories" class="nav-card" style="padding: 16px; border-radius: var(--radius-md);">
        <div class="card-top" style="margin-bottom: 8px;">
          <span style="font-size: 22px;">📁</span>
          <span class="badge-count"><?= number_format((int)$counts['categories']) ?></span>
        </div>
        <div style="font-size: 15px; font-weight: 700; color: #fff;">Categories</div>
        <div class="card-desc" style="font-size: 11px;">Primary genre taxonomies.</div>
      </a>

      <a href="/subcategories" class="nav-card" style="padding: 16px; border-radius: var(--radius-md);">
        <div class="card-top" style="margin-bottom: 8px;">
          <span style="font-size: 22px;">📂</span>
          <span class="badge-count"><?= number_format((int)$counts['subcategories']) ?></span>
        </div>
        <div style="font-size: 15px; font-weight: 700; color: #fff;">Subcategories</div>
        <div class="card-desc" style="font-size: 11px;">Detailed sub-genre classification.</div>
      </a>

      <a href="/languages" class="nav-card" style="padding: 16px; border-radius: var(--radius-md);">
        <div class="card-top" style="margin-bottom: 8px;">
          <span style="font-size: 22px;">🌐</span>
          <span class="badge-count"><?= number_format((int)$counts['languages']) ?></span>
        </div>
        <div style="font-size: 15px; font-weight: 700; color: #fff;">Languages</div>
        <div class="card-desc" style="font-size: 11px;">Localization and language flags.</div>
      </a>
    </div>
  </div>
</div>