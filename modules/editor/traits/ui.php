<?php
namespace WizardAi\Modules\Editor\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.Security.NonceVerification.Recommended
// phpcs:disable WordPress.Security.ValidatedSanitizedInput



trait Ui {
    public function enqueue_agent_scripts_elementor() {
        $this->enqueue_agent_scripts('', true);
    }

    public function render_agent_chatbot_elementor() {
        $this->render_agent_chatbot(true);
    }

    public function add_agent_menu() {
        add_submenu_page(
            'wizard-ai',
            __('Agent Settings', 'wizard-ai'),
            __('Editor Agent', 'wizard-ai'),
            'manage_options',
            'wizard-ai-agent',
            [$this, 'wb_ai_agent_page_html']
        );
    }

    public function enqueue_media_integration_assets() {
        wp_enqueue_script(
            'wizard-ai-media-integration',
            WIZARD_AI_URL . 'modules/editor/assets/js/media-integration.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-data', 'wp-hooks', 'wp-compose'],
            file_exists(WIZARD_AI_PATH . 'modules/editor/assets/js/media-integration.js') ? filemtime(WIZARD_AI_PATH . 'modules/editor/assets/js/media-integration.js') : '1.0',
            true
        );
    }


    private function should_render_agent() {
        if (!get_option('wai_agent_enabled', 0)) return false;

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen) return false;

        $user = wp_get_current_user();
        if (empty($user)) return false;

        $selected_roles = get_option('wai_agent_roles', ['administrator']);
        $has_role = false;
        if (!empty($user->roles)) {
            foreach ($user->roles as $role) {
                if (in_array($role, $selected_roles)) {
                    $has_role = true;
                    break;
                }
            }
        }
        if (!$has_role) return false;

        $selected_post_types = get_option('wai_agent_post_types', ['post', 'page']);
        $selected_taxonomies = get_option('wai_agent_taxonomies', ['category', 'post_tag']);
        $enable_users = get_option('wai_agent_enable_users', 1);

        if ($screen->base === 'post') {
            return in_array($screen->post_type, $selected_post_types);
        } elseif ($screen->base === 'term' || $screen->base === 'edit-tags') {
            return in_array($screen->taxonomy, $selected_taxonomies);
        } elseif (in_array($screen->base, ['user', 'profile', 'user-edit'])) {
            return $enable_users ? true : false;
        }

        return false;
    }

    public function enqueue_agent_scripts($hook = '', $force = false) {
        if (!$force && !$this->should_render_agent()) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $screen_base = $screen ? $screen->base : 'elementor';

        wp_enqueue_style('wizard-ai-agent-style', WIZARD_AI_URL . 'modules/editor/assets/css/agent.css', [], '1.0.0');
        wp_enqueue_script('wizard-ai-agent-script', WIZARD_AI_URL . 'modules/editor/assets/js/agent.js', ['jquery'], filemtime(WIZARD_AI_PATH . 'modules/editor/assets/js/agent.js'), true);
        
        wp_localize_script('wizard-ai-agent-script', 'wizardAiAgentData', [
            'rest_url' => esc_url_raw(rest_url('wizard-ai/v1/ai-chat')),
            'nonce' => wp_create_nonce('wp_rest'),
            'screen' => $screen_base,
            'preferredModel' => get_user_meta(get_current_user_id(), '_wai_preferred_model', true),
            'debugMode' => (defined('WP_DEBUG') && WP_DEBUG)
        ]);
    }

    public function render_agent_chatbot($force = false) {
        if (!$force && !$this->should_render_agent()) {
            return;
        }
        ?>
        <div id="wai-agent-chatbot" class="wai-agent-closed">
            <div id="wai-agent-header">
                <span class="dashicons dashicons-superhero"></span> <span class="wai-agent-title-text">Wizard AI Agent</span>
                <button id="wai-agent-settings-toggle" title="Settings" style="margin-left: auto; margin-right: 5px; background: none; border: none; color: #fff; cursor: pointer;"><span class="dashicons dashicons-admin-generic"></span></button>
                <button id="wai-agent-toggle" style="background: none; border: none; color: #fff; cursor: pointer;"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
            </div>
            <div id="wai-agent-model-area" style="display: none; padding: 10px; background: #fff; border-bottom: 1px solid #ccd0d4;">
                <select id="wai-agent-model-select" style="width: 100%;">
                    <option value="">Loading models...</option>
                </select>
                <label style="display: block; margin-top: 10px; font-size: 12px; color: #555;">
                    <input type="checkbox" id="wai-agent-pass-theme" value="1" checked> Pass Theme Styles (Colors, Fonts)
                </label>
            </div>
            <div id="wai-agent-body" style="display: none;">
                <div id="wai-agent-messages">
                    <div class="wai-agent-msg wai-agent-sys">Hello! I can help you manage this content. Just tell me what you need.</div>
                </div>
                <div id="wai-agent-input-area">
                    <textarea id="wai-agent-prompt" placeholder="<?php esc_attr_e('Ask me to manage this content (title, content, meta)...', 'wizard-ai'); ?>"></textarea>
                    <button id="wai-agent-send" class="button button-primary"><span class="dashicons dashicons-controls-play"></span></button>
                </div>
            </div>
        </div>
        <?php
    }

}
