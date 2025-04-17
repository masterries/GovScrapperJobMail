<?php
require_once __DIR__ . '/../includes/header.php';

$id = intval($_GET['id'] ?? 0);
if ($id) {
    db_query('DELETE FROM filter_sets WHERE id = :id AND user_id = :u', ['id'=>$id, 'u'=>$_SESSION['user_id']]);
}
header('Location: list.php');
exit;