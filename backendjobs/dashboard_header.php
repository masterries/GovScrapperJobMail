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
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --accent-color: #ffc107;
            --success-color: #198754;
            --info-color: #0dcaf0;
            --light-bg: #f8f9fa;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f5f7fb;
        }
        
        .app-header {
            background-color: white;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            padding: 1rem 0;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .app-header .app-logo {
            font-weight: 700;
            font-size: 1.4rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .app-header .user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .time-frame-form {
            display: flex;
            align-items: center;
            background-color: white;
            border-radius: 0.5rem;
            padding: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.05);
        }
        
        .time-frame-form .form-control {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            width: 5rem;
        }
        
        .time-frame-form .input-group-text {
            background-color: white;
            border-left: none;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        
        .app-header .nav-link {
            padding: 0.5rem 1rem;
            color: var(--secondary-color);
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }
        
        .app-header .nav-link:hover {
            background-color: var(--light-bg);
            color: var(--primary-color);
        }
        
        .app-header .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }
        
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
            color: var(--primary-color);
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
            background-color: white;
        }
        
        .keyword-list {
            list-style: none;
            padding-left: 0;
        }
        
        .keyword-list li {
            background-color: var(--light-bg);
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
            color: var(--secondary-color);
        }
        
        .note-icon:hover {
            color: #212529;
        }
        
        .note-icon.has-note {
            color: var(--accent-color);
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
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .job-card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .job-card.pinned {
            border-color: var(--accent-color);
        }
        
        .job-card .card-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .job-description {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .debug-info {
            background-color: var(--light-bg);
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 1rem;
            margin-bottom: 1rem;
            font-family: monospace;
            font-size: 0.875rem;
        }
        
        /* Responsive styles */
        @media (max-width: 767.98px) {
            .app-header .app-logo {
                font-size: 1.2rem;
            }
            
            .time-frame-form {
                flex-direction: column;
                align-items: stretch;
                width: 100%;
            }
            
            .time-frame-form .input-group {
                margin-bottom: 0.5rem;
            }
            
            .user-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .app-header .action-buttons {
                flex-direction: column;
                width: 100%;
            }
            
            .app-header .action-buttons .btn {
                margin-bottom: 0.5rem;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Stylischer Header -->
    <header class="app-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <div class="app-logo">
                        <i class="bi bi-briefcase-fill"></i>
                        Job Dashboard - <span class="text-secondary"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="user-section float-md-end">
                        <form method="POST" class="time-frame-form me-2">
                            <div class="input-group">
                                <input type="number" name="time_frame" class="form-control" value="<?php echo $time_frame; ?>" min="1" max="365">
                                <span class="input-group-text">Tage</span>
                            </div>
                            <button type="submit" name="update_timeframe" class="btn btn-outline-primary ms-2">
                                <i class="bi bi-calendar-check"></i> Aktualisieren
                            </button>
                        </form>
                        <div class="action-buttons">
                            <a href="job_statistics.php" class="btn btn-primary me-2">
                                <i class="bi bi-bar-chart-fill"></i> Statistiken
                            </a>
                            <a href="logout.php" class="btn btn-outline-secondary">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <div class="container-fluid">