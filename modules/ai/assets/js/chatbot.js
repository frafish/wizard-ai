document.addEventListener('DOMContentLoaded', function() {
    const chatbot = document.getElementById('wbai-chatbot');
    if (!chatbot) return;

    const toggleBtn = document.getElementById('wbai-chatbot-toggle');
    const header = document.getElementById('wbai-chatbot-header');
    const sendBtn = document.getElementById('wbai-chatbot-send');
    const promptInput = document.getElementById('wbai-chatbot-prompt');
    const messagesArea = document.getElementById('wbai-chatbot-messages');
    
    let sessionId = localStorage.getItem('wbai_chatbot_session_id');
    if (!sessionId) {
        sessionId = 'sess_' + Math.random().toString(36).substr(2, 9);
        localStorage.setItem('wbai_chatbot_session_id', sessionId);
    }

    let chatHistory = [];
    try {
        const storedHistory = localStorage.getItem('wbai_chatbot_history');
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
                div.className = 'wbai-chatbot-msg wbai-chatbot-' + msg.role;
                if (msg.role === 'ai' || msg.role === 'sys') {
                    div.innerHTML = msg.text;
                } else {
                    div.innerText = msg.text;
                    if (emailRegex.test(msg.text)) hasEmail = true;
                }
                messagesArea.appendChild(div);
            });
            messagesArea.scrollTop = messagesArea.scrollHeight;

            if (hasEmail) {
                const hint = document.getElementById('wbai-chatbot-email-hint');
                if (hint) hint.style.display = 'none';
            }
        }
    }
    renderHistory();

    function toggleChat() {
        if (chatbot.classList.contains('wbai-chatbot-closed')) {
            chatbot.classList.remove('wbai-chatbot-closed');
            toggleBtn.innerHTML = '<span class="dashicons dashicons-arrow-down-alt2"></span>';
            setTimeout(() => promptInput.focus(), 100);
        } else {
            chatbot.classList.add('wbai-chatbot-closed');
            toggleBtn.innerHTML = '<span class="dashicons dashicons-arrow-up-alt2"></span>';
        }
    }

    let hasDragged = false;

    if (typeof jQuery !== 'undefined' && jQuery.ui) {
        jQuery('#wbai-chatbot').draggable({
            handle: '#wbai-chatbot-header',
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
                
                localStorage.setItem('wbai_chatbot_pos', JSON.stringify(savePos));
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
                localStorage.setItem('wbai_chatbot_size', JSON.stringify({
                    width: ui.size.width + 'px',
                    height: ui.size.height + 'px'
                }));
            }
        });
    }

    const savedPos = localStorage.getItem('wbai_chatbot_pos');
    if (savedPos) {
        try {
            const pos = JSON.parse(savedPos);
            if (pos.left && pos.left !== 'auto') chatbot.style.left = pos.left;
            if (pos.right && pos.right !== 'auto') chatbot.style.right = pos.right;
            if (pos.top && pos.top !== 'auto') chatbot.style.top = pos.top;
            if (pos.bottom && pos.bottom !== 'auto') chatbot.style.bottom = pos.bottom;
        } catch(e) {}
    }

    const savedSize = localStorage.getItem('wbai_chatbot_size');
    if (savedSize) {
        try {
            const size = JSON.parse(savedSize);
            if (size.width) chatbot.style.width = size.width;
            if (size.height) chatbot.style.height = size.height;
        } catch(e) {}
    }

    header.addEventListener('click', function(e) {
        if (hasDragged) return;
        if (chatbot.classList.contains('wbai-chatbot-closed')) {
            toggleChat();
        } else if (e.target.closest('#wbai-chatbot-toggle')) {
            toggleChat();
        }
    });

    const emailSubmitBtn = document.getElementById('wbai-chatbot-email-submit');
    const nameInput = document.getElementById('wbai-chatbot-name-input');
    const emailInput = document.getElementById('wbai-chatbot-email-input');
    if (emailSubmitBtn && emailInput) {
        emailSubmitBtn.addEventListener('click', function(e) {
            e.preventDefault();
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

    const resetBtn = document.getElementById('wbai-chatbot-reset');
    if (resetBtn) {
        resetBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (confirm(wizardAiChatbotData.resetConfirm || 'Are you sure you want to start a new chat?')) {
                localStorage.removeItem('wbai_chatbot_session_id');
                localStorage.removeItem('wbai_chatbot_history');
                sessionId = 'sess_' + Math.random().toString(36).substr(2, 9);
                localStorage.setItem('wbai_chatbot_session_id', sessionId);
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
        div.className = 'wbai-chatbot-msg wbai-chatbot-' + role;
        if (role === 'ai' || role === 'sys') {
            div.innerHTML = text;
        } else {
            div.innerText = text;
        }
        messagesArea.appendChild(div);
        messagesArea.scrollTop = messagesArea.scrollHeight;
        
        if (save) {
            chatHistory.push({ text: text, role: role });
            localStorage.setItem('wbai_chatbot_history', JSON.stringify(chatHistory));
        }
        
        return div;
    }

    const micBtn = document.getElementById('wbai-chatbot-mic');
    const isLiveMode = chatbot.getAttribute('data-live-mode') === '1';
    
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
        
        const emailRegex = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/;
        if (emailRegex.test(text)) {
            const hint = document.getElementById('wbai-chatbot-email-hint');
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

            const reqData = {
                prompt: text,
                session_id: sessionId,
                current_url: window.location.href,
                current_title: document.title,
                current_content: current_content
            };
            if (wizardAiChatbotData.debugMode) {
                console.debug('Wizard AI Chatbot: Sending request', { url: wizardAiChatbotData.rest_url, data: reqData });
            }

            const response = await fetch(wizardAiChatbotData.rest_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
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
                speakText(data.reply);
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
});
