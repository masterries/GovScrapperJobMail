<?php
require_once __DIR__ . '/../includes/header.php';

// Gepinnte Gruppen mit zusätzlichen Daten für die Tabelle
$stmt = db_query('
    SELECT 
        up.id as pin_id,
        up.target_key, 
        uj.title, 
        uj.base_title,
        uj.post_date,
        uj.ministry,
        uj.job_category,
        uj.status,
        up.pinned_at
    FROM user_pins up 
    JOIN unique_jobs uj ON up.target_key = uj.group_key 
    WHERE up.user_id = :u AND up.target_type = "job_group" 
    ORDER BY up.pinned_at DESC', 
    ['u' => $_SESSION['user_id']]
);
$groups = $stmt->fetchAll();

// Gepinnte einzelne Jobs mit zusätzlichen Daten
$stmt2 = db_query('
    SELECT 
        up.id as pin_id,
        up.target_key, 
        uj.title, 
        uj.base_title,
        uj.post_date,
        uj.ministry,
        uj.job_category,
        uj.status,
        up.pinned_at
    FROM user_pins up 
    JOIN unique_jobs uj ON up.target_key = uj.id 
    WHERE up.user_id = :u AND up.target_type = "job" 
    ORDER BY up.pinned_at DESC', 
    ['u' => $_SESSION['user_id']]
);
$jobs = $stmt2->fetchAll();

// Notizen für Job-IDs abrufen
$jobIds = array_column($jobs, 'target_key');
$jobNotes = [];
if (!empty($jobIds)) {
    $params = [];
    $placeholders = [];
    foreach ($jobIds as $i => $id) {
        $placeholder = ":id{$i}";
        $placeholders[] = $placeholder;
        $params[$placeholder] = $id;
    }
    $inClause = implode(',', $placeholders);
    $params['user_id'] = $_SESSION['user_id'];
    
    $noteStmt = db_query(
        "SELECT target_key, COUNT(*) as note_count FROM job_notes 
         WHERE user_id = :user_id AND target_type = 'job' AND target_key IN ({$inClause})
         GROUP BY target_key",
        $params
    );
    while ($row = $noteStmt->fetch()) {
        $jobNotes[$row['target_key']] = $row['note_count'];
    }
}

// Notizen für Gruppen abrufen
$groupKeys = array_column($groups, 'target_key');
$groupNotes = [];
if (!empty($groupKeys)) {
    $params = [];
    $placeholders = [];
    foreach ($groupKeys as $i => $key) {
        $placeholder = ":key{$i}";
        $placeholders[] = $placeholder;
        $params[$placeholder] = $key;
    }
    $inClause = implode(',', $placeholders);
    $params['user_id'] = $_SESSION['user_id'];
    
    $noteStmt = db_query(
        "SELECT target_key, COUNT(*) as note_count FROM job_notes 
         WHERE user_id = :user_id AND target_type = 'job_group' AND target_key IN ({$inClause})
         GROUP BY target_key",
        $params
    );
    while ($row = $noteStmt->fetch()) {
        $groupNotes[$row['target_key']] = $row['note_count'];
    }
}
?>

<div class="row mb-4">
    <div class="col">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="h3"><i class="bi bi-pin-angle-fill me-2"></i>Gepinnte Jobs</h1>
            <a href="/search/index.php" class="btn btn-outline-primary">
                <i class="bi bi-search me-2"></i>Neue Suche
            </a>
        </div>
    </div>
</div>

<!-- Job-Gruppen -->
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0">
      <i class="bi bi-collection me-2"></i>
      Job-Gruppen (<?= count($groups) ?>)
    </h5>
    <span class="badge bg-warning">Gepinnt</span>
  </div>
  <div class="card-body p-0">
    <?php if ($groups): ?>
    <table id="groupsTable" class="table table-striped table-hover table-bordered mb-0">
      <thead class="table-light">
        <tr>
          <th style="width: 50px" class="text-center">Aktion</th>
          <th>Titel</th>
          <th style="width: 120px">Datum</th>
          <th style="width: 140px">Kategorie</th>
          <th style="width: 140px">Ministerium</th>
          <th style="width: 100px">Status</th>
          <th style="width: 50px" class="text-center">Notiz</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($groups as $group): ?>
        <tr>
          <td class="text-center">
            <a href="#" class="unpin-link" data-id="<?= $group['pin_id'] ?>" data-type="job_group" data-bs-toggle="tooltip" title="Entpinnen">
              <i class="bi bi-pin-fill text-warning"></i>
            </a>
          </td>
          <td>
            <a href="/search/job_view.php?group_key=<?= urlencode($group['target_key']) ?>" class="text-decoration-none fw-medium" data-bs-toggle="tooltip" data-bs-html="true" title="<?= htmlspecialchars($group['title']) ?>">
              <?= !empty($group['base_title']) ? htmlspecialchars($group['base_title']) : htmlspecialchars($group['title']) ?>
            </a>
          </td>
          <td><?= htmlspecialchars($group['post_date']) ?></td>
          <td><span class="badge bg-light text-dark"><?= htmlspecialchars($group['job_category'] ?? '-') ?></span></td>
          <td><?= htmlspecialchars($group['ministry'] ?? '-') ?></td>
          <td><span class="badge bg-secondary"><?= htmlspecialchars($group['status'] ?? '-') ?></span></td>
          <td class="text-center">
            <a href="#" class="comment-link" data-id="<?= $group['target_key'] ?>" data-type="job_group" data-bs-toggle="tooltip" title="Notizen bearbeiten">
              <i class="bi bi-chat-text"></i>
              <?php if (!empty($groupNotes[$group['target_key']])): ?>
                <span class="badge bg-info"><?= $groupNotes[$group['target_key']] ?></span>
              <?php endif; ?>
            </a>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <?php else: ?>
      <div class="p-4 text-center">
        <p class="text-muted mb-0">Keine gepinnten Gruppen vorhanden.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Einzel-Jobs -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0">
      <i class="bi bi-briefcase me-2"></i>
      Einzel-Jobs (<?= count($jobs) ?>)
    </h5>
    <span class="badge bg-warning">Gepinnt</span>
  </div>
  <div class="card-body p-0">
    <?php if ($jobs): ?>
    <table id="jobsTable" class="table table-striped table-hover table-bordered mb-0">
      <thead class="table-light">
        <tr>
          <th style="width: 50px" class="text-center">Aktion</th>
          <th>Titel</th>
          <th style="width: 120px">Datum</th>
          <th style="width: 140px">Kategorie</th>
          <th style="width: 140px">Ministerium</th>
          <th style="width: 100px">Status</th>
          <th style="width: 50px" class="text-center">Notiz</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($jobs as $job): ?>
        <tr>
          <td class="text-center">
            <a href="#" class="unpin-link" data-id="<?= $job['pin_id'] ?>" data-type="job" data-bs-toggle="tooltip" title="Entpinnen">
              <i class="bi bi-pin-fill text-warning"></i>
            </a>
          </td>
          <td>
            <a href="/search/job_view_single.php?job_id=<?= urlencode($job['target_key']) ?>" class="text-decoration-none fw-medium" data-bs-toggle="tooltip" data-bs-html="true" title="<?= htmlspecialchars($job['title']) ?>">
              <?= htmlspecialchars($job['title']) ?>
            </a>
          </td>
          <td><?= htmlspecialchars($job['post_date']) ?></td>
          <td><span class="badge bg-light text-dark"><?= htmlspecialchars($job['job_category'] ?? '-') ?></span></td>
          <td><?= htmlspecialchars($job['ministry'] ?? '-') ?></td>
          <td><span class="badge bg-secondary"><?= htmlspecialchars($job['status'] ?? '-') ?></span></td>
          <td class="text-center">
            <a href="#" class="comment-link" data-id="<?= $job['target_key'] ?>" data-type="job" data-bs-toggle="tooltip" title="Notizen bearbeiten">
              <i class="bi bi-chat-text"></i>
              <?php if (!empty($jobNotes[$job['target_key']])): ?>
                <span class="badge bg-info"><?= $jobNotes[$job['target_key']] ?></span>
              <?php endif; ?>
            </a>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <?php else: ?>
      <div class="p-4 text-center">
        <p class="text-muted mb-0">Keine gepinnten Einzel-Jobs vorhanden.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Comment Modal -->
<div class="modal fade" id="commentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-chat-text me-2"></i>Notizen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        <div id="commentHistory" class="mb-3 p-3 bg-light rounded" style="max-height: 300px; overflow-y: auto;"></div>
        <div class="mb-3">
          <label for="newComment" class="form-label">Neue Notiz</label>
          <textarea id="newComment" class="form-control" rows="3" placeholder="Notiz eingeben..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
        <button type="button" id="saveComment" class="btn btn-primary">
          <i class="bi bi-save me-1"></i> Speichern
        </button>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php';?>

<script>
$(document).ready(function(){
  // DataTables Initialisierung mit Prüfung, ob bereits initialisiert
  if ($.fn.dataTable.isDataTable('#groupsTable')) {
    $('#groupsTable').DataTable().destroy();
  }
  if ($.fn.dataTable.isDataTable('#jobsTable')) {
    $('#jobsTable').DataTable().destroy();
  }
  
  // Tabellen neu initialisieren
  $('#groupsTable, #jobsTable').DataTable({
    language: {
      url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/de-DE.json'
    },
    responsive: true,
    order: [[1, 'asc']]
  });
  
  // Tooltip Initialisierung
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
  });
  
  var currentCommentId;
  var currentCommentType;
  
  // Unpin-Funktion
  $('.unpin-link').click(function(e){
    e.preventDefault();
    var link = $(this);
    var pinId = link.data('id');
    
    if(confirm('Möchtest du diesen Job wirklich entpinnen?')) {
      $.post('delete_pin.php', { pin_id: pinId }, function(){
        // Zeile entfernen und Tabelle aktualisieren
        link.closest('tr').fadeOut(300, function(){
          $(this).remove();
          
          // Prüfen, ob noch Zeilen übrig sind
          var table = link.closest('table');
          var tbody = table.find('tbody');
          if(tbody.find('tr').length === 0) {
            // Tabelle mit Nachricht ersetzen wenn keine Einträge mehr
            var card = table.closest('.card-body');
            var type = (table.attr('id') === 'groupsTable') ? 'Gruppen' : 'Einzel-Jobs';
            card.html('<div class="p-4 text-center"><p class="text-muted mb-0">Keine gepinnten ' + type + ' vorhanden.</p></div>');
          }
        });
      });
    }
  });
  
  // Comment Modal
  $('.comment-link').click(function(e){
    e.preventDefault();
    currentCommentId = $(this).data('id');
    currentCommentType = $(this).data('type');
    $('#commentModal').modal('show');
    loadComments();
  });
  
  function loadComments(){
    $('#commentHistory').html('<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"></div></div>');
    $.get('/notes/list.php', { type: currentCommentType, key: currentCommentId }, function(html){
      $('#commentHistory').html(html);
    });
  }
  
  $('#saveComment').click(function(){
    var txt = $('#newComment').val().trim();
    if(!txt) return;
    
    // Disable button and show spinner
    var btn = $(this);
    var originalHtml = btn.html();
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Speichern...');
    
    $.post('/notes/save.php', { type: currentCommentType, key: currentCommentId, note: txt }, function(){
      $('#newComment').val('');
      loadComments();
      
      // Re-enable button
      btn.prop('disabled', false).html(originalHtml);
    });
  });
});
</script>