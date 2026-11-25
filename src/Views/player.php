<?php
/**
 * src/Views/player.php
 * Web Arcade Player View for RetroArch Emscripten WebAssembly.
 */

declare(strict_types=1);

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

// Strict Server-Side Device & Role Permission Guard (Player Read or Write access required)
\Vault\Auth\Auth::requireAccess('player', 'read');

$consoleId = (int)($_GET['console_id'] ?? ($_GET['consoleId'] ?? 0));
$gameId    = (int)($_GET['game_id'] ?? ($_GET['gameId'] ?? 0));
$fileId    = (int)($_GET['file_id'] ?? ($_GET['fileId'] ?? ($_GET['id'] ?? 0)));
$core      = trim((string)($_GET['core'] ?? ''));

$gameTitle = 'Web Arcade Player';
$resolvedWebCore = $core;
$resolvedFileId = $fileId;

// Resolve game and console records if gameId or id provided
$gameRepo = new \Vault\Repositories\GameRepository();
$gameCustomCore = '';
if ($gameId <= 0 && !empty($_GET['id'])) {
    $potentialGame = $gameRepo->getById((int)$_GET['id']);
    if ($potentialGame) {
        $gameId = (int)$potentialGame['id'];
        if ($consoleId <= 0 && !empty($potentialGame['console_id'])) {
            $consoleId = (int)$potentialGame['console_id'];
        }
        if (!empty($potentialGame['title'])) {
            $gameTitle = (string)$potentialGame['title'];
        }
        if (!empty($potentialGame['uses_custom_core']) && trim((string)($potentialGame['custom_core'] ?? '')) !== '') {
            $gameCustomCore = trim((string)$potentialGame['custom_core']);
        }
    }
}

if ($gameId > 0) {
    $gameData = $gameRepo->getById($gameId);
    if ($gameData) {
        if (!empty($gameData['title'])) {
            $gameTitle = (string)$gameData['title'];
        }
        if ($consoleId <= 0 && !empty($gameData['console_id'])) {
            $consoleId = (int)$gameData['console_id'];
        }
        if (!empty($gameData['uses_custom_core']) && trim((string)($gameData['custom_core'] ?? '')) !== '') {
            $gameCustomCore = trim((string)$gameData['custom_core']);
        }
    }
}

if ($resolvedWebCore === '' && $gameCustomCore !== '') {
    $resolvedWebCore = $gameCustomCore;
}

// 2. Resolve console record, web_core and button mappings
$consoleName = '';
$consoleMappings = [
    'gamepad'   => null,
    'cellphone' => null,
    'rog_ally'  => null,
];
if ($consoleId > 0) {
    $consoleRepo = new \Vault\Repositories\ConsoleRepository();
    $consoleData = $consoleRepo->getById($consoleId);
    if ($consoleData) {
        $consoleName = (string)($consoleData['name'] ?? '');
        if ($resolvedWebCore === '' && !empty($consoleData['web_core'])) {
            $resolvedWebCore = trim((string)$consoleData['web_core']);
        }
        $consoleMappings = [
            'gamepad'   => !empty($consoleData['mapping_gamepad_path']) ? '/' . ltrim($consoleData['mapping_gamepad_path'], '/') : null,
            'cellphone' => !empty($consoleData['mapping_cellphone_path']) ? '/' . ltrim($consoleData['mapping_cellphone_path'], '/') : null,
            'rog_ally'  => !empty($consoleData['mapping_rog_ally_path']) ? '/' . ltrim($consoleData['mapping_rog_ally_path'], '/') : null,
        ];
    }
}

// 3. Resolve main downloadable file for the game if fileId not given
if ($gameId > 0 && $resolvedFileId <= 0) {
    $downloadRepo = new \Vault\Repositories\DownloadRepository();
    $gameFiles = $downloadRepo->getByGameId($gameId);
    if (!empty($gameFiles)) {
        $mainFile = null;
        foreach ($gameFiles as $gf) {
            if (!empty($gf['is_main'])) {
                $mainFile = $gf;
                break;
            }
        }
        $mainFile = $mainFile ?? $gameFiles[0];
        $resolvedFileId = (int)$mainFile['id'];
        if ($gameTitle === 'Web Arcade Player' && !empty($mainFile['display_name'])) {
            $gameTitle = (string)$mainFile['display_name'];
        }
    }
} elseif ($resolvedFileId > 0 && $gameTitle === 'Web Arcade Player') {
    $downloadRepo = new \Vault\Repositories\DownloadRepository();
    $fileData = $downloadRepo->getById($resolvedFileId);
    if ($fileData) {
        $gameTitle = (string)($fileData['game_title'] ?? ($fileData['display_name'] ?? 'Web Arcade Player'));
        if ($gameId <= 0 && !empty($fileData['game_id'])) {
            $gameId = (int)$fileData['game_id'];
        }
    }
}

// Record browser play in user personal records (counts as downloaded and played)
$playerId = \Vault\Auth\Auth::id();
if ($playerId !== null && $playerId > 0 && $gameId > 0) {
    $playerGameRepo = new \Vault\Repositories\PlayerGameRepository();
    $playerGameRepo->recordBrowserPlay($playerId, $gameId);
}

// Automatically scan server BIOS files in /assets/emscripten/bios/
$biosDir = dirname(__DIR__, 2) . '/assets/emscripten/bios';
$serverBiosFiles = [];
if (is_dir($biosDir)) {
    $scanned = scandir($biosDir);
    if ($scanned !== false) {
        foreach ($scanned as $f) {
            if ($f !== '.' && $f !== '..' && is_file($biosDir . '/' . $f)) {
                $serverBiosFiles[] = $f;
            }
        }
    }
}

// Automatically scan server button remap files in /assets/emscripten/remaps/
$remapsDir = dirname(__DIR__, 2) . '/assets/emscripten/remaps';
$serverRemapFiles = [];
if (is_dir($remapsDir)) {
    $scannedRemaps = scandir($remapsDir);
    if ($scannedRemaps !== false) {
        foreach ($scannedRemaps as $rf) {
            if ($rf !== '.' && $rf !== '..' && str_ends_with(strtolower($rf), '.rmp') && is_file($remapsDir . '/' . $rf)) {
                $serverRemapFiles[] = $rf;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?= htmlspecialchars($gameTitle, ENT_QUOTES, 'UTF-8') ?> — Videogame Vault Web Arcade</title>
  <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    :root {
      --player-bg: #070a12;
      --player-panel: #0f172a;
      --player-border: #1e293b;
      --player-accent: #0284c7;
      --player-accent-light: #38bdf8;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html, body {
      width: 100% !important;
      height: 100% !important;
      margin: 0 !important;
      padding: 0 !important;
      background-color: var(--player-bg) !important;
      color: var(--text-main, #f8fafc);
      font-family: var(--font-sans, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif);
      overflow: hidden !important;
    }

    .player-layout {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      width: 100vw;
      height: 100vh;
      margin: 0 !important;
      padding: 0 !important;
      display: flex;
      flex-direction: column;
      background: radial-gradient(circle at 50% 20%, #172554 0%, #070a12 75%);
    }

    /* Top Application Bar */
    .player-header {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 44px;
      padding: 0 12px;
      background: rgba(15, 23, 42, 0.9);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--player-border);
      z-index: 20;
      flex-shrink: 0;
    }

    .header-left {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .back-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      height: 28px;
      padding: 0 10px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: var(--radius-md, 6px);
      color: var(--text-main, #f8fafc);
      text-decoration: none;
      font-size: 12px;
      font-weight: 500;
      line-height: 1;
      transition: all var(--transition-fast, 0.15s ease);
      cursor: pointer;
    }

    .back-btn:hover {
      background: rgba(56, 189, 248, 0.15);
      border-color: var(--player-accent-light);
      color: #fff;
    }

    .player-title-group {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .player-logo {
      width: 18px;
      height: 18px;
      object-fit: contain;
    }

    .player-title {
      font-size: 13px;
      font-weight: 600;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 6px;
      line-height: 1;
    }

    .badge-core {
      display: inline-flex;
      align-items: center;
      height: 22px;
      padding: 0 7px;
      border-radius: 9999px;
      font-size: 10.5px;
      font-family: var(--font-mono, monospace);
      font-weight: 600;
      background: rgba(2, 132, 199, 0.2);
      color: var(--player-accent-light);
      border: 1px solid rgba(56, 189, 248, 0.3);
      text-transform: uppercase;
      line-height: 1;
    }

    .badge-status {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      height: 22px;
      padding: 0 7px;
      border-radius: 9999px;
      font-size: 10.5px;
      background: rgba(16, 185, 129, 0.15);
      color: #34d399;
      border: 1px solid rgba(52, 211, 153, 0.3);
      font-weight: 500;
      line-height: 1;
    }

    .status-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #10b981;
      animation: pulse 1.8s infinite;
    }

    @keyframes pulse {
      0% { opacity: 0.4; transform: scale(0.9); }
      50% { opacity: 1; transform: scale(1.1); }
      100% { opacity: 0.4; transform: scale(0.9); }
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .action-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      height: 28px;
      padding: 0 9px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: var(--radius-md, 6px);
      color: var(--text-muted, #94a3b8);
      font-size: 12px;
      font-weight: 500;
      line-height: 1;
      cursor: pointer;
      transition: all var(--transition-fast, 0.15s ease);
    }

    .action-btn:hover {
      background: rgba(255, 255, 255, 0.1);
      color: #fff;
      border-color: rgba(255, 255, 255, 0.2);
    }

    /* Screen Stage / Arcade Frame */
    .stage-container {
      position: relative;
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 14px 20px;
      overflow: hidden;
      width: 100vw;
      height: calc(100vh - 44px);
    }

    .arcade-bezel {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      height: 100%;
      max-width: min(1000px, calc(100vw - 640px));
      max-height: calc(100vh - 68px);
      background: #000;
      border: 2px solid var(--player-border);
      border-radius: 8px;
      box-shadow: 0 12px 36px rgba(0, 0, 0, 0.7), 0 0 24px rgba(2, 132, 199, 0.15);
      overflow: hidden;
      margin: 0 auto;
    }

    /* HTML5 Canvas with Pixelated Rendering */
    #canvas {
      display: block;
      width: 100%;
      height: 100%;
      object-fit: contain;
      image-rendering: -moz-crisp-edges;
      image-rendering: -webkit-crisp-edges;
      image-rendering: pixelated;
      image-rendering: crisp-edges;
      background-color: #000;
      outline: none;
      cursor: default;
    }

    /* Loading Overlay */
    .loading-overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(11, 15, 25, 0.94);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 16px;
      z-index: 30;
      transition: opacity 0.4s ease, visibility 0.4s ease;
    }

    .loading-overlay.hidden {
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
    }

    .spinner-ring {
      width: 48px;
      height: 48px;
      border: 3px solid rgba(56, 189, 248, 0.15);
      border-top-color: var(--player-accent-light);
      border-radius: 50%;
      animation: spin 0.9s cubic-bezier(0.4, 0, 0.2, 1) infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    .loading-card {
      text-align: center;
      max-width: 420px;
      padding: 0 20px;
    }

    .loading-title {
      font-size: 15px;
      font-weight: 600;
      color: #fff;
      margin-bottom: 6px;
    }

    .loading-status {
      font-size: 13px;
      color: var(--player-accent-light);
      font-family: var(--font-mono, monospace);
      min-height: 20px;
    }

    .loading-subtext {
      font-size: 12px;
      color: var(--text-muted, #94a3b8);
      margin-top: 6px;
    }

    /* Error Display inside Overlay */
    .error-card {
      display: none;
      flex-direction: column;
      align-items: center;
      text-align: center;
      max-width: 440px;
      padding: 24px;
      background: rgba(30, 41, 59, 0.7);
      border: 1px solid rgba(239, 68, 68, 0.35);
      border-radius: var(--radius-lg, 10px);
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
    }

    .error-icon {
      font-size: 36px;
      margin-bottom: 10px;
    }

    .error-title {
      font-size: 15px;
      font-weight: 600;
      color: #f87171;
      margin-bottom: 8px;
    }

    .error-message {
      font-size: 13px;
      color: var(--text-main, #f8fafc);
      line-height: 1.5;
      margin-bottom: 16px;
      word-break: break-word;
    }

    .error-actions {
      display: flex;
      gap: 10px;
    }

    /* Centered Header Title Group */
    .header-center {
      position: absolute;
      left: 50%;
      top: 50%;
      transform: translate(-50%, -50%);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      max-width: 48vw;
      text-align: center;
      pointer-events: none;
      padding: 0;
      margin: 0;
    }

    .header-game-title {
      font-size: 13.5px;
      font-weight: 600;
      color: #fff;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      margin: 0;
      padding: 0;
      line-height: 1;
    }

    .header-console-divider {
      color: rgba(255, 255, 255, 0.3);
      font-size: 12px;
      user-select: none;
      line-height: 1;
      padding: 0 2px;
    }

    .header-console-name {
      font-size: 12.5px;
      font-weight: 500;
      color: var(--player-accent-light, #38bdf8);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      margin: 0;
      padding: 0;
      line-height: 1;
    }

    /* Button Mapping Modal */
    .mapping-modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.8);
      backdrop-filter: blur(8px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      padding: 20px;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.2s ease;
    }

    .mapping-modal-backdrop.active {
      opacity: 1;
      pointer-events: auto;
    }

    .mapping-modal-dialog {
      background: #0f172a;
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 12px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
      width: 100%;
      max-width: 820px;
      max-height: 88vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      transform: scale(0.96);
      transition: transform 0.2s ease;
    }

    .mapping-modal-backdrop.active .mapping-modal-dialog {
      transform: scale(1);
    }

    .mapping-modal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 18px;
      border-bottom: 1px solid var(--player-border, #1e293b);
      background: rgba(15, 23, 42, 0.98);
      gap: 12px;
    }

    .mapping-modal-header-left {
      display: flex;
      align-items: center;
      gap: 14px;
      flex-wrap: wrap;
    }

    .mapping-modal-title {
      font-size: 14px;
      font-weight: 600;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 6px;
      margin: 0;
    }

    .mapping-device-select {
      background: #1e293b;
      color: #f8fafc;
      border: 1px solid #334155;
      border-radius: 6px;
      padding: 5px 12px;
      font-size: 12px;
      font-weight: 500;
      cursor: pointer;
      outline: none;
      transition: border-color 0.15s ease;
    }

    .mapping-device-select:focus {
      border-color: var(--player-accent-light, #38bdf8);
    }

    .mapping-modal-close {
      background: transparent;
      border: none;
      color: #94a3b8;
      font-size: 22px;
      line-height: 1;
      cursor: pointer;
      padding: 4px 8px;
      border-radius: 4px;
      transition: color 0.15s ease, background 0.15s ease;
    }

    .mapping-modal-close:hover {
      color: #fff;
      background: rgba(255, 255, 255, 0.1);
    }

    .mapping-modal-body {
      padding: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 360px;
      max-height: 70vh;
      overflow-y: auto;
      background: #070a12;
    }

    .mapping-img {
      max-width: 100%;
      max-height: 65vh;
      object-fit: contain;
      border-radius: 8px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    }

    .mapping-empty-placeholder {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 12px;
      color: #94a3b8;
      padding: 40px 20px;
      text-align: center;
    }

    .mapping-empty-placeholder .icon {
      font-size: 42px;
      opacity: 0.85;
    }

    /* Fullscreen Mode Overrides */
    :fullscreen .player-header {
      display: none;
    }

    :fullscreen .stage-container {
      padding: 0;
    }

    :fullscreen .arcade-bezel {
      max-width: 100vw;
      max-height: 100vh;
      border: none;
      border-radius: 0;
    }

    :-webkit-full-screen .player-header {
      display: none;
    }

    :-webkit-full-screen .stage-container {
      padding: 0;
    }

    :-webkit-full-screen .arcade-bezel {
      max-width: 100vw;
      max-height: 100vh;
      border: none;
      border-radius: 0;
    }

    :fullscreen .player-info-box,
    :-webkit-full-screen .player-info-box {
      display: none !important;
    }

    /* Left Information Box / Controls & State Card */
    .player-info-box {
      position: absolute;
      left: 20px;
      top: 50%;
      transform: translateY(-50%);
      z-index: 10;
      width: 275px;
      max-width: 275px;
      max-height: calc(100vh - 72px);
      background: rgba(15, 23, 42, 0.88);
      backdrop-filter: blur(12px);
      border: 1px solid var(--player-border, #1e293b);
      border-radius: 8px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
      padding: 14px 16px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      overflow-y: auto;
      flex-shrink: 0;
      color: var(--text-main, #f8fafc);
      font-size: 11.5px;
      line-height: 1.45;
      user-select: text;
    }

    /* Sleek custom scrollbar for info box */
    .player-info-box::-webkit-scrollbar {
      width: 5px;
    }
    .player-info-box::-webkit-scrollbar-track {
      background: rgba(15, 23, 42, 0.5);
    }
    .player-info-box::-webkit-scrollbar-thumb {
      background: #334155;
      border-radius: 4px;
    }
    .player-info-box::-webkit-scrollbar-thumb:hover {
      background: #475569;
    }

    .info-section {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .info-header {
      display: flex;
      align-items: center;
      gap: 7px;
      font-size: 12px;
      font-weight: 700;
      color: #38bdf8;
      letter-spacing: 0.02em;
    }

    .info-header svg {
      flex-shrink: 0;
      color: #38bdf8;
    }

    .info-list {
      list-style: none;
      margin: 0;
      padding: 0;
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .info-list li {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      font-size: 11.5px;
      color: #cbd5e1;
      padding: 2px 0;
    }

    .info-label {
      color: #94a3b8;
    }

    .info-val kbd {
      display: inline-block;
      padding: 1px 6px;
      font-family: var(--font-mono, monospace);
      font-size: 11px;
      font-weight: 700;
      color: #38bdf8;
      background: rgba(2, 132, 199, 0.15);
      border: 1px solid rgba(56, 189, 248, 0.35);
      border-radius: 4px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
      white-space: nowrap;
    }

    .info-note {
      margin: 2px 0 0;
      font-size: 11px;
      line-height: 1.45;
      padding: 7px 9px;
      border-radius: 6px;
    }

    .warning-note {
      background: rgba(245, 158, 11, 0.1);
      border: 1px solid rgba(245, 158, 11, 0.25);
      color: #fcd34d;
    }

    .highlight-note {
      background: rgba(56, 189, 248, 0.1);
      border: 1px solid rgba(56, 189, 248, 0.25);
      color: #bae6fd;
    }

    .info-divider {
      height: 1px;
      background: var(--player-border, #1e293b);
      margin: 2px 0;
    }

    @media (max-width: 1200px) {
      .player-info-box {
        display: none;
      }
      .arcade-bezel {
        max-width: 960px;
      }
    }

    /* Tablet & Mobile Non-Fullscreen Responsive Layout */
    @media (max-width: 1024px) {
      .player-layout {
        height: 100vh;
        height: 100dvh;
      }

      .player-header {
        gap: 8px;
        padding: 0 10px;
      }

      .header-left {
        flex-shrink: 0;
        gap: 8px;
      }

      /* Prevent absolute center title from overlapping left badges and right action buttons */
      .header-center {
        position: static;
        transform: none;
        flex: 1 1 auto;
        min-width: 0;
        max-width: none;
        justify-content: center;
        overflow: hidden;
        padding: 0 4px;
      }

      .header-right {
        flex-shrink: 0;
        gap: 5px;
      }

      #statusBadge {
        display: none !important;
      }

      .stage-container {
        width: 100%;
        height: auto;
        flex: 1 1 auto;
        min-height: 0;
        padding: 8px 10px;
      }

      .arcade-bezel {
        max-width: 100%;
        max-height: 100%;
      }
    }

    @media (max-width: 768px) {
      .player-header {
        height: 46px;
        padding: 0 8px;
        gap: 6px;
      }

      .player-logo,
      #coreBadge,
      .header-console-divider,
      .header-console-name {
        display: none !important;
      }

      #stateSyncBadge {
        font-size: 9.5px;
        height: 20px;
        padding: 0 6px;
        white-space: nowrap;
      }

      .header-game-title {
        font-size: 12.5px;
      }

      .back-btn,
      .action-btn {
        height: 30px;
        padding: 0 8px;
        font-size: 11.5px;
        white-space: nowrap;
        flex-shrink: 0;
      }

      .stage-container {
        padding: 6px;
      }

      .mapping-modal-backdrop {
        padding: 10px;
      }

      .mapping-modal-header {
        padding: 10px 12px;
      }

      .mapping-modal-body {
        padding: 12px;
        min-height: 220px;
      }
    }

    @media (max-width: 540px) {
      .player-title-group {
        display: none !important;
      }

      .header-btn-text {
        display: none;
      }

      .action-btn {
        width: 32px;
        height: 30px;
        padding: 0;
      }

      .back-btn {
        padding: 0 8px;
        font-size: 11px;
      }

      .header-game-title {
        font-size: 12px;
      }
    }

    /* Pseudo-Fullscreen Fallback (for iOS Safari where native Fullscreen API on div is unavailable) */
    .player-layout.is-fullscreen-active .player-header,
    .player-layout.is-fullscreen-active .player-info-box {
      display: none !important;
    }

    .player-layout.is-fullscreen-active .stage-container {
      padding: 0;
      height: 100vh;
    }

    .player-layout.is-fullscreen-active .arcade-bezel {
      max-width: 100vw;
      max-height: 100vh;
      border: none;
      border-radius: 0;
    }

    /* Mobile Fullscreen Overlay (ONLY visible when in Fullscreen on a Mobile/Touch device) */
    .mobile-fs-overlay {
      display: none;
      position: absolute;
      inset: 0;
      z-index: 25;
      pointer-events: none;
      user-select: none;
      -webkit-user-select: none;
      touch-action: none;
    }

    .player-layout.is-mobile-device:fullscreen .mobile-fs-overlay,
    .player-layout.is-mobile-device:-webkit-full-screen .mobile-fs-overlay,
    .player-layout.is-mobile-device.is-fullscreen-active .mobile-fs-overlay {
      display: block;
    }

    /* Top-Left & Top-Right Floating Icon Stacks (Save + Pad on Left, Load + Exit FS on Right) */
    .mobile-fs-corner-stack {
      position: absolute;
      top: max(14px, env(safe-area-inset-top, 14px));
      display: flex;
      flex-direction: column;
      gap: 10px;
      pointer-events: auto;
      z-index: 28;
    }

    .mobile-fs-corner-left {
      left: max(16px, env(safe-area-inset-left, 16px));
    }

    .mobile-fs-corner-right {
      right: max(16px, env(safe-area-inset-right, 16px));
    }

    .mobile-util-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      padding: 0;
      border-radius: 50%;
      border: 1.5px solid rgba(255, 255, 255, 0.22);
      background: rgba(15, 23, 42, 0.65);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.5);
      color: #f8fafc;
      cursor: pointer;
      touch-action: manipulation;
      transition: background 0.12s ease, transform 0.08s ease, border-color 0.12s ease, opacity 0.12s ease;
    }

    .mobile-util-btn:active {
      transform: scale(0.92);
      background: rgba(56, 189, 248, 0.35);
      border-color: #38bdf8;
    }

    .mobile-util-btn.btn-save-state {
      border-color: rgba(16, 185, 129, 0.5);
      color: #6ee7b7;
    }

    .mobile-util-btn.btn-load-state {
      border-color: rgba(56, 189, 248, 0.5);
      color: #7dd3fc;
    }

    .mobile-util-btn.btn-pad-toggle {
      opacity: 0.55;
      color: #94a3b8;
    }

    .mobile-util-btn.btn-pad-toggle.is-active {
      opacity: 1;
      background: rgba(2, 132, 199, 0.38);
      border-color: rgba(56, 189, 248, 0.7);
      color: #fff;
    }

    .mobile-fs-toast {
      position: absolute;
      top: max(16px, env(safe-area-inset-top, 16px));
      left: 50%;
      transform: translateX(-50%);
      padding: 5px 14px;
      border-radius: 9999px;
      background: rgba(15, 23, 42, 0.88);
      border: 1px solid rgba(52, 211, 153, 0.5);
      color: #34d399;
      font-size: 11.5px;
      font-weight: 600;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.2s ease, visibility 0.2s ease;
      pointer-events: none;
      white-space: nowrap;
      z-index: 28;
    }

    .mobile-fs-toast.visible {
      opacity: 1;
      visibility: visible;
    }

    /* Virtual Touch Controller Container */
    .mobile-touch-controls {
      position: absolute;
      inset: 0;
      pointer-events: none;
    }

    .mobile-touch-controls.is-hidden {
      display: none !important;
    }

    /* Left Cluster: Shoulder L + Circular Pizza D-Pad */
    .touch-left-cluster {
      position: absolute;
      left: max(16px, env(safe-area-inset-left, 16px));
      bottom: max(22px, env(safe-area-inset-bottom, 22px));
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 14px;
      pointer-events: none;
    }

    /* Right Cluster: Shoulder R + Action Diamond */
    .touch-right-cluster {
      position: absolute;
      right: max(16px, env(safe-area-inset-right, 16px));
      bottom: max(22px, env(safe-area-inset-bottom, 22px));
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 14px;
      pointer-events: none;
    }

    /* Bottom Center Cluster: Select & Start */
    .touch-center-cluster {
      position: absolute;
      bottom: max(18px, env(safe-area-inset-bottom, 18px));
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      align-items: center;
      gap: 14px;
      pointer-events: none;
    }

    .touch-btn {
      pointer-events: auto;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(15, 23, 42, 0.45);
      border: 1.5px solid rgba(255, 255, 255, 0.26);
      color: rgba(248, 250, 252, 0.88);
      font-weight: 700;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
      touch-action: none;
      user-select: none;
      -webkit-user-select: none;
      transition: background 0.06s ease, transform 0.06s ease, border-color 0.06s ease;
    }

    .touch-btn.pressed {
      background: rgba(56, 189, 248, 0.48);
      border-color: #38bdf8;
      color: #fff;
      transform: scale(0.93);
    }

    .touch-shoulder-btn {
      width: 68px;
      height: 32px;
      border-radius: 10px;
      font-size: 12px;
      letter-spacing: 0.04em;
    }

    /* Circular Pizza-Sliced D-Pad (Zero gaps between directions) */
    .touch-dpad-wheel {
      position: relative;
      width: 150px;
      height: 150px;
      border-radius: 50%;
      background: rgba(15, 23, 42, 0.42);
      border: 2px solid rgba(255, 255, 255, 0.28);
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.45);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
      overflow: hidden;
      pointer-events: auto;
      touch-action: none;
      user-select: none;
      -webkit-user-select: none;
    }

    /* Diagonal Pizza Slice Divider Lines */
    .touch-dpad-wheel::before,
    .touch-dpad-wheel::after {
      content: '';
      position: absolute;
      top: 50%;
      left: -10%;
      width: 120%;
      height: 1.5px;
      background: rgba(255, 255, 255, 0.24);
      pointer-events: none;
      z-index: 3;
    }

    .touch-dpad-wheel::before {
      transform: translateY(-50%) rotate(45deg);
    }

    .touch-dpad-wheel::after {
      transform: translateY(-50%) rotate(-45deg);
    }

    .dpad-pizza-slice {
      position: absolute;
      inset: 0;
      pointer-events: none;
      transition: background 0.05s ease, color 0.05s ease;
      color: rgba(248, 250, 252, 0.85);
      font-size: 16px;
      font-weight: 700;
      z-index: 1;
    }

    .dpad-pizza-slice.pressed {
      background: rgba(56, 189, 248, 0.52);
      color: #ffffff;
    }

    .dpad-slice-up {
      clip-path: polygon(0 0, 100% 0, 50% 50%);
      -webkit-clip-path: polygon(0 0, 100% 0, 50% 50%);
    }

    .dpad-slice-right {
      clip-path: polygon(100% 0, 100% 100%, 50% 50%);
      -webkit-clip-path: polygon(100% 0, 100% 100%, 50% 50%);
    }

    .dpad-slice-down {
      clip-path: polygon(100% 100%, 0 100%, 50% 50%);
      -webkit-clip-path: polygon(100% 100%, 0 100%, 50% 50%);
    }

    .dpad-slice-left {
      clip-path: polygon(0 100%, 0 0, 50% 50%);
      -webkit-clip-path: polygon(0 100%, 0 0, 50% 50%);
    }

    .dpad-slice-arrow {
      position: absolute;
      line-height: 1;
      pointer-events: none;
    }

    .dpad-slice-up .dpad-slice-arrow {
      top: 15px;
      left: 50%;
      transform: translateX(-50%);
    }

    .dpad-slice-right .dpad-slice-arrow {
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
    }

    .dpad-slice-down .dpad-slice-arrow {
      bottom: 15px;
      left: 50%;
      transform: translateX(-50%);
    }

    .dpad-slice-left .dpad-slice-arrow {
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
    }

    .dpad-center-hub {
      position: absolute;
      top: 50%;
      left: 50%;
      width: 28px;
      height: 28px;
      transform: translate(-50%, -50%);
      border-radius: 50%;
      background: rgba(15, 23, 42, 0.85);
      border: 1.5px solid rgba(255, 255, 255, 0.25);
      pointer-events: none;
      z-index: 4;
    }

    /* 3x3 Grid Diamond Action Pad (X top, Y left, A right, B bottom) */
    .touch-actions {
      display: grid;
      grid-template-columns: repeat(3, 48px);
      grid-template-rows: repeat(3, 48px);
      gap: 3px;
    }

    .touch-action-btn {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      font-size: 14px;
    }

    .touch-action-x { grid-column: 2; grid-row: 1; border-color: rgba(56, 189, 248, 0.45); }
    .touch-action-y { grid-column: 1; grid-row: 2; border-color: rgba(52, 211, 153, 0.45); }
    .touch-action-a { grid-column: 3; grid-row: 2; border-color: rgba(248, 113, 113, 0.45); }
    .touch-action-b { grid-column: 2; grid-row: 3; border-color: rgba(251, 191, 36, 0.45); }

    .touch-pill-btn {
      height: 28px;
      padding: 0 14px;
      border-radius: 9999px;
      font-size: 10.5px;
      letter-spacing: 0.06em;
    }
  </style>
</head>
<body>
  <div class="player-layout" id="playerWrapper">
    <!-- Header -->
    <header class="player-header">
      <div class="header-left">
        <button type="button" class="back-btn" onclick="handleExit()" title="Close Player">
          <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14">
            <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd"/>
          </svg>
          Exit
        </button>

        <div class="player-title-group">
          <img src="/assets/favicon.svg" alt="Vault Icon" class="player-logo">
          <span class="badge-core" id="coreBadge">CORE: —</span>
          <span class="badge-status" id="statusBadge" style="display: none;">
            <span class="status-dot"></span>
            <span>Running</span>
          </span>
          <span class="badge-core" id="stateSyncBadge" style="display: none; border-color: rgba(56, 189, 248, 0.4); color: #38bdf8;">
            Slot 0 Ready
          </span>
        </div>
      </div>

      <!-- Centered Game and Console Name -->
      <div class="header-center" id="headerCenter">
        <h1 class="header-game-title" id="headerGameTitle"><?= htmlspecialchars($gameTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <span class="header-console-divider" id="headerConsoleDivider" <?= empty($consoleName) ? 'style="display: none;"' : '' ?>>•</span>
        <span class="header-console-name" id="headerConsoleName"><?= htmlspecialchars($consoleName, ENT_QUOTES, 'UTF-8') ?></span>
      </div>

      <div class="header-right">
        <button type="button" class="action-btn" id="btnShowMapping" onclick="openMappingModal()" title="View Console Button Mapping" aria-label="Controls">
          <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14">
            <path d="M21 6H3c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-10 7H8v3H6v-3H3v-2h3V8h2v3h3v2zm4.5 2c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm4-3c-.83 0-1.5-.67-1.5-1.5S18.67 9 19.5 9s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
          </svg>
          <span class="header-btn-text">Controls</span>
        </button>
        <button type="button" class="action-btn" title="Restart Game" aria-label="Reset" onclick="location.reload()">
          <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14">
            <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.75a.75.75 0 00-.75.75v4.482a.75.75 0 001.5 0v-2.433l.312.311a7 7 0 1010.5-5.265.75.75 0 00-.75.75 1.5 1.5 0 01-1.5 1.5z" clip-rule="evenodd"/>
          </svg>
          <span class="header-btn-text">Reset</span>
        </button>
        <button type="button" class="action-btn" title="Toggle Fullscreen" aria-label="Fullscreen" onclick="toggleFullscreen()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
            <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
          </svg>
          <span class="header-btn-text">Fullscreen</span>
        </button>
      </div>
    </header>

    <!-- Main Arcade Stage -->
    <main class="stage-container">
      <!-- Information Box to the Left of Emulator Screen -->
      <aside class="player-info-box" id="playerInfoBox" aria-label="Controls and Game State Information">
        <div class="info-section">
          <div class="info-header">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
              <rect x="2" y="4" width="20" height="16" rx="2"></rect>
              <path d="M6 8h.001M10 8h.001M14 8h.001M18 8h.001M6 12h.001M10 12h.001M14 12h.001M18 12h.001M6 16h12"></path>
            </svg>
            <span>Key Mappings (No Gamepad)</span>
          </div>
          <ul class="info-list">
            <li><span class="info-label">Directional Pad (D-Pad)</span> <span class="info-val"><kbd>Arrow Keys</kbd></span></li>
            <li><span class="info-label">B Button</span> <span class="info-val"><kbd>Z</kbd></span></li>
            <li><span class="info-label">A Button</span> <span class="info-val"><kbd>X</kbd></span></li>
            <li><span class="info-label">Y Button</span> <span class="info-val"><kbd>A</kbd></span></li>
            <li><span class="info-label">X Button</span> <span class="info-val"><kbd>S</kbd></span></li>
            <li><span class="info-label">L Button</span> <span class="info-val"><kbd>Q</kbd></span></li>
            <li><span class="info-label">R Button</span> <span class="info-val"><kbd>W</kbd></span></li>
            <li><span class="info-label">Select</span> <span class="info-val"><kbd>Right Shift</kbd></span></li>
            <li><span class="info-label">Start</span> <span class="info-val"><kbd>Enter</kbd></span></li>
          </ul>
          <p class="info-note warning-note">
            More complex gamepads will require additional buttons not supported on keyboard mapping. It is highly advised to use a gamepad.
          </p>
        </div>

        <div class="info-divider"></div>

        <div class="info-section">
          <div class="info-header">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
              <polyline points="17 21 17 13 7 13 7 21"></polyline>
              <polyline points="7 3 7 8 15 8"></polyline>
            </svg>
            <span>Game States</span>
          </div>
          <ul class="info-list">
            <li><span class="info-label">Save State</span> <span class="info-val"><kbd>F2</kbd></span></li>
            <li><span class="info-label">Load State</span> <span class="info-val"><kbd>F4</kbd></span></li>
            <li><span class="info-label">Next Save State Slot</span> <span class="info-val"><kbd>F7</kbd></span></li>
            <li><span class="info-label">Previous Save State Slot</span> <span class="info-val"><kbd>F6</kbd></span></li>
          </ul>
          <p class="info-note highlight-note">
            <strong>Slot 0 Persistent Save:</strong> Save States in <strong>Slot 0</strong> (the default slot) are automatically synced to your personal account and persist across sessions. Additional slots (1+) remain temporary for the current session only.
          </p>
        </div>
      </aside>

      <!-- Emulator Screen Bezel -->
      <div class="arcade-bezel">
        <!-- Emscripten HTML5 Canvas -->
        <canvas id="canvas" oncontextmenu="event.preventDefault()" tabindex="0"></canvas>

        <!-- Mobile Fullscreen Overlay (Save/Load State + Optional Virtual Gamepad) -->
        <div class="mobile-fs-overlay" id="mobileFsOverlay" aria-label="Mobile Fullscreen Controls">
          <!-- Top-Left Icon Stack: Save State (top) + Gamepad Toggle (under it) -->
          <div class="mobile-fs-corner-stack mobile-fs-corner-left">
            <button type="button" class="mobile-util-btn btn-save-state" onclick="triggerMobileSaveState(event)" title="Save State (Slot 0)" aria-label="Save State (Slot 0)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
              </svg>
            </button>
            <button type="button" class="mobile-util-btn btn-pad-toggle is-active" id="btnMobileTouchToggle" onclick="toggleMobileTouchPad(event)" title="Show or Hide Touch Gamepad" aria-label="Show or Hide Touch Gamepad">
              <svg viewBox="0 0 24 24" fill="currentColor" width="19" height="19">
                <path d="M21 6H3c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-10 7H8v3H6v-3H3v-2h3V8h2v3h3v2zm4.5 2c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm4-3c-.83 0-1.5-.67-1.5-1.5S18.67 9 19.5 9s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
              </svg>
            </button>
          </div>

          <!-- Top-Right Icon Stack: Load State (top) + Exit Fullscreen (under it) -->
          <div class="mobile-fs-corner-stack mobile-fs-corner-right">
            <button type="button" class="mobile-util-btn btn-load-state" onclick="triggerMobileLoadState(event)" title="Load State (Slot 0)" aria-label="Load State (Slot 0)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
              </svg>
            </button>
            <button type="button" class="mobile-util-btn" onclick="toggleFullscreen()" title="Exit Fullscreen" aria-label="Exit Fullscreen">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                <path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"/>
              </svg>
            </button>
          </div>

          <div class="mobile-fs-toast" id="mobileFsToast">Slot 0 Saved ✓</div>

          <!-- Toggleable On-Screen Virtual Touch Gamepad -->
          <div class="mobile-touch-controls" id="mobileTouchControls">
            <div class="touch-left-cluster">
              <button type="button" class="touch-btn touch-shoulder-btn" data-key="q" data-code="KeyQ" data-keycode="81">L</button>
              <div class="touch-dpad-wheel" id="touchDpadWheel" aria-label="Circular Directional Pad">
                <div class="dpad-pizza-slice dpad-slice-up" id="dpadSliceUp"><span class="dpad-slice-arrow">▲</span></div>
                <div class="dpad-pizza-slice dpad-slice-right" id="dpadSliceRight"><span class="dpad-slice-arrow">▶</span></div>
                <div class="dpad-pizza-slice dpad-slice-down" id="dpadSliceDown"><span class="dpad-slice-arrow">▼</span></div>
                <div class="dpad-pizza-slice dpad-slice-left" id="dpadSliceLeft"><span class="dpad-slice-arrow">◀</span></div>
                <div class="dpad-center-hub"></div>
              </div>
            </div>

            <div class="touch-center-cluster">
              <button type="button" class="touch-btn touch-pill-btn" data-key="Shift" data-code="ShiftRight" data-keycode="16" data-location="2">SELECT</button>
              <button type="button" class="touch-btn touch-pill-btn" data-key="Enter" data-code="Enter" data-keycode="13">START</button>
            </div>

            <div class="touch-right-cluster">
              <button type="button" class="touch-btn touch-shoulder-btn" data-key="w" data-code="KeyW" data-keycode="87">R</button>
              <div class="touch-actions">
                <button type="button" class="touch-btn touch-action-btn touch-action-x" data-key="s" data-code="KeyS" data-keycode="83">X</button>
                <button type="button" class="touch-btn touch-action-btn touch-action-y" data-key="a" data-code="KeyA" data-keycode="65">Y</button>
                <button type="button" class="touch-btn touch-action-btn touch-action-a" data-key="x" data-code="KeyX" data-keycode="88">A</button>
                <button type="button" class="touch-btn touch-action-btn touch-action-b" data-key="z" data-code="KeyZ" data-keycode="90">B</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Loading & Status Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
          <div class="spinner-ring" id="loadingSpinner"></div>
          
          <div class="loading-card" id="loadingCard">
            <h2 class="loading-title">Launching Web Arcade</h2>
            <div class="loading-status" id="loadingStatusText">Initializing runtime...</div>
            <p class="loading-subtext">Compiling WebAssembly core and streaming game ROM...</p>
          </div>

          <!-- Error Alert Card (Hidden by default) -->
          <div class="error-card" id="errorCard">
            <div class="error-icon">⚠️</div>
            <h3 class="error-title" id="errorTitle">Failed to Start Emulation</h3>
            <p class="error-message" id="errorMessage">An unexpected error occurred while loading the game.</p>
            <div class="error-actions">
              <button type="button" class="action-btn" onclick="location.reload()">Retry</button>
              <button type="button" class="action-btn" onclick="handleExit()">Exit</button>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Button Mapping Modal Popup -->
  <div class="mapping-modal-backdrop" id="mappingModal" onclick="handleMappingBackdropClick(event)">
    <div class="mapping-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="mappingModalTitle">
      <div class="mapping-modal-header">
        <div class="mapping-modal-header-left">
          <h2 class="mapping-modal-title" id="mappingModalTitle">Button Mapping — <?= htmlspecialchars($consoleName ?: 'Console', ENT_QUOTES, 'UTF-8') ?></h2>
          <select class="mapping-device-select" id="mappingDeviceSelect" onchange="renderMappingImage(this.value)" aria-label="Select Device Layout">
            <option value="gamepad" selected>Gamepad</option>
            <option value="cellphone">Cellphone Adapter</option>
            <option value="rog_ally">ROG Ally</option>
          </select>
        </div>
        <button type="button" class="mapping-modal-close" onclick="closeMappingModal()" aria-label="Close button mapping modal">&times;</button>
      </div>
      <div class="mapping-modal-body" id="mappingModalBody">
        <!-- Dynamically rendered -->
      </div>
    </div>
  </div>

  <!-- Optional Core Metadata Lookup -->
  <script src="/assets/emscripten/core_list.js" onerror="console.info('core_list.js not present, using raw core identifiers.');"></script>

  <script>
    /**
     * Videogame Vault — Web Arcade Container
     * Powered by Libretro / RetroArch Emscripten WebAssembly
     */

    // 1. Parse URL Query Parameters
    const urlParams = new URLSearchParams(window.location.search);
    let consoleIdParam = (urlParams.get('console_id') || urlParams.get('consoleId') || '<?= $consoleId > 0 ? (int)$consoleId : '' ?>').trim();
    let gameIdParam = (urlParams.get('game_id') || urlParams.get('gameId') || urlParams.get('id') || '<?= $gameId > 0 ? (int)$gameId : '' ?>').trim();
    let coreParam = (urlParams.get('core') || '<?= addslashes($resolvedWebCore) ?>').trim().toLowerCase();
    let fileIdParam = (urlParams.get('file_id') || urlParams.get('fileId') || '<?= $resolvedFileId > 0 ? (int)$resolvedFileId : '' ?>').trim();

    // Server-scanned BIOS files from /assets/emscripten/bios/
    window.serverBiosFiles = <?= json_encode($serverBiosFiles, JSON_UNESCAPED_SLASHES) ?>;

    // Server-scanned button remap files from /assets/emscripten/remaps/
    window.serverRemapFiles = <?= json_encode($serverRemapFiles, JSON_UNESCAPED_SLASHES) ?>;

    // Server-preloaded console metadata & mappings
    window.consoleName = <?= json_encode($consoleName, JSON_UNESCAPED_SLASHES) ?>;
    window.consoleMappings = <?= json_encode($consoleMappings, JSON_UNESCAPED_SLASHES) ?>;

    // Server-preloaded core info
    window.resolvedWebCore = <?= json_encode($resolvedWebCore, JSON_UNESCAPED_SLASHES) ?>;

    let currentMappings = window.consoleMappings || {};
    let currentConsoleName = window.consoleName || 'Console';

    function escapeHtml(str) {
      if (!str) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    /**
     * Updates the generic footer status indicator
     * status: 'loading' | 'running' | 'error'
     */
    function updateFooterStatus(status, text) {
      // Footer status removed per UX design
    }

    /**
     * Opens the console button mapping popup
     */
    function openMappingModal() {
      const modal = document.getElementById('mappingModal');
      const select = document.getElementById('mappingDeviceSelect');
      const title = document.getElementById('mappingModalTitle');
      if (title) {
        title.textContent = `Button Mapping — ${currentConsoleName || 'Console'}`;
      }
      if (select) {
        select.value = 'gamepad';
      }
      renderMappingImage('gamepad');
      if (modal) {
        modal.classList.add('active');
      }
    }

    /**
     * Closes the console button mapping popup
     */
    function closeMappingModal() {
      const modal = document.getElementById('mappingModal');
      if (modal) {
        modal.classList.remove('active');
      }
      if (canvas) {
        canvas.focus();
      }
    }

    function handleMappingBackdropClick(event) {
      if (event.target === document.getElementById('mappingModal')) {
        closeMappingModal();
      }
    }

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        const modal = document.getElementById('mappingModal');
        if (modal && modal.classList.contains('active')) {
          closeMappingModal();
        }
      }
    });

    /**
     * Renders mapping image for the selected device layout
     */
    function renderMappingImage(deviceType) {
      const body = document.getElementById('mappingModalBody');
      if (!body) return;

      const deviceLabels = {
        gamepad: 'Gamepad',
        cellphone: 'Cellphone Adapter',
        rog_ally: 'ROG Ally'
      };

      const path = currentMappings[deviceType];
      if (path) {
        body.innerHTML = `
          <img src="${escapeHtml(path)}" alt="${escapeHtml(currentConsoleName)} - ${escapeHtml(deviceLabels[deviceType] || deviceType)}" class="mapping-img" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'mapping-empty-placeholder\\'><span class=\\'icon\\'>⚠️</span><span>Failed to load mapping image.</span></div>';">
        `;
      } else {
        body.innerHTML = `
          <div class="mapping-empty-placeholder">
            <span class="icon">🎮</span>
            <span>No button mapping image registered for <strong>${escapeHtml(deviceLabels[deviceType] || deviceType)}</strong>.</span>
          </div>
        `;
      }
    }

    // DOM Elements
    const canvas = document.getElementById('canvas');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const loadingCard = document.getElementById('loadingCard');
    const loadingStatusText = document.getElementById('loadingStatusText');
    const errorCard = document.getElementById('errorCard');
    const errorTitle = document.getElementById('errorTitle');
    const errorMessage = document.getElementById('errorMessage');
    const coreBadge = document.getElementById('coreBadge');
    const statusBadge = document.getElementById('statusBadge');
    const stateSyncBadge = document.getElementById('stateSyncBadge');

    // Slot 0 Save State Persistence State
    let activeRomBaseName = '';
    let activeVfsRef = null;
    let lastSyncedStateHash = null;
    let isSyncingState = false;
    let stateWatcherInterval = null;
    let stateBadgeResetTimer = null;

    /**
     * Updates the header Slot 0 badge to give visual feedback on restore/save
     */
    function updateStateSyncBadge(text, highlight = false, autoHideMs = 0) {
      if (!stateSyncBadge) return;
      if (stateBadgeResetTimer) {
        clearTimeout(stateBadgeResetTimer);
        stateBadgeResetTimer = null;
      }
      stateSyncBadge.textContent = text;
      stateSyncBadge.style.display = 'inline-flex';
      if (highlight) {
        stateSyncBadge.style.background = 'rgba(16, 185, 129, 0.18)';
        stateSyncBadge.style.borderColor = 'rgba(16, 185, 129, 0.5)';
        stateSyncBadge.style.color = '#34d399';
      } else {
        stateSyncBadge.style.background = 'rgba(2, 132, 199, 0.15)';
        stateSyncBadge.style.borderColor = 'rgba(56, 189, 248, 0.4)';
        stateSyncBadge.style.color = '#38bdf8';
      }
      if (autoHideMs > 0) {
        stateBadgeResetTimer = setTimeout(() => {
          stateSyncBadge.textContent = 'Slot 0 Synced';
          stateSyncBadge.style.background = 'rgba(2, 132, 199, 0.15)';
          stateSyncBadge.style.borderColor = 'rgba(56, 189, 248, 0.4)';
          stateSyncBadge.style.color = '#38bdf8';
        }, autoHideMs);
      }
    }

    /**
     * Fast FNV-1a 32-bit + length hash for Uint8Array to reliably detect new Slot 0 saves in VFS
     */
    function computeStateHash(bytes) {
      if (!bytes || !bytes.byteLength) return '0:0';
      let h = 0x811c9dc5;
      const len = bytes.byteLength;
      const step = len > 65536 ? Math.floor(len / 32768) : 1;
      for (let i = 0; i < len; i += step) {
        h ^= bytes[i];
        h = Math.imul(h, 0x01000193) >>> 0;
      }
      return `${len}:${h.toString(16)}`;
    }

    /**
     * Collects all known internal library_name casing aliases for a core
     */
    function getCoreCasingAliases(coreName) {
      const lower = (coreName || '').trim().toLowerCase();
      const aliases = new Set([lower, coreName]);
      const knownCoreMap = {
        fceumm: ['FCEUmm', 'fceumm'],
        gearcoleco: ['Gearcoleco', 'gearcoleco'],
        gearsystem: ['Gearsystem', 'gearsystem'],
        o2em: ['O2EM', 'o2em'],
        tia: ['tia', 'Stella'],
        a5200: ['a5200', 'Atari800']
      };
      if (knownCoreMap[lower]) {
        knownCoreMap[lower].forEach(a => aliases.add(a));
      }
      if (window.libretroCores && window.libretroCores[lower]) {
        const title = window.libretroCores[lower];
        const match = title.match(/\(([^)]+)\)/);
        if (match && match[1]) {
          aliases.add(match[1].trim());
        }
      }
      return Array.from(aliases).filter(Boolean);
    }

    /**
     * Returns all candidate VFS directories where RetroArch may read or write save states
     */
    function getCandidateStateDirs(coreName) {
      const baseDirs = [
        '/home/web_user/retroarch/userdata/states',
        '/home/web_user/userdata/states'
      ];
      const dirs = [...baseDirs];
      const aliases = getCoreCasingAliases(coreName);
      baseDirs.forEach(base => {
        aliases.forEach(alias => {
          dirs.push(`${base}/${alias}`);
        });
      });
      return dirs;
    }

    /**
     * Ensures a directory path exists in Emscripten VFS
     */
    function ensureVfsDirectory(fs, dirPath) {
      if (!fs || !dirPath || dirPath === '/') return;
      if (typeof fs.mkdirTree === 'function') {
        try { fs.mkdirTree(dirPath); return; } catch (_) {}
      }
      const segments = dirPath.split('/').filter(Boolean);
      let cur = '';
      for (const s of segments) {
        cur += '/' + s;
        try {
          const st = fs.stat(cur);
          if (st) continue;
        } catch (_) {}
        try { fs.mkdir(cur); } catch (_) {}
      }
    }

    /**
     * Writes Slot 0 binary bytes into all candidate VFS state locations
     */
    function writeSlot0ToVfs(fs, romBaseName, coreName, stateBytes, skipPath = null) {
      if (!fs || !romBaseName || !stateBytes || stateBytes.byteLength <= 64) return;
      const slot0Filename = `${romBaseName}.state`;
      const targetDirs = getCandidateStateDirs(coreName);
      targetDirs.forEach(dir => {
        const fullPath = `${dir}/${slot0Filename}`;
        if (skipPath && fullPath === skipPath) return;
        ensureVfsDirectory(fs, dir);
        try {
          if (typeof fs.writeFile === 'function') {
            fs.writeFile(fullPath, stateBytes);
          } else if (typeof fs.createDataFile === 'function') {
            try { fs.unlink(fullPath); } catch (_) {}
            fs.createDataFile(dir, slot0Filename, stateBytes, true, true, false);
          }
        } catch (err) {
          console.warn(`[WebPlayer State] Could not write Slot 0 state at ${fullPath}:`, err);
        }
      });
    }

    /**
     * Fetches the authenticated user's saved Slot 0 state from the server and mounts it in VFS before boot
     */
    async function restoreSlot0StateFromServer(fs, romBaseName, coreName) {
      if (!fs || !romBaseName || !consoleIdParam || !gameIdParam) return;
      try {
        updateLoadingStatus('Checking for saved Slot 0 game state...');
        const url = `/api/player/state?console_id=${encodeURIComponent(consoleIdParam)}&game_id=${encodeURIComponent(gameIdParam)}&_t=${Date.now()}`;
        const res = await fetch(url, { cache: 'no-store' });
        if (res.status === 204 || res.status === 404) {
          console.info('[WebPlayer State] No existing Slot 0 save state on server for this user/game.');
          return;
        }
        if (!res.ok) {
          console.warn(`[WebPlayer State] Failed to check Slot 0 save state (HTTP ${res.status}).`);
          return;
        }
        const buf = await res.arrayBuffer();
        if (!buf || buf.byteLength <= 64) {
          console.warn('[WebPlayer State] Ignored empty or invalid state payload from server.');
          return;
        }
        const stateBytes = new Uint8Array(buf);
        lastSyncedStateHash = computeStateHash(stateBytes);
        writeSlot0ToVfs(fs, romBaseName, coreName, stateBytes);
        updateStateSyncBadge('Slot 0 Ready (F4)');
        console.info(`[WebPlayer State] Restored Slot 0 state (${stateBytes.byteLength} bytes, hash=${lastSyncedStateHash}) into VFS.`);
      } catch (err) {
        console.warn('[WebPlayer State] Error restoring Slot 0 save state:', err);
      }
    }

    /**
     * Recursively searches VFS state directories for the latest Slot 0 (.state) file
     */
    function findChangedSlot0InVfs(fs, romBaseName) {
      if (!fs || !romBaseName) return null;
      const expectedName = `${romBaseName}.state`.toLowerCase();
      const rootDirs = [
        '/home/web_user/retroarch/userdata/states',
        '/home/web_user/userdata/states'
      ];

      const candidates = [];

      function scanDir(dirPath, depth) {
        if (depth > 3) return;
        let entries = [];
        try {
          entries = fs.readdir(dirPath);
        } catch (_) {
          return;
        }
        for (const entry of entries) {
          if (entry === '.' || entry === '..') continue;
          const fullPath = `${dirPath}/${entry}`;
          let st = null;
          try {
            st = fs.stat(fullPath);
          } catch (_) {
            continue;
          }
          if (!st) continue;
          const isDir = typeof fs.isDir === 'function' ? fs.isDir(st.mode) : ((st.mode & 0o170000) === 0o040000);
          if (isDir) {
            scanDir(fullPath, depth + 1);
          } else {
            const lowerEntry = entry.toLowerCase();
            // Strictly match Slot 0 (.state) and exclude .state1, .state2, .state.auto, .png, etc.
            if (lowerEntry === expectedName || (lowerEntry.endsWith('.state') && !lowerEntry.endsWith('.state.auto'))) {
              try {
                const raw = fs.readFile(fullPath);
                if (raw && raw.byteLength > 64) {
                  const bytes = raw instanceof Uint8Array ? raw : new Uint8Array(raw);
                  const hash = computeStateHash(bytes);
                  const mtimeMs = st.mtime ? (st.mtime instanceof Date ? st.mtime.getTime() : Number(st.mtime)) : 0;
                  candidates.push({
                    path: fullPath,
                    bytes,
                    hash,
                    mtimeMs
                  });
                }
              } catch (_) {}
            }
          }
        }
      }

      rootDirs.forEach(d => scanDir(d, 0));
      if (candidates.length === 0) return null;

      // Prefer any candidate whose content hash differs from lastSyncedStateHash, ordered by newest mtime
      const changed = candidates.filter(c => c.hash !== lastSyncedStateHash);
      if (changed.length === 0) return null;

      changed.sort((a, b) => b.mtimeMs - a.mtimeMs);
      return changed[0];
    }

    /**
     * Checks VFS for a modified Slot 0 (.state) file and uploads it to the server if changed
     */
    async function syncSlot0StateIfChanged(isExitSync = false) {
      if (!activeVfsRef || !activeRomBaseName || !consoleIdParam || !gameIdParam) return false;
      if (isSyncingState && !isExitSync) return false;

      const found = findChangedSlot0InVfs(activeVfsRef, activeRomBaseName);
      if (!found) return false;

      isSyncingState = true;
      const previousHash = lastSyncedStateHash;
      lastSyncedStateHash = found.hash;

      try {
        // Mirror the newly saved Slot 0 file across candidate VFS paths so F4 works regardless of subdir
        writeSlot0ToVfs(activeVfsRef, activeRomBaseName, coreParam, found.bytes, found.path);

        const blob = new Blob([found.bytes], { type: 'application/octet-stream' });
        const formData = new FormData();
        formData.append('console_id', String(consoleIdParam));
        formData.append('game_id', String(gameIdParam));
        formData.append('rom_name', activeRomBaseName);
        formData.append('state_file', blob, `${activeRomBaseName}.state`);

        const fetchOptions = {
          method: 'POST',
          body: formData
        };
        if (isExitSync && found.bytes.byteLength < 60000) {
          fetchOptions.keepalive = true;
        }

        const res = await fetch('/api/player/save-state', fetchOptions);
        if (!res.ok) {
          throw new Error(`HTTP ${res.status}`);
        }
        const resJson = await res.json();
        console.info(`[WebPlayer State] Synced Slot 0 state to server (${found.bytes.byteLength} bytes, file=${resJson.filename || ''}).`);
        updateStateSyncBadge('Slot 0 Saved ✓', true, 4000);
        return true;
      } catch (err) {
        lastSyncedStateHash = previousHash;
        console.warn('[WebPlayer State] Failed to sync Slot 0 state to server:', err);
        return false;
      } finally {
        isSyncingState = false;
      }
    }

    /**
     * Starts real-time background monitoring for Slot 0 save state changes in VFS
     */
    function startSlot0StateWatcher(fs, romBaseName) {
      activeVfsRef = fs;
      activeRomBaseName = romBaseName;
      if (stateWatcherInterval) {
        clearInterval(stateWatcherInterval);
      }
      stateWatcherInterval = setInterval(() => {
        syncSlot0StateIfChanged(false);
      }, 2000);
    }

    /**
     * Updates loading screen status text
     */
    function updateLoadingStatus(text) {
      if (loadingStatusText) {
        loadingStatusText.textContent = text;
      }
      console.log('[WebPlayer Status]', text);
    }

    /**
     * Renders a fatal error and stops the loader
     */
    function showError(title, message) {
      if (loadingSpinner) loadingSpinner.style.display = 'none';
      if (loadingCard) loadingCard.style.display = 'none';
      if (errorCard) errorCard.style.display = 'flex';
      if (errorTitle) errorTitle.textContent = title;
      if (errorMessage) errorMessage.textContent = message;
      updateFooterStatus('error', '(error)');
      console.error('[WebPlayer Error]', title, message);
    }

    let isExiting = false;

    /**
     * Handles exit / closes the player tab and returns to the game library
     */
    async function handleExit() {
      if (isExiting) return;
      isExiting = true;
      if (stateWatcherInterval) {
        clearInterval(stateWatcherInterval);
        stateWatcherInterval = null;
      }
      try {
        await syncSlot0StateIfChanged(true);
      } catch (_) {}
      try {
        window.close();
      } catch (_) {}
      setTimeout(() => {
        try {
          window.open('', '_self', '');
          window.close();
        } catch (_) {}

        if (!window.closed) {
          if (window.history.length > 1) {
            window.history.back();
          } else {
            window.location.href = '/games-portal';
          }
        }
      }, 50);
    }

    window.addEventListener('pagehide', () => {
      syncSlot0StateIfChanged(true);
    });

    /**
     * Toggles fullscreen on the player wrapper (with pseudo-fullscreen fallback for iOS mobile browsers)
     */
    function toggleFullscreen() {
      const container = document.getElementById('playerWrapper');
      if (!container) return;

      const isNativeFs = !!(document.fullscreenElement || document.webkitFullscreenElement);
      const isPseudoFs = container.classList.contains('is-fullscreen-active');

      if (!isNativeFs && !isPseudoFs) {
        if (container.requestFullscreen) {
          container.requestFullscreen().then(() => {
            container.classList.add('is-fullscreen-active');
            refreshMobileGamepadVisibility();
          }).catch(() => {
            container.classList.add('is-fullscreen-active');
            refreshMobileGamepadVisibility();
          });
        } else if (container.webkitRequestFullscreen) {
          try {
            container.webkitRequestFullscreen();
          } catch (_) {}
          container.classList.add('is-fullscreen-active');
          refreshMobileGamepadVisibility();
        } else {
          container.classList.add('is-fullscreen-active');
          refreshMobileGamepadVisibility();
        }
      } else {
        container.classList.remove('is-fullscreen-active');
        releaseAllVirtualTouchKeys();
        if (isNativeFs) {
          if (document.exitFullscreen) {
            document.exitFullscreen().catch(err => console.warn(err));
          } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
          }
        }
      }
      if (canvas) {
        setTimeout(() => canvas.focus(), 50);
      }
    }

    function handleFullscreenChange() {
      const container = document.getElementById('playerWrapper');
      if (!container) return;
      const isNativeFs = !!(document.fullscreenElement || document.webkitFullscreenElement);
      if (isNativeFs) {
        container.classList.add('is-fullscreen-active');
        refreshMobileGamepadVisibility();
      } else {
        container.classList.remove('is-fullscreen-active');
        releaseAllVirtualTouchKeys();
      }
    }

    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);

    // =========================================================================
    // Mobile Fullscreen Overlay: Save/Load State, Gamepad Detection & Touch Pad
    // =========================================================================
    let isTouchGamepadVisible = true;
    let manualTouchGamepadOverride = null; // null = auto-detect gamepad, true/false = user forced
    let mobileFsToastTimer = null;
    const activeTouchButtons = new Set();

    function detectIsMobileOrTouchDevice() {
      const ua = navigator.userAgent || '';
      const isMobileUa = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|Tablet/i.test(ua);
      const isCoarsePointer = !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);
      const hasTouchPoints = (navigator.maxTouchPoints && navigator.maxTouchPoints > 0) || ('ontouchstart' in window);
      return isMobileUa || isCoarsePointer || (hasTouchPoints && window.innerWidth <= 1280);
    }

    function hasConnectedPhysicalGamepad() {
      if (typeof navigator.getGamepads !== 'function') return false;
      try {
        const pads = navigator.getGamepads();
        if (!pads) return false;
        for (let i = 0; i < pads.length; i++) {
          if (pads[i] && pads[i].connected) {
            return true;
          }
        }
      } catch (_) {}
      return false;
    }

    function setTouchGamepadVisibility(visible) {
      isTouchGamepadVisible = !!visible;
      const controlsEl = document.getElementById('mobileTouchControls');
      const toggleBtn = document.getElementById('btnMobileTouchToggle');
      const toggleLabel = document.getElementById('mobileTouchToggleLabel');

      if (controlsEl) {
        if (isTouchGamepadVisible) {
          controlsEl.classList.remove('is-hidden');
        } else {
          controlsEl.classList.add('is-hidden');
          releaseAllVirtualTouchKeys();
        }
      }
      if (toggleBtn) {
        toggleBtn.classList.toggle('is-active', isTouchGamepadVisible);
      }
      if (toggleLabel) {
        toggleLabel.textContent = isTouchGamepadVisible ? 'Pad: On' : 'Pad: Off';
      }
    }

    function refreshMobileGamepadVisibility() {
      const container = document.getElementById('playerWrapper');
      if (container) {
        container.classList.toggle('is-mobile-device', detectIsMobileOrTouchDevice());
      }
      if (manualTouchGamepadOverride !== null) {
        setTouchGamepadVisibility(manualTouchGamepadOverride);
        return;
      }
      const physicalConnected = hasConnectedPhysicalGamepad();
      setTouchGamepadVisibility(!physicalConnected);
    }

    function toggleMobileTouchPad(event) {
      if (event) {
        event.preventDefault();
        event.stopPropagation();
      }
      manualTouchGamepadOverride = !isTouchGamepadVisible;
      setTouchGamepadVisibility(manualTouchGamepadOverride);
      showMobileFsToast(manualTouchGamepadOverride ? 'Touch Gamepad Enabled' : 'Touch Gamepad Hidden');
      if (canvas) canvas.focus();
    }

    function showMobileFsToast(message, isError = false) {
      const toast = document.getElementById('mobileFsToast');
      if (!toast) return;
      if (mobileFsToastTimer) {
        clearTimeout(mobileFsToastTimer);
        mobileFsToastTimer = null;
      }
      toast.textContent = message;
      toast.style.borderColor = isError ? 'rgba(248, 113, 113, 0.55)' : 'rgba(52, 211, 153, 0.55)';
      toast.style.color = isError ? '#f87171' : '#34d399';
      toast.classList.add('visible');
      mobileFsToastTimer = setTimeout(() => {
        toast.classList.remove('visible');
      }, 2400);
    }

    function dispatchEmscriptenKey(type, key, code, keyCode, location = 0) {
      const eventInit = {
        bubbles: true,
        cancelable: true,
        key: key,
        code: code,
        location: location,
        repeat: false
      };
      const evt = new KeyboardEvent(type, eventInit);
      try {
        Object.defineProperty(evt, 'keyCode', { get: () => keyCode });
        Object.defineProperty(evt, 'which', { get: () => keyCode });
      } catch (_) {}

      const target = canvas || document;
      target.dispatchEvent(evt);
    }

    function tapSyntheticKey(key, code, keyCode) {
      dispatchEmscriptenKey('keydown', key, code, keyCode, 0);
      setTimeout(() => {
        dispatchEmscriptenKey('keyup', key, code, keyCode, 0);
      }, 65);
    }

    function triggerMobileSaveState(event) {
      if (event) {
        event.preventDefault();
        event.stopPropagation();
      }
      try {
        if (window.Module && typeof window.Module._cmd_save_state === 'function') {
          window.Module._cmd_save_state();
        } else {
          tapSyntheticKey('F2', 'F2', 113);
        }
        showMobileFsToast('Saving Slot 0...');
        setTimeout(async () => {
          const synced = await syncSlot0StateIfChanged(false);
          if (synced) {
            showMobileFsToast('Slot 0 Saved ✓');
          } else {
            // Second check in case async disk task needed slightly more time
            setTimeout(async () => {
              const syncedRetry = await syncSlot0StateIfChanged(false);
              if (syncedRetry) {
                showMobileFsToast('Slot 0 Saved ✓');
              }
            }, 400);
          }
        }, 220);
      } catch (err) {
        console.warn('[WebPlayer Mobile] Error triggering Save State:', err);
        showMobileFsToast('Save Failed', true);
      }
      if (canvas) canvas.focus();
    }

    function triggerMobileLoadState(event) {
      if (event) {
        event.preventDefault();
        event.stopPropagation();
      }
      try {
        if (window.Module && typeof window.Module._cmd_load_state === 'function') {
          window.Module._cmd_load_state();
        } else {
          tapSyntheticKey('F4', 'F4', 115);
        }
        showMobileFsToast('Slot 0 Loaded ✓');
      } catch (err) {
        console.warn('[WebPlayer Mobile] Error triggering Load State:', err);
        showMobileFsToast('Load Failed', true);
      }
      if (canvas) canvas.focus();
    }

    function releaseVirtualTouchButton(btn) {
      if (!btn || !activeTouchButtons.has(btn)) return;
      activeTouchButtons.delete(btn);
      btn.classList.remove('pressed');
      const key = btn.getAttribute('data-key') || '';
      const code = btn.getAttribute('data-code') || '';
      const keyCode = parseInt(btn.getAttribute('data-keycode') || '0', 10);
      const location = parseInt(btn.getAttribute('data-location') || '0', 10);
      if (key && code) {
        dispatchEmscriptenKey('keyup', key, code, keyCode, location);
      }
    }

    function pressVirtualTouchButton(btn) {
      if (!btn || activeTouchButtons.has(btn)) return;
      activeTouchButtons.add(btn);
      btn.classList.add('pressed');
      const key = btn.getAttribute('data-key') || '';
      const code = btn.getAttribute('data-code') || '';
      const keyCode = parseInt(btn.getAttribute('data-keycode') || '0', 10);
      const location = parseInt(btn.getAttribute('data-location') || '0', 10);
      if (key && code) {
        dispatchEmscriptenKey('keydown', key, code, keyCode, location);
      }
    }

    const dpadDirectionsMeta = {
      up:    { key: 'ArrowUp',    code: 'ArrowUp',    keyCode: 38, elId: 'dpadSliceUp' },
      right: { key: 'ArrowRight', code: 'ArrowRight', keyCode: 39, elId: 'dpadSliceRight' },
      down:  { key: 'ArrowDown',  code: 'ArrowDown',  keyCode: 40, elId: 'dpadSliceDown' },
      left:  { key: 'ArrowLeft',  code: 'ArrowLeft',  keyCode: 37, elId: 'dpadSliceLeft' }
    };
    const activeDpadDirections = new Set();

    function setDpadDirectionsActive(nextDirs) {
      // Release any direction no longer active
      Array.from(activeDpadDirections).forEach(dir => {
        if (!nextDirs.has(dir)) {
          activeDpadDirections.delete(dir);
          const meta = dpadDirectionsMeta[dir];
          if (meta) {
            const el = document.getElementById(meta.elId);
            if (el) el.classList.remove('pressed');
            dispatchEmscriptenKey('keyup', meta.key, meta.code, meta.keyCode, 0);
          }
        }
      });

      // Press any newly active direction
      nextDirs.forEach(dir => {
        if (!activeDpadDirections.has(dir)) {
          activeDpadDirections.add(dir);
          const meta = dpadDirectionsMeta[dir];
          if (meta) {
            const el = document.getElementById(meta.elId);
            if (el) el.classList.add('pressed');
            dispatchEmscriptenKey('keydown', meta.key, meta.code, meta.keyCode, 0);
          }
        }
      });
    }

    function releaseDpadWheelDirections() {
      if (activeDpadDirections.size > 0) {
        setDpadDirectionsActive(new Set());
      }
    }

    function updateDpadWheelFromPoint(wheelEl, clientX, clientY) {
      if (!wheelEl) return;
      const rect = wheelEl.getBoundingClientRect();
      const centerX = rect.left + rect.width / 2;
      const centerY = rect.top + rect.height / 2;
      const dx = clientX - centerX;
      const dy = clientY - centerY;
      const dist = Math.hypot(dx, dy);

      // Small deadzone only at the very center hub (radius < 10px)
      if (dist < 10) {
        releaseDpadWheelDirections();
        return;
      }

      // Angle in degrees: -90 = Up, 0 = Right, 90 = Down, ±180 = Left
      const angle = Math.atan2(dy, dx) * (180 / Math.PI);
      const nextDirs = new Set();

      // 4 contiguous pizza wedges with seamless diagonal overlap zones (±14° around the 45° cut lines)
      if (angle >= -149 && angle <= -31) {
        nextDirs.add('up');
      }
      if (angle >= -59 && angle <= 59) {
        nextDirs.add('right');
      }
      if (angle >= 31 && angle <= 149) {
        nextDirs.add('down');
      }
      if (angle >= 121 || angle <= -121) {
        nextDirs.add('left');
      }

      setDpadDirectionsActive(nextDirs);
    }

    function releaseAllVirtualTouchKeys() {
      Array.from(activeTouchButtons).forEach(btn => releaseVirtualTouchButton(btn));
      releaseDpadWheelDirections();
    }

    function initMobileOverlayControls() {
      refreshMobileGamepadVisibility();
      window.addEventListener('resize', refreshMobileGamepadVisibility);

      window.addEventListener('gamepadconnected', (e) => {
        console.info('[WebPlayer Gamepad] Physical controller connected:', e.gamepad ? e.gamepad.id : '');
        if (manualTouchGamepadOverride === null) {
          setTouchGamepadVisibility(false);
          showMobileFsToast('Gamepad Detected — Touch Pad Hidden');
        }
      });

      window.addEventListener('gamepaddisconnected', () => {
        console.info('[WebPlayer Gamepad] Physical controller disconnected.');
        if (manualTouchGamepadOverride === null) {
          const stillHasPad = hasConnectedPhysicalGamepad();
          setTouchGamepadVisibility(!stillHasPad);
          if (!stillHasPad) {
            showMobileFsToast('Gamepad Disconnected — Touch Pad On');
          }
        }
      });

      // Bind continuous zero-gap touch & slide tracking on the circular pizza D-Pad
      const dpadWheel = document.getElementById('touchDpadWheel');
      if (dpadWheel) {
        let activeDpadTouchId = null;
        let isMouseDraggingDpad = false;

        const handleDpadTouch = (e) => {
          e.preventDefault();
          e.stopPropagation();
          let trackedTouch = null;
          if (activeDpadTouchId !== null) {
            for (let i = 0; i < e.touches.length; i++) {
              if (e.touches[i].identifier === activeDpadTouchId) {
                trackedTouch = e.touches[i];
                break;
              }
            }
          }
          if (!trackedTouch && e.targetTouches && e.targetTouches.length > 0) {
            trackedTouch = e.targetTouches[0];
            activeDpadTouchId = trackedTouch.identifier;
          }
          if (trackedTouch) {
            updateDpadWheelFromPoint(dpadWheel, trackedTouch.clientX, trackedTouch.clientY);
          } else {
            activeDpadTouchId = null;
            releaseDpadWheelDirections();
          }
        };

        dpadWheel.addEventListener('touchstart', handleDpadTouch, { passive: false });
        dpadWheel.addEventListener('touchmove', handleDpadTouch, { passive: false });
        dpadWheel.addEventListener('touchend', (e) => {
          e.preventDefault();
          e.stopPropagation();
          let stillTouching = false;
          for (let i = 0; i < e.touches.length; i++) {
            if (e.touches[i].identifier === activeDpadTouchId) {
              stillTouching = true;
              break;
            }
          }
          if (!stillTouching) {
            activeDpadTouchId = null;
            releaseDpadWheelDirections();
          }
        }, { passive: false });
        dpadWheel.addEventListener('touchcancel', (e) => {
          e.preventDefault();
          e.stopPropagation();
          activeDpadTouchId = null;
          releaseDpadWheelDirections();
        }, { passive: false });

        // Pointer fallback for non-touch testing
        dpadWheel.addEventListener('pointerdown', (e) => {
          if (e.pointerType === 'touch') return;
          e.preventDefault();
          isMouseDraggingDpad = true;
          updateDpadWheelFromPoint(dpadWheel, e.clientX, e.clientY);
        });
        dpadWheel.addEventListener('pointermove', (e) => {
          if (e.pointerType === 'touch' || !isMouseDraggingDpad) return;
          e.preventDefault();
          updateDpadWheelFromPoint(dpadWheel, e.clientX, e.clientY);
        });
        const stopMouseDpad = (e) => {
          if (e.pointerType === 'touch' || !isMouseDraggingDpad) return;
          isMouseDraggingDpad = false;
          releaseDpadWheelDirections();
        };
        dpadWheel.addEventListener('pointerup', stopMouseDpad);
        dpadWheel.addEventListener('pointerleave', stopMouseDpad);
        dpadWheel.addEventListener('pointercancel', stopMouseDpad);
      }

      const touchButtons = document.querySelectorAll('#mobileTouchControls .touch-btn');
      touchButtons.forEach(btn => {
        btn.addEventListener('touchstart', (e) => {
          e.preventDefault();
          e.stopPropagation();
          pressVirtualTouchButton(btn);
        }, { passive: false });

        btn.addEventListener('touchend', (e) => {
          e.preventDefault();
          e.stopPropagation();
          releaseVirtualTouchButton(btn);
        }, { passive: false });

        btn.addEventListener('touchcancel', (e) => {
          e.preventDefault();
          e.stopPropagation();
          releaseVirtualTouchButton(btn);
        }, { passive: false });

        btn.addEventListener('pointerdown', (e) => {
          if (e.pointerType === 'touch') return;
          e.preventDefault();
          pressVirtualTouchButton(btn);
        });

        btn.addEventListener('pointerup', (e) => {
          if (e.pointerType === 'touch') return;
          e.preventDefault();
          releaseVirtualTouchButton(btn);
        });

        btn.addEventListener('pointerleave', (e) => {
          if (e.pointerType === 'touch') return;
          releaseVirtualTouchButton(btn);
        });
      });
    }

    initMobileOverlayControls();

    // 2. Initialize Emscripten Module Global Definition
    window.Module = {
      noInitialRun: true,
      arguments: [],
      preRun: [],
      postRun: [],
      print: function(text) {
        if (arguments.length > 1) text = Array.prototype.slice.call(arguments).join(' ');
        console.log('[RetroArch stdout]', text);
        if (text && (text.includes('Save task COMPLETED') || text.includes('Saved state to') || text.includes('Saving state'))) {
          setTimeout(() => syncSlot0StateIfChanged(false), 180);
        }
      },
      printErr: function(text) {
        if (arguments.length > 1) text = Array.prototype.slice.call(arguments).join(' ');
        console.warn('[RetroArch stderr]', text);
        if (text && (text.includes('Save task COMPLETED') || text.includes('Saved state to') || text.includes('Saving state'))) {
          setTimeout(() => syncSlot0StateIfChanged(false), 180);
        }
      },
      canvas: (function() {
        canvas.addEventListener('webglcontextlost', function(e) {
          showError('WebGL Context Lost', 'The graphics context was lost. Please restart or reload the game.');
          e.preventDefault();
        }, false);
        return canvas;
      })(),
      setStatus: function(text) {
        if (!text) return;
        updateLoadingStatus(text);
      },
      totalDependencies: 0,
      monitorRunDependencies: function(left) {
        this.totalDependencies = Math.max(this.totalDependencies, left);
        if (left > 0) {
          updateLoadingStatus(`Loading runtime assets (${this.totalDependencies - left}/${this.totalDependencies})...`);
        }
      },
      locateFile: function(path, prefix) {
        if (path.endsWith('.wasm')) {
          return `/assets/emscripten/${path}`;
        }
        return prefix + path;
      }
    };

    /**
     * Dynamically loads and initializes the Libretro core ES Module / Script
     */
    async function loadAndInitCore(coreName) {
      updateLoadingStatus(`Loading Libretro core "${coreName}"...`);
      const coreUrl = `/assets/emscripten/${encodeURIComponent(coreName)}_libretro.js`;
      let factory = null;
      let isEsModule = true;

      // Inspect the core script to determine if it is an ES6 modularized build (export default)
      // or a classic global Emscripten script (which binds directly to window.Module).
      try {
        const probeRes = await fetch(coreUrl);
        if (!probeRes.ok) {
          throw new Error(`Core script not found or failed to load (HTTP ${probeRes.status}): ${coreUrl}`);
        }
        const scriptText = await probeRes.text();
        isEsModule = /\bexport\s+default\b/.test(scriptText);
      } catch (probeErr) {
        console.warn('[Core Loader] Script probe notice:', probeErr);
      }

      if (isEsModule) {
        try {
          // Primary modern Emscripten / RetroArch approach: dynamic ES module import
          const coreMod = await import(coreUrl);
          factory = coreMod.default || window[`libretro_${coreName}`] || coreMod;
        } catch (importErr) {
          console.warn('[Core Loader] Dynamic import failed, falling back to script injection:', importErr);
          isEsModule = false;
        }
      }

      if (!isEsModule) {
        // Legacy Emscripten builds (e.g. RetroArch 1.9.x) do not have a built-in ResizeObserver
        // and read canvas.width / canvas.height directly (which default to 300x150 if unset).
        const syncLegacyCanvasSize = () => {
          if (!canvas) return;
          const dpr = window.devicePixelRatio || 1;
          const w = Math.round((canvas.clientWidth || 800) * dpr);
          const h = Math.round((canvas.clientHeight || 600) * dpr);
          if (w > 0 && h > 0 && (canvas.width !== w || canvas.height !== h)) {
            canvas.width = w;
            canvas.height = h;
          }
        };
        syncLegacyCanvasSize();
        window.addEventListener('resize', syncLegacyCanvasSize);

        await new Promise((resolve, reject) => {
          const script = document.createElement('script');
          script.src = coreUrl;
          script.async = true;
          script.onload = () => resolve();
          script.onerror = () => reject(new Error(`Core script not found or failed to load: ${coreUrl}`));
          document.head.appendChild(script);
        });
        factory = window[`libretro_${coreName}`] || window.Module;
      }

      if (typeof factory === 'function') {
        updateLoadingStatus('Initializing core runtime & WebAssembly...');
        const initialized = await factory(window.Module || {});
        if (initialized) {
          window.Module = Object.assign(window.Module || {}, initialized);
        }
      }
    }

    /**
     * Waits for Emscripten runtime to finish Wasm compilation and initialization
     */
    function waitForRuntime() {
      return new Promise((resolve) => {
        if (window.Module && window.Module.calledRun) {
          resolve();
          return;
        }

        if (window.Module && window.Module.ready && typeof window.Module.ready.then === 'function') {
          window.Module.ready.then(() => resolve()).catch(() => resolve());
          return;
        }

        const existingInit = window.Module.onRuntimeInitialized;
        window.Module.onRuntimeInitialized = function() {
          if (typeof existingInit === 'function') {
            try { existingInit(); } catch (e) { console.warn(e); }
          }
          resolve();
        };

        // Fallback interval check in case onRuntimeInitialized already triggered
        const interval = setInterval(() => {
          if (window.Module && window.Module.calledRun) {
            clearInterval(interval);
            resolve();
          }
        }, 50);
      });
    }

    /**
     * Loads and mounts the active core's button remap (.rmp) into the Emscripten virtual filesystem.
     * RetroArch looks for remap files using the internal library_name (e.g. "FCEUmm", "tia", etc.),
     * which in Emscripten's case-sensitive Unix VFS must match the exact case.
     */
    async function loadCoreRemap(coreName, fs) {
      if (!coreName || !fs) return;

      const lowerCore = coreName.trim().toLowerCase();

      // 1. Discover matching remap file from server (case-insensitive)
      let remapFilename = null;
      const remapList = Array.isArray(window.serverRemapFiles) ? window.serverRemapFiles : [];
      
      // Look for case-insensitive match in scanned server files
      for (const rf of remapList) {
        const base = rf.replace(/\.rmp$/i, '');
        if (base.toLowerCase() === lowerCore) {
          remapFilename = rf;
          break;
        }
      }

      // If not in scanned list, also check if libretroCores has a known alias
      if (!remapFilename && window.libretroCores && window.libretroCores[lowerCore]) {
        const title = window.libretroCores[lowerCore];
        const match = title.match(/\(([^)]+)\)/);
        if (match && match[1]) {
          const alias = match[1].trim();
          for (const rf of remapList) {
            const base = rf.replace(/\.rmp$/i, '');
            if (base.toLowerCase() === alias.toLowerCase()) {
              remapFilename = rf;
              break;
            }
          }
        }
      }

      // Fallback filename if not discovered in scanned list
      if (!remapFilename) {
        remapFilename = `${coreName}.rmp`;
      }

      const remapUrl = `/assets/emscripten/remaps/${encodeURIComponent(remapFilename)}`;

      try {
        let response = await fetch(remapUrl);

        // If direct fetch returned 404, try alternative casings
        if (!response.ok && remapFilename.toLowerCase() !== `${lowerCore}.rmp`) {
          const altUrl = `/assets/emscripten/remaps/${encodeURIComponent(lowerCore)}.rmp`;
          try {
            const altRes = await fetch(altUrl);
            if (altRes.ok) {
              response = altRes;
              remapFilename = `${lowerCore}.rmp`;
            }
          } catch (_) {}
        }

        if (response.status === 404) {
          console.warn(`[WebPlayer Remap] No custom button remap found for core '${coreName}' at ${remapUrl} (HTTP 404). Booting with core defaults.`);
          return;
        }

        if (!response.ok) {
          console.warn(`[WebPlayer Remap] Remap fetch returned HTTP ${response.status} for core '${coreName}'. Booting with core defaults.`);
          return;
        }

        const remapBuffer = await response.arrayBuffer();
        if (!remapBuffer || remapBuffer.byteLength === 0) {
          console.warn(`[WebPlayer Remap] Remap file at ${remapUrl} is empty. Booting with core defaults.`);
          return;
        }

        const remapData = new Uint8Array(remapBuffer);
        const fileBasename = remapFilename.replace(/\.rmp$/i, '');

        // 2. Gather ALL possible core name casing aliases
        // In RetroArch, system->info.library_name determines the remap folder name.
        // For example:
        // - "tia" has library_name = "tia" (lowercase)
        // - "fceumm" has library_name = "FCEUmm" (mixed case)
        // - "genesis_plus_gx" has library_name = "Genesis Plus GX"
        const coreAliases = new Set([
          lowerCore,
          coreName,
          fileBasename
        ]);

        if (window.libretroCores && window.libretroCores[lowerCore]) {
          const title = window.libretroCores[lowerCore];
          const match = title.match(/\(([^)]+)\)/);
          if (match && match[1]) {
            coreAliases.add(match[1].trim());
          }
        }

        // 3. Mount remap into VFS for every alias to guarantee exact case match in RetroArch
        const baseRemapDirs = [
          '/home/web_user/userdata/config/remaps',
          '/home/web_user/retroarch/userdata/config/remaps',
          '/home/web_user/retroarch/userdata/remaps',
          '/home/web_user/retroarch/config/remaps',
          '/home/web/retroarch/config/remaps'
        ];

        coreAliases.forEach((alias) => {
          baseRemapDirs.forEach((baseDir) => {
            const dirPath = `${baseDir}/${alias}`;

            // Ensure directory exists
            if (typeof fs.mkdirTree === 'function') {
              try { fs.mkdirTree(dirPath); } catch (_) {}
            } else {
              const segments = dirPath.split('/').filter(Boolean);
              let current = '';
              for (const seg of segments) {
                current += '/' + seg;
                try { fs.mkdir(current); } catch (_) {}
              }
            }

            // Write core remap file: alias.rmp and fileBasename.rmp
            const filenamesToWrite = new Set([`${alias}.rmp`, `${fileBasename}.rmp`]);

            filenamesToWrite.forEach((fname) => {
              const fullPath = `${dirPath}/${fname}`;
              try {
                try { fs.unlink(fullPath); } catch (_) {}
                if (typeof fs.createDataFile === 'function') {
                  fs.createDataFile(dirPath, fname, remapData, true, true, false);
                } else if (typeof fs.writeFile === 'function') {
                  fs.writeFile(fullPath, remapData);
                }
              } catch (writeErr) {
                console.warn(`[FS Remap Mount Notice] Could not write ${fullPath}:`, writeErr);
              }
            });
          });
        });

        console.info(`[WebPlayer Remap] Successfully mounted remap '${remapFilename}' (${remapBuffer.byteLength} bytes) for core aliases:`, Array.from(coreAliases));
      } catch (err) {
        console.warn(`[WebPlayer Remap] Failed to load remap for core '${coreName}':`, err);
      }
    }

    /**
     * Loads and mounts server BIOS files from /assets/emscripten/bios/ into Emscripten virtual filesystem.
     * Target path: /home/web_user/userdata/system/{filename}
     */
    async function loadBiosFiles(fs) {
      if (!fs) return;

      const primarySystemDir = '/home/web_user/userdata/system';
      const fallbackSystemDirs = [
        primarySystemDir,
        '/home/web_user/retroarch/userdata/system',
        '/home/web_user/retroarch/system',
        '/home/web/retroarch/system'
      ];

      // 1. Ensure target system directory hierarchy exists using FS.mkdirTree
      fallbackSystemDirs.forEach((dir) => {
        try {
          if (typeof fs.mkdirTree === 'function') {
            fs.mkdirTree(dir);
          } else {
            const segments = dir.split('/').filter(Boolean);
            let current = '';
            for (const seg of segments) {
              current += '/' + seg;
              try { fs.mkdir(current); } catch (_) {}
            }
          }
        } catch (dirErr) {
          if (dirErr.code !== 'EEXIST') {
            console.warn(`[FS.mkdirTree Notice] Could not create system directory '${dir}':`, dirErr);
          }
        }
      });

      // 2. Discover BIOS files from server
      let biosList = Array.isArray(window.serverBiosFiles) ? [...window.serverBiosFiles] : [];

      if (biosList.length === 0) {
        try {
          const apiRes = await fetch('/api/emscripten/bios');
          if (apiRes.ok) {
            const data = await apiRes.json();
            if (Array.isArray(data.files) && data.files.length > 0) {
              biosList = data.files;
            }
          }
        } catch (_) {
          // Graceful fallback if API fails
        }
      }

      if (biosList.length === 0) {
        console.info('[WebPlayer BIOS] No BIOS files discovered in /assets/emscripten/bios/.');
        return;
      }

      console.info(`[WebPlayer BIOS] Found ${biosList.length} BIOS file(s) on server:`, biosList);
      updateLoadingStatus(`Loading system BIOS (0/${biosList.length})...`);

      let loadedCount = 0;
      for (let i = 0; i < biosList.length; i++) {
        const biosFilename = biosList[i];
        const biosUrl = `/assets/emscripten/bios/${encodeURIComponent(biosFilename)}`;

        try {
          updateLoadingStatus(`Loading BIOS (${i + 1}/${biosList.length}): ${biosFilename}...`);
          const response = await fetch(biosUrl);

          if (!response.ok) {
            console.warn(`[WebPlayer BIOS] Could not fetch BIOS '${biosFilename}' from ${biosUrl} (HTTP ${response.status}). Skipping optional BIOS.`);
            continue;
          }

          const biosBuffer = await response.arrayBuffer();
          if (!biosBuffer || biosBuffer.byteLength === 0) {
            console.warn(`[WebPlayer BIOS] BIOS file '${biosFilename}' is empty (0 bytes). Skipping.`);
            continue;
          }

          const biosData = new Uint8Array(biosBuffer);

          // Write each fetched BIOS binary file into the target VFS path
          fallbackSystemDirs.forEach((systemDir) => {
            const targetPath = `${systemDir}/${biosFilename}`;
            try {
              try { fs.unlink(targetPath); } catch (_) {}
              if (typeof fs.writeFile === 'function') {
                fs.writeFile(targetPath, biosData);
              } else if (typeof fs.createDataFile === 'function') {
                fs.createDataFile(systemDir, biosFilename, biosData, true, true, false);
              }
            } catch (writeErr) {
              console.warn(`[WebPlayer BIOS] Could not write ${targetPath}:`, writeErr);
            }
          });

          loadedCount++;
          console.info(`[WebPlayer BIOS] Mounted '${biosFilename}' (${biosBuffer.byteLength} bytes) to ${primarySystemDir}/${biosFilename}`);
        } catch (err) {
          console.warn(`[WebPlayer BIOS] Error fetching or mounting BIOS '${biosFilename}':`, err);
        }
      }

      console.info(`[WebPlayer BIOS] Successfully mounted ${loadedCount}/${biosList.length} BIOS file(s) into VFS.`);
    }

    /**
     * Main Asynchronous Game Boot Sequence
     */
    async function bootGame() {
      try {
        updateFooterStatus('loading', '(loading...)');

        // Step A: Dynamically resolve Web Core and Main Download File ID from backend if needed
        if (!coreParam || !fileIdParam) {
          if (!consoleIdParam && !gameIdParam) {
            showError('Missing Parameters', 'Please provide console_id and game_id in the URL. (e.g. ?console_id=1&game_id=470)');
            return;
          }

          updateLoadingStatus('Retrieving console web core and game download file...');

          const metaUrl = `/api/player/meta?console_id=${encodeURIComponent(consoleIdParam)}&game_id=${encodeURIComponent(gameIdParam)}${fileIdParam ? '&file_id=' + encodeURIComponent(fileIdParam) : ''}`;
          const metaRes = await fetch(metaUrl);

          if (!metaRes.ok) {
            let metaErrMsg = '';
            try {
              const metaErrJson = await metaRes.json();
              if (metaErrJson && (metaErrJson.error || metaErrJson.message)) {
                metaErrMsg = metaErrJson.error || metaErrJson.message;
              }
            } catch (_) {}

            throw new Error(metaErrMsg || `Failed to retrieve game configuration from server (HTTP ${metaRes.status}).`);
          }

          const metaResJson = await metaRes.json();
          const metaData = (metaResJson && metaResJson.data) ? metaResJson.data : metaResJson;
          if (!metaData.web_core) {
            throw new Error('This console does not have a web emulation core configured.');
          }
          if (!metaData.file_id) {
            throw new Error('No downloadable game file found for this game.');
          }

          coreParam = (metaData.web_core || '').trim().toLowerCase();
          fileIdParam = String(metaData.file_id);
          if (metaData.console_id) {
            consoleIdParam = String(metaData.console_id);
          }
          if (metaData.game_id) {
            gameIdParam = String(metaData.game_id);
          }
          if (metaData.user_id) {
            userIdParam = String(metaData.user_id);
          }

          if (metaData.console_name) {
            currentConsoleName = metaData.console_name;
            const consoleEl = document.getElementById('headerConsoleName');
            const dividerEl = document.getElementById('headerConsoleDivider');
            const modalTitle = document.getElementById('mappingModalTitle');
            if (consoleEl) {
              consoleEl.textContent = metaData.console_name;
              consoleEl.style.display = 'inline';
            }
            if (dividerEl) {
              dividerEl.style.display = 'inline';
            }
            if (modalTitle) {
              modalTitle.textContent = `Button Mapping — ${metaData.console_name}`;
            }
          }

          if (metaData.game_title) {
            const gameEl = document.getElementById('headerGameTitle');
            if (gameEl) {
              gameEl.textContent = metaData.game_title;
            }
            document.title = `${metaData.game_title} — Videogame Vault Web Arcade`;
          }

          if (metaData.mappings) {
            currentMappings = metaData.mappings;
          }
        }

        // Update UI badges
        if (coreBadge) {
          const friendlyName = (window.libretroCores && window.libretroCores[coreParam]) 
            ? window.libretroCores[coreParam] 
            : coreParam.toUpperCase();
          coreBadge.textContent = `CORE: ${friendlyName}`;
          coreBadge.title = `Libretro core: ${coreParam}`;
        }

        // Step B: Fetch Secure ROM binary directly from Backend (stream=1 bypasses cloud CORS restrictions)
        updateLoadingStatus('Fetching secure ROM stream...');
        const response = await fetch(`/download/${encodeURIComponent(fileIdParam)}?stream=1`);

        if (response.redirected && response.url.includes('/login')) {
          throw new Error('Authentication required. Please sign in to play catalogued games.');
        }

        if (!response.ok) {
          let errDetail = '';
          try {
            const errJson = await response.json();
            if (errJson && (errJson.error || errJson.message)) {
              errDetail = errJson.error || errJson.message;
            }
          } catch (_) {}

          if (response.status === 401) {
            throw new Error('Authentication required. Please sign in to play catalogued games.');
          }
          if (response.status === 404) {
            throw new Error(errDetail || `Game file ID #${fileIdParam} was not found on the server.`);
          }
          throw new Error(errDetail || `Server returned HTTP status ${response.status}: ${response.statusText}`);
        }

        const contentType = (response.headers.get('content-type') || '').toLowerCase();
        if (contentType.includes('text/html') || contentType.includes('application/json')) {
          let errDetail = '';
          try {
            const errJson = await response.json();
            if (errJson && (errJson.error || errJson.message)) {
              errDetail = errJson.error || errJson.message;
            }
          } catch (_) {}
          throw new Error(errDetail || 'Server returned an invalid response instead of a binary ROM file.');
        }

        let romFilename = `game_${fileIdParam}.rom`;
        const contentDisposition = response.headers.get('content-disposition');
        if (contentDisposition && contentDisposition.includes('filename=')) {
          const match = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
          if (match && match[1]) {
            romFilename = match[1].replace(/['"]/g, '').trim();
          }
        }

        const romBuffer = await response.arrayBuffer();
        if (!romBuffer || romBuffer.byteLength === 0) {
          throw new Error('The downloaded ROM file is empty (0 bytes).');
        }

        updateLoadingStatus(`ROM downloaded (${(romBuffer.byteLength / 1024 / 1024).toFixed(2)} MB). Injecting core...`);

        // Step C: Inject & Initialize Libretro Core
        await loadAndInitCore(coreParam);

        // Step D: Ensure Emscripten runtime readiness
        updateLoadingStatus('Finalizing Emscripten WebAssembly runtime...');
        await waitForRuntime();

        // Step E: Write ROM binary into Emscripten Virtual Filesystem (FS)
        updateLoadingStatus('Mounting ROM into virtual filesystem...');
        const fs = window.FS || (window.Module && window.Module.FS);
        if (!fs || typeof fs.createDataFile !== 'function') {
          throw new Error('Emscripten virtual filesystem (FS) is not available on this Libretro core.');
        }

        // Clean filename to prevent filesystem path issues
        const cleanName = romFilename.replace(/[^a-zA-Z0-9._-]/g, '_');
        const virtualRomPath = '/' + cleanName;

        try {
          try { fs.unlink(virtualRomPath); } catch (_) {}
          fs.createDataFile('/', cleanName, new Uint8Array(romBuffer), true, true, false);
        } catch (fsErr) {
          console.warn('[FS Notice]', fsErr);
        }

        // Step F: Mount Core Button Remap (.rmp) if available
        updateLoadingStatus('Loading core button remap...');
        await loadCoreRemap(coreParam, fs);

        // Step G: Mount Server BIOS files (/assets/emscripten/bios/) into VFS (/home/web_user/userdata/system)
        updateLoadingStatus('Loading system BIOS...');
        await loadBiosFiles(fs);

        // Step H: Setup Runtime Directory in Emscripten VFS
        updateLoadingStatus('Preparing runtime storage...');
        const primaryStatesDir = '/home/web_user/retroarch/userdata/states';

        function ensureVfsDir(dirPath) {
          if (!dirPath || dirPath === '/' || !fs) return;
          const segments = dirPath.split('/').filter(Boolean);
          let cur = '';
          for (const s of segments) {
            cur += '/' + s;
            let exists = false;
            try {
              const st = fs.stat(cur);
              if (st) exists = true;
            } catch (_) {}
            if (!exists) {
              try {
                fs.mkdir(cur);
              } catch (_) {}
            }
          }
        }

        // Build standard VFS runtime directory trees for in-session states
        ensureVfsDir(primaryStatesDir);
        ensureVfsDir('/home/web_user/retroarch/userdata/system');
        ensureVfsDir('/home/web_user/retroarch/userdata/content');
        ensureVfsDir('/home/web_user/.config/retroarch');

        // Write RetroArch configuration to enforce savestate_directory in VFS
        const retroarchConfigContent = [
          'savestate_directory = "' + primaryStatesDir + '"',
          'savefile_directory = "' + primaryStatesDir + '"',
          'sort_savestates_enable = "false"',
          'sort_savestates_by_content_enable = "false"',
          'savestates_in_content_dir = "false"',
          'state_slot = "0"'
        ].join('\n');

        const configPaths = [
          '/home/web_user/retroarch/userdata/retroarch.cfg',
          '/home/web_user/.config/retroarch/retroarch.cfg',
          '/home/web_user/.retroarch.cfg',
          '/etc/retroarch.cfg'
        ];

        configPaths.forEach(cfgPath => {
          const dir = cfgPath.substring(0, cfgPath.lastIndexOf('/'));
          ensureVfsDir(dir);
          try {
            try { fs.unlink(cfgPath); } catch (_) {}
            if (typeof fs.writeFile === 'function') {
              fs.writeFile(cfgPath, retroarchConfigContent);
            } else if (typeof fs.createDataFile === 'function') {
              fs.createDataFile(dir, 'retroarch.cfg', retroarchConfigContent, true, true, false);
            }
          } catch (_) {}
        });

        // Step H.2: Restore user's Slot 0 save state (.state) from server before core boots
        const romBaseName = cleanName.replace(/\.[^/.]+$/, '') || cleanName;
        await restoreSlot0StateFromServer(fs, romBaseName, coreParam);

        // Step I: Execute Core via Module.callMain
        updateLoadingStatus('Booting core...');
        if (!window.Module || typeof window.Module.callMain !== 'function') {
          throw new Error('Module.callMain is not defined on this Libretro Emscripten build.');
        }

        window.Module.arguments = [virtualRomPath];
        window.Module.callMain([virtualRomPath]);

        // Start background watcher to automatically persist Slot 0 save state updates
        startSlot0StateWatcher(fs, romBaseName);

        // Step J: Success - Hide Loading Overlay & Focus Canvas
        if (loadingOverlay) {
          loadingOverlay.classList.add('hidden');
        }
        if (statusBadge) {
          statusBadge.style.display = 'inline-flex';
        }
        updateFooterStatus('running', '(running)');
        if (canvas) {
          canvas.focus();
        }

      } catch (err) {
        showError('Emulation Launch Error', err.message || 'An error occurred during ROM preparation.');
      }
    }

    // Start boot sequence once DOM is ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', bootGame);
    } else {
      bootGame();
    }
  </script>
</body>
</html>

