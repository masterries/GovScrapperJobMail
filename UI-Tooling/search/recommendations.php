<?php
require_once __DIR__ . '/../includes/header.php';

// Benutzerfilter laden
$stmt = db_query('SELECT id, name, mode FROM filter_sets WHERE user_id = :u', ['u' => $_SESSION['user_id']]);
$userFilters = $stmt->fetchAll();

// Datum vor 7 Tagen berechnen
$dateBefore7Days = date('Y-m-d', strtotime('-7 days'));

// Empfehlungen sammeln
$recommendations = [];

// Für jeden Filter Empfehlungen erstellen
foreach ($userFilters as $filter) {
    // Filter-Keywords laden
    $keywords = db_query('SELECT keyword FROM filter_keywords WHERE filter_id = :id', ['id' => $filter['id']])->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($keywords)) {
        continue; // Überspringe Filter ohne Keywords
    }
    
    // SQL für diesen Filter zusammenbauen
    $sql = "SELECT * FROM unique_jobs WHERE created_at >= :dateLimit";
    $params = ['dateLimit' => $dateBefore7Days];
    
    // Keywords-Klauseln erstellen
    $keywordClauses = [];
    foreach ($keywords as $j => $keyword) {
        $like = "%{$keyword}%";
        
        // Suchfelder für Keywords - NUR Softmodus verwenden, unabhängig vom Filter-Modus
        // Das bedeutet, wir suchen nur in Basisdaten, nicht in Aufgaben oder Profil
        $searchFields = ['title', 'link', 'group_classification', 'base_title', 'job_category', 'ministry', 'organization', 'status'];
        // 'missions' wurde entfernt, damit nicht in Aufgaben gesucht wird
        
        // WICHTIG: Wir suchen NICHT in full_description, unabhängig vom Filtermodus
        
        // Klauseln für alle Suchfelder erstellen
        $fieldClauses = [];
        foreach ($searchFields as $fIdx => $field) {
            $param = "kw{$j}_{$fIdx}";
            $fieldClauses[] = "{$field} LIKE :{$param}";
            $params[$param] = $like;
        }
        
        // Alle Feldklauseln für dieses Keyword mit ODER verbinden
        $keywordClauses[] = '(' . implode(' OR ', $fieldClauses) . ')';
    }
    
    // Keyword-Klauseln je nach Filtermodus mit AND oder OR verbinden
    $keywordOperator = ($filter['mode'] === 'soft') ? ' OR ' : ' AND ';
    $sql .= " AND (" . implode($keywordOperator, $keywordClauses) . ")";
    
    // Query ausführen und Ergebnisse speichern
    $stmt = db_query($sql, $params);
    $filterJobs = $stmt->fetchAll();
    
    // Jobs mit Filter-ID markieren und zum Gesamtergebnis hinzufügen
    foreach ($filterJobs as &$job) {
        $job['filter_id'] = $filter['id'];
        $job['filter_name'] = $filter['name'];
        
        // Die auslösenden Keywords finden mit Angabe des Feldes
        $matchedKeywordsDetails = []; // Für Tooltip/Hover
        $matchedKeywordsSimple = []; // Für normale Anzeige
        foreach ($keywords as $keyword) {
            // Wir prüfen jedes Feld einzeln, um das Feld zu identifizieren
            $searchFields = [
                'title' => 'Titel',
                'link' => 'Link',
                'group_classification' => 'Klassifikation',
                'base_title' => 'Basis-Titel',
                'job_category' => 'Kategorie',
                'ministry' => 'Ministerium',
                'organization' => 'Organisation',
                'status' => 'Status'
                // 'missions' wurde entfernt, damit nicht in Aufgaben gesucht wird
            ];
            
            $foundInFields = [];
            $keywordFound = false;
            
            foreach ($searchFields as $field => $fieldName) {
                if (stripos($job[$field], $keyword) !== false) {
                    $foundInFields[] = $fieldName;
                    $keywordFound = true;
                }
            }
            
            if ($keywordFound) {
                $matchedKeywordsSimple[] = $keyword;
                $matchedKeywordsDetails[] = $keyword . ' (' . implode(', ', $foundInFields) . ')';
            }
        }
        
        $job['matched_keywords'] = implode(', ', $matchedKeywordsDetails);
        $job['matched_keywords_simple'] = implode(', ', $matchedKeywordsSimple);
        $recommendations[] = $job;
    }
}

// Duplikate so entfernen, dass alle Filter-Treffer erhalten bleiben
$uniqueRecommendations = [];
$jobFilters = []; // Speichert für jeden Job alle Filter und Keywords
$seenIds = [];

// Alle Jobs nach ihrer ID gruppieren
$jobsById = [];
foreach ($recommendations as $job) {
    $jobId = $job['id'];
    if (!isset($jobsById[$jobId])) {
        $jobsById[$jobId] = [
            'job' => $job,
            'filters' => [
                [
                    'name' => $job['filter_name'],
                    'id' => $job['filter_id']
                ]
            ],
            'keywords' => explode(', ', $job['matched_keywords_simple'])
        ];
    } else {
        // Filter hinzufügen
        $jobsById[$jobId]['filters'][] = [
            'name' => $job['filter_name'],
            'id' => $job['filter_id']
        ];
        // Keywords hinzufügen
        $jobsById[$jobId]['keywords'] = array_merge(
            $jobsById[$jobId]['keywords'],
            explode(', ', $job['matched_keywords_simple'])
        );
    }
}

// Aus den gruppierten Daten wieder einzelne Job-Einträge machen
foreach ($jobsById as $jobId => $jobData) {
    $job = $jobData['job'];
    
    // Alle Filter für diesen Job zusammenfassen
    $filterNames = array_map(function($filter) {
        return $filter['name'];
    }, $jobData['filters']);
    
    $job['filter_names'] = implode(', ', $filterNames);
    
    // Alle Keywords für diesen Job zusammenfassen (Duplikate entfernen)
    $uniqueKeywords = array_values(array_unique($jobData['keywords']));
    $job['all_matched_keywords'] = implode(', ', $uniqueKeywords);
    
    $uniqueRecommendations[] = $job;
}

// Pinned-Jobs-IDs laden
$pinnedIds = db_query('SELECT target_key FROM user_pins WHERE user_id = :u AND target_type = "job"', ['u'=>$_SESSION['user_id']])->fetchAll(PDO::FETCH_COLUMN);

// Sortiere: zuerst gepinnt, dann übrige
usort($uniqueRecommendations, function($a, $b) use ($pinnedIds) {
    $aPinned = in_array($a['id'], $pinnedIds);
    $bPinned = in_array($b['id'], $pinnedIds);
    if ($aPinned === $bPinned) return 0;
    return $aPinned ? -1 : 1;
});

// Hilfsfunktion zum Kürzen von Titeln
function truncateTitle($title, $length = 50) {
    if (strlen($title) <= $length) {
        return $title;
    }
    return substr($title, 0, $length) . '...';
}

// Seitenspezifisches JavaScript, das nach den Bibliotheken geladen wird
$pageSpecificScript = <<<EOT
<script>
$(document).ready(function() {
  // DataTable initialisieren nur wenn noch nicht initialisiert
  if (!$.fn.dataTable.isDataTable('#recommendationsTable')) {
    var table = $('#recommendationsTable').DataTable({
      language: {
        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/de-DE.json',
      },
      order: [[2, 'desc']], // Spalte 2 (Datum) absteigend sortieren
      pageLength: 25
    });
  } else {
    // Falls bereits initialisiert, stelle sicher, dass die Sortierung angewendet wird
    var table = $('#recommendationsTable').DataTable();
    table.order([2, 'desc']).draw();
  }
  
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
  
  // Pin-Toggle-Funktionalität mit klassischem Formular
  $('.pin-link').click(function(e) {
    e.preventDefault();
    var link = $(this);
    var jobId = link.data('id');
    
    // Erstelle ein verstecktes Formular
    var form = $('<form>', {
      'method': 'post',
      'action': '/pins/toggle.php',
      'style': 'display: none;'
    });
    
    // Füge die notwendigen Felder hinzu
    form.append($('<input>', {
      'type': 'hidden',
      'name': 'type',
      'value': 'job'
    }));
    
    form.append($('<input>', {
      'type': 'hidden',
      'name': 'key',
      'value': jobId
    }));
    
    // Füge das Formular zur Seite hinzu und sende es ab
    $('body').append(form);
    form.submit();
  });
});
</script>
EOT;
?>

<div class="card mb-4">
  <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
    <h5 class="mb-0">
      <i class="bi bi-lightbulb me-2"></i>
      Jobempfehlungen der letzten 7 Tage
    </h5>
    <a href="index.php" class="btn btn-outline-light btn-sm">
      <i class="bi bi-search me-1"></i> Zur Suche
    </a>
  </div>
  
  <div class="card-body p-0">
    <?php if (empty($uniqueRecommendations)): ?>
      <div class="p-4 text-center">
        <div class="text-muted mb-3">
          <i class="bi bi-inbox-fill display-4"></i>
        </div>
        <h4>Keine aktuellen Empfehlungen gefunden</h4>
        <p>Es wurden in den letzten 7 Tagen keine Jobs gefunden, die zu deinen Filtern passen.</p>
        <div class="mt-3">
          <a href="/filters/list.php" class="btn btn-outline-primary">
            <i class="bi bi-gear me-2"></i>Filter verwalten
          </a>
        </div>
      </div>
    <?php else: ?>
      <!-- Sortierbare Tabelle mit DataTables -->
      <table id="recommendationsTable" class="table table-striped table-hover table-bordered mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 50px" class="text-center">Pin</th>
            <th>Titel</th>
            <th style="width: 120px">Datum</th>
            <th style="width: 140px">Filter</th>
            <th style="width: 140px">Keywords</th>
            <th style="width: 50px" class="text-center">Notiz</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($uniqueRecommendations as $job): ?>
          <tr class="<?= in_array($job['id'], $pinnedIds) ? 'table-warning' : '' ?>">
            <td class="text-center">
              <a href="#" class="pin-link" data-id="<?= $job['id'] ?>" data-bs-toggle="tooltip" title="<?= in_array($job['id'], $pinnedIds) ? 'Job entpinnen' : 'Job pinnen' ?>">
                <i class="bi bi-pin<?= in_array($job['id'], $pinnedIds) ? '-fill text-warning' : '' ?>"></i>
              </a>
            </td>
            <td>
              <a href="job_view.php?group_key=<?= urlencode($job['group_key']) ?>" class="text-decoration-none fw-medium">
                <?= htmlspecialchars(truncateTitle($job['title'])) ?>
                <?php if(!empty($job['base_title']) && $job['base_title'] !== $job['title']): ?>
                  <div class="small text-muted">
                    <?= htmlspecialchars($job['base_title']) ?>
                  </div>
                <?php endif; ?>
              </a>
            </td>
            <td><?= htmlspecialchars($job['post_date'] ?? $job['created_at']) ?></td>
            <td><span class="badge bg-info"><?= htmlspecialchars($job['filter_names']) ?></span></td>
            <td>
              <span data-bs-toggle="tooltip" title="<?= htmlspecialchars($job['matched_keywords']) ?>">
                <?= htmlspecialchars($job['all_matched_keywords']) ?>
              </span>
            </td>
            <td class="text-center">
              <a href="#" class="comment-link" data-id="<?= $job['id'] ?>" data-bs-toggle="tooltip" title="Notizen bearbeiten">
                <i class="bi bi-chat-text"></i>
              </a>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
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
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php';?>
<?php echo $pageSpecificScript; ?>