<?php
/**
 * src/Views/navbar.php
 * Modular Application Navigation Bar Component.
 *
 * Variables expected in scope:
 * @var string|null $navActive Identifier for active navigation tab (e.g. 'dashboard')
 * @var string|null $activeNav Alias identifier
 */

declare(strict_types=1);

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

$active = $navActive ?? ($activeNav ?? 'dashboard');
?>
<!-- Global Application Navigation Bar -->
<nav class="master-nav">
  <div class="nav-cluster">
    <a href="/" class="nav-tab <?= $active === 'dashboard' ? 'active' : '' ?>">
      🏠 Dashboard
    </a>
    <a href="/collection" class="nav-tab player-tab <?= $active === 'collection' ? 'active' : '' ?>">
      ▶ Player Hub
    </a>
    <span class="nav-divider nav-desktop-only"></span>

    <!-- Maintenance / Data Entry (Desktop Only) -->
    <a href="/games" class="nav-tab nav-desktop-only <?= $active === 'games' ? 'active' : '' ?>">
      Games
    </a>
    <a href="/consoles" class="nav-tab nav-desktop-only <?= $active === 'consoles' ? 'active' : '' ?>">
      Consoles
    </a>
    <a href="/publishers" class="nav-tab nav-desktop-only <?= $active === 'publishers' ? 'active' : '' ?>">
      Publishers
    </a>
    <a href="/categories" class="nav-tab nav-desktop-only <?= $active === 'categories' ? 'active' : '' ?>">
      Categories
    </a>
    <a href="/subcategories" class="nav-tab nav-desktop-only <?= $active === 'subcategories' ? 'active' : '' ?>">
      Subcategories
    </a>
    <a href="/languages" class="nav-tab nav-desktop-only <?= $active === 'languages' ? 'active' : '' ?>">
      Languages
    </a>
    <span class="nav-divider"></span>

    <!-- System & Export Portals -->
    <a href="/reports" class="nav-tab <?= $active === 'reports' ? 'active' : '' ?>">
      📊 Reports
    </a>
    <a href="/bulk-upload" class="nav-tab nav-desktop-only <?= $active === 'bulk_upload' ? 'active' : '' ?>">
      📥 Bulk Upload
    </a>
    <a href="/admin" class="nav-tab nav-desktop-only <?= $active === 'admin' ? 'active' : '' ?>">
      ⚙ Admin
    </a>
  </div>
  <div class="nav-actions-quick nav-desktop-only">
    <a href="/games?is_new=1" class="btn btn-sm primary">+ Add Game</a>
  </div>
</nav>
