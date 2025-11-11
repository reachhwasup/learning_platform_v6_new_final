/*
File: /assets/js/main.js (FINAL VERSION)
*/

document.addEventListener('DOMContentLoaded', () => {
    // --- Dashboard: View Switcher & Search ---
    const gridViewBtn = document.getElementById('grid-view-btn');
    const listViewBtn = document.getElementById('list-view-btn');
    const gridContainer = document.getElementById('modules-grid-container');
    const listContainer = document.getElementById('modules-list-container');
    const searchInput = document.getElementById('module-search');
    const noResultsMessage = document.getElementById('no-results-message');

    // Function to set the view
    function setView(view) {
        if (view === 'list') {
            if(gridContainer) gridContainer.classList.add('hidden');
            if(listContainer) listContainer.classList.remove('hidden');
            if(listViewBtn) listViewBtn.classList.add('active');
            if(gridViewBtn) gridViewBtn.classList.remove('active');
            localStorage.setItem('module_view', 'list');
        } else { // Default to grid view
            if(listContainer) listContainer.classList.add('hidden');
            if(gridContainer) gridContainer.classList.remove('hidden');
            if(gridViewBtn) gridViewBtn.classList.add('active');
            if(listViewBtn) listViewBtn.classList.remove('active');
            localStorage.setItem('module_view', 'grid');
        }
    }

    // Event Listeners for buttons
    if (gridViewBtn && listViewBtn && gridContainer && listContainer) {
        gridViewBtn.addEventListener('click', () => setView('grid'));
        listViewBtn.addEventListener('click', () => setView('list'));

        // Check for saved preference on page load
        const savedView = localStorage.getItem('module_view');
        if (savedView) {
            setView(savedView);
        }
    }

    // Search functionality
    if (searchInput) {
        const allCards = document.querySelectorAll('.module-card'); // Grid view cards
        const allRows = document.querySelectorAll('.module-row');   // List view rows

        searchInput.addEventListener('keyup', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            let visibleCountCards = 0;
            let visibleCountRows = 0;

            allCards.forEach(card => {
                const title = card.dataset.title || '';
                if (title.includes(searchTerm)) {
                    card.classList.remove('hidden');
                    visibleCountCards++;
                } else {
                    card.classList.add('hidden');
                }
            });

            allRows.forEach(row => {
                const title = row.dataset.title || '';
                if (title.includes(searchTerm)) {
                    row.classList.remove('hidden');
                    visibleCountRows++;
                } else {
                    row.classList.add('hidden');
                }
            });

            if (noResultsMessage) {
                // Determine which view is active to check the correct count
                const isListView = listContainer && !listContainer.classList.contains('hidden');
                const visibleCount = isListView ? visibleCountRows : visibleCountCards;

                if (visibleCount === 0) {
                    noResultsMessage.classList.remove('hidden');
                } else {
                    noResultsMessage.classList.add('hidden');
                }
            }
        });
    }
});
