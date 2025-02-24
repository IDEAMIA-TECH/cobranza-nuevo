// Función para manejar el estado del sidebar en localStorage
function saveSidebarState(isCollapsed) {
    localStorage.setItem('sidebarCollapsed', isCollapsed);
}

// Función para obtener el estado guardado del sidebar
function getSavedSidebarState() {
    return localStorage.getItem('sidebarCollapsed') === 'true';
}

document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('main');
    
    // Aplicar el estado guardado al cargar la página
    if (getSavedSidebarState()) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('sidebar-collapsed');
    }
    
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('sidebar-collapsed');
        
        // Guardar el estado actual
        saveSidebarState(sidebar.classList.contains('collapsed'));
    });
}); 