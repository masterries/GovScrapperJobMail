<?php
require_once __DIR__ . '/../includes/header.php';

$type = $_POST['type'] ?? '';
$key  = $_POST['key'] ?? '';
$note = trim($_POST['note'] ?? '');
if ($type && $key && $note !== '') {
    // Einfache Insert, um alle Versionshistorien zu speichern
    db_query(
        'INSERT INTO job_notes (user_id, target_type, target_key, note, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())',
        [
            $_SESSION['user_id'],
            $type,
            $key,
            $note
        ]
    );
}

$referer = $_SERVER['HTTP_REFERER'] ?? '/';
header('Location: ' . $referer);
exit;