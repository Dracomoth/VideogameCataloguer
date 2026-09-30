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

// Compute avatar url or fallback initials
$avatarUrl    = null;
$userFullName = 'Guest User';
$roleName     = 'Guest';
$initials     = 'U';

if ($currentUser !== null) {
    $avatarUrl = !empty($currentUser['avatar_path']) ? (string)$currentUser['avatar_path'] : null;
    $first     = trim((string)($currentUser['first_name'] ?? ''));
    $last      = trim((string)($currentUser['last_name'] ?? ''));
    $userFullName = trim($first . ' ' . $last);
    if ($userFullName === '') {
        $userFullName = 'User';
    }
    $roleName = (string)($currentUser['role_name'] ?? 'User');

    $initials = '';
    if ($first !== '') {
        $initials .= substr($first, 0, 1);
    }
    if ($last !== '') {
        $initials .= substr($last, 0, 1);
    }
    if ($initials === '') {
        $initials = 'U';
    }
    $initials = strtoupper($initials);
}
$titleTooltip = $userFullName . ' (' . $roleName . ')';
?>
<!-- Top Banner with Title, Sandwich Trigger, Telemetry Under Title & User Avatar -->
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

      <div class="header-brand-block">
        <div class="header-brand-top">
          <a href="/" class="brand-link">
            <div class="brand-logo-badge">🎮</div>
            <div class="brand-text-block">
              <h1 class="brand-title">Videogame Vault</h1>
              <p class="brand-subtitle">Collection Tracker & Cataloguer</p>
            </div>
          </a>
        </div>

        <!-- Telemetry buttons placed under the title -->
        <div class="header-telemetry-row">
          <div class="telemetry-pill">
            <span>Games:</span> <strong id="headerTotalGames"><?= number_format($totalGames) ?></strong>
          </div>
          <div class="telemetry-pill">
            <span>Owned:</span> <strong id="headerOwnedGames"><?= number_format($ownedGames) ?></strong>
          </div>
          <div class="telemetry-pill">
            <span>Consoles:</span> <strong id="headerTotalConsoles"><?= number_format($totalSystems) ?></strong>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Side: User section displaying ONLY the avatar (or avatar initials) -->
    <div class="header-user-section">
      <?php if ($currentUser !== null): ?>
        <button type="button" class="header-avatar-btn" onclick="toggleSidebar()" title="<?= View::e($titleTooltip) ?>" aria-label="<?= View::e($titleTooltip) ?>">
          <?php if (!empty($avatarUrl)): ?>
            <img src="<?= View::e($avatarUrl) ?>" alt="<?= View::e($userFullName) ?>" class="header-avatar-img">
          <?php else: ?>
            <div class="header-avatar-initials"><?= View::e($initials) ?></div>
          <?php endif; ?>
        </button>
      <?php else: ?>
        <a href="/login" class="header-login-link" title="Sign In">🔑 Sign In</a>
      <?php endif; ?>
    </div>
  </div>
</header>
