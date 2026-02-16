$(function() {
    setupPinToggle();
    setupNoteModal();
});

function setupPinToggle() {
    $('.toggle-pin-btn').off('click').on('click', function() {
        const jobId = $(this).data('job-id');
        const btn = $(this);
        const icon = btn.find('i');
        const card = btn.closest('.job-card');
        const isPinned = icon.hasClass('bi-pin-fill');

        $.ajax({
            type: 'POST',
            url: 'job_details.php' + window.location.search,
            data: {
                job_id: jobId,
                toggle_pin: 1,
                ajax: 1
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (isPinned) {
                        icon.removeClass('bi-pin-fill text-warning').addClass('bi-pin');
                        card.removeClass('pinned');
                    } else {
                        icon.removeClass('bi-pin').addClass('bi-pin-fill text-warning');
                        card.addClass('pinned');
                    }
                }
            }
        });
    });
}

function setupNoteModal() {
    $('#noteModal').on('show.bs.modal', function(event) {
        const button = $(event.relatedTarget);
        const jobId = button.data('job-id');
        const jobTitle = button.data('job-title');
        const modal = $(this);

        modal.find('#noteJobId').val(jobId);
        modal.find('#noteJobTitle').text(jobTitle);

        $.getJSON('job_details.php' + window.location.search + '&get_note=1&job_id=' + jobId, function(data) {
            modal.find('#noteText').val(data.note);
        });
    });

    $('#saveNoteBtn').off('click').on('click', function() {
        const jobId = $('#noteJobId').val();
        const note = $('#noteText').val();

        $.ajax({
            type: 'POST',
            url: 'job_details.php' + window.location.search,
            data: {
                job_id: jobId,
                note: note,
                save_note: 1,
                ajax: 1
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const icon = $('.note-icon[data-job-id="' + jobId + '"]');
                    if (note.trim() !== '') {
                        icon.addClass('has-note');
                    } else {
                        icon.removeClass('has-note');
                    }
                    $('#noteModal').modal('hide');
                }
            }
        });
    });
}
