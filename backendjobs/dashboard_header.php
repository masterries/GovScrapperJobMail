<?php
// Common header elements and CSS styles
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0d6efd">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>Job Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="mobile-styles.css">
    <link rel="stylesheet" href="assets/dashboard.css">
</head>
<body>
    <header class="app-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-lg-6 col-md-6">
                    <div class="app-logo">
                        <i class="bi bi-briefcase-fill"></i>
                        Job Dashboard
                    </div>
                    <div class="app-meta">Angemeldet als <?php echo htmlspecialchars($_SESSION['username']); ?></div>
                </div>
                <div class="col-lg-6 col-md-6">
                    <div class="user-section">
                        <form method="POST" class="time-frame-form">
                            <div class="input-group">
                                <input type="number" name="time_frame" class="form-control" value="<?php echo $time_frame; ?>" min="1" max="365" aria-label="Zeitraum in Tagen">
                                <span class="input-group-text">Tage</span>
                            </div>
                            <button type="submit" name="update_timeframe" class="btn btn-light btn-sm ms-md-2">
                                <i class="bi bi-calendar-check"></i> Zeitraum speichern
                            </button>
                        </form>
                        <div class="action-buttons d-flex align-items-center gap-2">
                            <a href="job_statistics.php" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-bar-chart-fill"></i> Statistiken
                            </a>
                            <a href="logout.php" class="btn btn-light btn-sm text-primary">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container-fluid">
