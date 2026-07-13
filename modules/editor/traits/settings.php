<?php
namespace WizardAi\Modules\Editor\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait Settings {
    public function wb_ai_agent_page_html() {
        if (isset($_POST['wai_agent_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wai_agent_settings_nonce'])), 'wai_agent_settings')) {
            $post_types = isset($_POST['wai_agent_post_types']) ? array_map('sanitize_text_field', wp_unslash($_POST['wai_agent_post_types'])) : [];
            $taxonomies = isset($_POST['wai_agent_taxonomies']) ? array_map('sanitize_text_field', wp_unslash($_POST['wai_agent_taxonomies'])) : [];
            $roles = isset($_POST['wai_agent_roles']) ? array_map('sanitize_text_field', wp_unslash($_POST['wai_agent_roles'])) : [];
            $enable_users = isset($_POST['wai_agent_enable_users']) ? 1 : 0;
            $enabled = isset($_POST['wai_agent_enabled']) ? 1 : 0;

            update_option('wai_agent_post_types', $post_types);
            update_option('wai_agent_taxonomies', $taxonomies);
            update_option('wai_agent_roles', $roles);
            update_option('wai_agent_enable_users', $enable_users);
            update_option('wai_agent_enabled', $enabled);
            echo '<div class="updated"><p>' . esc_html__('Settings saved.', 'wizard-ai') . '</p></div>';
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
            <h1 style="display: flex; align-items: center; gap: 10px;">
                <span class="dashicons dashicons-edit" style="font-size: 28px; width: 28px; height: 28px;"></span> 
                <?php esc_html_e('Editor Agent Settings', 'wizard-ai'); ?>
            </h1>
            <p style="font-size: 14px; color: #50575e;"><?php esc_html_e('Configure where the Wizard AI assistant appears within your WordPress editing screens.', 'wizard-ai'); ?></p>
            
            <div style="background:#fff; padding:30px; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.05); margin-top:20px; max-width:800px; border: 1px solid #e2e4e7;">
                <form method="post" action="">
                    <?php wp_nonce_field('wai_agent_settings', 'wai_agent_settings_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label style="font-weight: 600;"><?php esc_html_e('Enable Editor Agent', 'wizard-ai'); ?></label>
                            </th>
                            <td>
                                <label class="wai-switch">
                                    <input type="checkbox" name="wai_agent_enabled" value="1" <?php checked($is_enabled, 1); ?>>
                                    <span class="wai-slider wai-round"></span>
                                </label>
                                <p class="description" style="margin-top: 5px;"><?php esc_html_e('Toggle the AI assistant floating button inside the block editor.', 'wizard-ai'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label style="font-weight: 600;"><?php esc_html_e('Enable for Post Types', 'wizard-ai'); ?></label>
                            </th>
                            <td>
                                <fieldset style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #ccd0d4;">
                                    <?php foreach ($all_post_types as $pt): ?>
                                        <label style="display: flex; align-items: center; gap: 8px;">
                                            <input type="checkbox" name="wai_agent_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $selected_post_types)); ?>>
                                            <?php echo esc_html($pt->label); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label style="font-weight: 600;"><?php esc_html_e('Enable for Taxonomies', 'wizard-ai'); ?></label>
                            </th>
                            <td>
                                <fieldset style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #ccd0d4;">
                                    <?php foreach ($all_taxonomies as $tax): ?>
                                        <label style="display: flex; align-items: center; gap: 8px;">
                                            <input type="checkbox" name="wai_agent_taxonomies[]" value="<?php echo esc_attr($tax->name); ?>" <?php checked(in_array($tax->name, $selected_taxonomies)); ?>>
                                            <?php echo esc_html($tax->label); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label style="font-weight: 600;"><?php esc_html_e('Enable for User Profiles', 'wizard-ai'); ?></label>
                            </th>
                            <td>
                                <label class="wai-switch">
                                    <input type="checkbox" name="wai_agent_enable_users" value="1" <?php checked($enable_users, 1); ?>>
                                    <span class="wai-slider wai-round"></span>
                                </label>
                                <p class="description" style="margin-top: 5px;"><?php esc_html_e('Allow the agent to assist when editing user profiles.', 'wizard-ai'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label style="font-weight: 600;"><?php esc_html_e('Allowed User Roles', 'wizard-ai'); ?></label>
                            </th>
                            <td>
                                <fieldset style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #ccd0d4;">
                                    <?php foreach ($all_roles as $role_key => $role): ?>
                                        <label style="display: flex; align-items: center; gap: 8px;">
                                            <input type="checkbox" name="wai_agent_roles[]" value="<?php echo esc_attr($role_key); ?>" <?php checked(in_array($role_key, $selected_roles)); ?>>
                                            <?php echo esc_html($role['name']); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                                <p class="description" style="margin-top: 10px;"><?php esc_html_e('Select which roles are permitted to use the AI assistant in the editor.', 'wizard-ai'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e4e7;">
                        <input type="submit" name="submit" id="submit" class="button button-primary button-hero" value="<?php esc_attr_e('Save Changes', 'wizard-ai'); ?>">
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
}
