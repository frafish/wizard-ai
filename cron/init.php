<?php

if (!defined('ABSPATH')) {
    exit;
}

// Include RAG embeddings cron class
require_once WIZARD_AI_PATH . 'cron/rag.php';

// Register WP-Cron hook
add_action('wizard_ai_rag_sync_event', 'wizard_ai_run_rag_sync');
function wizard_ai_run_rag_sync() {
    if (class_exists('WizardAI_RAG_Embeddings_Cron')) {
        $cron = new WizardAI_RAG_Embeddings_Cron();
        $cron->run();
    }
}

// Register Settings
add_action('admin_init', 'wizard_ai_rag_settings_init');
function wizard_ai_rag_settings_init() {
    register_setting('wbai_rag_settings_group', 'wbai_rag_cron_enabled');
    register_setting('wbai_rag_settings_group', 'wbai_rag_cron_frequency');
    register_setting('wbai_rag_settings_group', 'wbai_rag_embedding_provider');
    
    register_setting('wbai_rag_settings_group', 'wbai_rag_sync_contents');
    register_setting('wbai_rag_settings_group', 'wbai_rag_sync_products');
    register_setting('wbai_rag_settings_group', 'wbai_rag_sync_terms');
    register_setting('wbai_rag_settings_group', 'wbai_rag_sync_plugins');
    register_setting('wbai_rag_settings_group', 'wbai_rag_sync_settings');
}

// Reschedule when settings change
add_action('update_option_wbai_rag_cron_enabled', 'wizard_ai_rag_reschedule_cron', 10, 0);
add_action('update_option_wbai_rag_cron_frequency', 'wizard_ai_rag_reschedule_cron', 10, 0);

add_action('update_option_wbai_rag_embedding_provider', 'wizard_ai_rag_provider_changed', 10, 2);
function wizard_ai_rag_provider_changed($old_value, $new_value) {
    if ($old_value !== $new_value) {
        $upload_dir = wp_upload_dir();
        $db_path = $upload_dir['basedir'] . '/wbai/rag_embeddings.sqlite';
        if (file_exists($db_path)) {
            try {
                $db = new PDO('sqlite:' . $db_path);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
                $db->exec("DELETE FROM document_embeddings");
            } catch (Exception $e) {
                // ignore
            }
        }
    }
}

function wizard_ai_rag_reschedule_cron() {
    wp_clear_scheduled_hook('wizard_ai_rag_sync_event');
    wizard_ai_schedule_rag_sync();
}

// Schedule cron event 
add_action('init', 'wizard_ai_schedule_rag_sync');
function wizard_ai_schedule_rag_sync() {
    $enabled = get_option('wbai_rag_cron_enabled', 'no');
    if ($enabled === 'no') {
        wp_clear_scheduled_hook('wizard_ai_rag_sync_event');
        return;
    }
    
    $frequency = get_option('wbai_rag_cron_frequency', 'hourly');
    
    $timestamp = wp_next_scheduled('wizard_ai_rag_sync_event');
    if ($timestamp) {
        $current_schedule = wp_get_schedule('wizard_ai_rag_sync_event');
        if ($current_schedule !== $frequency) {
            wp_clear_scheduled_hook('wizard_ai_rag_sync_event');
            wp_schedule_event(time(), $frequency, 'wizard_ai_rag_sync_event');
        }
    } else {
        wp_schedule_event(time(), $frequency, 'wizard_ai_rag_sync_event');
    }
}

// Clear schedule on deactivation
register_deactivation_hook(WIZARD_AI_FILE, 'wizard_ai_deactivate_rag_sync');
function wizard_ai_deactivate_rag_sync() {
    wp_clear_scheduled_hook('wizard_ai_rag_sync_event');
}

// Handle Manual Sync
add_action('admin_post_wizard_ai_manual_rag_sync', 'wizard_ai_handle_manual_rag_sync');
function wizard_ai_handle_manual_rag_sync() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized.', 'wizard-ai'));
    }
    check_admin_referer('wizard_ai_rag_sync_nonce');
    
    // Run the sync
    wizard_ai_run_rag_sync();
    
    // Redirect back
    wp_redirect(add_query_arg('rag_sync_run', '1', wp_get_referer()));
    exit;
}

// Notice after manual sync
add_action('admin_notices', 'wizard_ai_rag_sync_notice');
function wizard_ai_rag_sync_notice() {
    if (isset($_GET['rag_sync_run']) && $_GET['rag_sync_run'] == '1') {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Wizard AI RAG Embeddings sync completed.', 'wizard-ai') . '</p></div>';
    }
}

// Add Cron Settings Page
add_action('admin_menu', 'wizard_ai_cron_settings_menu', 11);
function wizard_ai_cron_settings_menu() {
    $parent_slug = 'wizard-ai';
    add_submenu_page(
        $parent_slug,
        __('RAG Embeddings', 'wizard-ai'),
        __('RAG Sync', 'wizard-ai'),
        'manage_options',
        'wizard-ai-rag',
        'wizard_ai_cron_settings_page'
    );
}

function wizard_ai_cron_settings_page() {
    $sync_url = wp_nonce_url(admin_url('admin-post.php?action=wizard_ai_manual_rag_sync'), 'wizard_ai_rag_sync_nonce');
    $enabled = get_option('wbai_rag_cron_enabled', 'no');
    $frequency = get_option('wbai_rag_cron_frequency', 'hourly');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Wizard AI - RAG Vector Embeddings', 'wizard-ai'); ?></h1>
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2><?php esc_html_e('Synchronize Knowledge Base', 'wizard-ai'); ?></h2>
            <p><?php esc_html_e('Synchronize your site\'s content into the local vector database. This allows the AI to reference your published pages and posts as context.', 'wizard-ai'); ?></p>
            
            <form method="post" action="options.php" style="margin-top: 20px; background: #f9f9f9; padding: 15px; border: 1px solid #ddd;">
                <?php settings_fields('wbai_rag_settings_group'); ?>
                <h3><?php esc_html_e('Automated Sync Settings', 'wizard-ai'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Background Sync', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="radio" name="wbai_rag_cron_enabled" value="yes" <?php checked($enabled, 'yes'); ?>>
                                <?php esc_html_e('Yes, run automatically', 'wizard-ai'); ?>
                            </label><br>
                            <label>
                                <input type="radio" name="wbai_rag_cron_enabled" value="no" <?php checked($enabled, 'no'); ?>>
                                <?php esc_html_e('No, manual sync only', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Sync Frequency', 'wizard-ai'); ?></th>
                        <td>
                            <select name="wbai_rag_cron_frequency">
                                <option value="hourly" <?php selected($frequency, 'hourly'); ?>><?php esc_html_e('Once Hourly', 'wizard-ai'); ?></option>
                                <option value="twicedaily" <?php selected($frequency, 'twicedaily'); ?>><?php esc_html_e('Twice Daily', 'wizard-ai'); ?></option>
                                <option value="daily" <?php selected($frequency, 'daily'); ?>><?php esc_html_e('Once Daily', 'wizard-ai'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('How often should the site sync content to the AI database?', 'wizard-ai'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('What to Sync', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="hidden" name="wbai_rag_sync_contents" value="0">
                                <input type="checkbox" name="wbai_rag_sync_contents" value="1" <?php checked(get_option('wbai_rag_sync_contents', 1), 1); ?>> 
                                <?php esc_html_e('WordPress Contents (Posts/Pages/Blocks)', 'wizard-ai'); ?>
                            </label><br>
                            <label>
                                <input type="hidden" name="wbai_rag_sync_products" value="0">
                                <input type="checkbox" name="wbai_rag_sync_products" value="1" <?php checked(get_option('wbai_rag_sync_products', 1), 1); ?>> 
                                <?php esc_html_e('WooCommerce Products', 'wizard-ai'); ?>
                            </label><br>
                            <label>
                                <input type="hidden" name="wbai_rag_sync_terms" value="0">
                                <input type="checkbox" name="wbai_rag_sync_terms" value="1" <?php checked(get_option('wbai_rag_sync_terms', 1), 1); ?>> 
                                <?php esc_html_e('Taxonomies & Categories', 'wizard-ai'); ?>
                            </label><br>
                            <label>
                                <input type="hidden" name="wbai_rag_sync_plugins" value="0">
                                <input type="checkbox" name="wbai_rag_sync_plugins" value="1" <?php checked(get_option('wbai_rag_sync_plugins', 1), 1); ?>> 
                                <?php esc_html_e('Active Plugins API (Hooks, Classes, Functions)', 'wizard-ai'); ?>
                            </label><br>
                            <label>
                                <input type="hidden" name="wbai_rag_sync_settings" value="0">
                                <input type="checkbox" name="wbai_rag_sync_settings" value="1" <?php checked(get_option('wbai_rag_sync_settings', 1), 1); ?>> 
                                <?php esc_html_e('Global Settings', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Embedding Provider', 'wizard-ai'); ?></th>
                        <td>
                            <?php 
                            $provider = get_option('wbai_rag_embedding_provider', ''); 
                            $has_gemini = !empty(get_option('connectors_ai_google_api_key', ''));
                            $has_openai = !empty(get_option('connectors_ai_openai_api_key', ''));
                            $has_hf = !empty(get_option('connectors_ai_huggingface_api_key', ''));
                            ?>
                            <select name="wbai_rag_embedding_provider" id="wbai_rag_embedding_provider" required>
                                <option value="" disabled <?php selected($provider, ''); ?>><?php esc_html_e('-- Select a Provider --', 'wizard-ai'); ?></option>
                                <option value="gemini" <?php selected($provider, 'gemini'); ?> <?php echo !$has_gemini ? 'disabled' : ''; ?>>Google Gemini <?php echo !$has_gemini ? ' (Not Configured)' : ''; ?></option>
                                <option value="openai" <?php selected($provider, 'openai'); ?> <?php echo !$has_openai ? 'disabled' : ''; ?>>OpenAI <?php echo !$has_openai ? ' (Not Configured)' : ''; ?></option>
                                <option value="huggingface" <?php selected($provider, 'huggingface'); ?> <?php echo !$has_hf ? 'disabled' : ''; ?>>HuggingFace <?php echo !$has_hf ? ' (Not Configured)' : ''; ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Select the AI provider to use for vectorization. You must first configure its API key in the WordPress Connectors page.', 'wizard-ai'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save Sync Settings', 'wizard-ai'), 'primary', 'submit', false); ?>
            </form>

            <hr style="margin: 30px 0;">
            
            <h3><?php esc_html_e('Manual Trigger', 'wizard-ai'); ?></h3>
            <p><?php esc_html_e('You can trigger the synchronization process manually at any time.', 'wizard-ai'); ?></p>
            <p><a href="<?php echo esc_url($sync_url); ?>" class="button button-secondary"><?php esc_html_e('Sync RAG DB Now', 'wizard-ai'); ?></a></p>
        </div>
        
        <?php
        // Generate Database Status Report
        $upload_dir = wp_upload_dir();
        $db_path = $upload_dir['basedir'] . '/wbai/rag_embeddings.sqlite';
        $total_chunks = 0;
        $total_posts = 0;
        $last_update = __('Never', 'wizard-ai');
        $db_size = '0 KB';

        if (file_exists($db_path)) {
            $db_size = size_format(filesize($db_path));
            try {
                $db = new PDO('sqlite:' . $db_path);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
                
                $result = $db->query("SELECT COUNT(*) as c FROM document_embeddings");
                if ($result) {
                    $row = $result->fetch(PDO::FETCH_ASSOC);
                    $total_chunks = $row['c'] ?? 0;
                }
                
                $result = $db->query("SELECT COUNT(DISTINCT post_id) as c FROM document_embeddings");
                if ($result) {
                    $row = $result->fetch(PDO::FETCH_ASSOC);
                    $total_posts = $row['c'] ?? 0;
                }
                
                $result = $db->query("SELECT MAX(updated_at) as m FROM document_embeddings");
                if ($result) {
                    $row = $result->fetch(PDO::FETCH_ASSOC);
                    if (!empty($row['m'])) {
                        $last_update = get_date_from_gmt($row['m'], get_option('date_format') . ' ' . get_option('time_format'));
                    }
                }
            } catch (Exception $e) {
                $last_update = __('Error reading database', 'wizard-ai');
            }
        }
        ?>
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2><?php esc_html_e('Database Status Report', 'wizard-ai'); ?></h2>
            <table class="widefat striped" style="margin-top: 15px;">
                <tbody>
                    <tr>
                        <th style="width: 30%;"><?php esc_html_e('Status', 'wizard-ai'); ?></th>
                        <td>
                            <?php if ($total_chunks > 0): ?>
                                <span style="color: #46b450; font-weight: bold;">&#10003; <?php esc_html_e('Active & Populated', 'wizard-ai'); ?></span>
                            <?php else: ?>
                                <span style="color: #dc3232; font-weight: bold;">&#10007; <?php esc_html_e('Empty', 'wizard-ai'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Indexed Posts', 'wizard-ai'); ?></th>
                        <td><strong><?php echo number_format_i18n($total_posts); ?></strong></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Total Vectors (Chunks)', 'wizard-ai'); ?></th>
                        <td><strong><?php echo number_format_i18n($total_chunks); ?></strong></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Last Sync Time', 'wizard-ai'); ?></th>
                        <td><?php echo esc_html($last_update); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Database Size', 'wizard-ai'); ?></th>
                        <td><?php echo esc_html($db_size); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
