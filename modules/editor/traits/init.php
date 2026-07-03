<?php
namespace WizardAi\Modules\Editor\Traits;

trait Init {
    public function register_agent_hooks() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_agent_scripts']);
        add_action('admin_footer', [$this, 'render_agent_chatbot']);
        add_action('admin_menu', [$this, 'add_agent_menu']);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueue_agent_scripts_elementor']);
        add_action('elementor/editor/footer', [$this, 'render_agent_chatbot_elementor']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_media_integration_assets']);
    }
}
