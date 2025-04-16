<?php
session_start();

// Check if the user is logged in. If not, redirect to login.php.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once './db_connect.php'; // Include your database connection

// Check if user is a guest
$is_guest = isset($_SESSION['is_guest']) && $_SESSION['is_guest'] === true;

// Check if a group_id is provided
if (!isset($_GET['group_id']) || !is_numeric($_GET['group_id'])) {
    header("Location: dashboard.php");
    exit;
}

$group_id = (int)$_GET['group_id'];

// Fetch the unique job details first
$stmt = $pdo->prepare("SELECT * FROM unique_jobs WHERE group_id = ?");
$stmt->execute([$group_id]);
$group_job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group_job) {
    header("Location: dashboard.php");
    exit;
}

// Fetch all individual jobs in this group using the grouped_ids field
$grouped_ids = explode(',', $group_job['grouped_ids']);
$jobs = [];

if (!empty($grouped_ids)) {
    // Create placeholders for the IN clause
    $placeholders = implode(',', array_fill(0, count($grouped_ids), '?'));

    $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id IN ({$placeholders}) ORDER BY created_at DESC");
    $stmt->execute($grouped_ids);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no jobs are found, log an error for debugging
    if (empty($jobs)) {
        error_log("No jobs found for group ID {$group_id} with IDs: " . implode(',', $grouped_ids));
    }
} else {
    error_log("No grouped_ids found for group ID {$group_id}");
}

// Get user's pinned jobs and notes - only for registered users
if (!$is_guest) {
    $stmt = $pdo->prepare("SELECT job_id FROM pinned_jobs WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $pinned_job_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'job_id');

    $stmt = $pdo->prepare("SELECT job_id, note FROM job_notes WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $job_notes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $job_notes[$row['job_id']] = $row['note'];
    }
} else {
    // For guests, empty arrays
    $pinned_job_ids = [];
    $job_notes = [];
}

// Handle form submissions - only for registered users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_guest) {
    // Handle job pin/unpin
    if (isset($_POST['toggle_pin'])) {
        $job_id = (int)$_POST['job_id'];
        
        // Check if already pinned
        $stmt = $pdo->prepare("SELECT id FROM pinned_jobs WHERE user_id = ? AND job_id = ?");
        $stmt->execute([$_SESSION['user_id'], $job_id]);
        
        if ($stmt->rowCount() > 0) {
            // Unpin
            $stmt = $pdo->prepare("DELETE FROM pinned_jobs WHERE user_id = ? AND job_id = ?");
            $stmt->execute([$_SESSION['user_id'], $job_id]);
        } else {
            // Pin
            $stmt = $pdo->prepare("INSERT INTO pinned_jobs (user_id, job_id) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], $job_id]);
        }
        
        // Return JSON response for AJAX request
        if (isset($_POST['ajax'])) {
            echo json_encode(['success' => true]);
            exit;
        }
        
        // Redirect to the same page
        header("Location: job_details.php?group_id={$group_id}");
        exit;
    }
    
    // Handle job notes update
    if (isset($_POST['save_note'])) {
        $job_id = (int)$_POST['job_id'];
        $note = trim($_POST['note']);
        
        // Check if note already exists
        $stmt = $pdo->prepare("SELECT id FROM job_notes WHERE user_id = ? AND job_id = ?");
        $stmt->execute([$_SESSION['user_id'], $job_id]);
        
        if ($stmt->rowCount() > 0) {
            // Update existing note
            $stmt = $pdo->prepare("UPDATE job_notes SET note = ?, updated_at = NOW() WHERE user_id = ? AND job_id = ?");
            $stmt->execute([$note, $_SESSION['user_id'], $job_id]);
        } else {
            // Insert new note
            $stmt = $pdo->prepare("INSERT INTO job_notes (user_id, job_id, note) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $job_id, $note]);
        }
        
        // Return to AJAX call
        if (isset($_POST['ajax'])) {
            echo json_encode(['success' => true]);
            exit;
        }
    }
}

// AJAX request to get note - only for registered users
if (isset($_GET['get_note']) && isset($_GET['job_id']) && !$is_guest) {
    $job_id = (int)$_GET['job_id'];
    $stmt = $pdo->prepare("SELECT note FROM job_notes WHERE user_id = ? AND job_id = ?");
    $stmt->execute([$_SESSION['user_id'], $job_id]);
    $note = $stmt->fetchColumn();
    
    echo json_encode(['note' => $note ?: '']);
    exit;
}

// Set a placeholder time_frame value for the header
$time_frame = 0;
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>Job Details - <?php echo htmlspecialchars($group_job['base_title'] ?? 'Job Group'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="mobile-styles.css">
    <style>
        .pinned {
            background-color: #fff3cd;
        }
        .note-icon {
            cursor: pointer;
            color: #6c757d;
        }
        .note-icon:hover {
            color: #212529;
        }
        .note-icon.has-note {
            color: #ffc107;
        }
        .note-modal textarea {
            height: 200px;
        }
        .job-card {
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .job-card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .job-card.pinned {
            border-color: #ffc107;
        }
        .job-description {
            max-height: 300px;
            overflow-y: auto;
        }
        .debug-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 1rem;
            margin-bottom: 1rem;
            font-family: monospace;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container mt-4 job-details-container">
        <!-- Header with navigation -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Job Details</h4>
            <div>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Zurück zum Dashboard
                </a>
            </div>
        </div>
        
        <!-- Job Group Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><?php echo htmlspecialchars($group_job['base_title'] ?? 'Kein Titel verfügbar'); ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Klassifikation:</strong> <?php echo htmlspecialchars($group_job['group_classification'] ?? 'N/A'); ?></p>
                        <p><strong>Erstellt am:</strong> <?php echo !empty($group_job['created_at']) ? date('d.m.Y', strtotime($group_job['created_at'])) : 'N/A'; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Gruppe ID:</strong> <?php echo htmlspecialchars($group_job['group_id'] ?? 'N/A'); ?></p>
                        <p><strong>Anzahl ähnlicher Jobs:</strong> <?php echo count($jobs); ?></p>
                    </div>
                </div>
                
                <?php if (empty($jobs)): ?>
                <div class="alert alert-warning">
                    <strong>Hinweis:</strong> Keine einzelnen Jobs in dieser Gruppe gefunden.
                </div>
                <div class="debug-info">
                    <p><strong>Debug-Info:</strong></p>
                    <p>Group ID: <?php echo $group_id; ?></p>
                    <p>Grouped IDs: <?php echo $group_job['grouped_ids'] ?? 'Not available'; ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($jobs)): ?>
        <!-- Individual Jobs -->
        <h5 class="mb-3">Alle Jobs in dieser Gruppe (<?php echo count($jobs); ?>)</h5>
        
        <div class="row">
            <?php foreach ($jobs as $job): ?>
                <?php 
                $is_pinned = in_array($job['id'], $pinned_job_ids);
                $has_note = isset($job_notes[$job['id']]);
                ?>
                <div class="col-md-6">
                    <div class="card job-card <?php echo $is_pinned ? 'pinned' : ''; ?>">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><?php echo htmlspecialchars($job['title'] ?? 'N/A'); ?></h6>
                            <div>
                                <?php if (!$is_guest): ?>
                                <button type="button" class="btn btn-sm toggle-pin-btn" style="background: none; border: none;" data-job-id="<?php echo $job['id']; ?>">
                                    <i class="bi <?php echo $is_pinned ? 'bi-pin-fill text-warning' : 'bi-pin'; ?> fs-5"></i>
                                </button>
                                <i class="bi bi-sticky <?php echo $has_note ? 'has-note' : ''; ?> note-icon" 
                                  data-bs-toggle="modal" data-bs-target="#noteModal" 
                                  data-job-id="<?php echo $job['id']; ?>"
                                  data-job-title="<?php echo htmlspecialchars($job['title'] ?? 'N/A'); ?>"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <p><strong>Organisation:</strong> <?php echo htmlspecialchars($job['organization'] ?? 'N/A'); ?></p>
                                <p><strong>Standort:</strong> <?php echo htmlspecialchars($job['location'] ?? 'N/A'); ?></p>
                                <p><strong>Erstellt am:</strong> <?php echo !empty($job['created_at']) ? date('d.m.Y', strtotime($job['created_at'])) : 'N/A'; ?></p>
                                <?php if (!empty($job['application_deadline'])): ?>
                                    <p><strong>Bewerbungsfrist:</strong> <?php echo htmlspecialchars($job['application_deadline']); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="accordion" id="jobAccordion<?php echo $job['id']; ?>">
                                <?php if (!empty($job['full_description'])): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                                data-bs-target="#description<?php echo $job['id']; ?>">
                                            Vollständige Beschreibung
                                        </button>
                                    </h2>
                                    <div id="description<?php echo $job['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#jobAccordion<?php echo $job['id']; ?>">
                                        <div class="accordion-body job-description">
                                            <?php echo nl2br(htmlspecialchars($job['full_description'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($job['job_details'])): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                                data-bs-target="#details<?php echo $job['id']; ?>">
                                            Job Details
                                        </button>
                                    </h2>
                                    <div id="details<?php echo $job['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#jobAccordion<?php echo $job['id']; ?>">
                                        <div class="accordion-body job-description">
                                            <?php echo nl2br(htmlspecialchars($job['job_details'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($job['profile'])): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                                data-bs-target="#profile<?php echo $job['id']; ?>">
                                            Profil
                                        </button>
                                    </h2>
                                    <div id="profile<?php echo $job['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#jobAccordion<?php echo $job['id']; ?>">
                                        <div class="accordion-body job-description">
                                            <?php echo nl2br(htmlspecialchars($job['profile'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($job['link'])): ?>
                            <div class="mt-3">
                                <a href="<?php echo htmlspecialchars($job['link']); ?>" target="_blank" class="btn btn-primary">
                                    <i class="bi bi-link-45deg"></i> Job Ansehen
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <p>Keine spezifischen Jobs gefunden. Hier sind die Rohdaten des Job-Eintrags:</p>
            <div class="debug-info">
                <pre><?php print_r($group_job); ?></pre>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Note Modal -->
    <div class="modal fade" id="noteModal" tabindex="-1" aria-labelledby="noteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="noteModalLabel">Notiz</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="noteForm">
                        <input type="hidden" id="noteJobId" name="job_id">
                        <input type="hidden" name="ajax" value="1">
                        <div class="mb-3">
                            <label for="noteText" class="form-label">Notiz für: <span id="noteJobTitle"></span></label>
                            <textarea class="form-control" id="noteText" name="note" rows="4"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                    <button type="button" class="btn btn-primary" id="saveNoteBtn">Speichern</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="job_details_scripts.js"></script>
</body>
</html>