<?php
session_start();

// Check if the user is logged in. If not, redirect to login.php.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once './db_connect.php'; // Include your database connection

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle filter creation
    if (isset($_POST['create_filter'])) {
        $filter_name = trim($_POST['filter_name']);
        $keywords = trim($_POST['keywords']);
        
        if (!empty($filter_name) && !empty($keywords)) {
            // Add filter
            $stmt = $pdo->prepare("INSERT INTO filter_sets (user_id, name) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], $filter_name]);
            
            $filter_id = $pdo->lastInsertId();
            
            // Add keywords to filter
            $keyword_array = array_map('trim', explode(',', $keywords));
            foreach ($keyword_array as $keyword) {
                if (!empty($keyword)) {
                    $stmt = $pdo->prepare("INSERT INTO filter_keywords (filter_id, keyword) VALUES (?, ?)");
                    $stmt->execute([$filter_id, $keyword]);
                }
            }
        }
    }
    
    // Handle JSON import filter creation
    if (isset($_POST['import_json_filter']) && isset($_FILES['jsonFile']) && $_FILES['jsonFile']['error'] == 0) {
        $filter_name = trim($_POST['import_filter_name']);
        $selected_language = $_POST['language'] ?? 'de'; // Default to German
        
        if (!empty($filter_name)) {
            $json_content = file_get_contents($_FILES['jsonFile']['tmp_name']);
            $keywords_data = json_decode($json_content, true);
            
            if ($keywords_data && isset($keywords_data['keywords']) && is_array($keywords_data['keywords'])) {
                // Extract keywords in the selected language
                $extracted_keywords = [];
                foreach ($keywords_data['keywords'] as $keyword_set) {
                    if (isset($keyword_set[$selected_language]) && !empty($keyword_set[$selected_language])) {
                        $extracted_keywords[] = trim($keyword_set[$selected_language]);
                    }
                }
                
                if (!empty($extracted_keywords)) {
                    // Add filter
                    $stmt = $pdo->prepare("INSERT INTO filter_sets (user_id, name) VALUES (?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $filter_name]);
                    
                    $filter_id = $pdo->lastInsertId();
                    
                    // Add extracted keywords to filter
                    foreach ($extracted_keywords as $keyword) {
                        if (!empty($keyword)) {
                            $stmt = $pdo->prepare("INSERT INTO filter_keywords (filter_id, keyword) VALUES (?, ?)");
                            $stmt->execute([$filter_id, $keyword]);
                        }
                    }
                    
                    // Set success message
                    $_SESSION['success_message'] = "Filter '{$filter_name}' mit " . count($extracted_keywords) . " Stichwörtern erfolgreich importiert.";
                }
            }
        }
    }
    
    // Handle filter update
    if (isset($_POST['update_filter'])) {
        $filter_id = (int)$_POST['filter_id'];
        $filter_name = trim($_POST['filter_name']);
        $keywords = trim($_POST['keywords']);
        
        if (!empty($filter_name) && !empty($keywords)) {
            // Update filter name
            $stmt = $pdo->prepare("UPDATE filter_sets SET name = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$filter_name, $filter_id, $_SESSION['user_id']]);
            
            // Delete old keywords
            $stmt = $pdo->prepare("DELETE FROM filter_keywords WHERE filter_id = ?");
            $stmt->execute([$filter_id]);
            
            // Add new keywords
            $keyword_array = array_map('trim', explode(',', $keywords));
            foreach ($keyword_array as $keyword) {
                if (!empty($keyword)) {
                    $stmt = $pdo->prepare("INSERT INTO filter_keywords (filter_id, keyword) VALUES (?, ?)");
                    $stmt->execute([$filter_id, $keyword]);
                }
            }
        }
    }
    
    // Handle filter deletion
    if (isset($_POST['delete_filter'])) {
        $filter_id = (int)$_POST['filter_id'];
        
        // Delete filter and its keywords (cascade should handle the keywords)
        $stmt = $pdo->prepare("DELETE FROM filter_sets WHERE id = ? AND user_id = ?");
        $stmt->execute([$filter_id, $_SESSION['user_id']]);
    }
    
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
        
        // Redirect to the same page with all parameters preserved
        $redirect_url = 'dashboard.php';
        if (!empty($_GET)) {
            $redirect_url .= '?' . http_build_query($_GET);
        }
        header("Location: " . $redirect_url);
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
    
    // Handle time frame update
    if (isset($_POST['update_timeframe'])) {
        $time_frame = (int)$_POST['time_frame'];
        if ($time_frame > 0) {
            // Check if settings already exist
            $stmt = $pdo->prepare("SELECT id FROM user_settings WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            if ($stmt->rowCount() > 0) {
                // Update
                $stmt = $pdo->prepare("UPDATE user_settings SET time_frame = ? WHERE user_id = ?");
                $stmt->execute([$time_frame, $_SESSION['user_id']]);
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO user_settings (user_id, time_frame) VALUES (?, ?)");
                $stmt->execute([$_SESSION['user_id'], $time_frame]);
            }
        }
    }
    
    // Only redirect if not an AJAX request
    if (!isset($_POST['ajax'])) {
        // Redirect to avoid form resubmission
        header("Location: dashboard.php" . (isset($_GET['filter']) ? "?filter=" . $_GET['filter'] : ""));
        exit;
    }
}

// AJAX request to get note
if (isset($_GET['get_note']) && isset($_GET['job_id'])) {
    $job_id = (int)$_GET['job_id'];
    $stmt = $pdo->prepare("SELECT note FROM job_notes WHERE user_id = ? AND job_id = ?");
    $stmt->execute([$_SESSION['user_id'], $job_id]);
    $note = $stmt->fetchColumn();
    
    echo json_encode(['note' => $note ?: '']);
    exit;
}

// Get user's filters
$stmt = $pdo->prepare("SELECT fs.id, fs.name, GROUP_CONCAT(fk.keyword SEPARATOR ', ') as keywords 
                      FROM filter_sets fs 
                      LEFT JOIN filter_keywords fk ON fs.id = fk.filter_id 
                      WHERE fs.user_id = ? 
                      GROUP BY fs.id, fs.name");
$stmt->execute([$_SESSION['user_id']]);
$filters = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's time frame setting
$stmt = $pdo->prepare("SELECT time_frame FROM user_settings WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_settings = $stmt->fetch(PDO::FETCH_ASSOC);
$time_frame = $user_settings ? $user_settings['time_frame'] : 7; // Default to 7 days if not set

// Get user's pinned jobs
$stmt = $pdo->prepare("SELECT job_id FROM pinned_jobs WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$pinned_job_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'job_id');

// Get user's job notes
$stmt = $pdo->prepare("SELECT job_id, note FROM job_notes WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$job_notes = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $job_notes[$row['job_id']] = $row['note'];
}

// Determine which filter to use
$active_filter = null;
$search_term = null;
$edit_filter = null;
$pinned_only = false;

if (isset($_GET['filter']) && $_GET['filter'] !== 'search') {
    // Using a saved filter
    $filter_id = (int)$_GET['filter'];
    foreach ($filters as $filter) {
        if ($filter['id'] == $filter_id) {
            $active_filter = $filter;
            break;
        }
    }
} elseif (isset($_GET['search'])) {
    // Using search tab
    $search_term = trim($_GET['search']);
} elseif (isset($_GET['edit'])) {
    // Editing a filter
    $edit_id = (int)$_GET['edit'];
    foreach ($filters as $filter) {
        if ($filter['id'] == $edit_id) {
            $edit_filter = $filter;
            break;
        }
    }
} elseif (isset($_GET['pinned'])) {
    // Showing only pinned jobs
    $pinned_only = true;
}

// Build the job query based on filters
$where_clauses = [];
$params = [];

if ($pinned_only) {
    // If we're in the pinned tab, show only pinned jobs
    if (!empty($pinned_job_ids)) {
        $placeholders = implode(',', array_fill(0, count($pinned_job_ids), '?'));
        $where_clauses[] = "id IN ({$placeholders})";
        
        foreach ($pinned_job_ids as $pinned_id) {
            $params[] = $pinned_id;
        }
    } else {
        // No pinned jobs, return empty result
        $where_clauses[] = "1=0"; // This will result in no rows being returned
    }
} else {
    // For other tabs, build a more complex query to prioritize pinned jobs but respect time frame
    $date_limit = date('Y-m-d H:i:s', strtotime("-{$time_frame} days"));
    
    // Base condition to get recent jobs by created_at date
    $date_condition = "created_at >= ?";
    
    // If we have pinned jobs, add them to the query
    if (!empty($pinned_job_ids)) {
        $pinned_placeholders = implode(',', array_fill(0, count($pinned_job_ids), '?'));
        $pinned_condition = "id IN ({$pinned_placeholders})";
        
        // Get either pinned jobs OR jobs within time frame
        $where_clauses[] = "({$pinned_condition} OR {$date_condition})";
        
        // Add parameters for pinned jobs
        foreach ($pinned_job_ids as $pinned_id) {
            $params[] = $pinned_id;
        }
        
        // Add parameter for date condition
        $params[] = $date_limit;
    } else {
        // No pinned jobs, just use date condition
        $where_clauses[] = $date_condition;
        $params[] = $date_limit;
    }
}

// Keyword filter (if a filter is selected)
if ($active_filter) {
    $keyword_conditions = [];
    $keywords = explode(', ', $active_filter['keywords']);
    
    foreach ($keywords as $keyword) {
        if (!empty($keyword)) {
            $keyword_condition = "(title LIKE ? OR full_description LIKE ? OR job_details LIKE ? OR profile LIKE ?)";
            $keyword_conditions[] = $keyword_condition;
            
            $keyword_param = "%{$keyword}%";
            $params[] = $keyword_param;
            $params[] = $keyword_param;
            $params[] = $keyword_param;
            $params[] = $keyword_param;
        }
    }
    
    if (!empty($keyword_conditions)) {
        $where_clauses[] = "(" . implode(" OR ", $keyword_conditions) . ")";
    }
} elseif ($search_term) {
    // Free search
    $search_condition = "(title LIKE ? OR full_description LIKE ? OR job_details LIKE ? OR profile LIKE ? OR organization LIKE ? OR location LIKE ?)";
    $where_clauses[] = $search_condition;
    
    $search_param = "%{$search_term}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Combine where clauses
$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Fetch filtered jobs with an is_pinned marker for sorting
$select_fields = "*";

// Combine where clauses
$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Create the complete query - using pinned status for ORDER BY
$query = "SELECT {$select_fields} FROM jobs {$where_sql}";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Debugging - Log queries
function debug_to_console($data) {
    echo '<script>';
    echo 'console.log(' . json_encode($data) . ')';
    echo '</script>';
}

// Debug log for date filter
$date_debug = [
    'Zeitrahmen' => $time_frame,
    'Limit-Datum' => $date_limit,
    'Anzahl der Jobs' => count($jobs),
    'SQL' => $query,
    'Params' => $all_params,
    'Where Clause' => $where_sql
];
//debug_to_console($date_debug);

// Add an info div to show time filter parameters
$time_info = "Jobs der letzten {$time_frame} Tage (seit " . date('d.m.Y', strtotime("-{$time_frame} days")) . ")";
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
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <!-- Header with user info and logout button -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Job Dashboard - <?php echo htmlspecialchars($_SESSION['username']); ?></h4>
            <div>
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
        
        <!-- Filter Tabs -->
        <ul class="nav nav-tabs" id="filterTabs" role="tablist">
            <!-- All Jobs Tab -->
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo !isset($_GET['filter']) && !isset($_GET['search']) && !isset($_GET['edit']) && !isset($_GET['pinned']) ? 'active' : ''; ?>" 
                   href="dashboard.php" role="tab">
                    <i class="bi bi-list"></i> Alle Jobs
                </a>
            </li>
            
            <!-- Pinned Jobs Tab -->
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo isset($_GET['pinned']) ? 'active' : ''; ?>" 
                   href="dashboard.php?pinned=1" role="tab">
                    <i class="bi bi-pin-fill"></i> Gepinnte Jobs
                </a>
            </li>
            
            <!-- Search Tab -->
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo isset($_GET['search']) ? 'active' : ''; ?>" 
                   href="#searchTab" data-bs-toggle="tab" role="tab">
                    <i class="bi bi-search"></i> Freie Suche
                </a>
            </li>
            
            <!-- User Filters -->
            <?php foreach ($filters as $filter): ?>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?php echo isset($_GET['filter']) && $_GET['filter'] == $filter['id'] ? 'active' : ''; ?>" 
                       href="dashboard.php?filter=<?php echo $filter['id']; ?>" role="tab">
                        <i class="bi bi-funnel"></i> <?php echo htmlspecialchars($filter['name']); ?>
                        <div class="filter-actions">
                            <a href="dashboard.php?edit=<?php echo $filter['id']; ?>" class="btn btn-sm edit-filter">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="filter_id" value="<?php echo $filter['id']; ?>">
                                <button type="submit" name="delete_filter" class="btn btn-sm delete-filter" 
                                        onclick="return confirm('Möchten Sie diesen Filter wirklich löschen?');">
                                    <i class="bi bi-x"></i>
                                </button>
                            </form>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
            
            <!-- Add New Filter Tab -->
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo isset($_GET['edit']) ? '' : ''; ?>" href="#newFilterTab" data-bs-toggle="tab" role="tab">
                    <i class="bi bi-plus-circle"></i> Neuer Filter
                </a>
            </li>
            
            <!-- Edit Filter Tab (hidden, activated via JavaScript) -->
            <?php if ($edit_filter): ?>
                <li class="nav-item d-none" role="presentation" id="editFilterTab">
                    <a class="nav-link active" href="#editFilter" data-bs-toggle="tab" role="tab">
                        Filter bearbeiten
                    </a>
                </li>
            <?php endif; ?>
        </ul>
        
        <!-- Tab Content -->
        <div class="tab-content" id="filterTabsContent">
            <!-- Search Tab Content -->
            <div class="tab-pane fade <?php echo isset($_GET['search']) ? 'show active' : ''; ?>" id="searchTab" role="tabpanel">
                <form action="dashboard.php" method="GET" class="mb-4">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Suchbegriff eingeben..." 
                               value="<?php echo htmlspecialchars($search_term ?? ''); ?>">
                        <button type="submit" class="btn btn-primary">Suchen</button>
                    </div>
                </form>
            </div>
            
            <!-- New Filter Tab Content -->
            <div class="tab-pane fade" id="newFilterTab" role="tabpanel">
                <form method="POST" class="mb-4">
                    <div class="mb-3">
                        <label for="filterName" class="form-label">Filtername</label>
                        <input type="text" class="form-control" id="filterName" name="filter_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="keywords" class="form-label">Stichwörter (durch Komma getrennt)</label>
                        <input type="text" class="form-control" id="keywords" name="keywords" 
                               placeholder="z.B. Informatik, Data Science, Programmierung" required>
                    </div>
                    <button type="submit" name="create_filter" class="btn btn-primary">Filter erstellen</button>
                </form>
                
                <hr class="my-4">
                
                <h5>Stichwörter aus JSON importieren</h5>
                <form method="POST" enctype="multipart/form-data" class="mb-4" id="jsonImportForm">
                    <div class="mb-3">
                        <label for="jsonFile" class="form-label">JSON-Datei mit Stichwörtern</label>
                        <input type="file" class="form-control" id="jsonFile" name="jsonFile" accept=".json">
                        <small class="text-muted">Format: {"keywords": [{"en": "Data", "fr": "Données", "de": "Daten"}, ...]}</small>
                    </div>
                    <div class="mb-3">
                        <label for="importFilterName" class="form-label">Filtername</label>
                        <input type="text" class="form-control" id="importFilterName" name="import_filter_name" placeholder="Name für den neuen Filter">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gewünschte Sprache</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="language" id="langDe" value="de" checked>
                            <label class="form-check-label" for="langDe">Deutsch</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="language" id="langEn" value="en">
                            <label class="form-check-label" for="langEn">Englisch</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="language" id="langFr" value="fr">
                            <label class="form-check-label" for="langFr">Französisch</label>
                        </div>
                    </div>
                    <button type="button" id="parseJsonBtn" class="btn btn-secondary">JSON prüfen</button>
                    <button type="submit" name="import_json_filter" class="btn btn-primary">Importieren</button>
                </form>
                
                <!-- Preview Modal -->
                <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="previewModalLabel">Stichwörter Vorschau</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="keywordsPreview"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                                <button type="button" class="btn btn-primary" id="confirmImportBtn">Importieren</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Edit Filter Tab Content -->
            <?php if ($edit_filter): ?>
                <div class="tab-pane fade show active" id="editFilter" role="tabpanel">
                    <h5 class="mb-3">Filter bearbeiten: <?php echo htmlspecialchars($edit_filter['name']); ?></h5>
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="filter_id" value="<?php echo $edit_filter['id']; ?>">
                        <div class="mb-3">
                            <label for="editFilterName" class="form-label">Filtername</label>
                            <input type="text" class="form-control" id="editFilterName" name="filter_name" 
                                   value="<?php echo htmlspecialchars($edit_filter['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="editKeywords" class="form-label">Stichwörter (durch Komma getrennt)</label>
                            <input type="text" class="form-control" id="editKeywords" name="keywords" 
                                   value="<?php echo htmlspecialchars($edit_filter['keywords']); ?>" required>
                        </div>
                        <div class="d-flex justify-content-between">
                            <button type="submit" name="update_filter" class="btn btn-primary">Filter aktualisieren</button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">Abbrechen</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Display success message if any -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <!-- Active Filter Info -->
        <?php if ($active_filter): ?>
            <div class="alert alert-info mt-3">
                <strong>Aktiver Filter:</strong> <?php echo htmlspecialchars($active_filter['name']); ?>
                <div>
                    <strong>Stichwörter:</strong>
                    <ul class="keyword-list mt-2 mb-0">
                        <?php foreach (explode(', ', $active_filter['keywords']) as $keyword): ?>
                            <li><?php echo htmlspecialchars($keyword); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php elseif ($search_term): ?>
            <div class="alert alert-info mt-3">
                <strong>Suche nach:</strong> "<?php echo htmlspecialchars($search_term); ?>"
            </div>
        <?php elseif ($pinned_only): ?>
            <div class="alert alert-warning mt-3">
                <i class="bi bi-pin-fill"></i> <strong>Gepinnte Jobs:</strong> Hier werden nur deine angepinnten Jobs angezeigt.
            </div>
        <?php else: ?>
            <div class="alert alert-light mt-3">
                <i class="bi bi-calendar-date"></i> <strong><?php echo $time_info; ?></strong>
                <small class="text-muted d-block">Gepinnte Jobs werden immer angezeigt, unabhängig vom Datum.</small>
            </div>
        <?php endif; ?>
        
        <!-- Jobs Table with DataTables -->
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Jobs (<?php echo count($jobs); ?> gefunden)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($jobs)): ?>
                    <p>Keine Jobs gefunden, die Ihren Filterkriterien entsprechen.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table id="jobsTable" class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th class="pin-status-column">Gepinnt</th> <!-- Hidden pin status column for sorting -->
                                    <th style="width: 50px;"></th> <!-- Pin button column -->
                                    <th style="width: 50px;"></th> <!-- Notes column -->
                                    <th>Titel</th>
                                    <th>Organisation</th>
                                    <th>Erstellt am</th>
                                    <th>Bewerbungsfrist</th>
                                    <th style="width: 120px;">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jobs as $job): ?>
                                    <?php 
                                    $is_pinned = in_array($job['id'], $pinned_job_ids);
                                    $has_note = isset($job_notes[$job['id']]);
                                    ?>
                                    <tr class="<?php echo $is_pinned ? 'pinned' : ''; ?>" data-job-id="<?php echo $job['id']; ?>">
                                        <td class="pin-status-column"><?php echo $is_pinned ? '1' : '0'; ?></td>
                                        <td class="text-center">
                                            <form method="POST" class="pin-form">
                                                <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                <button type="button" class="btn btn-sm toggle-pin-btn" style="background: none; border: none;" data-job-id="<?php echo $job['id']; ?>">
                                                    <i class="bi <?php echo $is_pinned ? 'bi-pin-fill text-warning' : 'bi-pin'; ?> fs-5"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="text-center">
                                            <i class="bi bi-sticky <?php echo $has_note ? 'has-note' : ''; ?> note-icon fs-5" 
                                              data-bs-toggle="modal" data-bs-target="#noteModal" 
                                              data-job-id="<?php echo $job['id']; ?>"
                                              data-job-title="<?php echo htmlspecialchars($job['title']); ?>"></i>
                                        </td>
                                        <td><?php echo htmlspecialchars($job['title'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($job['organization'] ?? 'N/A'); ?></td>
                                        <td data-sort="<?php echo strtotime($job['created_at'] ?? 0); ?>">
                                            <?php if (!empty($job['created_at'])): ?>
                                                <?php echo date('d.m.Y', strtotime($job['created_at'])); ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td data-sort="<?php echo strtotime($job['application_deadline'] ?? 0); ?>">
                                            <?php if (!empty($job['application_deadline'])): ?>
                                                <?php echo htmlspecialchars($job['application_deadline']); ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($job['link'])): ?>
                                                <a href="<?php echo htmlspecialchars($job['link']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-link-45deg"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="job_details.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-info-circle"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
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
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('#jobsTable').DataTable({
                "order": [[0, "desc"], [4, "desc"]], // Sort by is_pinned first, then created_at
                "orderFixed": { "pre": [0, "desc"] }, // Always keep pinned jobs on top regardless of user sorting
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/de-DE.json"
                },
                "columnDefs": [
                    { "orderable": false, "targets": [1, 6] }, // Disable sorting for note and actions columns
                    { "type": "num", "targets": [4, 5] }, // Date columns are numeric for sorting
                    { "visible": false, "targets": [0] } // Hide the pin status column (used only for sorting)
                ],
                "pageLength": 25, // Show 25 entries per page
                "stateSave": true // Save table state (sorting, pagination)
            });
            
            // Handle pin toggle via AJAX
            $('.toggle-pin-btn').click(function() {
                const jobId = $(this).data('job-id');
                const btn = $(this);
                const icon = btn.find('i');
                const isPinned = icon.hasClass('bi-pin-fill');
                
                // Submit the form via AJAX
                $.ajax({
                    type: "POST",
                    url: "dashboard.php",
                    data: {
                        job_id: jobId,
                        toggle_pin: 1,
                        ajax: 1
                    },
                    dataType: "json",
                    success: function(response) {
                        if (response.success) {
                            // Toggle the pin icon
                            if (isPinned) {
                                icon.removeClass('bi-pin-fill text-warning').addClass('bi-pin');
                                // If we're in the pinned tab, fade out and remove the row
                                if (window.location.href.includes('pinned=1')) {
                                    btn.closest('tr').fadeOut(400, function() {
                                        // Reload the table to update counts and maintain proper state
                                        location.reload();
                                    });
                                }
                            } else {
                                icon.removeClass('bi-pin').addClass('bi-pin-fill text-warning');
                                // If toggling to pinned, might want to highlight the row
                                btn.closest('tr').addClass('pinned');
                            }
                        }
                    }
                });
            });
            
            // Handle note modal
            $('#noteModal').on('show.bs.modal', function (event) {
                const button = $(event.relatedTarget);
                const jobId = button.data('job-id');
                const jobTitle = button.data('job-title');
                
                const modal = $(this);
                modal.find('#noteJobId').val(jobId);
                modal.find('#noteJobTitle').text(jobTitle);
                
                // Load existing note if any
                $.getJSON('dashboard.php?get_note=1&job_id=' + jobId, function(data) {
                    modal.find('#noteText').val(data.note);
                });
            });
            
            // Save note
            $('#saveNoteBtn').click(function() {
                const jobId = $('#noteJobId').val();
                const note = $('#noteText').val();
                
                $.ajax({
                    type: "POST",
                    url: "dashboard.php",
                    data: {
                        job_id: jobId,
                        note: note,
                        save_note: 1,
                        ajax: 1
                    },
                    dataType: "json",
                    success: function(response) {
                        if (response.success) {
                            // Update the note icon
                            const noteIcon = $('tr[data-job-id="' + jobId + '"] .note-icon');
                            if (note.trim() !== '') {
                                noteIcon.addClass('has-note');
                            } else {
                                noteIcon.removeClass('has-note');
                            }
                            
                            // Close the modal
                            $('#noteModal').modal('hide');
                        }
                    }
                });
            });
            
            // JSON Import functionality
            $('#parseJsonBtn').click(function() {
                const fileInput = document.getElementById('jsonFile');
                const filterName = $('#importFilterName').val();
                const selectedLanguage = $('input[name="language"]:checked').val();
                
                if (!fileInput.files || fileInput.files.length === 0) {
                    alert('Bitte wählen Sie eine JSON-Datei aus.');
                    return;
                }
                
                if (!filterName) {
                    alert('Bitte geben Sie einen Filternamen ein.');
                    return;
                }
                
                const file = fileInput.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    try {
                        const jsonData = JSON.parse(e.target.result);
                        
                        if (!jsonData.keywords || !Array.isArray(jsonData.keywords)) {
                            alert('Ungültiges JSON-Format. Es wird ein "keywords"-Array erwartet.');
                            return;
                        }
                        
                        // Extract keywords in the selected language
                        const extractedKeywords = [];
                        jsonData.keywords.forEach(keywordSet => {
                            if (keywordSet[selectedLanguage]) {
                                extractedKeywords.push(keywordSet[selectedLanguage]);
                            }
                        });
                        
                        if (extractedKeywords.length === 0) {
                            alert(`Keine Stichwörter für die Sprache "${selectedLanguage}" gefunden.`);
                            return;
                        }
                        
                        // Display preview
                        const previewHtml = `
                            <p><strong>Filtername:</strong> ${filterName}</p>
                            <p><strong>Sprache:</strong> ${selectedLanguage}</p>
                            <p><strong>Gefundene Stichwörter (${extractedKeywords.length}):</strong></p>
                            <div class="border p-2 mb-3" style="max-height: 300px; overflow-y: auto;">
                                ${extractedKeywords.map(keyword => `<span class="badge bg-primary me-1 mb-1">${keyword}</span>`).join('')}
                            </div>
                        `;
                        
                        $('#keywordsPreview').html(previewHtml);
                        const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
                        previewModal.show();
                        
                        // Set up the confirm button to submit the form
                        $('#confirmImportBtn').off('click').on('click', function() {
                            $('#jsonImportForm').submit();
                        });
                        
                    } catch (error) {
                        alert('Fehler beim Parsen der JSON-Datei: ' + error.message);
                    }
                };
                
                reader.readAsText(file);
            });
        });
        
        // Activate tab based on URL
        document.addEventListener('DOMContentLoaded', function() {
            const searchParams = new URLSearchParams(window.location.search);
            if (searchParams.has('search')) {
                const searchTab = document.querySelector('#filterTabs a[href="#searchTab"]');
                if (searchTab) {
                    const tab = new bootstrap.Tab(searchTab);
                    tab.show();
                }
            }
            // For new filter tab activation
            if (searchParams.has('edit')) {
                const newFilterTab = document.querySelector('#filterTabs a[href="#newFilterTab"]');
                if (newFilterTab) {
                    const tab = new bootstrap.Tab(newFilterTab);
                    tab.show();
                }
            }
        });
    </script>