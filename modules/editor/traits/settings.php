<?php
namespace WizardAi\Modules\Editor\Traits;

trait Settings {
    public function wb_ai_agent_page_html() {
        if (isset($_POST['wai_agent_settings_nonce']) && wp_verify_nonce($_POST['wai_agent_settings_nonce'], 'wai_agent_settings')) {
            $post_types = isset($_POST['wai_agent_post_types']) ? array_map('sanitize_text_field', $_POST['wai_agent_post_types']) : [];
            $taxonomies = isset($_POST['wai_agent_taxonomies']) ? array_map('sanitize_text_field', $_POST['wai_agent_taxonomies']) : [];
            $roles = isset($_POST['wai_agent_roles']) ? array_map('sanitize_text_field', $_POST['wai_agent_roles']) : [];
            $enable_users = isset($_POST['wai_agent_enable_users']) ? 1 : 0;
            $enabled = isset($_POST['wai_agent_enabled']) ? 1 : 0;

            update_option('wai_agent_post_types', $post_types);
            update_option('wai_agent_taxonomies', $taxonomies);
            update_option('wai_agent_roles', $roles);
            update_option('wai_agent_enable_users', $enable_users);
            update_option('wai_agent_enabled', $enabled);
            echo '<div class="updated"><p>' . __('Settings saved.', 'wizard-ai') . '</p></div>';
        }

        $is_enabled = get_option('wai_agent_enabled', 0);
        $selected_post_types = get_option('wai_agent_post_types', ['post', 'page']);
        $selected_taxonomies = get_option('wai_agent_taxonomies', ['category', 'post_tag']);
        $selected_roles = get_option('wai_agent_roles', ['administrator']);
        $enable_users = get_option('wai_agent_enable_users', 1);

        $all_post_types = get_post_types(['show_ui' => true], 'objects');
        $all_taxonomies = get_taxonomies(['show_ui' => true], 'objects');
        
        global $wp_roles;
        if (!isset($wp_roles)) {
            $wp_roles = new \WP_Roles();
        }
        $all_roles = $wp_roles->roles;

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Editor Agent Settings', 'wizard-ai'); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field('wai_agent_settings', 'wai_agent_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Editor Agent', 'wizard-ai'); ?></th>
                        <td>
                            <label class="wai-switch">
                                <input type="checkbox" name="wai_agent_enabled" value="1" <?php checked($is_enabled, 1); ?>>
                                <span class="wai-slider wai-round"></span>
                            </label>
                            <p class="description"><?php esc_html_e('Toggle the AI assistant inside the post/page editor.', 'wizard-ai'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable for Post Types', 'wizard-ai'); ?></th>
                        <td>
                            <?php foreach ($all_post_types as $pt): ?>
                                <label>
                                    <input type="checkbox" name="wai_agent_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $selected_post_types)); ?>>
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
                                    <input type="checkbox" name="wai_agent_taxonomies[]" value="<?php echo esc_attr($tax->name); ?>" <?php checked(in_array($tax->name, $selected_taxonomies)); ?>>
                                    <?php echo esc_html($tax->label); ?>
                                </label><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable for User Profiles', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wai_agent_enable_users" value="1" <?php checked($enable_users, 1); ?>>
                                <?php esc_html_e('Yes', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Allowed User Roles', 'wizard-ai'); ?></th>
                        <td>
                            <?php foreach ($all_roles as $role_key => $role): ?>
                                <label>
                                    <input type="checkbox" name="wai_agent_roles[]" value="<?php echo esc_attr($role_key); ?>" <?php checked(in_array($role_key, $selected_roles)); ?>>
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
