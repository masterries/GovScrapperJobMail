<?php
require_once __DIR__ . '/../includes/header.php';

$jobId = $_GET['job_id'] ?? '';
if (!$jobId) {
    echo '<div class="alert alert-danger">Ungültige Job-ID.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Job-Details laden
$stmt = db_query('SELECT * FROM jobs WHERE id = :id', ['id' => $jobId]);
$job = $stmt->fetch();
if (!$job) {
    echo '<div class="alert alert-danger">Job nicht gefunden.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Pinned-Status
$p = db_query('SELECT id FROM user_pins WHERE user_id = :u AND target_type = "job" AND target_key = :k', 
             ['u'=>$_SESSION['user_id'], 'k'=>$jobId]);
$pinned = (bool) $p->fetch();

// Notizen für Job laden
$notes = db_query('SELECT note, created_at FROM job_notes WHERE user_id = :u AND target_type = "job" AND target_key = :k ORDER BY created_at DESC', 
               ['u'=>$_SESSION['user_id'], 'k'=>$jobId]);
$jobNotes = $notes->fetchAll();
?>

<!-- Job Header -->
<div class="card mb-4">
  <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
    <h5 class="mb-0">
      <i class="bi bi-briefcase me-2"></i>
      <?= htmlspecialchars($job['title']) ?>
    </h5>
    <div>
      <span class="badge bg-light text-dark me-2">
        <i class="bi bi-calendar me-1"></i> <?= htmlspecialchars($job['created_at']) ?>
      </span>
      <a href="#" id="pinJobBtn" class="btn btn-sm <?= $pinned ? 'btn-warning' : 'btn-outline-light' ?>" 
         data-id="<?= htmlspecialchars($jobId) ?>">
        <i class="bi bi-pin<?= $pinned ? '-fill' : '' ?> me-1"></i> 
        <?= $pinned ? 'Gepinnt' : 'Pinnen' ?>
      </a>
    </div>
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-6">
        <dl class="row mb-0">
          <dt class="col-sm-4">Kategorie</dt>
          <dd class="col-sm-8"><?= htmlspecialchars($job['job_category'] ?? '-') ?></dd>
          
          <dt class="col-sm-4">Ministerium</dt>
          <dd class="col-sm-8"><?= htmlspecialchars($job['ministry'] ?? '-') ?></dd>
          
          <dt class="col-sm-4">Organisation</dt>
          <dd class="col-sm-8"><?= htmlspecialchars($job['organization'] ?? '-') ?></dd>
        </dl>
      </div>
      <div class="col-md-6">
        <dl class="row mb-0">
          <dt class="col-sm-4">Status</dt>
          <dd class="col-sm-8">
            <span class="badge bg-secondary"><?= htmlspecialchars($job['status'] ?? '-') ?></span>
          </dd>
          
          <dt class="col-sm-4">Erstellt am</dt>
          <dd class="col-sm-8"><?= htmlspecialchars($job['created_at'] ?? '-') ?></dd>
          
          <dt class="col-sm-4">Aktualisiert</dt>
          <dd class="col-sm-8"><?= htmlspecialchars($job['updated_at'] ?? '-') ?></dd>
        </dl>
      </div>
    </div>
  </div>
</div>

<!-- Tab Navigation für Details und Notizen -->
<ul class="nav nav-tabs mb-4" id="jobTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab">
      <i class="bi bi-info-circle me-1"></i> Details
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes" type="button" role="tab">
      <i class="bi bi-journal-text me-1"></i> Notizen (<?= count($jobNotes) ?>)
    </button>
  </li>
</ul>

<!-- Tab-Inhalte -->
<div class="tab-content">
  <!-- Details Tab -->
  <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
    <div class="card">
      <div class="card-body">
        <?php if (!empty($job['link'])): ?>
        <div class="mb-4">
          <h6 class="fw-bold">Link</h6>
          <a href="<?= htmlspecialchars($job['link']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-box-arrow-up-right me-1"></i> Job-Anzeige öffnen
          </a>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($job['missions'])): ?>
        <div class="mb-4">
          <h6 class="fw-bold">Aufgaben</h6>
          <div class="p-3 bg-light rounded">
            <?= nl2br(htmlspecialchars($job['missions'])) ?>
          </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($job['profile'])): ?>
        <div class="mb-4">
          <h6 class="fw-bold">Profil</h6>
          <div class="p-3 bg-light rounded">
            <?= nl2br(htmlspecialchars($job['profile'])) ?>
          </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($job['full_description'])): ?>
        <div>
          <h6 class="fw-bold">Vollständige Beschreibung</h6>
          <div class="p-3 bg-light rounded">
            <?= nl2br(htmlspecialchars($job['full_description'])) ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <!-- Notizen Tab -->
  <div class="tab-pane fade" id="notes" role="tabpanel" aria-labelledby="notes-tab">
    <div class="card">
      <div class="card-body">
        <form id="jobNoteForm" class="mb-4">
          <div class="mb-3">
            <label for="jobNote" class="form-label">Neue Notiz zum Job</label>
            <textarea id="jobNote" class="form-control" rows="3" placeholder="Notiz eingeben..."></textarea>
          </div>
          <input type="hidden" id="jobIdInput" value="<?= htmlspecialchars($jobId) ?>">
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
        <?php if (count($jobNotes) > 0): ?>
          <div class="note-history">
            <?php foreach ($jobNotes as $note): ?>
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

<?php require_once __DIR__ . '/../includes/footer.php';?>

<!-- Page specific script -->
<script>
$(document).ready(function() {
  // Job Pin Toggle
  $('#pinJobBtn').click(function(e) {
    e.preventDefault();
    var btn = $(this);
    var id = btn.data('id');
    
    $.post('/pins/toggle.php', { type: 'job', key: id }, function() {
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
  
  // Notizen speichern
  $('#jobNoteForm').submit(function(e) {
    e.preventDefault();
    var note = $('#jobNote').val().trim();
    var key = $('#jobIdInput').val();
    
    if (!note) return;
    
    $.post('/notes/save.php', { type: 'job', key: key, note: note }, function() {
      $('#jobNote').val('');
      // Seite neu laden, um neue Notiz zu sehen
      location.reload();
    });
  });
  
  // Notizformular zurücksetzen
  $('#clearForm').click(function() {
    $('#jobNote').val('');
  });
});
</script>