<?php
namespace WizardAi\Modules\Chatbot\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.Security.NonceVerification.Recommended
// phpcs:disable WordPress.Security.ValidatedSanitizedInput



trait Ui {
    public function enqueue_chatbot_scripts() {
        if (!get_option('wai_chatbot_enabled', 0)) return;
        if (isset($_REQUEST['elementor-preview']) && $_REQUEST['elementor-preview']) return;
        if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'elementor') return;

        $upload_dir = wp_upload_dir();
        $db_path = $upload_dir['basedir'] . '/wai/rag.sqlite';
        if (!file_exists($db_path)) return;

        wp_enqueue_style('dashicons');
        wp_enqueue_style('wizard-ai-chatbot-style', WIZARD_AI_URL . 'modules/chatbot/assets/css/chatbot.css', [], '1.0.3');
        wp_enqueue_script('wizard-ai-chatbot-script', WIZARD_AI_URL . 'modules/chatbot/assets/js/chatbot.js', ['jquery', 'jquery-ui-draggable', 'jquery-ui-resizable'], '1.0.2', true);
        
        $post_id = get_the_ID();
        if (!$post_id) {
            $post_id = get_option('page_on_front');
        }
        if (!$post_id) {
            $fallback = get_posts(['post_type' => 'any', 'post_status' => 'publish', 'posts_per_page' => 1]);
            $post_id = !empty($fallback) ? $fallback[0]->ID : 0;
        }

        $chatbot_name = get_option('wai_chatbot_name', 'AI Bot');
        if (empty($chatbot_name)) {
            $chatbot_name = 'AI Bot';
        }
        $chatbot_name = apply_filters('wpml_translate_single_string', $chatbot_name, 'wizard-ai', 'chatbot_name');
        
        $current_user = wp_get_current_user();
        $user_name = $current_user->exists() ? $current_user->display_name : '';

        wp_localize_script('wizard-ai-chatbot-script', 'wizardAiChatbotData', [
            'rest_url' => esc_url_raw(rest_url('wizard-ai/v1/chatbot')),
            'nonce' => wp_create_nonce('wp_rest'),
            'debugMode' => (defined('WP_DEBUG') && WP_DEBUG),
            'resetConfirm' => __('Are you sure you want to start a new chat?', 'wizard-ai'),
            'post_id' => $post_id,
            'chatbotName' => esc_html($chatbot_name),
            'userName' => esc_html($user_name)
        ]);
    }

    public function render_chatbot() {
        if (!get_option('wai_chatbot_enabled', 0)) return;
        if (isset($_REQUEST['elementor-preview']) && $_REQUEST['elementor-preview']) return;
        if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'elementor') return;

        $upload_dir = wp_upload_dir();
        $db_path = $upload_dir['basedir'] . '/wai/rag.sqlite';
        if (!file_exists($db_path)) return;
        
        $icon = esc_attr(get_option('wai_chatbot_icon', 'dashicons-format-chat'));
        $color = esc_attr(get_option('wai_chatbot_color', '#2271b1'));
        $position = get_option('wai_chatbot_position', 'bottom-right');
        
        $chatbot_name = get_option('wai_chatbot_name', '');
        $chatbot_name = apply_filters('wpml_translate_single_string', $chatbot_name, 'wizard-ai', 'chatbot_name');
        $chatbot_name = esc_html($chatbot_name);

        $greeting = get_option('wai_chatbot_greeting', 'Hello! How can I help you today?');
        $greeting = apply_filters('wpml_translate_single_string', $greeting, 'wizard-ai', 'chatbot_greeting');
        $greeting = esc_html($greeting);
        
        $pos_class = $position === 'bottom-left' ? 'wai-pos-bottom-left' : 'wai-pos-bottom-right';
        ?>
        <style>
            #wai-chatbot-header, #wai-chatbot-send, #wai-chatbot-email-submit { background-color: <?php echo esc_attr($color); ?> !important; }
            .wai-privacy-link { color: <?php echo esc_attr($color); ?> !important; }
        </style>
        <div id="wai-chatbot" class="wai-chatbot-closed <?php echo esc_attr($pos_class); ?>" data-live-mode="1">
            <div id="wai-chatbot-header">
                <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                <?php if (!empty($chatbot_name)): ?>
                    <span class="wai-chatbot-title-text"><?php echo esc_html($chatbot_name); ?></span>
                <?php endif; ?>
                <button id="wai-chatbot-reset" title="<?php esc_attr_e('Reset Chat', 'wizard-ai'); ?>"><span class="dashicons dashicons-update-alt"></span></button>
                <button id="wai-chatbot-toggle"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
            </div>
            <div id="wai-chatbot-body">
                <div id="wai-chatbot-messages">
                    <div class="wai-chatbot-msg wai-chatbot-sys"><?php echo wp_kses_post($greeting); ?></div>
                </div>
                <?php 
                $contact_msg = get_option('wai_chatbot_contact_msg', '');
                if (!is_user_logged_in() && !empty($contact_msg)): ?>
                    <div id="wai-chatbot-email-hint">
                        <div class="wai-chatbot-contact-msg"><?php echo esc_html($contact_msg); ?></div>
                        <div class="wai-chatbot-email-form">
                            <input type="text" id="wai-chatbot-name-input" class="wai-chatbot-email-input" placeholder="<?php esc_attr_e('Your Name', 'wizard-ai'); ?>">
                            <input type="email" id="wai-chatbot-email-input" class="wai-chatbot-email-input" placeholder="<?php esc_attr_e('Your Email', 'wizard-ai'); ?>">
                            <button id="wai-chatbot-email-submit" class="button"><?php esc_html_e('Send', 'wizard-ai'); ?></button>
                        </div>
                    </div>
                <?php endif; ?>
                <div id="wai-chatbot-input-area">
                    <input type="text" id="wai-chatbot-hp" value="" tabindex="-1" autocomplete="off">
                    <div style="position: relative; flex: 1; display: flex;">
                        <textarea id="wai-chatbot-prompt" placeholder="<?php esc_attr_e('Ask a question...', 'wizard-ai'); ?>"></textarea>
                        <button id="wai-chatbot-mic" type="button" title="<?php esc_attr_e('Speech to Text', 'wizard-ai'); ?>">
                            <span class="dashicons dashicons-microphone"></span>
                        </button>
                    </div>
                    <button id="wai-chatbot-send" class="button button-primary"><span class="dashicons dashicons-controls-play"></span></button>
                </div>
                <?php if (get_option('wai_chatbot_gdpr_required', 1)): ?>
                <div id="wai-chatbot-gdpr-notice">
                    <?php 
                    $default_gdpr_text = __('By chatting, you agree to our processing of conversation logs to assist with your request.', 'wizard-ai');
                    $custom_gdpr_text = get_option('wai_chatbot_gdpr_text', $default_gdpr_text);
                    $custom_gdpr_text = apply_filters('wpml_translate_single_string', $custom_gdpr_text, 'wizard-ai', 'chatbot_gdpr_text');
                    
                    if (!is_user_logged_in() && get_option('wai_chatbot_track_sessions', 0)) {
                        echo '<label class="wai-chatbot-gdpr-label">';
                        echo '<input type="checkbox" id="wai-chatbot-gdpr-consent">';
                        echo '<span>' . wp_kses_post($custom_gdpr_text) . '</span></label>';
                    } else {
                        echo wp_kses_post($custom_gdpr_text);
                    }
                    ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

}
