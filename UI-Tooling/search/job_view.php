<?php
require_once __DIR__ . '/../includes/header.php';

$groupKey = $_GET['group_key'] ?? '';
if (!$groupKey) {
    echo '<p>Ungültiger Job-Group-Key.</p>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Meta der Gruppe
$stmt = db_query('SELECT * FROM unique_jobs WHERE group_key = :gk', ['gk' => $groupKey]);
$meta = $stmt->fetch();
if (!$meta) {
    echo '<p>Keine Jobs gefunden.</p>';
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

// Notiz Gruppe
$ng = db_query('SELECT note FROM job_notes WHERE user_id = :u AND target_type = "job_group" AND target_key = :k', ['u'=>$_SESSION['user_id'], 'k'=>$groupKey]);
$noteGroup = $ng->fetchColumn();
?>
<h2><?= htmlspecialchars($meta['title']) ?></h2>
<p>Erstellt am <?= htmlspecialchars($meta['post_date']) ?></p>
<form method="post" action="/pins/toggle.php">
    <input type="hidden" name="type" value="job_group">
    <input type="hidden" name="key" value="<?= htmlspecialchars($groupKey) ?>">
    <button type="submit"><?= $pinnedGroup ? 'Unpin' : 'Pin' ?></button>
</form>
<form method="post" action="/notes/save.php">
    <input type="hidden" name="type" value="job_group">
    <input type="hidden" name="key" value="<?= htmlspecialchars($groupKey) ?>">
    <textarea name="note" rows="3"><?= htmlspecialchars($noteGroup) ?></textarea><br>
    <button type="submit">Notiz speichern</button>
</form>

<h3>Einzel-Jobs</h3>
<ul>
<?php foreach ($jobs as $j): ?>
    <li>
        <?= htmlspecialchars($j['title']) ?> (<?= htmlspecialchars($j['created_at']) ?>)
        <form method="post" action="/pins/toggle.php" style="display:inline">
            <input type="hidden" name="type" value="job">
            <input type="hidden" name="key" value="<?= $j['id'] ?>">
            <button type="submit"><?= in_array($j['id'], $pinnedJobs) ? 'Unpin' : 'Pin' ?></button>
        </form>
        <form method="post" action="/notes/save.php" style="display:inline">
            <input type="hidden" name="type" value="job">
            <input type="hidden" name="key" value="<?= $j['id'] ?>">
            <button type="button" onclick="this.nextElementSibling.style.display='block'">Notiz</button>
            <textarea name="note" style="display:none" rows="2"><?= htmlspecialchars(
                (db_query('SELECT note FROM job_notes WHERE user_id = :u AND target_type = "job" AND target_key = :k', ['u'=>$_SESSION['user_id'], 'k'=>$j['id']])->fetchColumn())
            ) ?></textarea>
            <button type="submit" style="display:none">Speichern</button>
        </form>
    </li>
<?php endforeach; ?>
</ul>
<?php require_once __DIR__ . '/../includes/footer.php';?>