<?php
/**
 * src/Views/reports.php
 * Curated Collection Audits & Custom Dynamic Report Engine.
 *
 * Variables provided by ReportController:
 * @var array{physical_inventory: int, incomplete_media: int, cleared_beaten: int} $stats
 * @var array<string, array{table: string, label: string, icon: string, total_records: int, columns: array<int, array<string, mixed>>}> $catalog
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
   Reports & Audits Workbench Scoped Styles
   -------------------------------------------------------------------------- */
.reports-container {
  display: flex;
  flex-direction: column;
  gap: 28px;
  padding: 20px 24px 48px;
  max-width: 1400px;
  margin: 0 auto;
  box-sizing: border-box;
}

/* Section Header Typography */
.report-section-header {
  display: flex;
  align-items: center;
  gap: 8px;
  border-bottom: 1px solid var(--border, #1e293b);
  padding-bottom: 12px;
  margin-bottom: 16px;
}
.report-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #cbd5e1;
  margin: 0;
}
.report-section-icon {
  font-size: 13px;
  line-height: 1;
}

/* --------------------------------------------------------------------------
   Section 1: Curated Collection Audits Cards Grid
   -------------------------------------------------------------------------- */
.audits-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}
@media (max-width: 1024px) {
  .audits-grid {
    grid-template-columns: 1fr;
  }
}

.audit-card {
  background: rgba(15, 23, 42, 0.65);
  border: 1px solid var(--border, #1e293b);
  border-radius: 8px;
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 190px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
  transition: border-color var(--transition-fast), transform var(--transition-fast);
}
.audit-card:hover {
  border-color: rgba(56, 189, 248, 0.35);
  transform: translateY(-1px);
}

.audit-card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 14px;
}
.audit-card-icon {
  font-size: 26px;
  line-height: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.audit-badge {
  background: rgba(2, 132, 199, 0.15);
  border: 1px solid rgba(56, 189, 248, 0.35);
  color: #38bdf8;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 10px;
  border-radius: 9999px;
  letter-spacing: 0.02em;
  white-space: nowrap;
}

.audit-card-title {
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #f8fafc;
  line-height: 1.35;
  margin: 0 0 8px 0;
}
.audit-card-desc {
  font-size: 11px;
  color: #94a3b8;
  line-height: 1.5;
  margin: 0 0 18px 0;
  flex-grow: 1;
}

.audit-formats {
  display: flex;
  align-items: center;
  gap: 8px;
}
.btn-format {
  background: #0c121e;
  border: 1px solid var(--border, #1e293b);
  color: #cbd5e1;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.03em;
  padding: 5px 12px;
  border-radius: 4px;
  cursor: pointer;
  outline: none;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: all var(--transition-fast);
  user-select: none;
}
.btn-format:hover:not(:disabled) {
  background: #172554;
  color: #38bdf8;
  border-color: #38bdf8;
  box-shadow: 0 0 8px rgba(56, 189, 248, 0.2);
}
.btn-format:disabled {
  opacity: 0.5;
  cursor: wait;
}

/* --------------------------------------------------------------------------
   Section 2: Custom Report Builder
   -------------------------------------------------------------------------- */
.custom-builder-card {
  background: rgba(15, 23, 42, 0.65);
  border: 1px solid var(--border, #1e293b);
  border-radius: 8px;
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 18px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
}

/* Builder Controls Row */
.builder-controls-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
  padding-bottom: 14px;
  border-bottom: 1px solid rgba(30, 41, 59, 0.7);
}

.table-selector-group {
  display: flex;
  align-items: center;
  gap: 12px;
}
.table-selector-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #94a3b8;
  white-space: nowrap;
}
.table-select-wrapper {
  position: relative;
  min-width: 280px;
}
.table-select {
  width: 100%;
  height: 34px;
  padding: 0 32px 0 12px;
  font-size: 12px;
  font-weight: 600;
  color: #f1f5f9;
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

.table-record-pill {
  font-size: 11px;
  color: #94a3b8;
  background: rgba(12, 18, 30, 0.8);
  border: 1px solid var(--border, #1e293b);
  padding: 4px 10px;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.table-record-pill strong {
  color: #38bdf8;
  font-weight: 700;
}

/* Builder Actions Cluster */
.builder-actions-cluster {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.selection-summary {
  font-size: 11px;
  color: #94a3b8;
}
.selection-summary strong {
  color: #f8fafc;
}

.btn-tool {
  background: #0c121e;
  border: 1px solid var(--border, #1e293b);
  color: #94a3b8;
  border-radius: 4px;
  padding: 4px 10px;
  height: 28px;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  transition: all var(--transition-fast);
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.btn-tool:hover {
  background: var(--panel-hover, #1c273e);
  color: #f8fafc;
  border-color: #38bdf8;
}

.custom-export-group {
  display: flex;
  align-items: center;
  gap: 6px;
  padding-left: 6px;
  border-left: 1px solid var(--border, #1e293b);
}
.btn-export-primary {
  background: #0369a1;
  border: 1px solid #0284c7;
  color: #ffffff;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.03em;
  padding: 5px 14px;
  border-radius: 4px;
  cursor: pointer;
  outline: none;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: all var(--transition-fast);
}
.btn-export-primary:hover:not(:disabled) {
  background: #0284c7;
  box-shadow: 0 0 10px rgba(56, 189, 248, 0.4);
}
.btn-export-primary:disabled {
  opacity: 0.5;
  cursor: wait;
}

/* --------------------------------------------------------------------------
   Draggable Field Reordering Grid
   -------------------------------------------------------------------------- */
.fields-table-container {
  overflow-x: auto;
  border: 1px solid var(--border, #1e293b);
  border-radius: 6px;
  background: rgba(12, 18, 30, 0.5);
}

.fields-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 12px;
}
.fields-table th {
  background: #0c121e;
  color: #94a3b8;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 10px 14px;
  border-bottom: 1px solid var(--border, #1e293b);
  user-select: none;
  white-space: nowrap;
}
.fields-table td {
  padding: 10px 14px;
  border-bottom: 1px solid rgba(30, 41, 59, 0.6);
  color: #cbd5e1;
  vertical-align: middle;
}

/* Draggable Rows */
.field-row {
  transition: background-color var(--transition-fast), border-color var(--transition-fast);
  cursor: default;
}
.field-row:hover {
  background: rgba(255, 255, 255, 0.025);
}
.field-row.row-selected {
  background: rgba(56, 189, 248, 0.04);
}
.field-row.dragging {
  opacity: 0.4;
  background: rgba(56, 189, 248, 0.15) !important;
}
.field-row.drag-over-top {
  border-top: 2px solid #38bdf8 !important;
}
.field-row.drag-over-bottom {
  border-bottom: 2px solid #38bdf8 !important;
}

/* Drag Handle Component */
.drag-handle {
  width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: grab;
  color: #64748b;
  font-size: 14px;
  font-weight: bold;
  user-select: none;
  border-radius: 3px;
  transition: color var(--transition-fast), background var(--transition-fast);
}
.drag-handle:hover {
  color: #38bdf8;
  background: rgba(56, 189, 248, 0.1);
}
.drag-handle:active {
  cursor: grabbing;
}

.order-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 24px;
  height: 20px;
  padding: 0 4px;
  font-size: 10.5px;
  font-weight: 700;
  font-family: var(--font-mono, monospace);
  color: #64748b;
  background: #090e1a;
  border: 1px solid var(--border, #1e293b);
  border-radius: 3px;
}
.row-selected .order-badge {
  color: #38bdf8;
  border-color: rgba(56, 189, 248, 0.35);
}

.col-checkbox {
  width: 16px;
  height: 16px;
  cursor: pointer;
  accent-color: var(--accent, #0284c7);
}

.field-name-tag {
  font-family: var(--font-mono, monospace);
  font-size: 11.5px;
  font-weight: 600;
  color: #38bdf8;
}
.field-label-text {
  font-weight: 500;
  color: #f1f5f9;
}
.field-type-pill {
  font-family: var(--font-mono, monospace);
  font-size: 10.5px;
  background: #090e1a;
  border: 1px solid var(--border, #1e293b);
  color: #94a3b8;
  padding: 2px 7px;
  border-radius: 3px;
}
.field-attr-pill {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 3px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.attr-pk {
  background: rgba(245, 158, 11, 0.15);
  border: 1px solid rgba(245, 158, 11, 0.35);
  color: #fbbf24;
}
.attr-null {
  background: rgba(148, 163, 184, 0.1);
  border: 1px solid rgba(148, 163, 184, 0.25);
  color: #94a3b8;
}
.attr-req {
  background: rgba(16, 185, 129, 0.12);
  border: 1px solid rgba(16, 185, 129, 0.3);
  color: #34d399;
}

/* Empty or Hint Row */
.empty-hint {
  text-align: center;
  padding: 36px 20px;
  color: #64748b;
  font-size: 12px;
}
</style>

<div class="reports-container">

  <!-- ========================================================================
       SECTION 1: CURATED COLLECTION AUDITS
       Matches user-uploaded spec image (Cardboard box, Picture frame, Star)
       ======================================================================== -->
  <section class="reports-section">
    <div class="report-section-header">
      <h2 class="report-section-title">
        <span class="report-section-icon">🗂️</span> CURATED COLLECTION AUDITS
      </h2>
    </div>

    <div class="audits-grid">
      <!-- 1. Physical Inventory Audit -->
      <div class="audit-card" data-report="physical_inventory">
        <div>
          <div class="audit-card-top">
            <span class="audit-card-icon" title="Owned Titles Inventory">📦</span>
            <span class="audit-badge" id="badgeOwned"><?= number_format($stats['physical_inventory']) ?> Owned</span>
          </div>
          <h3 class="audit-card-title">PHYSICAL INVENTORY AUDIT</h3>
          <p class="audit-card-desc">
            Export restricted to owned titles (InCollection = 1) for physical insurance inspection.
          </p>
        </div>
        <div class="audit-formats">
          <button type="button" class="btn-format" onclick="exportPremade('physical_inventory', 'csv', this)">.CSV</button>
          <button type="button" class="btn-format" onclick="exportPremade('physical_inventory', 'xlsx', this)">.XLSX</button>
          <button type="button" class="btn-format" onclick="exportPremade('physical_inventory', 'txt', this)">.TXT</button>
        </div>
      </div>

      <!-- 2. Incomplete Media Audit -->
      <div class="audit-card" data-report="incomplete_media">
        <div>
          <div class="audit-card-top">
            <span class="audit-card-icon" title="Missing Assets Audit">🖼️</span>
            <span class="audit-badge" id="badgeFlagged"><?= number_format($stats['incomplete_media']) ?> Flagged</span>
          </div>
          <h3 class="audit-card-title">INCOMPLETE MEDIA AUDIT</h3>
          <p class="audit-card-desc">
            Audit identifying every game missing either its cover BoxArt or gameplay Screenshot.
          </p>
        </div>
        <div class="audit-formats">
          <button type="button" class="btn-format" onclick="exportPremade('incomplete_media', 'csv', this)">.CSV</button>
          <button type="button" class="btn-format" onclick="exportPremade('incomplete_media', 'xlsx', this)">.XLSX</button>
          <button type="button" class="btn-format" onclick="exportPremade('incomplete_media', 'txt', this)">.TXT</button>
        </div>
      </div>

      <!-- 3. Cleared & Beaten Logbook -->
      <div class="audit-card" data-report="cleared_beaten">
        <div>
          <div class="audit-card-top">
            <span class="audit-card-icon" title="Beaten Games Log">★</span>
            <span class="audit-badge" id="badgeCleared"><?= number_format($stats['cleared_beaten']) ?> Cleared</span>
          </div>
          <h3 class="audit-card-title">CLEARED & BEATEN LOGBOOK</h3>
          <p class="audit-card-desc">
            Roster of games beaten (Won = 1) accompanied by your gameplay comments.
          </p>
        </div>
        <div class="audit-formats">
          <button type="button" class="btn-format" onclick="exportPremade('cleared_beaten', 'csv', this)">.CSV</button>
          <button type="button" class="btn-format" onclick="exportPremade('cleared_beaten', 'xlsx', this)">.XLSX</button>
          <button type="button" class="btn-format" onclick="exportPremade('cleared_beaten', 'txt', this)">.TXT</button>
        </div>
      </div>
    </div>
  </section>

  <!-- ========================================================================
       SECTION 2: CUSTOM REPORT BUILDER
       Allows selecting table, drag-and-drop reordering, checkboxes & export
       ======================================================================== -->
  <section class="reports-section">
    <div class="report-section-header">
      <h2 class="report-section-title">
        <span class="report-section-icon">⚙️</span> CUSTOM REPORT BUILDER
      </h2>
    </div>

    <div class="custom-builder-card">
      <!-- Toolbar / Selector Bar -->
      <div class="builder-controls-bar">
        <div class="table-selector-group">
          <label class="table-selector-label" for="customTableSelect">Target Database Table</label>
          <div class="table-select-wrapper">
            <select id="customTableSelect" class="table-select" onchange="onTableChange(this.value)">
              <?php foreach ($catalog as $tKey => $tMeta): ?>
                <option value="<?= View::e($tKey) ?>">
                  <?= $tMeta['icon'] ?> <?= View::e($tMeta['label']) ?> (<?= number_format($tMeta['total_records']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <span class="table-select-arrow">▼</span>
          </div>

          <span class="table-record-pill" id="tableRecordCountPill">
            Total Rows: <strong id="tableTotalRecords">0</strong>
          </span>
        </div>

        <div class="builder-actions-cluster">
          <span class="selection-summary">
            Selected: <strong id="selectedColsCount">0</strong> of <span id="totalColsCount">0</span> columns
          </span>

          <button type="button" class="btn-tool" onclick="toggleSelectAll(true)" title="Include all table fields">
            ✓ Select All
          </button>
          <button type="button" class="btn-tool" onclick="toggleSelectAll(false)" title="Exclude all table fields">
            ✕ Deselect All
          </button>
          <button type="button" class="btn-tool" onclick="resetColumnOrder()" title="Restore default database field sequence">
            ↺ Reset Order
          </button>

          <div class="custom-export-group">
            <button type="button" class="btn-export-primary" onclick="exportCustom('csv', this)">
              .CSV
            </button>
            <button type="button" class="btn-export-primary" onclick="exportCustom('xlsx', this)">
              .XLSX
            </button>
            <button type="button" class="btn-export-primary" onclick="exportCustom('txt', this)">
              .TXT
            </button>
          </div>
        </div>
      </div>

      <!-- Fields Reordering Grid with Drag & Drop Handlers -->
      <div class="fields-table-container">
        <table class="fields-table" id="fieldsTable">
          <thead>
            <tr>
              <th style="width: 44px; text-align: center;" title="Drag handle to reorder columns">↕</th>
              <th style="width: 50px; text-align: center;">#</th>
              <th style="width: 50px; text-align: center;">
                <input type="checkbox" id="masterCheckbox" class="col-checkbox" onchange="toggleSelectAll(this.checked)" title="Toggle all fields">
              </th>
              <th style="min-width: 170px;">Field Name</th>
              <th style="min-width: 220px;">Report Header / Label</th>
              <th style="min-width: 130px;">Data Type</th>
              <th style="min-width: 140px;">Attributes</th>
            </tr>
          </thead>
          <tbody id="fieldsTbody">
            <!-- Dynamically populated via JavaScript -->
          </tbody>
        </table>
      </div>
    </div>
  </section>

</div>

<script>
/**
 * Master Table Catalog Schema Metadata
 */
const TABLE_CATALOG = <?= json_encode($catalog, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

// State management for current custom builder session
let currentTable = 'games';
let currentColumns = []; // Array of column metadata objects in current user-defined order
let draggedRow = null;

document.addEventListener('DOMContentLoaded', () => {
  const initialTable = document.getElementById('customTableSelect').value || 'games';
  onTableChange(initialTable);
});

/**
 * Handles switching the target database table in custom report builder.
 */
function onTableChange(tableName) {
  currentTable = tableName;
  const meta = TABLE_CATALOG[tableName];
  if (!meta) return;

  document.getElementById('tableTotalRecords').textContent = Number(meta.total_records || 0).toLocaleString();

  // Clone columns preserving all metadata, defaulting checked to true
  currentColumns = (meta.columns || []).map((col) => ({
    ...col,
    selected: true,
  }));

  renderFieldsGrid();
}

/**
 * Resets columns to default database order and selects all.
 */
function resetColumnOrder() {
  const meta = TABLE_CATALOG[currentTable];
  if (!meta) return;

  currentColumns = (meta.columns || []).map((col) => ({
    ...col,
    selected: true,
  }));

  renderFieldsGrid();
  if (window.showToast) {
    window.showToast('Column sequence restored to database defaults.', 'info');
  }
}

/**
 * Toggles selection of all columns in the grid.
 */
function toggleSelectAll(select) {
  currentColumns.forEach((col) => {
    col.selected = !!select;
  });
  renderFieldsGrid();
}

/**
 * Renders the fields grid rows with drag handlers and checkboxes.
 */
function renderFieldsGrid() {
  const tbody = document.getElementById('fieldsTbody');
  tbody.innerHTML = '';

  let selectedCount = 0;

  currentColumns.forEach((col, index) => {
    if (col.selected) selectedCount++;

    const tr = document.createElement('tr');
    tr.className = `field-row ${col.selected ? 'row-selected' : ''}`;
    tr.draggable = true;
    tr.dataset.index = String(index);
    tr.dataset.field = col.field;

    // 1. Drag Handle
    const tdDrag = document.createElement('td');
    tdDrag.style.textAlign = 'center';
    tdDrag.innerHTML = `<span class="drag-handle" title="Drag up or down to reorder report column">⋮⋮</span>`;

    // 2. Order Number Badge
    const tdOrder = document.createElement('td');
    tdOrder.style.textAlign = 'center';
    tdOrder.innerHTML = `<span class="order-badge">${index + 1}</span>`;

    // 3. Selection Checkbox
    const tdCheck = document.createElement('td');
    tdCheck.style.textAlign = 'center';
    const chk = document.createElement('input');
    chk.type = 'checkbox';
    chk.className = 'col-checkbox';
    chk.checked = !!col.selected;
    chk.addEventListener('change', (e) => {
      e.stopPropagation();
      col.selected = chk.checked;
      tr.classList.toggle('row-selected', chk.checked);
      updateSelectionSummary();
    });
    tdCheck.appendChild(chk);

    // 4. Database Field Name
    const tdField = document.createElement('td');
    tdField.innerHTML = `<span class="field-name-tag">${escapeHtml(col.field)}</span>`;

    // 5. Friendly Label
    const tdLabel = document.createElement('td');
    tdLabel.innerHTML = `<span class="field-label-text">${escapeHtml(col.label)}</span>`;

    // 6. Data Type
    const tdType = document.createElement('td');
    tdType.innerHTML = `<span class="field-type-pill">${escapeHtml(col.type)}</span>`;

    // 7. Attributes
    const tdAttr = document.createElement('td');
    let attrHtml = '';
    if (col.is_pk) {
      attrHtml += `<span class="field-attr-pill attr-pk">PRIMARY KEY</span> `;
    }
    if (col.is_null) {
      attrHtml += `<span class="field-attr-pill attr-null">NULLABLE</span>`;
    } else {
      attrHtml += `<span class="field-attr-pill attr-req">REQUIRED</span>`;
    }
    tdAttr.innerHTML = attrHtml;

    tr.appendChild(tdDrag);
    tr.appendChild(tdOrder);
    tr.appendChild(tdCheck);
    tr.appendChild(tdField);
    tr.appendChild(tdLabel);
    tr.appendChild(tdType);
    tr.appendChild(tdAttr);

    // Clicking anywhere on row toggles checkbox (unless clicked on drag handle or checkbox)
    tr.addEventListener('click', (e) => {
      if (e.target.closest('.drag-handle') || e.target.closest('.col-checkbox')) {
        return;
      }
      chk.checked = !chk.checked;
      col.selected = chk.checked;
      tr.classList.toggle('row-selected', chk.checked);
      updateSelectionSummary();
    });

    // Attach Drag and Drop Event Listeners
    attachRowDragListeners(tr);

    tbody.appendChild(tr);
  });

  updateSelectionSummary();
}

/**
 * Updates the selected columns count indicators and master checkbox.
 */
function updateSelectionSummary() {
  const selectedCount = currentColumns.filter(c => c.selected).length;
  const totalCount = currentColumns.length;

  document.getElementById('selectedColsCount').textContent = String(selectedCount);
  document.getElementById('totalColsCount').textContent = String(totalCount);

  const masterChk = document.getElementById('masterCheckbox');
  if (masterChk) {
    masterChk.checked = selectedCount === totalCount && totalCount > 0;
    masterChk.indeterminate = selectedCount > 0 && selectedCount < totalCount;
  }
}

/**
 * HTML5 Drag & Drop Implementation for Grid Rows
 */
function attachRowDragListeners(row) {
  row.addEventListener('dragstart', (e) => {
    draggedRow = row;
    row.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', row.dataset.index);
  });

  row.addEventListener('dragend', () => {
    if (draggedRow) {
      draggedRow.classList.remove('dragging');
    }
    document.querySelectorAll('.field-row').forEach(r => {
      r.classList.remove('drag-over-top', 'drag-over-bottom');
    });
    draggedRow = null;
  });

  row.addEventListener('dragover', (e) => {
    e.preventDefault();
    if (!draggedRow || draggedRow === row) return;

    e.dataTransfer.dropEffect = 'move';
    const rect = row.getBoundingClientRect();
    const midY = rect.top + rect.height / 2;

    if (e.clientY < midY) {
      row.classList.add('drag-over-top');
      row.classList.remove('drag-over-bottom');
    } else {
      row.classList.add('drag-over-bottom');
      row.classList.remove('drag-over-top');
    }
  });

  row.addEventListener('dragleave', () => {
    row.classList.remove('drag-over-top', 'drag-over-bottom');
  });

  row.addEventListener('drop', (e) => {
    e.preventDefault();
    row.classList.remove('drag-over-top', 'drag-over-bottom');
    if (!draggedRow || draggedRow === row) return;

    const fromIdx = parseInt(draggedRow.dataset.index, 10);
    let toIdx = parseInt(row.dataset.index, 10);

    const rect = row.getBoundingClientRect();
    const midY = rect.top + rect.height / 2;
    const isBelow = e.clientY >= midY;

    // Adjust destination index based on drop position
    if (isBelow && fromIdx > toIdx) {
      toIdx += 1;
    } else if (!isBelow && fromIdx < toIdx) {
      toIdx -= 1;
    }

    // Reorder internal array
    const movedItem = currentColumns.splice(fromIdx, 1)[0];
    currentColumns.splice(toIdx, 0, movedItem);

    // Re-render grid with new order and preserved states
    renderFieldsGrid();
  });
}

/**
 * --------------------------------------------------------------------------
 * EXPORT DISPATCHERS & ENGINES
 * --------------------------------------------------------------------------
 */

/**
 * Triggers export for Curated Premade Audits.
 */
async function exportPremade(reportName, format, btnElement) {
  const originalHtml = btnElement.innerHTML;
  btnElement.disabled = true;
  btnElement.innerHTML = `⏳`;

  try {
    const res = await fetch(`/api/reports/premade?report=${encodeURIComponent(reportName)}`);
    const json = await res.json();

    if (!json.success || !json.data) {
      throw new Error(json.error || 'Failed to fetch report data.');
    }

    const { filename, headers, rows, title } = json.data;

    dispatchExport(format, filename, headers, rows, title);

    if (window.showToast) {
      window.showToast(`✓ Exported ${rows.length.toLocaleString()} rows to ${filename}.${format}`, 'success');
    }
  } catch (err) {
    console.error('Export Premade Error:', err);
    if (window.showToast) {
      window.showToast(err.message || 'Export error occurred.', 'danger');
    } else {
      alert(err.message);
    }
  } finally {
    btnElement.disabled = false;
    btnElement.innerHTML = originalHtml;
  }
}

/**
 * Triggers export for Custom Report Builder with ordered selected columns.
 */
async function exportCustom(format, btnElement) {
  const selectedCols = currentColumns.filter(c => c.selected).map(c => c.field);

  if (selectedCols.length === 0) {
    if (window.showToast) {
      window.showToast('Please select at least one column for custom export.', 'warning');
    } else {
      alert('Please select at least one column for custom export.');
    }
    return;
  }

  const originalHtml = btnElement.innerHTML;
  btnElement.disabled = true;
  btnElement.innerHTML = `⏳ Generating...`;

  try {
    const res = await fetch('/api/reports/custom', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        table: currentTable,
        columns: selectedCols,
      }),
    });

    const json = await res.json();
    if (!json.success || !json.data) {
      throw new Error(json.error || 'Failed to generate custom report.');
    }

    const { filename, headers, rows, table } = json.data;
    const sheetTitle = (table.charAt(0).toUpperCase() + table.slice(1)) + ' Report';

    dispatchExport(format, filename, headers, rows, sheetTitle);

    if (window.showToast) {
      window.showToast(`✓ Exported ${selectedCols.length} columns (${rows.length.toLocaleString()} rows) to ${filename}.${format}`, 'success');
    }
  } catch (err) {
    console.error('Custom Export Error:', err);
    if (window.showToast) {
      window.showToast(err.message || 'Custom export error occurred.', 'danger');
    } else {
      alert(err.message);
    }
  } finally {
    btnElement.disabled = false;
    btnElement.innerHTML = originalHtml;
  }
}

/**
 * Dispatches formatting and browser file download for .csv, .xlsx, or .txt.
 */
function dispatchExport(format, filename, headers, rows, sheetName = 'Report') {
  format = (format || 'csv').toLowerCase();

  if (format === 'csv') {
    exportToCsv(filename, headers, rows);
  } else if (format === 'xlsx') {
    exportToXlsx(filename, headers, rows, sheetName);
  } else if (format === 'txt') {
    exportToTxt(filename, headers, rows);
  } else {
    throw new Error(`Unsupported export format: ${format}`);
  }
}

/**
 * Exports data as standard Comma-Separated Values (.csv) with UTF-8 BOM.
 */
function exportToCsv(filename, headers, rows) {
  const escapeCell = (val) => {
    if (val === null || val === undefined) return '';
    const str = String(val);
    if (str.includes(',') || str.includes('"') || str.includes('\n') || str.includes('\r')) {
      return '"' + str.replace(/"/g, '""') + '"';
    }
    return str;
  };

  let csvContent = '\uFEFF'; // UTF-8 Byte Order Mark
  csvContent += headers.map(escapeCell).join(',') + '\r\n';

  for (let i = 0; i < rows.length; i++) {
    csvContent += rows[i].map(escapeCell).join(',') + '\r\n';
  }

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  triggerDownload(blob, `${filename}.csv`);
}

/**
 * Exports data as strictly Tab-Separated Values (.txt).
 * CRITICAL RULE FROM USER:
 * "In the specific case of .txt exports, these must not contain any other separation character, only values and tab characters."
 */
function exportToTxt(filename, headers, rows) {
  const cleanCell = (val) => {
    if (val === null || val === undefined) return '';
    // Must NOT contain any separation character, only values and tab characters.
    // Replace any internal tabs or linebreaks with spaces so no rogue delimiters exist.
    return String(val).replace(/[\t\r\n]/g, ' ');
  };

  let txtContent = headers.map(cleanCell).join('\t') + '\r\n';

  for (let i = 0; i < rows.length; i++) {
    txtContent += rows[i].map(cleanCell).join('\t') + '\r\n';
  }

  const blob = new Blob([txtContent], { type: 'text/plain;charset=utf-8;' });
  triggerDownload(blob, `${filename}.txt`);
}

/**
 * Exports data as Microsoft Excel Workbook (.xlsx) using preloaded SheetJS engine.
 */
function exportToXlsx(filename, headers, rows, sheetName = 'Report') {
  if (typeof XLSX === 'undefined') {
    throw new Error('Spreadsheet rendering engine (SheetJS) is not loaded.');
  }

  const aoa = [headers, ...rows];
  const wb = XLSX.utils.book_new();
  const ws = XLSX.utils.aoa_to_sheet(aoa);

  // Auto-size column widths based on maximum string length
  const colWidths = headers.map((h, i) => {
    let maxLen = String(h).length;
    for (let r = 0; r < Math.min(rows.length, 100); r++) {
      const cellVal = rows[r][i];
      if (cellVal !== null && cellVal !== undefined) {
        maxLen = Math.max(maxLen, String(cellVal).length);
      }
    }
    return { wch: Math.min(Math.max(maxLen + 3, 10), 50) };
  });
  ws['!cols'] = colWidths;

  XLSX.utils.book_append_sheet(wb, ws, sheetName.substring(0, 31));
  XLSX.writeFile(wb, `${filename}.xlsx`);
}

/**
 * Helper to trigger standard browser file download via Blob.
 */
function triggerDownload(blob, filename) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  setTimeout(() => {
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }, 200);
}

/**
 * HTML Escaper helper
 */
function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
</script>
