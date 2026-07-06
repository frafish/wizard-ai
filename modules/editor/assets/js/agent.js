jQuery(document).ready(function($) {
    const $chatbot = $('#wai-agent-chatbot');
    const $header = $('#wai-agent-header');
    const $body = $('#wai-agent-body');
    const $toggleBtn = $('#wai-agent-toggle span');
    const $messages = $('#wai-agent-messages');
    const $prompt = $('#wai-agent-prompt');
    const $sendBtn = $('#wai-agent-send');
    
    let isProcessing = false;
    let conversationId = '';
    
    let isDragging = false;
    let hasDragged = false;
    let dragStartX, dragStartY;
    let initialX, initialY;

    $header.on('mousedown', function(e) {
        if ($(e.target).closest('button').length) return;
        
        isDragging = true;
        hasDragged = false;
        dragStartX = e.clientX;
        dragStartY = e.clientY;
        
        let rect = $chatbot[0].getBoundingClientRect();
        initialX = rect.left;
        initialY = rect.top;
        
        $(document).on('mousemove.waidrag', function(e) {
            if (!isDragging) return;
            const dx = e.clientX - dragStartX;
            const dy = e.clientY - dragStartY;
            
            if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
                if (!hasDragged) {
                    hasDragged = true;
                    $chatbot.css({
                        transition: 'none',
                        right: 'auto',
                        bottom: 'auto',
                        left: initialX + 'px',
                        top: initialY + 'px',
                        margin: 0
                    });
                }
                
                let newLeft = initialX + dx;
                let newTop = initialY + dy;
                
                const maxX = window.innerWidth - $chatbot.outerWidth();
                const maxY = window.innerHeight - $chatbot.outerHeight();
                
                newLeft = Math.max(0, Math.min(newLeft, maxX));
                newTop = Math.max(0, Math.min(newTop, maxY));
                
                $chatbot.css({
                    left: newLeft + 'px',
                    top: newTop + 'px'
                });
            }
        });
        
        $(document).on('mouseup.waidrag', function(e) {
            isDragging = false;
            $(document).off('mousemove.waidrag mouseup.waidrag');
            if (hasDragged) {
                setTimeout(() => hasDragged = false, 100);
                
                // Smart anchoring: bind to closest edges so it expands inward
                let rect = $chatbot[0].getBoundingClientRect();
                let ww = window.innerWidth;
                let wh = window.innerHeight;
                
                let css = {};
                
                if (rect.left > ww / 2) {
                    css.right = (ww - rect.right) + 'px';
                    css.left = 'auto';
                } else {
                    css.left = rect.left + 'px';
                    css.right = 'auto';
                }
                
                if (rect.top > wh / 2) {
                    css.bottom = (wh - rect.bottom) + 'px';
                    css.top = 'auto';
                } else {
                    css.top = rect.top + 'px';
                    css.bottom = 'auto';
                }
                
                $chatbot.css(css);
            }
            $chatbot.css('transition', 'width 0.3s ease, border-radius 0.3s ease');
        });
    });

    $header.on('click', function(e) {
        if (hasDragged) return;
        if ($(e.target).closest('#wai-agent-settings-toggle').length) return;
        
        if ($body.is(':visible')) {
            $body.slideUp(300);
            $('#wai-agent-model-area').slideUp(300);
            $toggleBtn.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            setTimeout(() => $chatbot.addClass('wai-agent-closed'), 300);
        } else {
            $chatbot.removeClass('wai-agent-closed');
            $body.slideDown(300);
            $toggleBtn.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            $prompt.focus();
        }
    });

    $('#wai-agent-settings-toggle').on('click', function(e) {
        e.stopPropagation();
        $('#wai-agent-model-area').slideToggle(300);
        if (!$body.is(':visible')) {
            $chatbot.removeClass('wai-agent-closed');
            $body.slideDown(300);
            $toggleBtn.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
        }
    });

    const modelSelect = document.getElementById('wai-agent-model-select');
    if (modelSelect) {
        fetch(wizardAiAgentData.rest_url.replace('/ai-chat', '/ai-models'), {
            headers: { 'X-WP-Nonce': wizardAiAgentData.nonce }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.models) {
                modelSelect.innerHTML = '';
                if (typeof data.models === 'object' && Object.keys(data.models).length > 0) {
                    Object.entries(data.models).forEach(([groupName, groupModels]) => {
                        const optgroup = document.createElement('optgroup');
                        optgroup.label = groupName;
                        const sortedIds = Object.keys(groupModels).sort();
                        sortedIds.forEach(id => {
                            const opt = document.createElement('option');
                            opt.value = id;
                            opt.textContent = groupModels[id];
                            if (id === wizardAiAgentData.preferredModel) {
                                opt.selected = true;
                            }
                            optgroup.appendChild(opt);
                        });
                        modelSelect.appendChild(optgroup);
                    });
                }
            } else {
                modelSelect.innerHTML = '<option value="">Failed to load models</option>';
            }
        })
        .catch(err => {
            modelSelect.innerHTML = '<option value="">Error loading models</option>';
        });
    }

    function addMessage(text, sender) {
        if (sender === 'ai') {
            text = text.replace(/\n/g, '<br>');
            text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');
        }
        const $msg = $('<div>').addClass('wai-agent-msg').addClass('wai-agent-' + sender);
        $msg.html(text);
        $messages.append($msg);
        $messages.scrollTop($messages[0].scrollHeight);
    }

    let lastSelectedBlockClientIds = [];
    if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
        wp.data.subscribe(() => {
            const blockEditor = wp.data.select('core/block-editor');
            if (blockEditor) {
                const selected = blockEditor.getSelectedBlockClientIds();
                if (selected && selected.length > 0) {
                    lastSelectedBlockClientIds = selected;
                }
            }
        });
    }

    function gatherContext() {
        let context = "I am on the screen: " + wizardAiAgentData.screen + "\n";
        
        // Grab form inputs generically
        const title = $('#title').val() || $('input[name="name"]').val() || '';
        if (title) context += "Title/Name: " + title + "\n";
        
        // Attempt to get Gutenberg content if available
        let content = '';
        if (window.elementor) {
            context += "CRITICAL INSTRUCTION: You are interacting directly with the active Elementor Editor. To INSERT or APPEND new widgets or sections, you MUST output a JSON representation of the Elementor models inside an ```elementor-insert code block. Do NOT use standard ```json blocks. The JSON must be an array of Elementor models or a single model. Example:\n```elementor-insert\n{\n  \"id\": \"abc1234\",\n  \"elType\": \"section\",\n  \"elements\": [\n    {\n      \"id\": \"def5678\",\n      \"elType\": \"column\",\n      \"elements\": [\n        {\n          \"id\": \"xyz9876\",\n          \"elType\": \"widget\",\n          \"widgetType\": \"heading\",\n          \"settings\": { \"title\": \"Hello World\" }\n        }\n      ]\n    }\n  ]\n}\n```\nCRITICAL WIDGET PROPERTIES:\n- For 'text-editor' widgets, the HTML text MUST be placed in `settings.editor` (e.g. `\"settings\": { \"editor\": \"<p>My text</p>\" }`). Do not use 'content' or 'text'.\n- For 'image' widgets, use `settings.image.url` (e.g. `\"settings\": { \"image\": { \"url\": \"https://example.com/image.jpg\" } }`).\nTo completely REPLACE the entire page content, use an ```elementor-replace code block.\nThe system will parse this JSON and inject it into the editor in real-time.\n";
        } else if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/block-editor')) {
            const blockEditorData = wp.data.select('core/block-editor');
            const blocks = blockEditorData.getBlocks();
            
            const passTheme = document.getElementById('wai-agent-pass-theme');
            if (passTheme && passTheme.checked) {
                const settings = blockEditorData.getSettings();
                if (settings) {
                    let themeContext = "THEME STYLES (theme.json):\n";
                    if (settings.colors && settings.colors.length > 0) {
                        themeContext += "- Colors: " + settings.colors.map(c => `${c.name} (has-${c.slug}-color)`).join(", ") + "\n";
                    }
                    if (settings.fontSizes && settings.fontSizes.length > 0) {
                        themeContext += "- Font Sizes: " + settings.fontSizes.map(f => `${f.name} (has-${f.slug}-font-size)`).join(", ") + "\n";
                    }
                    if (settings.spacingSizes && settings.spacingSizes.length > 0) {
                        themeContext += "- Spacing Sizes: " + settings.spacingSizes.map(s => `${s.name} (${s.slug})`).join(", ") + "\n";
                    }
                    context += themeContext + "\n";
                }
            }

            
            function findPostContentClientId(blocksList) {
                if (!blocksList) return null;
                for (const block of blocksList) {
                    if (block.name === 'core/post-content') return block.clientId;
                    if (block.innerBlocks && block.innerBlocks.length > 0) {
                        const found = findPostContentClientId(block.innerBlocks);
                        if (found) return found;
                    }
                }
                return null;
            }
            
            if (blocks && blocks.length > 0) {
                const postContentId = findPostContentClientId(blocks);
                const targetBlocks = postContentId ? wp.data.select('core/block-editor').getBlocks(postContentId) : blocks;
                content = wp.blocks.serialize(targetBlocks);
            }
            if (content) context += "Content:\n" + content + "\n\n";
            
            let selectedBlocks = [];
            if (lastSelectedBlockClientIds.length > 0) {
                selectedBlocks = lastSelectedBlockClientIds.map(id => wp.data.select('core/block-editor').getBlock(id)).filter(Boolean);
            }
            
            if (selectedBlocks && selectedBlocks.length > 0) {
                const selectedContent = wp.blocks.serialize(selectedBlocks);
                context += "CURRENTLY SELECTED BLOCKS (The user has highlighted these blocks in the editor. If the user asks to 'change this' or 'rewrite this', they are referring to these blocks. Please prioritize modifying them):\n" + selectedContent + "\n\n";
            }
            
            if (wp.blocks && wp.blocks.getBlockTypes) {
                const availableBlocks = wp.blocks.getBlockTypes().map(b => {
                    let attrsInfo = [];
                    if (b.attributes) {
                        for (const [key, val] of Object.entries(b.attributes)) {
                            attrsInfo.push(`${key}(${val.type || 'any'})`);
                        }
                    }
                    let supportsInfo = [];
                    if (b.supports) {
                        if (b.supports.color) supportsInfo.push('color');
                        if (b.supports.spacing) supportsInfo.push('spacing');
                        if (b.supports.typography) supportsInfo.push('typography');
                        if (b.supports.align) supportsInfo.push('align');
                    }
                    let detail = '- ' + b.name;
                    if (attrsInfo.length) detail += ` | attrs: {${attrsInfo.join(', ')}}`;
                    if (supportsInfo.length) detail += ` | supports: [${supportsInfo.join(', ')}]`;
                    return detail;
                }).join('\n');
                context += "AVAILABLE BLOCK TYPES & THEIR PARAMETERS (Use these attributes in the JSON comment of the block):\n" + availableBlocks + "\n\n";
            }
            
            try {
                const patterns = wp.data && wp.data.select('core') ? wp.data.select('core').getBlockPatterns() : null;
                if (patterns && patterns.length) {
                    const availablePatterns = patterns.map(p => p.name + ' ("' + p.title + '")').join(', ');
                    context += "AVAILABLE PATTERNS (insert using <!-- wp:pattern {\"slug\":\"PATTERN_NAME\"} /-->):\n" + availablePatterns + "\n\n";
                }
            } catch (e) {}
            
            context += "CRITICAL INSTRUCTION: You are interacting directly with the active Gutenberg Block Editor. By default, you MUST NEVER replace the full page content unless explicitly asked. Always prefer to APPEND new blocks (or insert them at the current position) and leave the old content intact. To INSERT or APPEND new blocks, you MUST output the raw Gutenberg HTML inside a ```gutenberg-insert code block. Do NOT use standard ```html blocks.\n\nGutenberg block HTML is strictly validated. The inner HTML MUST perfectly match the block wrapper comments. For complex layouts like columns, you MUST use the exact structure with the `wp-block-columns` and `wp-block-column` wrapper divs.\nTo completely REPLACE the entire page content, use a ```gutenberg-replace code block.\nTo EDIT and REPLACE the user's currently selected block(s) with your new version, use a ```gutenberg-edit code block. \nIMPORTANT: ALWAYS use these markdown code blocks. NEVER use manage-posts or update tools to modify the current post if the user wants to update the blocks in the editor visually. Just output the blocks inside ```gutenberg-edit or ```gutenberg-insert and the editor will automatically apply them!\nIf you cannot perfectly remember the exact HTML wrapper for a complex core block, it is safer to use a `core/html` block and insert standard raw HTML.\n";
        } else {
            content = $('#content').val() || $('#description').val() || '';
            if (content) context += "Content/Description:\n" + content + "\n";
        }
        
        return context;
    }

    async function sendPrompt(text = null, isAuto = false) {
        if (!isAuto) {
            text = $prompt.val().trim();
            if (!text || isProcessing) return;
            addMessage(text, 'user');
            $prompt.val('');
            isProcessing = true;
            $sendBtn.prop('disabled', true).find('span').removeClass('dashicons-controls-play').addClass('dashicons-update').css('animation', 'spin 1s linear infinite');
            addMessage('Thinking...', 'sys');
        }

        let $sysMsg = $messages.find('.wai-agent-sys').last();

        try {
            if (wizardAiAgentData.debugMode) console.debug("[Wizard AI Agent] Gathering context...");
            const sessionContext = gatherContext();
            
            let objectId = $('#post_ID').val() || $('#tag_ID').val() || $('#user_id').val() || '';
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                const currentPostId = wp.data.select('core/editor').getCurrentPostId();
                if (currentPostId) objectId = currentPostId;
            }

            const requestBody = {
                prompt: isAuto ? "" : text,
                session_context: sessionContext,
                conversation_id: conversationId,
                model: $('#wai-agent-model-select').val() || '',
                execute_tools: isAuto,
                object_id: objectId,
                object_type: wizardAiAgentData.screen
            };
            
            if (wizardAiAgentData.debugMode) console.debug("[Wizard AI Agent] Sending API request:", requestBody);

            const response = await fetch(wizardAiAgentData.rest_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wizardAiAgentData.nonce
                },
                body: JSON.stringify(requestBody)
            });

            if (wizardAiAgentData.debugMode) console.debug("[Wizard AI Agent] HTTP Response Status:", response.status);
            
            let data;
            const textResponse = await response.text();
            
            try {
                data = JSON.parse(textResponse);
                if (wizardAiAgentData.debugMode) console.debug("[Wizard AI Agent] JSON Response Data:", data);
            } catch (jsonError) {
                console.error("[Wizard AI] Failed to parse JSON response. Raw text:", textResponse);
                throw new Error("Server returned an invalid JSON response. Please check the browser console.");
            }
            
            if (data.success) {
                if (data.conversation_id) {
                    conversationId = data.conversation_id;
                }
                
                if (data.action === 'tool_calls') {
                    $sysMsg.html('Executing actions...');
                    // Automatically continue processing
                    return sendPrompt(null, true);
                } else {
                    $sysMsg.remove();
                    let aiResponse = data.response || "Done.";
                    
                    if (window.elementor) {
                        let jsonContent = "";
                        const elRegex = /```(?:[a-zA-Z0-9-]*)\s*([\s\S]*?)```/gi;
                        let match;
                        while ((match = elRegex.exec(aiResponse)) !== null) {
                            jsonContent += match[1] + "\n";
                        }
                        
                        const htmlRegex = /<pre><code[^>]*>([\s\S]*?)<\/code><\/pre>/gi;
                        while ((match = htmlRegex.exec(aiResponse)) !== null) {
                            jsonContent += match[1].replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#039;/g, "'") + "\n";
                        }
                        
                        if (!jsonContent.trim()) {
                            // Fallback: maybe the AI just returned pure JSON without markdown backticks
                            let strippedResponse = aiResponse.trim();
                            if (strippedResponse.startsWith('{') || strippedResponse.startsWith('[')) {
                                jsonContent = strippedResponse;
                            }
                        }
                        
                        if (jsonContent.trim()) {
                            console.log("[Wizard AI] Parsed Elementor JSON Content:", jsonContent);
                            try {
                                const models = JSON.parse(jsonContent.trim());
                                const items = Array.isArray(models) ? models : [models];
                                console.log("[Wizard AI] Elementor models to inject:", items);
                                
                                if (aiResponse.toLowerCase().includes('```elementor-replace') || aiResponse.toLowerCase().includes('language-elementor-replace')) {
                                    if (window.elementor && window.elementor.getPreviewView) {
                                        const previewView = window.elementor.getPreviewView();
                                        if (previewView.collection) {
                                            previewView.collection.reset();
                                        }
                                    }
                                }
                                
                                for (const sectionModel of items) {
                                    if (window.$e && window.$e.run) {
                                        window.$e.run('document/elements/create', {
                                            model: sectionModel,
                                            container: window.elementor.getPreviewContainer()
                                        });
                                    } else {
                                        window.elementor.getPreviewView().addChildModel(sectionModel);
                                    }
                                }
                                aiResponse = aiResponse.replace(/```(?:[a-zA-Z0-9-]*)\s*[\s\S]*?```/gi, "<br><em>[Successfully applied Elementor widgets to the page]</em><br>");
                                aiResponse = aiResponse.replace(/<pre><code[^>]*>[\s\S]*?<\/code><\/pre>/gi, "<br><em>[Successfully applied Elementor widgets to the page]</em><br>");
                            } catch (err) {
                                console.error("[Wizard AI] Error parsing Elementor JSON:", err);
                            }
                        } else {
                            console.log("[Wizard AI] No Elementor code blocks matched. aiResponse was:", aiResponse);
                        }
                    } else if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/block-editor')) {
                        let injectedBlocks = [];
                        let hasReplace = aiResponse.includes('```gutenberg-replace') || aiResponse.includes('language-gutenberg-replace');
                        
                        let blockContentToParse = "";
                        
                        // Handle raw markdown blocks
                        const blockRegex = /```(?:[a-zA-Z0-9-]*)\s*([\s\S]*?)```/gi;
                        let match;
                        while ((match = blockRegex.exec(aiResponse)) !== null) {
                            blockContentToParse += match[1] + "\n\n";
                        }
                        
                        // Handle HTML parsed code blocks
                        const htmlRegex = /<pre><code[^>]*>([\s\S]*?)<\/code><\/pre>/gi;
                        while ((match = htmlRegex.exec(aiResponse)) !== null) {
                            let decoded = match[1]
                                .replace(/&lt;/g, '<')
                                .replace(/&gt;/g, '>')
                                .replace(/&amp;/g, '&')
                                .replace(/&quot;/g, '"')
                                .replace(/&#039;/g, "'");
                            blockContentToParse += decoded + "\n\n";
                        }
                        
                        if (!blockContentToParse.trim()) {
                            if (aiResponse.includes('<!-- wp:') || aiResponse.includes('&lt;!-- wp:')) {
                                blockContentToParse = aiResponse
                                    .replace(/&lt;/g, '<')
                                    .replace(/&gt;/g, '>')
                                    .replace(/&amp;/g, '&')
                                    .replace(/&quot;/g, '"')
                                    .replace(/&#039;/g, "'");
                            }
                        }

                        if (blockContentToParse.trim()) {
                            console.log("[Wizard AI] Parsed Gutenberg Content to Parse:", blockContentToParse);
                            try {
                                const allParsed = wp.blocks.parse(blockContentToParse);
                                console.log("[Wizard AI] wp.blocks.parse result:", allParsed);
                                for (const block of allParsed) {
                                    if (block.name === 'core/freeform') {
                                        const content = typeof block.originalContent === 'string' ? block.originalContent : (block.attributes ? block.attributes.content : '');
                                        if (!content || content.trim() === '') {
                                            continue;
                                        }
                                    }
                                    
                                    // Even if block.isValid is false, we keep it! Gutenberg will show a "Attempt Block Recovery" button,
                                    // which is much better than silently skipping the block and confusing the user.
                                    if (block.isValid === false) {
                                        console.warn("[Wizard AI] Parsed block is marked invalid, but inserting anyway for recovery:", block.name);
                                    }
                                    
                                    injectedBlocks.push(block);
                                }
                                console.log("[Wizard AI] injectedBlocks after filter:", injectedBlocks);
                            } catch (err) {
                                console.error("[Wizard AI] Error during native block parsing:", err);
                            }
                        }
                        
                        if (injectedBlocks.length > 0) {
                            try {
                                const blockEditorData = wp.data.select('core/block-editor');
                                const blockEditorDispatch = wp.data.dispatch('core/block-editor');
                                
                                function findPostContentClientId(blocks) {
                                    if (!blocks) return null;
                                    for (const block of blocks) {
                                        if (block.name === 'core/post-content') return block.clientId;
                                        if (block.innerBlocks && block.innerBlocks.length > 0) {
                                            const found = findPostContentClientId(block.innerBlocks);
                                            if (found) return found;
                                        }
                                    }
                                    return null;
                                }
                                
                                const postContentClientId = findPostContentClientId(blockEditorData.getBlocks());

                                if (aiResponse.toLowerCase().includes('```gutenberg-replace') || aiResponse.toLowerCase().includes('language-gutenberg-replace')) {
                                    if (postContentClientId) {
                                        blockEditorDispatch.replaceInnerBlocks(postContentClientId, injectedBlocks);
                                    } else {
                                        blockEditorDispatch.resetBlocks(injectedBlocks);
                                    }
                                } else if (aiResponse.toLowerCase().includes('```gutenberg-edit') || aiResponse.toLowerCase().includes('language-gutenberg-edit')) {
                                    if (lastSelectedBlockClientIds && lastSelectedBlockClientIds.length > 0) {
                                        blockEditorDispatch.replaceBlocks(lastSelectedBlockClientIds, injectedBlocks);
                                    } else {
                                        blockEditorDispatch.insertBlocks(injectedBlocks);
                                    }
                                } else {
                                    if (lastSelectedBlockClientIds && lastSelectedBlockClientIds.length > 0) {
                                        const selectedBlockClientId = lastSelectedBlockClientIds[0];
                                        const blockIndex = blockEditorData.getBlockIndex(selectedBlockClientId);
                                        const rootClientId = blockEditorData.getBlockRootClientId(selectedBlockClientId);
                                        blockEditorDispatch.insertBlocks(injectedBlocks, blockIndex + 1, rootClientId);
                                    } else {
                                        if (postContentClientId) {
                                            const innerCount = blockEditorData.getBlockCount(postContentClientId);
                                            blockEditorDispatch.insertBlocks(injectedBlocks, innerCount, postContentClientId);
                                        } else {
                                            blockEditorDispatch.insertBlocks(injectedBlocks);
                                        }
                                    }
                                }
                                
                                aiResponse = aiResponse.replace(/```(?:[a-zA-Z0-9-]*)\s*[\s\S]*?```/gi, "<br><em>[Successfully applied generated blocks to the page]</em><br>");
                                aiResponse = aiResponse.replace(/<pre><code[^>]*>[\s\S]*?<\/code><\/pre>/gi, "<br><em>[Successfully applied generated blocks to the page]</em><br>");
                                aiResponse = aiResponse.replace(/<!--\s*wp:[\s\S]*?\/wp:[a-zA-Z0-9-]+\s*-->/g, '');
                                aiResponse = aiResponse.replace(/<!--\s*wp:[\s\S]*?\/-->/g, '');
                                aiResponse = aiResponse.replace(/&lt;!--\s*wp:[\s\S]*?\/wp:[a-zA-Z0-9-]+\s*--&gt;/g, '');
                                aiResponse = aiResponse.replace(/&lt;!--\s*wp:[\s\S]*?\/--&gt;/g, '');
                            } catch (err) {
                                console.error("[Wizard AI] Error injecting blocks into editor:", err);
                            }
                        }
                    }

                    addMessage(aiResponse, 'ai');
                }
            } else {
                $sysMsg.remove();
                console.warn("[Wizard AI] API returned an error:", data.message);
                addMessage("Error: " + (data.message || "Unknown error"), 'sys');
            }
        } catch (error) {
            console.error("[Wizard AI] Caught execution error:", error);
            $sysMsg.remove();
            addMessage("Request failed: " + error.message, 'sys');
            isAuto = false; // Force UI to reset in finally block
        } finally {
            if (!isAuto || (isAuto && !isProcessing)) {
                // If it's a final step, or an error happened, reset UI
                isProcessing = false;
                $sendBtn.prop('disabled', false).find('span').removeClass('dashicons-update').addClass('dashicons-controls-play').css('animation', 'none');
                $prompt.focus();
            }
        }
    }

    $sendBtn.on('click', function() { sendPrompt(); });

    $prompt.on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendPrompt();
        }
    });

    // Elementor Panel Integration (Angie-inspired UX)
    if (typeof window.elementor !== 'undefined') {
        window.elementor.on('panel:init', function() {
            setTimeout(function() {
                const $elementsWrapper = $('#elementor-panel-elements-wrapper');
                if ($elementsWrapper.length) {
                    const $aiButton = $('<div class="elementor-element-wrapper" style="width: 100%; padding: 10px; cursor: pointer; text-align: center; background: linear-gradient(135deg, #6366f1, #a855f7, #ec4899); color: white; border-radius: 4px; margin-bottom: 15px; font-weight: bold; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); transition: transform 0.2s, box-shadow 0.2s;"><span class="dashicons dashicons-superhero" style="margin-right: 8px; vertical-align: middle;"></span> <span style="vertical-align: middle;">Build with Wizard AI</span></div>');
                    
                    $aiButton.on('mouseenter', function() {
                        $(this).css('transform', 'translateY(-2px)');
                        $(this).css('box-shadow', '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)');
                    }).on('mouseleave', function() {
                        $(this).css('transform', 'none');
                        $(this).css('box-shadow', '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)');
                    });

                    $aiButton.on('click', function() {
                        if ($chatbot.hasClass('wai-agent-closed')) {
                            $toggleBtn.click();
                        }
                        
                        $prompt.focus();
                        
                        // Add a subtle highlight animation to the prompt box to draw attention
                        $prompt.css('transition', 'box-shadow 0.3s ease, border-color 0.3s ease');
                        $prompt.css('box-shadow', '0 0 0 3px rgba(168, 85, 247, 0.4)');
                        $prompt.css('border-color', '#a855f7');
                        setTimeout(() => {
                            $prompt.css('box-shadow', 'none');
                            $prompt.css('border-color', '#ccd0d4');
                        }, 1500);
                    });
                    
                    $elementsWrapper.prepend($aiButton);
                }
            }, 1000); // Small delay to ensure Elementor DOM is fully hydrated
        });
    }
});
