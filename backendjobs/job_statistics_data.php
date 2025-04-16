<?php
session_start();

// Check if the user is logged in. If not, return error.
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Nicht angemeldet']);
    exit;
}

require_once './db_connect.php'; // Include database connection

// Get monthly data for both jobs and unique_jobs tables (last 12 months)
$stmt_all = $pdo->query("
    SELECT 
        YEAR(created_at) as year,
        MONTH(created_at) as month, 
        COUNT(*) as count 
    FROM jobs 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) 
    GROUP BY YEAR(created_at), MONTH(created_at) 
    ORDER BY year ASC, month ASC
");
$all_jobs_data = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

$stmt_unique = $pdo->query("
    SELECT 
        YEAR(created_at) as year,
        MONTH(created_at) as month, 
        COUNT(*) as count 
    FROM unique_jobs 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) 
    GROUP BY YEAR(created_at), MONTH(created_at) 
    ORDER BY year ASC, month ASC
");
$unique_jobs_data = $stmt_unique->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for chart
$monthNames = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
$months = [];
$all_jobs = [];
$unique_jobs = [];

// Create a map of all unique year-month combinations
$year_month_map = [];
foreach ($all_jobs_data as $row) {
    $key = $row['year'] . '-' . str_pad($row['month'], 2, '0', STR_PAD_LEFT);
    $year_month_map[$key] = [
        'label' => $monthNames[$row['month']-1] . ' ' . $row['year'],
        'all' => $row['count'],
        'unique' => 0
    ];
}

foreach ($unique_jobs_data as $row) {
    $key = $row['year'] . '-' . str_pad($row['month'], 2, '0', STR_PAD_LEFT);
    if (isset($year_month_map[$key])) {
        $year_month_map[$key]['unique'] = $row['count'];
    } else {
        $year_month_map[$key] = [
            'label' => $monthNames[$row['month']-1] . ' ' . $row['year'],
            'all' => 0,
            'unique' => $row['count']
        ];
    }
}

// Sort by key (which is year-month)
ksort($year_month_map);

// Extract the data into arrays for the chart
foreach ($year_month_map as $data) {
    $months[] = $data['label'];
    $all_jobs[] = $data['all'];
    $unique_jobs[] = $data['unique'];
}

// Create the response
$response = [
    'months' => $months,
    'all_jobs' => $all_jobs,
    'unique_jobs' => $unique_jobs
];

// Return JSON
header('Content-Type: application/json');
echo json_encode($response);