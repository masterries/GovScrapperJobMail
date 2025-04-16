<?php
session_start();

// Check if the user is logged in. If not, redirect to login.php.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once './db_connect.php'; // Include your database connection

// Get active view type (all jobs or unique jobs)
$view_type = isset($_GET['view']) && $_GET['view'] === 'all' ? 'all' : 'unique';

// Fetch extended job statistics
$jobStats = [];

// Total number of jobs
$stmt = $pdo->query("SELECT COUNT(*) as total_jobs FROM jobs");
$jobStats['total_jobs'] = $stmt->fetchColumn();

// Total unique jobs
$stmt = $pdo->query("SELECT COUNT(*) as total_unique_jobs FROM unique_jobs");
$jobStats['total_unique_jobs'] = $stmt->fetchColumn();

// Jobs by classification - depends on view type
if ($view_type === 'all') {
    $stmt = $pdo->query("
        SELECT 
            group_classification, 
            COUNT(*) as count 
        FROM jobs 
        WHERE group_classification IS NOT NULL AND group_classification != '' 
        GROUP BY group_classification 
        ORDER BY count DESC 
        LIMIT 15
    ");
} else {
    $stmt = $pdo->query("
        SELECT 
            group_classification, 
            COUNT(*) as count 
        FROM unique_jobs 
        WHERE group_classification IS NOT NULL AND group_classification != '' 
        GROUP BY group_classification 
        ORDER BY count DESC 
        LIMIT 15
    ");
}
$jobStats['by_classification'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Jobs by creation date (last 30 days) - depends on view type
if ($view_type === 'all') {
    $stmt = $pdo->query("
        SELECT 
            DATE(created_at) as date, 
            COUNT(*) as count 
        FROM jobs 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
        GROUP BY DATE(created_at) 
        ORDER BY date ASC
    ");
} else {
    $stmt = $pdo->query("
        SELECT 
            DATE(created_at) as date, 
            COUNT(*) as count 
        FROM unique_jobs 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
        GROUP BY DATE(created_at) 
        ORDER BY date ASC
    ");
}
$jobStats['by_date'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Jobs by organization - always from jobs table as unique_jobs doesn't have organization
$stmt = $pdo->query("
    SELECT 
        organization, 
        COUNT(*) as count 
    FROM jobs 
    WHERE organization IS NOT NULL AND organization != '' 
    GROUP BY organization 
    ORDER BY count DESC 
    LIMIT 10
");
$jobStats['by_organization'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Most frequent jobs (repeated postings) - hot jobs
$stmt = $pdo->query("
    SELECT 
        uj.base_title, 
        uj.group_classification, 
        uj.group_id, 
        COUNT(DISTINCT DATE(j.created_at)) as posting_days, 
        COUNT(*) as appearance_count,
        MAX(j.created_at) as last_posted
    FROM unique_jobs uj
    JOIN jobs j ON FIND_IN_SET(j.id, uj.grouped_ids)
    GROUP BY uj.base_title, uj.group_classification, uj.group_id
    HAVING appearance_count > 1
    ORDER BY appearance_count DESC
    LIMIT 15
");
$jobStats['hot_jobs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Jobs by month - depends on view type
if ($view_type === 'all') {
    $stmt = $pdo->query("
        SELECT 
            YEAR(created_at) as year,
            MONTH(created_at) as month, 
            COUNT(*) as count 
        FROM jobs 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) 
        GROUP BY YEAR(created_at), MONTH(created_at) 
        ORDER BY year ASC, month ASC
    ");
} else {
    $stmt = $pdo->query("
        SELECT 
            YEAR(created_at) as year,
            MONTH(created_at) as month, 
            COUNT(*) as count 
        FROM unique_jobs 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) 
        GROUP BY YEAR(created_at), MONTH(created_at) 
        ORDER BY year ASC, month ASC
    ");
}
$jobStats['by_month'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// User statistics
$stmt = $pdo->query("
    SELECT COUNT(*) as total_users,
    (SELECT COUNT(*) FROM pinned_jobs) as total_pinned_jobs,
    (SELECT COUNT(*) FROM job_notes) as total_notes,
    (SELECT COUNT(DISTINCT user_id) FROM pinned_jobs) as users_with_pins,
    (SELECT COUNT(DISTINCT user_id) FROM job_notes) as users_with_notes
    FROM users
");
$jobStats['user_stats'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Jobs by contract type - always from jobs table
$stmt = $pdo->query("
    SELECT 
        CASE 
            WHEN contract_type IS NULL OR contract_type = '' THEN 'Nicht angegeben'
            ELSE contract_type
        END as contract_type,
        COUNT(*) as count
    FROM jobs
    GROUP BY contract_type
    ORDER BY count DESC
");
$jobStats['by_contract_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set a placeholder time_frame value for the header
$time_frame = 0;
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Statistiken</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-card {
            transition: all 0.3s ease;
            border-radius: 0.5rem;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .hot-jobs-table {
            max-height: 400px;
            overflow-y: auto;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .view-toggle .btn {
            border-radius: 20px;
        }
        .view-toggle .active {
            background-color: #0d6efd;
            color: white;
        }
    </style>
</head>
<body>
    <?php require_once './dashboard_header.php'; ?>

    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="bi bi-bar-chart-line-fill"></i> Job Statistiken & Analysen</h3>
            
            <!-- View Toggle Buttons -->
            <div class="view-toggle">
                <a href="job_statistics.php?view=unique" class="btn <?php echo $view_type === 'unique' ? 'active' : 'btn-outline-primary'; ?> me-2">
                    <i class="bi bi-filter"></i> Einzigartige Jobs
                </a>
                <a href="job_statistics.php?view=all" class="btn <?php echo $view_type === 'all' ? 'active' : 'btn-outline-primary'; ?>">
                    <i class="bi bi-list-ul"></i> Alle Jobs
                </a>
            </div>
        </div>

        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> 
            <strong>Ansichtsmodus:</strong> 
            <?php if ($view_type === 'all'): ?>
                Zeigt Statistiken für <strong>alle Jobs</strong> (inkl. Duplikate). Diese Ansicht zeigt die tatsächliche Anzahl aller Stellenausschreibungen.
            <?php else: ?>
                Zeigt Statistiken für <strong>einzigartige Jobs</strong> (ohne Duplikate). Diese Ansicht ist oft aussagekräftiger für Trendanalysen.
            <?php endif; ?>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4 stats-card bg-primary text-white">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6 text-center mb-3">
                                <h2 class="display-4"><?php echo number_format($jobStats['total_unique_jobs']); ?></h2>
                                <p class="text-white-50">Einzigartige Jobs</p>
                            </div>
                            <div class="col-6 text-center mb-3">
                                <h2 class="display-4"><?php echo number_format($jobStats['total_jobs']); ?></h2>
                                <p class="text-white-50">Alle Jobs</p>
                            </div>
                        </div>
                        <hr class="bg-white">
                        <div class="row">
                            <div class="col-md-6 text-center">
                                <h4><?php echo number_format($jobStats['user_stats']['total_pinned_jobs']); ?></h4>
                                <p class="text-white-50">Gepinnte Jobs</p>
                            </div>
                            <div class="col-md-6 text-center">
                                <h4><?php echo number_format($jobStats['user_stats']['total_notes']); ?></h4>
                                <p class="text-white-50">Notizen</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4 stats-card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="bi bi-tags"></i> Jobs nach Vertragsart</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="contractTypeChart" height="250"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card mb-4 stats-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-calendar3"></i> Jobs nach Monat (12 Monate)</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4 stats-card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Jobs der letzten 30 Tage</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="dateChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card mb-4 stats-card">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="bi bi-building"></i> Top 10 Organisationen</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="organizationChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-5">
                <div class="card mb-4 stats-card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="bi bi-tags"></i> Jobs nach Klassifikation</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="classificationChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card mb-4 stats-card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="bi bi-fire"></i> Hot Jobs (häufig ausgeschrieben)</h5>
                    </div>
                    <div class="card-body">
                        <div class="hot-jobs-table">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>Job Titel</th>
                                        <th>Klassifikation</th>
                                        <th>Ausschreibungstage</th>
                                        <th>Erscheinungen</th>
                                        <th>Zuletzt</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jobStats['hot_jobs'] as $job): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($job['base_title']); ?></td>
                                        <td><?php echo htmlspecialchars($job['group_classification']); ?></td>
                                        <td><?php echo $job['posting_days']; ?></td>
                                        <td><span class="badge bg-danger"><?php echo $job['appearance_count']; ?></span></td>
                                        <td><?php echo date('d.m.Y', strtotime($job['last_posted'])); ?></td>
                                        <td>
                                            <a href="job_details.php?group_id=<?php echo $job['group_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-info-circle"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($view_type === 'all'): ?>
        <div class="row">
            <div class="col-12">
                <div class="card mb-4 stats-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Vergleich: Alle Jobs vs. Einzigartige Jobs</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="comparisonChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Monthly chart
        const monthlyLabels = <?php 
            $labels = [];
            $monthNames = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
            foreach ($jobStats['by_month'] as $item) {
                $labels[] = $monthNames[$item['month']-1] . ' ' . $item['year'];
            }
            echo json_encode($labels); 
        ?>;
        
        const monthlyData = <?php echo json_encode(array_column($jobStats['by_month'], 'count')); ?>;
        
        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: '<?php echo $view_type === 'all' ? 'Alle Jobs' : 'Einzigartige Jobs'; ?>',
                    data: monthlyData,
                    backgroundColor: 'rgba(40, 167, 69, 0.2)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: true },
                    title: {
                        display: true,
                        text: 'Anzahl der Jobs pro Monat'
                    }
                },
                scales: { y: { beginAtZero: true } }
            }
        });

        // Daily chart for last 30 days
        const dateData = {
            labels: <?php echo json_encode(array_column($jobStats['by_date'], 'date')); ?>,
            datasets: [{
                label: '<?php echo $view_type === 'all' ? 'Alle Jobs' : 'Einzigartige Jobs'; ?>',
                data: <?php echo json_encode(array_column($jobStats['by_date'], 'count')); ?>,
                backgroundColor: 'rgba(23, 162, 184, 0.2)',
                borderColor: 'rgba(23, 162, 184, 1)',
                borderWidth: 1,
                fill: true,
                tension: 0.4
            }]
        };

        new Chart(document.getElementById('dateChart'), {
            type: 'line',
            data: dateData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: true },
                    title: {
                        display: true,
                        text: 'Tägliche Job-Veröffentlichungen (letzte 30 Tage)'
                    }
                },
                scales: { y: { beginAtZero: true } }
            }
        });

        // Organization chart
        const organizationData = {
            labels: <?php echo json_encode(array_column($jobStats['by_organization'], 'organization')); ?>,
            datasets: [{
                label: 'Anzahl der Jobs',
                data: <?php echo json_encode(array_column($jobStats['by_organization'], 'count')); ?>,
                backgroundColor: 'rgba(255, 193, 7, 0.2)',
                borderColor: 'rgba(255, 193, 7, 1)',
                borderWidth: 1
            }]
        };

        new Chart(document.getElementById('organizationChart'), {
            type: 'bar',
            data: organizationData,
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false },
                    title: {
                        display: true,
                        text: 'Top 10 Organisationen nach Anzahl der Jobs'
                    }
                }
            }
        });

        // Classification chart
        const classificationData = {
            labels: <?php echo json_encode(array_column($jobStats['by_classification'], 'group_classification')); ?>,
            datasets: [{
                label: 'Anzahl der Jobs',
                data: <?php echo json_encode(array_column($jobStats['by_classification'], 'count')); ?>,
                backgroundColor: [
                    'rgba(108, 117, 125, 0.2)',
                    'rgba(108, 117, 125, 0.3)',
                    'rgba(108, 117, 125, 0.4)',
                    'rgba(108, 117, 125, 0.5)',
                    'rgba(108, 117, 125, 0.6)'
                ],
                borderColor: 'rgba(108, 117, 125, 1)',
                borderWidth: 1
            }]
        };

        new Chart(document.getElementById('classificationChart'), {
            type: 'pie',
            data: classificationData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', align: 'start' },
                    title: {
                        display: true,
                        text: 'Verteilung nach Klassifikation'
                    }
                }
            }
        });

        // Contract type chart
        const contractTypeData = {
            labels: <?php echo json_encode(array_column($jobStats['by_contract_type'], 'contract_type')); ?>,
            datasets: [{
                label: 'Anzahl der Jobs',
                data: <?php echo json_encode(array_column($jobStats['by_contract_type'], 'count')); ?>,
                backgroundColor: [
                    'rgba(0, 123, 255, 0.5)',
                    'rgba(220, 53, 69, 0.5)',
                    'rgba(255, 193, 7, 0.5)',
                    'rgba(40, 167, 69, 0.5)',
                    'rgba(23, 162, 184, 0.5)'
                ],
                borderWidth: 1
            }]
        };

        new Chart(document.getElementById('contractTypeChart'), {
            type: 'doughnut',
            data: contractTypeData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12
                        }
                    },
                    title: {
                        display: true,
                        text: 'Verteilung nach Vertragsart'
                    }
                }
            }
        });

        <?php if ($view_type === 'all'): ?>
        // Comparison chart (only shown in 'all' view)
        // Fetch data for comparison
        fetch('job_statistics_data.php')
            .then(response => response.json())
            .then(data => {
                const comparisonChart = new Chart(document.getElementById('comparisonChart'), {
                    type: 'line',
                    data: {
                        labels: data.months,
                        datasets: [
                            {
                                label: 'Alle Jobs',
                                data: data.all_jobs,
                                borderColor: 'rgba(0, 123, 255, 1)',
                                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                                fill: true
                            },
                            {
                                label: 'Einzigartige Jobs',
                                data: data.unique_jobs,
                                borderColor: 'rgba(40, 167, 69, 1)',
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top' },
                            title: {
                                display: true,
                                text: 'Vergleich: Alle Jobs vs. Einzigartige Jobs (pro Monat)'
                            }
                        },
                        scales: { y: { beginAtZero: true } }
                    }
                });
            });
        <?php endif; ?>
    </script>

    <?php require_once './dashboard_footer.php'; ?>
</body>
</html>