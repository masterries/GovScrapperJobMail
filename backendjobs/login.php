<?php
// Configuration flag to disable registration
$registration_disabled = true; // Setze auf false, um die Registrierung zu aktivieren

session_start();
require_once './db_connect.php'; // Include your database connection

// Guest login functionality
if (isset($_GET['guest'])) {
    // Create a guest session
    $_SESSION['user_id'] = 0; // Special ID for guests
    $_SESSION['username'] = 'Gast';
    $_SESSION['is_guest'] = true;
    header("Location: dashboard.php");
    exit;
}

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Simple validation
    if (empty($username)) {
        $errors['username'] = 'Benutzername ist erforderlich';
    }
    if (empty($password)) {
        $errors['password'] = 'Passwort ist erforderlich';
    }

    if (empty($errors)) {
        // Retrieve the user from the database using the username
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify password using password_verify
        if ($user && password_verify($password, $user['password_hash'])) {
            // Credentials are valid, start a session and redirect to dashboard
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: dashboard.php");
            exit;
        } else {
            $errors['login'] = 'Ungültiger Benutzername oder Passwort';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="mobile-styles.css">
    <style>
        .guest-access {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Login</div>
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
                            <label for="password" class="form-label">Passwort</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Login</button>
                    </form>
                    
                    <?php if (!$registration_disabled): ?>
                        <p class="mt-3">Noch nicht registriert? <a href="register.php">Hier registrieren</a></p>
                    <?php else: ?>
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle"></i> Die Registrierung ist derzeit deaktiviert.
                        </div>
                    <?php endif; ?>
                    
                    <div class="guest-access text-center">
                        <p>Kein Account? Testen Sie unsere Plattform mit eingeschränktem Zugriff.</p>
                        <a href="login.php?guest=1" class="btn btn-outline-secondary">
                            <i class="bi bi-person"></i> Als Gast fortfahren
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
