<?php
/**
 * src/Views/layout.php
 * Master Layout Shell & UI Workbench Scaffold.
 * Orchestrates modular components: header, navbar, dynamic body ($content), and footer.
 *
 * Variables provided by View::render():
 * @var string $content          Rendered inner view markup (body)
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
  <!-- Modular Top Header -->
  <?php require __DIR__ . '/header.php'; ?>

  <!-- Modular Global Navigation Bar -->
  <?php require __DIR__ . '/navbar.php'; ?>

  <!-- Primary Application Content Slot (Dynamic Body) -->
  <main class="master-main-content">
    <?= $content ?>
  </main>

  <!-- Modular Grounded Footer -->
  <?php require __DIR__ . '/footer.php'; ?>
</div>

<!-- Global Dynamic Toast Mount -->
<div id="toastContainer"></div>
</body>
</html>