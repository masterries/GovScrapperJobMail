<?php
require_once __DIR__ . '/../database/db_connect.php';
session_start();

$type = $_GET['type'] ?? '';
$key  = $_GET['key'] ?? '';
if (!$type || !$key || empty($_SESSION['user_id'])) {
    http_response_code(400);
    echo 'Ungültige Anfrage';
    exit;
}

// Alle Kommentare abrufen, sortiert nach Erstellungsdatum (aufsteigend)
$stmt = db_query(
    'SELECT note, created_at FROM job_notes WHERE user_id = :u AND target_type = :t AND target_key = :k ORDER BY created_at ASC',
    ['u'=>$_SESSION['user_id'], 't'=>$type, 'k'=>$key]
);
$entries = $stmt->fetchAll();

if ($entries) {
    foreach ($entries as $e) {
        echo '<div class="mb-3">';
        echo '<small>' . htmlspecialchars($e['created_at']) . '</small>';
        echo '<p>' . nl2br(htmlspecialchars($e['note'])) . '</p>';
        echo '</div>';
    }
} else {
    echo '<p>Keine Kommentare vorhanden.</p>';
}
