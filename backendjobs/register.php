<?php
session_start();
require_once 'db_connect.php'; // Datei mit Datenbankverbindung

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validierung
    if (empty($username)) {
        $errors['username'] = 'Benutzername ist erforderlich';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $errors['username'] = 'Benutzername muss zwischen 3-20 Zeichen lang sein und darf nur Buchstaben, Zahlen und Unterstriche enthalten';
    }

    if (empty($email)) {
        $errors['email'] = 'E-Mail ist erforderlich';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Bitte gib eine gültige E-Mail-Adresse ein';
    }

    if (empty($password)) {
        $errors['password'] = 'Passwort ist erforderlich';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Passwort muss mindestens 6 Zeichen lang sein';
    }

    if ($password !== $password_confirm) {
        $errors['password_confirm'] = 'Passwörter stimmen nicht überein';
    }

    // Prüfen, ob Benutzername oder E-Mail bereits existiert
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->rowCount() > 0) {
            $errors['exists'] = 'Benutzername oder E-Mail ist bereits registriert';
        }
    }

    // Benutzer erstellen, wenn keine Fehler aufgetreten sind
    if (empty($errors)) {
        // Hash des Passworts erstellen
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Verifizierungstoken erstellen
        $verification_token = bin2hex(random_bytes(32));
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, verification_token) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$username, $email, $password_hash, $verification_token])) {
            // E-Mail mit Verifizierungslink senden (siehe separate Funktion)
            sendVerificationEmail($email, $verification_token);
            
            $_SESSION['registration_success'] = true;
            header("Location: login.php");
            exit;
        } else {
            $errors['db'] = 'Registrierung fehlgeschlagen. Bitte versuche es später noch einmal.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrierung</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Registrierung</div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">Benutzername</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">E-Mail</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Passwort</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="text-muted">Mindestens 8 Zeichen</small>
                            </div>
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">Passwort bestätigen</label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Registrieren</button>
                        </form>
                        <p class="mt-3">Bereits registriert? <a href="login.php">Hier anmelden</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>