<?php
// Common header elements and CSS styles
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style>
        .pinned {
            background-color: #fff3cd;
        }
        .pin-status-column {
            display: none; /* Hide the pin status column used for sorting */
        }
        .nav-tabs .nav-item .nav-link {
            position: relative;
            padding-right: 45px;
        }
        .nav-tabs .nav-item .filter-actions {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
        }
        .nav-tabs .nav-item .filter-actions button {
            border: none;
            background: transparent;
            padding: 0;
            font-size: 0.8rem;
            opacity: 0.6;
            margin-left: 2px;
        }
        .nav-tabs .nav-item .filter-actions button:hover {
            opacity: 1;
        }
        .edit-filter {
            color: #0d6efd;
        }
        .delete-filter {
            color: #dc3545;
        }
        .tab-content {
            border-left: 1px solid #dee2e6;
            border-right: 1px solid #dee2e6;
            border-bottom: 1px solid #dee2e6;
            padding: 20px;
            border-radius: 0 0 5px 5px;
            margin-bottom: 20px; /* Add space between tab content and job table */
        }
        .keyword-list {
            list-style: none;
            padding-left: 0;
        }
        .keyword-list li {
            background-color: #f8f9fa;
            padding: 5px 10px;
            margin-bottom: 5px;
            border-radius: 4px;
            display: inline-block;
            margin-right: 5px;
        }
        #jobsTable_filter {
            margin-bottom: 15px;
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
        .jobs-container {
            margin-top: 30px; /* Add more space between tabs and job list */
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
    <div class="container-fluid mt-4">
        <!-- Header with user info, statistics link and logout button -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Job Dashboard - <?php echo htmlspecialchars($_SESSION['username']); ?></h4>
            <div>
                <a href="job_statistics.php" class="btn btn-primary me-2">
                    <i class="bi bi-bar-chart-fill"></i> Statistiken
                </a>
                <form method="POST" class="d-inline-block me-2">
                    <div class="input-group">
                        <input type="number" name="time_frame" class="form-control" value="<?php echo $time_frame; ?>" min="1" max="365" style="width: 80px;">
                        <span class="input-group-text">Tage</span>
                        <button type="submit" name="update_timeframe" class="btn btn-outline-primary">Zeitraum aktualisieren</button>
                    </div>
                    <small class="text-muted d-block">* basierend auf "Erstellt am"</small>
                </form>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </div>