<?php
require_once __DIR__ . '/../includes/header.php';

// Alle Filter des aktuellen Users
$stmt = db_query('SELECT fs.*, (SELECT COUNT(*) FROM filter_keywords fk WHERE fk.filter_id = fs.id) AS kw_count FROM filter_sets fs WHERE user_id = :u', ['u'=>$_SESSION['user_id']]);
$filters = $stmt->fetchAll();
?>
<h2>Filter verwalten</h2>
<a href="edit.php">Neuen Filter anlegen</a>
<table>
    <thead><tr><th>Name</th><th>Von</th><th>Bis</th><th>Modus</th><th>Keywords</th><th>Aktionen</th></tr></thead>
    <tbody>
    <?php foreach ($filters as $f): ?>
        <tr>
            <td><?= htmlspecialchars($f['name']) ?></td>
            <td><?= htmlspecialchars($f['date_from'] ?? '-') ?></td>
            <td><?= htmlspecialchars($f['date_to'] ?? '-') ?></td>
            <td><?= htmlspecialchars($f['mode']) ?></td>
            <td><?= $f['kw_count'] ?></td>
            <td>
                <a href="edit.php?id=<?= $f['id'] ?>">Bearbeiten</a> |
                <a href="delete.php?id=<?= $f['id'] ?>" onclick="return confirm('Filter wirklich löschen?');">Löschen</a>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php require_once __DIR__ . '/../includes/footer.php';?>