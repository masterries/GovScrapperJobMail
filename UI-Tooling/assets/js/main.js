/**
 * Haupt-JavaScript-Datei für allgemeine Funktionen
 */
document.addEventListener('DOMContentLoaded', function() {
  // Tooltips initialisieren
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
  });
  
  // Aktive Navigation hervorheben
  const currentPath = window.location.pathname;
  document.querySelectorAll('.nav-link').forEach(function(link) {
    const linkPath = link.getAttribute('href');
    if (currentPath.includes(linkPath) && linkPath !== '/') {
      link.classList.add('active');
    }
  });
});