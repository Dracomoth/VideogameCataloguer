<?php
/**
 * src/Views/header.php
 * Modular Application Header Component.
 *
 * Variables expected in scope:
 * @var int|null $totalGames
 * @var int|null $ownedGames
 * @var int|null $totalSystems
 */

declare(strict_types=1);

use Vault\Auth\Auth;
use Vault\Services\View;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

$totalGames   = (int)($totalGames ?? 0);
$ownedGames   = (int)($ownedGames ?? 0);
$totalSystems = (int)($totalSystems ?? 0);
$currentUser  = Auth::user();
?>
<!-- Top Banner with Title, Sandwich Trigger & Quick Telemetry -->
<header class="master-header">
  <div class="master-header-overlay"></div>
  <div class="master-header-content">
    <div class="header-brand">
      <!-- Sandwich Toggle Button BEFORE the title and title logo -->
      <button type="button" id="sidebarToggle" class="sidebar-toggle-btn" onclick="toggleSidebar()" aria-label="Toggle navigation menu" aria-expanded="false" title="Toggle Navigation Menu">
        <span class="hamburger-box">
          <span class="hamburger-line line-1"></span>
          <span class="hamburger-line line-2"></span>
          <span class="hamburger-line line-3"></span>
        </span>
      </button>

      <a href="/" class="brand-link">
        <div class="brand-logo-badge">🎮</div>
        <div>
          <h1 class="brand-title">Videogame Vault</h1>
          <p class="brand-subtitle">Collection Tracker & Cataloguer</p>
        </div>
      </a>
    </div>

    <!-- Quick Telemetry Counters & User Controls -->
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

      <?php if ($currentUser !== null): ?>
        <div class="telemetry-user-pill">
          <span>👤 <?= View::e($currentUser['first_name']) ?></span>
          <span class="telemetry-role-tag"><?= View::e($currentUser['role_name'] ?? 'User') ?></span>
        </div>
        <a href="/logout" class="header-logout-link" title="Sign Out">🚪 Logout</a>
      <?php else: ?>
        <a href="/login" class="header-login-link" title="Sign In">🔑 Sign In</a>
      <?php endif; ?>
    </div>
  </div>
</header>
