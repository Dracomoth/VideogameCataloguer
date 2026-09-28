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

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

$totalGames   = (int)($totalGames ?? 0);
$ownedGames   = (int)($ownedGames ?? 0);
$totalSystems = (int)($totalSystems ?? 0);
?>
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

    <!-- Quick Telemetry Counters -->
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
