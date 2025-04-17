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

<div class="auth-container">
  <div class="card auth-card animate__animated animate__fadeIn">
    <div class="text-center mb-4">
      <i class="bi bi-person-plus-fill text-primary" style="font-size: 3rem;"></i>
      <h2 class="card-title">Registrieren</h2>
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
        <label for="username" class="form-label">Benutzername</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($username ?? '') ?>" required>
          <div class="invalid-feedback">
            Bitte gib einen Benutzernamen ein.
          </div>
        </div>
      </div>
      
      <div class="mb-3">
        <label for="email" class="form-label">E-Mail</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required>
          <div class="invalid-feedback">
            Bitte gib eine gültige E-Mail-Adresse ein.
          </div>
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
        <div class="form-text">
          Passwort sollte mindestens 8 Zeichen lang sein.
        </div>
      </div>
      
      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary">Registrieren</button>
      </div>
    </form>
    
    <div class="text-center mt-4">
      <p class="mb-0">Bereits registriert? <a href="login.php" class="text-decoration-none">Anmelden</a></p>
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

// Bootstrap validation
(function() {
  'use strict';
  var forms = document.querySelectorAll('.needs-validation');
  Array.prototype.slice.call(forms).forEach(function(form) {
    form.addEventListener('submit', function(event) {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>