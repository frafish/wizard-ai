document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('wai-abilities-search');
    const categorySelect = document.getElementById('wai-abilities-category-filter');
    if (!searchInput || !categorySelect) return;

    function filterTable() {
        const nameFilter = searchInput.value.toLowerCase();
        const categoryFilter = categorySelect.value;
        const rows = document.querySelectorAll('#wai-abilities-table tbody tr.wai-ability-row');
        
        rows.forEach(row => {
            const nameEl = row.querySelector('.wai-ability-name');
            const categoryEl = row.querySelector('.wai-ability-category');
            if (nameEl && categoryEl) {
                const name = nameEl.textContent.toLowerCase();
                const category = categoryEl.textContent.toLowerCase();
                
                const matchName = name.includes(nameFilter);
                const matchCategory = categoryFilter === '' || category === categoryFilter;
                
                if (matchName && matchCategory) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
    }

    searchInput.addEventListener('input', filterTable);
    categorySelect.addEventListener('change', filterTable);
});
