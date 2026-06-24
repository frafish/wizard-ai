<?php
namespace WizardAi\Modules\Ai\Traits;

trait Agent {
    public function register_agent_hooks() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_agent_scripts']);
        add_action('admin_footer', [$this, 'render_agent_chatbot']);
    }

    private function should_render_agent() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen) return false;

        $user = wp_get_current_user();
        if (empty($user)) return false;

        $selected_roles = get_option('wbai_agent_roles', ['administrator']);
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

        $selected_post_types = get_option('wbai_agent_post_types', ['post', 'page']);
        $selected_taxonomies = get_option('wbai_agent_taxonomies', ['category', 'post_tag']);
        $enable_users = get_option('wbai_agent_enable_users', 1);

        if ($screen->base === 'post') {
            return in_array($screen->post_type, $selected_post_types);
        } elseif ($screen->base === 'term' || $screen->base === 'edit-tags') {
            return in_array($screen->taxonomy, $selected_taxonomies);
        } elseif (in_array($screen->base, ['user', 'profile', 'user-edit'])) {
            return $enable_users ? true : false;
        }

        return false;
    }

    public function enqueue_agent_scripts($hook) {
        if (!$this->should_render_agent()) {
            return;
        }

        $screen = get_current_screen();

        wp_enqueue_style('wizard-ai-agent-style', WIZARD_AI_URL . 'modules/ai/assets/css/agent.css', [], '1.0.0');
        wp_enqueue_script('wizard-ai-agent-script', WIZARD_AI_URL . 'modules/ai/assets/js/agent.js', ['jquery'], '1.0.0', true);
        
        wp_localize_script('wizard-ai-agent-script', 'wizardAiAgentData', [
            'rest_url' => esc_url_raw(rest_url('wizard-blocks/v1/ai-chat')),
            'nonce' => wp_create_nonce('wp_rest'),
            'screen' => $screen->base,
            'preferredModel' => get_user_meta(get_current_user_id(), '_wbai_preferred_model', true),
            'debugMode' => (defined('WP_DEBUG') && WP_DEBUG)
        ]);
    }

    public function render_agent_chatbot() {
        if (!$this->should_render_agent()) {
            return;
        }
        ?>
        <div id="wbai-agent-chatbot" class="wbai-agent-closed">
            <div id="wbai-agent-header">
                <span class="dashicons dashicons-superhero"></span> <span class="wbai-agent-title-text">Wizard AI Agent</span>
                <button id="wbai-agent-settings-toggle" title="Settings" style="margin-left: auto; margin-right: 5px; background: none; border: none; color: #fff; cursor: pointer;"><span class="dashicons dashicons-admin-generic"></span></button>
                <button id="wbai-agent-toggle" style="background: none; border: none; color: #fff; cursor: pointer;"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
            </div>
            <div id="wbai-agent-model-area" style="display: none; padding: 10px; background: #fff; border-bottom: 1px solid #ccd0d4;">
                <select id="wbai-agent-model-select" style="width: 100%;">
                    <option value="">Loading models...</option>
                </select>
            </div>
            <div id="wbai-agent-body" style="display: none;">
                <div id="wbai-agent-messages">
                    <div class="wbai-agent-msg wbai-agent-sys">Hello! I can help you manage this content. Just tell me what you need.</div>
                </div>
                <div id="wbai-agent-input-area">
                    <textarea id="wbai-agent-prompt" placeholder="<?php esc_attr_e('Ask me to manage this content (title, content, meta)...', 'wizard-ai'); ?>"></textarea>
                    <button id="wbai-agent-send" class="button button-primary"><span class="dashicons dashicons-controls-play"></span></button>
                </div>
            </div>
        </div>
        <?php
    }

    public function wb_ai_agent_page_html() {
        if (isset($_POST['wbai_agent_settings_nonce']) && wp_verify_nonce($_POST['wbai_agent_settings_nonce'], 'wbai_agent_settings')) {
            $post_types = isset($_POST['wbai_agent_post_types']) ? array_map('sanitize_text_field', $_POST['wbai_agent_post_types']) : [];
            $taxonomies = isset($_POST['wbai_agent_taxonomies']) ? array_map('sanitize_text_field', $_POST['wbai_agent_taxonomies']) : [];
            $roles = isset($_POST['wbai_agent_roles']) ? array_map('sanitize_text_field', $_POST['wbai_agent_roles']) : [];
            $enable_users = isset($_POST['wbai_agent_enable_users']) ? 1 : 0;

            update_option('wbai_agent_post_types', $post_types);
            update_option('wbai_agent_taxonomies', $taxonomies);
            update_option('wbai_agent_roles', $roles);
            update_option('wbai_agent_enable_users', $enable_users);
            echo '<div class="updated"><p>' . __('Settings saved.', 'wizard-ai') . '</p></div>';
        }

        $selected_post_types = get_option('wbai_agent_post_types', ['post', 'page']);
        $selected_taxonomies = get_option('wbai_agent_taxonomies', ['category', 'post_tag']);
        $selected_roles = get_option('wbai_agent_roles', ['administrator']);
        $enable_users = get_option('wbai_agent_enable_users', 1);

        $all_post_types = get_post_types(['show_ui' => true], 'objects');
        $all_taxonomies = get_taxonomies(['show_ui' => true], 'objects');
        
        global $wp_roles;
        if (!isset($wp_roles)) {
            $wp_roles = new \WP_Roles();
        }
        $all_roles = $wp_roles->roles;

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Agent Settings', 'wizard-ai'); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field('wbai_agent_settings', 'wbai_agent_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable for Post Types', 'wizard-ai'); ?></th>
                        <td>
                            <?php foreach ($all_post_types as $pt): ?>
                                <label>
                                    <input type="checkbox" name="wbai_agent_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $selected_post_types)); ?>>
                                    <?php echo esc_html($pt->label); ?>
                                </label><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable for Taxonomies', 'wizard-ai'); ?></th>
                        <td>
                            <?php foreach ($all_taxonomies as $tax): ?>
                                <label>
                                    <input type="checkbox" name="wbai_agent_taxonomies[]" value="<?php echo esc_attr($tax->name); ?>" <?php checked(in_array($tax->name, $selected_taxonomies)); ?>>
                                    <?php echo esc_html($tax->label); ?>
                                </label><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable for User Profiles', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wbai_agent_enable_users" value="1" <?php checked($enable_users, 1); ?>>
                                <?php esc_html_e('Yes', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Allowed User Roles', 'wizard-ai'); ?></th>
                        <td>
                            <?php foreach ($all_roles as $role_key => $role): ?>
                                <label>
                                    <input type="checkbox" name="wbai_agent_roles[]" value="<?php echo esc_attr($role_key); ?>" <?php checked(in_array($role_key, $selected_roles)); ?>>
                                    <?php echo esc_html($role['name']); ?>
                                </label><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
