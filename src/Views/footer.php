<?php
/**
 * src/Views/footer.php
 * Modular Application Footer Component.
 */

declare(strict_types=1);

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}
?>
<!-- Sticky Grounded Footer -->
<footer class="master-footer">
  <div class="footer-row-primary">
    <div class="footer-col">
      <span class="status-indicator-dot"></span>
      <span class="footer-subtext">Database Online &bull; UTF-8 Engine</span>
    </div>
    <div class="footer-col shortcuts-legend">
      <span><kbd>Ctrl</kbd>+<kbd>S</kbd> Save Record</span>
      <span><kbd>Esc</kbd> Reset / Clear</span>
    </div>
    <div class="footer-col date-display">
      📅 <?= date('l, F j, Y') ?>
    </div>
  </div>
  <div class="footer-row-secondary">
    <div>Videogame Vault &copy; <?= date('Y') ?> &bull; Handheld & Retro Vault</div>
    <a href="#top" onclick="window.scrollTo({top: 0, behavior: 'smooth'}); return false;" class="scroll-top-link">
      Back to Top &uarr;
    </a>
  </div>
</footer>
