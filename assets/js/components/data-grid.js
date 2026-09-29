/**
 * assets/js/components/data-grid.js
 * Universal Vanilla JS Web Component: <data-grid> / <vault-grid>
 *
 * Provides a modular, high-performance data-grid for Videogame Vault maintenance screens
 * (Languages, Categories, Consoles, Games, Publishers, etc.).
 *
 * Capabilities:
 * - Rich content rendering (text, pill badges, images/avatars, action buttons, custom HTML callbacks)
 * - Interactive multi-column client-side and server-side filtering & global search
 * - Comprehensive pagination:
 *     - "First" (<<) and "Previous" (<) navigation
 *     - Current page indicator ("Page X of Y")
 *     - "Next" (>) and "Last" (>>) navigation
 *     - Direct "Jump to page" numeric input with validation
 *     - Page size selector (25, 50, 100 items per page)
 *     - Telemetry counter ("Showing X to Y of Z entries")
 * - Interactive column sorting (asc / desc)
 * - Empty state with responsive dark-slate workbench aesthetics
 * - Custom event dispatching ('action-click', 'row-click', 'page-change', 'filter-change')
 *
 * Usage Example:
 * <data-grid id="languagesGrid"></data-grid>
 * <script>
 *   const grid = document.getElementById('languagesGrid');
 *   grid.columns = [
 *     { key: 'id', label: 'ID', width: '80px', sortable: true },
 *     { key: 'name', label: 'Language Name', sortable: true, searchable: true },
 *     { key: 'games_count', label: 'Games Linked', type: 'badge', variant: 'primary', sortable: true },
 *     { key: 'actions', label: 'Actions', type: 'actions', align: 'right' }
 *   ];
 *   grid.data = [...];
 * </script>
 */

class DataGrid extends HTMLElement {
  constructor() {
    super();

    // Internal State
    this._columns = [];
    this._rawData = [];
    this._filteredData = [];
    this._currentPage = 1;
    this._pageSize = 25;
    this._pageSizeOptions = [25, 50, 100];
    this._searchQuery = '';
    this._activeFilters = {};
    this._sortColumn = null;
    this._sortDirection = 'asc'; // 'asc' | 'desc'
    this._customFilters = [];

    // Unique component ID for element scoping
    this._uid = 'vg_' + Math.random().toString(36).substring(2, 9);
  }

  static get observedAttributes() {
    return ['page-size', 'search-placeholder'];
  }

  attributeChangedCallback(name, oldValue, newValue) {
    if (oldValue === newValue) return;
    if (name === 'page-size') {
      const parsed = parseInt(newValue, 10);
      if (!isNaN(parsed) && parsed > 0) {
        this._pageSize = parsed;
        this._currentPage = 1;
        this._applyDataPipeline();
      }
    }
  }

  connectedCallback() {
    this.render();
    this._setupHeightSync();
  }

  disconnectedCallback() {
    this._teardownHeightSync();
  }

  // =========================================================================
  // Public Properties & Setters
  // =========================================================================

  get columns() {
    return this._columns;
  }

  set columns(cols) {
    this._columns = Array.isArray(cols) ? cols : [];
    this.render();
  }

  get data() {
    return this._rawData;
  }

  set data(items) {
    this._rawData = Array.isArray(items) ? items : [];
    this._currentPage = 1;
    this._applyDataPipeline();
  }

  get pageSize() {
    return this._pageSize;
  }

  set pageSize(size) {
    const val = parseInt(size, 10);
    if (!isNaN(val) && val > 0) {
      this._pageSize = val;
      this._currentPage = 1;
      this._applyDataPipeline();
    }
  }

  get customFilters() {
    return this._customFilters;
  }

  set customFilters(filters) {
    this._customFilters = Array.isArray(filters) ? filters : [];
    this.render();
  }

  // =========================================================================
  // Core Data Processing (Search, Filter, Sort, Paginate)
  // =========================================================================

  _applyDataPipeline() {
    let result = [...this._rawData];

    // 1. Global text search
    if (this._searchQuery.trim() !== '') {
      const q = this._searchQuery.toLowerCase().trim();
      result = result.filter(row => {
        return this._columns.some(col => {
          if (col.searchable === false) return false;
          const val = row[col.key];
          if (val === null || val === undefined) return false;
          return String(val).toLowerCase().includes(q);
        });
      });
    }

    // 2. Custom column / dropdown filters
    for (const [key, filterVal] of Object.entries(this._activeFilters)) {
      if (filterVal !== null && filterVal !== undefined && filterVal !== '' && filterVal !== 'all') {
        result = result.filter(row => {
          const rowVal = row[key];
          return String(rowVal) === String(filterVal);
        });
      }
    }

    // 3. Sorting
    if (this._sortColumn) {
      const colKey = this._sortColumn;
      const isAsc = this._sortDirection === 'asc';

      result.sort((a, b) => {
        let valA = a[colKey];
        let valB = b[colKey];

        if (valA === null || valA === undefined) valA = '';
        if (valB === null || valB === undefined) valB = '';

        // Numeric comparison
        const numA = Number(valA);
        const numB = Number(valB);
        if (!isNaN(numA) && !isNaN(numB) && valA !== '' && valB !== '') {
          return isAsc ? numA - numB : numB - numA;
        }

        // String collation
        const strA = String(valA).toLowerCase();
        const strB = String(valB).toLowerCase();
        return isAsc ? strA.localeCompare(strB) : strB.localeCompare(strA);
      });
    }

    this._filteredData = result;

    // Validate page range
    const totalPages = this.totalPages;
    if (this._currentPage > totalPages && totalPages > 0) {
      this._currentPage = totalPages;
    } else if (this._currentPage < 1) {
      this._currentPage = 1;
    }

    this._updateBodyAndPagination();
  }

  get totalRecords() {
    return this._filteredData.length;
  }

  get totalPages() {
    return Math.max(1, Math.ceil(this.totalRecords / this._pageSize));
  }

  get paginatedRows() {
    const start = (this._currentPage - 1) * this._pageSize;
    return this._filteredData.slice(start, start + this._pageSize);
  }

  // =========================================================================
  // Navigation & Pagination Actions
  // =========================================================================

  goToPage(page) {
    const target = Math.max(1, Math.min(page, this.totalPages));
    if (target !== this._currentPage) {
      this._currentPage = target;
      this._updateBodyAndPagination();
      this.dispatchEvent(new CustomEvent('page-change', {
        bubbles: true,
        detail: {
          page: this._currentPage,
          pageSize: this._pageSize,
          totalPages: this.totalPages,
          totalRecords: this.totalRecords
        }
      }));
    }
  }

  // =========================================================================
  // Rendering
  // =========================================================================

  render() {
    this.innerHTML = `
      <div class="vault-grid-wrapper" id="${this._uid}">
        <!-- Top Toolbar: Search, Filters & Page Size -->
        <div class="vault-grid-toolbar">
          <div class="vault-grid-toolbar-left">
            <div class="vault-grid-search-wrap">
              <span class="vault-grid-search-icon">🔍</span>
              <input 
                type="search" 
                class="vault-grid-search-input" 
                placeholder="${this.getAttribute('search-placeholder') || 'Search records...'}" 
                value="${this._escapeHtml(this._searchQuery)}"
              />
            </div>
            ${this._renderCustomFilterBars()}
          </div>
          <div class="vault-grid-toolbar-right">
            <div class="vault-grid-size-selector">
              <label for="${this._uid}_size">Display:</label>
              <select id="${this._uid}_size" class="vault-grid-select-size">
                ${this._pageSizeOptions.map(size => `
                  <option value="${size}" ${this._pageSize === size ? 'selected' : ''}>${size} rows</option>
                `).join('')}
              </select>
            </div>
          </div>
        </div>

        <!-- Main Datasheet Table Container -->
        <div class="vault-grid-table-container">
          <table class="vault-grid-table">
            <thead>
              ${this._renderTableHeader()}
            </thead>
            <tbody class="vault-grid-tbody">
              <!-- Dynamically populated by _updateBodyAndPagination() -->
            </tbody>
          </table>
        </div>

        <!-- Bottom Pagination & Telemetry Bar -->
        <div class="vault-grid-footer">
          <div class="vault-grid-telemetry">
            <!-- Telemetry updated dynamically -->
          </div>
          <div class="vault-grid-pagination">
            <!-- Pagination controls updated dynamically -->
          </div>
        </div>
      </div>
    `;

    this._bindEvents();
    this._applyDataPipeline();
    this._requestSyncHeight();
  }

  _renderCustomFilterBars() {
    if (!this._customFilters || this._customFilters.length === 0) return '';

    return `
      <div class="vault-grid-filters-group">
        ${this._customFilters.map(filter => `
          <div class="vault-grid-filter-item">
            ${filter.label ? `<label>${this._escapeHtml(filter.label)}:</label>` : ''}
            <select data-filter-key="${this._escapeHtml(filter.key)}" class="vault-grid-select-filter">
              <option value="all">${this._escapeHtml(filter.allLabel || 'All')}</option>
              ${(filter.options || []).map(opt => `
                <option value="${this._escapeHtml(opt.value)}" ${this._activeFilters[filter.key] == opt.value ? 'selected' : ''}>
                  ${this._escapeHtml(opt.label)}
                </option>
              `).join('')}
            </select>
          </div>
        `).join('')}
      </div>
    `;
  }

  _renderTableHeader() {
    return `
      <tr>
        ${this._columns.map(col => {
          const isSorted = this._sortColumn === col.key;
          const sortIcon = isSorted ? (this._sortDirection === 'asc' ? ' ▲' : ' ▼') : (col.sortable ? ' ↕' : '');
          const sortClass = col.sortable ? 'vault-grid-th-sortable' : '';
          const align = col.align ? `text-align: ${col.align};` : '';
          const width = col.width ? `width: ${col.width};` : '';

          return `
            <th 
              data-col-key="${this._escapeHtml(col.key)}" 
              class="${sortClass} ${isSorted ? 'sorted' : ''}" 
              style="${align} ${width}"
            >
              <div class="vault-grid-th-content" style="${col.align === 'right' ? 'justify-content: flex-end;' : ''}">
                <span>${this._escapeHtml(col.label || col.key)}</span>
                ${sortIcon ? `<span class="vault-grid-sort-indicator">${sortIcon}</span>` : ''}
              </div>
            </th>
          `;
        }).join('')}
      </tr>
    `;
  }

  _updateBodyAndPagination() {
    const tbody = this.querySelector('.vault-grid-tbody');
    const telemetry = this.querySelector('.vault-grid-telemetry');
    const pagination = this.querySelector('.vault-grid-pagination');

    if (!tbody) return;

    const rows = this.paginatedRows;
    const total = this.totalRecords;
    const rawTotal = this._rawData.length;

    // 1. Render Table Rows
    if (rows.length === 0) {
      tbody.innerHTML = `
        <tr class="vault-grid-empty-row">
          <td colspan="${Math.max(1, this._columns.length)}">
            <div class="vault-grid-empty-state">
              <span class="vault-grid-empty-icon">📂</span>
              <p class="vault-grid-empty-title">No records found</p>
              <p class="vault-grid-empty-sub">
                ${this._searchQuery ? 'Try clearing your search query or relaxing active filters.' : 'There are currently no items in this catalog.'}
              </p>
            </div>
          </td>
        </tr>
      `;
    } else {
      tbody.innerHTML = rows.map((row, idx) => {
        const absoluteIndex = (this._currentPage - 1) * this._pageSize + idx;
        return `
          <tr data-row-index="${absoluteIndex}">
            ${this._columns.map(col => {
              const align = col.align ? `text-align: ${col.align};` : '';
              return `
                <td style="${align}">
                  ${this._renderCell(row, col, absoluteIndex)}
                </td>
              `;
            }).join('')}
          </tr>
        `;
      }).join('');
    }

    // 2. Render Telemetry Info
    if (telemetry) {
      if (total === 0) {
        telemetry.innerHTML = `<span>Showing <strong>0</strong> entries</span>`;
      } else {
        const start = (this._currentPage - 1) * this._pageSize + 1;
        const end = Math.min(this._currentPage * this._pageSize, total);
        const filterNote = rawTotal !== total ? ` (filtered from ${rawTotal} total)` : '';
        telemetry.innerHTML = `
          <span>Showing <strong>${start}</strong> to <strong>${end}</strong> of <strong>${total}</strong> entries${filterNote}</span>
        `;
      }
    }

    // 3. Render Pagination Controls
    if (pagination) {
      const cur = this._currentPage;
      const pages = this.totalPages;
      const isFirstDisabled = cur <= 1;
      const isLastDisabled = cur >= pages;

      pagination.innerHTML = `
        <div class="vault-grid-paging-buttons">
          <button 
            type="button" 
            class="vault-grid-page-btn btn-first" 
            title="First Page" 
            ${isFirstDisabled ? 'disabled' : ''}
          >&laquo; First</button>
          
          <button 
            type="button" 
            class="vault-grid-page-btn btn-prev" 
            title="Previous Page" 
            ${isFirstDisabled ? 'disabled' : ''}
          >&lsaquo; Prev</button>
          
          <div class="vault-grid-page-indicator">
            Page <span class="page-current">${cur}</span> of <span class="page-total">${pages}</span>
          </div>

          <button 
            type="button" 
            class="vault-grid-page-btn btn-next" 
            title="Next Page" 
            ${isLastDisabled ? 'disabled' : ''}
          >Next &rsaquo;</button>
          
          <button 
            type="button" 
            class="vault-grid-page-btn btn-last" 
            title="Last Page" 
            ${isLastDisabled ? 'disabled' : ''}
          >Last &raquo;</button>
        </div>

        <div class="vault-grid-jump-container">
          <label for="${this._uid}_jump">Jump:</label>
          <input 
            type="number" 
            id="${this._uid}_jump" 
            class="vault-grid-jump-input" 
            min="1" 
            max="${pages}" 
            value="${cur}" 
          />
          <button type="button" class="vault-grid-jump-btn">Go</button>
        </div>
      `;
    }

    this.dispatchEvent(new CustomEvent('grid-updated', { bubbles: true }));
    this._requestSyncHeight();
  }

  _renderCell(row, col, rowIndex) {
    const rawVal = row[col.key];

    // Custom render callback takes precedence
    if (typeof col.render === 'function') {
      return col.render(rawVal, row, rowIndex);
    }

    const type = col.type || 'text';

    switch (type) {
      case 'badge': {
        const variant = typeof col.variant === 'function' ? col.variant(rawVal, row) : (col.variant || 'primary');
        const badgeText = col.formatter ? col.formatter(rawVal, row) : (rawVal ?? '—');
        return `
          <span class="vault-grid-badge badge-${this._escapeHtml(variant)}">
            ${this._escapeHtml(badgeText)}
          </span>
        `;
      }

      case 'image': {
        if (!rawVal) {
          return `<span class="vault-grid-no-img">No Image</span>`;
        }
        const alt = col.altKey ? (row[col.altKey] || 'Image') : 'Thumbnail';
        return `
          <div class="vault-grid-thumb-wrap">
            <img 
              src="${this._escapeHtml(rawVal)}" 
              alt="${this._escapeHtml(alt)}" 
              class="vault-grid-thumb"
              loading="lazy"
              onerror="this.onerror=null; this.parentElement.innerHTML='<span class=\\'vault-grid-no-img\\'>Failed</span>';"
            />
          </div>
        `;
      }

      case 'actions': {
        const actions = col.actions || [
          { name: 'edit', label: '✏️ Edit', class: 'btn-action-edit' },
          { name: 'delete', label: '🗑️ Delete', class: 'btn-action-delete' }
        ];

        return `
          <div class="vault-grid-actions-group">
            ${actions.map(act => {
              const visible = typeof act.visible === 'function' ? act.visible(row) : true;
              if (!visible) return '';
              const variantClass = act.class || (act.name === 'delete' ? 'btn-action-delete' : 'btn-action-edit');
              return `
                <button 
                  type="button" 
                  class="vault-grid-action-btn ${variantClass}" 
                  data-action="${this._escapeHtml(act.name)}" 
                  data-row-index="${rowIndex}"
                  title="${this._escapeHtml(act.label || act.name)}"
                >
                  ${this._escapeHtml(act.label || act.name)}
                </button>
              `;
            }).join('')}
          </div>
        `;
      }

      case 'boolean': {
        const isTrue = rawVal === true || rawVal === 1 || rawVal === '1';
        return isTrue 
          ? `<span class="vault-grid-badge badge-success">✓ Yes</span>`
          : `<span class="vault-grid-badge badge-muted">✗ No</span>`;
      }

      case 'text':
      default: {
        const displayVal = col.formatter ? col.formatter(rawVal, row) : (rawVal ?? '—');
        return `<span>${this._escapeHtml(displayVal)}</span>`;
      }
    }
  }

  // =========================================================================
  // Event Listeners & Delegations
  // =========================================================================

  _bindEvents() {
    // 1. Search input (debounced)
    const searchInput = this.querySelector('.vault-grid-search-input');
    if (searchInput) {
      let debounceTimer = null;
      searchInput.addEventListener('input', (e) => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
          this._searchQuery = e.target.value;
          this._currentPage = 1;
          this._applyDataPipeline();
          this.dispatchEvent(new CustomEvent('filter-change', {
            bubbles: true,
            detail: { search: this._searchQuery, filters: this._activeFilters }
          }));
        }, 180);
      });
    }

    // 2. Custom dropdown filters
    this.addEventListener('change', (e) => {
      if (e.target && e.target.classList.contains('vault-grid-select-filter')) {
        const key = e.target.getAttribute('data-filter-key');
        if (key) {
          this._activeFilters[key] = e.target.value;
          this._currentPage = 1;
          this._applyDataPipeline();
          this.dispatchEvent(new CustomEvent('filter-change', {
            bubbles: true,
            detail: { search: this._searchQuery, filters: this._activeFilters }
          }));
        }
      }
    });

    // 3. Page size selector
    const sizeSelect = this.querySelector('.vault-grid-select-size');
    if (sizeSelect) {
      sizeSelect.addEventListener('change', (e) => {
        this.pageSize = parseInt(e.target.value, 10);
      });
    }

    // 4. Header sorting click delegation
    const thead = this.querySelector('thead');
    if (thead) {
      thead.addEventListener('click', (e) => {
        const th = e.target.closest('th.vault-grid-th-sortable');
        if (!th) return;
        const colKey = th.getAttribute('data-col-key');
        if (!colKey) return;

        if (this._sortColumn === colKey) {
          this._sortDirection = this._sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
          this._sortColumn = colKey;
          this._sortDirection = 'asc';
        }

        // Re-render header to update arrows
        thead.innerHTML = this._renderTableHeader();
        this._applyDataPipeline();
      });
    }

    // 5. Pagination button clicks delegation
    this.addEventListener('click', (e) => {
      // First
      if (e.target.closest('.btn-first')) {
        this.goToPage(1);
        return;
      }
      // Prev
      if (e.target.closest('.btn-prev')) {
        this.goToPage(this._currentPage - 1);
        return;
      }
      // Next
      if (e.target.closest('.btn-next')) {
        this.goToPage(this._currentPage + 1);
        return;
      }
      // Last
      if (e.target.closest('.btn-last')) {
        this.goToPage(this.totalPages);
        return;
      }
      // Jump Go button
      if (e.target.closest('.vault-grid-jump-btn')) {
        const jumpInput = this.querySelector('.vault-grid-jump-input');
        if (jumpInput) {
          const val = parseInt(jumpInput.value, 10);
          if (!isNaN(val)) this.goToPage(val);
        }
        return;
      }
      // Action buttons delegation
      const actionBtn = e.target.closest('.vault-grid-action-btn');
      if (actionBtn) {
        e.stopPropagation();
        const action = actionBtn.getAttribute('data-action');
        const rowIndex = parseInt(actionBtn.getAttribute('data-row-index'), 10);
        const rowData = this._filteredData[rowIndex];
        this.dispatchEvent(new CustomEvent('action-click', {
          bubbles: true,
          detail: { action, row: rowData, index: rowIndex }
        }));
        return;
      }
      // Row click delegation
      const tr = e.target.closest('.vault-grid-tbody tr');
      if (tr && !tr.classList.contains('vault-grid-empty-row')) {
        const rowIndex = parseInt(tr.getAttribute('data-row-index'), 10);
        const rowData = this._filteredData[rowIndex];
        this.dispatchEvent(new CustomEvent('row-click', {
          bubbles: true,
          detail: { row: rowData, index: rowIndex }
        }));
      }
    });

    // 6. Enter key in Jump input
    this.addEventListener('keydown', (e) => {
      if (e.target && e.target.classList.contains('vault-grid-jump-input') && e.key === 'Enter') {
        e.preventDefault();
        const val = parseInt(e.target.value, 10);
        if (!isNaN(val)) this.goToPage(val);
      }
    });
  }

  // =========================================================================
  // Height Synchronization (PC Fixed Size vs Mobile Dynamic Bounds)
  // =========================================================================

  _setupHeightSync() {
    this._teardownHeightSync();

    if (window.ResizeObserver) {
      this._sideFormObserver = new ResizeObserver(() => {
        this._requestSyncHeight();
      });
      const sideForm = this._findSideForm();
      if (sideForm) {
        this._sideFormObserver.observe(sideForm);
        this._observedSideForm = sideForm;
      }
    }

    this._onWindowResize = () => {
      this._requestSyncHeight();
    };
    window.addEventListener('resize', this._onWindowResize, { passive: true });

    this._requestSyncHeight();
  }

  _teardownHeightSync() {
    if (this._sideFormObserver) {
      this._sideFormObserver.disconnect();
      this._sideFormObserver = null;
    }
    this._observedSideForm = null;
    if (this._onWindowResize) {
      window.removeEventListener('resize', this._onWindowResize);
      this._onWindowResize = null;
    }
    if (this._syncRafId) {
      cancelAnimationFrame(this._syncRafId);
      this._syncRafId = null;
    }
  }

  _requestSyncHeight() {
    if (this._syncRafId) {
      cancelAnimationFrame(this._syncRafId);
    }
    this._syncRafId = requestAnimationFrame(() => {
      this._syncHeight();
    });
  }

  _isMobileLayout() {
    return window.innerWidth <= 860;
  }

  _findSideForm() {
    // 1. Search in closest workspace container
    const workspace = this.closest('.workspace, .workspace-consoles, .workspace-games');
    if (workspace) {
      const form = workspace.querySelector('.editor-card, aside.editor-card');
      if (form) return form;
    }
    // 2. Search in parent container sibling tree
    if (this.parentElement) {
      const siblingForm = this.parentElement.parentElement?.querySelector('.editor-card, aside.editor-card');
      if (siblingForm) return siblingForm;
    }
    // 3. Fallback to document query
    return document.querySelector('.editor-card, aside.editor-card');
  }

  _syncHeight() {
    const MIN_GRID_HEIGHT = 520;
    const wrapper = this.querySelector('.vault-grid-wrapper');
    const tableContainer = this.querySelector('.vault-grid-table-container');

    if (this._isMobileLayout()) {
      // Mobile Mode:
      // Allow grid to range from empty state minimum (~200px) to maximum 5-6 records (~315px)
      this.style.height = '';
      if (wrapper) {
        wrapper.style.height = '';
      }
      if (tableContainer) {
        tableContainer.style.height = '';
        tableContainer.style.minHeight = '200px';
        tableContainer.style.maxHeight = '315px';
      }
      return;
    }

    // PC Browser Mode:
    // Fixed size: either established reasonable minimum (520px) or maximum of side form if bigger
    const sideForm = this._findSideForm();
    let targetHeight = MIN_GRID_HEIGHT;

    if (sideForm) {
      if (this._sideFormObserver && this._observedSideForm !== sideForm) {
        this._sideFormObserver.observe(sideForm);
        this._observedSideForm = sideForm;
      }
      const formHeight = Math.round(sideForm.offsetHeight);
      if (formHeight > 0) {
        targetHeight = Math.max(MIN_GRID_HEIGHT, formHeight);
      }
    }

    this.style.height = `${targetHeight}px`;
    if (wrapper) {
      wrapper.style.height = '100%';
    }
    if (tableContainer) {
      tableContainer.style.height = '';
      tableContainer.style.minHeight = '0';
      tableContainer.style.maxHeight = 'none';
    }
  }

  _escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
}

// Register both custom elements for convenience
if (!customElements.get('data-grid')) {
  customElements.define('data-grid', DataGrid);
}
if (!customElements.get('vault-grid')) {
  customElements.define('vault-grid', DataGrid);
}
