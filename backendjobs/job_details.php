<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once './db_connect.php';

// Check if job ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$job_id = (int)$_GET['id'];

// Get job details
$stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
$stmt->execute([$job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

// If job not found, redirect back to dashboard
if (!$job) {
    header("Location: dashboard.php");
    exit;
}

// Check if job is pinned
$stmt = $pdo->prepare("SELECT id FROM pinned_jobs WHERE user_id = ? AND job_id = ?");
$stmt->execute([$_SESSION['user_id'], $job_id]);
$is_pinned = ($stmt->rowCount() > 0);

// Handle pin/unpin action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_pin'])) {
    if ($is_pinned) {
        // Unpin
        $stmt = $pdo->prepare("DELETE FROM pinned_jobs WHERE user_id = ? AND job_id = ?");
        $stmt->execute([$_SESSION['user_id'], $job_id]);
    } else {
        // Pin
        $stmt = $pdo->prepare("INSERT INTO pinned_jobs (user_id, job_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $job_id]);
    }
    
    // Refresh the page to update pin status
    header("Location: job_details.php?id={$job_id}");
    exit;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Details - <?php echo htmlspecialchars($job['title'] ?? 'N/A'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .job-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .job-section {
            margin-bottom: 25px;
        }
        .job-section h5 {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Navigation -->
        <div class="mb-3">
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Zurück zum Dashboard
            </a>
        </div>
        
        <!-- Job Header -->
        <div class="job-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h2><?php echo htmlspecialchars($job['title'] ?? 'N/A'); ?></h2>
                    <p class="lead mb-0"><?php echo htmlspecialchars($job['organization'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <form method="POST">
                        <button type="submit" name="toggle_pin" class="btn btn-outline-warning">
                            <i class="bi <?php echo $is_pinned ? 'bi-pin-fill' : 'bi-pin'; ?>"></i>
                            <?php echo $is_pinned ? 'Entpinnen' : 'Anpinnen'; ?>
                        </button>
                    </form>
                    <?php if (!empty($job['link'])): ?>
                        <a href="<?php echo htmlspecialchars($job['link']); ?>" target="_blank" class="btn btn-primary mt-2">
                            <i class="bi bi-link-45deg"></i> Original öffnen
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Left Column: Key Information -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Übersicht</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between">
                                <span><i class="bi bi-geo-alt"></i> Standort:</span>
                                <span class="text-end"><?php echo htmlspecialchars($job['location'] ?? 'N/A'); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span><i class="bi bi-briefcase"></i> Kategorie:</span>
                                <span class="text-end"><?php echo htmlspecialchars($job['job_category'] ?? 'N/A'); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span><i class="bi bi-building"></i> Organisation:</span>
                                <span class="text-end"><?php echo htmlspecialchars($job['organization'] ?? 'N/A'); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span><i class="bi bi-calendar-date"></i> Hinzugefügt am:</span>
                                <span class="text-end">
                                    <?php echo !empty($job['added_at']) ? date('d.m.Y', strtotime($job['added_at'])) : 'N/A'; ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span><i class="bi bi-hourglass-split"></i> Bewerbungsfrist:</span>
                                <span class="text-end"><?php echo htmlspecialchars($job['application_deadline'] ?? 'N/A'); ?></span>
                            </li>
                            <?php if (!empty($job['contract_type'])): ?>
                            <li class="list-group-item d-flex justify-content-between">
                                <span><i class="bi bi-file-earmark-text"></i> Vertragsart:</span>
                                <span class="text-end"><?php echo htmlspecialchars($job['contract_type']); ?></span>
                            </li>
                            <?php endif; ?>
                            <?php if (!empty($job['education_level'])): ?>
                            <li class="list-group-item d-flex justify-content-between">
                                <span><i class="bi bi-mortarboard"></i> Ausbildungsniveau:</span>
                                <span class="text-end"><?php echo htmlspecialchars($job['education_level']); ?></span>
                            </li>
                            <?php endif; ?>
                            <?php if (!empty($job['vacancy_count'])): ?>
                            <li class="list-group-item d-flex justify-content-between">
                                <span><i class="bi bi-people"></i> Offene Stellen:</span>
                                <span class="text-end"><?php echo htmlspecialchars($job['vacancy_count']); ?></span>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Detailed Information -->
            <div class="col-md-8">
                <!-- Job Description -->
                <?php if (!empty($job['full_description'])): ?>
                <div class="job-section">
                    <h5><i class="bi bi-info-circle"></i> Stellenbeschreibung</h5>
                    <div>
                        <?php echo nl2br(htmlspecialchars($job['full_description'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Job Details -->
                <?php if (!empty($job['job_details'])): ?>
                <div class="job-section">
                    <h5><i class="bi bi-list-task"></i> Details zur Stelle</h5>
                    <div>
                        <?php echo nl2br(htmlspecialchars($job['job_details'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Profile -->
                <?php if (!empty($job['profile'])): ?>
                <div class="job-section">
                    <h5><i class="bi bi-person"></i> Anforderungsprofil</h5>
                    <div>
                        <?php echo nl2br(htmlspecialchars($job['profile'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- How to Apply -->
                <?php if (!empty($job['how_to_apply'])): ?>
                <div class="job-section">
                    <h5><i class="bi bi-envelope"></i> Bewerbungshinweise</h5>
                    <div>
                        <?php echo nl2br(htmlspecialchars($job['how_to_apply'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Required Documents -->
                <?php if (!empty($job['required_documents'])): ?>
                <div class="job-section">
                    <h5><i class="bi bi-file-earmark"></i> Erforderliche Unterlagen</h5>
                    <div>
                        <?php echo nl2br(htmlspecialchars($job['required_documents'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Additional Information -->
                <?php if (!empty($job['general_information'])): ?>
                <div class="job-section">
                    <h5><i class="bi bi-info-square"></i> Weitere Informationen</h5>
                    <div>
                        <?php echo nl2br(htmlspecialchars($job['general_information'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>