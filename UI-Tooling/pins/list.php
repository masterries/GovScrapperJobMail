<?php
require_once __DIR__ . '/../includes/header.php';

// Gepinnte Gruppen
$stmt = db_query('SELECT up.target_key, uj.title, uj.post_date FROM user_pins up JOIN unique_jobs uj ON up.target_key = uj.group_key WHERE up.user_id = :u AND up.target_type = "job_group" ORDER BY up.pinned_at DESC', ['u'=>$_SESSION['user_id']]);
$groups = $stmt->fetchAll();

// Gepinnte einzelne Jobs
$stmt2 = db_query('SELECT up.target_key, j.title, j.created_at FROM user_pins up JOIN jobs j ON up.target_key = j.id WHERE up.user_id = :u AND up.target_type = "job" ORDER BY up.pinned_at DESC', ['u'=>$_SESSION['user_id']]);
$jobs = $stmt2->fetchAll();
?>
<h2>Angepinnte Job-Gruppen</h2>
<?php if ($groups): ?>
<ul>
    <?php foreach ($groups as $g): ?>
    <li><a href="../search/job_view.php?group_key=<?= urlencode($g['target_key']) ?>"><?= htmlspecialchars($g['title']) ?></a> (<?= htmlspecialchars($g['post_date']) ?>)</li>
    <?php endforeach; ?>
</ul>
<?php else: ?>
<p>Keine gepinnten Gruppen.</p>
<?php endif; ?>

<h2>Angepinnte Einzel-Jobs</h2>
<?php if ($jobs): ?>
<ul>
    <?php foreach ($jobs as $j): ?>
    <li><a href="../search/job_view.php?group_key=<?= urlencode($j['target_key']) ?>"><?= htmlspecialchars($j['title']) ?></a> (<?= htmlspecialchars($j['created_at']) ?>)</li>
    <?php endforeach; ?>
</ul>
<?php else: ?>
<p>Keine gepinnten Einzel-Jobs.</p>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php';?>