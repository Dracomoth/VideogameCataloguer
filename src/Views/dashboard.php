<?php
/**
 * src/Views/dashboard.php
 * Permanent Command Center Dashboard Body View.
 * Injected into the <main> slot of layout.php.
 *
 * Variables provided by DashboardController:
 * @var array<string, mixed> $kpi
 * @var array<string, int> $counts
 * @var array<int, array<string, mixed>> $topConsoles
 */

declare(strict_types=1);

use Vault\Auth\Auth;
use Vault\Services\View;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

$canCollection    = Auth::can('collection', 'read');
$canGames         = Auth::can('games', 'read');
$canConsoles      = Auth::can('consoles', 'read');
$canConsoleTypes  = Auth::can('console_types', 'read');
$canPublishers    = Auth::can('publishers', 'read');
$canCategories    = Auth::can('categories', 'read');
$canSubcategories = Auth::can('subcategories', 'read');
$canLanguages     = Auth::can('languages', 'read');

$hasAnyPortal = $canGames || $canConsoles || $canConsoleTypes || $canPublishers || $canCategories || $canSubcategories || $canLanguages;

$healthPct   = (float)($kpi['health_pct'] ?? 0);
$healthColor = $healthPct >= 90 ? 'var(--success)' : ($healthPct >= 70 ? 'var(--warning)' : 'var(--danger)');
?>
<div class="dashboard-container">
  <!-- SECTION 1: Top 3 KPI Metric Tiles -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; width: 100%;">
    <!-- 1. Total Games & Owned -->
    <div class="editor-card" style="padding: 18px; position: static;">
      <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Total Games</div>
      <div style="font-size: 32px; font-weight: 800; color: #fff; margin-top: 4px; line-height: 1.1;">
        <?= number_format((int)($kpi['total_games'] ?? 0)) ?>
      </div>
      <div style="font-size: 12px; color: var(--text-dim); margin-top: 6px;">
        <span style="color: var(--border-focus); font-weight: 700;"><?= number_format((int)($kpi['owned_games'] ?? 0)) ?></span> in physical library
      </div>
    </div>

    <!-- 2. In Collection (% of total) -->
    <div class="editor-card" style="padding: 18px; position: static;">
      <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">In Collection</div>
      <div style="font-size: 32px; font-weight: 800; color: var(--warning); margin-top: 4px; line-height: 1.1;">
        <?= View::e((string)($kpi['owned_pct'] ?? 0)) ?>%
      </div>
      <div style="font-size: 12px; color: var(--text-dim); margin-top: 6px;">
        <span style="color: #fff; font-weight: 600;"><?= number_format((int)($kpi['owned_games'] ?? 0)) ?></span> of <?= number_format((int)($kpi['total_games'] ?? 0)) ?> titles in collection
      </div>
    </div>

    <!-- 3. Catalog Health -->
    <div class="editor-card" style="padding: 18px; position: static;">
      <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Catalog Health</div>
      <div style="font-size: 32px; font-weight: 800; color: <?= $healthColor ?>; margin-top: 4px; line-height: 1.1;">
        <?= View::e((string)($kpi['health_pct'] ?? 0)) ?>%
      </div>
      <div style="font-size: 12px; color: var(--text-dim); margin-top: 6px;">
        <span style="color: var(--success); font-weight: 600;"><?= number_format((int)($kpi['complete_games'] ?? 0)) ?></span> complete &bull; <span style="color: var(--warning); font-weight: 600;"><?= number_format((int)($kpi['missing_secondary'] ?? 0)) ?></span> missing secondary &bull; <span style="color: var(--danger); font-weight: 600;"><?= number_format((int)($kpi['missing_basic'] ?? 0)) ?></span> missing basic
      </div>
    </div>
  </div>

  <!-- SECTION 2: Launchpad Banner -->
  <?php if ($canCollection): ?>
    <div style="width: 100%;">
      <a href="/collection" class="nav-card" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-color: var(--border-focus); text-decoration: none; padding: 24px; display: block;">
        <div class="card-top" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
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
    </div>
  <?php endif; ?>

  <!-- SECTION 3: Consoles Breakdown & Management Portals -->
  <div class="dashboard-main-row" <?= !$hasAnyPortal ? 'style="grid-template-columns: 1fr;"' : '' ?>>
    <!-- Top Hardware Breakdown -->
    <div class="grid-card" style="border: 1px solid var(--border); border-radius: var(--radius-md);">
      <div class="grid-header">
        <span style="font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Top Systems (Owned)</span>
        <?php if ($canConsoles): ?>
          <a href="/consoles" style="color: var(--border-focus); font-size: 11px; text-decoration: none;">View All &rarr;</a>
        <?php endif; ?>
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
                <tr <?= $canCollection ? "onclick=\"window.location.href='/collection?console_id=" . (int)$c['id'] . "'\" style=\"cursor: pointer;\"" : "" ?>>
                  <td style="font-weight: 600;">
                    <?= View::e($c['console'] ?? '') ?>
                    <?php if (!empty($c['console_type_name'])): ?>
                      <span style="font-size: 9px; font-weight: 700; padding: 1px 6px; border-radius: 4px; margin-left: 6px; display: inline-block; background-color: <?= View::e($c['badge_bg_color'] ?? '#1e3a8a') ?>; color: <?= View::e($c['badge_font_color'] ?? '#93c5fd') ?>; border: 1px solid <?= View::e($c['badge_font_color'] ?? '#93c5fd') ?>44; text-transform: uppercase;">
                        <?= View::e($c['console_type_name']) ?>
                      </span>
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
    <?php if ($hasAnyPortal): ?>
      <div class="dashboard-portals-grid">
        <?php if ($canGames): ?>
          <a href="/games" class="nav-card" style="padding: 16px; border-radius: var(--radius-md);">
            <div class="card-top" style="margin-bottom: 8px;">
              <span style="font-size: 22px;">🎮</span>
              <span class="badge-count"><?= number_format((int)($kpi['total_games'] ?? 0)) ?></span>
            </div>
            <div style="font-size: 15px; font-weight: 700; color: #fff;">Games</div>
            <div class="card-desc" style="font-size: 11px;">Data entry workbench & media uploader.</div>
          </a>
        <?php endif; ?>

        <?php if ($canConsoles): ?>
          <a href="/consoles" class="nav-card" style="padding: 16px; border-radius: var(--radius-md);">
            <div class="card-top" style="margin-bottom: 8px;">
              <span style="font-size: 22px;">🕹</span>
              <span class="badge-count"><?= number_format((int)($counts['consoles'] ?? 0)) ?></span>
            </div>
            <div style="font-size: 15px; font-weight: 700; color: #fff;">Consoles</div>
            <div class="card-desc" style="font-size: 11px;">Hardware specs, logos, photos & emulators.</div>
          </a>
        <?php endif; ?>

        <?php if ($canConsoleTypes): ?>
          <a href="/console-types" class="nav-card" style="padding: 16px; border-radius: var(--radius-md);">
            <div class="card-top" style="margin-bottom: 8px;">
              <span style="font-size: 22px;">🏷️</span>
              <span class="badge-count"><?= number_format((int)($counts['console_types'] ?? 0)) ?></span>
            </div>
            <div style="font-size: 15px; font-weight: 700; color: #fff;">Console Types</div>
            <div class="card-desc" style="font-size: 11px;">Hardware categories and badge styles.</div>
          </a>
        <?php endif; ?>

        <?php if ($canPublishers): ?>
          <a href="/publishers" class="nav-card" style="padding: 16px; border-radius: var(--radius-md);">
            <div class="card-top" style="margin-bottom: 8px;">
              <span style="font-size: 22px;">🏢</span>
              <span class="badge-count"><?= number_format((int)($counts['publishers'] ?? 0)) ?></span>
            </div>
            <div style="font-size: 15px; font-weight: 700; color: #fff;">Publishers</div>
            <div class="card-desc" style="font-size: 11px;">Game studios and console manufacturers.</div>
          </a>
        <?php endif; ?>

        <?php if ($canCategories): ?>
          <a href="/categories" class="nav-card" style="padding: 16px; border-radius: var(--radius-md);">
            <div class="card-top" style="margin-bottom: 8px;">
              <span style="font-size: 22px;">📁</span>
              <span class="badge-count"><?= number_format((int)($counts['categories'] ?? 0)) ?></span>
            </div>
            <div style="font-size: 15px; font-weight: 700; color: #fff;">Categories</div>
            <div class="card-desc" style="font-size: 11px;">Primary genre taxonomies.</div>
          </a>
        <?php endif; ?>

        <?php if ($canSubcategories): ?>
          <a href="/subcategories" class="nav-card" style="padding: 16px; border-radius: var(--radius-md);">
            <div class="card-top" style="margin-bottom: 8px;">
              <span style="font-size: 22px;">📂</span>
              <span class="badge-count"><?= number_format((int)($counts['subcategories'] ?? 0)) ?></span>
            </div>
            <div style="font-size: 15px; font-weight: 700; color: #fff;">Subcategories</div>
            <div class="card-desc" style="font-size: 11px;">Detailed sub-genre classification.</div>
          </a>
        <?php endif; ?>

        <?php if ($canLanguages): ?>
          <a href="/languages" class="nav-card" style="padding: 16px; border-radius: var(--radius-md);">
            <div class="card-top" style="margin-bottom: 8px;">
              <span style="font-size: 22px;">🌐</span>
              <span class="badge-count"><?= number_format((int)($counts['languages'] ?? 0)) ?></span>
            </div>
            <div style="font-size: 15px; font-weight: 700; color: #fff;">Languages</div>
            <div class="card-desc" style="font-size: 11px;">Localization and language flags.</div>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
