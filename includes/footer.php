<?php
/**
 * User-facing Footer (CORRECTED for Sidebar Layout)
 * Closes the main content tags and includes the necessary JavaScript.
 */
?>
            </div> <!-- Closes the mx-auto container from header.php -->
        </main> <!-- Closes the main tag from header.php -->
    </div> <!-- Closes the main-content container from header.php -->

<script>
// This script handles the responsive sidebar toggle
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('user-sidebar');
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const sidebarCloseButton = document.getElementById('sidebar-close-button');

    if (sidebar && mobileMenuButton && sidebarCloseButton) {
        mobileMenuButton.addEventListener('click', () => {
            sidebar.classList.remove('-translate-x-full');
        });

        sidebarCloseButton.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
        });
    }
});
</script>

<!-- This line loads your other JavaScript for features like the search bar -->
<script src="assets/js/main.js"></script>

</body>
</html>
