<?php
/**
 * src/Views/navbar.php
 * Modular Application Navigation Bar Component with RBAC Screen Filtering.
 *
 * Variables expected in scope:
 * @var string|null $navActive Identifier for active navigation tab (e.g. 'dashboard')
 * @var string|null $activeNav Alias identifier
 */

declare(strict_types=1);

use Vault\Auth\Auth;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

$active = $navActive ?? ($activeNav ?? 'dashboard');
$currentUser = Auth::user();

// Evaluates whether the current user has read/navigation access to a screen
$canView = function (string $screen): bool {
    if (Auth::check()) {
        return Auth::can($screen, 'read');
    }
    // Fallback during initial onboarding before mandatory login enforcement
    return true;
};
?>
<!-- Global Application Navigation Bar with RBAC Filtering -->
<nav class="master-nav">
  <div class="nav-cluster">
    <!-- 1. Operational Overview -->
    <?php if ($canView('dashboard')): ?>
      <a href="/" class="nav-tab <?= $active === 'dashboard' ? 'active' : '' ?>">
        🏠 Dashboard
      </a>
    <?php endif; ?>

    <?php if ($canView('collection')): ?>
      <a href="/collection" class="nav-tab player-tab <?= $active === 'collection' ? 'active' : '' ?>">
        ▶ Player Hub
      </a>
    <?php endif; ?>

    <?php if ($canView('games') || $canView('consoles') || $canView('console_types') || $canView('publishers') || $canView('categories') || $canView('subcategories') || $canView('languages')): ?>
      <span class="nav-divider nav-desktop-only"></span>
    <?php endif; ?>

    <!-- 2. Maintenance & Inventory (Desktop Focused) -->
    <?php if ($canView('games')): ?>
      <a href="/games" class="nav-tab nav-desktop-only <?= $active === 'games' ? 'active' : '' ?>">
        Games
      </a>
    <?php endif; ?>

    <?php if ($canView('consoles')): ?>
      <a href="/consoles" class="nav-tab nav-desktop-only <?= $active === 'consoles' ? 'active' : '' ?>">
        Consoles
      </a>
    <?php endif; ?>

    <?php if ($canView('console_types')): ?>
      <a href="/console-types" class="nav-tab nav-desktop-only <?= $active === 'console_types' ? 'active' : '' ?>">
        Console Types
      </a>
    <?php endif; ?>

    <?php if ($canView('publishers')): ?>
      <a href="/publishers" class="nav-tab nav-desktop-only <?= $active === 'publishers' ? 'active' : '' ?>">
        Publishers
      </a>
    <?php endif; ?>

    <?php if ($canView('categories')): ?>
      <a href="/categories" class="nav-tab nav-desktop-only <?= $active === 'categories' ? 'active' : '' ?>">
        Categories
      </a>
    <?php endif; ?>

    <?php if ($canView('subcategories')): ?>
      <a href="/subcategories" class="nav-tab nav-desktop-only <?= $active === 'subcategories' ? 'active' : '' ?>">
        Subcategories
      </a>
    <?php endif; ?>

    <?php if ($canView('languages')): ?>
      <a href="/languages" class="nav-tab nav-desktop-only <?= $active === 'languages' ? 'active' : '' ?>">
        Languages
      </a>
    <?php endif; ?>

    <?php if ($canView('reports') || $canView('bulk_upload') || $canView('users') || $canView('roles')): ?>
      <span class="nav-divider"></span>
    <?php endif; ?>

    <!-- 3. System & Administration -->
    <?php if ($canView('reports')): ?>
      <a href="/reports" class="nav-tab <?= $active === 'reports' ? 'active' : '' ?>">
        📊 Reports
      </a>
    <?php endif; ?>

    <?php if ($canView('bulk_upload')): ?>
      <a href="/bulk-upload" class="nav-tab nav-desktop-only <?= $active === 'bulk_upload' ? 'active' : '' ?>">
        📥 Bulk Upload
      </a>
    <?php endif; ?>

    <!-- User & Role Management Sections -->
    <?php if ($canView('users')): ?>
      <a href="/users" class="nav-tab nav-desktop-only <?= $active === 'users' ? 'active' : '' ?>">
        👥 Users
      </a>
    <?php endif; ?>

    <?php if ($canView('roles')): ?>
      <a href="/roles" class="nav-tab nav-desktop-only <?= $active === 'roles' ? 'active' : '' ?>">
        🛡 Roles
      </a>
    <?php endif; ?>
  </div>

  <!-- User Session Indicator & Auth Portal Trigger -->
  <div class="nav-cluster" style="margin-left: auto;">
    <?php if ($currentUser !== null): ?>
      <span class="nav-tab nav-desktop-only" style="cursor: default; opacity: 0.85; font-size: 11px;">
        👤 <?= htmlspecialchars((string)$currentUser['first_name'], ENT_QUOTES, 'UTF-8') ?>
        <span class="tag" style="font-size: 9px; margin-left: 4px; padding: 1px 4px; background: rgba(56, 189, 248, 0.15); color: #38bdf8;">
          <?= htmlspecialchars((string)($currentUser['role_name'] ?? 'User'), ENT_QUOTES, 'UTF-8') ?>
        </span>
      </span>
      <a href="/logout" class="nav-tab" style="color: #f87171;" title="Sign Out">
        🚪 Logout
      </a>
    <?php else: ?>
      <a href="/login" class="nav-tab" style="color: var(--border-focus);" title="Sign In">
        🔑 Sign In
      </a>
    <?php endif; ?>
  </div>
</nav>
