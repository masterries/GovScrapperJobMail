<?php
/**
 * Datenbankverbindung mit Umgebungsvariablen
 * 
 * Diese Datei stellt eine Verbindung zur Datenbank her und nutzt
 * Umgebungsvariablen für die Zugriffsdaten, um die Sicherheit zu erhöhen.
 */

// Datenbank-Konfiguration aus Umgebungsvariablen lesen
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'job_tracker';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASSWORD') ?: '';

// Optionale Konfiguration für UTF-8 und Zeitzone
$charset = 'utf8mb4';
date_default_timezone_set('Europe/Berlin'); // Zeitzone anpassen

// Verbindung mit PDO herstellen
try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    
    // Optional: Falls die Verbindung erfolgreich ist, eine Konstante definieren
    define('DB_CONNECTION_SUCCESSFUL', true);
    
} catch (PDOException $e) {
    // Fehlermeldung im Entwicklungsmodus anzeigen
    if (getenv('APP_ENV') === 'development') {
        die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
    } else {
        // Im Produktionsmodus keine detaillierten Fehlermeldungen anzeigen
        error_log("Datenbankfehler: " . $e->getMessage());
        die("Ein Datenbankfehler ist aufgetreten. Bitte versuche es später noch einmal.");
    }
}

/**
 * Optional: Helfer-Funktion für Datenbankabfragen
 * Diese Funktion kann in anderen Dateien verwendet werden, um einfach auf die Datenbank zuzugreifen
 */
function db_query($sql, $params = []) {
    global $pdo;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt;
}