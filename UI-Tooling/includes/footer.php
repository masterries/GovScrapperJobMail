</main>
<footer>
    <p>&copy; <?= date('Y') ?> Job Suchmaschine</p>
</footer>
<!-- JS Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJ8..." crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFY..." crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
<script>
  $(document).ready(function() {
    if ($('#jobsTable').length) {
      $('#jobsTable').DataTable({
        order: [[1, 'desc']], // default sort by second column (Datum) desc
        language: { url: 'https://cdn.datatables.net/plug-ins/1.12.1/i18n/de-DE.json' }
      });
    }
  });
</script>
</body>
</html>