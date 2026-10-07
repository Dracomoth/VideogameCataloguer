<?php
/**
 * src/Views/catalog_services.php
 * Catalog Services Workbench & Maintenance Page with Tabbed Diagnostics.
 *
 * Variables provided by CatalogServicesController:
 * @var string $pageTitle
 * @var array<int, array<string, mixed>> $brokenLinks Open broken link reports
 * @var bool $canWrite
 */

declare(strict_types=1);

use Vault\Services\View;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

$openCount = count($brokenLinks);
?>
<div class="services-container">
  <!-- Full-Width Master Tabs Header Bar -->
  <div class="services-tabs-bar">
    <button type="button" class="services-tab-btn active" id="tabBtnBrokenLinks" onclick="switchServicesTab('broken-links')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
        <line x1="2" y1="2" x2="22" y2="22"></line>
      </svg>
      <span>Broken Links</span>
      <?php if ($openCount > 0): ?>
        <span class="tab-badge"><?= $openCount ?></span>
      <?php endif; ?>
    </button>

    <button type="button" class="services-tab-btn" id="tabBtnDbRepair" onclick="switchServicesTab('database-repair')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
      </svg>
      <span>Database Repair</span>
    </button>
  </div>

  <!-- Tab Pane 1: Broken Links -->
  <div id="pane-broken-links" class="services-pane active">
    <section class="services-section-panel">
      <div class="services-panel-header">
        <div class="panel-title-group">
          <svg class="panel-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
            <line x1="2" y1="2" x2="22" y2="22"></line>
          </svg>
          <div>
            <h2 class="panel-title">Broken Links</h2>
            <p class="panel-desc">Review open user reports for missing or invalid downloadable file paths. Edit the path directly below to update the catalog and resolve the ticket, or dismiss invalid reports.</p>
          </div>
        </div>
        <span class="count-pill"><?= $openCount ?> open</span>
      </div>

      <?php if ($openCount > 0): ?>
        <div class="broken-links-list">
          <?php foreach ($brokenLinks as $link): ?>
            <?php
              $reportId  = (int)$link['report_id'];
              $fileId    = (int)$link['file_id'];
              $gameTitle = (string)($link['game_title'] ?? 'Unknown Resource');
              $consoleName = (string)($link['console_name'] ?? '');
              $fileName  = (string)($link['file_name'] ?? 'Downloadable File');
              $filePath  = (string)($link['file_path'] ?? '');
              $reportedBy = (string)($link['reported_by'] ?? 'Guest User');
              $createdDate = !empty($link['report_created']) ? date('M j, Y • g:i A', strtotime($link['report_created'])) : 'Recently';
              $badgeBg = (string)($link['badge_bg_color'] ?? '#1e3a8a');
              $badgeFg = (string)($link['badge_font_color'] ?? '#93c5fd');
            ?>
            <div class="broken-link-card" id="brokenCard_<?= $reportId ?>">
              <div class="link-card-body">
                <!-- Entity & File Summary Header -->
                <div class="link-meta-row">
                  <div class="link-entity-info">
                    <span class="link-game-title"><?= View::e($gameTitle) ?></span>
                    <?php if ($consoleName !== ''): ?>
                      <span class="dash-console-pill" style="background-color: <?= View::e($badgeBg) ?>; color: <?= View::e($badgeFg) ?>;">
                        <?= View::e($consoleName) ?>
                      </span>
                    <?php endif; ?>
                  </div>
                  <div class="link-report-date" title="Reported by <?= View::e($reportedBy) ?>">
                    <span>Reported by <strong><?= View::e($reportedBy) ?></strong> &bull; <?= View::e($createdDate) ?></span>
                  </div>
                </div>

                <!-- File Name & Editable Path Form -->
                <div class="link-fields-grid">
                  <div class="field-group">
                    <label class="field-label">File Display Name</label>
                    <div class="field-readonly-box">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                      </svg>
                      <span><?= View::e($fileName) ?></span>
                    </div>
                  </div>

                  <div class="field-group field-group-wide">
                    <label for="filePath_<?= $reportId ?>" class="field-label">File Path / Key / URL (Editable)</label>
                    <div class="input-with-icon">
                      <span class="input-icon">🔗</span>
                      <input type="text"
                             id="filePath_<?= $reportId ?>"
                             class="path-edit-input"
                             value="<?= View::e($filePath) ?>"
                             placeholder="Enter corrected S3 key or URL (e.g. snes/roms/game.zip)"
                             <?= !$canWrite ? 'disabled' : '' ?>>
                    </div>
                  </div>
                </div>

                <!-- Card Action Controls -->
                <?php if ($canWrite): ?>
                  <div class="link-card-actions">
                    <button type="button"
                            class="btn-service-fix"
                            onclick="confirmFixLink(<?= $reportId ?>, <?= $fileId ?>)"
                            title="Save new file path to database and mark report as closed">
                      <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                      </svg>
                      <span>Fix & Save Link</span>
                    </button>

                    <button type="button"
                            class="btn-service-dismiss"
                            onclick="confirmDismissTicket(<?= $reportId ?>)"
                            title="Close this report ticket without modifying the file path">
                      <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14">
                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                      </svg>
                      <span>Close Ticket</span>
                    </button>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <!-- Empty State Card -->
        <div class="services-empty-card">
          <div class="empty-icon-shield">✓</div>
          <h3 class="empty-title">All Download Links Online</h3>
          <p class="empty-desc">There are no open broken link reports. Every downloadable resource path is verified and operational.</p>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <!-- Tab Pane 2: Database Repair (Empty Placeholder) -->
  <div id="pane-database-repair" class="services-pane">
    <section class="services-section-panel">
      <div class="services-panel-header">
        <div class="panel-title-group">
          <svg class="panel-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
          </svg>
          <div>
            <h2 class="panel-title">Database Repair</h2>
            <p class="panel-desc">Automated schema integrity checks, orphaned file cleanup, and catalog index repair routines.</p>
          </div>
        </div>
        <span class="count-pill pill-idle">Standby</span>
      </div>

      <div class="services-empty-card">
        <div class="empty-icon-shield icon-tools">🛠️</div>
        <h3 class="empty-title">Database Repair Services</h3>
        <p class="empty-desc">No database repair actions are required at this time. Diagnostic tools and repair scripts will appear here when maintenance is required.</p>
      </div>
    </section>
  </div>
</div>

<style>
/* Catalog Services Workbench & Tabbed Architecture Styling */
.services-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
  width: 100%;
  max-width: 1400px;
  margin: 0 auto;
  padding: 16px 0 48px;
  box-sizing: border-box;
}

/* Full-Width Master Tabs Header */
.services-tabs-bar {
  display: flex;
  width: 100%;
  background: rgba(15, 23, 42, 0.7);
  border: 1px solid var(--border, #1e293b);
  border-radius: 8px;
  padding: 4px;
  gap: 4px;
  box-sizing: border-box;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
}

.services-tab-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 9px;
  padding: 12px 20px;
  font-size: 14px;
  font-weight: 700;
  color: #94a3b8;
  background: transparent;
  border: 1px solid transparent;
  border-radius: 6px;
  cursor: pointer;
  transition: all var(--transition-fast, 0.15s ease);
  user-select: none;
  outline: none;
}

.services-tab-btn:hover {
  color: #f1f5f9;
  background: rgba(255, 255, 255, 0.04);
}

.services-tab-btn.active {
  color: #38bdf8;
  background: rgba(14, 165, 233, 0.14);
  border-color: rgba(56, 189, 248, 0.35);
  box-shadow: 0 0 14px rgba(56, 189, 248, 0.2);
}

.services-tab-btn .tab-badge {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 9999px;
  background: rgba(56, 189, 248, 0.2);
  color: #38bdf8;
  border: 1px solid rgba(56, 189, 248, 0.4);
}

/* Tab Panes */
.services-pane {
  display: none;
  flex-direction: column;
  gap: 20px;
  width: 100%;
  animation: fadeInPane 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.services-pane.active {
  display: flex;
}

@keyframes fadeInPane {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Section Panel Styling */
.services-section-panel {
  background: var(--panel, #0f172a);
  border: 1px solid var(--border, #1e293b);
  border-radius: var(--radius-lg, 12px);
  padding: 24px;
  box-shadow: 0 10px 28px rgba(0, 0, 0, 0.3);
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.services-panel-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  padding-bottom: 16px;
}

.panel-title-group {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.panel-icon-svg {
  color: #38bdf8;
  margin-top: 3px;
  flex-shrink: 0;
}

.panel-title {
  font-size: 17px;
  font-weight: 800;
  color: #ffffff;
  margin: 0;
}

.panel-desc {
  font-size: 12.5px;
  color: var(--text-muted, #94a3b8);
  margin: 4px 0 0;
  line-height: 1.45;
}

.count-pill {
  font-size: 11.5px;
  font-weight: 700;
  color: #38bdf8;
  background: rgba(56, 189, 248, 0.12);
  border: 1px solid rgba(56, 189, 248, 0.3);
  border-radius: var(--radius-pill, 9999px);
  padding: 4px 10px;
  white-space: nowrap;
}

.pill-idle {
  color: #94a3b8;
  background: rgba(148, 163, 184, 0.12);
  border-color: rgba(148, 163, 184, 0.3);
}

/* Broken Link Cards List */
.broken-links-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.broken-link-card {
  background: linear-gradient(145deg, #131d2e, #0c1421);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: var(--radius-md, 8px);
  padding: 16px 20px;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
  transition: border-color var(--transition-fast, 0.15s ease);
}

.broken-link-card:hover {
  border-color: rgba(56, 189, 248, 0.35);
}

.link-card-body {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.link-meta-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.link-entity-info {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.link-game-title {
  font-size: 15px;
  font-weight: 800;
  color: #ffffff;
  letter-spacing: -0.01em;
}

.link-report-date {
  font-size: 12px;
  color: var(--text-dim, #64748b);
}

.link-fields-grid {
  display: grid;
  grid-template-columns: 260px 1fr;
  gap: 14px;
}

@media (max-width: 860px) {
  .link-fields-grid {
    grid-template-columns: 1fr;
  }
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--text-muted, #94a3b8);
}

.field-readonly-box {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 36px;
  padding: 0 12px;
  background: rgba(15, 23, 42, 0.6);
  border: 1px solid var(--border, #1e293b);
  border-radius: 6px;
  color: #f1f5f9;
  font-size: 13px;
  font-weight: 600;
}

.input-with-icon {
  display: flex;
  align-items: center;
  position: relative;
}

.input-icon {
  position: absolute;
  left: 10px;
  font-size: 13px;
  pointer-events: none;
}

.path-edit-input {
  width: 100%;
  height: 36px;
  padding: 0 12px 0 32px;
  font-size: 13px;
  font-family: var(--font-mono, ui-monospace, monospace);
  font-weight: 600;
  background: #070b14;
  border: 1px solid var(--border, #1e293b);
  border-radius: 6px;
  color: #38bdf8;
  outline: none;
  transition: border-color var(--transition-fast, 0.15s ease), box-shadow var(--transition-fast, 0.15s ease);
}

.path-edit-input:focus {
  border-color: #38bdf8;
  box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
}

/* Card Actions */
.link-card-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 6px;
  border-top: 1px dashed rgba(255, 255, 255, 0.06);
}

.btn-service-fix {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
  border: 1px solid #38bdf8;
  color: #ffffff;
  font-size: 12.5px;
  font-weight: 700;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  transition: all var(--transition-fast, 0.15s ease);
}

.btn-service-fix:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(56, 189, 248, 0.35);
}

.btn-service-fix:active {
  transform: translateY(0);
}

.btn-service-dismiss {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(30, 41, 59, 0.8);
  border: 1px solid var(--border, #1e293b);
  color: var(--text-muted, #94a3b8);
  font-size: 12.5px;
  font-weight: 600;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  transition: all var(--transition-fast, 0.15s ease);
}

.btn-service-dismiss:hover {
  background: #334155;
  color: #ffffff;
  border-color: #475569;
}

.btn-service-dismiss:active {
  transform: translateY(0);
}

/* Empty State Card */
.services-empty-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  background: rgba(15, 23, 42, 0.4);
  border: 1px dashed var(--border, #1e293b);
  border-radius: var(--radius-md, 8px);
  text-align: center;
}

.empty-icon-shield {
  width: 48px;
  height: 48px;
  background: rgba(16, 185, 129, 0.12);
  border: 1px solid rgba(16, 185, 129, 0.3);
  border-radius: 50%;
  color: #10b981;
  font-size: 22px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 12px;
}

.icon-tools {
  background: rgba(56, 189, 248, 0.12);
  border-color: rgba(56, 189, 248, 0.3);
  color: #38bdf8;
  font-size: 20px;
}

.empty-title {
  font-size: 16px;
  font-weight: 800;
  color: #ffffff;
  margin: 0;
}

.empty-desc {
  font-size: 13px;
  color: var(--text-muted, #94a3b8);
  margin: 6px 0 0;
  max-width: 480px;
}
</style>

<script>
/**
 * Tab Switching Handler
 */
function switchServicesTab(tabName) {
  document.querySelectorAll('.services-tab-btn').forEach(btn => btn.classList.remove('active'));
  document.querySelectorAll('.services-pane').forEach(pane => pane.classList.remove('active'));

  const activeBtn = tabName === 'broken-links' ? document.getElementById('tabBtnBrokenLinks') : document.getElementById('tabBtnDbRepair');
  const activePane = document.getElementById(`pane-${tabName}`);

  if (activeBtn) activeBtn.classList.add('active');
  if (activePane) activePane.classList.add('active');
}

/**
 * Fix Broken Link and Close Ticket Handler
 */
async function confirmFixLink(reportId, fileId) {
  const inputElem = document.getElementById(`filePath_${reportId}`);
  if (!inputElem) return;

  const newPath = inputElem.value.trim();
  if (newPath === '') {
    if (typeof window.showToast === 'function') {
      window.showToast('Please enter a valid file path or URL.', 'error');
    }
    inputElem.focus();
    return;
  }

  try {
    const res = await fetch('/api/catalog-services/fix-broken-link', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        report_id: reportId,
        file_id: fileId,
        file_path: newPath
      })
    });

    const json = await res.json();
    if (res.ok && json.success) {
      if (typeof window.showToast === 'function') {
        window.showToast('File path updated & report closed!', 'success');
      }
      // Immediately refresh the page as requested
      setTimeout(() => {
        window.location.reload();
      }, 100);
    } else {
      if (typeof window.showToast === 'function') {
        window.showToast(json.error || 'Failed to update link.', 'error');
      }
    }
  } catch (err) {
    if (typeof window.showToast === 'function') {
      window.showToast('Network error while updating file link.', 'error');
    }
  }
}

/**
 * Dismiss Ticket Handler without changing file record
 */
async function confirmDismissTicket(reportId) {
  try {
    const res = await fetch('/api/catalog-services/dismiss-broken-link', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        report_id: reportId
      })
    });

    const json = await res.json();
    if (res.ok && json.success) {
      if (typeof window.showToast === 'function') {
        window.showToast('Ticket closed successfully.', 'success');
      }
      // Immediately refresh the page as requested
      setTimeout(() => {
        window.location.reload();
      }, 100);
    } else {
      if (typeof window.showToast === 'function') {
        window.showToast(json.error || 'Failed to close ticket.', 'error');
      }
    }
  } catch (err) {
    if (typeof window.showToast === 'function') {
      window.showToast('Network error while closing ticket.', 'error');
    }
  }
}

// Attach to window scope
window.switchServicesTab = switchServicesTab;
window.confirmFixLink = confirmFixLink;
window.confirmDismissTicket = confirmDismissTicket;
</script>

