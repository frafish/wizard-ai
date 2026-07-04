document.addEventListener('DOMContentLoaded', function() {
    const nonce = waiSkillsData.nonce;
    const apiUrl = waiSkillsData.apiUrl;
    let skillsData = [];

    const listEl = document.getElementById('wai-skills-list');
    const editorEl = document.getElementById('wai-skill-editor');
    const oldIdInput = document.getElementById('wai-skill-old-id');
    const idInput = document.getElementById('wai-skill-id');
    const contentInput = document.getElementById('wai-skill-content');
    const saveBtn = document.getElementById('wai-save-skill-btn');
    const deleteBtn = document.getElementById('wai-delete-skill-btn');
    const addBtn = document.getElementById('wai-add-skill-btn');

    async function loadSkills() {
        listEl.innerHTML = `<li>${waiSkillsData.loading}</li>`;
        try {
            const res = await fetch(apiUrl, {
                headers: { 'X-WP-Nonce': nonce }
            });
            const data = await res.json();
            if (data.success) {
                skillsData = data.skills;
                renderList();
            } else {
                listEl.innerHTML = '<li>Error loading skills.</li>';
            }
        } catch (e) {
            listEl.innerHTML = '<li>Error loading skills.</li>';
        }
    }

    function renderList() {
        listEl.innerHTML = '';
        if (skillsData.length === 0) {
            listEl.innerHTML = `<li><em style="color:#666;">${waiSkillsData.noSkills}</em></li>`;
        } else {
            skillsData.forEach(skill => {
                const li = document.createElement('li');
                li.style.marginBottom = '5px';
                const a = document.createElement('a');
                a.href = '#';
                a.innerText = skill.id;
                a.style.textDecoration = 'none';
                a.style.display = 'block';
                a.style.padding = '8px';
                a.style.background = '#f0f0f1';
                a.style.borderRadius = '3px';
                a.style.color = '#2271b1';
                a.addEventListener('click', (e) => {
                    e.preventDefault();
                    openEditor(skill.id, skill.content, skill.is_builtin);
                });
                li.appendChild(a);
                listEl.appendChild(li);
            });
        }
    }

    function openEditor(id, content, is_builtin = false) {
        editorEl.style.display = 'block';
        oldIdInput.value = id;
        idInput.value = id;
        contentInput.value = content;
        
        if (is_builtin) {
            idInput.readOnly = true;
            contentInput.readOnly = true;
            saveBtn.style.display = 'none';
            deleteBtn.style.display = 'none';
        } else {
            idInput.readOnly = false;
            contentInput.readOnly = false;
            saveBtn.style.display = 'inline-block';
            deleteBtn.style.display = id ? 'inline-block' : 'none';
        }
    }

    addBtn.addEventListener('click', () => {
        openEditor('', '');
        idInput.focus();
    });

    saveBtn.addEventListener('click', async () => {
        saveBtn.disabled = true;
        saveBtn.innerText = waiSkillsData.saving;
        
        try {
            const res = await fetch(apiUrl, {
                method: 'POST',
                headers: { 
                    'X-WP-Nonce': nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    old_id: oldIdInput.value,
                    id: idInput.value,
                    content: contentInput.value
                })
            });
            const data = await res.json();
            if (data.success) {
                await loadSkills();
                openEditor(idInput.value.includes('.') ? idInput.value : idInput.value + '.md', contentInput.value);
                alert(waiSkillsData.saveSuccess);
            } else {
                alert('Error: ' + data.message);
            }
        } catch (e) {
            alert('Error saving skill.');
        }
        
        saveBtn.disabled = false;
        saveBtn.innerText = waiSkillsData.saveSkill;
    });

    deleteBtn.addEventListener('click', async () => {
        if (!confirm(waiSkillsData.confirmDelete)) return;
        
        deleteBtn.disabled = true;
        try {
            const res = await fetch(apiUrl, {
                method: 'DELETE',
                headers: { 
                    'X-WP-Nonce': nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: oldIdInput.value
                })
            });
            const data = await res.json();
            if (data.success) {
                editorEl.style.display = 'none';
                await loadSkills();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (e) {
            alert('Error deleting skill.');
        }
        deleteBtn.disabled = false;
    });

    loadSkills();
});
