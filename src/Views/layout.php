<?php
/**
 * src/Views/layout.php
 * Master Layout Shell & UI Workbench Scaffold.
 *
 * Variables provided by View::render():
 * @var string $content          Rendered inner view markup
 * @var string|null $pageTitle   Document <title> and header designation
 * @var string|null $activeNav   Identifier for active navigation tab
 * @var array<string, int>|null $stats Optional collection telemetry figures
 */

declare(strict_types=1);

use Vault\Services\View;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

$title = !empty($pageTitle) ? View::e($pageTitle) . ' | Videogame Vault' : 'Videogame Vault';
$navActive = $activeNav ?? 'dashboard';

// Default telemetry badges
$totalGames   = $stats['total_games'] ?? 0;
$ownedGames   = $stats['owned_games'] ?? 0;
$totalSystems = $stats['total_consoles'] ?? 0;

$stylePath = __DIR__ . '/../../assets/css/style.css';
$styleVer  = file_exists($stylePath) ? (string)filemtime($stylePath) : '1.0';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $title ?></title>
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= $styleVer ?>">
</head>
<body>
<div class="master-wrapper">
  <!-- Top Banner with Title & Quick Telemetry -->
  <header class="master-header">
    <div class="master-header-overlay"></div>
    <div class="master-header-content">
      <div class="header-brand">
        <a href="/" class="brand-link">
          <div class="brand-logo-badge">🎮</div>
          <div>
            <h1 class="brand-title">Videogame Vault</h1>
            <p class="brand-subtitle">Collection Tracker & Cataloguer</p>
          </div>
        </a>
      </div>
      <!-- Telemetry Counters -->
      <div class="header-telemetry">
        <div class="telemetry-pill">
          <span>Games:</span> <strong><?= number_format($totalGames) ?></strong>
        </div>
        <div class="telemetry-pill">
          <span>Owned:</span> <strong><?= number_format($ownedGames) ?></strong>
        </div>
        <div class="telemetry-pill">
          <span>Consoles:</span> <strong><?= number_format($totalSystems) ?></strong>
        </div>
      </div>
    </div>
  </header>

  <!-- Global Application Navigation Bar -->
  <nav class="master-nav">
    <div class="nav-cluster">
      <a href="/" class="nav-tab <?= $navActive === 'dashboard' ? 'active' : '' ?>">
        🏠 Dashboard
      </a>
      <a href="/collection" class="nav-tab player-tab <?= $navActive === 'collection' ? 'active' : '' ?>">
        ▶ Player Hub
      </a>
      <span class="nav-divider nav-desktop-only"></span>

      <!-- Maintenance / Data Entry (Desktop Only) -->
      <a href="/games" class="nav-tab nav-desktop-only <?= $navActive === 'games' ? 'active' : '' ?>">
        Games
      </a>
      <a href="/consoles" class="nav-tab nav-desktop-only <?= $navActive === 'consoles' ? 'active' : '' ?>">
        Consoles
      </a>
      <a href="/publishers" class="nav-tab nav-desktop-only <?= $navActive === 'publishers' ? 'active' : '' ?>">
        Publishers
      </a>
      <a href="/categories" class="nav-tab nav-desktop-only <?= $navActive === 'categories' ? 'active' : '' ?>">
        Categories
      </a>
      <a href="/subcategories" class="nav-tab nav-desktop-only <?= $navActive === 'subcategories' ? 'active' : '' ?>">
        Subcategories
      </a>
      <a href="/languages" class="nav-tab nav-desktop-only <?= $navActive === 'languages' ? 'active' : '' ?>">
        Languages
      </a>
      <span class="nav-divider"></span>

      <!-- System & Export Portals -->
      <a href="/reports" class="nav-tab <?= $navActive === 'reports' ? 'active' : '' ?>">
        📊 Reports
      </a>
      <a href="/bulk-upload" class="nav-tab nav-desktop-only <?= $navActive === 'bulk_upload' ? 'active' : '' ?>">
        📥 Bulk Upload
      </a>
      <a href="/admin" class="nav-tab nav-desktop-only <?= $navActive === 'admin' ? 'active' : '' ?>">
        ⚙ Admin
      </a>
    </div>
    <div class="nav-actions-quick nav-desktop-only">
      <a href="/games?is_new=1" class="btn btn-sm primary">+ Add Game</a>
    </div>
  </nav>

  <!-- Primary Application Content Slot -->
  <main class="master-main-content">
    <?= $content ?>
  </main>

  <!-- Sticky Grounded Footer -->
  <footer class="master-footer">
    <div class="footer-row-primary">
      <div class="footer-col">
        <span class="status-indicator-dot"></span>
        <span class="footer-subtext">Database Online &bull; UTF-8 Engine</span>
      </div>
      <div class="footer-col shortcuts-legend">
        <span><kbd>Ctrl</kbd>+<kbd>S</kbd> Save Record</span>
        <span><kbd>Esc</kbd> Reset / Clear</span>
      </div>
      <div class="footer-col date-display">
        📅 <?= date('l, F j, Y') ?>
      </div>
    </div>
    <div class="footer-row-secondary">
      <div>Videogame Vault &copy; <?= date('Y') ?> &bull; Handheld & Retro Vault</div>
      <a href="#top" onclick="window.scrollTo({top: 0, behavior: 'smooth'}); return false;" class="scroll-top-link">
        Back to Top &uarr;
      </a>
    </div>
  </footer>
</div>

<!-- Global Dynamic Toast Mount -->
<div id="toastContainer"></div>
</body>
</html>