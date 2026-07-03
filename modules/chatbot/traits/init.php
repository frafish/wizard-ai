<?php
namespace WizardAi\Modules\Chatbot\Traits;

trait Init {
    public function register_chatbot_hooks() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_chatbot_scripts']);
        add_action('wp_footer', [$this, 'render_chatbot']);
        add_action('rest_api_init', [$this, 'register_chatbot_routes']);
        add_action('admin_menu', [$this, 'add_chatbot_menu']);
    }

    public function register_chatbot_routes() {
        register_rest_route('wizard-blocks/v1', '/chatbot', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_chatbot_request'],
            'permission_callback' => function(\WP_REST_Request $request) {
                $nonce = $request->get_header('X-WP-Nonce');
                if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_rest')) {
                    return new \WP_Error('rest_forbidden', __('Bot activity detected. Invalid nonce.', 'wizard-ai'), ['status' => 403]);
                }
                return true;
            }
        ]);
    }

    public function add_chatbot_menu() {
        add_submenu_page(
            'wizard-ai',
            __('Frontend Chatbot', 'wizard-ai'),
            __('Frontend Chatbot', 'wizard-ai'),
            'manage_options',
            'wizard-ai-chatbot',
            [$this, 'wb_ai_chatbot_page_html']
        );
        add_submenu_page(
            null,
            __('Chatbot Logs', 'wizard-ai'),
            __('Chatbot Logs', 'wizard-ai'),
            'manage_options',
            'wizard-ai-chatbot-logs',
            [$this, 'wb_ai_chatbot_logs_page_html']
        );
    }


}
