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
    
    // Verificar que los elementos existan
    if (!sidebarToggle || !sidebar || !mainContent) {
        console.warn('No se encontraron elementos necesarios para el sidebar');
        return;
    }
    
    // Crear overlay solo si no existe
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }
    
    // Aplicar el estado guardado al cargar la página
    if (getSavedSidebarState() && window.innerWidth > 768) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('sidebar-collapsed');
    }
    
    // Función para cerrar el sidebar en móvil
    function closeMobileSidebar() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    sidebarToggle.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        } else {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('sidebar-collapsed');
            saveSidebarState(sidebar.classList.contains('collapsed'));
        }
    });
    
    // Agregar event listener al overlay solo si existe
    if (overlay) {
        overlay.addEventListener('click', closeMobileSidebar);
    }
    
    // Manejar cambios de tamaño de ventana
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeMobileSidebar();
            if (getSavedSidebarState()) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('sidebar-collapsed');
            }
        }
    });
    
    // Soporte para gestos táctiles
    let touchStartX = 0;
    let touchEndX = 0;
    
    document.addEventListener('touchstart', e => {
        touchStartX = e.changedTouches[0].screenX;
    }, false);
    
    document.addEventListener('touchend', e => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, false);
    
    function handleSwipe() {
        if (window.innerWidth <= 768) {
            const swipeDistance = touchEndX - touchStartX;
            if (Math.abs(swipeDistance) > 50) { // Mínimo de 50px para considerar como swipe
                if (swipeDistance > 0) { // Swipe derecha
                    sidebar.classList.add('active');
                    overlay.classList.add('active');
                } else { // Swipe izquierda
                    closeMobileSidebar();
                }
            }
        }
    }
}); 