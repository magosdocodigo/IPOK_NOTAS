<?php
/**
 * Toggle Sidebar Script Include
 * Use no final de cada página do admin: include '../includes/sidebar_toggle.php';
 */
?>

<script>
    // Toggle sidebar
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        if (sidebar && mainContent) {
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('sidebar-hidden');
        }
    }

    // Fechar sidebar ao clicar em um link (mobile)
    document.querySelectorAll('.sidebar-menu .menu-item').forEach(link => {
        link.addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar && sidebar.classList.contains('hidden')) {
                sidebar.classList.remove('hidden');
                document.querySelector('.main-content').classList.remove('sidebar-hidden');
            }
        });
    });
</script>
