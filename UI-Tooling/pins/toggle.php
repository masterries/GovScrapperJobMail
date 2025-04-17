<?php
require_once __DIR__ . '/../includes/header.php';
$type = $_POST['type'] ?? '';
$key = $_POST['key'] ?? '';
if ($type && $key) {
    // Prüfen ob bereits gepinnt
    $stmt = db_query('SELECT id FROM user_pins WHERE user_id = :u AND target_type = :t AND target_key = :k', ['u'=>$_SESSION['user_id'],'t'=>$type,'k'=>$key]);
    if ($stmt->fetch()) {
        // löschen
        db_query('DELETE FROM user_pins WHERE user_id = :u AND target_type = :t AND target_key = :k', ['u'=>$_SESSION['user_id'],'t'=>$type,'k'=>$key]);
    } else {
        // anlegen
        db_query('INSERT INTO user_pins (user_id,target_type,target_key,pinned_at) VALUES (:u,:t,:k,NOW())', ['u'=>$_SESSION['user_id'],'t'=>$type,'k'=>$key]);
    }
}
// zurück zur vorherigen Seite
$referer = $_SERVER['HTTP_REFERER'] ?? '/UI-Tooling/index.php';
header('Location: ' . $referer);
exit;