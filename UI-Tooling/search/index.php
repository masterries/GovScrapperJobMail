<?php
require_once __DIR__ . '/../includes/header.php';

// verfügbare Filter für aktuellen User laden
$stmt = db_query('SELECT id, name FROM filter_sets WHERE user_id = :u', ['u' => $_SESSION['user_id']]);
$filters = $stmt->fetchAll();
?>
<div class="row justify-content-center">
  <div class="col-md-8">
    <h2>Jobs durchsuchen</h2>
    <form method="get" action="results.php" class="needs-validation" novalidate>
      <div class="mb-3">
        <label for="q" class="form-label">Suchbegriff</label>
        <input type="text" id="q" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" class="form-control" required>
      </div>
      <fieldset class="mb-3">
        <legend class="col-form-label">Suchmodus</legend>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="mode" id="modeSoft" value="soft" <?= (($_GET['mode'] ?? 'soft') === 'soft' ? 'checked' : '') ?>>
          <label class="form-check-label" for="modeSoft">Softsearch</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="mode" id="modeFull" value="full" <?= (($_GET['mode'] ?? '') === 'full' ? 'checked' : '') ?>>
          <label class="form-check-label" for="modeFull">Fullsearch</label>
        </div>
      </fieldset>
      <div class="mb-3">
        <label for="filter_id" class="form-label">Filter (optional)</label>
        <select id="filter_id" name="filter_id" class="form-select">
          <option value="">-- keiner --</option>
          <?php foreach ($filters as $f): ?>
            <option value="<?= $f['id'] ?>" <?= (($_GET['filter_id'] ?? '') == $f['id'] ? 'selected' : '') ?>><?= htmlspecialchars($f['name']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Suchen</button>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>