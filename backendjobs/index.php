<?php
/**
 * Index.php - Einstiegspunkt der Anwendung
 * 
 * Diese Datei dient als Einstiegspunkt für die Job-Tracker-Anwendung.
 * Sie überprüft, ob ein Benutzer angemeldet ist und leitet entsprechend weiter.
 */

// Session starten, falls noch nicht geschehen
session_start();

// Datenbankverbindung einbinden (optional, wenn nötig)
require_once './db_connect.php';

// Überprüfen, ob der Benutzer angemeldet ist
$is_logged_in = isset($_SESSION['user_id']);

// Weiterleitung abhängig vom Anmeldestatus
if ($is_logged_in) {
    // Benutzer ist angemeldet, zum Dashboard weiterleiten
    header("Location: dashboard.php");
    exit;
} else {
    // Benutzer ist nicht angemeldet, zur Login-Seite weiterleiten
    header("Location: login.php");
    exit;
}

// Falls die Weiterleitungen nicht funktionieren, eine Informationsmeldung anzeigen
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Job Tracker</h3>
                    </div>
                    <div class="card-body">
                        <p>Wenn Sie diese Seite sehen, bedeutet das, dass die automatische Weiterleitung nicht funktioniert hat.</p>
                        <div class="d-grid gap-2">
                            <a href="login.php" class="btn btn-primary">Zum Login</a>
                            <a href="dashboard.php" class="btn btn-secondary">Zum Dashboard (falls Sie bereits angemeldet sind)</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>