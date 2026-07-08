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
        add_action('rest_api_init', [$this, 'register_editor_api_routes']);
    }

    public function register_editor_api_routes() {
        register_rest_route('wizard-ai/v1', '/editor-pull', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_editor_pull'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ]);
    }

    public function handle_editor_pull(\WP_REST_Request $request) {
        $transient_key = 'wai_editor_push_' . get_current_user_id();
        $blocks = get_transient($transient_key);
        if ($blocks) {
            delete_transient($transient_key);
            return rest_ensure_response(['success' => true, 'blocks' => $blocks]);
        }
        return rest_ensure_response(['success' => false]);
    }
}
