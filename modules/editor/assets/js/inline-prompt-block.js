(function(wp) {
    const registerBlockType = wp.blocks.registerBlockType;
    const createElement = wp.element.createElement;

    registerBlockType('wizard-ai/prompt', {
        title: 'Wizard AI Generate',
        description: 'Generate a full layout or blocks inline using Wizard AI',
        icon: 'superhero',
        category: 'design',
        attributes: {
            prompt: { type: 'string', default: '' }
        },
        edit: function(props) {
            const prompt = props.attributes.prompt;
            
            const handleGenerate = () => {
                if (!prompt) return;
                
                // Trigger the Wizard AI Agent
                const $chatbot = jQuery('#wai-agent-chatbot');
                const $toggleBtn = jQuery('#wai-agent-toggle span');
                const $prompt = jQuery('#wai-agent-prompt');
                const $sendBtn = jQuery('#wai-agent-send');
                
                if ($chatbot.length && $prompt.length && $sendBtn.length) {
                    if ($chatbot.hasClass('wai-agent-closed')) {
                        $toggleBtn.click();
                    }
                    // Insert a temporary block below the prompt block to act as a placeholder
                    const blockEditorSelect = wp.data.select('core/block-editor');
                    const blockEditorDispatch = wp.data.dispatch('core/block-editor');
                    
                    const currentIndex = blockEditorSelect.getBlockIndex(props.clientId);
                    const placeholderBlock = wp.blocks.createBlock('core/paragraph', { content: '⏳ Generating...' });
                    
                    // Insert immediately after the prompt block
                    blockEditorDispatch.insertBlock(placeholderBlock, currentIndex + 1);
                    
                    // Select the placeholder block so the agent targets it instead of the prompt block
                    blockEditorDispatch.selectBlock(placeholderBlock.clientId);

                    // We explicitly ask the agent to replace the currently selected block (which is now the placeholder!)
                    $prompt.val('Please generate and replace the current block with the following: ' + prompt);
                    $prompt.focus();
                    $sendBtn.click();
                } else {
                    alert('Wizard AI Agent is not active or could not be found. Please ensure the agent is enabled in the settings.');
                }
            };
            
            return createElement(
                'div',
                {
                    className: 'wp-block-wizard-ai-prompt',
                    style: {
                        padding: '16px',
                        background: '#f8fafc',
                        border: '1px solid #e2e8f0',
                        borderRadius: '6px',
                        fontFamily: 'inherit',
                        boxShadow: '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06)'
                    }
                },
                [
                    createElement('div', { style: { display: 'flex', alignItems: 'center', marginBottom: '12px' } }, [
                        createElement('span', { className: 'dashicons dashicons-superhero', style: { color: '#a855f7', marginRight: '8px', fontSize: '24px', width: '24px', height: '24px' } }),
                        createElement('strong', { style: { color: '#0f172a', fontSize: '16px' } }, 'Build with Wizard AI')
                    ]),
                    createElement('p', { style: { color: '#64748b', fontSize: '13px', marginTop: '0', marginBottom: '12px' } }, 
                        'Describe what you want to build. You can generate anything from a single block to a full page layout.'
                    ),
                    createElement('textarea', {
                        value: prompt,
                        onChange: (e) => props.setAttributes({ prompt: e.target.value }),
                        placeholder: 'Example: A landing page header with a title, a subtitle, and two buttons...',
                        style: {
                            width: '100%',
                            padding: '12px',
                            border: '1px solid #cbd5e1',
                            borderRadius: '4px',
                            minHeight: '100px',
                            marginBottom: '12px',
                            fontFamily: 'inherit',
                            fontSize: '14px',
                            resize: 'vertical'
                        }
                    }),
                    createElement('button', {
                        onClick: handleGenerate,
                        style: {
                            background: 'linear-gradient(135deg, #6366f1, #a855f7, #ec4899)',
                            color: 'white',
                            border: 'none',
                            padding: '10px 20px',
                            borderRadius: '4px',
                            cursor: 'pointer',
                            fontWeight: '600',
                            display: 'inline-flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            fontSize: '14px',
                            boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)'
                        }
                    }, 'Generate')
                ]
            );
        },
        save: function() {
            return null; // Dynamic block
        }
    });
})(window.wp);
