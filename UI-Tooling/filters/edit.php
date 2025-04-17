<?php
require_once __DIR__ . '/../includes/header.php';

$id = intval($_GET['id'] ?? 0);
$errors = [];
// Lade existierenden Datensatz
if ($id) {
    $filter = db_query('SELECT * FROM filter_sets WHERE id = :id AND user_id = :u', ['id'=>$id,'u'=>$_SESSION['user_id']])->fetch();
    if (!$filter) { die('Filter nicht gefunden'); }
    $kw = db_query('SELECT keyword FROM filter_keywords WHERE filter_id = :f', ['f'=>$id])->fetchAll(PDO::FETCH_COLUMN);
    $keywords = implode("\n", $kw);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $date_from = $_POST['date_from'] ?: null;
    $date_to   = $_POST['date_to'] ?: null;
    $mode      = ($_POST['mode'] ?? 'soft') === 'full' ? 'full' : 'soft';
    $keys_text = trim($_POST['keywords'] ?? '');

    if (!$name) { $errors[] = 'Name ist erforderlich.'; }
    if (!$errors) {
        if ($id) {
            db_query('UPDATE filter_sets SET name=:n, date_from=:df, date_to=:dt, mode=:m WHERE id=:id', ['n'=>$name,'df'=>$date_from,'dt'=>$date_to,'m'=>$mode,'id'=>$id]);
        } else {
            db_query('INSERT INTO filter_sets (user_id,name,date_from,date_to,mode) VALUES (:u,:n,:df,:dt,:m)', ['u'=>$_SESSION['user_id'],'n'=>$name,'df'=>$date_from,'dt'=>$date_to,'m'=>$mode]);
            $id = db_query('SELECT LAST_INSERT_ID()')->fetchColumn();
        }
        // Keywords neu setzen
        db_query('DELETE FROM filter_keywords WHERE filter_id = :f', ['f'=>$id]);
        if ($keys_text) {
            $lines = preg_split('/\r?\n/', $keys_text);
            foreach ($lines as $l) {
                $kwd = trim($l);
                if ($kwd) {
                    db_query('INSERT INTO filter_keywords (filter_id,keyword) VALUES (:f,:k)', ['f'=>$id,'k'=>$kwd]);
                }
            }
        }
        header('Location: list.php'); exit;
    }
}
?>

<div class="row mb-4">
    <div class="col">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="h3">
                <i class="bi bi-<?= $id ? 'pencil-square' : 'plus-circle' ?> me-2"></i>
                <?= $id ? 'Filter bearbeiten' : 'Neuen Filter anlegen' ?>
            </h1>
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Zurück zur Liste
            </a>
        </div>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger" role="alert">
    <h4 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Fehler!</h4>
    <ul class="mb-0">
        <?php foreach($errors as $e): ?>
            <li><?=htmlspecialchars($e)?></li>
        <?php endforeach?>
    </ul>
</div>
<?php endif ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-sliders me-2"></i>Filter-Details
        </h5>
    </div>
    <div class="card-body">
        <form method="post">
            <div class="row mb-3">
                <div class="col-lg-6">
                    <label for="name" class="form-label">Filter-Name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-tag"></i></span>
                        <input type="text" class="form-control" id="name" name="name" value="<?=htmlspecialchars($filter['name'] ?? '')?>" required>
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6 col-lg-3 mb-3 mb-md-0">
                    <label for="date_from" class="form-label">Von Datum</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?=htmlspecialchars($filter['date_from'] ?? '')?>">
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <label for="date_to" class="form-label">Bis Datum</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?=htmlspecialchars($filter['date_to'] ?? '')?>">
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-lg-6">
                    <label class="form-label d-block">Filtermodus</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="mode" id="modeSoft" value="soft" <?=((($filter['mode']??'soft')==='soft')?'checked':'')?>>
                        <label class="form-check-label" for="modeSoft">
                            <span class="badge bg-info">Soft</span>
                            <small class="text-muted ms-1">Mindestens ein Suchbegriff muss enthalten sein</small>
                        </label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="mode" id="modeFull" value="full" <?=((($filter['mode']??'soft')==='full')?'checked':'')?>>
                        <label class="form-check-label" for="modeFull">
                            <span class="badge bg-primary">Full</span>
                            <small class="text-muted ms-1">Alle Suchbegriffe müssen enthalten sein</small>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-lg-8">
                    <label for="keywords" class="form-label">Keywords (einen pro Zeile)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <textarea class="form-control" id="keywords" name="keywords" rows="7" placeholder="Geben Sie hier Ihre Suchbegriffe ein, je einen pro Zeile..."><?=htmlspecialchars($keywords ?? '')?></textarea>
                    </div>
                    <div class="form-text">Diese Keywords werden für die Filterung der Jobs verwendet.</div>
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i>Speichern
                </button>
                <a href="list.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle me-2"></i>Abbrechen
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php';?>