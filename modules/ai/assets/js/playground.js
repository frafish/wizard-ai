function initPlaygroundSpeech() {
    // Speech to Text for AI Playground
    const speechBtn = document.getElementById('wbai-speech-to-text');
    const promptEl = document.getElementById('wbai-playground-prompt');
    const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition || window.mozSpeechRecognition || window.msSpeechRecognition;

    if (speechBtn && promptEl && SpeechRec) {
        speechBtn.style.display = 'block';
        const recognition = new SpeechRec();
        recognition.continuous = false;
        recognition.interimResults = false;

        let isRecording = false;

        recognition.onstart = function () {
            isRecording = true;
            speechBtn.textContent = '🔴';
        };

        recognition.onresult = function (event) {
            const transcript = event.results[0][0].transcript;
            if (promptEl.value) {
                promptEl.value += ' ' + transcript;
            } else {
                promptEl.value = transcript;
            }
        };

        recognition.onerror = function (event) {
            console.error('Speech recognition error', event.error);
            isRecording = false;
            speechBtn.textContent = '🎤';

            if (event.error === 'not-allowed') {
                alert('Microphone access was denied. Please allow microphone access to use speech-to-text.');
            } else if (navigator.userAgent.toLowerCase().includes('firefox')) {
                alert('Speech recognition failed. Note: Firefox desktop often requires third-party extensions or specific OS setups for speech recognition to function even when enabled in about:config.');
            } else {
                alert('Speech recognition error: ' + event.error);
            }
        };

        recognition.onend = function () {
            isRecording = false;
            speechBtn.textContent = '🎤';
        };

        speechBtn.addEventListener('click', function () {
            if (isRecording) {
                recognition.stop();
            } else {
                try {
                    recognition.start();
                } catch (e) {
                    console.error('Failed to start speech recognition', e);
                    alert('Failed to start speech recognition. Your browser might not fully support this feature.');
                }
            }
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPlaygroundSpeech);
} else {
    initPlaygroundSpeech();
}

document.addEventListener('DOMContentLoaded', function () {
    function escapeHtml(unsafe) {
        return (unsafe || '').toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }



    if (typeof jQuery !== 'undefined' && jQuery.fn.resizable) {
        const chatWrapper = jQuery('#wbai-playground-chat-wrapper');
        if (chatWrapper.length) {
            chatWrapper.resizable({
                handles: 'se, s'
            });

            chatWrapper.on('click', '.toggle-distraction-free', function () {
                chatWrapper.toggleClass('distraction-free');
                jQuery(this).toggleClass('dashicons-fullscreen-alt');
                jQuery(this).toggleClass('dashicons-fullscreen-exit-alt');
                return false;
            });
        }
    }


    // AI Playground chat
    const sendBtn = document.getElementById('wbai-playground-send');
    const modelSelect = document.getElementById('wbai-playground-model');
    const fallbackContainer = document.getElementById('wbai-fallback-models-container');
    const fallbackModelsCheckbox = document.getElementById('wbai-fallback-models');

    if (modelSelect) {
        modelSelect.addEventListener('change', function () {
            if (fallbackContainer) {
                fallbackContainer.style.display = this.value === '' ? 'inline-block' : 'none';
            }
        });

        if (fallbackContainer) {
            fallbackContainer.style.display = modelSelect.value === '' ? 'inline-block' : 'none';
        }

        fetch(window.wbaiSettings.restUrl.replace('ai-chat', 'ai-models'), {
            headers: { 'X-WP-Nonce': window.wbaiSettings.nonceRest },
            cache: 'no-cache'
        })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.models) {
                    if (typeof data.models === 'object' && Object.keys(data.models).length > 0) {
                        const firstVal = Object.values(data.models)[0];
                        if (typeof firstVal === 'object') {
                            Object.entries(data.models).forEach(([groupName, groupModels]) => {
                                const optgroup = document.createElement('optgroup');
                                optgroup.label = groupName;
                                Object.entries(groupModels).forEach(([id, name]) => {
                                    const opt = document.createElement('option');
                                    opt.value = id;
                                    opt.textContent = name;
                                    optgroup.appendChild(opt);
                                });
                                modelSelect.appendChild(optgroup);
                            });
                        } else {
                            Object.entries(data.models).forEach(([id, name]) => {
                                const opt = document.createElement('option');
                                opt.value = id;
                                opt.textContent = name;
                                modelSelect.appendChild(opt);
                            });
                        }
                    }

                    if (window.wbaiSettings.preferredModel) {
                        const exists = Array.from(modelSelect.options).some(opt => opt.value === window.wbaiSettings.preferredModel);
                        if (exists) {
                            modelSelect.value = window.wbaiSettings.preferredModel;
                            if (fallbackContainer) {
                                fallbackContainer.style.display = 'none';
                            }
                        }
                    }

                    if (typeof jQuery !== 'undefined') {
                        if (jQuery.fn.selectWoo) {
                            jQuery(modelSelect).selectWoo({
                                width: '350px'
                            });
                            jQuery(modelSelect).on('select2:select', function (e) {
                                modelSelect.dispatchEvent(new Event('change'));
                            });
                        } else if (jQuery.fn.select2) {
                            jQuery(modelSelect).select2({
                                width: '350px'
                            });
                            jQuery(modelSelect).on('select2:select', function (e) {
                                modelSelect.dispatchEvent(new Event('change'));
                            });
                        }
                    }
                }
            })
            .catch(e => console.error('Failed to load AI models', e));
    }

    // Session prompts import/export
    window.wbaiSessionPrompts = window.wbaiSessionPrompts || [];
    window.wbaiPromptQueue = window.wbaiPromptQueue || [];
    window.wbaiCurrentConversationId = null;

    const exportBtn = document.getElementById('wbai-export-session');
    const importBtn = document.getElementById('wbai-import-session');
    const importFile = document.getElementById('wbai-import-file');
    const toggleSafeModeBtn = document.getElementById('wbai-toggle-safe-mode');
    let aiEnforceSafeMode = toggleSafeModeBtn && toggleSafeModeBtn.dataset.active === '1';

    if (aiEnforceSafeMode) {
        setTimeout(async () => {
            try {
                const testUrl = window.wbaiSettings.restUrl.replace('ai-chat', 'ai-models');
                const backendRes = await fetch(testUrl, {
                    headers: { 'X-WP-Nonce': window.wbaiSettings.nonceRest },
                    cache: 'no-cache'
                });
                const frontendRes = await fetch(window.wbaiSettings.homeUrl);

                if (backendRes.ok && backendRes.status === 200 && frontendRes.ok && frontendRes.status === 200) {
                    aiEnforceSafeMode = false;
                    if (toggleSafeModeBtn) {
                        toggleSafeModeBtn.classList.remove('wbai-safe-mode-active');
                        toggleSafeModeBtn.dataset.active = "0";
                    }
                    if (document.getElementById('wbai-safemode-status')) {
                        document.getElementById('wbai-safemode-status').innerText = 'Native (All Plugins Active)';
                    }

                    await fetch(window.wbaiSettings.restUrl.replace('ai-chat', 'toggle-safe-mode') + '?wbai_enforce_safe_mode=1', {
                        method: 'POST',
                        headers: { 'X-WP-Nonce': window.wbaiSettings.nonceRest, 'Content-Type': 'application/json' },
                        body: JSON.stringify({ force: 'disable' })
                    });

                    // We don't have addMessage in scope here directly unless it's defined globally, but it's not.
                    // Instead, append directly.
                    const chatEl = document.getElementById('wbai-playground-chat');
                    if (chatEl) {
                        chatEl.insertAdjacentHTML('beforeend', '<div class="wbai-msg-tool-result wbai-success" style="margin-bottom: 10px; padding: 10px; background: #eaf5ea; border-left: 4px solid #46b450; font-size: 13px;"><strong>System:</strong> Initial 500 error check passed. Safe Mode has been automatically disabled.</div>');
                        chatEl.scrollTop = chatEl.scrollHeight;
                    }
                }
            } catch (e) { }
        }, 1500);
    }

    if (toggleSafeModeBtn) {
        toggleSafeModeBtn.addEventListener('click', async function () {
            const originalTitle = toggleSafeModeBtn.title;
            toggleSafeModeBtn.title = 'Toggling...';
            toggleSafeModeBtn.style.opacity = '0.7';
            try {
                const isCurrentlyActive = toggleSafeModeBtn.dataset.active === '1';
                const actionForce = isCurrentlyActive ? 'disable' : 'enable';

                const response = await fetch(window.wbaiSettings.restUrl.replace('ai-chat', 'toggle-safe-mode') + '?wbai_enforce_safe_mode=1', {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': window.wbaiSettings.nonceRest, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ force: actionForce })
                });
                const data = await response.json();
                if (data.success) {
                    if (data.safe_mode) {
                        aiEnforceSafeMode = true;
                        toggleSafeModeBtn.classList.add('wbai-safe-mode-active');
                        toggleSafeModeBtn.dataset.active = "1";
                        document.getElementById('wbai-safemode-status').innerText = 'Strict Safe Mode Enforced (.wb_ai_safe)';
                    } else {
                        aiEnforceSafeMode = false;
                        toggleSafeModeBtn.classList.remove('wbai-safe-mode-active');
                        toggleSafeModeBtn.dataset.active = "0";
                        document.getElementById('wbai-safemode-status').innerText = 'Native (All Plugins Active)';
                    }
                }
            } catch (err) {
                console.error('Error toggling safe mode', err);
            }
            toggleSafeModeBtn.title = originalTitle;
            toggleSafeModeBtn.style.opacity = '1';
        });
    }

    function saveChatState() {
        const chatEl = document.getElementById('wbai-playground-chat');
        if (chatEl && window.wbaiSessionPrompts.length > 0) {
            const state = {
                is_full_state: true,
                html: chatEl.innerHTML,
                conversation_id: window.wbaiCurrentConversationId,
                session_prompts: window.wbaiSessionPrompts
            };
            localStorage.setItem('wbai_chat_state', JSON.stringify(state));
        }
    }

    function restoreChatState(state) {
        if (state && state.is_full_state) {
            const chatEl = document.getElementById('wbai-playground-chat');
            if (chatEl && state.html) {
                chatEl.innerHTML = state.html;
            }
            if (state.conversation_id) {
                window.wbaiCurrentConversationId = state.conversation_id;
            }
            if (state.session_prompts) {
                window.wbaiSessionPrompts = state.session_prompts;
            }
            // Re-initialize any CodeMirror instances if needed, or they remain as static code blocks.
            // In most cases, previous messages don't need re-execution.
        }
    }

    // Manual restore logic
    const restoreBtn = document.getElementById('wbai-restore-last-session');
    if (restoreBtn) {
        try {
            const saved = localStorage.getItem('wbai_chat_state');
            if (saved) {
                restoreBtn.style.display = 'inline-block';
                restoreBtn.addEventListener('click', function () {
                    restoreChatState(JSON.parse(saved));
                    this.style.display = 'none';
                    alert('Session restored successfully.');
                });
            }
        } catch (e) {
            console.error('Failed to check saved chat state', e);
        }
    }

    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            if (window.wbaiSessionPrompts.length === 0) {
                alert('No prompts submitted in this session yet.');
                return;
            }
            const chatEl = document.getElementById('wbai-playground-chat');
            const state = {
                is_full_state: true,
                html: chatEl ? chatEl.innerHTML : '',
                conversation_id: window.wbaiCurrentConversationId,
                session_prompts: window.wbaiSessionPrompts
            };
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(state, null, 2));
            const downloadAnchorNode = document.createElement('a');
            downloadAnchorNode.setAttribute("href", dataStr);
            downloadAnchorNode.setAttribute("download", "wbai_session_" + Date.now() + ".json");
            document.body.appendChild(downloadAnchorNode);
            downloadAnchorNode.click();
            downloadAnchorNode.remove();
        });
    }

    if (importBtn && importFile) {
        importBtn.addEventListener('click', function () {
            importFile.click();
        });

        importFile.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function (e) {
                try {
                    const data = JSON.parse(e.target.result);
                    if (data && data.is_full_state) {
                        restoreChatState(data);
                        localStorage.setItem('wbai_chat_state', JSON.stringify(data));
                        alert('Session restored successfully.');
                    } else if (Array.isArray(data) && data.length > 0) {
                        window.wbaiPromptQueue = data;
                        checkPromptQueue();
                    } else {
                        alert('No valid session or prompts found in file.');
                    }
                } catch (err) {
                    alert('Invalid JSON format.');
                }
            };
            reader.readAsText(file);
            importFile.value = ''; // reset
        });
    }

    function checkPromptQueue() {
        if (window.wbaiPromptQueue.length > 0 && (!sendBtn || !sendBtn.disabled)) {
            const nextPrompt = window.wbaiPromptQueue.shift();
            const promptEl = document.getElementById('wbai-playground-prompt');
            if (promptEl && sendBtn) {
                promptEl.value = nextPrompt;
                sendBtn.click();
            }
        }
    }

    if (sendBtn) {
        const promptElMain = document.getElementById('wbai-playground-prompt');
        if (promptElMain) {
            promptElMain.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    if (!sendBtn.disabled) {
                        sendBtn.click();
                    }
                }
            });
        }

        sendBtn.addEventListener('click', async function () {
            const promptEl = document.getElementById('wbai-playground-prompt');
            const chatEl = document.getElementById('wbai-playground-chat');
            const prompt = promptEl.value.trim();
            if (!prompt) return;

            window.wbaiSessionPrompts.push(prompt);

            chatEl.insertAdjacentHTML('beforeend', '<div class="wbai-msg-user"><strong>You:</strong><br>' + prompt.replace(/\n/g, '<br>') + '</div>');
            promptEl.value = '';

            sendBtn.disabled = true;

            const doStep = async (requestBody, stepMessage, activeToolNodes = [], isAutoRetry = false) => {
                if (!document.getElementById('wbai-spinner-style')) {
                    document.head.insertAdjacentHTML('beforeend', '<style id="wbai-spinner-style">@keyframes wbai-spin { to { transform: rotate(360deg); } }</style>');
                }
                const loadingId = 'loading-' + Date.now();
                chatEl.insertAdjacentHTML('beforeend', '<div id="' + loadingId + '" class="wbai-msg-loading"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" stroke-opacity="0.25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg><em>' + stepMessage + '</em></div>');
                chatEl.scrollTop = chatEl.scrollHeight;

                try {
                    let fetchUrl = window.wbaiSettings.restUrl;
                    if (aiEnforceSafeMode) {
                        fetchUrl += (fetchUrl.includes('?') ? '&' : '?') + 'wbai_enforce_safe_mode=1';
                    }

                    if (window.wbaiSettings.debugMode) console.debug("[Wizard AI Playground] Sending API request:", { url: fetchUrl, data: requestBody });

                    const response = await fetch(fetchUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': window.wbaiSettings.nonceRest
                        },
                        body: JSON.stringify(requestBody)
                    });

                    if (window.wbaiSettings.debugMode) console.debug("[Wizard AI Playground] HTTP Response Status:", response.status);

                    let data;
                    try {
                        data = await response.json();
                        if (window.wbaiSettings.debugMode) console.debug("[Wizard AI Playground] JSON Response Data:", data);
                    } catch (parseError) {
                        if (!response.ok && response.status >= 500) {
                            if (!aiEnforceSafeMode) {
                                aiEnforceSafeMode = true;
                                document.getElementById('wbai-safemode-status').innerText = 'Strict Safe Mode Enforced (Auto-Recovered)';

                                const toggleSafeModeBtn = document.getElementById('wbai-toggle-safe-mode');
                                if (toggleSafeModeBtn) {
                                    toggleSafeModeBtn.classList.add('wbai-safe-mode-active');
                                    toggleSafeModeBtn.dataset.active = "1";
                                }

                                try {
                                    await fetch(window.wbaiSettings.restUrl.replace('ai-chat', 'toggle-safe-mode') + '?wbai_enforce_safe_mode=1', {
                                        method: 'POST',
                                        headers: { 'X-WP-Nonce': window.wbaiSettings.nonceRest, 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ force: 'enable' })
                                    });
                                } catch (e) { }
                                chatEl.insertAdjacentHTML('beforeend', '<div class="wbai-msg-error"><strong>System:</strong> A third-party plugin caused a Fatal Error (500) while the AI was processing. Safe Mode (.wb_ai_safe) has been enforced for the AI to auto-recover.</div>');
                                document.getElementById(loadingId).remove();

                                // Instead of just retrying, we start a task to debug it
                                const debugPrompt = "SYSTEM ALERT: A Fatal Error (HTTP 500) occurred during the last action. Safe Mode has been activated. Please use the wpab__ai__manage-debug tool to enable the debug log, reproduce the error, and fix the issue.";

                                return await doStep({
                                    conversation_id: requestBody.conversation_id,
                                    prompt: debugPrompt,
                                    model: document.getElementById('wbai-playground-model') ? document.getElementById('wbai-playground-model').value : ''
                                }, window.wbaiSettings.textAiThinking, [], true);
                            }
                        }
                        throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                    }

                    if (data.conversation_id) {
                        window.wbaiCurrentConversationId = data.conversation_id;
                    }
                    document.getElementById(loadingId).remove();

                    const friendlyNames = {
                        'wpab__core__get-site-info': 'Reading Site Information',
                        'wpab__core__get-user-info': 'Reading User Information',
                        'wpab__core__get-environment-info': 'Reading Environment Information',
                        'wpab__ai__execute-php': 'Executing PHP Code',
                        'wpab__ai__generate-image': 'Generating Image',
                        'wpab__ai__read-file': 'Reading File',
                        'wpab__ai__modify-file': 'Modifying File',
                        'wpab__ai__list-directory': 'Listing Directory',
                        'wpab__ai__search-web': 'Searching Web',
                        'wpab__ai__db-query': 'Executing DB Query',
                        'wpab__ai__manage-plugins': 'Managing Plugins',
                        'wpab__ai__manage-themes': 'Managing Themes',
                        'wpab__ai__manage-system': 'Managing System Info',
                        'wpab__ai__manage-debug': 'Managing Debug Log',
                        'wpab__ai__manage-posts': 'Managing Posts/Pages',
                        'wpab__ai__manage-comments': 'Managing Comments',
                        'wpab__ai__manage-users': 'Managing Users',
                        'wpab__ai__manage-media': 'Managing Media Library',
                        'wpab__ai__manage-menus': 'Managing Menus',
                        'wpab__ai__manage-woocommerce': 'Managing WooCommerce',
                        'wpab__ai__manage-wpml': 'Managing WPML',
                        'wpab__ai__get-post-details': 'Getting Post Details',
                        'wpab__ai__get-post-terms': 'Getting Post Terms',
                        'wpab__ai__title-generation': 'Generating Title',
                        'wpab__ai__comment-analysis': 'Analyzing Comments',
                        'wpab__ai__editorial-updates': 'Applying Editorial Updates',
                        'wpab__ai__content-classification': 'Classifying Content',
                        'wpab__ai__excerpt-generation': 'Generating Excerpt',
                        'wpab__ai__summarization': 'Summarizing Content',
                        'wpab__ai__editorial-notes': 'Writing Editorial Notes',
                        'wpab__ai__alt-text-generation': 'Generating Image Alt Text',
                        'wpab__ai__content-resizing': 'Resizing Content',
                        'wpab__ai__meta-description': 'Generating Meta Description',
                        'wpab__ai__image-generation': 'Generating Image',
                        'wpab__ai__image-import': 'Importing Image',
                        'wpab__ai__image-prompt-generation': 'Generating Image Prompt'
                    };

                    if (data.success) {
                        if (data.previous_results && data.previous_results.length > 0) {
                            let criticalExecuted = false;
                            data.previous_results.forEach((res, index) => {
                                if (res.name === 'wpab__ai__execute-php' || res.name === 'wpab__ai__modify-file' || res.name === 'wpab__ai__db-query') {
                                    criticalExecuted = true;
                                }
                                const node = activeToolNodes[index];
                                if (node) {
                                    const iconSpan = node.querySelector('.wbai-tool-icon');
                                    if (iconSpan) iconSpan.innerText = (res.response && res.response.error) ? '❌' : '✔️';

                                    const detailsDiv = node.querySelector('.wbai-tool-details');
                                    if (detailsDiv) {
                                        let msg = 'Completed.';
                                        if (res.response && res.response.message) {
                                            msg = res.response.message;
                                        } else if (res.response && res.response.error) {
                                            msg = 'Error: ' + res.response.error;
                                        }
                                        let rollbackBtnHtml = '';
                                        if (res.response && res.response.backup_id) {
                                            rollbackBtnHtml = `<div class="wbai-rollback-btn-wrapper"><button type="button" class="button button-small wbai-rollback-btn" data-backup-id="${escapeHtml(res.response.backup_id)}">↩️ Rollback Action</button></div>`;
                                        }
                                        const resultHtml = '<div class="wbai-msg-tool-result ' + ((res.response && res.response.error) ? 'wbai-error' : 'wbai-success') + '"><strong>Result:</strong> ' + msg + '<details class="wbai-msg-tool-details"><summary>(technical output returned to AI)</summary><pre class="wbai-msg-tool-pre">' + JSON.stringify(res.response, null, 2).replace(/</g, "&lt;").replace(/>/g, "&gt;") + '</pre></details>' + rollbackBtnHtml + '</div>';
                                        detailsDiv.insertAdjacentHTML('beforeend', resultHtml);

                                        // Auto-close when completed to keep UI clean
                                        detailsDiv.style.display = 'none';
                                    }
                                }
                            });

                            if (criticalExecuted) {
                                try {
                                    const feResponse = await fetch(window.wbaiSettings.homeUrl);
                                    if (!feResponse.ok && feResponse.status >= 500) {
                                        chatEl.insertAdjacentHTML('beforeend', '<div class="wbai-msg-error"><strong>System:</strong> ⚠️ WARNING: The frontend of your website is currently returning a ' + feResponse.status + ' Error! Your last action may have broken the site. Safe Mode is being activated automatically.</div>');

                                        if (!aiEnforceSafeMode) {
                                            aiEnforceSafeMode = true;
                                            document.getElementById('wbai-safemode-status').innerText = 'Strict Safe Mode Enforced (Auto-Recovered)';

                                            const toggleSafeModeBtn = document.getElementById('wbai-toggle-safe-mode');
                                            if (toggleSafeModeBtn) {
                                                toggleSafeModeBtn.classList.add('wbai-safe-mode-active');
                                                toggleSafeModeBtn.dataset.active = "1";
                                            }

                                            try {
                                                await fetch(window.wbaiSettings.restUrl.replace('ai-chat', 'toggle-safe-mode') + '?wbai_enforce_safe_mode=1', {
                                                    method: 'POST',
                                                    headers: { 'X-WP-Nonce': window.wbaiSettings.nonceRest, 'Content-Type': 'application/json' },
                                                    body: JSON.stringify({ force: 'enable' })
                                                });
                                            } catch (e) { }
                                        }

                                        // Send a prompt to the AI to debug it
                                        setTimeout(() => {
                                            const debugPrompt = "SYSTEM ALERT: The last action caused a Fatal Error on the frontend of the website. The homepage is returning HTTP 500. Safe Mode is now active. Please use the wpab__ai__manage-debug tool to enable the debug log, find the error, and fix it immediately.";
                                            doStep({
                                                conversation_id: data.conversation_id,
                                                prompt: debugPrompt,
                                                model: document.getElementById('wbai-playground-model') ? document.getElementById('wbai-playground-model').value : ''
                                            }, window.wbaiSettings.textAiThinking, [], true);
                                        }, 1000);
                                    }
                                } catch (e) {
                                    // ignore network errors for verification
                                }
                            }
                        }

                        if (data.action === 'tool_result' && data.tools) {
                            data.tools.forEach(t => {
                                const resultDiv = document.createElement('div');
                                resultDiv.className = 'wbai-tool-result';
                                resultDiv.style.cssText = 'margin-bottom: 10px; padding: 10px; background: #eaf5ea; border-left: 4px solid #46b450; border-radius: 3px; font-size: 13px;';

                                let preStr = '<pre class="wbai-pre-wrap">';
                                let stringified = typeof t.result === 'object' ? JSON.stringify(t.result, null, 2) : String(t.result);

                                let rollbackBtnHtml = '';
                                if (typeof t.result === 'object' && t.result !== null && t.result.backup_id) {
                                    rollbackBtnHtml = `<div class="wbai-rollback-btn-wrapper"><button type="button" class="button button-small wbai-rollback-btn" data-backup-id="${escapeHtml(t.result.backup_id)}">↩️ Rollback Action</button></div>`;
                                }

                                resultDiv.innerHTML = `<strong>Result: ${t.name}</strong><br>${preStr}${escapeHtml(stringified)}</pre>${rollbackBtnHtml}`;

                                chatEl.appendChild(resultDiv);
                            });
                        }

                        if (data.action === 'tool_calls') {
                            const nextActiveNodes = [];
                            const toolsWrapper = document.createElement('div');
                            toolsWrapper.style.cssText = 'margin-bottom:15px; margin-top: 15px; background: #f8f9fa; border: 1px dashed #ccc; padding: 10px; border-radius: 4px; font-size: 13px;';
                            toolsWrapper.innerHTML = '<strong>System Tasks:</strong><br>';

                            let requiresApproval = false;

                            data.tools.forEach(t => {
                                const isDbQuery = t.name.includes('db-query') || t.name.includes('db_query');
                                const isSensitive = t.name.includes('execute-php') || t.name.includes('execute_php') || isDbQuery || t.name.includes('modify-file') || t.name.includes('modify_file');

                                let needsApproval = t.name.includes('execute-php') || t.name.includes('execute_php') || t.name.includes('modify-file') || t.name.includes('modify_file');

                                if (isDbQuery && t.args.query) {
                                    const qUpper = t.args.query.trim().toUpperCase();
                                    if (!qUpper.startsWith('SELECT') && !qUpper.startsWith('SHOW') && !qUpper.startsWith('DESCRIBE')) {
                                        needsApproval = true;
                                    }
                                } else if (isDbQuery) {
                                    needsApproval = true;
                                }

                                if (needsApproval) {
                                    requiresApproval = true;
                                }

                                const friendlyName = friendlyNames[t.name] || t.name;
                                const toolNode = document.createElement('div');
                                toolNode.style.marginBottom = '8px';

                                const header = document.createElement('div');
                                header.style.cssText = 'display: flex; align-items: center; cursor: pointer; padding: 4px; border-radius: 4px; transition: background 0.2s;';
                                header.onmouseover = () => header.style.background = '#e9ecef';
                                header.onmouseout = () => header.style.background = 'transparent';
                                header.innerHTML = '<span class="wbai-tool-icon" style="margin-right: 8px;">⚙️</span><strong>' + friendlyName + '</strong> <span title="Click to view details" class="wbai-tool-help">?</span>';

                                const details = document.createElement('div');
                                details.className = 'wbai-tool-details';
                                details.style.cssText = 'display: ' + (isSensitive ? 'block' : 'none') + '; margin-top: 5px; padding: 8px; background: #eee; border-radius: 4px; font-family: monospace; font-size: 11px; overflow-x: auto; white-space: pre-wrap;';



                                let displayArgs = '';
                                console.log(t.name);
                                if ((t.name.includes('execute-php') || t.name.includes('execute_php')) && t.args.code) {
                                    if (window.wbaiSettings.cmSettings && typeof wp !== 'undefined' && wp.codeEditor) {
                                        const taId = 'wbai-cm-' + t.name.replace(/[^a-zA-Z0-9]/g, '') + '-' + Math.floor(Math.random() * 1000000);
                                        let editorCode = t.args.code;
                                        if (!editorCode.trim().startsWith('<?php')) {
                                            editorCode = '<?php\n' + editorCode;
                                        }
                                        displayArgs = '<strong>PHP Code (Editable):</strong><br><textarea id="' + taId + '" class="wbai-code-ta">' + editorCode.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</textarea>';
                                        const otherArgs = { ...t.args };
                                        delete otherArgs.code;
                                        if (Object.keys(otherArgs).length > 0) {
                                            displayArgs += '<br><strong>Other Arguments:</strong><br>' + JSON.stringify(otherArgs, null, 2);
                                        }
                                        setTimeout(() => {
                                            const ta = document.getElementById(taId);
                                            if (ta) {
                                                try {
                                                    const editor = wp.codeEditor.initialize(ta, window.wbaiSettings.cmSettings);
                                                    editor.codemirror.setOption('viewportMargin', Infinity);
                                                    toolNode.dataset.cmId = t.id;
                                                    toolNode.cmEditor = editor.codemirror;
                                                    setTimeout(() => { 
                                                        editor.codemirror.refresh(); 
                                                    }, 50);
                                                } catch (err) {}
                                            }
                                        }, 100);
                                    } else {
                                        let codeStr = t.args.code.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                        codeStr = codeStr.replace(/(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/g, '<span class="wbai-hl-var">$1</span>');
                                        codeStr = codeStr.replace(/('[^']*')/g, '<span class="wbai-hl-string">$1</span>');
                                        codeStr = codeStr.replace(/("[^"]*")/g, '<span class="wbai-hl-string">$1</span>');
                                        const keywords = ['return', 'array', 'if', 'else', 'for', 'foreach', 'while', 'function', 'class', 'public', 'private', 'protected', 'new'];
                                        const keywordRegex = new RegExp('\\b(' + keywords.join('|') + ')\\b', 'g');
                                        codeStr = codeStr.replace(keywordRegex, '<span class="wbai-hl-keyword">$1</span>');
                                        codeStr = codeStr.replace(/([a-zA-Z_]+)\s*\(/g, '<span class="wbai-hl-func">$1</span>(');
                                        codeStr = codeStr.replace(/WBAI_COLOR_([a-zA-Z0-9]+)/g, '"color: #$1;"');

                                        displayArgs = '<strong>PHP Code:</strong><br><pre class="wbai-msg-sql-pre"><code>' + codeStr + '</code></pre>';

                                        const otherArgs = { ...t.args };
                                        delete otherArgs.code;
                                        if (Object.keys(otherArgs).length > 0) {
                                            displayArgs += '<br><strong>Other Arguments:</strong><br>' + JSON.stringify(otherArgs, null, 2);
                                        }
                                    }
                                } else if ((t.name.includes('db-query') || t.name.includes('db_query')) && t.args.query) {
                                    if (window.wbaiSettings.cmSqlSettings && typeof wp !== 'undefined' && wp.codeEditor) {
                                        const taId = 'wbai-cm-' + t.name.replace(/[^a-zA-Z0-9]/g, '') + '-' + Math.floor(Math.random() * 1000000);
                                        displayArgs = '<strong>SQL Query (Editable):</strong><br><textarea id="' + taId + '" class="wbai-code-ta">' + t.args.query.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</textarea>';
                                        const otherArgs = { ...t.args };
                                        delete otherArgs.query;
                                        if (Object.keys(otherArgs).length > 0) {
                                            displayArgs += '<br><strong>Other Arguments:</strong><br>' + JSON.stringify(otherArgs, null, 2);
                                        }
                                        setTimeout(() => {
                                            const ta = document.getElementById(taId);
                                            if (ta) {
                                                try {
                                                    const editor = wp.codeEditor.initialize(ta, window.wbaiSettings.cmSqlSettings);
                                                    editor.codemirror.setOption('viewportMargin', Infinity);
                                                    toolNode.dataset.cmQueryId = t.id;
                                                    toolNode.cmEditorQuery = editor.codemirror;
                                                    setTimeout(() => { 
                                                        editor.codemirror.refresh(); 
                                                    }, 50);
                                                } catch (err) {}
                                            }
                                        }, 100);
                                    } else {
                                        let queryStr = t.args.query.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                        const sqlKeywords = ['SELECT', 'FROM', 'WHERE', 'AND', 'OR', 'INSERT', 'INTO', 'UPDATE', 'SET', 'DELETE', 'JOIN', 'LEFT', 'RIGHT', 'INNER', 'ORDER BY', 'GROUP BY', 'LIMIT'];
                                        const sqlKeywordRegex = new RegExp('\\b(' + sqlKeywords.join('|') + ')\\b', 'gi');
                                        queryStr = queryStr.replace(sqlKeywordRegex, '<span class="wbai-hl-keyword">$1</span>');

                                        displayArgs = '<strong>SQL Query:</strong><br><pre class="wbai-msg-sql-pre"><code>' + queryStr + '</code></pre>';

                                        const otherArgs = { ...t.args };
                                        delete otherArgs.query;
                                        if (Object.keys(otherArgs).length > 0) {
                                            displayArgs += '<br><strong>Other Arguments:</strong><br>' + JSON.stringify(otherArgs, null, 2);
                                        }
                                    }
                                } else if ((t.name.includes('modify-file') || t.name.includes('modify_file')) && t.args.content) {
                                    if (window.wbaiSettings.cmSettings && typeof wp !== 'undefined' && wp.codeEditor) {
                                        const taId = 'wbai-cm-' + t.name.replace(/[^a-zA-Z0-9]/g, '') + '-' + Math.floor(Math.random() * 1000000);
                                        displayArgs = '<strong>File Content (Editable):</strong><br><textarea id="' + taId + '" class="wbai-code-ta">' + t.args.content.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</textarea>';
                                        const otherArgs = { ...t.args };
                                        delete otherArgs.content;
                                        if (Object.keys(otherArgs).length > 0) {
                                            displayArgs += '<br><strong>Other Arguments:</strong><br>' + JSON.stringify(otherArgs, null, 2);
                                        }
                                        setTimeout(() => {
                                            const ta = document.getElementById(taId);
                                            if (ta) {
                                                try {
                                                    const editor = wp.codeEditor.initialize(ta, window.wbaiSettings.cmSettings);
                                                    editor.codemirror.setOption('viewportMargin', Infinity);
                                                    toolNode.dataset.cmFileId = t.id;
                                                    toolNode.cmEditorFile = editor.codemirror;
                                                    setTimeout(() => { 
                                                        editor.codemirror.refresh(); 
                                                    }, 50);
                                                } catch (err) {}
                                            }
                                        }, 100);
                                    } else {
                                        displayArgs = '<strong>File Content:</strong><br><pre class="wbai-msg-sql-pre"><code>' + t.args.content.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</code></pre>';
                                        const otherArgs = { ...t.args };
                                        delete otherArgs.content;
                                        if (Object.keys(otherArgs).length > 0) {
                                            displayArgs += '<br><strong>Other Arguments:</strong><br>' + JSON.stringify(otherArgs, null, 2);
                                        }
                                    }
                                } else {
                                    displayArgs = '<strong>Arguments:</strong><br>' + JSON.stringify(t.args, null, 2);
                                }

                                details.innerHTML = displayArgs;

                                header.classList.add('wbai-tool-header-toggle');

                                toolNode.appendChild(header);
                                toolNode.appendChild(details);
                                toolsWrapper.appendChild(toolNode);
                                nextActiveNodes.push(toolNode);
                            });

                            if (requiresApproval) {
                                const approvalWrapper = document.createElement('div');
                                approvalWrapper.style.cssText = 'margin-top: 10px; display: flex; gap: 10px; align-items: center; border-top: 1px solid #ddd; padding-top: 10px;';

                                const approveBtn = document.createElement('button');
                                approveBtn.className = 'button button-primary';
                                approveBtn.innerText = 'Approve & Execute';

                                const cancelBtn = document.createElement('button');
                                cancelBtn.className = 'button button-secondary';
                                cancelBtn.innerText = 'Cancel';

                                approvalWrapper.appendChild(approveBtn);
                                approvalWrapper.appendChild(cancelBtn);
                                toolsWrapper.appendChild(approvalWrapper);

                                chatEl.appendChild(toolsWrapper);
                                chatEl.scrollTop = chatEl.scrollHeight;

                                const globalAutoApprove = document.getElementById('wbai-global-auto-approve');
                                if (globalAutoApprove && globalAutoApprove.checked) {
                                    approvalWrapper.style.display = 'none';
                                    await doStep({
                                        conversation_id: data.conversation_id,
                                        execute_tools: true,
                                        model: modelSelect ? modelSelect.value : '',
                                        fallback_models: fallbackModelsCheckbox ? fallbackModelsCheckbox.checked : false
                                    }, window.wbaiSettings.textAiThinking, nextActiveNodes);
                                } else {
                                    approveBtn.onclick = async () => {
                                        approveBtn.disabled = true;
                                        cancelBtn.disabled = true;
                                        approveBtn.innerText = 'Executing...';

                                        const modified_tools = {};
                                        nextActiveNodes.forEach(node => {
                                            if (node.dataset.cmId && node.cmEditor) {
                                                let cmValue = node.cmEditor.getValue();
                                                if (cmValue.trim().startsWith('<?php')) {
                                                    cmValue = cmValue.replace(/^\s*<\?php\s*/i, '');
                                                }
                                                modified_tools[node.dataset.cmId] = { code: cmValue };
                                            } else if (node.dataset.cmQueryId && node.cmEditorQuery) {
                                                modified_tools[node.dataset.cmQueryId] = { query: node.cmEditorQuery.getValue() };
                                            } else if (node.dataset.cmFileId && node.cmEditorFile) {
                                                modified_tools[node.dataset.cmFileId] = { content: node.cmEditorFile.getValue() };
                                            }
                                        });

                                        await doStep({
                                            conversation_id: data.conversation_id,
                                            execute_tools: true,
                                            modified_tools: modified_tools,
                                            model: modelSelect ? modelSelect.value : '',
                                            fallback_models: fallbackModelsCheckbox ? fallbackModelsCheckbox.checked : false
                                        }, window.wbaiSettings.textAiThinking, nextActiveNodes);

                                        approvalWrapper.style.display = 'none';
                                    };

                                    cancelBtn.onclick = async () => {
                                        approveBtn.disabled = true;
                                        cancelBtn.disabled = true;
                                        cancelBtn.innerText = 'Cancelled';

                                        await doStep({
                                            conversation_id: data.conversation_id,
                                            cancel_tools: true,
                                            model: modelSelect ? modelSelect.value : '',
                                            fallback_models: fallbackModelsCheckbox ? fallbackModelsCheckbox.checked : false
                                        }, window.wbaiSettings.textAiThinking, nextActiveNodes);

                                        approvalWrapper.style.display = 'none';
                                    };
                                }
                            } else {
                                chatEl.appendChild(toolsWrapper);
                                chatEl.scrollTop = chatEl.scrollHeight;

                                await doStep({
                                    conversation_id: data.conversation_id,
                                    execute_tools: true,
                                    model: modelSelect ? modelSelect.value : '',
                                    fallback_models: fallbackModelsCheckbox ? fallbackModelsCheckbox.checked : false
                                }, window.wbaiSettings.textAiThinking, nextActiveNodes);
                            }
                        } else {
                            let aiResponse = data.response || '';
                            const escapedResponse = escapeHtml(aiResponse);
                            const copyBtnHtml = `<button type="button" class="button button-small wbai-copy-btn" style="float: right;" data-text="${escapedResponse}" title="Copy to clipboard"><span class="dashicons dashicons-clipboard" style="font-size: 16px; width: 16px; height: 16px; margin-top: 3px;"></span> Copy</button>`;
                            const redirectMatch = aiResponse.match(/\[REDIRECT_TO_BLOCK:\s*(\d+)\]/i);
                            if (redirectMatch) {
                                const blockId = redirectMatch[1];
                                aiResponse = aiResponse.replace(redirectMatch[0], '');
                                chatEl.insertAdjacentHTML('beforeend', '<div class="wbai-msg-ai">' + copyBtnHtml + '<strong>AI:</strong><br>' + aiResponse + '<br><br><em>Redirecting to block editor...</em></div>');
                                setTimeout(() => {
                                    window.location.href = window.ajaxurl.replace('admin-ajax.php', 'post.php?post=' + blockId + '&action=edit');
                                }, 1500);
                            } else {
                                chatEl.insertAdjacentHTML('beforeend', '<div class="wbai-msg-ai">' + copyBtnHtml + '<strong>AI:</strong><br>' + aiResponse + '</div>');
                            }
                            sendBtn.disabled = false;
                            checkPromptQueue();
                        }
                    } else {
                        let errorMessage = data.message || 'Unknown error';
                        errorMessage = errorMessage.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank">$1</a>');
                        chatEl.insertAdjacentHTML('beforeend', '<div class="wbai-msg-error"><strong>Error:</strong><br>' + errorMessage + '</div>');
                        sendBtn.disabled = false;
                        checkPromptQueue();

                        if (!isAutoRetry) {
                            if (!aiEnforceSafeMode) {
                                aiEnforceSafeMode = true;
                                const statusEl = document.getElementById('wbai-safemode-status');
                                if (statusEl) statusEl.innerText = 'Strict Safe Mode Enforced (Auto-Recovered)';
                                const toggleBtn = document.getElementById('wbai-toggle-safe-mode');
                                if (toggleBtn) {
                                    toggleBtn.classList.add('wbai-safe-mode-active');
                                    toggleBtn.dataset.active = "1";
                                }
                                fetch(window.wbaiSettings.restUrl.replace('ai-chat', 'toggle-safe-mode') + '?wbai_enforce_safe_mode=1', {
                                    method: 'POST',
                                    headers: { 'X-WP-Nonce': window.wbaiSettings.nonceRest, 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ force: 'enable' })
                                }).catch(() => { });
                            }

                            setTimeout(() => {
                                const errorPrompt = "SYSTEM ALERT: The last action resulted in an error: " + (data.message || 'Unknown error') + ". Safe Mode has been activated. Please use the wpab__ai__manage-debug tool to enable the debug log, find the error, and fix it.";
                                doStep({
                                    conversation_id: data.conversation_id || requestBody.conversation_id,
                                    prompt: errorPrompt,
                                    model: document.getElementById('wbai-playground-model') ? document.getElementById('wbai-playground-model').value : ''
                                }, window.wbaiSettings.textAiThinking, [], true);
                            }, 1000);
                        }
                    }
                } catch (e) {
                    const l = document.getElementById(loadingId);
                    if (l) l.remove();
                    let errorMessage = e.message || 'Unknown error';
                    errorMessage = errorMessage.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank">$1</a>');
                    chatEl.insertAdjacentHTML('beforeend', '<div class="wbai-msg-error"><strong>Error:</strong><br>' + errorMessage + '</div>');
                    sendBtn.disabled = false;
                    checkPromptQueue();

                    if (!isAutoRetry) {
                        if (!aiEnforceSafeMode) {
                            aiEnforceSafeMode = true;
                            const statusEl = document.getElementById('wbai-safemode-status');
                            if (statusEl) statusEl.innerText = 'Strict Safe Mode Enforced (Auto-Recovered)';
                            const toggleBtn = document.getElementById('wbai-toggle-safe-mode');
                            if (toggleBtn) {
                                toggleBtn.classList.add('wbai-safe-mode-active');
                                toggleBtn.dataset.active = "1";
                            }
                            fetch(window.wbaiSettings.restUrl.replace('ai-chat', 'toggle-safe-mode') + '?wbai_enforce_safe_mode=1', {
                                method: 'POST',
                                headers: { 'X-WP-Nonce': window.wbaiSettings.nonceRest, 'Content-Type': 'application/json' },
                                body: JSON.stringify({ force: 'enable' })
                            }).catch(() => { });
                        }

                        setTimeout(() => {
                            const errorPrompt = "SYSTEM ALERT: The last action resulted in an error: " + (e.message || 'Unknown error') + ". Safe Mode has been activated. Please use the wpab__ai__manage-debug tool to enable the debug log, find the error, and fix it.";
                            doStep({
                                conversation_id: requestBody.conversation_id || window.wbaiCurrentConversationId,
                                prompt: errorPrompt,
                                model: document.getElementById('wbai-playground-model') ? document.getElementById('wbai-playground-model').value : ''
                            }, window.wbaiSettings.textAiThinking, [], true);
                        }, 1000);
                    }
                }
                chatEl.scrollTop = chatEl.scrollHeight;
                saveChatState();
            };

            const payload = {
                prompt: prompt,
                model: modelSelect ? modelSelect.value : '',
                fallback_models: fallbackModelsCheckbox ? fallbackModelsCheckbox.checked : false,
                system_info_context: (document.getElementById('wbai-include-system-info') && document.getElementById('wbai-include-system-info').checked && document.getElementById('wbai-system-info-context')) ? document.getElementById('wbai-system-info-context').value : '',
                session_context: document.getElementById('wbai-session-context') ? document.getElementById('wbai-session-context').value : '',
                permanent_context: document.getElementById('wbai-permanent-context') ? document.getElementById('wbai-permanent-context').value : ''
            };

            let ragContext = '';
            document.querySelectorAll('.wbai-rag-checkbox:checked').forEach(cb => {
                const type = cb.dataset.type;
                const ta = document.getElementById('wbai-rag-context-' + type);
                if (ta && ta.value) {
                    ragContext += "\n--- RAG DATA: " + type.toUpperCase() + " ---\n" + ta.value + "\n";
                }
            });
            if (ragContext) {
                payload.rag_context = ragContext;
            }

            // Uncheck after first prompt to save tokens
            document.querySelectorAll('.wbai-rag-checkbox:checked').forEach(cb => {
                cb.checked = false;
            });

            if (window.wbaiCurrentConversationId) {
                payload.conversation_id = window.wbaiCurrentConversationId;
            }
            await doStep(payload, window.wbaiSettings.textAiThinking);
        });
    }
    // Clear backups
    const clearBackupsBtn = document.getElementById('wbai-clear-backups');
    if (clearBackupsBtn) {
        clearBackupsBtn.addEventListener('click', function () {
            if (!confirm('Are you sure you want to delete all temporary AI action backups? You will not be able to rollback recent actions anymore.')) return;

            const originalText = clearBackupsBtn.innerText;
            clearBackupsBtn.disabled = true;
            clearBackupsBtn.innerText = 'Clearing...';

            fetch(window.wbaiSettings.restUrl.replace('/ai-chat', '/delete-ai-backups'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.wbaiSettings.nonceRest
                }
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        alert('Backups cleared successfully.');
                        const container = document.getElementById('wbai-backups-container');
                        if (container) {
                            container.style.display = 'none';
                        }
                    } else {
                        alert('Failed to clear backups.');
                    }
                })
                .catch(e => {
                    console.error(e);
                    alert('Error clearing backups.');
                })
                .finally(() => {
                    clearBackupsBtn.disabled = false;
                    clearBackupsBtn.innerText = originalText;
                });
        });
    }

    // Rollback action delegate
    document.addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('wbai-rollback-btn')) {
            const btn = e.target;
            const backupId = btn.dataset.backupId;
            if (!confirm('Are you sure you want to rollback this action? This will restore the file/database to its exact state before this tool was executed.')) return;

            btn.disabled = true;
            btn.textContent = 'Rolling back...';

            fetch(window.wbaiSettings.restUrl.replace('/ai-chat', '/rollback-ai-action'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.wbaiSettings.nonceRest
                },
                body: JSON.stringify({ backup_id: backupId })
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        btn.textContent = '✅ Rolled Back';
                        btn.style.color = 'green';
                        btn.style.borderColor = 'green';
                    } else {
                        btn.disabled = false;
                        btn.textContent = '↩️ Rollback Action';
                        alert('Rollback failed: ' + (res.message || 'Unknown error'));
                    }
                })
                .catch(err => {
                    console.error('Rollback error', err);
                    btn.disabled = false;
                    btn.textContent = '↩️ Rollback Action';
                    alert('Rollback failed.');
                });
        }
    });

    // Tool details toggle delegate
    document.addEventListener('click', function (e) {
        let target = e.target;
        while (target && target !== document) {
            if (target.classList && target.classList.contains('wbai-tool-header-toggle')) {
                const details = target.nextElementSibling;
                if (details && details.classList.contains('wbai-tool-details')) {
                    details.style.display = details.style.display === 'none' ? 'block' : 'none';
                }
                break;
            }
            if (target.classList && target.classList.contains('wbai-copy-btn')) {
                const textToCopy = target.getAttribute('data-text');
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(textToCopy).then(() => {
                        const originalHtml = target.innerHTML;
                        target.innerHTML = '✅ Copied!';
                        setTimeout(() => { target.innerHTML = originalHtml; }, 2000);
                    }).catch(err => {
                        console.error('Failed to copy text: ', err);
                    });
                } else {
                    const textarea = document.createElement('textarea');
                    textarea.value = textToCopy;
                    document.body.appendChild(textarea);
                    textarea.select();
                    try {
                        document.execCommand('copy');
                        const originalHtml = target.innerHTML;
                        target.innerHTML = '✅ Copied!';
                        setTimeout(() => { target.innerHTML = originalHtml; }, 2000);
                    } catch (err) {
                        console.error('Fallback copy failed', err);
                    }
                    document.body.removeChild(textarea);
                }
                break;
            }
            target = target.parentNode;
        }
    });

});
