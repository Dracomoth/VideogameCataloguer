<?php
/**
 * reports.php - Collection Reports & Data Exports
 * 
 * Fully decoupled architecture: zero inline database queries.
 * Interacts asynchronously with api/export.php.
 */
$pageTitle = 'Reports & Data Exports';
$activeNav = 'reports';

require_once __DIR__ . '/layout_header.php';
?>

<div class="form-pane">
  <div class="form-section-title">
    &#128190; Core Entity Snapshots
  </div>

  <div class="dashboard-grid">
    <!-- Games -->
    <div class="nav-card">
      <div>
        <div class="card-top">
          <span class="card-icon">&#127918;</span>
          <span class="badge-count" id="count-games">...</span>
        </div>
        <div class="card-title">Games Master Catalog</div>
        <p class="card-desc">Complete library database with platforms, categories, play statuses, and comments.</p>
      </div>
      <div class="card-action">
        <a href="api/export.php?action=export&target=games&format=csv" class="btn btn-sm">.CSV</a>
        <a href="api/export.php?action=export&target=games&format=xlsx" class="btn btn-sm">.XLSX</a>
        <a href="api/export.php?action=export&target=games&format=txt" class="btn btn-sm">.TXT</a>
      </div>
    </div>

    <!-- Consoles -->
    <div class="nav-card">
      <div>
        <div class="card-top">
          <span class="card-icon">&#128377;</span>
          <span class="badge-count" id="count-consoles">...</span>
        </div>
        <div class="card-title">Consoles & Platforms</div>
        <p class="card-desc">Hardware registry with generations, portable flags, makers, and RetroArch cores.</p>
      </div>
      <div class="card-action">
        <a href="api/export.php?action=export&target=consoles&format=csv" class="btn btn-sm">.CSV</a>
        <a href="api/export.php?action=export&target=consoles&format=xlsx" class="btn btn-sm">.XLSX</a>
        <a href="api/export.php?action=export&target=consoles&format=txt" class="btn btn-sm">.TXT</a>
      </div>
    </div>

    <!-- Publishers -->
    <div class="nav-card">
      <div>
        <div class="card-top">
          <span class="card-icon">&#127970;</span>
          <span class="badge-count" id="count-publishers">...</span>
        </div>
        <div class="card-title">Publishers & Studios</div>
        <p class="card-desc">Company directory cross-referenced with console manufacturing flags and title volume.</p>
      </div>
      <div class="card-action">
        <a href="api/export.php?action=export&target=publishers&format=csv" class="btn btn-sm">.CSV</a>
        <a href="api/export.php?action=export&target=publishers&format=xlsx" class="btn btn-sm">.XLSX</a>
        <a href="api/export.php?action=export&target=publishers&format=txt" class="btn btn-sm">.TXT</a>
      </div>
    </div>

    <!-- Categories -->
    <div class="nav-card">
      <div>
        <div class="card-top">
          <span class="card-icon">&#128194;</span>
          <span class="badge-count" id="count-categories">...</span>
        </div>
        <div class="card-title">Categories Taxonomy</div>
        <p class="card-desc">Primary genres linked to subcategory classifications and assigned titles.</p>
      </div>
      <div class="card-action">
        <a href="api/export.php?action=export&target=categories&format=csv" class="btn btn-sm">.CSV</a>
        <a href="api/export.php?action=export&target=categories&format=xlsx" class="btn btn-sm">.XLSX</a>
        <a href="api/export.php?action=export&target=categories&format=txt" class="btn btn-sm">.TXT</a>
      </div>
    </div>

    <!-- Languages -->
    <div class="nav-card">
      <div>
        <div class="card-top">
          <span class="card-icon">&#127760;</span>
          <span class="badge-count" id="count-languages">...</span>
        </div>
        <div class="card-title">Languages Directory</div>
        <p class="card-desc">Supported software regional and linguistic distribution tally.</p>
      </div>
      <div class="card-action">
        <a href="api/export.php?action=export&target=languages&format=csv" class="btn btn-sm">.CSV</a>
        <a href="api/export.php?action=export&target=languages&format=xlsx" class="btn btn-sm">.XLSX</a>
        <a href="api/export.php?action=export&target=languages&format=txt" class="btn btn-sm">.TXT</a>
      </div>
    </div>
  </div>

  <div class="form-section-title" style="margin-top: 24px;">
    &#128202; Curated Collection Audits
  </div>

  <div class="dashboard-grid">
    <!-- Physical Inventory -->
    <div class="nav-card">
      <div>
        <div class="card-top">
          <span class="card-icon">&#128230;</span>
          <span class="badge-count" id="count-inventory">...</span>
        </div>
        <div class="card-title">Physical Inventory Audit</div>
        <p class="card-desc">Export restricted to owned titles (InCollection = 1) for physical insurance inspection.</p>
      </div>
      <div class="card-action">
        <a href="api/export.php?action=export&target=inventory&format=csv" class="btn btn-sm">.CSV</a>
        <a href="api/export.php?action=export&target=inventory&format=xlsx" class="btn btn-sm">.XLSX</a>
        <a href="api/export.php?action=export&target=inventory&format=txt" class="btn btn-sm">.TXT</a>
      </div>
    </div>

    <!-- Missing Media Audit -->
    <div class="nav-card">
      <div>
        <div class="card-top">
          <span class="card-icon">&#128444;</span>
          <span class="badge" id="count-missing_art">...</span>
        </div>
        <div class="card-title">Incomplete Media Audit</div>
        <p class="card-desc">Audit identifying every game missing either its cover BoxArt or gameplay Screenshot.</p>
      </div>
      <div class="card-action">
        <a href="api/export.php?action=export&target=missing_art&format=csv" class="btn btn-sm">.CSV</a>
        <a href="api/export.php?action=export&target=missing_art&format=xlsx" class="btn btn-sm">.XLSX</a>
        <a href="api/export.php?action=export&target=missing_art&format=txt" class="btn btn-sm">.TXT</a>
      </div>
    </div>

    <!-- Won Logbook -->
    <div class="nav-card">
      <div>
        <div class="card-top">
          <span class="card-icon">&#9733;</span>
          <span class="badge-count" id="count-won">...</span>
        </div>
        <div class="card-title">Cleared &amp; Beaten Logbook</div>
        <p class="card-desc">Roster of games beaten (Won = 1) accompanied by your gameplay comments.</p>
      </div>
      <div class="card-action">
        <a href="api/export.php?action=export&target=won&format=csv" class="btn btn-sm">.CSV</a>
        <a href="api/export.php?action=export&target=won&format=xlsx" class="btn btn-sm">.XLSX</a>
        <a href="api/export.php?action=export&target=won&format=txt" class="btn btn-sm">.TXT</a>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', loadReportTelemetry);

  async function loadReportTelemetry() {
    try {
      const res = await fetch('api/export.php?action=summary', {
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error || 'Failed to retrieve telemetry.');

      const d = json.data;
      document.getElementById('count-games').textContent = Number(d.games).toLocaleString() + ' Titles';
      document.getElementById('count-consoles').textContent = Number(d.consoles).toLocaleString() + ' Platforms';
      document.getElementById('count-publishers').textContent = Number(d.publishers).toLocaleString() + ' Studios';
      document.getElementById('count-categories').textContent = Number(d.categories).toLocaleString() + ' Categories';
      document.getElementById('count-languages').textContent = Number(d.languages).toLocaleString() + ' Locales';
      document.getElementById('count-inventory').textContent = Number(d.inventory).toLocaleString() + ' Owned';
      document.getElementById('count-missing_art').textContent = Number(d.missing_art).toLocaleString() + ' Flagged';
      document.getElementById('count-won').textContent = Number(d.won).toLocaleString() + ' Cleared';
    } catch (err) {
      console.error(err);
      document.querySelectorAll('.badge-count, .badge').forEach(el => {
        if (el.id.startsWith('count-')) el.textContent = 'Err';
      });
    }
  }
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>