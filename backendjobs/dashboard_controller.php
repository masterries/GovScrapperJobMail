<?php
// Handle all form submissions and data processing

// Initialize variables
$active_filter = null;
$search_term = null;
$edit_filter = null;
$pinned_only = false;
$jobs = [];
$search_mode = 'soft'; // Default search mode: 'soft' (view only) or 'full' (all fields)
$custom_date_from = null;
$custom_date_to = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequests($pdo);
}

// Handle AJAX request to get note
if (isset($_GET['get_note']) && isset($_GET['job_id'])) {
    $job_id = (int)$_GET['job_id'];
    $stmt = $pdo->prepare("SELECT note FROM job_notes WHERE user_id = ? AND job_id = ?");
    $stmt->execute([$_SESSION['user_id'], $job_id]);
    $note = $stmt->fetchColumn();
    
    echo json_encode(['note' => $note ?: '']);
    exit;
}

// Get user data
$filters = getUserFilters($pdo);
$time_frame = getUserTimeFrame($pdo);
$pinned_job_ids = getUserPinnedJobs($pdo);
$job_notes = getUserJobNotes($pdo);

// Determine which filter to use
if (isset($_GET['filter']) && $_GET['filter'] !== 'search') {
    // Using a saved filter
    $filter_id = (int)$_GET['filter'];
    foreach ($filters as $filter) {
        if ($filter['id'] == $filter_id) {
            $active_filter = $filter;
            // Use date range from filter if available
            if (!empty($filter['date_from'])) {
                $custom_date_from = $filter['date_from'];
            }
            if (!empty($filter['date_to'])) {
                $custom_date_to = $filter['date_to'];
            }
            break;
        }
    }
} elseif (isset($_GET['search'])) {
    // Using search tab
    $search_term = trim($_GET['search']);
    
    // Check if search mode is specified
    if (isset($_GET['search_mode']) && in_array($_GET['search_mode'], ['soft', 'full'])) {
        $search_mode = $_GET['search_mode'];
    }
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

// Check for custom date range
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $custom_date_from = date('Y-m-d', strtotime($_GET['date_from']));
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $custom_date_to = date('Y-m-d', strtotime($_GET['date_to']));
}

// Fetch jobs data
$jobs = getFilteredJobs($pdo, $active_filter, $search_term, $pinned_only, $pinned_job_ids, $time_frame, $search_mode, $custom_date_from, $custom_date_to);

// Calculate time info for display
if ($custom_date_from && $custom_date_to) {
    $time_info = "Jobs vom " . date('d.m.Y', strtotime($custom_date_from)) . " bis " . date('d.m.Y', strtotime($custom_date_to));
} elseif ($custom_date_from) {
    $time_info = "Jobs seit " . date('d.m.Y', strtotime($custom_date_from));
} elseif ($custom_date_to) {
    $time_info = "Jobs bis " . date('d.m.Y', strtotime($custom_date_to));
} else {
    $time_info = "Jobs der letzten {$time_frame} Tage (seit " . date('d.m.Y', strtotime("-{$time_frame} days")) . ")";
}

/**
 * Handle all POST requests
 */
function handlePostRequests($pdo) {
    // Handle filter creation
    if (isset($_POST['create_filter'])) {
        createFilter($pdo);
    }
    
    // Handle JSON import filter creation
    if (isset($_POST['import_json_filter']) && isset($_FILES['jsonFile']) && $_FILES['jsonFile']['error'] == 0) {
        importJsonFilter($pdo);
    }
    
    // Handle filter update
    if (isset($_POST['update_filter'])) {
        updateFilter($pdo);
    }
    
    // Handle filter deletion
    if (isset($_POST['delete_filter'])) {
        deleteFilter($pdo);
    }
    
    // Handle job pin/unpin
    if (isset($_POST['toggle_pin'])) {
        togglePinJob($pdo);
    }
    
    // Handle job notes update
    if (isset($_POST['save_note'])) {
        saveJobNote($pdo);
    }
    
    // Handle time frame update
    if (isset($_POST['update_timeframe'])) {
        updateTimeFrame($pdo);
    }
    
    // Only redirect if not an AJAX request
    if (!isset($_POST['ajax'])) {
        // Redirect to avoid form resubmission
        header("Location: dashboard.php" . (isset($_GET['filter']) ? "?filter=" . $_GET['filter'] : ""));
        exit;
    }
}

/**
 * Create a new filter
 */
function createFilter($pdo) {
    $filter_name = trim($_POST['filter_name']);
    $keywords = trim($_POST['keywords']);
    $date_from = !empty($_POST['filter_date_from']) ? $_POST['filter_date_from'] : null;
    $date_to = !empty($_POST['filter_date_to']) ? $_POST['filter_date_to'] : null;
    
    if (!empty($filter_name) && !empty($keywords)) {
        // Add filter
        $stmt = $pdo->prepare("INSERT INTO filter_sets (user_id, name, date_from, date_to) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $filter_name, $date_from, $date_to]);
        
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

/**
 * Import filter from JSON file
 */
function importJsonFilter($pdo) {
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

/**
 * Update an existing filter
 */
function updateFilter($pdo) {
    $filter_id = (int)$_POST['filter_id'];
    $filter_name = trim($_POST['filter_name']);
    $keywords = trim($_POST['keywords']);
    $date_from = !empty($_POST['filter_date_from']) ? $_POST['filter_date_from'] : null;
    $date_to = !empty($_POST['filter_date_to']) ? $_POST['filter_date_to'] : null;
    
    if (!empty($filter_name) && !empty($keywords)) {
        // Update filter name and date range
        $stmt = $pdo->prepare("UPDATE filter_sets SET name = ?, date_from = ?, date_to = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$filter_name, $date_from, $date_to, $filter_id, $_SESSION['user_id']]);
        
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

/**
 * Delete a filter
 */
function deleteFilter($pdo) {
    $filter_id = (int)$_POST['filter_id'];
    
    // Delete filter and its keywords (cascade should handle the keywords)
    $stmt = $pdo->prepare("DELETE FROM filter_sets WHERE id = ? AND user_id = ?");
    $stmt->execute([$filter_id, $_SESSION['user_id']]);
}

/**
 * Toggle job pin status
 */
function togglePinJob($pdo) {
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

/**
 * Save job note
 */
function saveJobNote($pdo) {
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

/**
 * Update time frame setting
 */
function updateTimeFrame($pdo) {
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

/**
 * Get user's filters
 */
function getUserFilters($pdo) {
    $stmt = $pdo->prepare("SELECT fs.id, fs.name, fs.date_from, fs.date_to, GROUP_CONCAT(fk.keyword SEPARATOR ', ') as keywords 
                          FROM filter_sets fs 
                          LEFT JOIN filter_keywords fk ON fs.id = fk.filter_id 
                          WHERE fs.user_id = ? 
                          GROUP BY fs.id, fs.name, fs.date_from, fs.date_to");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get user's time frame setting
 */
function getUserTimeFrame($pdo) {
    $stmt = $pdo->prepare("SELECT time_frame FROM user_settings WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user_settings ? $user_settings['time_frame'] : 7; // Default to 7 days if not set
}

/**
 * Get user's pinned jobs
 */
function getUserPinnedJobs($pdo) {
    $stmt = $pdo->prepare("SELECT job_id FROM pinned_jobs WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'job_id');
}

/**
 * Get user's job notes
 */
function getUserJobNotes($pdo) {
    $stmt = $pdo->prepare("SELECT job_id, note FROM job_notes WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $job_notes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $job_notes[$row['job_id']] = $row['note'];
    }
    return $job_notes;
}

/**
 * Get filtered jobs based on criteria
 */
function getFilteredJobs($pdo, $active_filter, $search_term, $pinned_only, $pinned_job_ids, $time_frame, $search_mode = 'soft', $custom_date_from = null, $custom_date_to = null) {
    // Build the job query based on filters
    $where_clauses = [];
    $params = [];

    // Determine which table to use based on search mode
    if ($search_mode === 'full' && !empty($search_term)) {
        // For full-text search, first find matching job IDs in the jobs table
        $search_params = [];
        $search_conditions = [];
        $search_condition = "(title LIKE ? OR full_description LIKE ? OR job_details LIKE ? OR profile LIKE ? OR organization LIKE ? OR location LIKE ?)";
        $search_conditions[] = $search_condition;
        
        $search_param = "%{$search_term}%";
        for ($i = 0; $i < 6; $i++) {
            $search_params[] = $search_param;
        }
        
        $search_sql = "WHERE " . implode(" OR ", $search_conditions);
        $query = "SELECT id FROM jobs {$search_sql}";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($search_params);
        $job_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
        
        // Then get those jobs from the unique_jobs view to ensure we have all needed fields
        if (!empty($job_ids)) {
            $placeholders = implode(',', array_fill(0, count($job_ids), '?'));
            $where_clauses[] = "id IN ({$placeholders})";
            $params = array_merge($params, $job_ids);
        } else {
            // No results found
            $where_clauses[] = "1=0";
        }
        
        $table = "unique_jobs";
    } else {
        // Use unique_jobs view for soft search
        $table = "unique_jobs";
        
        // Rest of your existing soft search code...
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
                $where_clauses[] = "1=0"; 
            }
        } else {
            // Date conditions here...
            // Date condition based on custom range or default time frame
            if ($custom_date_from && $custom_date_to) {
                $date_condition = "created_at BETWEEN ? AND ?";
                $date_params = [$custom_date_from . " 00:00:00", $custom_date_to . " 23:59:59"];
            } elseif ($custom_date_from) {
                $date_condition = "created_at >= ?";
                $date_params = [$custom_date_from . " 00:00:00"];
            } elseif ($custom_date_to) {
                $date_condition = "created_at <= ?";
                $date_params = [$custom_date_to . " 23:59:59"];
            } else {
                // Default time frame
                $date_limit = date('Y-m-d H:i:s', strtotime("-{$time_frame} days"));
                $date_condition = "created_at >= ?";
                $date_params = [$date_limit];
            }
            
            // Pinned jobs
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
                foreach ($date_params as $date_param) {
                    $params[] = $date_param;
                }
            } else {
                // No pinned jobs, just use date condition
                $where_clauses[] = $date_condition;
                foreach ($date_params as $date_param) {
                    $params[] = $date_param;
                }
            }
        }

        // Keywords/search for soft search
        if ($active_filter) {
            $keyword_conditions = [];
            $keywords = explode(', ', $active_filter['keywords']);
            
            foreach ($keywords as $keyword) {
                if (!empty($keyword)) {
                    // Soft search on unique_jobs view
                    $keyword_condition = "(base_title LIKE ? OR group_classification LIKE ?)";
                    $keyword_conditions[] = $keyword_condition;
                    
                    $keyword_param = "%{$keyword}%";
                    $params[] = $keyword_param;
                    $params[] = $keyword_param;
                }
            }
            
            if (!empty($keyword_conditions)) {
                $where_clauses[] = "(" . implode(" OR ", $keyword_conditions) . ")";
            }
        } elseif ($search_term && $search_mode === 'soft') {
            // Soft search
            $search_condition = "(base_title LIKE ? OR group_classification LIKE ?)";
            $where_clauses[] = $search_condition;
            
            $search_param = "%{$search_term}%";
            $params[] = $search_param;
            $params[] = $search_param;
        }
    }

    // Combine where clauses
    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

    // Create the complete query
    $query = "SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC";

    // Execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>