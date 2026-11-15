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

$fileId = (int)($_GET['file_id'] ?? ($_GET['id'] ?? 0));
$gameId = (int)($_GET['game_id'] ?? 0);
$core = trim((string)($_GET['core'] ?? ''));

// Game title resolution
$gameTitle = 'Web Arcade Player';
if ($fileId > 0) {
    $downloadRepo = new \Vault\Repositories\DownloadRepository();
    $fileData = $downloadRepo->getById($fileId);
    if ($fileData && !empty($fileData['game_title'])) {
        $gameTitle = (string)$fileData['game_title'];
    } elseif ($fileData && !empty($fileData['display_name'])) {
        $gameTitle = (string)$fileData['display_name'];
    }
} elseif ($gameId > 0) {
    $gameRepo = new \Vault\Repositories\GameRepository();
    $gameData = $gameRepo->getById($gameId);
    if ($gameData && !empty($gameData['title'])) {
        $gameTitle = (string)$gameData['title'];
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

    body, html {
      width: 100%;
      height: 100%;
      background-color: var(--player-bg);
      color: var(--text-main, #f8fafc);
      font-family: var(--font-sans, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif);
      overflow: hidden;
    }

    .player-layout {
      display: flex;
      flex-direction: column;
      width: 100vw;
      height: 100vh;
      background: radial-gradient(circle at 50% 20%, #172554 0%, #070a12 75%);
    }

    /* Top Application Bar */
    .player-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 52px;
      padding: 0 16px;
      background: rgba(15, 23, 42, 0.85);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--player-border);
      z-index: 20;
      flex-shrink: 0;
    }

    .header-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: var(--radius-md, 6px);
      color: var(--text-main, #f8fafc);
      text-decoration: none;
      font-size: 12px;
      font-weight: 500;
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
      gap: 8px;
    }

    .player-logo {
      width: 22px;
      height: 22px;
      object-fit: contain;
    }

    .player-title {
      font-size: 14px;
      font-weight: 600;
      letter-spacing: 0.3px;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .badge-core {
      display: inline-flex;
      align-items: center;
      padding: 3px 8px;
      border-radius: 9999px;
      font-size: 11px;
      font-family: var(--font-mono, monospace);
      font-weight: 600;
      background: rgba(2, 132, 199, 0.2);
      color: var(--player-accent-light);
      border: 1px solid rgba(56, 189, 248, 0.3);
      text-transform: uppercase;
    }

    .badge-status {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 3px 8px;
      border-radius: 9999px;
      font-size: 11px;
      background: rgba(16, 185, 129, 0.15);
      color: #34d399;
      border: 1px solid rgba(52, 211, 153, 0.3);
      font-weight: 500;
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
      gap: 8px;
    }

    .action-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      height: 32px;
      padding: 0 10px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: var(--radius-md, 6px);
      color: var(--text-muted, #94a3b8);
      font-size: 12px;
      font-weight: 500;
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
      padding: 16px;
      overflow: hidden;
    }

    .arcade-bezel {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      height: 100%;
      max-width: 1000px;
      max-height: calc(100vh - 120px);
      background: #000;
      border: 2px solid var(--player-border);
      border-radius: 8px;
      box-shadow: 0 12px 36px rgba(0, 0, 0, 0.7), 0 0 24px rgba(2, 132, 199, 0.15);
      overflow: hidden;
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

    /* Bottom Status & Controls Bar */
    .player-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 38px;
      padding: 0 16px;
      background: rgba(15, 23, 42, 0.75);
      border-top: 1px solid var(--player-border);
      font-size: 11px;
      color: var(--text-dim, #64748b);
      flex-shrink: 0;
    }

    .controls-hint {
      display: flex;
      align-items: center;
      gap: 14px;
      overflow-x: auto;
      white-space: nowrap;
    }

    .key-badge {
      display: inline-flex;
      align-items: center;
      padding: 1px 5px;
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: 3px;
      font-family: var(--font-mono, monospace);
      font-size: 10px;
      color: var(--text-main, #f8fafc);
      margin-right: 4px;
    }

    /* Fullscreen Mode Overrides */
    :fullscreen .player-header,
    :fullscreen .player-footer {
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

    :-webkit-full-screen .player-header,
    :-webkit-full-screen .player-footer {
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
  </style>
</head>
<body>
  <div class="player-layout" id="playerWrapper">
    <!-- Header -->
    <header class="player-header">
      <div class="header-left">
        <button type="button" class="back-btn" onclick="handleExit()">
          <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14">
            <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd"/>
          </svg>
          Exit
        </button>

        <div class="player-title-group">
          <img src="/assets/favicon.svg" alt="Vault Icon" class="player-logo">
          <span class="player-title">Videogame Vault Web Arcade</span>
          <span class="badge-core" id="coreBadge">CORE: —</span>
          <span class="badge-status" id="statusBadge" style="display: none;">
            <span class="status-dot"></span>
            <span>Running</span>
          </span>
        </div>
      </div>

      <div class="header-right">
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

    <!-- Footer Controls Bar -->
    <footer class="player-footer">
      <div class="controls-hint">
        <span>🎮 <strong>Controls:</strong></span>
        <span><span class="key-badge">Arrows</span> D-Pad</span>
        <span><span class="key-badge">Z</span> / <span class="key-badge">X</span> A/B</span>
        <span><span class="key-badge">A</span> / <span class="key-badge">S</span> X/Y</span>
        <span><span class="key-badge">Enter</span> Start</span>
        <span><span class="key-badge">R-Shift</span> Select</span>
        <span><span class="key-badge">Q</span> / <span class="key-badge">W</span> L/R</span>
      </div>
      <div>
        <span>RetroArch Web (Emscripten / WASM)</span>
      </div>
    </footer>
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
    const coreParam = (urlParams.get('core') || '').trim().toLowerCase();
    const fileIdParam = (urlParams.get('file_id') || urlParams.get('fileId') || urlParams.get('game_id') || urlParams.get('gameId') || urlParams.get('id') || '').trim();

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
      console.error('[WebPlayer Error]', title, message);
    }

    /**
     * Handles exit / return navigation
     */
    function handleExit() {
      if (window.history.length > 1) {
        window.history.back();
      } else {
        window.location.href = '/games-portal';
      }
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
     * Main Asynchronous Game Boot Sequence
     */
    async function bootGame() {
      // Step A: Parameter validation
      if (!coreParam) {
        showError('Missing Core Parameter', 'The "core" query parameter was not specified in the URL. (e.g. ?core=snes9x&file_id=12)');
        return;
      }
      if (!fileIdParam) {
        showError('Missing File ID Parameter', 'The "file_id" query parameter was not specified in the URL. (e.g. ?core=snes9x&file_id=12)');
        return;
      }

      // Update UI badges
      if (coreBadge) {
        const friendlyName = (window.libretroCores && window.libretroCores[coreParam]) 
          ? window.libretroCores[coreParam] 
          : coreParam.toUpperCase();
        coreBadge.textContent = `CORE: ${friendlyName}`;
        coreBadge.title = `Libretro core: ${coreParam}`;
      }

      try {
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

        // Step F: Execute Core via Module.callMain
        updateLoadingStatus('Booting core...');
        if (!window.Module || typeof window.Module.callMain !== 'function') {
          throw new Error('Module.callMain is not defined on this Libretro Emscripten build.');
        }

        window.Module.callMain([virtualRomPath]);

        // Step G: Success - Hide Loading Overlay & Focus Canvas
        if (loadingOverlay) {
          loadingOverlay.classList.add('hidden');
        }
        if (statusBadge) {
          statusBadge.style.display = 'inline-flex';
        }
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

