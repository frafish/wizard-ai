<?php
namespace WizardAi\Modules\Ai\Traits;

trait MediaIntegration {
    public function register_media_integration_hooks() {
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_media_integration_assets']);
    }

    public function enqueue_media_integration_assets() {
        wp_enqueue_script(
            'wizard-ai-media-integration',
            WIZARD_AI_URL . 'modules/ai/assets/js/media-integration.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-data', 'wp-hooks', 'wp-compose'],
            file_exists(WIZARD_AI_PATH . 'modules/ai/assets/js/media-integration.js') ? filemtime(WIZARD_AI_PATH . 'modules/ai/assets/js/media-integration.js') : '1.0',
            true
        );
    }
}
