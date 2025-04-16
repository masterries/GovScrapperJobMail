// Initialize DataTables
function initDataTable() {
    // Check if DataTable is already initialized
    if (!$.fn.DataTable.isDataTable('#jobsTable')) {
        // Determine if user is guest by checking table structure
        const isGuest = $('#jobsTable thead tr th').length < 7;
        
        // Configure columnDefs based on user type
        let columnDefs = [];
        
        if (isGuest) {
            // Guest view (no pin/note columns)
            columnDefs = [
                { "orderable": false, "targets": [4] }, // Disable sorting for actions column (letzte Spalte)
                { 
                    "type": "num", // Sortiere nach Unix-Timestamp
                    "targets": [3], // Erstellt am
                    "render": function(data, type, row, meta) {
                        if (type === 'sort') {
                            // Suche das data-sort Attribut im aktuellen Zellen-Element
                            var td = meta && meta.settings && meta.settings.aoData[meta.row] && meta.settings.aoData[meta.row].anCells[meta.col];
                            if (td && td.getAttribute) {
                                var sortVal = td.getAttribute('data-sort');
                                return sortVal ? parseInt(sortVal, 10) : 0;
                            }
                        }
                        return data;
                    }
                },
                { "visible": false, "targets": [0] }    // Hide the pin status column (used only for sorting)
            ];
        } else {
            // Regular user view (with pin/note columns)
            columnDefs = [
                { "orderable": false, "targets": [1, 2, 6] }, // Disable sorting for pin, notes and actions columns
                { 
                    "type": "num",  // Use numeric sorting like in guest view
                    "targets": [5], // Erstellt am column
                    "orderable": true,
                    "render": function(data, type, row, meta) {
                        if (type === 'sort') {
                            var td = meta && meta.settings && meta.settings.aoData[meta.row] && meta.settings.aoData[meta.row].anCells[meta.col];
                            if (td && td.getAttribute) {
                                var sortVal = td.getAttribute('data-sort');
                                return sortVal ? parseInt(sortVal, 10) : 0;
                            }
                        }
                        return data;
                    }
                },
                { "visible": false, "targets": [0] }  // Hide the pin status column (used only for sorting)
            ];
        }
        
        // Remove the date-de sorting function as we're using numeric timestamps
        if (!$.fn.dataTable.ext.type.search['num']) {
            $.fn.dataTable.ext.type.order['num-pre'] = function(data) {
                return typeof data === 'string' ?
                    (data === "" ? 0 : parseInt(data, 10)) :
                    (typeof data === 'number' ? data : 0);
            };
        }
        
        $('#jobsTable').DataTable({
            "order": [[0, "desc"], [isGuest ? 3 : 5, "desc"]], // Sort by is_pinned first, then created_at
            "orderFixed": { "pre": [0, "desc"] }, // Always keep pinned jobs on top regardless of user sorting
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/de-DE.json"
            },
            "columnDefs": columnDefs,
            "pageLength": 25, // Show 25 entries per page
            "stateSave": true // Save table state (sorting, pagination)
        });
    }
}

// Handle pin toggle via AJAX
function setupPinToggle() {
    $('.toggle-pin-btn').off('click').on('click', function() {
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
}

// Handle note modal
function setupNoteModal() {
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
    $('#saveNoteBtn').off('click').on('click', function() {
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
}

// JSON Import functionality
function setupJsonImport() {
    $('#parseJsonBtn').off('click').on('click', function() {
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
}

// Fix for tab behavior - ensure tab content is properly shown/hidden
function setupTabBehavior() {
    $('#searchTabBtn, #newFilterTabBtn').off('click').on('click', function(e) {
        e.preventDefault();
        $(this).tab('show');
    });
}

// On DOM ready
$(document).ready(function() {
    // Initialize all components
    initDataTable();
    setupPinToggle();
    setupNoteModal();
    setupJsonImport();
    setupTabBehavior();
    
    // Activate tab based on URL
    const searchParams = new URLSearchParams(window.location.search);
    if (searchParams.has('search')) {
        const searchTab = document.querySelector('#searchTabBtn');
        if (searchTab) {
            const tab = new bootstrap.Tab(searchTab);
            tab.show();
        }
    }
    // For new filter tab activation
    if (searchParams.has('edit')) {
        const editFilterTab = document.querySelector('#editFilter');
        if (editFilterTab) {
            $('#editFilterTab').removeClass('d-none');
        }
    }
});