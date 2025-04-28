<?php
session_start();
require_once __DIR__ . '/../database/db_connect.php';

$currentPage = basename($_SERVER['PHP_SELF']);
if (!in_array($currentPage, ['login.php', 'register.php'])) {
    if (empty($_SESSION['user_id'])) {
        header('Location: /auth/login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JobSearch Portal</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="/search/index.php">
      <i class="bi bi-briefcase-fill me-2"></i>
      JobSearch
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="/search/index.php">
            <i class="bi bi-search me-1"></i> Suche
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $currentPage === 'recommendations.php' ? 'active' : '' ?>" href="/search/recommendations.php">
            <i class="bi bi-lightbulb me-1"></i> Empfehlungen
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $currentPage === 'list.php' && strpos($_SERVER['PHP_SELF'], '/filters/') !== false ? 'active' : '' ?>" href="/filters/list.php">
            <i class="bi bi-funnel me-1"></i> Filter
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $currentPage === 'list.php' && strpos($_SERVER['PHP_SELF'], '/pins/') !== false ? 'active' : '' ?>" href="/pins/list.php">
            <i class="bi bi-pin-angle me-1"></i> Gepinnt
          </a>
        </li>
      </ul>
      <div class="d-flex">
        <?php if (!empty($_SESSION['user_id'])): ?>
          <div class="dropdown">
            <button class="btn btn-outline-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
              <i class="bi bi-person-circle me-1"></i>
              <?php 
                $stmt = db_query("SELECT username FROM users WHERE id = ?", [$_SESSION['user_id']]);
                $user = $stmt->fetch();
                echo htmlspecialchars($user['username'] ?? 'Benutzer');
              ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Abmelden</a></li>
            </ul>
          </div>
        <?php else: ?>
          <a href="/auth/login.php" class="btn btn-outline-light">Anmelden</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
<main class="container my-4"><?php if ($currentPage !== 'login.php' && $currentPage !== 'register.php'): ?>
  <div class="mb-4">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/search/index.php">Home</a></li>
        <?php
          if ($currentPage === 'index.php' && strpos($_SERVER['PHP_SELF'], '/search/') !== false):
            echo '<li class="breadcrumb-item active">Suche</li>';
          elseif ($currentPage === 'results.php'):
            echo '<li class="breadcrumb-item"><a href="/search/index.php">Suche</a></li>';
            echo '<li class="breadcrumb-item active">Ergebnisse</li>';
          elseif ($currentPage === 'recommendations.php'):
            echo '<li class="breadcrumb-item active">Empfehlungen</li>';
          elseif ($currentPage === 'job_view.php'):
            echo '<li class="breadcrumb-item"><a href="/search/index.php">Suche</a></li>';
            echo '<li class="breadcrumb-item active">Job Details</li>';
          elseif (strpos($_SERVER['PHP_SELF'], '/filters/') !== false):
            echo '<li class="breadcrumb-item active">Filter</li>';
          elseif (strpos($_SERVER['PHP_SELF'], '/pins/') !== false):
            echo '<li class="breadcrumb-item active">Gepinnte Jobs</li>';
          endif;
        ?>
      </ol>
    </nav>
  </div>
<?php endif; ?>