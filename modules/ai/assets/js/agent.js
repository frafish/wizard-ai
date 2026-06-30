jQuery(document).ready(function($) {
    const $chatbot = $('#wbai-agent-chatbot');
    const $header = $('#wbai-agent-header');
    const $body = $('#wbai-agent-body');
    const $toggleBtn = $('#wbai-agent-toggle span');
    const $messages = $('#wbai-agent-messages');
    const $prompt = $('#wbai-agent-prompt');
    const $sendBtn = $('#wbai-agent-send');
    
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
        
        $(document).on('mousemove.wbaidrag', function(e) {
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
        
        $(document).on('mouseup.wbaidrag', function(e) {
            isDragging = false;
            $(document).off('mousemove.wbaidrag mouseup.wbaidrag');
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
        if ($(e.target).closest('#wbai-agent-settings-toggle').length) return;
        
        if ($body.is(':visible')) {
            $body.slideUp(300);
            $('#wbai-agent-model-area').slideUp(300);
            $toggleBtn.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            setTimeout(() => $chatbot.addClass('wbai-agent-closed'), 300);
        } else {
            $chatbot.removeClass('wbai-agent-closed');
            $body.slideDown(300);
            $toggleBtn.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            $prompt.focus();
        }
    });

    $('#wbai-agent-settings-toggle').on('click', function(e) {
        e.stopPropagation();
        $('#wbai-agent-model-area').slideToggle(300);
        if (!$body.is(':visible')) {
            $chatbot.removeClass('wbai-agent-closed');
            $body.slideDown(300);
            $toggleBtn.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
        }
    });

    const modelSelect = document.getElementById('wbai-agent-model-select');
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
        const $msg = $('<div>').addClass('wbai-agent-msg').addClass('wbai-agent-' + sender);
        $msg.html(text);
        $messages.append($msg);
        $messages.scrollTop($messages[0].scrollHeight);
    }

    function gatherContext() {
        let context = "I am on the screen: " + wizardAiAgentData.screen + "\n";
        
        // Grab form inputs generically
        const title = $('#title').val() || $('input[name="name"]').val() || '';
        if (title) context += "Title/Name: " + title + "\n";
        
        // Attempt to get Gutenberg content if available
        let content = '';
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/block-editor')) {
            const blocks = wp.data.select('core/block-editor').getBlocks();
            
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
            
            const selectedBlocks = wp.data.select('core/block-editor').getSelectedBlocks();
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
            
            context += "CRITICAL INSTRUCTION: You are interacting directly with the active Gutenberg Block Editor. By default, you MUST NEVER replace the full page content unless explicitly asked. Always prefer to APPEND new blocks (or insert them at the current position) and leave the old content intact. To INSERT or APPEND new blocks, you MUST output the raw Gutenberg HTML inside a ```gutenberg-insert code block. Do NOT use standard ```html blocks. Example:\n```gutenberg-insert\n<!-- wp:image {\"id\":123} -->\n<figure class=\"wp-block-image\"><img src=\"...\" alt=\"\" class=\"wp-image-123\"/></figure>\n<!-- /wp:image -->\n```\nTo completely REPLACE the entire page content, use a ```gutenberg-replace code block (only when requested).\nADVANCED LAYOUTS: If the user requests advanced layouts, animations, or custom CSS styles that are not manageable with standard block controls, you can insert a `core/html` block containing raw HTML, inline `<style>`, and `<script>` tags. Alternatively, if Wizard Blocks is installed, you may use your tools to generate new Wizard Blocks on the fly and insert them into the page (inform the user they may need to reload to see them).\nThe system will automatically parse these specific blocks and apply them to the user's editor in real-time. If you do not use these exact code block wrappers, the content will NOT be inserted.\n";
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

        let $sysMsg = $messages.find('.wbai-agent-sys').last();

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
                model: $('#wbai-agent-model-select').val() || '',
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
                    
                    if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/block-editor')) {
                        let injectedBlocks = [];
                        let hasReplace = aiResponse.includes('```gutenberg-replace') || aiResponse.includes('language-gutenberg-replace');
                        
                        let blockContentToParse = "";
                        
                        // Handle raw markdown blocks
                        const blockRegex = /```(?:gutenberg-insert|gutenberg-replace|gutenberg)\s*([\s\S]*?)```/gi;
                        let match;
                        while ((match = blockRegex.exec(aiResponse)) !== null) {
                            blockContentToParse += match[1] + "\n\n";
                        }
                        
                        // Handle HTML parsed code blocks
                        const htmlRegex = /<pre><code class="language-(?:gutenberg-insert|gutenberg-replace|gutenberg)">([\s\S]*?)<\/code><\/pre>/gi;
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
                            try {
                                const allParsed = wp.blocks.parse(blockContentToParse);
                                for (const block of allParsed) {
                                    if (block.name === 'core/freeform') continue;
                                    injectedBlocks.push(block);
                                }
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

                                if (hasReplace) {
                                    if (postContentClientId) {
                                        blockEditorDispatch.replaceInnerBlocks(postContentClientId, injectedBlocks);
                                    } else {
                                        blockEditorDispatch.resetBlocks(injectedBlocks);
                                    }
                                } else {
                                    const selectedBlockClientId = blockEditorData.getSelectedBlockClientId();
                                    if (selectedBlockClientId) {
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
                                
                                aiResponse = aiResponse.replace(/```(?:gutenberg-insert|gutenberg-replace|html|xml|gutenberg)\s*[\s\S]*?```/gi, "\n*[Successfully applied generated blocks to the page]*\n");
                                aiResponse = aiResponse.replace(/<pre><code class="language-(?:gutenberg-insert|gutenberg-replace|gutenberg|html|xml)">[\s\S]*?<\/code><\/pre>/gi, "\n*[Successfully applied generated blocks to the page]*\n");
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
});
