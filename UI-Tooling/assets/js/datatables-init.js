/**
 * DataTables-Initialisierung und Konfiguration
 */
$(document).ready(function() {
    // Initialize DataTables
    var jobTable = $('#jobTable').DataTable({
        "order": [[0, "desc"]],
        "pageLength": 25,
        "language": {
            "lengthMenu": "Zeige _MENU_ Einträge pro Seite",
            "zeroRecords": "Keine Ergebnisse gefunden",
            "info": "Seite _PAGE_ von _PAGES_",
            "infoEmpty": "Keine verfügbaren Einträge",
            "infoFiltered": "(gefiltert aus _MAX_ Gesamteinträgen)",
            "search": "Suchen:",
            "paginate": {
                "first": "Erste",
                "last": "Letzte",
                "next": "Weiter",
                "previous": "Zurück"
            }
        }
    });
});