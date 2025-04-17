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
<h2>Suchergebnisse</h2>
<p><?= count($jobs) ?> Treffer für "<?= htmlspecialchars($q) ?>" (<?= htmlspecialchars($mode) ?>)</p>
<!-- Sortierbare Tabelle mit DataTables -->
<table id="jobsTable" class="table table-striped table-bordered">
  <thead>
    <tr>
      <th>Pin</th>
      <th>Titel</th>
      <th>Datum</th>
      <th>Kategorie</th>
      <th>Ministerium</th>
      <th>Status</th>
      <th>Kommentar</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($jobs as $job): ?>
    <tr>
      <td class="text-center">
        <a href="#" class="pin-link" data-id="<?= $job['id'] ?>">
          <i class="bi bi-pin<?= in_array($job['id'], $pinnedIds) ? '-fill text-warning' : '' ?>"></i>
        </a>
      </td>
      <td><a href="job_view.php?group_key=<?= urlencode($job['group_key']) ?>"><?= htmlspecialchars($job['title']) ?></a></td>
      <td><?= htmlspecialchars($job['post_date']) ?></td>
      <td><?= htmlspecialchars($job['job_category']) ?></td>
      <td><?= htmlspecialchars($job['ministry']) ?></td>
      <td><?= htmlspecialchars($job['status']) ?></td>
      <td class="text-center">
        <a href="#" class="comment-link" data-id="<?= $job['id'] ?>">
          <i class="bi bi-chat-text"></i>
        </a>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>

<!-- Comment Modal -->
<div class="modal fade" id="commentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Kommentare</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="commentHistory"></div>
        <div class="mb-3">
          <label for="newComment" class="form-label">Neuer Kommentar</label>
          <textarea id="newComment" class="form-control" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="saveComment" class="btn btn-primary">Speichern</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
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
    $('#commentHistory').html('Lade...');
    $.get('/notes/list.php', { type: 'job', key: currentCommentId }, function(html){
      $('#commentHistory').html(html);
    });
  }
  $('#saveComment').click(function(){
    var txt = $('#newComment').val().trim();
    if(!txt) return;
    $.post('/notes/save.php', { type: 'job', key: currentCommentId, note: txt }, function(){
      $('#newComment').val('');
      loadComments();
    });
  });
});
</script>