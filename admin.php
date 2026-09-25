<?php
/**
 * admin.php - System & Storage Maintenance Workbench
 * Decoupled architecture: communicates asynchronously with api/admin.php.
 */
$pageTitle = 'System Administration';$activeNav  = 'admin';
require_once __DIR__ . '/layout_header.php';
?>

<div class="wrapper" style="margin-bottom: 24px;">
  <!-- Section Header -->
  <div class="form-pane">
    <div class="form-section-title">&#9881; System & Storage Maintenance</div>
    <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 4px;">
      Perform filesystem audits, reconcile orphaned images, detect broken database file pointers, and maintain database integrity.
    </p>
  </div>

  <!-- Operational Action Cards -->
  <div class="form-pane" style="background: var(--surface-alt); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
      
      <!-- Card 1: Orphan Scanner -->
      <div class="editor-card" style="position: static; padding: 16px;">
        <div style="font-weight: 700; font-size: 14px; color: #fff; margin-bottom: 6px;">&#128451; Orphan Storage Files</div>
        <p style="font-size: 12px; color: var(--text-dim); line-height: 1.4; margin-bottom: 14px;">
          Find physical images inside <code>images/games/</code> and <code>images/consoles/</code> that have no corresponding database record.
        </p>
        <div style="display: flex; gap: 8px; margin-top: auto;">
          <button type="button" class="btn" style="flex: 1;" onclick="runMaintenanceAction('scan_orphans')">Scan Files</button>
          <button type="button" id="purgeBtn" class="btn danger" style="flex: 1;" onclick="confirmPurge()" disabled>Purge (<span id="orphanCount">0</span>)</button>
        </div>
      </div>

      <!-- Card 2: Broken References Check -->
      <div class="editor-card" style="position: static; padding: 16px;">
        <div style="font-weight: 700; font-size: 14px; color: #fff; margin-bottom: 6px;">&#128269; Broken Media Paths</div>
        <p style="font-size: 12px; color: var(--text-dim); line-height: 1.4; margin-bottom: 14px;">
          Check database records where <code>BoxArt</code> or <code>Image</code> paths are set, but the actual file is missing on the server.
        </p>
        <button type="button" class="btn" style="width: 100%; margin-top: auto;" onclick="runMaintenanceAction('scan_missing')">Check Broken Pointers</button>
      </div>

      <!-- Card 3: Database Optimization -->
      <div class="editor-card" style="position: static; padding: 16px;">
        <div style="font-weight: 700; font-size: 14px; color: #fff; margin-bottom: 6px;">&#9889; Database Health Check</div>
        <p style="font-size: 12px; color: var(--text-dim); line-height: 1.4; margin-bottom: 14px;">
          Run <code>ANALYZE</code> and <code>OPTIMIZE TABLE</code> queries across all core application tables to rebuild indexes.
        </p>
        <button type="button" class="btn" style="width: 100%; margin-top: auto;" onclick="runMaintenanceAction('optimize_tables')">Optimize Tables</button>
      </div>

    </div>
  </div>

  <!-- Telemetry & Diagnostic Output Console -->
  <div class="datasheet-section">
    <div class="grid-header">
      <span style="font-weight: 600;">Maintenance Log &amp; Diagnostic Telemetry</span>
      <span id="logStatusBadge" class="badge-count" style="display: none;">Ready</span>
    </div>
    <div class="form-pane">
      <div class="form-group">
        <textarea
          id="adminLogArea"
          class="form-control"
          style="height: 240px; font-family: var(--font-mono); font-size: 12px; line-height: 1.4; white-space: pre;"
          readonly
          placeholder="Diagnostic reports, scan details, and system maintenance feedback will stream here..."
        ></textarea>
      </div>
    </div>
  </div>
</div>

<script>
  let discoveredOrphans = [];

  async function runMaintenanceAction(action, payload = {}) {
    const logArea = document.getElementById('adminLogArea');
    const badge   = document.getElementById('logStatusBadge');

    badge.style.display = 'inline-flex';
    badge.textContent = 'Running...';
    logArea.value = `[${new Date().toLocaleTimeString()}] Executing operation: ${action}...\n`;

    try {
      const res = await fetch(`api/admin.php?action=${action}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });

      const rawText = await res.text();
      logArea.value += rawText + "\n";
      if (!rawText || !rawText.trim()) {
        throw new Error(`Server returned an empty response (HTTP ${res.status}). Check server error logs.`);
      }
      
      let json;
      try {
        json = JSON.parse(rawText);
      } catch (parseErr) {
        throw new Error(`Non-JSON response from server:\n${rawText.slice(0, 300)}...`);
      }

      if (!json.success) throw new Error(json.error || 'Operation failed.');

      badge.textContent = 'Completed';
      logArea.value = json.data.report;

      if (action === 'scan_orphans') {
        discoveredOrphans = json.data.orphans || [];
        const purgeBtn = document.getElementById('purgeBtn');
        document.getElementById('orphanCount').textContent = discoveredOrphans.length;
        purgeBtn.disabled = discoveredOrphans.length === 0;
      }
    } catch (err) {
      badge.textContent = 'Failed';
      logArea.value += `\n[ERROR]: ${err.message}`;
    }
  }
  
  async function confirmPurge() {
    if (!discoveredOrphans.length) return;

    const count = discoveredOrphans.length;
    const proceed = confirm(`Permanently delete ${count} orphaned file(s) from server storage?\n\nThis will remove the unreferenced image files and clean up the staging queue. This action cannot be undone.`);

    if (!proceed) return;

    const purgeBtn = document.getElementById('purgeBtn');
    purgeBtn.disabled = true;

     // Trigger the backend purge action
    await runMaintenanceAction('purge_orphans');
    
    // Reset local state & counter after execution
    discoveredOrphans = [];
    document.getElementById('orphanCount').textContent = '0';
  }
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>