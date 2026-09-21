<?php
/**
 * bulk_upload.php - Batch Import Workbench
 * 
 * Fully decoupled architecture: zero inline database queries.
 * Communicates asynchronously with api/bulk_upload.php.
 */
$pageTitle = 'Bulk Upload';$activeNav = 'bulk_upload';

require_once __DIR__ . '/layout_header.php';
?>

<div class="wrapper" style="margin-bottom: 24px;">
  <!-- Header Notification & Mode Banner -->
  <div class="form-pane">
    <div class="form-section-title">
      &#128229; Database Bulk Ingestion Workbench
    </div>
    <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 4px;">
      Batch import structured data directly into the database. Supported file types include 
      <strong>.CSV</strong>, tab-separated <strong>.TXT / .TSV</strong>, or <strong>.XLS / .XLSX</strong> spreadsheets.
      Primary keys (<code>ID</code>) are automatically skipped, unknown columns are omitted, and foreign keys are verified before insertion.
    </p>
  </div>

  <!-- Main Upload Form Container -->
  <form id="bulkUploadForm" onsubmit="handleBulkUpload(event)">
    <div class="form-pane" style="background: var(--surface-alt); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
      
      <div class="form-grid-2">
        <!-- Target Table Selector -->
        <div class="form-group">
          <label for="targetTableSelect">Target Database Table</label>
          <select id="targetTableSelect" name="table" class="form-control" onchange="updateExpectedFields()">
            <option value="Games">&#127918; Games (Catalog Library)</option>
            <option value="Consoles">&#128377; Consoles (Platforms)</option>
            <option value="Publishers">&#127970; Publishers (Studios)</option>
            <option value="Categories">&#128194; Categories (Taxonomy)</option>
            <option value="Subcategories">&#128194; Subcategories (Genres)</option>
            <option value="Languages">&#127760; Languages (Locales)</option>
          </select>
        </div>

        <!-- Ingestion Rule Summary Note -->
        <div class="form-group">
          <label>Schema Mapping &amp; Constraints</label>
          <div id="fieldSummaryBadge" style="font-size: 12px; color: var(--text-dim); padding-top: 8px;">
            Recognized Headers: <span id="schemaFieldsList" style="color: var(--border-focus); font-family: var(--font-mono);">Loading...</span>
          </div>
        </div>
      </div>

      <!-- Drag & Drop Upload Zone -->
      <div class="form-group" style="margin-top: 10px;">
        <label>Import Data File</label>
        <div 
          class="dropzone" 
          id="dropArea" 
          onclick="document.getElementById('fileInput').click()"
          ondragover="handleDragOver(event)" 
          ondragleave="handleDragLeave(event)" 
          ondrop="handleFileDrop(event)"
        >
          <div class="dropzone-empty" id="dropzoneContent">
            <span>&#128196;</span>
            <p id="dropzonePrompt">Click to select or drag and drop a <strong>.CSV</strong>, <strong>.TXT</strong>, or <strong>.XLSX</strong> file here</p>
            <small>Tab characters, commas, and semicolons are parsed automatically</small>
          </div>
          <input 
            type="file" 
            id="fileInput" 
            name="file" 
            class="hidden-file-input" 
            accept=".csv, .txt, .tsv, .xls, .xlsx" 
            onchange="handleFileSelected(event)"
          >
        </div>
      </div>

      <!-- Action Execution Bar -->
      <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
        <span id="uploadStatusText" style="font-size: 13px; color: var(--text-muted);">Ready to process.</span>
        <button type="submit" id="submitBtn" class="btn primary" disabled>
          &#128229; Execute Batch Ingestion
        </button>
      </div>

    </div>
  </form>

  <!-- Telemetry & Error Feedback Section -->
  <div class="datasheet-section">
    <div class="grid-header">
      <span style="font-weight: 600;">Ingestion Log &amp; Validation Diagnostics</span>
      <span id="resultBadge" class="badge-count" style="display: none;">0 Processed</span>
    </div>

    <div class="form-pane">
      <div class="form-group">
        <label for="errorLogArea">Process Output &amp; Row Exceptions</label>
        <textarea 
          id="errorLogArea" 
          class="form-control" 
          style="height: 180px; font-family: var(--font-mono); font-size: 12px; line-height: 1.4; white-space: pre;" 
          readonly 
          placeholder="Detailed processing results, batch counts, and field-level validation errors will appear here after execution..."
        ></textarea>
      </div>
    </div>
  </div>
</div>

<script>
  // Expected schema columns per table (excluding primary key ID)
  const schemaDefinitions = {
    Games: ['Game', 'Console ID', 'Category ID', 'Subcategory ID', 'Language ID', 'Publisher ID', 'Year', 'Tags', 'Image', 'BoxArt', 'InCollection', 'Played', 'Won', 'Comments'],
    Consoles: ['Console', 'Publisher ID', 'Year', 'Generation', 'IsHandheld', 'IsComputer', 'IsArcade', 'Image', 'Logo', 'Comments', 'Emulator', 'Emulator Link', 'EmulatorAndroid', 'EmulatorAndroid Link', 'RetroArchCore', 'Core Link', 'IsForReference'],
    Publishers: ['Publisher', 'Console Maker'],
    Categories: ['Category'],
    Subcategories: ['Category ID', 'Subcategory'],
    Languages: ['Language']
  };

  let selectedFile = null;

  document.addEventListener('DOMContentLoaded', () => {
    updateExpectedFields();
  });

  function updateExpectedFields() {
    const table = document.getElementById('targetTableSelect').value;
    const fields = schemaDefinitions[table] || [];
    document.getElementById('schemaFieldsList').textContent = fields.join(', ');
  }

  function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    document.getElementById('dropArea').classList.add('dragover');
  }

  function handleDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    document.getElementById('dropArea').classList.remove('dragover');
  }

  function handleFileDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    document.getElementById('dropArea').classList.remove('dragover');

    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      document.getElementById('fileInput').files = e.dataTransfer.files;
      setFile(e.dataTransfer.files[0]);
    }
  }

  function handleFileSelected(e) {
    if (e.target.files && e.target.files.length > 0) {
      setFile(e.target.files[0]);
    }
  }

  function setFile(file) {
    selectedFile = file;
    const prompt = document.getElementById('dropzonePrompt');
    prompt.innerHTML = `Selected File: <strong>${escapeHtml(file.name)}</strong> (${(file.size / 1024).toFixed(1)} KB)`;
    document.getElementById('submitBtn').disabled = false;
    document.getElementById('uploadStatusText').textContent = 'File attached. Click Execute to begin.';
  }

  async function handleBulkUpload(e) {
    e.preventDefault();
    if (!selectedFile) return;

    const submitBtn = document.getElementById('submitBtn');
    const statusText = document.getElementById('uploadStatusText');
    const errorBox = document.getElementById('errorLogArea');
    const resultBadge = document.getElementById('resultBadge');

    submitBtn.disabled = true;
    submitBtn.innerHTML = '&#8987; Ingesting...';
    statusText.textContent = 'Uploading and validating batch records against database constraints...';
    errorBox.value = 'Ingestion underway... Please do not close the window.';
    resultBadge.style.display = 'none';

    const formData = new FormData(document.getElementById('bulkUploadForm'));

    try {
      const res = await fetch('api/bulk_upload.php', {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: formData
      });

      const json = await res.json();
      if (!json.success) {
        throw new Error(json.error || 'Bulk upload encountered an operational error.');
      }

      const sum = json.summary;
      resultBadge.style.display = 'inline-flex';
      resultBadge.textContent = `${sum.inserted} Added / ${sum.skipped} Skipped`;

      statusText.textContent = `Batch completed: ${sum.inserted} rows inserted, ${sum.skipped} skipped.`;

      let reportLog = `=======================================================\n`;
      reportLog += ` BULK INGESTION REPORT: TABLE [${sum.table}]\n`;
      reportLog += ` Timestamp: ${new Date().toLocaleString()}\n`;
      reportLog += ` Total Records Processed: ${sum.total}\n`;
      reportLog += ` Successfully Inserted : ${sum.inserted}\n`;
      reportLog += ` Skipped / Failed Rows  : ${sum.skipped}\n`;
      reportLog += `=======================================================\n\n`;

      if (sum.error_log && sum.error_log.length > 0) {
        reportLog += `EXCEPTION DETAILS (${sum.error_log.length} errors recorded):\n`;
        reportLog += `-------------------------------------------------------\n`;
        sum.error_log.forEach((errLine, i) => {
          reportLog += `[${i + 1}] ${errLine}\n`;
        });
      } else {
        reportLog += `STATUS: All records satisfied schema and foreign key constraints without errors.\n`;
      }

      errorBox.value = reportLog;

    } catch (err) {
      statusText.textContent = 'Upload halted due to an error.';
      errorBox.value = `CRITICAL FAILURE:\n-------------------------------------------------------\n${err.message}`;
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '&#128229; Execute Batch Ingestion';
    }
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>