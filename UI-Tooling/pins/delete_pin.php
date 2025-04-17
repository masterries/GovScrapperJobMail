<?php
require_once __DIR__ . '/../includes/header.php';

// Nur POST-Anfragen akzeptieren
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.0 405 Method Not Allowed');
    echo 'Nur POST-Anfragen erlaubt';
    exit;
}

// Prüfe, ob ein Pin-ID angegeben wurde
$pinId = isset($_POST['pin_id']) ? intval($_POST['pin_id']) : 0;
if (!$pinId) {
    header('HTTP/1.0 400 Bad Request');
    echo 'Keine gültige Pin-ID angegeben';
    exit;
}

// Prüfen, ob der Pin dem aktuellen Benutzer gehört und ihn löschen
$stmt = db_query('DELETE FROM user_pins WHERE id = :id AND user_id = :uid', [
    'id' => $pinId,
    'uid' => $_SESSION['user_id']
]);

// Erfolgs-Response
header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>