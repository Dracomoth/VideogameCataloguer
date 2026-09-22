<?php
/**
 * layout_header.php - Master Top Template
 * 
 * Expected variables from caller (optional):
 * @var string $pageTitle   Title tag and main heading
 * @var string $activeNav   Identifies the active navigation link
 */
require_once __DIR__ . '/db.php';

$pageTitle =$pageTitle ?? 'Videogame Database';
$activeNav =$activeNav ?? '';

// Fetch quick counts for the header badge bar if not already present
try {
    $headerStats =$pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM `Games`) AS total_games,
            (SELECT COUNT(*) FROM `Games` WHERE `InCollection` = 1) AS owned_games,
            (SELECT COUNT(*) FROM `Consoles`) AS total_consoles
    ")->fetch();
} catch (Exception $e) {$headerStats = ['total_games' => 0, 'owned_games' => 0, 'total_consoles' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> | Videogame Vault</title>
  <link rel="stylesheet" href="css/style.css?v=<?= file_exists(__DIR__ . '/css/style.css') ? filemtime(__DIR__ . '/css/style.css') : '1.0' ?>">
</head>
<body>

<div class="master-wrapper">
  <!-- Top Banner with Background Art & Title -->
  <header class="master-header">
    <div class="master-header-overlay"></div>
    <div class="master-header-content">
      <div class="header-brand">
        <a href="index.php" class="brand-link">
          <div class="brand-logo-badge">&#127918;</div>
          <div>
            <h1 class="brand-title">Videogame Vault</h1>
            <p class="brand-subtitle">Collection Tracker & Cataloguer</p>
          </div>
        </a>
      </div>

      <!-- Quick Telemetry Chips -->
      <div class="header-telemetry">
        <div class="telemetry-pill">
          <span>Games:</span> <strong><?= number_format((int)$headerStats['total_games']) ?></strong>
        </div>
        <div class="telemetry-pill">
          <span>Owned:</span> <strong><?= number_format((int)$headerStats['owned_games']) ?></strong>
        </div>
        <div class="telemetry-pill">
          <span>Consoles:</span> <strong><?= number_format((int)$headerStats['total_consoles']) ?></strong>
        </div>
      </div>
    </div>
  </header>

  <!-- Global Navigation Bar -->
  <nav class="master-nav">
    <div class="nav-cluster">
      <a href="index.php" class="nav-tab <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
        &#127968; Dashboard
      </a>
      <a href="collection.php" class="nav-tab player-tab <?= $activeNav === 'collection' ? 'active' : '' ?>">
        &#9654; Player Hub
      </a>

      <!-- First Divider (Hidden on mobile along with the management items) -->
      <span class="nav-divider nav-desktop-only"></span>

      <!-- Maintenance / Data Entry (Desktop Only) -->
      <a href="games.php" class="nav-tab nav-desktop-only <?= $activeNav === 'games' ? 'active' : '' ?>">
        Games
      </a>
      <a href="consoles.php" class="nav-tab nav-desktop-only<?= $activeNav === 'consoles' ? 'active' : '' ?>">
        Consoles
      </a>
      <a href="publishers.php" class="nav-tab nav-desktop-only<?= $activeNav === 'publishers' ? 'active' : '' ?>">
        Publishers
      </a>
      <a href="categories.php" class="nav-tab nav-desktop-only<?= $activeNav === 'categories' ? 'active' : '' ?>">
        Categories
      </a>
      <a href="subcategories.php" class="nav-tab nav-desktop-only <?= $activeNav === 'subcategories' ? 'active' : '' ?>">
        Subcategories
      </a>
      <a href="languages.php" class="nav-tab nav-desktop-only <?= $activeNav === 'languages' ? 'active' : '' ?>">
        Languages
      </a>

      <span class="nav-divider"></span>

      <!-- New Management Features -->
      <a href="reports.php" class="nav-tab <?= $activeNav === 'reports' ? 'active' : '' ?>">
        &#128202; Reports
      </a>
      <a href="bulk_upload.php" class="nav-tab nav-desktop-only <?= $activeNav === 'bulk_upload' ? 'active' : '' ?>">
        &#128229; Bulk Upload
      </a>
    </div>

    <div class="nav-actions-quick nav-desktop-only">
      <a href="games.php?is_new=1" class="btn btn-sm primary">+ Add Game</a>
    </div>
  </nav>

  <!-- Content Container (Inner form/view will render inside this) -->
  <main class="master-main-content">