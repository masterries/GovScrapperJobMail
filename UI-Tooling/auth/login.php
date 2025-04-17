<?php
require_once __DIR__ . '/../includes/header.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $errors[] = 'Bitte Benutzername und Passwort eingeben.';
    } else {
        // Benutzer abrufen
        $stmt = db_query('SELECT id, password_hash FROM users WHERE username = :u OR email = :e', ['u' => $username, 'e' => $username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            // Login erfolgreich
            $_SESSION['user_id'] = $user['id'];
            // last_login aktualisieren
            db_query('UPDATE users SET last_login = NOW() WHERE id = :id', ['id' => $user['id']]);
            header('Location: /search/index.php');
            exit;
        } else {
            $errors[] = 'Ungültige Anmeldedaten.';
        }
    }
}
?>
<h2>Anmelden</h2>
<?php if ($errors): ?>
    <ul class="errors">
    <?php foreach ($errors as $err): ?>
        <li><?= htmlspecialchars($err) ?></li>
    <?php endforeach ?>
    </ul>
<?php endif ?>
<form method="post" action="">
    <label>Benutzername oder E-Mail:<br><input type="text" name="username" value="<?= htmlspecialchars($username ?? '') ?>"></label><br>
    <label>Passwort:<br><input type="password" name="password"></label><br>
    <button type="submit">Anmelden</button>
</form>
<p>Noch nicht registriert? <a href="register.php">Registrieren</a></p>
<?php require_once __DIR__ . '/../includes/footer.php';?>