<?php
/**
 * src/Views/bulk_upload.php
 * Database Bulk Ingestion Workbench View.
 *
 * Implements batch import of structured data (.CSV, .TXT, .TSV, .XLS, .XLSX)
 * into target tables with schema validation, foreign key integrity checks,
 * interactive confirmation workflows, and diagnostic console logging.
 *
 * Variables provided by BulkUploadController:
 * @var array<string, array<string, mixed>> $catalog
 * @var bool $canWrite
 * @var string $pageTitle
 * @var string $activeNav
 */

declare(strict_types=1);

use Vault\Services\View;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}
?>

<!-- Vendor Spreadsheet Engine (Zero External Dependency Local Script) -->
<script src="/assets/js/xlsx.full.min.js"></script>

<style>
/* --------------------------------------------------------------------------
   Database Bulk Ingestion Workbench Scoped Styles
   -------------------------------------------------------------------------- */
.bulk-workbench {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 20px 24px;
  max-width: 1400px;
  margin: 0 auto;
  box-sizing: border-box;
}

/* Header Area */
.workbench-header {
  border-bottom: 1px solid var(--border, #1e293b);
  padding-bottom: 14px;
}
.workbench-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #cbd5e1;
  margin: 0;
}
.workbench-icon {
  font-size: 13px;
  line-height: 1;
}
.workbench-desc {
  font-size: 11px;
  color: #94a3b8;
  margin-top: 6px;
  line-height: 1.5;
  max-width: 1200px;
}
.workbench-desc strong {
  color: #e2e8f0;
  font-weight: 600;
}

/* Top Configuration Grid */
.config-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  align-items: start;
}
@media (max-width: 900px) {
  .config-grid {
    grid-template-columns: 1fr;
  }
}

.config-col {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.config-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #94a3b8;
}

.table-select-wrapper {
  position: relative;
}
.table-select {
  width: 100%;
  height: 34px;
  padding: 0 30px 0 10px;
  font-size: 12px;
  font-weight: 500;
  color: #cbd5e1;
  background: var(--surface-alt, #0c121e);
  border: 1px solid var(--border, #1e293b);
  border-radius: 4px;
  appearance: none;
  -webkit-appearance: none;
  cursor: pointer;
  outline: none;
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}
.table-select:focus {
  border-color: var(--border-focus, #38bdf8);
  box-shadow: 0 0 0 1px var(--border-focus, #38bdf8);
}
.table-select-arrow {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
  color: #64748b;
  font-size: 9px;
}

/* Schema Constraints Text Line (Matches Attachment) */
.schema-info-text {
  font-size: 11px;
  line-height: 1.5;
  color: #94a3b8;
  padding-top: 6px;
}
.recognized-headers-label {
  color: #94a3b8;
  font-weight: 500;
}
.recognized-header-tag {
  color: #38bdf8;
  font-weight: 500;
}

/* Dropzone */
.dropzone-container {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.dropzone {
  border: 1px dashed rgba(56, 189, 248, 0.25);
  border-radius: 6px;
  background: rgba(12, 18, 30, 0.4);
  padding: 32px 20px;
  text-align: center;
  cursor: pointer;
  transition: all var(--transition-fast);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  user-select: none;
  position: relative;
}
.dropzone:hover,
.dropzone.dragover {
  border-color: var(--border-focus, #38bdf8);
  background: rgba(56, 189, 248, 0.05);
}
.dropzone-icon {
  font-size: 24px;
  opacity: 0.85;
  line-height: 1;
  margin-bottom: 6px;
}
.dropzone-main-text {
  font-size: 12px;
  font-weight: 500;
  color: #cbd5e1;
}
.dropzone-sub-text {
  font-size: 11px;
  color: #64748b;
}

/* File Loaded Pill */
.file-loaded-banner {
  display: none;
  align-items: center;
  justify-content: space-between;
  padding: 8px 14px;
  background: rgba(2, 132, 199, 0.1);
  border: 1px solid rgba(56, 189, 248, 0.3);
  border-radius: 4px;
  margin-top: 4px;
}
.file-loaded-left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.file-loaded-icon {
  font-size: 16px;
}
.file-loaded-title {
  font-size: 12px;
  font-weight: 700;
  color: #fff;
}
.file-loaded-meta {
  font-size: 11px;
  color: #94a3b8;
}
.file-btn-remove {
  background: transparent;
  border: 1px solid var(--border, #1e293b);
  color: #94a3b8;
  border-radius: 3px;
  padding: 2px 8px;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  transition: all var(--transition-fast);
}
.file-btn-remove:hover {
  border-color: var(--danger, #ef4444);
  color: #fca5a5;
  background: var(--danger-surface, rgba(239, 68, 68, 0.12));
}

/* Workbench Action Bar */
.action-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 8px 0;
}
.action-status {
  font-size: 11px;
  font-weight: 500;
  color: #64748b;
  display: flex;
  align-items: center;
  gap: 8px;
}
.status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--text-dim, #64748b);
  display: inline-block;
}
.status-dot.active {
  background: var(--border-focus, #38bdf8);
  box-shadow: 0 0 6px var(--border-focus, #38bdf8);
}
.status-dot.success {
  background: var(--success, #10b981);
  box-shadow: 0 0 6px var(--success, #10b981);
}
.status-dot.error {
  background: var(--danger, #ef4444);
  box-shadow: 0 0 6px var(--danger, #ef4444);
}
.status-dot.warning {
  background: var(--warning, #f59e0b);
  box-shadow: 0 0 6px var(--warning, #f59e0b);
}

.btn-execute {
  height: 32px;
  padding: 0 16px;
  font-size: 12px;
  font-weight: 600;
  border-radius: 4px;
  background: var(--accent, #0284c7);
  border: none;
  color: #ffffff;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  transition: all var(--transition-fast);
}
.btn-execute:hover:not(:disabled) {
  background: var(--accent-hover, #0369a1);
}
.btn-execute:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Console & Diagnostics Section */
.diagnostics-section {
  border-top: 1px solid var(--border, #1e293b);
  padding-top: 14px;
  margin-top: 4px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.diagnostics-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.diagnostics-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #cbd5e1;
}
.diagnostics-sub {
  font-size: 11px;
  color: #64748b;
  margin-top: 4px;
}
.diagnostics-tools {
  display: flex;
  gap: 6px;
  align-items: center;
}
.console-tool-btn {
  background: #0c121e;
  border: 1px solid var(--border, #1e293b);
  color: #94a3b8;
  border-radius: 3px;
  padding: 2px 8px;
  height: 22px;
  font-size: 10px;
  font-weight: 600;
  cursor: pointer;
  transition: all var(--transition-fast);
}
.console-tool-btn:hover {
  background: var(--panel-hover, #1c273e);
  color: #fff;
  border-color: var(--border-focus, #38bdf8);
}

/* Terminal Console Viewport */
.console-terminal {
  background: #080c16;
  border: 1px solid var(--border, #1e293b);
  border-radius: 4px;
  padding: 12px 14px;
  height: 240px;
  min-height: 240px;
  max-height: 240px;
  overflow-y: auto;
  font-family: var(--font-mono);
  font-size: 11px;
  line-height: 1.65;
  color: #cbd5e1;
  box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.4);
}
.console-placeholder {
  color: #475569;
  font-style: normal;
}
.log-line {
  display: block;
  word-break: break-word;
  white-space: pre-wrap;
}
.log-time {
  color: #64748b;
  user-select: none;
  margin-right: 8px;
}
.badge-info {
  color: #38bdf8;
  font-weight: 700;
}
.badge-ok {
  color: #10b981;
  font-weight: 700;
}
.badge-not-ok {
  color: #ef4444;
  font-weight: 700;
}
.badge-warn {
  color: #f59e0b;
  font-weight: 700;
}
.badge-summary {
  color: #f8fafc;
  font-weight: 800;
  background: rgba(56, 189, 248, 0.15);
  padding: 1px 6px;
  border-radius: 3px;
}

/* Modals */
.bulk-modal-backdrop {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(3px);
  -webkit-backdrop-filter: blur(3px);
  z-index: 9999;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.bulk-modal-backdrop.open {
  display: flex;
}
.bulk-modal-card {
  background: var(--panel, #151d30);
  border: 1px solid var(--border, #1e293b);
  border-radius: 8px;
  width: 100%;
  max-width: 480px;
  padding: 20px;
  box-shadow: var(--shadow-lg);
  display: flex;
  flex-direction: column;
  gap: 14px;
  animation: modalFadeIn 0.15s ease-out;
}
@keyframes modalFadeIn {
  from { opacity: 0; transform: translateY(-8px) scale(0.98); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.modal-header-row {
  display: flex;
  align-items: center;
  gap: 10px;
}
.modal-icon {
  font-size: 20px;
  line-height: 1;
}
.modal-title {
  font-size: 14px;
  font-weight: 700;
  color: #fff;
}
.modal-body-text {
  font-size: 12px;
  line-height: 1.6;
  color: #94a3b8;
}
.modal-alert-box {
  padding: 10px 14px;
  border-radius: 4px;
  font-size: 12px;
  line-height: 1.5;
  background: rgba(56, 189, 248, 0.08);
  border: 1px solid rgba(56, 189, 248, 0.25);
  color: #7dd3fc;
  display: flex;
  align-items: center;
  gap: 8px;
}
.modal-alert-box.warning {
  background: rgba(245, 158, 11, 0.08);
  border-color: rgba(245, 158, 11, 0.25);
  color: #fde68a;
}
.modal-alert-box.danger {
  background: rgba(239, 68, 68, 0.08);
  border-color: rgba(239, 68, 68, 0.25);
  color: #fca5a5;
}
.modal-footer-row {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 6px;
}
.modal-btn {
  height: 32px;
  padding: 0 14px;
  font-size: 12px;
  font-weight: 600;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all var(--transition-fast);
}
.modal-btn-cancel {
  background: #0c121e;
  border: 1px solid #1e293b;
  color: #cbd5e1;
}
.modal-btn-cancel:hover {
  background: #1c273e;
  color: #fff;
  border-color: #334155;
}
.modal-btn-primary {
  background: var(--accent, #0284c7);
  border: 1px solid var(--border-focus, #38bdf8);
  color: #fff;
}
.modal-btn-primary:hover {
  background: var(--accent-hover, #0369a1);
}
.modal-btn-secondary {
  background: #1e293b;
  border: 1px solid #334155;
  color: #cbd5e1;
}
.modal-btn-secondary:hover {
  background: #334155;
  color: #fff;
}
</style>

<div class="bulk-workbench">
  <!-- 1. Header Section -->
  <div class="workbench-header">
    <h1 class="workbench-title">
      <span class="workbench-icon">🗄️</span> DATABASE BULK INGESTION WORKBENCH
    </h1>
    <p class="workbench-desc">
      Batch import structured data directly into the database. Supported file types include <strong>.CSV</strong>, tab-separated <strong>.TXT / .TSV</strong>, or <strong>.XLS / .XLSX</strong> spreadsheets. Primary keys (ID) are automatically skipped, unknown columns are omitted, and foreign keys are verified before insertion.
    </p>
  </div>

  <!-- 2. Configuration & Schema Mapping Grid -->
  <div class="config-grid">
    <!-- Left: Target Database Table Selector -->
    <div class="config-col">
      <label for="targetTableSelect" class="config-label">Target Database Table</label>
      <div class="table-select-wrapper">
        <select id="targetTableSelect" class="table-select" <?= !$canWrite ? 'disabled' : '' ?>>
          <?php foreach ($catalog as $tKey => $tMeta): ?>
            <option value="<?= View::e($tKey) ?>">
              <?= View::e($tMeta['icon'] . ' ' . $tMeta['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="table-select-arrow">▼</span>
      </div>
    </div>

    <!-- Right: Schema Mapping & Constraints Display -->
    <div class="config-col">
      <div class="config-label">Schema Mapping & Constraints</div>
      <div class="schema-info-text">
        <span class="recognized-headers-label">Recognized Headers:&nbsp;</span><span id="recognizedHeadersDisplay">Loading schema...</span>
      </div>
    </div>
  </div>

  <!-- 3. Import Data File Dropzone -->
  <div class="dropzone-container">
    <div class="config-label">Import Data File</div>
    <div id="dropzone" class="dropzone" tabindex="0">
      <input type="file" id="fileInput" accept=".csv, .txt, .tsv, .xls, .xlsx" style="display: none;" <?= !$canWrite ? 'disabled' : '' ?>>
      <div class="dropzone-icon">📄</div>
      <div class="dropzone-main-text">Click to select or drag and drop a .CSV, .TXT, or .XLSX file here</div>
      <div class="dropzone-sub-text">Tab characters, commas, and semicolons are parsed automatically</div>
    </div>

    <!-- File Information Pill (Shown upon file loading) -->
    <div id="fileLoadedBanner" class="file-loaded-banner">
      <div class="file-loaded-left">
        <div class="file-loaded-icon">📊</div>
        <div>
          <div id="fileLoadedName" class="file-loaded-title">sample_file.csv</div>
          <div id="fileLoadedMeta" class="file-loaded-meta">0 KB &bull; 0 records detected</div>
        </div>
      </div>
      <button type="button" id="btnRemoveFile" class="file-btn-remove">✕ Change File</button>
    </div>
  </div>

  <!-- 4. Action Bar -->
  <div class="action-bar">
    <div class="action-status">
      <span id="statusDot" class="status-dot"></span>
      <span id="statusText">Ready to process.</span>
    </div>

    <div>
      <?php if (!$canWrite): ?>
        <span style="font-size: 11px; color: var(--warning, #f59e0b); margin-right: 12px;">⚠️ Read-Only Permissions: Ingestion Disabled</span>
      <?php endif; ?>
      <button type="button" id="btnExecuteBatch" class="btn-execute" disabled>
        <span>📥</span> Execute Batch Ingestion
      </button>
    </div>
  </div>

  <!-- 5. Ingestion Log & Validation Diagnostics -->
  <div class="diagnostics-section">
    <div class="diagnostics-header">
      <div>
        <div class="diagnostics-title">Ingestion Log & Validation Diagnostics</div>
        <div class="diagnostics-sub">Process Output & Row Exceptions</div>
      </div>
      <div class="diagnostics-tools">
        <button type="button" id="btnClearConsole" class="console-tool-btn" title="Clear console output">🧹 Clear</button>
        <button type="button" id="btnCopyConsole" class="console-tool-btn" title="Copy console text">📋 Copy Log</button>
      </div>
    </div>

    <div id="consoleTerminal" class="console-terminal">
      <div id="consolePlaceholder" class="console-placeholder">Detailed processing results, batch counts, and field-level validation errors will appear here after execution...</div>
    </div>
  </div>
</div>

<!-- ========================================================================
     MODALS
     ======================================================================== -->

<!-- Modal 1: Pre-Execution Warning Confirmation Modal -->
<div id="modalConfirmStart" class="bulk-modal-backdrop" role="dialog" aria-modal="true">
  <div class="bulk-modal-card">
    <div class="modal-header-row">
      <div class="modal-icon">🗄️</div>
      <div class="modal-title">Confirm Bulk Upload</div>
    </div>
    <div class="modal-alert-box">
      <span>Note: Verified rows will be inserted directly into the database. Please review your file data before starting.</span>
    </div>
    <div class="modal-body-text" id="modalConfirmStartText">
      You are about to start bulk upload for table <strong id="modalConfirmTargetTable">games</strong> with <strong id="modalConfirmRowCount">0</strong> detected row(s). The system will first check all fields against database types and constraints.
    </div>
    <div class="modal-footer-row">
      <button type="button" id="btnModalCancelStart" class="modal-btn modal-btn-cancel">Cancel</button>
      <button type="button" id="btnModalProceedStart" class="modal-btn modal-btn-primary">Start Upload</button>
    </div>
  </div>
</div>

<!-- Modal 2: Partial Validation Failure Confirmation Modal -->
<div id="modalPartialFailure" class="bulk-modal-backdrop" role="dialog" aria-modal="true">
  <div class="bulk-modal-card">
    <div class="modal-header-row">
      <div class="modal-icon">⚠️</div>
      <div class="modal-title">Some Records Need Review</div>
    </div>
    <div class="modal-alert-box warning">
      <span>We found issues in some of your rows, while other rows passed validation and are ready to import.</span>
    </div>
    <div class="modal-body-text">
      Validation results for your file:
      <ul style="margin: 8px 0 10px 20px; line-height: 1.8;">
        <li><strong style="color: var(--success, #10b981);" id="modalCountOk">0</strong> row(s) passed validation and can be saved.</li>
        <li><strong style="color: var(--danger, #ef4444);" id="modalCountNotOk">0</strong> row(s) have errors and will be skipped (see log below).</li>
      </ul>
      Would you like to proceed with uploading the valid rows only, or cancel to fix your file first?
    </div>
    <div class="modal-footer-row">
      <button type="button" id="btnModalHaltProcess" class="modal-btn modal-btn-cancel">Cancel & Fix File</button>
      <button type="button" id="btnModalInsertOkOnly" class="modal-btn modal-btn-primary">Upload Valid Records Only</button>
    </div>
  </div>
</div>

<!-- Modal 3: All Records Failed Modal -->
<div id="modalAllFailed" class="bulk-modal-backdrop" role="dialog" aria-modal="true">
  <div class="bulk-modal-card">
    <div class="modal-header-row">
      <div class="modal-icon">❌</div>
      <div class="modal-title">Upload Could Not Proceed</div>
    </div>
    <div class="modal-alert-box danger">
      <span>None of the rows passed validation. Zero records have been inserted into your database.</span>
    </div>
    <div class="modal-body-text">
      All <strong id="modalAllFailedCount">0</strong> record(s) encountered validation errors. Please check the detailed diagnostic messages in the console below to correct any column names, required fields, or missing IDs, then try uploading again.
    </div>
    <div class="modal-footer-row">
      <button type="button" id="btnModalDismissFailed" class="modal-btn modal-btn-primary">Close & Review Log</button>
    </div>
  </div>
</div>

<!-- ========================================================================
     CLIENT-SIDE APPLICATION LOGIC
     ======================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Catalog schema passed from server
  const schemaCatalog = <?= json_encode($catalog, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const canWrite = <?= $canWrite ? 'true' : 'false' ?>;

  // DOM Elements
  const targetTableSelect = document.getElementById('targetTableSelect');
  const recognizedHeadersDisplay = document.getElementById('recognizedHeadersDisplay');
  const dropzone = document.getElementById('dropzone');
  const fileInput = document.getElementById('fileInput');
  const fileLoadedBanner = document.getElementById('fileLoadedBanner');
  const fileLoadedName = document.getElementById('fileLoadedName');
  const fileLoadedMeta = document.getElementById('fileLoadedMeta');
  const btnRemoveFile = document.getElementById('btnRemoveFile');
  const btnExecuteBatch = document.getElementById('btnExecuteBatch');
  const statusDot = document.getElementById('statusDot');
  const statusText = document.getElementById('statusText');
  const consoleTerminal = document.getElementById('consoleTerminal');
  const consolePlaceholder = document.getElementById('consolePlaceholder');
  const btnClearConsole = document.getElementById('btnClearConsole');
  const btnCopyConsole = document.getElementById('btnCopyConsole');

  // Modals
  const modalConfirmStart = document.getElementById('modalConfirmStart');
  const modalConfirmTargetTable = document.getElementById('modalConfirmTargetTable');
  const modalConfirmRowCount = document.getElementById('modalConfirmRowCount');
  const btnModalCancelStart = document.getElementById('btnModalCancelStart');
  const btnModalProceedStart = document.getElementById('btnModalProceedStart');

  const modalPartialFailure = document.getElementById('modalPartialFailure');
  const modalCountOk = document.getElementById('modalCountOk');
  const modalCountNotOk = document.getElementById('modalCountNotOk');
  const btnModalHaltProcess = document.getElementById('btnModalHaltProcess');
  const btnModalInsertOkOnly = document.getElementById('btnModalInsertOkOnly');

  const modalAllFailed = document.getElementById('modalAllFailed');
  const modalAllFailedCount = document.getElementById('modalAllFailedCount');
  const btnModalDismissFailed = document.getElementById('btnModalDismissFailed');

  // Active State
  let activeFile = null;
  let parsedRows = [];
  let detectedHeaders = [];
  let isProcessing = false;
  let pendingValidationResult = null;

  // --------------------------------------------------------------------------
  // 1. Schema Display Synchronization
  // --------------------------------------------------------------------------
  function updateSchemaDisplay() {
    const tableKey = targetTableSelect.value;
    const schema = schemaCatalog[tableKey];
    if (!schema) {
      recognizedHeadersDisplay.textContent = 'Schema unavailable';
      return;
    }

    const headersStr = schema.recognized_headers || '';
    const parts = headersStr.split(',').map(s => s.trim()).filter(Boolean);
    recognizedHeadersDisplay.innerHTML = parts.map(p => `<span class="recognized-header-tag">${escapeHtml(p)}</span>`).join(', ');
  }

  targetTableSelect.addEventListener('change', () => {
    updateSchemaDisplay();
    if (parsedRows.length > 0) {
      logLine(`Target table changed to: ${targetTableSelect.value}`, 'INFO');
    }
  });
  updateSchemaDisplay();

  // --------------------------------------------------------------------------
  // 2. Logging & Console Diagnostics Engine
  // --------------------------------------------------------------------------
  function getTimestamp() {
    const now = new Date();
    return now.toTimeString().split(' ')[0] + '.' + String(now.getMilliseconds()).padStart(3, '0');
  }

  function logLine(message, type = 'INFO') {
    if (consolePlaceholder) {
      consolePlaceholder.style.display = 'none';
    }

    const line = document.createElement('div');
    line.className = 'log-line';

    let badgeClass = 'badge-info';
    let badgeText = `[${type}]`;

    if (type === 'OK') badgeClass = 'badge-ok';
    else if (type === 'NOT OK' || type === 'ERROR') badgeClass = 'badge-not-ok';
    else if (type === 'WARN') badgeClass = 'badge-warn';
    else if (type === 'SUMMARY') badgeClass = 'badge-summary';

    line.innerHTML = `<span class="log-time">${getTimestamp()}</span> <span class="${badgeClass}">${badgeText}</span> ${escapeHtml(message)}`;
    consoleTerminal.appendChild(line);
    consoleTerminal.scrollTop = consoleTerminal.scrollHeight;
  }

  function clearConsole() {
    consoleTerminal.innerHTML = '';
    const p = document.createElement('div');
    p.id = 'consolePlaceholder';
    p.className = 'console-placeholder';
    p.textContent = 'Detailed processing results, batch counts, and field-level validation errors will appear here after execution...';
    consoleTerminal.appendChild(p);
  }

  btnClearConsole.addEventListener('click', clearConsole);

  btnCopyConsole.addEventListener('click', () => {
    const text = consoleTerminal.innerText;
    if (!text || text.includes('Detailed processing results')) {
      showToast('Console is empty.', 'info');
      return;
    }
    navigator.clipboard.writeText(text).then(() => {
      showToast('Console log copied to clipboard.', 'success');
    }).catch(() => {
      showToast('Could not copy log text.', 'error');
    });
  });

  // --------------------------------------------------------------------------
  // 3. File Dropzone & Client-Side File Parsing
  // --------------------------------------------------------------------------
  dropzone.addEventListener('click', () => {
    if (!canWrite || isProcessing) return;
    fileInput.click();
  });

  dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    if (!canWrite || isProcessing) return;
    dropzone.classList.add('dragover');
  });

  dropzone.addEventListener('dragleave', () => {
    dropzone.classList.remove('dragover');
  });

  dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('dragover');
    if (!canWrite || isProcessing) return;

    if (e.dataTransfer && e.dataTransfer.files.length > 0) {
      handleSelectedFile(e.dataTransfer.files[0]);
    }
  });

  fileInput.addEventListener('change', () => {
    if (fileInput.files.length > 0) {
      handleSelectedFile(fileInput.files[0]);
    }
  });

  btnRemoveFile.addEventListener('click', () => {
    if (isProcessing) return;
    resetFileSelection();
  });

  function resetFileSelection() {
    activeFile = null;
    parsedRows = [];
    detectedHeaders = [];
    pendingValidationResult = null;
    fileInput.value = '';

    fileLoadedBanner.style.display = 'none';
    dropzone.style.display = 'flex';
    btnExecuteBatch.disabled = true;

    setStatus('Ready to process.', '');
    logLine('File selection cleared.', 'INFO');
  }

  function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(2) + ' MB';
  }

  function handleSelectedFile(file) {
    if (!file) return;

    const validExtensions = ['.csv', '.txt', '.tsv', '.xls', '.xlsx'];
    const fileName = file.name.toLowerCase();
    const hasValidExt = validExtensions.some(ext => fileName.endsWith(ext));

    if (!hasValidExt) {
      showToast('Unsupported file type. Allowed: .CSV, .TXT, .TSV, .XLS, .XLSX', 'error');
      logLine(`File rejected: '${file.name}' has an unsupported extension.`, 'ERROR');
      return;
    }

    activeFile = file;
    setStatus('Parsing file structure...', 'active');
    logLine(`File selected: '${file.name}' (${formatBytes(file.size)}). Reading data...`, 'INFO');

    const reader = new FileReader();

    reader.onload = (e) => {
      try {
        const buffer = e.target.result;
        let rows = [];

        if (typeof XLSX !== 'undefined') {
          // Parse using SheetJS (XLSX, XLS, CSV, TSV)
          const workbook = XLSX.read(buffer, { type: 'array' });
          const firstSheetName = workbook.SheetNames[0];
          if (!firstSheetName) {
            throw new Error('Spreadsheet has no worksheets.');
          }
          const worksheet = workbook.Sheets[firstSheetName];
          rows = XLSX.utils.sheet_to_json(worksheet, { defval: '', raw: false });
        } else {
          // Fallback simple CSV/TSV parser if XLSX library unavailable
          const text = new TextDecoder('utf-8').decode(buffer);
          rows = parseDelimitedText(text);
        }

        if (!rows || rows.length === 0) {
          throw new Error('File contains no data rows or could not be parsed.');
        }

        parsedRows = rows;
        detectedHeaders = Object.keys(rows[0] || {});

        // Update UI Banner
        fileLoadedName.textContent = file.name;
        fileLoadedMeta.textContent = `${formatBytes(file.size)} • ${rows.length} record(s) detected • Headers: [${detectedHeaders.slice(0, 6).join(', ')}${detectedHeaders.length > 6 ? '...' : ''}]`;
        fileLoadedBanner.style.display = 'flex';
        dropzone.style.display = 'none';

        if (canWrite) {
          btnExecuteBatch.disabled = false;
        }

        setStatus(`File loaded: ${rows.length} records ready for ingestion.`, 'success');
        logLine(`Successfully parsed ${rows.length} rows with ${detectedHeaders.length} headers: [${detectedHeaders.join(', ')}]`, 'OK');
      } catch (err) {
        logLine(`Error parsing '${file.name}': ${err.message}`, 'ERROR');
        showToast('Error parsing file: ' + err.message, 'error');
        resetFileSelection();
      }
    };

    reader.onerror = () => {
      logLine(`Failed to read file: '${file.name}'.`, 'ERROR');
      showToast('Could not read file from disk.', 'error');
      resetFileSelection();
    };

    reader.readAsArrayBuffer(file);
  }

  /**
   * Fallback pure-JS CSV / TSV text parser
   */
  function parseDelimitedText(text) {
    const lines = text.split(/\r?\n/).filter(line => line.trim() !== '');
    if (lines.length < 2) return [];

    const firstLine = lines[0];
    const delimiter = (firstLine.split('\t').length > firstLine.split(',').length) ? '\t' : ',';
    const headers = firstLine.split(delimiter).map(h => h.trim().replace(/^["']|["']$/g, ''));

    const rows = [];
    for (let i = 1; i < lines.length; i++) {
      const parts = lines[i].split(delimiter).map(p => p.trim().replace(/^["']|["']$/g, ''));
      if (parts.length === 0 || (parts.length === 1 && parts[0] === '')) continue;
      const row = {};
      headers.forEach((h, idx) => {
        row[h] = parts[idx] !== undefined ? parts[idx] : '';
      });
      rows.push(row);
    }
    return rows;
  }

  function setStatus(text, dotClass = '') {
    statusText.textContent = text;
    statusDot.className = 'status-dot ' + dotClass;
  }

  // --------------------------------------------------------------------------
  // 4. Modal Flow & Confirmation Handlers
  // --------------------------------------------------------------------------
  btnExecuteBatch.addEventListener('click', () => {
    if (!canWrite || isProcessing || parsedRows.length === 0) return;

    const tableKey = targetTableSelect.value;
    const schema = schemaCatalog[tableKey] || { label: tableKey };

    modalConfirmTargetTable.textContent = `${schema.label} (${tableKey})`;
    modalConfirmRowCount.textContent = String(parsedRows.length);
    modalConfirmStart.classList.add('open');
  });

  btnModalCancelStart.addEventListener('click', () => {
    modalConfirmStart.classList.remove('open');
    logLine('User cancelled bulk ingestion prompt.', 'WARN');
  });

  btnModalProceedStart.addEventListener('click', async () => {
    modalConfirmStart.classList.remove('open');
    await startValidationAndIngestionProcess();
  });

  btnModalDismissFailed.addEventListener('click', () => {
    modalAllFailed.classList.remove('open');
  });

  btnModalHaltProcess.addEventListener('click', () => {
    modalPartialFailure.classList.remove('open');
    haltProcessByUser();
  });

  btnModalInsertOkOnly.addEventListener('click', async () => {
    modalPartialFailure.classList.remove('open');
    if (!pendingValidationResult) return;
    await executeInsertion(pendingValidationResult.okRecords, 'PARTIAL');
  });

  // --------------------------------------------------------------------------
  // 5. Ingestion Pipeline: Validation Pass
  // --------------------------------------------------------------------------
  async function startValidationAndIngestionProcess() {
    isProcessing = true;
    btnExecuteBatch.disabled = true;
    targetTableSelect.disabled = true;
    setStatus('Running schema and constraint validation...', 'active');

    const table = targetTableSelect.value;
    logLine(`Starting bulk ingestion workbench for table '${table}'...`, 'INFO');
    logLine(`Validating ${parsedRows.length} record(s) against schema, data types, and foreign keys...`, 'INFO');

    try {
      const response = await fetch('/api/bulk-upload/validate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          table: table,
          records: parsedRows,
        }),
      });

      const resJson = await response.json();
      if (!resJson.success) {
        throw new Error(resJson.error || 'Server validation failed.');
      }

      const valData = resJson.data;
      const records = valData.records || [];
      const validCount = valData.valid_count;
      const invalidCount = valData.invalid_count;
      const totalCount = valData.total_records;

      // Log ignored columns if any
      if (valData.ignored_columns && valData.ignored_columns.length > 0) {
        logLine(`Ignored column(s) in upload file: [${valData.ignored_columns.join(', ')}]`, 'WARN');
      }
      logLine(`Mapped table column(s): [${valData.mapped_columns.join(', ')}]`, 'INFO');

      // Log each record's diagnostic state
      const okRecords = [];
      records.forEach((rec) => {
        if (rec.status === 'OK') {
          okRecords.push(rec);
          logLine(`Row ${rec.row_number}: [OK] Record verified. Passed constraint validation.`, 'OK');
        } else {
          const errStr = (rec.errors || []).join('; ');
          logLine(`Row ${rec.row_number}: [NOT OK] Validation failed - ${errStr}`, 'NOT OK');
        }
      });

      pendingValidationResult = {
        table: table,
        totalCount: totalCount,
        validCount: validCount,
        invalidCount: invalidCount,
        okRecords: okRecords,
      };

      // ----------------------------------------------------------------------
      // Decision Matrix Based on User Rules:
      // A) All records are OK -> Proceed with insert automatically (no confirmation needed)
      // B) Records NOT OK exist -> Ask user to confirm: insert OK records only OR halt
      // C) All records NOT OK -> Halt insert automatically
      // ----------------------------------------------------------------------
      if (valData.all_ok) {
        logLine(`All ${totalCount} records are valid! Proceeding directly to database insertion...`, 'INFO');
        await executeInsertion(okRecords, 'FULL');
      } else if (valData.all_invalid) {
        logLine(`All ${totalCount} records failed validation. Automatic insertion halted.`, 'ERROR');
        printSummary(totalCount, 0, totalCount, 'FAILED_ALL_INVALID');
        modalAllFailedCount.textContent = String(totalCount);
        modalAllFailed.classList.add('open');
        finishProcess('All records failed validation. Zero records inserted.', 'error');
      } else {
        // Partial failure: Ask user confirmation
        logLine(`Validation completed with issues: ${validCount} valid record(s), ${invalidCount} invalid record(s).`, 'WARN');
        modalCountOk.textContent = String(validCount);
        modalCountNotOk.textContent = String(invalidCount);
        modalPartialFailure.classList.add('open');
        setStatus(`Validation issues detected: ${validCount} OK, ${invalidCount} Invalid. Waiting for user decision...`, 'warning');
      }
    } catch (err) {
      logLine(`Validation error: ${err.message}`, 'ERROR');
      showToast('Validation failed: ' + err.message, 'error');
      finishProcess('Validation failed: ' + err.message, 'error');
    }
  }

  // --------------------------------------------------------------------------
  // 6. Ingestion Pipeline: Execution Pass
  // --------------------------------------------------------------------------
  async function executeInsertion(recordsToInsert, mode = 'FULL') {
    setStatus(`Inserting ${recordsToInsert.length} record(s) into database...`, 'active');
    const table = targetTableSelect.value;
    logLine(`Executing transaction insert for ${recordsToInsert.length} record(s) into '${table}'...`, 'INFO');

    try {
      const response = await fetch('/api/bulk-upload/execute', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          table: table,
          records: recordsToInsert,
        }),
      });

      const resJson = await response.json();
      if (!resJson.success) {
        throw new Error(resJson.error || 'Server error during batch insertion.');
      }

      const execData = resJson.data;
      const uploadedCount = execData.uploaded_count || 0;
      const failedCount = (pendingValidationResult ? pendingValidationResult.totalCount : recordsToInsert.length) - uploadedCount;
      const totalCount = pendingValidationResult ? pendingValidationResult.totalCount : recordsToInsert.length;

      if (execData.errors && execData.errors.length > 0) {
        execData.errors.forEach(err => logLine(err, 'ERROR'));
      }

      logLine(`Database transaction committed successfully!`, 'OK');
      printSummary(totalCount, uploadedCount, failedCount, mode === 'FULL' ? 'COMPLETED_SUCCESS' : 'COMPLETED_PARTIAL');

      showToast(`Bulk upload finished: ${uploadedCount} record(s) inserted.`, 'success');
      finishProcess(`Bulk upload completed: ${uploadedCount} record(s) inserted.`, 'success');
    } catch (err) {
      logLine(`Insertion failed: ${err.message}`, 'ERROR');
      showToast('Insertion failed: ' + err.message, 'error');
      finishProcess('Insertion failed: ' + err.message, 'error');
    }
  }

  function haltProcessByUser() {
    const totalCount = pendingValidationResult ? pendingValidationResult.totalCount : parsedRows.length;
    logLine(`Process halted by user choice. No changes were made to the database.`, 'WARN');
    printSummary(totalCount, 0, totalCount, 'HALTED_BY_USER');
    showToast('Ingestion halted. Zero records inserted.', 'info');
    finishProcess('Ingestion halted by user. Zero records inserted.', 'warning');
  }

  function printSummary(total, uploaded, notUploaded, status) {
    logLine(`------------------------------------------------------------`, 'SUMMARY');
    logLine(`=== BATCH INGESTION SUMMARY ===`, 'SUMMARY');
    logLine(`Total Records Evaluated : ${total}`, 'SUMMARY');
    logLine(`Successfully Uploaded   : ${uploaded}`, 'SUMMARY');
    logLine(`Not Uploaded (Errors)   : ${notUploaded}`, 'SUMMARY');
    logLine(`Final Execution Status  : ${status}`, 'SUMMARY');
    logLine(`------------------------------------------------------------`, 'SUMMARY');
  }

  function finishProcess(statusMsg, dotType = '') {
    isProcessing = false;
    pendingValidationResult = null;
    targetTableSelect.disabled = false;
    if (canWrite && parsedRows.length > 0) {
      btnExecuteBatch.disabled = false;
    }
    setStatus(statusMsg, dotType);
  }

  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
});
</script>
