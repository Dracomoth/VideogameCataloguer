<?php
/**
 * src/Views/navbar.php
 * Collapsible Floating Sidebar Navigation Component with RBAC Screen Filtering.
 *
 * Variables expected in scope:
 * @var string|null $navActive Identifier for active navigation tab (e.g. 'dashboard')
 * @var string|null $activeNav Alias identifier
 */

declare(strict_types=1);

use Vault\Auth\Auth;
use Vault\Services\View;

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

// 1. Group 1: Operational Overview
$canDashboard = $canView('dashboard');
$canPlayerHub = $canView('collection');
$hasGroup1    = $canDashboard || $canPlayerHub;

// 2. Group 2: Core Catalog & Metadata
$canGames         = $canView('games');
$canConsoles      = $canView('consoles');
$canPublishers    = $canView('publishers');

$canConsoleTypes  = $canView('console_types');
$canCategories    = $canView('categories');
$canSubcategories = $canView('subcategories');
$canLanguages     = $canView('languages');
$hasMetadata      = $canConsoleTypes || $canCategories || $canSubcategories || $canLanguages;

$hasGroup2        = $canGames || $canConsoles || $canPublishers || $hasMetadata;

// 3. Group 3: Tools & Administration
$canReports       = $canView('reports');
$canBulkUpload    = $canView('bulk_upload');
$hasTools         = $canReports || $canBulkUpload;

$canUsers         = $canView('users');
$canRoles         = $canView('roles');
$hasAdmin         = $canUsers || $canRoles;

$hasGroup3        = $hasTools || $hasAdmin;

// Dynamic Separators based on visible options
$showSep1 = $hasGroup1 && ($hasGroup2 || $hasGroup3);
$showSep2 = $hasGroup2 && $hasGroup3;

// Active group states for collapsible menus
$isMetadataActive = in_array($active, ['console_types', 'categories', 'subcategories', 'languages'], true);
$isToolsActive    = in_array($active, ['reports', 'bulk_upload'], true);
$isAdminActive    = in_array($active, ['users', 'roles'], true);
?>

<!-- Floating Sidebar Backdrop Overlay -->
<div id="sidebarBackdrop" class="sidebar-backdrop" onclick="closeSidebar()" aria-hidden="true"></div>

<!-- Collapsible Floating Sidebar Drawer -->
<aside id="masterSidebar" class="master-sidebar" aria-label="Main Navigation">
  <div class="sidebar-inner">
    <!-- Sidebar Header with Brand Badge & Close Button -->
    <div class="sidebar-header">
      <div class="sidebar-brand">
        <div class="sidebar-logo-badge">🎮</div>
        <div class="sidebar-brand-text">
          <span class="sidebar-brand-title">Videogame Vault</span>
          <span class="sidebar-brand-subtitle">Navigation</span>
        </div>
      </div>
      <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" onclick="closeSidebar()" aria-label="Close navigation menu" title="Close Menu (Esc)">
        ✕
      </button>
    </div>

    <!-- Scrollable Navigation Items -->
    <nav class="sidebar-nav-body">
      <!-- 1. Operational Overview -->
      <?php if ($canDashboard): ?>
        <a href="/" class="sidebar-link <?= $active === 'dashboard' ? 'active' : '' ?>" onclick="closeSidebar()">
          <span class="sidebar-icon">🏠</span>
          <span class="sidebar-label">Dashboard</span>
        </a>
      <?php endif; ?>

      <?php if ($canPlayerHub): ?>
        <a href="/collection" class="sidebar-link player-hub-link <?= $active === 'collection' ? 'active' : '' ?>" onclick="closeSidebar()">
          <span class="sidebar-icon">▶</span>
          <span class="sidebar-label">Player Hub</span>
        </a>
      <?php endif; ?>

      <!-- Separator 1 -->
      <?php if ($showSep1): ?>
        <div class="sidebar-separator"></div>
      <?php endif; ?>

      <!-- 2. Core Catalog -->
      <?php if ($canGames): ?>
        <a href="/games" class="sidebar-link <?= $active === 'games' ? 'active' : '' ?>" onclick="closeSidebar()">
          <span class="sidebar-icon">🕹️</span>
          <span class="sidebar-label">Games</span>
        </a>
      <?php endif; ?>

      <?php if ($canConsoles): ?>
        <a href="/consoles" class="sidebar-link <?= $active === 'consoles' ? 'active' : '' ?>" onclick="closeSidebar()">
          <span class="sidebar-icon">💻</span>
          <span class="sidebar-label">Consoles</span>
        </a>
      <?php endif; ?>

      <?php if ($canPublishers): ?>
        <a href="/publishers" class="sidebar-link <?= $active === 'publishers' ? 'active' : '' ?>" onclick="closeSidebar()">
          <span class="sidebar-icon">🏷️</span>
          <span class="sidebar-label">Publishers</span>
        </a>
      <?php endif; ?>

      <!-- Metadata (Collapsible) -->
      <?php if ($hasMetadata): ?>
        <div class="sidebar-group <?= $isMetadataActive ? 'expanded' : '' ?>" id="group-metadata">
          <button type="button" class="sidebar-group-header" onclick="toggleSidebarGroup('group-metadata')" aria-expanded="<?= $isMetadataActive ? 'true' : 'false' ?>">
            <span class="group-header-label">
              <span class="sidebar-icon">📁</span>
              <span class="group-title">Metadata</span>
            </span>
            <span class="group-chevron">▾</span>
          </button>
          <div class="sidebar-sub-items">
            <?php if ($canConsoleTypes): ?>
              <a href="/console-types" class="sidebar-sub-link <?= $active === 'console_types' ? 'active' : '' ?>" onclick="closeSidebar()">
                <span class="sub-bullet">•</span>
                <span>Console Types</span>
              </a>
            <?php endif; ?>

            <?php if ($canCategories): ?>
              <a href="/categories" class="sidebar-sub-link <?= $active === 'categories' ? 'active' : '' ?>" onclick="closeSidebar()">
                <span class="sub-bullet">•</span>
                <span>Categories</span>
              </a>
            <?php endif; ?>

            <?php if ($canSubcategories): ?>
              <a href="/subcategories" class="sidebar-sub-link <?= $active === 'subcategories' ? 'active' : '' ?>" onclick="closeSidebar()">
                <span class="sub-bullet">•</span>
                <span>Subcategories</span>
              </a>
            <?php endif; ?>

            <?php if ($canLanguages): ?>
              <a href="/languages" class="sidebar-sub-link <?= $active === 'languages' ? 'active' : '' ?>" onclick="closeSidebar()">
                <span class="sub-bullet">•</span>
                <span>Languages</span>
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Separator 2 -->
      <?php if ($showSep2): ?>
        <div class="sidebar-separator"></div>
      <?php endif; ?>

      <!-- 3. Tools (Collapsible) -->
      <?php if ($hasTools): ?>
        <div class="sidebar-group <?= $isToolsActive ? 'expanded' : '' ?>" id="group-tools">
          <button type="button" class="sidebar-group-header" onclick="toggleSidebarGroup('group-tools')" aria-expanded="<?= $isToolsActive ? 'true' : 'false' ?>">
            <span class="group-header-label">
              <span class="sidebar-icon">⚙️</span>
              <span class="group-title">Tools</span>
            </span>
            <span class="group-chevron">▾</span>
          </button>
          <div class="sidebar-sub-items">
            <?php if ($canReports): ?>
              <a href="/reports" class="sidebar-sub-link <?= $active === 'reports' ? 'active' : '' ?>" onclick="closeSidebar()">
                <span class="sub-bullet">•</span>
                <span>Reports</span>
              </a>
            <?php endif; ?>

            <?php if ($canBulkUpload): ?>
              <a href="/bulk-upload" class="sidebar-sub-link <?= $active === 'bulk_upload' ? 'active' : '' ?>" onclick="closeSidebar()">
                <span class="sub-bullet">•</span>
                <span>Bulk Upload</span>
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Admin (Collapsible) -->
      <?php if ($hasAdmin): ?>
        <div class="sidebar-group <?= $isAdminActive ? 'expanded' : '' ?>" id="group-admin">
          <button type="button" class="sidebar-group-header" onclick="toggleSidebarGroup('group-admin')" aria-expanded="<?= $isAdminActive ? 'true' : 'false' ?>">
            <span class="group-header-label">
              <span class="sidebar-icon">🛡️</span>
              <span class="group-title">Admin</span>
            </span>
            <span class="group-chevron">▾</span>
          </button>
          <div class="sidebar-sub-items">
            <?php if ($canUsers): ?>
              <a href="/users" class="sidebar-sub-link <?= $active === 'users' ? 'active' : '' ?>" onclick="closeSidebar()">
                <span class="sub-bullet">•</span>
                <span>Users</span>
              </a>
            <?php endif; ?>

            <?php if ($canRoles): ?>
              <a href="/roles" class="sidebar-sub-link <?= $active === 'roles' ? 'active' : '' ?>" onclick="closeSidebar()">
                <span class="sub-bullet">•</span>
                <span>Roles</span>
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </nav>

    <!-- Sidebar Footer: Session Card & Logout -->
    <div class="sidebar-footer">
      <?php if ($currentUser !== null): ?>
        <?php
          $sidebarAvatarUrl = !empty($currentUser['avatar_path']) ? (string)$currentUser['avatar_path'] : null;
          $sFirst = trim((string)($currentUser['first_name'] ?? ''));
          $sLast  = trim((string)($currentUser['last_name'] ?? ''));
          $sInitials = '';
          if ($sFirst !== '') $sInitials .= substr($sFirst, 0, 1);
          if ($sLast !== '') $sInitials .= substr($sLast, 0, 1);
          $sInitials = strtoupper($sInitials ?: 'U');
        ?>
        <div class="sidebar-user-card">
          <div class="sidebar-user-avatar">
            <?php if (!empty($sidebarAvatarUrl)): ?>
              <img src="<?= View::e($sidebarAvatarUrl) ?>" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
            <?php else: ?>
              <span class="sidebar-avatar-initials"><?= View::e($sInitials) ?></span>
            <?php endif; ?>
          </div>
          <div class="sidebar-user-details">
            <span class="sidebar-user-name"><?= View::e($currentUser['first_name'] . ' ' . ($currentUser['last_name'] ?? '')) ?></span>
            <span class="sidebar-user-role"><?= View::e($currentUser['role_name'] ?? 'User') ?></span>
          </div>
          <a href="/logout" class="sidebar-logout-icon" title="Sign Out" aria-label="Sign Out">
            <svg class="sidebar-logout-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
              <polyline points="16 17 21 12 16 7"></polyline>
              <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
          </a>
        </div>
      <?php else: ?>
        <a href="/login" class="sidebar-login-link" onclick="closeSidebar()">🔑 Sign In</a>
      <?php endif; ?>
    </div>
  </div>
</aside>

<script>
// Sidebar Drawer Interactive Controller
(function() {
  function toggleSidebar() {
    const isOpen = document.body.classList.toggle('sidebar-open');
    const toggleBtn = document.getElementById('sidebarToggle');
    if (toggleBtn) {
      toggleBtn.classList.toggle('is-active', isOpen);
      toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
  }

  function closeSidebar() {
    document.body.classList.remove('sidebar-open');
    const toggleBtn = document.getElementById('sidebarToggle');
    if (toggleBtn) {
      toggleBtn.classList.remove('is-active');
      toggleBtn.setAttribute('aria-expanded', 'false');
    }
  }

  function toggleSidebarGroup(groupId) {
    const group = document.getElementById(groupId);
    if (!group) return;
    const isExpanded = group.classList.toggle('expanded');
    const btn = group.querySelector('.sidebar-group-header');
    if (btn) {
      btn.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
    }
  }

  // Keyboard accessibility: Escape key closes sidebar
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
      closeSidebar();
    }
  });

  // Attach global functions to window
  window.toggleSidebar = toggleSidebar;
  window.closeSidebar = closeSidebar;
  window.toggleSidebarGroup = toggleSidebarGroup;

  // Initialize toggle button event listener
  document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('sidebarToggle');
    if (toggleBtn) {
      toggleBtn.onclick = toggleSidebar;
    }
  });
})();
</script>
