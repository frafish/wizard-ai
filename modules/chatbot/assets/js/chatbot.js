document.addEventListener('DOMContentLoaded', function() {
    const chatbot = document.getElementById('wai-chatbot');
    if (!chatbot) return;

    const toggleBtn = document.getElementById('wai-chatbot-toggle');
    const header = document.getElementById('wai-chatbot-header');
    const sendBtn = document.getElementById('wai-chatbot-send');
    const promptInput = document.getElementById('wai-chatbot-prompt');
    const messagesArea = document.getElementById('wai-chatbot-messages');
    
    const isLiveMode = chatbot.getAttribute('data-live-mode') === '1';
    
    let sessionId = localStorage.getItem('wai_chatbot_session_id');
    if (!sessionId) {
        sessionId = 'sess_' + Math.random().toString(36).substr(2, 9);
        localStorage.setItem('wai_chatbot_session_id', sessionId);
    }

    let chatHistory = [];
    try {
        const storedHistory = localStorage.getItem('wai_chatbot_history');
        if (storedHistory) {
            chatHistory = JSON.parse(storedHistory);
        }
    } catch(e) {}

    function renderHistory() {
        if (chatHistory.length > 0) {
            const greeting = messagesArea.firstElementChild;
            messagesArea.innerHTML = '';
            if (greeting) {
                messagesArea.appendChild(greeting);
            }
            
            const emailRegex = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/;
            let hasEmail = false;

            chatHistory.forEach(msg => {
                const div = document.createElement('div');
                div.className = 'wai-chatbot-msg wai-chatbot-' + msg.role;
                
                const contentDiv = document.createElement('div');
                contentDiv.className = 'wai-msg-content';
                
                if (msg.role === 'ai' || msg.role === 'sys') {
                    contentDiv.innerHTML = msg.text;
                } else {
                    contentDiv.innerText = msg.text;
                    if (emailRegex.test(msg.text)) hasEmail = true;
                }
                div.appendChild(contentDiv);
                
                if (msg.role === 'ai' && 'speechSynthesis' in window) {
                    const speakBtn = document.createElement('button');
                    speakBtn.type = 'button';
                    speakBtn.className = 'wai-chatbot-speak-btn';
                    speakBtn.innerHTML = '🔊';
                    speakBtn.title = 'Speak message';
                    speakBtn.addEventListener('click', function() {
                        window.speechSynthesis.cancel();
                        speakText(msg.text);
                    });
                    div.appendChild(speakBtn);
                }
                
                messagesArea.appendChild(div);
            });
            messagesArea.scrollTop = messagesArea.scrollHeight;

            if (hasEmail) {
                const hint = document.getElementById('wai-chatbot-email-hint');
                if (hint) hint.style.display = 'none';
            }
        }
    }
    renderHistory();

    function toggleChat() {
        if (chatbot.classList.contains('wai-chatbot-closed')) {
            chatbot.classList.remove('wai-chatbot-closed');
            toggleBtn.innerHTML = '<span class="dashicons dashicons-arrow-down-alt2"></span>';
            setTimeout(() => promptInput.focus(), 100);
        } else {
            chatbot.classList.add('wai-chatbot-closed');
            toggleBtn.innerHTML = '<span class="dashicons dashicons-arrow-up-alt2"></span>';
        }
    }

    let hasDragged = false;

    if (typeof jQuery !== 'undefined' && jQuery.ui) {
        jQuery('#wai-chatbot').draggable({
            handle: '#wai-chatbot-header',
            start: function() {
                hasDragged = true;
                jQuery(this).css({
                    bottom: 'auto',
                    right: 'auto',
                    transition: 'none'
                });
            },
            stop: function(event, ui) {
                setTimeout(() => hasDragged = false, 100);
                jQuery(this).css('transition', 'all 0.3s ease');
                
                let rect = chatbot.getBoundingClientRect();
                let ww = window.innerWidth;
                let wh = window.innerHeight;
                
                let savePos = {};
                if (rect.left > ww / 2) {
                    savePos.right = (ww - rect.right) + 'px';
                    savePos.left = 'auto';
                } else {
                    savePos.left = rect.left + 'px';
                    savePos.right = 'auto';
                }
                
                if (rect.top > wh / 2) {
                    savePos.bottom = (wh - rect.bottom) + 'px';
                    savePos.top = 'auto';
                } else {
                    savePos.top = rect.top + 'px';
                    savePos.bottom = 'auto';
                }
                
                chatbot.style.left = savePos.left;
                chatbot.style.right = savePos.right;
                chatbot.style.top = savePos.top;
                chatbot.style.bottom = savePos.bottom;
                
                localStorage.setItem('wai_chatbot_pos', JSON.stringify(savePos));
            }
        }).resizable({
            minHeight: 300,
            minWidth: 250,
            handles: 'n, e, s, w, ne, se, sw, nw',
            start: function() {
                jQuery(this).css('transition', 'none');
            },
            stop: function(event, ui) {
                jQuery(this).css('transition', 'all 0.3s ease');
                localStorage.setItem('wai_chatbot_size', JSON.stringify({
                    width: ui.size.width + 'px',
                    height: ui.size.height + 'px'
                }));
            }
        });
    }

    const savedPos = localStorage.getItem('wai_chatbot_pos');
    if (savedPos) {
        try {
            const pos = JSON.parse(savedPos);
            if (pos.left && pos.left !== 'auto') chatbot.style.left = pos.left;
            if (pos.right && pos.right !== 'auto') chatbot.style.right = pos.right;
            if (pos.top && pos.top !== 'auto') chatbot.style.top = pos.top;
            if (pos.bottom && pos.bottom !== 'auto') chatbot.style.bottom = pos.bottom;
        } catch(e) {}
    }

    const savedSize = localStorage.getItem('wai_chatbot_size');
    if (savedSize) {
        try {
            const size = JSON.parse(savedSize);
            if (size.width) chatbot.style.width = size.width;
            if (size.height) chatbot.style.height = size.height;
        } catch(e) {}
    }

    header.addEventListener('click', function(e) {
        if (hasDragged) return;
        if (chatbot.classList.contains('wai-chatbot-closed')) {
            toggleChat();
        } else if (e.target.closest('#wai-chatbot-toggle')) {
            toggleChat();
        }
    });

    const emailSubmitBtn = document.getElementById('wai-chatbot-email-submit');
    const nameInput = document.getElementById('wai-chatbot-name-input');
    const emailInput = document.getElementById('wai-chatbot-email-input');
    if (emailSubmitBtn && emailInput) {
        emailSubmitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const consentBox = document.getElementById('wai-chatbot-gdpr-consent');
            if (consentBox && !consentBox.checked) {
                consentBox.parentElement.style.color = 'red';
                consentBox.focus();
                return;
            } else if (consentBox) {
                consentBox.parentElement.style.color = '';
            }

            const emailVal = emailInput.value.trim();
            const nameVal = nameInput ? nameInput.value.trim() : '';
            if (emailVal && /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/.test(emailVal)) {
                if (nameVal) {
                    promptInput.value = "My name is " + nameVal + " and my email is " + emailVal;
                } else {
                    promptInput.value = "My email is " + emailVal;
                }
                sendMessage();
            } else {
                emailInput.style.borderColor = 'red';
            }
        });
    }

    const resetBtn = document.getElementById('wai-chatbot-reset');
    if (resetBtn) {
        resetBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (confirm(wizardAiChatbotData.resetConfirm || 'Are you sure you want to start a new chat?')) {
                localStorage.removeItem('wai_chatbot_session_id');
                localStorage.removeItem('wai_chatbot_history');
                sessionId = 'sess_' + Math.random().toString(36).substr(2, 9);
                localStorage.setItem('wai_chatbot_session_id', sessionId);
                chatHistory = [];
                const greeting = messagesArea.firstElementChild;
                messagesArea.innerHTML = '';
                if (greeting) {
                    messagesArea.appendChild(greeting);
                }
            }
        });
    }

    function addMessage(text, role, save = true) {
        const div = document.createElement('div');
        div.className = 'wai-chatbot-msg wai-chatbot-' + role;
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'wai-msg-content';
        
        if (role === 'ai' || role === 'sys') {
            contentDiv.innerHTML = text;
        } else {
            contentDiv.innerText = text;
        }
        div.appendChild(contentDiv);
        
        if (role === 'ai' && 'speechSynthesis' in window) {
            const speakBtn = document.createElement('button');
            speakBtn.type = 'button';
            speakBtn.className = 'wai-chatbot-speak-btn';
            speakBtn.innerHTML = '🔊';
            speakBtn.title = 'Speak message';
            speakBtn.addEventListener('click', function() {
                window.speechSynthesis.cancel();
                speakText(text);
            });
            div.appendChild(speakBtn);
        }
        messagesArea.appendChild(div);
        messagesArea.scrollTop = messagesArea.scrollHeight;
        
        if (save) {
            chatHistory.push({ text: text, role: role });
            localStorage.setItem('wai_chatbot_history', JSON.stringify(chatHistory));
        }
        
        return div;
    }

    const micBtn = document.getElementById('wai-chatbot-mic');
    
    let recognition = null;
    let isListening = false;
    
    if (isLiveMode && ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window)) {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        recognition = new SpeechRecognition();
        recognition.continuous = false;
        recognition.interimResults = false;
        
        recognition.onstart = function() {
            isListening = true;
            if (micBtn) {
                micBtn.style.color = '#d63638';
                micBtn.style.opacity = '0.5';
            }
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
            }
        };
        
        recognition.onresult = function(event) {
            const transcript = event.results[0][0].transcript;
            promptInput.value = transcript;
            setTimeout(() => {
                sendMessage();
            }, 100);
        };
        
        recognition.onend = function() {
            isListening = false;
            if (micBtn) {
                micBtn.style.color = '';
                micBtn.style.opacity = '1';
            }
        };
        
        if (micBtn) {
            micBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (isListening) {
                    recognition.stop();
                } else {
                    recognition.start();
                }
            });
        }
    } else if (micBtn) {
        micBtn.style.display = 'none';
    }
    
    function speakText(text) {
        if (isLiveMode && 'speechSynthesis' in window) {
            setTimeout(() => {
                let cleanText = text.replace(/<[^>]+>/g, ' ').replace(/[#*_~\[\]()]/g, '');
                const utterance = new SpeechSynthesisUtterance(cleanText);
                window.speechSynthesis.speak(utterance);
            }, 100);
        }
    }

    async function sendMessage() {
        const text = promptInput.value.trim();
        if (!text) return;
        
        const consentBox = document.getElementById('wai-chatbot-gdpr-consent');
        if (consentBox && !consentBox.checked) {
            consentBox.parentElement.style.color = 'red';
            consentBox.focus();
            return;
        } else if (consentBox) {
            consentBox.parentElement.style.color = '';
            // Hide notice after consent since it's now approved for the session
            const noticeBox = document.getElementById('wai-chatbot-gdpr-notice');
            if (noticeBox) noticeBox.style.display = 'none';
        }
        
        const emailRegex = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/;
        if (emailRegex.test(text)) {
            const hint = document.getElementById('wai-chatbot-email-hint');
            if (hint) hint.style.display = 'none';
        }

        promptInput.value = '';
        promptInput.disabled = true;
        sendBtn.disabled = true;

        addMessage(text, 'user');
        const loadingDiv = addMessage('...', 'ai', false);

        try {
            let mainContentEl = document.querySelector('main, article, #content, .content, #main');
            let current_content = '';
            if (mainContentEl) {
                current_content = mainContentEl.innerText.substring(0, 15000);
            } else {
                current_content = document.body.innerText.substring(0, 15000);
            }

            let formsContext = '';
            document.querySelectorAll('form').forEach((f, idx) => {
                if (f.id === 'wai-chatbot-form') return;
                formsContext += `[Frontend Form ${idx + 1} (${f.id || f.className || 'unnamed'})]\n`;
                f.querySelectorAll('input, select, textarea').forEach(input => {
                    if (input.type === 'hidden' || input.type === 'submit' || input.type === 'button') return;
                    let name = input.name || input.id;
                    if (!name) return;
                    let type = input.tagName.toLowerCase() === 'select' ? 'select' : input.type;
                    let options = '';
                    if (type === 'select') {
                        options = ' Options: ' + Array.from(input.options).map(o => o.value).join(', ');
                    }
                    formsContext += `- ${name} (type: ${type})${options}\n`;
                });
                formsContext += '\n';
            });

            if (formsContext) {
                current_content += "\n\n--- Forms on this page ---\n" + formsContext + "Note: If the user needs help filling a form, prompt them for values step by step, and use the `wpab__ai__fill_form_field` tool to auto-fill the frontend form fields for them! Provide option lists and suggest values if appropriate.\n";
            }

            const hp = document.getElementById('wai-chatbot-hp');
            const reqData = {
                prompt: text,
                session_id: sessionId,
                current_url: window.location.href,
                current_title: document.title,
                current_content: current_content,
                current_post_id: wizardAiChatbotData.post_id,
                wai_hp: hp ? hp.value : ''
            };
            if (wizardAiChatbotData.debugMode) {
                console.debug('Wizard AI Chatbot: Sending request', { url: wizardAiChatbotData.rest_url, data: reqData });
            }

            const response = await fetch(wizardAiChatbotData.rest_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wizardAiChatbotData.nonce
                },
                body: JSON.stringify(reqData)
            });

            const data = await response.json();
            if (wizardAiChatbotData.debugMode) {
                console.debug('Wizard AI Chatbot: Received response', data);
            }
            
            messagesArea.removeChild(loadingDiv);
            
            if (data.success && data.reply) {
                addMessage(data.reply, 'ai');
                
                if (data.frontend_actions && data.frontend_actions.length > 0) {
                    data.frontend_actions.forEach(action => {
                        if (action.type === 'fill_form') {
                            try {
                                const field = document.querySelector(`[name="${action.fieldName}"]`) || document.getElementById(action.fieldName);
                                if (field) {
                                    field.value = action.fieldValue;
                                    field.dispatchEvent(new Event('change', { bubbles: true }));
                                    field.dispatchEvent(new Event('input', { bubbles: true }));
                                    field.style.boxShadow = '0 0 10px #4ade80';
                                    setTimeout(() => field.style.boxShadow = '', 2000);
                                }
                            } catch(err) {
                                console.error('Form fill error:', err);
                            }
                        } else if (action.type === 'show_email_form') {
                            const hint = document.getElementById('wai-chatbot-email-hint');
                            if (hint) {
                                hint.style.display = 'block';
                                setTimeout(() => {
                                    messagesArea.scrollTop = messagesArea.scrollHeight;
                                }, 100);
                            }
                        } else if (action.type === 'elementor_insert' && window.elementor) {
                            try {
                                let section = {
                                    id: Math.random().toString(36).substr(2, 7),
                                    elType: 'section',
                                    settings: {},
                                    elements: [{
                                        id: Math.random().toString(36).substr(2, 7),
                                        elType: 'column',
                                        settings: { _column_size: 100 },
                                        elements: [{
                                            id: Math.random().toString(36).substr(2, 7),
                                            elType: 'widget',
                                            widgetType: action.widgetType,
                                            settings: action.widgetData,
                                            elements: []
                                        }]
                                    }]
                                };
                                if (window.$e && window.$e.run) {
                                    window.$e.run('document/elements/create', {
                                        model: section,
                                        container: window.elementor.getPreviewContainer()
                                    });
                                } else {
                                    window.elementor.getPreviewView().addChildModel(section);
                                }
                            } catch(err) {
                                console.error('Elementor insert error:', err);
                            }
                        } else if (action.type === 'gutenberg_insert' && window.wp && wp.data && wp.data.dispatch('core/block-editor')) {
                            try {
                                const blocks = wp.blocks.parse(action.blockHTML);
                                wp.data.dispatch('core/block-editor').insertBlocks(blocks);
                            } catch(err) {
                                console.error('Gutenberg insert error:', err);
                            }
                        }
                    });
                }
            } else {
                addMessage(data.message || 'Error occurred.', 'sys');
            }
        } catch (e) {
            messagesArea.removeChild(loadingDiv);
            addMessage('Network error occurred.', 'sys');
        }

        promptInput.disabled = false;
        sendBtn.disabled = false;
        promptInput.focus();
    }

    sendBtn.addEventListener('click', sendMessage);
    promptInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    let lastPollTime = new Date().toISOString().replace('T', ' ').substring(0, 19);
    setInterval(async () => {
        if (!sessionId) return;
        const pollUrl = wizardAiChatbotData.rest_url + '/poll';
        try {
            const response = await fetch(pollUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wizardAiChatbotData.nonce
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    last_time: lastPollTime
                })
            });
            const data = await response.json();
            if (data.success && data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    // Avoid duplicating user messages we just sent
                    if (msg.role !== 'user') {
                        addMessage(msg.text, msg.role);
                    }
                    lastPollTime = msg.date_gmt;
                });
            }
        } catch(e) {}
    }, 5000);

});
