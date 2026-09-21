<?php
/**
 * layout_footer.php - Master Bottom Template
 */
$currentDateFormatted = date('l, F j, Y');
$dbStatus = isset($pdo) ? 'Database Online' : 'Database Offline';
?>
  </main> <!-- End of .master-main-content -->

  <!-- Master Sticky / Grounded Footer -->
  <footer class="master-footer">
    <div class="footer-row-primary">
      <div class="footer-col">
        <span class="status-indicator-dot"></span>
        <span class="footer-subtext"><?= $dbStatus ?> &bull; UTF-8 Engine</span>
      </div>

      <div class="footer-col shortcuts-legend">
        <span><kbd>Ctrl</kbd>+<kbd>S</kbd> Save Record</span>
        <span><kbd>Esc</kbd> Reset / Clear</span>
      </div>

      <div class="footer-col date-display">
        &#128197; <?= $currentDateFormatted ?>
      </div>
    </div>

    <div class="footer-row-secondary">
      <div>Videogame Cataloguer &copy; <?= date('Y') ?> &bull; Handheld & Retro Vault</div>
      <a href="#top" onclick="window.scrollTo({top: 0, behavior: 'smooth'}); return false;" class="scroll-top-link">
        Back to Top &uarr;
      </a>
    </div>
  </footer>
</div> <!-- End of .master-wrapper -->

<div id="toastContainer"></div>

</body>
</html>