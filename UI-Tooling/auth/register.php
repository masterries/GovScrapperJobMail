<?php
require_once __DIR__ . '/../includes/header.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$email || !$password) {
        $errors[] = 'Alle Felder sind erforderlich.';
    } else {
        // Prüfen, ob Benutzer oder E-Mail existiert
        $stmt = db_query('SELECT id FROM users WHERE username = :u OR email = :e', ['u' => $username, 'e' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Benutzername oder E-Mail ist bereits vergeben.';
        } else {
            // Einfügen
            $hash = password_hash($password, PASSWORD_DEFAULT);
            db_query('INSERT INTO users (username, email, password_hash, created_at) VALUES (:u, :e, :p, NOW())', [
                'u' => $username,
                'e' => $email,
                'p' => $hash
            ]);
            header('Location: /auth/login.php');
            exit;
        }
    }
}
?>
<h2>Registrieren</h2>
<?php if ($errors): ?>
    <ul class="errors">
    <?php foreach ($errors as $err): ?>
        <li><?= htmlspecialchars($err) ?></li>
    <?php endforeach ?>
    </ul>
<?php endif ?>
<form method="post" action="">
    <label>Benutzername:<br><input type="text" name="username" value="<?= htmlspecialchars($username ?? '') ?>"></label><br>
    <label>E-Mail:<br><input type="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>"></label><br>
    <label>Passwort:<br><input type="password" name="password"></label><br>
    <button type="submit">Registrieren</button>
</form>
<p>Bereits registriert? <a href="login.php">Anmelden</a></p>
<?php require_once __DIR__ . '/../includes/footer.php';?>