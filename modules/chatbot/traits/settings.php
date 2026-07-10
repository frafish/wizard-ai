<?php
namespace WizardAi\Modules\Chatbot\Traits;

trait Settings {
    public function wb_ai_chatbot_page_html() {
        if (isset($_POST['wai_chatbot_settings_nonce']) && wp_verify_nonce($_POST['wai_chatbot_settings_nonce'], 'wai_chatbot_settings')) {
            update_option('wai_chatbot_enabled', isset($_POST['wai_chatbot_enabled']) ? 1 : 0);

            update_option('wai_chatbot_icon', sanitize_text_field($_POST['wai_chatbot_icon']));
            update_option('wai_chatbot_color', sanitize_hex_color($_POST['wai_chatbot_color']));
            update_option('wai_chatbot_position', sanitize_text_field($_POST['wai_chatbot_position']));
            
            $name = sanitize_text_field($_POST['wai_chatbot_name']);
            update_option('wai_chatbot_name', $name);
            do_action('wpml_register_single_string', 'wizard-ai', 'chatbot_name', $name);

            $greeting = sanitize_textarea_field($_POST['wai_chatbot_greeting']);
            update_option('wai_chatbot_greeting', $greeting);
            do_action('wpml_register_single_string', 'wizard-ai', 'chatbot_greeting', $greeting);

            $contact_msg = sanitize_textarea_field($_POST['wai_chatbot_contact_msg']);
            update_option('wai_chatbot_contact_msg', $contact_msg);
            do_action('wpml_register_single_string', 'wizard-ai', 'chatbot_contact_msg', $contact_msg);
            
            $custom_context = sanitize_textarea_field($_POST['wai_chatbot_custom_context']);
            update_option('wai_chatbot_custom_context', $custom_context);
            
            update_option('wai_chatbot_model', sanitize_text_field($_POST['wai_chatbot_model']));
            
            update_option('wai_chatbot_auto_fallback', isset($_POST['wai_chatbot_auto_fallback']) ? 1 : 0);
            update_option('wai_chatbot_use_rag', isset($_POST['wai_chatbot_use_rag']) ? 1 : 0);
            update_option('wai_chatbot_full_rag_on_first', isset($_POST['wai_chatbot_full_rag_on_first']) ? 1 : 0);
            update_option('wai_chatbot_rag_first_limit', absint($_POST['wai_chatbot_rag_first_limit']));
            update_option('wai_chatbot_rag_search_limit', absint($_POST['wai_chatbot_rag_search_limit']));
            update_option('wai_chatbot_history_limit', absint($_POST['wai_chatbot_history_limit']));
            update_option('wai_chatbot_woocommerce', isset($_POST['wai_chatbot_woocommerce']) ? 1 : 0);
            $fallback_models = isset($_POST['wai_chatbot_fallback_models']) && is_array($_POST['wai_chatbot_fallback_models']) 
                ? array_map('sanitize_text_field', $_POST['wai_chatbot_fallback_models']) : [];
            update_option('wai_chatbot_fallback_models', $fallback_models);
            
            $track = isset($_POST['wai_chatbot_track_sessions']) ? 1 : 0;
            update_option('wai_chatbot_track_sessions', $track);

            $notify = isset($_POST['wai_chatbot_notify_new_session']) ? 1 : 0;
            update_option('wai_chatbot_notify_new_session', $notify);
            
            $notify_email = sanitize_text_field($_POST['wai_chatbot_notify_email']);
            update_option('wai_chatbot_notify_email', $notify_email);

            $gdpr_text = wp_unslash($_POST['wai_chatbot_gdpr_text'] ?? '');
            update_option('wai_chatbot_gdpr_text', $gdpr_text);
            do_action('wpml_register_single_string', 'wizard-ai', 'chatbot_gdpr_text', $gdpr_text);
            
            $gdpr_required = isset($_POST['wai_chatbot_gdpr_required']) ? 1 : 0;
            update_option('wai_chatbot_gdpr_required', $gdpr_required);
            
            echo '<div class="updated"><p>' . __('Settings saved.', 'wizard-ai') . '</p></div>';
        }

        $enabled = get_option('wai_chatbot_enabled', 0);
        $icon = get_option('wai_chatbot_icon', 'dashicons-format-chat');
        $color = get_option('wai_chatbot_color', '#2271b1');
        $position = get_option('wai_chatbot_position', 'bottom-right');

        $name = get_option('wai_chatbot_name', '');
        $greeting = get_option('wai_chatbot_greeting', 'Hello! How can I help you today?');
        $contact_msg = get_option('wai_chatbot_contact_msg', '');
        $custom_context = get_option('wai_chatbot_custom_context', '');
        $selected_model = get_option('wai_chatbot_model', '');
        $auto_fallback = get_option('wai_chatbot_auto_fallback', 0);
        $saved_fallback_models = get_option('wai_chatbot_fallback_models', []);
        
        $notify_new_session = get_option('wai_chatbot_notify_new_session', 0);
        $notify_email = get_option('wai_chatbot_notify_email', get_option('admin_email'));

        $upload_dir = wp_upload_dir();
        $rag_db_path = $upload_dir['basedir'] . '/wai/rag.sqlite';
        $rag_db_exists = file_exists($rag_db_path);

        // Fetch models for dropdown
        $models = [];
        if (class_exists('\WordPress\AiClient\AiClient')) {
            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            $requirements = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
                [\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration(), \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::chatHistory()], []
            );
            $providerModels = $registry->findModelsMetadataForSupport($requirements);
            foreach ($providerModels as $providerMetadata) {
                $providerName = $providerMetadata->getProvider()->getName();
                foreach ($providerMetadata->getModels() as $modelMeta) {
                    $id = $modelMeta->getId();
                    $modelName = $modelMeta->getName() ?: $id;
                    $providerId = $providerMetadata->getProvider()->getId();
                    $models[$providerId . '|' . $id] = '[' . $providerName . '] ' . $modelName;
                }
            }
        }
        
        $export_data = [
            'model' => $selected_model,
            'fallback_models' => $saved_fallback_models,
        ];
        $export_json = wp_json_encode($export_data);
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Frontend Chatbot Settings', 'wizard-ai'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wizard-ai-chatbot-logs')); ?>" class="page-title-action"><?php esc_html_e('View Chat Logs', 'wizard-ai'); ?></a>
            <hr class="wp-header-end">
            <form method="post" action="" id="wai_chatbot_settings_form">
                <?php wp_nonce_field('wai_chatbot_settings', 'wai_chatbot_settings_nonce'); ?>
                
                
            <h2 class="nav-tab-wrapper" id="wai-chatbot-tabs">
                <a href="#general" class="nav-tab nav-tab-active"><?php esc_html_e('General', 'wizard-ai'); ?></a>
                <a href="#appearance" class="nav-tab"><?php esc_html_e('Appearance', 'wizard-ai'); ?></a>
                <a href="#content" class="nav-tab"><?php esc_html_e('Content', 'wizard-ai'); ?></a>
                <?php if ($rag_db_exists): ?>
                <a href="#rag" class="nav-tab"><?php esc_html_e('Knowledge', 'wizard-ai'); ?></a>
                <?php endif; ?>
                <a href="#advanced" class="nav-tab"><?php esc_html_e('Advanced', 'wizard-ai'); ?></a>
            </h2>
            <div id="tab-general" class="wai-chatbot-tab-content">
            <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Chatbot', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wai_chatbot_enabled" value="1" <?php checked($enabled, 1); ?>>
                                <?php esc_html_e('Show chatbot on frontend', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('AI Model', 'wizard-ai'); ?></th>
                        <td>
                            <select name="wai_chatbot_model" style="min-width: 300px; margin-bottom: 5px;">
                                <option value=""><?php esc_html_e('Default (Auto-select)', 'wizard-ai'); ?></option>
                                <?php foreach ($models as $val => $label): ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($selected_model, $val); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto-Fallback', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wai_chatbot_auto_fallback" value="1" <?php checked($auto_fallback, 1); ?>>
                                <?php esc_html_e('Automatically switch to another model if the selected model fails', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Trusted Fallback Models', 'wizard-ai'); ?></th>
                        <td>
                            <p class="description" style="margin-bottom:10px;"><?php esc_html_e('Select which models are allowed to be used as fallbacks. If none are selected, all available models will be used.', 'wizard-ai'); ?></p>
                            <div style="display: flex; gap: 10px; align-items: flex-start;">
                                <select name="wai_chatbot_fallback_models[]" multiple style="min-width: 300px; height: 150px;">
                                    <?php foreach ($models as $val => $label): ?>
                                        <option value="<?php echo esc_attr($val); ?>" <?php echo in_array($val, $saved_fallback_models) ? 'selected' : ''; ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div style="display: flex; flex-direction: column; gap: 5px;">
                                    <button type="button" class="button" id="wai_export_btn" title="<?php esc_attr_e('Export Models Configuration', 'wizard-ai'); ?>" style="padding: 0 5px;">
                                        <span class="dashicons dashicons-download" style="margin-top: 4px; height: 20px; width: 20px;"></span>
                                    </button>
                                    <button type="button" class="button" id="wai_import_btn" title="<?php esc_attr_e('Import Models Configuration', 'wizard-ai'); ?>" style="padding: 0 5px;">
                                        <span class="dashicons dashicons-upload" style="margin-top: 4px; height: 20px; width: 20px;"></span>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Custom Context (Optional)', 'wizard-ai'); ?></th>
                        <td>
                            <textarea name="wai_chatbot_custom_context" rows="4" class="large-text" placeholder="<?php esc_attr_e('Provide custom instructions or context for the chatbot about your site...', 'wizard-ai'); ?>"><?php echo esc_textarea($custom_context); ?></textarea>
                            <p class="description"><?php esc_html_e('These instructions will be appended to the AI\'s system prompt. Useful for setting specific rules, brand tone, or custom context.', 'wizard-ai'); ?></p>
                        </td>
                    </tr>
                    </table></div>
            <?php if ($rag_db_exists): ?>
            <div id="tab-rag" class="wai-chatbot-tab-content" style="display:none;">
            <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Use RAG', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <?php $default_rag = get_option('wai_rag_cron_enabled', 'no') === 'yes' ? 1 : 1; ?>
                                <input type="checkbox" name="wai_chatbot_use_rag" id="wai_chatbot_use_rag" value="1" <?php checked(get_option('wai_chatbot_use_rag', $default_rag), 1); ?>>
                                <?php esc_html_e('Pass website Knowledge Base context to the Chatbot to improve answers', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr class="wai-rag-dependent">
                        <th scope="row"><?php esc_html_e('Full RAG on First Message', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wai_chatbot_full_rag_on_first" value="1" <?php checked(get_option('wai_chatbot_full_rag_on_first', 0), 1); ?>>
                                <?php esc_html_e('Inject the entire RAG knowledge base into the very first chat message so the AI "learns" all the site content upfront. (Warning: uses more tokens on the first request)', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr class="wai-rag-dependent">
                        <th scope="row"><?php esc_html_e('Token Optimization: RAG Limits', 'wizard-ai'); ?></th>
                        <td>
                            <div style="margin-bottom: 5px;">
                                <input type="number" name="wai_chatbot_rag_first_limit" value="<?php echo esc_attr(get_option('wai_chatbot_rag_first_limit', 30)); ?>" style="width: 80px;" min="1" max="500">
                                <label><?php esc_html_e('Max RAG records to inject on First Message (if enabled)', 'wizard-ai'); ?></label>
                            </div>
                            <div>
                                <input type="number" name="wai_chatbot_rag_search_limit" value="<?php echo esc_attr(get_option('wai_chatbot_rag_search_limit', 5)); ?>" style="width: 80px;" min="1" max="50">
                                <label><?php esc_html_e('Max RAG records to fetch on subsequent Search Queries', 'wizard-ai'); ?></label>
                            </div>
                            <p class="description"><?php esc_html_e('Lower values drastically reduce Token usage, but the AI will have less context.', 'wizard-ai'); ?></p>
                        </td>
                    </tr>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const ragToggle = document.getElementById('wai_chatbot_use_rag');
                            const dependentRows = document.querySelectorAll('.wai-rag-dependent');
                            
                            if (ragToggle) {
                                function updateRagVisibility() {
                                    dependentRows.forEach(row => {
                                        row.style.display = ragToggle.checked ? '' : 'none';
                                    });
                                }
                                ragToggle.addEventListener('change', updateRagVisibility);
                                updateRagVisibility();
                            }
                        });
                    </script>
                    <?php endif; ?>
                    </table></div>
            <div id="tab-advanced" class="wai-chatbot-tab-content" style="display:none;">
            <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Token Optimization: Chat History Limit', 'wizard-ai'); ?></th>
                        <td>
                            <input type="number" name="wai_chatbot_history_limit" value="<?php echo esc_attr(get_option('wai_chatbot_history_limit', 10)); ?>" style="width: 80px;" min="0">
                            <label><?php esc_html_e('Max previous messages to keep in memory', 'wizard-ai'); ?></label>
                            <p class="description"><?php esc_html_e('Trims the chat history to the last N messages to avoid the payload growing infinitely. Set to 0 to disable trimming. (Note: The first message is always kept if RAG is injected into it).', 'wizard-ai'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Track Sessions', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wai_chatbot_track_sessions" value="1" <?php checked(get_option('wai_chatbot_track_sessions', 0), 1); ?>>
                                <?php esc_html_e('Log conversations in the database so you can analyze them in the Chatbot Logs page', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('New Session Notification', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wai_chatbot_notify_new_session" value="1" <?php checked($notify_new_session, 1); ?>>
                                <?php esc_html_e('Send an email notification when a new chat session starts', 'wizard-ai'); ?>
                            </label>
                            <br><br>
                            <input type="email" name="wai_chatbot_notify_email" value="<?php echo esc_attr($notify_email); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Email address to receive the notification. Defaults to admin email.', 'wizard-ai'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Require GDPR Consent', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wai_chatbot_gdpr_required" value="1" <?php checked(get_option('wai_chatbot_gdpr_required', 1), 1); ?>>
                                <?php esc_html_e('Require users to check a privacy consent box before sending a message', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('GDPR Notice Text', 'wizard-ai'); ?></th>
                        <td>
                            <textarea name="wai_chatbot_gdpr_text" rows="3" style="width: 100%; max-width: 400px;"><?php echo esc_textarea(get_option('wai_chatbot_gdpr_text', 'By chatting, you agree to our processing of conversation logs to assist with your request. See our Privacy for your data rights.')); ?></textarea>
                            <p class="description"><?php esc_html_e('Enter the text to display in the GDPR consent notice.', 'wizard-ai'); ?></p>
                        </td>
                    </tr>
                    <?php if (class_exists('WooCommerce')): ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('WooCommerce Integration', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wai_chatbot_woocommerce" value="1" <?php checked(get_option('wai_chatbot_woocommerce', 1), 1); ?>>
                                <?php esc_html_e('Enable WooCommerce tools (Add to cart, apply coupon, check orders) for the Chatbot', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <?php endif; ?>
                    </table></div>
            <div id="tab-appearance" class="wai-chatbot-tab-content" style="display:none;">
            <table class="form-table">
            <tr>
                        <th scope="row"><?php esc_html_e('Chatbot Icon', 'wizard-ai'); ?></th>
                        <td>
                            <?php
                            $dashicons = [
                                'dashicons-format-chat', 'dashicons-smiley', 'dashicons-admin-users', 'dashicons-businessman', 'dashicons-testimonial', 'dashicons-megaphone', 'dashicons-microphone', 'dashicons-info', 'dashicons-editor-help', 'dashicons-lightbulb', 'dashicons-thumbs-up', 'dashicons-heart', 'dashicons-star-filled', 'dashicons-yes', 'dashicons-warning', 'dashicons-sos', 'dashicons-lifesaver', 'dashicons-visibility', 'dashicons-welcome-learn-more', 'dashicons-admin-site', 'dashicons-admin-generic', 'dashicons-admin-customizer', 'dashicons-admin-comments', 'dashicons-admin-network', 'dashicons-welcome-widgets-menus', 'dashicons-welcome-comments', 'dashicons-groups', 'dashicons-store', 'dashicons-format-status', 'dashicons-format-quote', 'dashicons-carrot', 'dashicons-art', 'dashicons-buddicons-buddypress-logo', 'dashicons-buddicons-groups', 'dashicons-buddicons-topics', 'dashicons-buddicons-pm', 'dashicons-email-alt', 'dashicons-email-alt2', 'dashicons-whatsapp', 'dashicons-facebook', 'dashicons-twitter'
                            ];
                            ?>
                            <div class="wai-icon-selector" style="max-height: 150px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; display: flex; flex-wrap: wrap; gap: 10px; max-width: 400px; background: #fff;">
                                <?php foreach($dashicons as $d): ?>
                                    <label style="cursor: pointer; padding: 5px; border: 1px solid <?php echo $icon === $d ? '#2271b1' : 'transparent'; ?>; border-radius: 4px; background: <?php echo $icon === $d ? '#e3f2fd' : 'transparent'; ?>;" onclick="document.querySelectorAll('.wai-icon-selector label').forEach(l => {l.style.borderColor='transparent'; l.style.background='transparent';}); this.style.borderColor='#2271b1'; this.style.background='#e3f2fd'; document.getElementById('wai_chatbot_icon_input').value='<?php echo esc_attr($d); ?>';">
                                        <span class="dashicons <?php echo esc_attr($d); ?>" style="font-size: 24px; width: 24px; height: 24px;"></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="wai_chatbot_icon" id="wai_chatbot_icon_input" value="<?php echo esc_attr($icon); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Main Color', 'wizard-ai'); ?></th>
                        <td>
                            <input type="color" name="wai_chatbot_color" value="<?php echo esc_attr($color); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Position', 'wizard-ai'); ?></th>
                        <td>
                            <select name="wai_chatbot_position">
                                <option value="bottom-right" <?php selected($position, 'bottom-right'); ?>><?php esc_html_e('Bottom Right', 'wizard-ai'); ?></option>
                                <option value="bottom-left" <?php selected($position, 'bottom-left'); ?>><?php esc_html_e('Bottom Left', 'wizard-ai'); ?></option>
                            </select>
                        </td>
                    </tr>
                    </table></div>
            <div id="tab-content" class="wai-chatbot-tab-content" style="display:none;">
            <table class="form-table">
            <tr>
                        <th scope="row"><?php esc_html_e('Chatbot Name', 'wizard-ai'); ?></th>
                        <td>
                            <input type="text" name="wai_chatbot_name" value="<?php echo esc_attr($name); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Initial Greeting', 'wizard-ai'); ?></th>
                        <td>
                            <textarea name="wai_chatbot_greeting" rows="4" class="large-text"><?php echo esc_textarea($greeting); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Contact Prompt (Optional)', 'wizard-ai'); ?></th>
                        <td>
                            <textarea name="wai_chatbot_contact_msg" rows="2" class="large-text" placeholder="<?php esc_attr_e('e.g. Do you want to be contacted by us? Leave your email.', 'wizard-ai'); ?>"><?php echo esc_textarea($contact_msg); ?></textarea>
                            <p class="description"><?php esc_html_e('This message will be appended to the AI\'s responses to encourage visitors to leave their email address. It will automatically hide once an email is provided or if the user is logged in.', 'wizard-ai'); ?></p>
                        </td>
                    </tr>

                </table></div>
                
                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const tabs = document.querySelectorAll("#wai-chatbot-tabs .nav-tab");
                    const contents = document.querySelectorAll(".wai-chatbot-tab-content");
                    tabs.forEach(tab => {
                        tab.addEventListener("click", function(e) {
                            e.preventDefault();
                            tabs.forEach(t => t.classList.remove("nav-tab-active"));
                            this.classList.add("nav-tab-active");
                            const target = this.getAttribute("href").replace("#", "tab-");
                            contents.forEach(c => c.style.display = "none");
                            document.getElementById(target).style.display = "block";
                        });
                    });
                });
                </script>
                
                <p class="submit">
                    <?php submit_button('', 'primary', 'submit', false); ?>
                </p>
                
                <input type="file" id="wai_import_file" style="display:none" accept=".json">

                <script>
                document.getElementById('wai_export_btn').addEventListener('click', function() {
                    const data = <?php echo $export_json; ?>;
                    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'wizard-ai-chatbot-config.json';
                    a.click();
                    URL.revokeObjectURL(url);
                });
                document.getElementById('wai_import_btn').addEventListener('click', function() {
                    document.getElementById('wai_import_file').click();
                });
                document.getElementById('wai_import_file').addEventListener('change', function(e) {
                    if (!e.target.files.length) return;
                    const reader = new FileReader();
                    reader.onload = function(evt) {
                        try {
                            const config = JSON.parse(evt.target.result);
                            
                            if (config.model) {
                                const modelSelect = document.querySelector('select[name="wai_chatbot_model"]');
                                if (modelSelect) modelSelect.value = config.model;
                            }
                            
                            if (config.fallback_models && Array.isArray(config.fallback_models)) {
                                const fallbackSelect = document.querySelector('select[name="wai_chatbot_fallback_models[]"]');
                                if (fallbackSelect) {
                                    Array.from(fallbackSelect.options).forEach(opt => {
                                        opt.selected = config.fallback_models.includes(opt.value);
                                    });
                                }
                            }
                            
                            e.target.value = '';
                            alert("<?php esc_html_e('Models imported successfully! Please click Save Changes to apply them.', 'wizard-ai'); ?>");
                        } catch (err) {
                            alert("<?php esc_html_e('Invalid JSON configuration file.', 'wizard-ai'); ?>");
                        }
                    };
                    reader.readAsText(e.target.files[0]);
                });
                </script>
            </form>
        </div>
        <?php
    }

}
