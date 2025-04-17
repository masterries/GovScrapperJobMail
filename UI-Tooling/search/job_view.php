<?php
require_once __DIR__ . '/../includes/header.php';

$groupKey = $_GET['group_key'] ?? '';
if (!$groupKey) {
    echo '<div class="alert alert-danger">Ungültiger Job-Group-Key.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Meta der Gruppe
$stmt = db_query('SELECT * FROM unique_jobs WHERE group_key = :gk', ['gk' => $groupKey]);
$meta = $stmt->fetch();
if (!$meta) {
    echo '<div class="alert alert-danger">Keine Jobs gefunden.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Einzelne Jobs
$ids = explode(',', $meta['grouped_ids']);
// Named-Placeholders und ID-Parameter (ohne 'u')
$inNames = [];
$idParams = [];
foreach ($ids as $i => $id) {
    $ph = "job{$i}";
    $inNames[] = ':' . $ph;
    $idParams[$ph] = $id;
}
$inList = implode(',', $inNames);
// Einzelne Jobs (nur ID-Parameter)
$stmt2 = db_query("SELECT * FROM jobs WHERE id IN ($inList) ORDER BY id", $idParams);
$jobs = $stmt2->fetchAll();

// Pinned-Status Gruppe
$p = db_query('SELECT id FROM user_pins WHERE user_id = :u AND target_type = "job_group" AND target_key = :k', ['u'=>$_SESSION['user_id'], 'k'=>$groupKey]);
$pinnedGroup = (bool) $p->fetch();

// Pinned-Status Jobs mit user_id und ID-Parametern
$paramsPinned = array_merge(['u'=>$_SESSION['user_id']], $idParams);
$pj = db_query(
    "SELECT target_key FROM user_pins WHERE user_id = :u AND target_type = 'job' AND target_key IN ($inList)",
    $paramsPinned
);
$pinnedJobs = array_column($pj->fetchAll(), 'target_key');

// Notizen für Gruppe laden
$ng = db_query('SELECT note, created_at FROM job_notes WHERE user_id = :u AND target_type = "job_group" AND target_key = :k ORDER BY created_at DESC', 
               ['u'=>$_SESSION['user_id'], 'k'=>$groupKey]);
$groupNotes = $ng->fetchAll();
?>

<!-- Job-Gruppe Header -->
<div class="card mb-4">
  <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
    <h5 class="mb-0">
      <i class="bi bi-briefcase me-2"></i>
      <?= htmlspecialchars($meta['title']) ?>
    </h5>
    <div>
      <span class="badge bg-light text-dark me-2">
        <i class="bi bi-calendar me-1"></i> <?= htmlspecialchars($meta['post_date']) ?>
      </span>
      <a href="#" id="pinGroupBtn" class="btn btn-sm <?= $pinnedGroup ? 'btn-warning' : 'btn-outline-light' ?>" 
         data-key="<?= htmlspecialchars($groupKey) ?>">
        <i class="bi bi-pin<?= $pinnedGroup ? '-fill' : '' ?> me-1"></i> 
        <?= $pinnedGroup ? 'Gepinnt' : 'Pinnen' ?>
      </a>
    </div>
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-6">
        <dl class="row mb-0">
          <dt class="col-sm-4">Kategorie</dt>
          <dd class="col-sm-8"><?= htmlspecialchars($meta['job_category'] ?? '-') ?></dd>
          
          <dt class="col-sm-4">Ministerium</dt>
          <dd class="col-sm-8"><?= htmlspecialchars($meta['ministry'] ?? '-') ?></dd>
          
          <dt class="col-sm-4">Organisation</dt>
          <dd class="col-sm-8"><?= htmlspecialchars($meta['organization'] ?? '-') ?></dd>
        </dl>
      </div>
      <div class="col-md-6">
        <dl class="row mb-0">
          <dt class="col-sm-4">Status</dt>
          <dd class="col-sm-8">
            <span class="badge bg-secondary"><?= htmlspecialchars($meta['status'] ?? '-') ?></span>
          </dd>
          
          <dt class="col-sm-4">Deadline</dt>
          <dd class="col-sm-8"><?= htmlspecialchars($meta['application_deadline'] ?? '-') ?></dd>
          
          <dt class="col-sm-4">Bildungsniveau</dt>
          <dd class="col-sm-8"><?= htmlspecialchars($meta['education_level'] ?? '-') ?></dd>
        </dl>
      </div>
    </div>
  </div>
  <div class="card-footer bg-light">
    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#descriptionCollapse">
      <i class="bi bi-text-paragraph me-1"></i> Vollständige Beschreibung anzeigen
    </button>
    <div class="collapse mt-3" id="descriptionCollapse">
      <div class="card card-body bg-light">
        <?= nl2br(htmlspecialchars($meta['full_description'] ?? '-')) ?>
      </div>
    </div>
  </div>
</div>

<!-- Tab Navigation für Notizen und Job-Versionen -->
<ul class="nav nav-tabs mb-4" id="jobTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="versions-tab" data-bs-toggle="tab" data-bs-target="#versions" type="button" role="tab">
      <i class="bi bi-layers me-1"></i> Job-Versionen (<?= count($jobs) ?>)
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes" type="button" role="tab">
      <i class="bi bi-journal-text me-1"></i> Notizen (<?= count($groupNotes) ?>)
    </button>
  </li>
</ul>

<!-- Tab-Inhalte -->
<div class="tab-content">
  <!-- Job-Versionen Tab -->
  <div class="tab-pane fade show active" id="versions" role="tabpanel" aria-labelledby="versions-tab">
    <div class="accordion" id="jobVersionsAccordion">
      <?php foreach ($jobs as $index => $j): ?>
        <div class="accordion-item">
          <h2 class="accordion-header" id="heading<?= $j['id'] ?>">
            <button class="accordion-button <?= $index > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" 
                   data-bs-target="#collapse<?= $j['id'] ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>">
              <div class="d-flex justify-content-between align-items-center w-100">
                <div>
                  <strong><?= htmlspecialchars($j['title']) ?></strong>
                  <?php if (in_array($j['id'], $pinnedJobs)): ?>
                    <i class="bi bi-pin-fill text-warning ms-2"></i>
                  <?php endif ?>
                </div>
                <div>
                  <small class="text-muted"><?= htmlspecialchars($j['created_at']) ?></small>
                </div>
              </div>
            </button>
          </h2>
          <div id="collapse<?= $j['id'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" 
               data-bs-parent="#jobVersionsAccordion">
            <div class="accordion-body">
              <div class="d-flex justify-content-end mb-3">
                <button class="btn btn-sm btn-outline-primary me-2 toggle-job-pin" data-id="<?= $j['id'] ?>">
                  <i class="bi bi-pin<?= in_array($j['id'], $pinnedJobs) ? '-fill' : '' ?>"></i>
                  <?= in_array($j['id'], $pinnedJobs) ? 'Entpinnen' : 'Pinnen' ?>
                </button>
                <button class="btn btn-sm btn-outline-secondary add-job-note" data-id="<?= $j['id'] ?>">
                  <i class="bi bi-chat-text"></i> Notiz
                </button>
              </div>
              
              <div class="row">
                <div class="col-md-6">
                  <h6 class="fw-bold">Details</h6>
                  <dl class="row">
                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($j['status'] ?? '-') ?></dd>
                    <dt class="col-sm-4">Link</dt>
                    <dd class="col-sm-8">
                      <?php if (!empty($j['link'])): ?>
                        <a href="<?= htmlspecialchars($j['link']) ?>" target="_blank">Öffnen <i class="bi bi-box-arrow-up-right"></i></a>
                      <?php else: ?>
                        -
                      <?php endif; ?>
                    </dd>
                  </dl>
                </div>
                <div class="col-md-6">
                  <h6 class="fw-bold">Weitere Informationen</h6>
                  <dl class="row">
                    <dt class="col-sm-4">Erstellt</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($j['created_at'] ?? '-') ?></dd>
                    <dt class="col-sm-4">Aktualisiert</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($j['updated_at'] ?? '-') ?></dd>
                  </dl>
                </div>
              </div>
              
              <?php if (!empty($j['missions'])): ?>
              <h6 class="fw-bold mt-3">Aufgaben</h6>
              <div class="p-3 bg-light rounded">
                <?= nl2br(htmlspecialchars($j['missions'])) ?>
              </div>
              <?php endif; ?>
              
              <?php if (!empty($j['profile'])): ?>
              <h6 class="fw-bold mt-3">Profil</h6>
              <div class="p-3 bg-light rounded">
                <?= nl2br(htmlspecialchars($j['profile'])) ?>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  
  <!-- Notizen Tab -->
  <div class="tab-pane fade" id="notes" role="tabpanel" aria-labelledby="notes-tab">
    <div class="card">
      <div class="card-body">
        <form id="groupNoteForm" class="mb-4">
          <div class="mb-3">
            <label for="groupNote" class="form-label">Neue Notiz zur Job-Gruppe</label>
            <textarea id="groupNote" class="form-control" rows="3" placeholder="Notiz eingeben..."></textarea>
          </div>
          <input type="hidden" id="groupKeyInput" value="<?= htmlspecialchars($groupKey) ?>">
          <div class="d-flex justify-content-between">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-save me-1"></i> Notiz speichern
            </button>
            <button type="button" id="clearForm" class="btn btn-outline-secondary">
              <i class="bi bi-x-lg me-1"></i> Zurücksetzen
            </button>
          </div>
        </form>
        
        <hr>
        
        <h6 class="mb-3">Notizenverlauf</h6>
        <?php if (count($groupNotes) > 0): ?>
          <div class="note-history">
            <?php foreach ($groupNotes as $note): ?>
              <div class="note-card mb-3">
                <div class="note-header d-flex justify-content-between">
                  <span class="text-muted small"><?= htmlspecialchars($note['created_at']) ?></span>
                </div>
                <div class="note-body p-3 bg-light rounded mt-1">
                  <?= nl2br(htmlspecialchars($note['note'])) ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="text-muted">Keine Notizen vorhanden.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Zurück-Button -->
<div class="mt-4">
  <a href="javascript:history.back()" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-2"></i> Zurück zu den Ergebnissen
  </a>
</div>

<!-- Notizen-Modal für Jobs -->
<div class="modal fade" id="jobNoteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-chat-text me-2"></i> Job-Notiz</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="jobNoteHistory" class="mb-3 p-3 bg-light rounded" style="max-height: 200px; overflow-y: auto;"></div>
        <form id="jobNoteForm">
          <input type="hidden" id="jobIdInput">
          <div class="mb-3">
            <label for="jobNote" class="form-label">Neue Notiz</label>
            <textarea id="jobNote" class="form-control" rows="3" placeholder="Notiz eingeben..."></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
        <button type="button" id="saveJobNote" class="btn btn-primary">
          <i class="bi bi-save me-1"></i> Speichern
        </button>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php';?>

<!-- Page specific script -->
<script>
$(document).ready(function() {
  // Job-Gruppe Pin Toggle
  $('#pinGroupBtn').click(function(e) {
    e.preventDefault();
    var btn = $(this);
    var key = btn.data('key');
    
    $.post('/pins/toggle.php', { type: 'job_group', key: key }, function() {
      btn.toggleClass('btn-warning btn-outline-light');
      var icon = btn.find('i');
      icon.toggleClass('bi-pin bi-pin-fill');
      if (btn.hasClass('btn-warning')) {
        btn.html('<i class="bi bi-pin-fill me-1"></i> Gepinnt');
      } else {
        btn.html('<i class="bi bi-pin me-1"></i> Pinnen');
      }
    });
  });
  
  // Job-Pin Toggle
  $('.toggle-job-pin').click(function() {
    var btn = $(this);
    var jobId = btn.data('id');
    
    $.post('/pins/toggle.php', { type: 'job', key: jobId }, function() {
      var icon = btn.find('i');
      icon.toggleClass('bi-pin bi-pin-fill');
      
      if (icon.hasClass('bi-pin-fill')) {
        btn.html('<i class="bi bi-pin-fill"></i> Entpinnen');
        // Pin-Icon im Accordion-Header hinzufügen
        var header = $(`#heading${jobId} .accordion-button`);
        if (header.find('.bi-pin-fill').length === 0) {
          header.find('strong').after('<i class="bi bi-pin-fill text-warning ms-2"></i>');
        }
      } else {
        btn.html('<i class="bi bi-pin"></i> Pinnen');
        // Pin-Icon aus dem Accordion-Header entfernen
        $(`#heading${jobId} .bi-pin-fill`).remove();
      }
    });
  });
  
  // Job-Notiz-Modal öffnen
  $('.add-job-note').click(function() {
    var jobId = $(this).data('id');
    $('#jobIdInput').val(jobId);
    $('#jobNoteModal').modal('show');
    
    // Notizhistorie laden
    loadJobNotes(jobId);
  });
  
  function loadJobNotes(jobId) {
    $('#jobNoteHistory').html('<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"></div></div>');
    $.get('/notes/list.php', { type: 'job', key: jobId }, function(html) {
      $('#jobNoteHistory').html(html || '<p class="text-muted">Keine Notizen vorhanden.</p>');
    });
  }
  
  // Job-Notiz speichern
  $('#saveJobNote').click(function() {
    var jobId = $('#jobIdInput').val();
    var note = $('#jobNote').val().trim();
    
    if (!note) return;
    
    var btn = $(this);
    var originalHtml = btn.html();
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Speichern...');
    
    $.post('/notes/save.php', { type: 'job', key: jobId, note: note }, function() {
      $('#jobNote').val('');
      loadJobNotes(jobId);
      btn.prop('disabled', false).html(originalHtml);
    });
  });
  
  // Gruppen-Notiz speichern
  $('#groupNoteForm').submit(function(e) {
    e.preventDefault();
    var note = $('#groupNote').val().trim();
    var key = $('#groupKeyInput').val();
    
    if (!note) return;
    
    $.post('/notes/save.php', { type: 'job_group', key: key, note: note }, function() {
      $('#groupNote').val('');
      // Seite neu laden, um neue Notiz zu sehen
      location.reload();
    });
  });
  
  // Notizformular zurücksetzen
  $('#clearForm').click(function() {
    $('#groupNote').val('');
  });
});
</script>