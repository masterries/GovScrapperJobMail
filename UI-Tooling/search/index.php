<?php
require_once __DIR__ . '/../includes/header.php';

// verfügbare Filter für aktuellen User laden
$stmt = db_query('SELECT id, name FROM filter_sets WHERE user_id = :u', ['u' => $_SESSION['user_id']]);
$filters = $stmt->fetchAll();
?>

<div class="row justify-content-center">
  <div class="col-md-8">
    <div class="card">
      <div class="card-header bg-primary text-white d-flex align-items-center">
        <i class="bi bi-search me-2"></i>
        <h5 class="mb-0">Jobs durchsuchen</h5>
      </div>
      <div class="card-body">
        <form method="get" action="results.php" class="needs-validation" novalidate>
          <div class="mb-4">
            <label for="q" class="form-label">Suchbegriff</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text" id="q" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" 
                    class="form-control form-control-lg" placeholder="Beruf, Position oder Stichwort eingeben..." required>
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-search me-1"></i> Suchen
              </button>
            </div>
            <div class="form-text">Mehre Suchbegriffe mit Leerzeichen trennen</div>
          </div>
          
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Suchmodus</label>
              <div class="d-flex">
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="mode" id="modeSoft" value="soft" 
                         <?= (($_GET['mode'] ?? 'soft') === 'soft' ? 'checked' : '') ?>>
                  <label class="form-check-label" for="modeSoft">
                    <i class="bi bi-search me-1"></i> Softsearch
                    <small class="d-block text-muted">Suche in Basisdaten</small>
                  </label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="mode" id="modeFull" value="full" 
                         <?= (($_GET['mode'] ?? '') === 'full' ? 'checked' : '') ?>>
                  <label class="form-check-label" for="modeFull">
                    <i class="bi bi-search-heart me-1"></i> Fullsearch
                    <small class="d-block text-muted">Inkl. Beschreibung</small>
                  </label>
                </div>
              </div>
            </div>
            
            <div class="col-md-6">
              <label for="filter_id" class="form-label">Filter anwenden</label>
              <select id="filter_id" name="filter_id" class="form-select">
                <option value="">-- Keinen Filter verwenden --</option>
                <?php foreach ($filters as $f): ?>
                  <option value="<?= $f['id'] ?>" <?= (($_GET['filter_id'] ?? '') == $f['id'] ? 'selected' : '') ?>>
                    <?= htmlspecialchars($f['name']) ?>
                  </option>
                <?php endforeach ?>
              </select>
              <div class="d-flex justify-content-end mt-2">
                <a href="/filters/list.php" class="text-decoration-none">
                  <i class="bi bi-gear me-1"></i> Filter verwalten
                </a>
              </div>
            </div>
          </div>
          
          <!-- Neue Suchtabellenauswahl -->
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Suchtabelle</label>
              <div class="d-flex">
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="search_table" id="tableUnique" value="unique" 
                         <?= (($_GET['search_table'] ?? 'unique') === 'unique' ? 'checked' : '') ?>>
                  <label class="form-check-label" for="tableUnique">
                    <i class="bi bi-grid me-1"></i> Unique Jobs
                    <small class="d-block text-muted">Zusammengefasste Jobs</small>
                  </label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="search_table" id="tableAll" value="all" 
                         <?= (($_GET['search_table'] ?? '') === 'all' ? 'checked' : '') ?>>
                  <label class="form-check-label" for="tableAll">
                    <i class="bi bi-table me-1"></i> Alle Jobs
                    <small class="d-block text-muted">Inklusive Historien</small>
                  </label>
                </div>
              </div>
            </div>
          </div>
          
          <div class="d-grid">
            <button type="submit" class="btn btn-lg btn-primary">
              <i class="bi bi-search me-2"></i> Jobs finden
            </button>
          </div>
        </form>
      </div>
    </div>
    
    <!-- Recent Searches / Stats -->
    <div class="card mt-4">
      <div class="card-header bg-light">
        <i class="bi bi-clock-history me-2"></i>
        Statistiken
      </div>
      <div class="card-body">
        <div class="row text-center">
          <div class="col-4">
            <div class="display-6 text-primary">
              <?php 
                $count = db_query("SELECT COUNT(*) FROM unique_jobs")->fetchColumn();
                echo number_format($count);
              ?>
            </div>
            <div class="small text-muted">Verfügbare Jobs</div>
          </div>
          <div class="col-4">
            <div class="display-6 text-success">
              <?php 
                $pinCount = db_query("SELECT COUNT(*) FROM user_pins WHERE user_id = ?", [$_SESSION['user_id']])->fetchColumn();
                echo number_format($pinCount);
              ?>
            </div>
            <div class="small text-muted">Gepinnte Jobs</div>
          </div>
          <div class="col-4">
            <div class="display-6 text-info">
              <?php 
                $noteCount = db_query("SELECT COUNT(*) FROM job_notes WHERE user_id = ?", [$_SESSION['user_id']])->fetchColumn();
                echo number_format($noteCount);
              ?>
            </div>
            <div class="small text-muted">Notizen</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>