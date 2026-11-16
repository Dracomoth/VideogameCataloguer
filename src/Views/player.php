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
    }
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
        </div>
      </div>

      <!-- Centered Game and Console Name -->
      <div class="header-center" id="headerCenter">
        <h1 class="header-game-title" id="headerGameTitle"><?= htmlspecialchars($gameTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <span class="header-console-divider" id="headerConsoleDivider" <?= empty($consoleName) ? 'style="display: none;"' : '' ?>>•</span>
        <span class="header-console-name" id="headerConsoleName"><?= htmlspecialchars($consoleName, ENT_QUOTES, 'UTF-8') ?></span>
      </div>

      <div class="header-right">
        <button type="button" class="action-btn" id="btnShowMapping" onclick="openMappingModal()" title="View Console Button Mapping">
          <svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13">
            <path d="M21 6H3c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-10 7H8v3H6v-3H3v-2h3V8h2v3h3v2zm4.5 2c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm4-3c-.83 0-1.5-.67-1.5-1.5S18.67 9 19.5 9s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
          </svg>
          Controls
        </button>
        <button type="button" class="action-btn" title="Restart Game" onclick="location.reload()">
          <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13">
            <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.75a.75.75 0 00-.75.75v4.482a.75.75 0 001.5 0v-2.433l.312.311a7 7 0 1010.5-5.265.75.75 0 00-.75.75 1.5 1.5 0 01-1.5 1.5z" clip-rule="evenodd"/>
          </svg>
          Reset
        </button>
        <button type="button" class="action-btn" title="Toggle Fullscreen" onclick="toggleFullscreen()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13">
            <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
          </svg>
          Fullscreen
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
          <p class="info-note warning-note">
            Game States persist only while the current player window is open. Be advise that if you close the current window, al save states will be deleted. If you want to be able to preserve save states across gaming sessions it is recommended that you use retroarch standalone, either mobile or desktop. Both versions can be obtained <a href="https://www.retroarch.com" target="_blank" rel="noopener noreferrer" style="color: #38bdf8; text-decoration: underline; font-weight: 600;">here</a>.
          </p>
        </div>
      </aside>

      <!-- Emulator Screen Bezel -->
      <div class="arcade-bezel">
        <!-- Emscripten HTML5 Canvas -->
        <canvas id="canvas" oncontextmenu="event.preventDefault()" tabindex="0"></canvas>

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
    function handleExit() {
      if (isExiting) return;
      isExiting = true;
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

    /**
     * Toggles fullscreen on the player wrapper
     */
    function toggleFullscreen() {
      const container = document.getElementById('playerWrapper');
      if (!document.fullscreenElement && !document.webkitFullscreenElement) {
        if (container.requestFullscreen) {
          container.requestFullscreen().catch(err => console.warn(err));
        } else if (container.webkitRequestFullscreen) {
          container.webkitRequestFullscreen();
        }
      } else {
        if (document.exitFullscreen) {
          document.exitFullscreen().catch(err => console.warn(err));
        } else if (document.webkitExitFullscreen) {
          document.webkitExitFullscreen();
        }
      }
    }

    // 2. Initialize Emscripten Module Global Definition
    window.Module = {
      noInitialRun: true,
      arguments: [],
      preRun: [],
      postRun: [],
      print: function(text) {
        if (arguments.length > 1) text = Array.prototype.slice.call(arguments).join(' ');
        console.log('[RetroArch stdout]', text);
      },
      printErr: function(text) {
        if (arguments.length > 1) text = Array.prototype.slice.call(arguments).join(' ');
        console.warn('[RetroArch stderr]', text);
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

      try {
        // Primary modern Emscripten / RetroArch approach: dynamic ES module import
        const coreMod = await import(coreUrl);
        factory = coreMod.default || window[`libretro_${coreName}`] || coreMod;
      } catch (importErr) {
        console.warn('[Core Loader] Dynamic import failed, falling back to script injection:', importErr);
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
        if (window.Module && (window.Module.calledRun || (window.Module.FS && window.Module.callMain))) {
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
          const fs = window.FS || (window.Module && window.Module.FS);
          if (fs && window.Module && typeof window.Module.callMain === 'function') {
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

          const metaUrl = `/api/player/meta?console_id=${encodeURIComponent(consoleIdParam)}&game_id=${encodeURIComponent(gameIdParam)}`;
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

        // Step I: Execute Core via Module.callMain
        updateLoadingStatus('Booting core...');
        if (!window.Module || typeof window.Module.callMain !== 'function') {
          throw new Error('Module.callMain is not defined on this Libretro Emscripten build.');
        }

        window.Module.arguments = [virtualRomPath];
        window.Module.callMain([virtualRomPath]);

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

