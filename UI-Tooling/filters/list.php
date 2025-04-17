<?php
require_once __DIR__ . '/../includes/header.php';

// Alle Filter des aktuellen Users
$stmt = db_query('SELECT fs.*, (SELECT COUNT(*) FROM filter_keywords fk WHERE fk.filter_id = fs.id) AS kw_count FROM filter_sets fs WHERE user_id = :u', ['u'=>$_SESSION['user_id']]);
$filters = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="h3"><i class="bi bi-funnel-fill me-2"></i>Filter verwalten</h1>
            <a href="edit.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Neuen Filter anlegen
            </a>
        </div>
    </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0">
      <i class="bi bi-list-ul me-2"></i>
      Meine Filter (<?= count($filters) ?>)
    </h5>
    <span class="badge bg-info">Verfügbar</span>
  </div>
  <div class="card-body p-0">
    <?php if ($filters): ?>
    <table id="filtersTable" class="table table-striped table-hover table-bordered mb-0">
      <thead class="table-light">
        <tr>
          <th>Name</th>
          <th style="width: 140px">Von</th>
          <th style="width: 140px">Bis</th>
          <th style="width: 100px">Modus</th>
          <th style="width: 100px" class="text-center">Keywords</th>
          <th style="width: 120px" class="text-center">Aktionen</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($filters as $f): ?>
        <tr>
          <td>
            <span class="fw-medium"><?= htmlspecialchars($f['name']) ?></span>
          </td>
          <td><?= htmlspecialchars($f['date_from'] ?? '-') ?></td>
          <td><?= htmlspecialchars($f['date_to'] ?? '-') ?></td>
          <td>
            <span class="badge bg-<?= $f['mode'] === 'full' ? 'primary' : 'info' ?>">
              <?= htmlspecialchars($f['mode']) ?>
            </span>
          </td>
          <td class="text-center">
            <span class="badge bg-light text-dark"><?= $f['kw_count'] ?></span>
          </td>
          <td class="text-center">
            <div class="btn-group btn-group-sm">
              <a href="/search/results.php?filter_id=<?= $f['id'] ?>" class="btn btn-outline-success" data-bs-toggle="tooltip" title="Filter anwenden">
                <i class="bi bi-play-fill"></i>
              </a>
              <a href="edit.php?id=<?= $f['id'] ?>" class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Filter bearbeiten">
                <i class="bi bi-pencil-fill"></i>
              </a>
              <a href="delete.php?id=<?= $f['id'] ?>" onclick="return confirm('Filter wirklich löschen?');" class="btn btn-outline-danger" data-bs-toggle="tooltip" title="Filter löschen">
                <i class="bi bi-trash-fill"></i>
              </a>
            </div>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <?php else: ?>
      <div class="p-4 text-center">
        <p class="text-muted mb-0">Keine Filter vorhanden. <a href="edit.php">Legen Sie Ihren ersten Filter an</a>.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php';?>

<script>
$(document).ready(function(){
  // DataTables Initialisierung
  $('#filtersTable').DataTable({
    language: {
      url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/de-DE.json'
    },
    responsive: true,
    order: [[0, 'asc']]
  });
  
  // Tooltip Initialisierung
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
  });
});
</script>