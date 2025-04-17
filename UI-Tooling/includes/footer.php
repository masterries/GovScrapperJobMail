</main>
<footer class="mt-5">
  <div class="container">
    <div class="row">
      <div class="col-md-6 text-center text-md-start">
        <p class="mb-0">&copy; <?= date('Y') ?> JobSearch Portal</p>
      </div>
      <div class="col-md-6 text-center text-md-end">
        <p class="mb-0">Made with <i class="bi bi-heart-fill text-danger"></i> for efficiency</p>
      </div>
    </div>
  </div>
</footer>

<!-- JS Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<!-- Initialize DataTables -->
<script>
$(document).ready(function() {
  // DataTables Initialization
  if ($('#jobsTable').length) {
    $('#jobsTable').DataTable({
      language: {
        url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/de-DE.json'
      },
      responsive: true,
      order: [[1, 'desc']]
    });
  }
  
  // Tooltips
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
  });
  
  // Active navigation highlighting
  const currentPath = window.location.pathname;
  $('.nav-link').each(function() {
    const linkPath = $(this).attr('href');
    if (currentPath.includes(linkPath) && linkPath !== '/') {
      $(this).addClass('active');
    }
  });
});
</script>

<!-- Any additional page-specific scripts -->
<?php if (isset($pageSpecificScript)): ?>
  <?= $pageSpecificScript ?>
<?php endif; ?>
</body>
</html>