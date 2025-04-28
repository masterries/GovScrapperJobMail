<?php
require_once __DIR__ . '/../includes/header.php';

$q = trim($_GET['q'] ?? '');
$mode = ($_GET['mode'] ?? 'soft') === 'full' ? 'full' : 'soft';
$filterId = intval($_GET['filter_id'] ?? 0);
$searchTable = ($_GET['search_table'] ?? 'unique') === 'all' ? 'jobs' : 'unique_jobs';

// Basis-Query
$sql = "SELECT * FROM {$searchTable} WHERE 1=1";
$params = [];

// Definiere die Suchfelder je nach ausgewählter Tabelle
if ($searchTable === 'unique_jobs') {
    $searchFields = ['title', 'link', 'group_classification', 'base_title', 'job_category', 'ministry', 'missions', 'organization', 'status'];
} else { // jobs Tabelle
    $searchFields = ['title', 'link', 'group_classification', 'job_category', 'ministry', 'missions', 'organization', 'status'];
}
if ($mode === 'full') {
    $searchFields[] = 'full_description';
}

// Suche
if ($q !== '') {
    $terms = preg_split('/\s+/', $q);
    $clauses = [];
    foreach ($terms as $i => $term) {
        $like = "%{$term}%";
        $sub = [];
        foreach ($searchFields as $fIdx => $f) {
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
    $fs = db_query('SELECT name, date_from, date_to, mode FROM filter_sets WHERE id = :id AND user_id = :u', ['id'=>$filterId,'u'=>$_SESSION['user_id']])->fetch();
    if ($fs) {
        if ($fs['date_from']) {
            $sql .= ' AND (created_at >= :df OR created_at IS NULL)';
            $params['df'] = $fs['date_from'];
        }
        if ($fs['date_to']) {
            $sql .= ' AND (created_at <= :dt OR created_at IS NULL)';
            $params['dt'] = $fs['date_to'];
        }
        // Filter-Keywords
        $kw = db_query('SELECT keyword FROM filter_keywords WHERE filter_id = :id', ['id'=>$filterId])->fetchAll(PDO::FETCH_COLUMN);
        if ($kw) {
            $fkClauses = [];
            foreach ($kw as $j => $k) {
                $like = "%{$k}%";
                $fmode = $fs['mode'];
                // Definiere die Suchfelder auch für Filter je nach Tabelle
                if ($searchTable === 'unique_jobs') {
                    $filterFields = ['title', 'link', 'group_classification', 'base_title', 'job_category', 'ministry', 'missions', 'organization', 'status'];
                } else { // jobs Tabelle
                    $filterFields = ['title', 'link', 'group_classification', 'job_category', 'ministry', 'missions', 'organization', 'status'];
                }
                if ($fmode === 'full') {
                    $filterFields[] = 'full_description';
                }
                
                $sub2 = [];
                foreach ($filterFields as $fIdx2 => $f2) {
                    $param = "fkj{$j}_{$fIdx2}";
                    $sub2[] = "{$f2} LIKE :{$param}";
                    $params[$param] = $like;
                }
                $fkClauses[] = '(' . implode(' OR ', $sub2) . ')';
            }
            
            // Verbinde Keywords je nach Filtermodus: im Soft-Modus mit OR, sonst mit AND
            $keywordOperator = ($fs['mode'] === 'soft') ? ' OR ' : ' AND ';
            $sql .= ' AND (' . implode($keywordOperator, $fkClauses) . ')';
        }
    }
}

// Ausführen
$stmt = db_query($sql, $params);
$jobs = $stmt->fetchAll();

// Pinned-Jobs-IDs aus user_pins für Suchergebnisse
if ($searchTable === 'unique_jobs') {
    $pinnedIds = db_query('SELECT target_key FROM user_pins WHERE user_id = :u AND target_type = "job"', ['u'=>$_SESSION['user_id']])->fetchAll(PDO::FETCH_COLUMN);
} else {
    // Für die jobs Tabelle
    $pinnedIds = db_query('SELECT target_key FROM user_pins WHERE user_id = :u AND target_type = "job"', ['u'=>$_SESSION['user_id']])->fetchAll(PDO::FETCH_COLUMN);
}

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
      <div class="col-12 col-sm-4">
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Suchbegriff" required>
        </div>
      </div>
      <div class="col-12 col-sm-2">
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="mode" id="modeSoft" value="soft" <?= ($mode === 'soft' ? 'checked' : '') ?>>
          <label class="form-check-label" for="modeSoft">Soft</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="mode" id="modeFull" value="full" <?= ($mode === 'full' ? 'checked' : '') ?>>
          <label class="form-check-label" for="modeFull">Full</label>
        </div>
      </div>
      <div class="col-12 col-sm-3">
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="search_table" id="tableUnique" value="unique" <?= ($searchTable === 'unique_jobs' ? 'checked' : '') ?>>
          <label class="form-check-label" for="tableUnique">Unique</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="search_table" id="tableAll" value="all" <?= ($searchTable === 'jobs' ? 'checked' : '') ?>>
          <label class="form-check-label" for="tableAll">All</label>
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
    <div>
      <span class="badge bg-<?= $mode === 'full' ? 'primary' : 'info' ?>">
        <?= $mode === 'full' ? 'Fullsearch' : 'Softsearch' ?>
      </span>
      <span class="badge bg-<?= $searchTable === 'jobs' ? 'warning' : 'secondary' ?> ms-2">
        <?= $searchTable === 'jobs' ? 'Alle Jobs' : 'Unique Jobs' ?>
      </span>
    </div>
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
            <?php if ($searchTable === 'unique_jobs'): ?>
            <a href="job_view.php?group_key=<?= urlencode($job['group_key']) ?>" class="text-decoration-none fw-medium">
            <?php else: ?>
            <a href="job_view_single.php?job_id=<?= urlencode($job['id']) ?>" class="text-decoration-none fw-medium">
            <?php endif; ?>
              <?= htmlspecialchars($job['title']) ?>
              <?php if($searchTable === 'unique_jobs' && !empty($job['base_title']) && $job['base_title'] !== $job['title']): ?>
                <div class="small text-muted">
                  <?= htmlspecialchars($job['base_title']) ?>
                </div>
              <?php endif; ?>
            </a>
          </td>
          <td><?= htmlspecialchars($job['post_date'] ?? $job['created_at']) ?></td>
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
      </div>
    </div>
  </div>
</div>

<!-- JavaScript für Kommentar-Modal und Pin-Funktionalität -->
<script>
$(document).ready(function() {
  // Comment-Modal mit dynamischen Daten füllen
  $('.comment-link').click(function(e) {
    e.preventDefault();
    var jobId = $(this).data('id');
    var modal = $('#commentModal');
    
    // Daten laden
    modal.find('.modal-body').html('<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');
    modal.modal('show');
    
    // AJAX-Anfrage für Notizen
    $.get('/notes/list.php', {type: 'job', key: jobId}, function(data) {
      modal.find('.modal-body').html(data);
      
      // Formular-Handler
      modal.find('form').on('submit', function(e) {
        e.preventDefault();
        var noteText = $('#note-text').val();
        $.post('/notes/save.php', {type: 'job', key: jobId, note: noteText}, function() {
          // Nach Speichern neu laden
          location.reload();
        });
      });
    });
  });
  
  // Pin-Toggle-Funktionalität
  $('.pin-link').click(function(e) {
    e.preventDefault();
    var link = $(this);
    var id = link.data('id');
    
    $.post('/pins/toggle.php', {type: 'job', key: id}, function() {
      var icon = link.find('i');
      if (icon.hasClass('bi-pin')) {
        icon.removeClass('bi-pin').addClass('bi-pin-fill text-warning');
        link.parents('tr').addClass('table-warning');
        link.attr('title', 'Job entpinnen');
      } else {
        icon.removeClass('bi-pin-fill text-warning').addClass('bi-pin');
        link.parents('tr').removeClass('table-warning');
        link.attr('title', 'Job pinnen');
      }
      
      // Tooltip aktualisieren
      var tooltip = bootstrap.Tooltip.getInstance(link[0]);
      if (tooltip) {
        tooltip.dispose();
      }
      new bootstrap.Tooltip(link[0]);
    });
  });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php';?>