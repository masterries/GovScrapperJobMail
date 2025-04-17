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

<div class="auth-container">
  <div class="card auth-card animate__animated animate__fadeIn">
    <div class="text-center mb-4">
      <i class="bi bi-briefcase-fill text-primary" style="font-size: 3rem;"></i>
      <h2 class="card-title">Anmelden</h2>
    </div>
    
    <?php if ($errors): ?>
      <div class="alert alert-danger" role="alert">
        <ul class="mb-0 ps-3">
          <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach ?>
        </ul>
      </div>
    <?php endif ?>

    <form method="post" action="" class="needs-validation" novalidate>
      <div class="mb-3">
        <label for="username" class="form-label">Benutzername oder E-Mail</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($username ?? '') ?>" required autofocus>
        </div>
      </div>
      
      <div class="mb-4">
        <label for="password" class="form-label">Passwort</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" class="form-control" id="password" name="password" required>
          <button class="btn btn-outline-secondary" type="button" id="togglePassword">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>
      
      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary">Anmelden</button>
      </div>
    </form>
    
    <div class="text-center mt-4">
      <p class="mb-0">Noch nicht registriert? <a href="register.php" class="text-decoration-none">Registrieren</a></p>
    </div>
  </div>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function() {
  const passwordInput = document.getElementById('password');
  const icon = this.querySelector('i');
  
  if (passwordInput.type === 'password') {
    passwordInput.type = 'text';
    icon.classList.replace('bi-eye', 'bi-eye-slash');
  } else {
    passwordInput.type = 'password';
    icon.classList.replace('bi-eye-slash', 'bi-eye');
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>