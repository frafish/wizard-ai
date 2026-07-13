<?php
namespace WizardAi\Modules\Editor\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait InlinePrompt {
    public function register_inline_prompt_hooks() {
        add_action('init', [$this, 'register_inline_prompt_block']);
    }

    public function register_inline_prompt_block() {
        if (!function_exists('register_block_type')) {
            return;
        }

        wp_register_script(
            'wizard-ai-inline-prompt-block',
            WIZARD_AI_URL . 'modules/editor/assets/js/inline-prompt-block.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-data'],
            file_exists(WIZARD_AI_PATH . 'modules/editor/assets/js/inline-prompt-block.js') ? filemtime(WIZARD_AI_PATH . 'modules/editor/assets/js/inline-prompt-block.js') : '1.0'
        );

        register_block_type('wizard-ai/prompt', [
            'editor_script' => 'wizard-ai-inline-prompt-block',
            'attributes' => [
                'prompt' => [
                    'type' => 'string',
                    'default' => ''
                ]
            ]
        ]);
    }
}
