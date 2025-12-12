<?php
// Include the header
require_once 'dashboard_header.php';

$jobCount = count($jobs);
$pinnedCount = is_array($pinned_job_ids) ? count($pinned_job_ids) : 0;
$filterCount = isset($filters) ? count($filters) : 0;
?>

<section class="app-hero mt-3">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div>
            <h2 class="mb-1">Dein Job-Cockpit</h2>
            <p class="subtitle mb-2"><?php echo $time_info; ?></p>
            <small class="text-muted">Verwalte Filter, suche gezielt und halte interessante Stellen fest.</small>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if (!$is_guest): ?>
            <a href="#newFilterTab" data-bs-toggle="tab" class="btn btn-primary" id="quickNewFilterBtn">
                <i class="bi bi-plus-circle"></i> Neuer Filter
            </a>
            <a href="dashboard.php?pinned=1" class="btn btn-outline-primary">
                <i class="bi bi-pin-angle"></i> Gepinnte Jobs
            </a>
            <?php endif; ?>
            <a href="job_statistics.php" class="btn btn-outline-secondary">
                <i class="bi bi-bar-chart"></i> Statistiken
            </a>
        </div>
    </div>

    <?php if ($is_guest): ?>
    <div class="alert alert-warning mt-3 mb-0">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-person-fill-exclamation fs-4"></i>
            <div>
                <h6 class="mb-1">Gast-Modus</h6>
                <p class="mb-2">Als Gast können Sie Jobs der letzten 7 Tage einsehen, aber keine Pins oder Notizen anlegen.</p>
                <a href="register.php" class="btn btn-sm btn-primary">Registrieren</a>
                <a href="login.php" class="btn btn-sm btn-outline-primary ms-1">Anmelden</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</section>

<div class="metrics-grid">
    <div class="metric-card">
        <div class="label">Gefundene Jobs</div>
        <div class="value"><?php echo $jobCount; ?></div>
        <span class="badge bg-light text-primary"><i class="bi bi-search"></i> Ergebnisbasis</span>
    </div>
    <div class="metric-card">
        <div class="label">Gepinnt</div>
        <?php if ($is_guest): ?>
            <div class="value text-muted">–</div>
            <span class="badge bg-secondary">Nur für registrierte Nutzer</span>
        <?php else: ?>
            <div class="value"><?php echo $pinnedCount; ?></div>
            <span class="badge bg-warning text-dark"><i class="bi bi-pin-angle"></i> Immer oben</span>
        <?php endif; ?>
    </div>
    <div class="metric-card">
        <div class="label">Eigene Filter</div>
        <div class="value"><?php echo $filterCount; ?></div>
        <span class="badge bg-light text-success"><i class="bi bi-funnel"></i> Schnellzugriff</span>
    </div>
    <div class="metric-card">
        <div class="label">Zeitraum</div>
        <div class="value"><?php echo htmlspecialchars($time_frame); ?> Tage</div>
        <span class="badge bg-info text-dark"><i class="bi bi-calendar-event"></i> Anpassbar</span>
    </div>
</div>

<div class="panel tabs-section">
    <!-- Filter Tabs -->
    <ul class="nav nav-tabs" id="filterTabs" role="tablist">
        <!-- All Jobs Tab -->
        <li class="nav-item" role="presentation">
            <a class="nav-link <?php echo !isset($_GET['filter']) && !isset($_GET['search']) && !isset($_GET['edit']) && !isset($_GET['pinned']) ? 'active' : ''; ?>"
               href="dashboard.php" role="tab">
                <i class="bi bi-list"></i> Alle Jobs
            </a>
        </li>

        <?php if (!$is_guest): ?>
        <!-- Pinned Jobs Tab - only for registered users -->
        <li class="nav-item" role="presentation">
            <a class="nav-link <?php echo isset($_GET['pinned']) ? 'active' : ''; ?>"
               href="dashboard.php?pinned=1" role="tab">
                <i class="bi bi-pin-fill"></i> Gepinnte Jobs
            </a>
        </li>

        <!-- Search Tab - only for registered users -->
        <li class="nav-item" role="presentation">
            <a class="nav-link <?php echo isset($_GET['search']) ? 'active' : ''; ?>"
               href="#searchTab" data-bs-toggle="tab" role="tab" id="searchTabBtn">
                <i class="bi bi-search"></i> Freie Suche
            </a>
        </li>

        <!-- User Filters - only for registered users -->
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

        <!-- Add New Filter Tab - only for registered users -->
        <li class="nav-item" role="presentation">
            <a class="nav-link" href="#newFilterTab" data-bs-toggle="tab" role="tab" id="newFilterTabBtn">
                <i class="bi bi-plus-circle"></i> Neuer Filter
            </a>
        </li>

        <!-- Edit Filter Tab (hidden, activated via JavaScript) - only for registered users -->
        <?php if ($edit_filter): ?>
            <li class="nav-item" role="presentation" id="editFilterTab">
                <a class="nav-link active" href="#editFilter" data-bs-toggle="tab" role="tab">
                    Filter bearbeiten
                </a>
            </li>
        <?php endif; ?>
        <?php endif; ?>
    </ul>

    <?php if (!$is_guest): ?>
    <!-- Tab Content - only for registered users -->
    <div class="tab-content" id="filterTabsContent">
        <!-- Search Tab Content -->
        <div class="tab-pane fade <?php echo isset($_GET['search']) ? 'show active' : ''; ?>" id="searchTab" role="tabpanel">
            <form action="dashboard.php" method="GET" class="mb-4">
                <div class="mb-3">
                    <label for="search" class="form-label">Suchbegriff</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="Suchbegriff eingeben..."
                           value="<?php echo htmlspecialchars($search_term ?? ''); ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Suchmodus</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="search_mode" id="softSearch" value="soft" <?php echo ($search_mode === 'soft') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="softSearch">
                            Soft Search (nur in Basis-Titel und Klassifikation)
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="search_mode" id="fullSearch" value="full" <?php echo ($search_mode === 'full') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="fullSearch">
                            Full-Text Search (in allen Textfeldern)
                        </label>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="date_from" class="form-label">Zeitraum von</label>
                        <input type="date" name="date_from" id="date_from" class="form-control"
                               value="<?php echo $custom_date_from ?? ''; ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="date_to" class="form-label">bis</label>
                        <input type="date" name="date_to" id="date_to" class="form-control"
                               value="<?php echo $custom_date_to ?? ''; ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Suchen</button>
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

                <div class="mb-3">
                    <label class="form-label">Zeitraum (optional)</label>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="filter_date_from" class="form-label">Von</label>
                            <input type="date" class="form-control" id="filter_date_from" name="filter_date_from">
                        </div>
                        <div class="col-md-6">
                            <label for="filter_date_to" class="form-label">Bis</label>
                            <input type="date" class="form-control" id="filter_date_to" name="filter_date_to">
                        </div>
                    </div>
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

                    <div class="mb-3">
                        <label class="form-label">Zeitraum (optional)</label>
                        <div class="row">
                            <div class="col-md-6">
                                <label for="edit_filter_date_from" class="form-label">Von</label>
                                <input type="date" class="form-control" id="edit_filter_date_from" name="filter_date_from"
                                       value="<?php echo $edit_filter['date_from'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_filter_date_to" class="form-label">Bis</label>
                                <input type="date" class="form-control" id="edit_filter_date_to" name="filter_date_to"
                                       value="<?php echo $edit_filter['date_to'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="submit" name="update_filter" class="btn btn-primary">Filter aktualisieren</button>
                        <a href="dashboard.php" class="btn btn-outline-secondary">Abbrechen</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
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

<div class="panel table-card">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h5 class="mb-1">Jobs (<?php echo $jobCount; ?> gefunden)</h5>
            <p class="text-muted small mb-0">Gepinnte Jobs bleiben immer sichtbar und werden zuerst sortiert.</p>
        </div>
        <?php if (!$is_guest): ?>
            <?php if ($active_filter): ?>
                <span class="badge bg-primary align-self-start">Aktiver Filter: <?php echo htmlspecialchars($active_filter['name']); ?></span>
            <?php elseif ($search_term): ?>
                <span class="badge bg-secondary align-self-start">Suche nach: <?php echo htmlspecialchars($search_term); ?></span>
            <?php elseif ($pinned_only): ?>
                <span class="badge bg-warning text-dark align-self-start"><i class="bi bi-pin"></i> Nur Pins</span>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if (!$is_guest): ?>
        <?php if ($active_filter): ?>
            <div class="alert alert-light border mb-3">
                <strong>Stichwörter:</strong>
                <ul class="keyword-list mt-2 mb-0">
                    <?php foreach (explode(', ', $active_filter['keywords']) as $keyword): ?>
                        <li><?php echo htmlspecialchars($keyword); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (!empty($active_filter['date_from']) || !empty($active_filter['date_to'])): ?>
                    <div class="mt-2">
                        <strong>Zeitraum:</strong>
                        <?php if (!empty($active_filter['date_from']) && !empty($active_filter['date_to'])): ?>
                            <?php echo date('d.m.Y', strtotime($active_filter['date_from'])); ?> bis <?php echo date('d.m.Y', strtotime($active_filter['date_to'])); ?>
                        <?php elseif (!empty($active_filter['date_from'])): ?>
                            ab <?php echo date('d.m.Y', strtotime($active_filter['date_from'])); ?>
                        <?php elseif (!empty($active_filter['date_to'])): ?>
                            bis <?php echo date('d.m.Y', strtotime($active_filter['date_to'])); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($search_term): ?>
            <div class="alert alert-light border mb-3 d-flex flex-wrap gap-2 align-items-center">
                <div>
                    <strong>Suche nach:</strong> "<?php echo htmlspecialchars($search_term); ?>"
                </div>
                <?php if ($search_mode === 'full'): ?>
                    <span class="badge bg-primary">Full-Text Search</span>
                <?php else: ?>
                    <span class="badge bg-secondary">Soft Search</span>
                <?php endif; ?>
                <?php if (!empty($custom_date_from) || !empty($custom_date_to)): ?>
                    <div>
                        <strong>Zeitraum:</strong>
                        <?php if (!empty($custom_date_from) && !empty($custom_date_to)): ?>
                            <?php echo date('d.m.Y', strtotime($custom_date_from)); ?> bis <?php echo date('d.m.Y', strtotime($custom_date_to)); ?>
                        <?php elseif (!empty($custom_date_from)): ?>
                            ab <?php echo date('d.m.Y', strtotime($custom_date_from)); ?>
                        <?php elseif (!empty($custom_date_to)): ?>
                            bis <?php echo date('d.m.Y', strtotime($custom_date_to)); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($pinned_only): ?>
            <div class="alert alert-warning border mb-3">
                <i class="bi bi-pin-fill"></i> <strong>Gepinnte Jobs:</strong> Hier werden nur deine angepinnten Jobs angezeigt.
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-light border mb-3">
            <i class="bi bi-calendar-date"></i> <strong><?php echo $time_info; ?></strong>
            <small class="text-muted d-block">Als Gast sehen Sie nur Jobs der letzten 7 Tage.</small>
        </div>
    <?php endif; ?>

    <?php if (empty($jobs)): ?>
        <p class="mb-0">Keine Jobs gefunden, die Ihren Filterkriterien entsprechen.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table id="jobsTable" class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="pin-status-column">Gepinnt</th> <!-- Hidden pin status column for sorting -->
                        <?php if (!$is_guest): ?>
                        <th style="width: 50px;"></th> <!-- Pin button column - only for registered users -->
                        <th style="width: 50px;"></th> <!-- Notes column - only for registered users -->
                        <?php endif; ?>
                        <th>Basis-Titel</th>
                        <th>Klassifikation</th>
                        <th>Erstellt am</th>
                        <th style="width: 120px;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job): ?>
                        <?php
                        $is_pinned = in_array($job['id'], $pinned_job_ids) || array_intersect(explode(',', $job['grouped_ids'] ?? ''), $pinned_job_ids);
                        $has_note = isset($job_notes[$job['id']]) || array_intersect(explode(',', $job['grouped_ids'] ?? ''), array_keys($job_notes));
                        ?>
                        <tr class="<?php echo $is_pinned ? 'pinned' : ''; ?>" data-job-id="<?php echo $job['id']; ?>">
                            <td class="pin-status-column"> <?php echo $is_pinned ? '1' : '0'; ?> </td>
                            <?php if (!$is_guest): ?>
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
                                  data-job-title="<?php echo htmlspecialchars($job['base_title']); ?>"></i>
                            </td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($job['base_title'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($job['group_classification'] ?? 'N/A'); ?></td>
                            <td data-sort="<?php echo strtotime($job['created_at'] ?? 0); ?>">
                                <?php if (!empty($job['created_at'])): ?>
                                    <?php echo date('d.m.Y', strtotime($job['created_at'])); ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="job_details.php?group_id=<?php echo $job['group_id']; ?>" class="btn btn-sm btn-info">
                                    <i class="bi bi-info-circle"></i> Details
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

<?php
// Include the footer
require_once 'dashboard_footer.php';
?>
