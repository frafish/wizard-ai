document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('wai-models-search');
    const statusSelect = document.getElementById('wai-status-filter');
    const selectAll = document.getElementById('wai-models-select-all');
    
    function getSelectedValues(id) {
        const inputs = document.querySelectorAll(`#${id} input[type="checkbox"]:checked`);
        return Array.from(inputs).map(i => i.value);
    }

    function updateFilterCounts() {
        const rows = Array.from(document.querySelectorAll('#wai-models-table tbody tr.wai-model-row'));
        const search = searchInput ? searchInput.value.toLowerCase() : '';
        
        const filters = [
            { id: 'wai-provider-filter', name: 'provider', isDropdown: true },
            { id: 'wai-family-filter', name: 'family', isDropdown: true },
            { id: 'wai-capability-filter', name: 'capability', isDropdown: true },
            { id: 'wai-status-filter', name: 'status', isDropdown: false, el: statusSelect }
        ];
        
        filters.forEach(sel => {
            let elements = [];
            if (sel.isDropdown) {
                elements = Array.from(document.querySelectorAll(`#${sel.id} .label-text`));
            } else {
                if (!sel.el) return;
                elements = Array.from(sel.el.querySelectorAll('option')).filter(o => o.value !== '');
            }
            
            elements.forEach(el => {
                const val = sel.isDropdown ? el.previousElementSibling.value : el.value;
                if (val === '') return;
                
                let count = 0;
                rows.forEach(row => {
                    const nameData = row.querySelector('.wai-model-name').getAttribute('data-search');
                    const provData = row.querySelector('.wai-model-provider').getAttribute('data-filter');
                    const famData = row.querySelector('.wai-model-family').getAttribute('data-filter');
                    const capData = row.querySelector('.wai-model-capabilities').getAttribute('data-filter');
                    const isChecked = row.querySelector('.wai-model-checkbox').checked;
                    
                    const testProv = sel.name === 'provider' ? [val] : getSelectedValues('wai-provider-filter');
                    const testFam = sel.name === 'family' ? [val] : getSelectedValues('wai-family-filter');
                    const testCap = sel.name === 'capability' ? [val] : getSelectedValues('wai-capability-filter');
                    const testStatus = sel.name === 'status' ? val : (statusSelect ? statusSelect.value : '');
                    
                    let show = true;
                    if (search && !nameData.includes(search)) show = false;
                    if (testProv.length > 0 && !testProv.includes(provData)) show = false;
                    if (testFam.length > 0 && !testFam.includes(famData)) show = false;
                    if (testCap.length > 0) {
                        const rowCaps = capData.split(',');
                        const hasAny = testCap.some(c => rowCaps.includes(c));
                        if (!hasAny) show = false;
                    }
                    if (testStatus !== '') {
                        if (testStatus === '1' && !isChecked) show = false;
                        if (testStatus === '0' && isChecked) show = false;
                    }
                    
                    if (show) count++;
                });
                
                const origLabel = el.getAttribute('data-label');
                if (origLabel) {
                    el.textContent = `${origLabel} (${count})`;
                }
            });
        });
    }

    function filterTable() {
        const search = searchInput.value.toLowerCase();
        const providers = getSelectedValues('wai-provider-filter');
        const families = getSelectedValues('wai-family-filter');
        const capabilities = getSelectedValues('wai-capability-filter');
        const status = statusSelect.value;
        
        const rows = document.querySelectorAll('#wai-models-table tbody tr.wai-model-row');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const nameData = row.querySelector('.wai-model-name').getAttribute('data-search');
            const provData = row.querySelector('.wai-model-provider').getAttribute('data-filter');
            const famData = row.querySelector('.wai-model-family').getAttribute('data-filter');
            const capData = row.querySelector('.wai-model-capabilities').getAttribute('data-filter');
            const isChecked = row.querySelector('.wai-model-checkbox').checked;
            
            let show = true;
            
            if (search && !nameData.includes(search)) show = false;
            if (providers.length > 0 && !providers.includes(provData)) show = false;
            if (families.length > 0 && !families.includes(famData)) show = false;
            if (capabilities.length > 0) {
                const rowCaps = capData.split(',');
                const hasAny = capabilities.some(c => rowCaps.includes(c));
                if (!hasAny) show = false;
            }
            if (status !== '') {
                if (status === '1' && !isChecked) show = false;
                if (status === '0' && isChecked) show = false;
            }
            
            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });
        
        const totalModelsEl = document.getElementById('wai-total-models-count');
        if (totalModelsEl) {
            totalModelsEl.textContent = visibleCount;
        }
        
        updateFilterCounts();
    }

    if (searchInput) searchInput.addEventListener('input', filterTable);
    if (statusSelect) statusSelect.addEventListener('change', filterTable);
    
    document.querySelectorAll('.wai-dropdown-check-list input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', filterTable);
    });
    
    document.querySelectorAll('.wai-dropdown-check-list .anchor').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const parent = this.parentElement;
            const wasVisible = parent.classList.contains('visible');
            document.querySelectorAll('.wai-dropdown-check-list').forEach(el => el.classList.remove('visible'));
            if (!wasVisible) {
                parent.classList.add('visible');
            }
        });
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.wai-dropdown-check-list')) {
            document.querySelectorAll('.wai-dropdown-check-list').forEach(el => el.classList.remove('visible'));
        }
    });
    
    // Initialize counts on page load
    filterTable();

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('#wai-models-table tbody tr.wai-model-row:not([style*="display: none"]) .wai-model-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
        });
    }

    const saveBtn = document.getElementById('wai-save-settings');
    const triggerBtn = document.getElementById('wai-trigger-update');
    const spinner = document.getElementById('wai-settings-spinner');

    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            spinner.classList.add('is-active');
            saveBtn.disabled = true;
            
            const enabled_models = [];
            document.querySelectorAll('.wai-model-checkbox:checked').forEach(cb => {
                enabled_models.push(cb.value);
            });
            
            const cron_enabled = document.getElementById('wai-cron-enabled').checked;

            fetch('/?rest_route=/wizard-ai/v1/ai-models/settings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify({
                    enabled_models: enabled_models,
                    cron_enabled: cron_enabled
                })
            })
            .then(res => res.json())
            .then(res => {
                spinner.classList.remove('is-active');
                saveBtn.disabled = false;
                alert('Settings saved successfully.');
            })
            .catch(err => {
                spinner.classList.remove('is-active');
                saveBtn.disabled = false;
                alert('An error occurred.');
            });
        });
    }

    if (triggerBtn) {
        triggerBtn.addEventListener('click', function() {
            spinner.classList.add('is-active');
            triggerBtn.disabled = true;
            
            fetch('/?rest_route=/wizard-ai/v1/ai-models&refresh=1', {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce
                }
            })
            .then(res => res.json())
            .then(res => {
                spinner.classList.remove('is-active');
                triggerBtn.disabled = false;
                alert('Models updated successfully. Reloading page...');
                window.location.reload();
            })
            .catch(err => {
                spinner.classList.remove('is-active');
                triggerBtn.disabled = false;
                alert('An error occurred while updating models.');
            });
        });
    }
});
