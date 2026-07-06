/**
 * Wizard Blocks AI - CodeMirror Logic
 */

let wizardAiModalInitialized = false;

function initAiModal() {
    if (wizardAiModalInitialized) {
        return;
    }

    const modal = document.getElementById('wizard-ai-modal');
    if (!modal) {
        return;
    }

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeAiModal();
        }
    });

    const closeBtn = document.getElementById('wizard-ai-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeAiModal);
    }

    const cancelBtn = document.getElementById('wizard-ai-cancel');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeAiModal);
    }

    const submitBtn = document.getElementById('ai-submit-btn');
    if (submitBtn) {
        submitBtn.addEventListener('click', runAiGeneration);
    }

    const speechBtn = document.getElementById('wizard-ai-speech-btn');
    const promptField = document.getElementById('ai-user-prompt');
    const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition || window.mozSpeechRecognition || window.msSpeechRecognition;

    if (speechBtn && promptField && SpeechRec) {
        speechBtn.style.display = 'block';
        const recognition = new SpeechRec();
        recognition.continuous = false;
        recognition.interimResults = false;
        
        let isRecording = false;

        recognition.onstart = function() {
            isRecording = true;
            speechBtn.textContent = '🔴';
        };

        recognition.onresult = function(event) {
            const transcript = event.results[0][0].transcript;
            if (promptField.value) {
                promptField.value += ' ' + transcript;
            } else {
                promptField.value = transcript;
            }
        };

        recognition.onerror = function(event) {
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

        recognition.onend = function() {
            isRecording = false;
            speechBtn.textContent = '🎤';
        };

        speechBtn.addEventListener('click', function() {
            if (isRecording) {
                recognition.stop();
            } else {
                try {
                    recognition.start();
                } catch(e) {
                    console.error('Failed to start speech recognition', e);
                    alert('Failed to start speech recognition. Your browser might not fully support this feature.');
                }
            }
        });
    }

    const modelSelect = document.getElementById('ai-model-select');
    if (modelSelect) {
        fetch(wizardData.rest_url + 'wizard-ai/v1/ai-models', {
            headers: { 'X-WP-Nonce': wizardData.nonce }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.models) {
                modelSelect.innerHTML = '';
                
                if (typeof data.models === 'object' && Object.keys(data.models).length > 0) {
                    const firstVal = Object.values(data.models)[0];
                    if (typeof firstVal === 'object') {
                        Object.entries(data.models).forEach(([groupName, groupModels]) => {
                            const optgroup = document.createElement('optgroup');
                            optgroup.label = groupName;
                            const sortedIds = Object.keys(groupModels).sort();
                            sortedIds.forEach(id => {
                                const opt = document.createElement('option');
                                opt.value = id;
                                opt.textContent = groupModels[id];
                                if (id === wizardData.preferredModel) {
                                    opt.selected = true;
                                }
                                optgroup.appendChild(opt);
                            });
                            modelSelect.appendChild(optgroup);
                        });
                    } else {
                        const sortedModels = Object.keys(data.models).sort();
                        sortedModels.forEach(id => {
                            const opt = document.createElement('option');
                            opt.value = id;
                            opt.textContent = data.models[id];
                            if (id === wizardData.preferredModel) {
                                opt.selected = true;
                            }
                            modelSelect.appendChild(opt);
                        });
                    }
                }
            } else {
                modelSelect.innerHTML = '<option value="">Failed to load models</option>';
            }
        })
        .catch(err => {
            console.error('Failed to load AI models:', err);
            modelSelect.innerHTML = '<option value="">Error loading models</option>';
        });
    }

    wizardAiModalInitialized = true;
}

function openAiPopup() {
    initAiModal();
    const modal = document.getElementById('wizard-ai-modal');
    if (!modal) {
        return;
    }
    modal.style.display = 'flex';
    const promptField = document.getElementById('ai-user-prompt');
    if (promptField) {
        promptField.focus();
    }
    document.addEventListener('keydown', handleAiKeys);
}

function handleAiKeys(e) {
    if (e.key === 'Escape') closeAiModal();
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) runAiGeneration();
}

function closeAiModal() {
    const modal = document.getElementById('wizard-ai-modal');
    if (modal) {
        modal.style.display = 'none';
    }
    document.removeEventListener('keydown', handleAiKeys);
}

function getCMInstance(id) {
    const textarea = document.getElementById(id);
    return textarea?.nextElementSibling?.CodeMirror || null;
}

async function runAiGeneration() {
    const btn = document.getElementById('ai-submit-btn');
    const promptField = document.getElementById('ai-user-prompt');
    const prompt = promptField.value.trim();

    const blockJsonEl = document.getElementById('block-json');
    var fullBundle = {};
    if (blockJsonEl && blockJsonEl.value) {
        try {
            fullBundle = JSON.parse(blockJsonEl.value);
        } catch (e) {
            console.warn('Could not parse block-json, defaulting to empty object', e);
        }
    }
    if (Array.isArray(fullBundle) && fullBundle.length === 0) fullBundle = {};

    if (!prompt) return alert(wizardData.emptyPromptError);

    const keys = ['render', 'script', 'viewScript', 'viewScriptModule', 'editorScript', 'style', 'viewStyle', 'editorStyle'];
    keys.forEach(key => {
        const cm = getCMInstance(`_block_${key}_file`);
        //console.log(key, cm, cm.getValue());
        if (cm) fullBundle[key] = cm.getValue();
    });

    const attributesCM = getCMInstance('_block_attributes');
    if (attributesCM) {
        const rawAttributes = attributesCM.getValue().trim();
        if (rawAttributes) {
            try {
                fullBundle.attributes = JSON.parse(rawAttributes);
            } catch (e) {
                return alert(wizardData.invalidJsonError);
            }
        }
    }

    fullBundle.title = document.getElementById('title')?.value;
    fullBundle.description = document.getElementById('excerpt')?.value;
    console.log(fullBundle);

    btn.disabled = true;
    btn.innerText = '🤖 ' + wizardData.analyzingLabel;

    const modelSelect = document.getElementById('ai-model-select');
    const selectedModel = modelSelect ? modelSelect.value : '';

    try {
        const reqData = { block_json: fullBundle, prompt: prompt, model: selectedModel };
        if (wizardData.debugMode) console.debug("[Wizard AI Block] Sending API request:", { url: wizardData.rest_url + 'wizard-ai/v1/process-ai', data: reqData });

        const response = await fetch(wizardData.rest_url + 'wizard-ai/v1/process-ai', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wizardData.nonce },
            body: JSON.stringify(reqData)
        });

        if (wizardData.debugMode) console.debug("[Wizard AI Block] HTTP Response Status:", response.status);

        const textResponse = await response.text();
        let data;
        try {
            data = JSON.parse(textResponse);
            if (wizardData.debugMode) console.debug("[Wizard AI Block] JSON Response Data:", data);
        } catch (jsonErr) {
            console.error("Raw AI Server Response:", textResponse);
            let errMsg = `Server returned invalid JSON (HTTP ${response.status}).`;
            if (textResponse.includes('Fatal error') || textResponse.includes('Parse error')) {
                errMsg = 'PHP Error occurred on the server.';
            }
            if (!response.ok && response.status >= 500) {
                errMsg += ' Check server logs for more details.';
            }
            throw new Error(`${errMsg} Check browser console for raw output.`);
        }

        if (!response.ok) {
            let errorMsg = data.message;
            if (!errorMsg && data.error && data.error.message) errorMsg = data.error.message;
            throw new Error(errorMsg || `Server Error ${response.status}`);
        }
        if (data.success && data.updated_json) {
            const updated = data.updated_json;

            // assets
            keys.forEach(key => {
                const cm = getCMInstance(`_block_${key}_file`);
                if (cm && updated[key] !== undefined) cm.setValue(updated[key]);
            });

            const updatedAttributesCM = getCMInstance('_block_attributes');
            if (updatedAttributesCM && updated.attributes !== undefined) {
                updatedAttributesCM.setValue(JSON.stringify(updated.attributes, null, 4));
            }

            if (updated.title && document.getElementById('title')) document.getElementById('title').value = updated.title;
            if (updated.description) {
                document.getElementById('excerpt').value = updated.description;
            }

            // update full json vision
            const updateBlockJsonEl = document.getElementById('block-json');
            if (updateBlockJsonEl) {
                updateBlockJsonEl.value = JSON.stringify(updated, null, 4);
            }

            promptField.value = '';
            closeAiModal();
        } else {
            throw new Error(data.message || wizardData.invalidAiResponseError);
        }
    } catch (e) {
        alert(wizardData.aiErrorPrefix + e.message);
    } finally {
        btn.disabled = false;
        btn.innerText = '✨ ' + wizardData.processLabel;
    }
}