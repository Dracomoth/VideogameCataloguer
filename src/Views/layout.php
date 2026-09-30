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

// Telemetry figures with fallback for views that don't explicitly pass $stats
if (!isset($stats) || !is_array($stats) || !isset($stats['total_games'])) {
    try {
        $tgRow = \Vault\Services\Database::fetchOne("
            SELECT 
                COUNT(*) AS total_games,
                SUM(CASE WHEN in_collection = 1 THEN 1 ELSE 0 END) AS owned_games
            FROM `games`
        ");
        $tcCount = (int)\Vault\Services\Database::fetchColumn("SELECT COUNT(*) FROM `consoles`");
        $stats = [
            'total_games'    => (int)($tgRow['total_games'] ?? 0),
            'owned_games'    => (int)($tgRow['owned_games'] ?? 0),
            'total_consoles' => $tcCount,
        ];
    } catch (\Throwable $e) {
        $stats = ['total_games' => 0, 'owned_games' => 0, 'total_consoles' => 0];
    }
}

$totalGames   = (int)($stats['total_games'] ?? 0);
$ownedGames   = (int)($stats['owned_games'] ?? 0);
$totalSystems = (int)($stats['total_consoles'] ?? 0);

$stylePath = __DIR__ . '/../../assets/css/style.css';
$styleVer  = file_exists($stylePath) ? (string)filemtime($stylePath) : '1.0';

$gridJsPath = __DIR__ . '/../../assets/js/components/data-grid.js';
$gridJsVer  = file_exists($gridJsPath) ? (string)filemtime($gridJsPath) : '1.0';
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

<script>
/**
 * Global Toast Notification Dispatcher
 */
function showToast(msg, type = 'success') {
  if (!msg) return;
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.textContent = msg;
  container.appendChild(toast);

  setTimeout(() => toast.classList.add('show'), 10);
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 250);
  }, 3200);
}
window.showToast = showToast;

/**
 * Global Header Telemetry Real-Time Synchronizer
 * Queries /api/telemetry and updates the header telemetry pills in real time.
 */
window.refreshHeaderTelemetry = async function() {
  try {
    const res = await fetch(`/api/telemetry?_t=${Date.now()}`, {
      cache: 'no-store',
      headers: {
        'Accept': 'application/json',
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache'
      }
    });
    if (!res.ok) return;

    const json = await res.json();
    const data = (json && json.data) ? json.data : json;
    if (!data) return;

    const gEl = document.getElementById('headerTotalGames');
    const oEl = document.getElementById('headerOwnedGames');
    const cEl = document.getElementById('headerTotalConsoles');

    if (gEl && data.total_games !== undefined) {
      gEl.textContent = Number(data.total_games).toLocaleString();
    }
    if (oEl && data.owned_games !== undefined) {
      oEl.textContent = Number(data.owned_games).toLocaleString();
    }
    if (cEl && data.total_consoles !== undefined) {
      cEl.textContent = Number(data.total_consoles).toLocaleString();
    }

    window.dispatchEvent(new CustomEvent('vault-telemetry-updated', { detail: data }));
  } catch (err) {
    console.debug('Header telemetry sync deferred:', err);
  }
};

// Auto-refresh when switching tabs / returning to window
window.addEventListener('focus', () => {
  if (typeof window.refreshHeaderTelemetry === 'function') {
    window.refreshHeaderTelemetry();
  }
});
</script>

<!-- Universal Web Components -->
<script src="/assets/js/components/data-grid.js?v=<?= $gridJsVer ?>"></script>
</body>
</html>