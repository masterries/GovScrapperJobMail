<?php
require_once __DIR__ . '/../includes/header.php';

$q = trim($_GET['q'] ?? '');
$mode = ($_GET['mode'] ?? 'soft') === 'full' ? 'full' : 'soft';
$filterId = intval($_GET['filter_id'] ?? 0);

// Basis-Query
$sql = "SELECT * FROM unique_jobs WHERE 1=1";
$params = [];

// Suche
if ($q !== '') {
    $terms = preg_split('/\s+/', $q);
    $clauses = [];
    foreach ($terms as $i => $term) {
        $like = "%{$term}%";
        $fields = ['title','link','group_classification','base_title','job_category','ministry','missions','organization','status'];
        if ($mode === 'full') {
            $fields[] = 'full_description';
        }
        $sub = [];
        foreach ($fields as $fIdx => $f) {
            $param = "t{$i}_{$fIdx}";
            $sub[] = "{$f} LIKE :{$param}";
            $params[$param] = $like;
        }
        $clauses[] = '(' . implode(' OR ', $sub) . ')';
    }
    $sql .= ' AND ' . implode(' AND ', $clauses);
}

// Filter anwenden
if ($filterId) {
    // Filter-Metadaten
    $fs = db_query('SELECT date_from, date_to, mode FROM filter_sets WHERE id = :id AND user_id = :u', ['id'=>$filterId,'u'=>$_SESSION['user_id']])->fetch();
    if ($fs) {
        if ($fs['date_from']) {
            $sql .= ' AND created_at >= :df';
            $params['df'] = $fs['date_from'];
        }
        if ($fs['date_to']) {
            $sql .= ' AND created_at <= :dt';
            $params['dt'] = $fs['date_to'];
        }
        // Filter-Keywords
        $kw = db_query('SELECT keyword FROM filter_keywords WHERE filter_id = :id', ['id'=>$filterId])->fetchAll(PDO::FETCH_COLUMN);
        if ($kw) {
            $fkClauses = [];
            foreach ($kw as $j => $k) {
                $like = "%{$k}%";
                $fmode = $fs['mode'];
                // wie Suche, aber für jeden Begriff
                $sub2 = [];
                $fields = ['title','link','group_classification','base_title','job_category','ministry','missions','organization','status'];
                if ($fmode === 'full') $fields[] = 'full_description';
                foreach ($fields as $fIdx2 => $f2) {
                    $param = "fkj{$j}_{$fIdx2}";
                    $sub2[] = "{$f2} LIKE :{$param}";
                    $params[$param] = $like;
                }
                $fkClauses[] = '(' . implode(' OR ', $sub2) . ')';
            }
            $sql .= ' AND (' . implode(' AND ', $fkClauses) . ')';
        }
    }
}

// Ausführen
$stmt = db_query($sql, $params);
$jobs = $stmt->fetchAll();

// Pinned-Jobs-IDs aus user_pins für Suchergebnisse
$pinnedIds = db_query('SELECT target_key FROM user_pins WHERE user_id = :u AND target_type = "job"', ['u'=>$_SESSION['user_id']])->fetchAll(PDO::FETCH_COLUMN);

// Sortiere: zuerst gepinnt, dann übrige
usort($jobs, function($a, $b) use ($pinnedIds) {
    $aPinned = in_array($a['id'], $pinnedIds);
    $bPinned = in_array($b['id'], $pinnedIds);
    if ($aPinned === $bPinned) return 0;
    return $aPinned ? -1 : 1;
});
?>

<!-- Suchformular (kompakt) -->
<div class="card mb-4">
  <div class="card-body bg-light p-3">
    <form method="get" action="results.php" class="row row-cols-lg-auto g-3 align-items-center">
      <div class="col-12 col-sm-5">
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Suchbegriff" required>
        </div>
      </div>
      <div class="col-12 col-sm-3">
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="mode" id="modeSoft" value="soft" <?= ($mode === 'soft' ? 'checked' : '') ?>>
          <label class="form-check-label" for="modeSoft">Soft</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="mode" id="modeFull" value="full" <?= ($mode === 'full' ? 'checked' : '') ?>>
          <label class="form-check-label" for="modeFull">Full</label>
        </div>
      </div>
      <div class="col-12 col-sm-2">
        <input type="hidden" name="filter_id" value="<?= $filterId ?>">
        <button type="submit" class="btn btn-primary w-100">Suchen</button>
      </div>
    </form>
  </div>
</div>

<!-- Ergebnisbereich -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0">
      <i class="bi bi-list-ul me-2"></i>
      <?= count($jobs) ?> Treffer für "<?= htmlspecialchars($q) ?>" <?= $filterId ? '(mit Filter)' : '' ?>
    </h5>
    <span class="badge bg-<?= $mode === 'full' ? 'primary' : 'info' ?>">
      <?= $mode === 'full' ? 'Fullsearch' : 'Softsearch' ?>
    </span>
  </div>
  <div class="card-body p-0">
    <!-- Sortierbare Tabelle mit DataTables -->
    <table id="jobsTable" class="table table-striped table-hover table-bordered mb-0">
      <thead class="table-light">
        <tr>
          <th style="width: 50px" class="text-center">Pin</th>
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
        <tr class="<?= in_array($job['id'], $pinnedIds) ? 'table-warning' : '' ?>">
          <td class="text-center">
            <a href="#" class="pin-link" data-id="<?= $job['id'] ?>" data-bs-toggle="tooltip" title="<?= in_array($job['id'], $pinnedIds) ? 'Job entpinnen' : 'Job pinnen' ?>">
              <i class="bi bi-pin<?= in_array($job['id'], $pinnedIds) ? '-fill text-warning' : '' ?>"></i>
            </a>
          </td>
          <td>
            <a href="job_view.php?group_key=<?= urlencode($job['group_key']) ?>" class="text-decoration-none fw-medium">
              <?= htmlspecialchars($job['title']) ?>
            </a>
          </td>
          <td><?= htmlspecialchars($job['post_date']) ?></td>
          <td><span class="badge bg-light text-dark"><?= htmlspecialchars($job['job_category']) ?></span></td>
          <td><?= htmlspecialchars($job['ministry']) ?></td>
          <td><span class="badge bg-secondary"><?= htmlspecialchars($job['status']) ?></span></td>
          <td class="text-center">
            <a href="#" class="comment-link" data-id="<?= $job['id'] ?>" data-bs-toggle="tooltip" title="Notizen bearbeiten">
              <i class="bi bi-chat-text"></i>
            </a>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Zurück zur Suche -->
<div class="mt-3">
  <a href="index.php" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-2"></i> Zurück zur Suche
  </a>
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
  var currentCommentId;
  
  // Pin toggling
  $('.pin-link').click(function(e){
    e.preventDefault();
    var jobId = $(this).data('id');
    var icon = $(this).find('i');
    $.post('/pins/toggle.php', { type: 'job', key: jobId }, function(){
      icon.toggleClass('bi-pin bi-pin-fill text-warning');
      // Zeile hervorheben/zurücksetzen
      var row = icon.closest('tr');
      row.toggleClass('table-warning');
    });
  });
  
  // Comment modal
  $('.comment-link').click(function(e){
    e.preventDefault();
    currentCommentId = $(this).data('id');
    $('#commentModal').modal('show');
    loadComments();
  });
  
  function loadComments(){
    $('#commentHistory').html('<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"></div></div>');
    $.get('/notes/list.php', { type: 'job', key: currentCommentId }, function(html){
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
    
    $.post('/notes/save.php', { type: 'job', key: currentCommentId, note: txt }, function(){
      $('#newComment').val('');
      loadComments();
      
      // Re-enable button
      btn.prop('disabled', false).html(originalHtml);
    });
  });
});
</script>