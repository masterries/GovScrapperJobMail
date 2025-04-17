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
<h2><?= $id ? 'Filter bearbeiten' : 'Neuen Filter anlegen' ?></h2>
<?php if ($errors): ?>
<ul class="errors">
 <?php foreach($errors as $e): ?><li><?=htmlspecialchars($e)?></li><?php endforeach?>
</ul>
<?php endif ?>
<form method="post">
    <label>Name:<br><input type="text" name="name" value="<?=htmlspecialchars($filter['name'] ?? '')?>"></label><br>
    <label>Von:<br><input type="date" name="date_from" value="<?=htmlspecialchars($filter['date_from'] ?? '')?>"></label><br>
    <label>Bis:<br><input type="date" name="date_to" value="<?=htmlspecialchars($filter['date_to'] ?? '')?>"></label><br>
    <label>Modus:<br>
        <input type="radio" name="mode" value="soft" <?=((($filter['mode']??'soft')==='soft')?'checked':'')?>> Soft<br>
        <input type="radio" name="mode" value="full" <?=((($filter['mode']??'soft')==='full')?'checked':'')?>> Full
    </label><br>
    <label>Keywords (eine pro Zeile):<br>
        <textarea name="keywords" rows="5"><?=htmlspecialchars($keywords ?? '')?></textarea>
    </label><br>
    <button type="submit">Speichern</button>
    <a href="list.php">Abbrechen</a>
</form>
<?php require_once __DIR__ . '/../includes/footer.php';?>