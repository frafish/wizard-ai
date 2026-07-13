<?php
namespace WizardAi\Modules\Wpml\traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait Settings {
    public function handle_settings_post() {
        if (isset($_POST['wai_wpml_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wai_wpml_settings_nonce'])), 'wai_wpml_settings')) {
            if (isset($_POST['wai_clear_logs'])) {
                $upload_dir = wp_upload_dir();
                $log_file = $upload_dir['basedir'] . '/wai/logs/wpml.log';
                if (file_exists($log_file)) {
                    wp_delete_file($log_file);
                }
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Logs cleared.', 'wizard-ai') . '</p></div>';
            } else {
                update_option('wai_wpml_model', sanitize_text_field(wp_unslash($_POST['wai_wpml_model'])));
                update_option('wai_wpml_auto_fallback', isset($_POST['wai_wpml_auto_fallback']) ? 1 : 0);
                
                $fallback_models = isset($_POST['wai_wpml_fallback_models']) && is_array($_POST['wai_wpml_fallback_models']) 
                    ? array_map('sanitize_text_field', wp_unslash($_POST['wai_wpml_fallback_models'])) : [];
                update_option('wai_wpml_fallback_models', $fallback_models);
                
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'wizard-ai') . '</p></div>';
            }
        }
    }

    public function render_settings_tab() {
        $models = [];
        if (class_exists('\WordPress\AiClient\AiClient')) {
            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            $requirements = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
                [\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration()], []
            );
            $providerModels = $registry->findModelsMetadataForSupport($requirements);
            foreach ($providerModels as $providerMetadata) {
                $providerName = $providerMetadata->getProvider()->getName();
                foreach ($providerMetadata->getModels() as $modelMeta) {
                    $id = $modelMeta->getId();
                    $modelName = $modelMeta->getName() ?: $id;
                    $providerId = $providerMetadata->getProvider()->getId();
                    
                    if (stripos($id, 'dall-e') !== false || stripos($id, 'midjourney') !== false) continue;
                    
                    $models[$providerId . '|' . $id] = '[' . $providerName . '] ' . $modelName;
                }
            }
        }
        $selected_model = get_option('wai_wpml_model', '');
        $auto_fallback = get_option('wai_wpml_auto_fallback', 0);
        $saved_fallback_models = get_option('wai_wpml_fallback_models', []);
        ?>
        <div class="icl_tm_wrap">
            <form method="post" action="">
                <?php wp_nonce_field('wai_wpml_settings', 'wai_wpml_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wai_wpml_model"><?php esc_html_e('Preferred Translation Model', 'wizard-ai'); ?></label></th>
                        <td>
                            <select name="wai_wpml_model" id="wai_wpml_model" style="min-width: 300px;">
                                <option value=""><?php esc_html_e('&mdash; Auto Detect Best Model &mdash;', 'wizard-ai'); ?></option>
                                <?php foreach ($models as $val => $label): ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($selected_model, $val); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Select the primary AI model to use for translations. These models support Text Generation. If empty, your globally preferred model will be used.', 'wizard-ai'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto-Fallback', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wai_wpml_auto_fallback" value="1" <?php checked($auto_fallback, 1); ?>>
                                <?php esc_html_e('Automatically switch to another model if the selected model fails (e.g. rate limit reached, out of tokens)', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Trusted Fallback Models', 'wizard-ai'); ?></th>
                        <td>
                            <p class="description" style="margin-bottom:10px;"><?php esc_html_e('Select which models are allowed to be used as fallbacks during batch translation to prevent failures. If none are selected, all available text models will be used.', 'wizard-ai'); ?></p>
                            <select name="wai_wpml_fallback_models[]" multiple style="min-width: 300px; height: 150px;">
                                <?php foreach ($models as $val => $label): ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php echo in_array($val, $saved_fallback_models) ? 'selected' : ''; ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'wizard-ai'); ?></button>
                </p>
            </form>
            
            <h3 style="margin-top: 30px;"><?php esc_html_e('Recent Error Logs', 'wizard-ai'); ?></h3>
            <?php 
            $upload_dir = wp_upload_dir();
            $log_file = $upload_dir['basedir'] . '/wai/logs/wpml.log';
            $logs = [];
            if (file_exists($log_file)) {
                $log_content = file_get_contents($log_file);
                $logs = array_filter(explode(PHP_EOL, $log_content));
                $logs = array_slice(array_reverse($logs), 0, 100);
            }
            ?>
            <div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; max-height: 300px; overflow-y: auto;">
                <?php if (empty($logs)): ?>
                    <p><?php esc_html_e('No errors logged.', 'wizard-ai'); ?></p>
                <?php else: ?>
                    <ul style="list-style: none; padding: 0; margin: 0; font-family: monospace;">
                        <?php foreach ($logs as $log): ?>
                            <li style="border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 5px; color: #d63638;">
                                <?php echo esc_html($log); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php if (!empty($logs)): ?>
                <form method="post" action="" style="margin-top: 10px;">
                    <?php wp_nonce_field('wai_wpml_settings', 'wai_wpml_settings_nonce'); ?>
                    <input type="hidden" name="wai_clear_logs" value="1">
                    <button type="submit" class="button"><?php esc_html_e('Clear Logs', 'wizard-ai'); ?></button>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public function log_error($message) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wai/logs';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            file_put_contents($log_dir . '/.htaccess', "Deny from all\n");
            file_put_contents($log_dir . '/index.php', "<?php // Silence is golden.");
        }
        $log_file = $log_dir . '/wpml.log';
        $time = current_time('mysql');
        $log_entry = "[{$time}] " . $message . PHP_EOL;
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
}
