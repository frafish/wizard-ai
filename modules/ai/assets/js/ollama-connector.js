document.addEventListener('DOMContentLoaded', function() {
    const observer = new MutationObserver(function(mutations) {
        if (document.getElementById('wai-injected-ollama-url')) return;

        // Find the container by looking for the unique description text
        let ollamaContainer = null;
        const allElements = document.querySelectorAll('*');
        for (let i = 0; i < allElements.length; i++) {
            if (allElements[i].childNodes.length === 1 && allElements[i].childNodes[0].nodeType === 3) {
                if (allElements[i].textContent.includes('Text generation with Ollama')) {
                    ollamaContainer = allElements[i].closest('.components-panel__body, .wpai-connector-item, .components-surface, form, section') || allElements[i].parentElement.parentElement;
                    break;
                }
            }
        }
        
        if (ollamaContainer) {
            // Find the API Key input within this container
            const inputs = ollamaContainer.querySelectorAll('input[type="text"], input[type="password"]');
            if (inputs.length > 0) {
                const apiKeyInput = inputs[0];
                const apiKeyInputContainer = apiKeyInput.closest('.components-base-control, .components-input-control') || apiKeyInput.parentElement;
                
                if (apiKeyInputContainer && !document.getElementById('wai-injected-ollama-url')) {
                    const urlContainer = document.createElement('div');
                    urlContainer.className = apiKeyInputContainer.className; // Copy classes from the API key container for styling
                    urlContainer.style.marginTop = '15px';
                    urlContainer.style.marginBottom = '15px';
                    urlContainer.innerHTML = `
                        <div class="components-base-control__field">
                            <label class="components-base-control__label" for="wai-injected-ollama-url" style="display:block; margin-bottom:8px; text-transform:uppercase; font-size:11px; font-weight:500; color:#1e1e1e;">OLLAMA BASE URL</label>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <input class="components-text-control__input" type="text" id="wai-injected-ollama-url" value="${waiOllamaConnectorData.baseUrl}" placeholder="http://localhost:11434" style="width: 100%; padding: 0 16px; min-height: 40px; border: 1px solid #757575; border-radius: 2px;">
                                <button type="button" id="wai-save-ollama-url" class="components-button is-primary" style="min-height:40px; padding: 0 16px; background: #3858E9; color: white; border: none; border-radius: 2px; cursor: pointer;">Save URL</button>
                                <span id="wai-ollama-url-spinner" class="spinner" style="float:none; margin:0;"></span>
                            </div>
                            <p class="components-base-control__help" style="margin-top:8px; font-size:12px; color:#757575;">Leave empty or use http://localhost:11434 for local models. Use https://ollama.com for Cloud models.</p>
                        </div>
                    `;
                    
                    apiKeyInputContainer.parentNode.insertBefore(urlContainer, apiKeyInputContainer.nextSibling);
                    
                    document.getElementById('wai-save-ollama-url').addEventListener('click', function(e) {
                        e.preventDefault();
                        const url = document.getElementById('wai-injected-ollama-url').value;
                        const spinner = document.getElementById('wai-ollama-url-spinner');
                        spinner.classList.add('is-active');
                        
                        fetch('/?rest_route=/wizard-ai/v1/ai-models/settings', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': waiOllamaConnectorData.nonce
                            },
                            body: JSON.stringify({
                                ollama_base_url: url
                            })
                        })
                        .then(res => res.json())
                        .then(res => {
                            spinner.classList.remove('is-active');
                            const btn = document.getElementById('wai-save-ollama-url');
                            const originalText = btn.textContent;
                            btn.textContent = 'Saved!';
                            setTimeout(() => { btn.textContent = originalText; }, 2000);
                        })
                        .catch(err => {
                            spinner.classList.remove('is-active');
                            alert('Failed to save Ollama URL');
                        });
                    });
                }
            }
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });
});
