</div>

<style>
/* Hide footer initially */
.footer-hidden {
    display: none;
}

/* When visible */
.footer-visible {
    display: block;
    background: #f8f9fa;
    padding: 10px 15px;
    text-align: center;
    border-top: 1px solid #ddd;
}
</style>

<footer class="bg-light text-center text-muted py-3 mt-auto border-top fixed-bottom footer-hidden" id="pageFooter">
  <div class="container">
    <small>&copy; <?php echo date('Y'); ?> Office Management System</small>
  </div>
</footer>

<!-- DataTables Bootstrap 5 CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">

<!-- jQuery DataTables core + Bootstrap 5 integration -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
  // Turn the Manager-Approved Completed Entries table into a jQuery DataTable
  document.addEventListener('DOMContentLoaded', function () {
    // Make sure jQuery and the table exist
    if (window.jQuery && $('#tasktable').length) {
      $('#tasktable').DataTable({
        // Keep the existing PHP sort order as default
        order: [],

        // Basic DataTables features
        paging: true,
        lengthChange: true,
        searching: true,
        ordering: true,
        info: true,
        autoWidth: false,

        // Disable sorting & searching on the Actions column (last column)
        columnDefs: [
          {
            targets: -1,          // last column = Actions
            orderable: false,
            searchable: false
          }
        ]
      });
    }
  });
</script>

<script>
document.addEventListener('scroll', function () {
    var footer = document.getElementById('pageFooter');

    // how far user has scrolled (bottom of viewport)
    var scrollBottom = window.innerHeight + window.scrollY;

    // total page height
    var pageHeight = document.documentElement.scrollHeight;

    // when user reaches (or almost reaches) bottom
    if (scrollBottom >= pageHeight - 5) {
        footer.classList.remove('footer-hidden');
        footer.classList.add('footer-visible');
    } else {
        footer.classList.remove('footer-visible');
        footer.classList.add('footer-hidden');
    }
});
</script>

<!-- <script>
document.addEventListener('DOMContentLoaded', function () {
    const box = document.getElementById('latestTimesheetScroll');
    if (!box) return;

    const STEP = 1;      // pixels per tick
    const INTERVAL = 60; // ms between ticks (smaller = faster)
    let autoStarted = false;
    let timerId = null;

    function startAutoScroll() {
        if (autoStarted) return;
        autoStarted = true;

        timerId = setInterval(function () {
            // Only scroll if content is taller than the box
            if (box.scrollHeight <= box.clientHeight) return;

            // One-way scroll
            box.scrollTop += STEP;

            // When we reach the bottom, jump back to top (no reverse)
            if (box.scrollTop + box.clientHeight >= box.scrollHeight) {
                box.scrollTop = 0;
            }
        }, INTERVAL);
    }

    function onUserScrollOnce() {
        // User scrolled manually at least once: start auto scroll
        startAutoScroll();

        // Remove listeners so auto-scroll won't retrigger logic
        box.removeEventListener('wheel', onUserScrollOnce);
        box.removeEventListener('touchmove', onUserScrollOnce);
        box.removeEventListener('scroll', onUserScrollOnce);
    }

    // Start auto-scroll only after user interacts once
    box.addEventListener('wheel', onUserScrollOnce, { passive: true });
    box.addEventListener('touchmove', onUserScrollOnce, { passive: true });
    box.addEventListener('scroll', onUserScrollOnce);
});
</script>

-->

<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/7.3.0/mdb.umd.min.js"></script>





</body>
</html>
