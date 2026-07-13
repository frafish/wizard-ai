<?php
namespace WizardAi\Modules\Playground\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait Init {
    public function register_playground_hooks() {

        add_action('rest_api_init', [$this, 'register_playground_routes']);
        add_action('admin_init', [$this, 'enable_safe_mode']);
        add_action('upgrader_process_complete', [$this, 'enable_safe_mode']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_playground_scripts']);
    }


    public function register_playground_routes() {
        register_rest_route('wizard-ai/v1', '/ai-chat', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_chat_request'],
            'permission_callback' => [\WizardAi\Modules\Ai\Ai::instance(), 'chat_permission_check']
        ]);
        register_rest_route('wizard-ai/v1', '/toggle-safe-mode', [
            'methods' => 'POST',
            'callback' => [$this, 'toggle_safe_mode'],
            'permission_callback' => function () { return current_user_can('manage_options'); }
        ]);
    }
}
