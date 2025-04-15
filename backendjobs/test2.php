<?php
/**
 * Eine einfache Hello World PHP-Datei
 */

// HTML-Grundstruktur mit Bootstrap für etwas Styling
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hello World PHP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding-top: 50px;
            background-color: #f5f5f5;
        }
        .hello-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .php-info {
            margin-top: 20px;
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="hello-container">
            <h1 class="text-center mb-4">
                <?php 
                // PHP-Code zur Ausgabe der Nachricht
                echo "Hello, World!"; 
                ?>
            </h1>
            
            <p class="text-center">
                Das ist meine erste PHP-Seite.
            </p>

            <div class="text-center mt-4">
                <p>Aktuelles Datum und Uhrzeit:</p>
                <p><strong>
                    <?php echo date('d.m.Y H:i:s'); ?>
                </strong></p>
            </div>
            
            <div class="php-info">
                <p>PHP-Version: <strong><?php echo phpversion(); ?></strong></p>
                <p>Server-Name: <strong><?php echo $_SERVER['SERVER_NAME'] ?? 'Nicht verfügbar'; ?></strong></p>
                <p>Server-Software: <strong><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Nicht verfügbar'; ?></strong></p>
            </div>
        </div>
    </div>
</body>
</html>