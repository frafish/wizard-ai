<?php
namespace WizardAi\Modules\Playground\Traits;
trait Ui {
    public function wb_ai_page_html() {
        $is_ai_configured = false;
        if (class_exists('\WordPress\AiClient\AiClient')) {
            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            if (count($registry->getRegisteredProviderIds()) > 0) {
                $is_ai_configured = true;
            }
        }

        $channel = get_option('wizard_blocks_gemini_api_channel', 'v1beta');
        ?>
        <div class="wrap">
            <h1 style="display: flex; align-items: center; gap: 10px;">
                <img src="<?php echo esc_url(WIZARD_AI_URL . 'modules/ai/assets/svg/wizard-ai.svg'); ?>" alt="Wizard AI" style="width: 40px; height: 40px;">
                <?php esc_html_e('Wizard AI Playground', 'wizard-ai'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wizard-ai-automation')); ?>" class="page-title-action"><span class="dashicons dashicons-clock" style="vertical-align: middle;"></span> <?php esc_html_e('Automated Tasks', 'wizard-ai'); ?></a>
            </h1>
            
            <?php if ($is_ai_configured): ?>
            
            <div class="card wai-playground-card">
                <div id="wai-playground-chat-wrapper">
                    <div id="wai-playground-chat"></div>
                    <button type="button" class="toggle-distraction-free" title="<?php esc_attr_e('Toggle full screen', 'wizard-ai'); ?>">
                        <span class="dashicons dashicons-fullscreen-alt"></span>
                    </button>
                </div>
                <div class="wai-playground-prompt-wrapper">
                    <textarea id="wai-playground-prompt" placeholder="<?php esc_attr_e('Ask the AI to do something (e.g. create a file, run a query)...', 'wizard-ai'); ?>"></textarea>
                    <div id="wai-playground-attachment-preview"></div>
                    <button type="button" id="wai-attach-media" title="<?php esc_attr_e('Attach Media', 'wizard-ai'); ?>"><span class="dashicons dashicons-plus"></span></button>
                    <button type="button" id="wai-speech-to-text" title="<?php esc_attr_e('Speech to text', 'wizard-ai'); ?>"><span class="dashicons dashicons-microphone"></span></button>
                    <div class="wai-prompt-hint"><?php esc_html_e('Ctrl + Enter to send', 'wizard-ai'); ?></div>
                </div>
                <div class="wai-toolbar">
                    <div class="wai-toolbar-left">
                        <select id="wai-playground-model">
                            <option value=""><?php esc_html_e('Automatic (Default)', 'wizard-ai'); ?></option>
                        </select>
                        
                        <label class="wai-fallback-models-label" id="wai-fallback-models-container">
                            <input type="checkbox" id="wai-fallback-models"> <?php esc_html_e('Auto-retry with other models on failure', 'wizard-ai'); ?>
                        </label>
                        <div class="wai-toolbar-separator"></div>
                        
                        <button type="button" id="wai-export-session" class="wai-session-btn" title="<?php esc_attr_e('Export session prompts to a JSON file', 'wizard-ai'); ?>" style="display: none;">
                            <span class="dashicons dashicons-download"></span>
                        </button>
                        <button type="button" id="wai-import-session" class="wai-session-btn" title="<?php esc_attr_e('Import prompts from JSON file and execute', 'wizard-ai'); ?>">
                            <span class="dashicons dashicons-upload"></span>
                        </button>
                        <input type="file" id="wai-import-file" accept=".json" class="wai-hidden">
                    </div>
                    <div class="wai-toolbar-right" style="display:flex; gap:10px;">
                    <label class="wai-auto-approve-label">
                            <input type="checkbox" id="wai-global-auto-approve"> <abbr title="<?php esc_html_e('Skip approvation and Auto-approve tasks for this session', 'wizard-ai'); ?>"><?php esc_html_e('Auto-approve', 'wizard-ai'); ?></abbr>
                        </label>
                    <button type="button" id="wai-toggle-safe-mode" class="button button-secondary wai-session-btn <?php echo file_exists(ABSPATH . '.wai_safe') ? 'wai-safe-mode-active' : ''; ?>" title="<?php esc_attr_e('Toggle AI Safe Mode (forces AI into isolated environment)', 'wizard-ai'); ?>" data-active="<?php echo file_exists(ABSPATH . '.wai_safe') ? '1' : '0'; ?>">
                            <span class="dashicons dashicons-shield"></span>
                        </button>
                    <button type="button" id="wai-playground-send" class="button button-primary button-large" title="<?php esc_attr_e('Send', 'wizard-ai'); ?>">
                        <span class="dashicons dashicons-controls-play"></span>
                    </button>
            </div>
                </div>
            </div>
                
            <div class="card wai-context-card">
                <details>
                    <summary class="wai-card-summary-wrap">
                        <h2>
                            <span class="dashicons dashicons-admin-settings"></span> 
                            <?php esc_html_e('Context', 'wizard-ai'); ?>
                        </h2>
                    </summary>
                    <div class="wai-context-columns">
                        <div class="wai-context-col">
                            <label for="wai-system-info-context">
                                <strong><?php esc_html_e('System Info', 'wizard-ai'); ?></strong>
                                <input type="checkbox" id="wai-include-system-info" value="1" checked style="margin-left:5px; margin-top:-2px;">
                                <span style="font-size:11px; font-weight:normal;"><?php esc_html_e('Pass with prompt', 'wizard-ai'); ?></span>
                            </label>
                            <textarea id="wai-system-info-context" class="wai-context-textarea" readonly><?php echo esc_textarea($this->get_environment_details()); ?></textarea>
                        </div>

                        <div class="wai-context-col">
                            <label for="wai-session-context"><strong><?php esc_html_e('Session', 'wizard-ai'); ?></strong></label>
                            <textarea id="wai-session-context" class="wai-context-textarea"></textarea>
                        </div>
                        <div class="wai-context-col">
                            <label for="wai-permanent-context"><strong><?php esc_html_e('Permanent', 'wizard-ai'); ?></strong></label>
                            <textarea id="wai-permanent-context" class="wai-context-textarea" placeholder="<?php esc_attr_e('Add your site-specific context here. E.g. \'This site uses TailwindCSS\'', 'wizard-ai'); ?>"><?php echo esc_textarea(get_option('wai_permanent_context', '')); ?></textarea>
                        </div>
                    </div>
                </details>
            </div>
                
            <?php
            $upload_dir = wp_upload_dir();
            $db_path = $upload_dir['basedir'] . '/wai/rag.sqlite';
            $has_rag_data = false;
            if (file_exists($db_path)) {
                try {
                    // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
                    $db = new \PDO('sqlite:' . $db_path);
                    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
                    $result = $db->query("SELECT COUNT(*) as c FROM document_embeddings");
                    if ($result) {
                        $row = $result->fetch(\PDO::FETCH_ASSOC);
                        if (!empty($row['c']) && $row['c'] > 0) {
                            $has_rag_data = true;
                        }
                    }
                } catch (\Exception $e) {}
            }
            if ($has_rag_data):
            ?>
            <div class="card wai-rag-card">
                <details>
                    <summary class="wai-card-summary-wrap">
                        <h2>
                            <span class="dashicons dashicons-database"></span> 
                            <?php esc_html_e('RAG Data', 'wizard-ai'); ?>
                        </h2>
                    </summary>
                    <div class="wai-context-columns">
                        <?php 
                        $rag_json = wp_upload_dir()['basedir'] . '/wai/rag.json';
                        if (file_exists($rag_json)): ?>
                            <div class="wai-context-col" id="wai-rag-container">
                                <button type="button" class="button" id="wai-load-rag-data"><?php esc_html_e('Load RAG Data', 'wizard-ai'); ?></button>
                                <p class="description" style="margin-top:5px; font-size:11px;"><?php esc_html_e('RAG data might be very large, load it only if you want to inspect or pass it to the prompt.', 'wizard-ai'); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="wai-context-col"><p><?php esc_html_e('No RAG data found.', 'wizard-ai'); ?></p></div>
                        <?php endif; ?>
                    </div>
                    <div style="padding: 0 15px 15px 15px;">
                        <p style="font-size: 12px; margin-top: 0; color: #666;"><em>Note: RAG Data checkboxes are automatically unchecked after the prompt is sent to save tokens on subsequent requests.</em></p>
                    </div>
                </details>
            </div>
            <?php endif; ?>
                
            <div class="wai-cards-row">
                <div class="card wai-prompts-card wai-column-card">
                    <?php $recent_prompts = $this->get_last_prompts(10); ?>
                <?php if (empty($recent_prompts)): ?>
                <details>
                    <summary class="wai-card-summary-wrap">
                        <h2>
                            <span class="dashicons dashicons-lightbulb"></span> 
                            <?php esc_html_e('Try these examples:', 'wizard-ai'); ?>
                        </h2>
                    </summary>
                <div class="wai-examples-wrapper">
                    <div class="wai-examples-list">
                        <?php
                        $examples = [
                            "Create a new Gutenberg block that displays a testimonial slider",
                            "Register a new custom block category called 'Wizard UI'",
                            "Edit my existing 'Hero' block to add a background video option",
                            "Enable the core/quote block in my block editor settings",
                            "Publish my latest blog news",
                            "Create a new page talking about my new product",
                            "Create the WPML translation in FR of my page",
                            "Add a banner with promo text before the woo checkout",
                            "Fix the compatibility of plugin xyz with PHP 8.5",
                            "Hide the adminbar for all non admin users"
                        ];
                        foreach ($examples as $example) {
                            echo '<button type="button" class="button button-secondary wai-example-btn" onclick="document.getElementById(\'wai-playground-prompt\').value = this.innerText; document.getElementById(\'wai-playground-prompt\').focus();">' . esc_html($example) . '</button>';
                        }
                        ?>
                    </div>
                </div>
                </details>
                <?php else: ?>
                <details>
                    <summary class="wai-card-summary-wrap">
                        <h2>
                            <span class="dashicons dashicons-clock"></span> 
                            <?php esc_html_e('Recent', 'wizard-ai'); ?>
                        </h2>
                    </summary>
                <div class="wai-recent-prompts-wrapper">
                    <div style="margin-bottom: 15px;">
                        <button type="button" id="wai-restore-last-session" class="button button-primary" style="width:100%; display:none;">
                            <?php esc_html_e('Restore Last Session', 'wizard-ai'); ?>
                        </button>
                    </div>
                    <div class="wai-recent-prompts-list">
                        <?php
                        foreach ($recent_prompts as $log_line) {
                            if (preg_match('/^\[(.*?)\] (.*?) : (.*)$/', $log_line, $matches)) {
                                $prompt_text = $matches[3];
                                $date_text = $matches[1];
                                $user_text = $matches[2];
                                /* translators: 1: Date, 2: User */
                                $display_text = sprintf(__('On %1$s by %2$s', 'wizard-ai'), $date_text, $user_text);
                                echo '<button type="button" class="button button-secondary wai-recent-prompt-btn" onclick="document.getElementById(\'wai-playground-prompt\').value = this.dataset.prompt; document.getElementById(\'wai-playground-prompt\').focus();" data-prompt="' . esc_attr($prompt_text) . '" title="' . esc_attr($display_text) . '">' . esc_html($prompt_text) . '</button>';
                            }
                        }
                        ?>
                    </div>
                    <?php 
                    $upload_dir = wp_upload_dir();
                    $log_url = $upload_dir['baseurl'] . '/wai/logs/prompts.log';
                    ?>
                    <div class="wai-recent-prompts-log-link">
                        <a href="<?php echo esc_url($log_url); ?>" target="_blank" class="button button-small"><?php esc_html_e('View prompts.log', 'wizard-ai'); ?></a>
                    </div>
                </div>
                </details>
                <?php endif; ?>
            </div>
                
                <?php
                $upload_dir = wp_upload_dir();
                $backup_dir = $upload_dir['basedir'] . '/wai/backup';
                $backup_actions = [];
                if (is_dir($backup_dir)) {
                    $files = glob($backup_dir . '/*.json');
                    if ($files) {
                        foreach ($files as $file) {
                            $data = json_decode(file_get_contents($file), true);
                            if ($data) {
                                $filename = basename($file);
                                $date = filemtime($file);
                                $desc = '';
                                $extra_html = '';
                                if ($data['action'] === 'modify-file') {
                                    $rel_path = str_replace(wp_normalize_path(WP_CONTENT_DIR), '', wp_normalize_path($data['original_path']));
                                    $desc = 'Modified file: ' . ltrim($rel_path, '/');
                                } elseif ($data['action'] === 'db-query') {
                                    $desc = 'DB ' . $data['type'] . ' on table: ' . $data['table'];
                                    if (!empty($data['query'])) {
                                        $extra_html .= '<li><strong>Query:</strong> <code>' . esc_html(strlen($data['query']) > 100 ? substr($data['query'], 0, 100) . '...' : $data['query']) . '</code></li>';
                                    }
                                } elseif ($data['action'] === 'update-options') {
                                    $opts = array_keys($data['options']);
                                    $desc = 'Options updated: ' . implode(', ', $opts);
                                } elseif ($data['action'] === 'execute-php-rollback' || $data['action'] === 'global-rollback') {
                                    $details = [];
                                    if (!empty($data['files'])) {
                                        $details[] = count($data['files']) . ' files';
                                        $file_links = [];
                                        foreach ($data['files'] as $i => $f) {
                                            $base = basename($f['path']);
                                            if ($f['is_new']) {
                                                $file_links[] = esc_html($base) . ' (New)';
                                            } else {
                                                $dl_url = rest_url('wizard-ai/v1/download-ai-backup?id=' . $filename . '&type=file&index=' . $i . '&_wpnonce=' . wp_create_nonce('wp_rest'));
                                                $file_links[] = esc_html($base) . ' <a href="' . esc_url($dl_url) . '" target="_blank" title="Download Original">(Download)</a>';
                                            }
                                        }
                                        $extra_html .= '<li><strong>Files:</strong> ' . implode(', ', $file_links) . '</li>';
                                    }
                                    if (!empty($data['db_changes'])) {
                                        $details[] = count($data['db_changes']) . ' DB changes';
                                        $tables = array_unique(array_column($data['db_changes'], 'table'));
                                        $dl_url = rest_url('wizard-ai/v1/download-ai-backup?id=' . $filename . '&type=sql&_wpnonce=' . wp_create_nonce('wp_rest'));
                                        $extra_html .= '<li><strong>Tables:</strong> ' . esc_html(implode(', ', $tables)) . ' <a href="' . esc_url($dl_url) . '" target="_blank" title="Download SQL Dump">(Download SQL)</a></li>';
                                    }
                                    if (!empty($data['options'])) {
                                        $details[] = count($data['options']) . ' options';
                                        $extra_html .= '<li><strong>Options:</strong> ' . esc_html(implode(', ', array_keys($data['options']))) . '</li>';
                                    }
                                    if (!empty($data['posts'])) {
                                        $details[] = count($data['posts']) . ' posts';
                                        $extra_html .= '<li><strong>Posts:</strong> ' . esc_html(implode(', ', array_keys($data['posts']))) . '</li>';
                                    }
                                    $desc = 'AI Action Rollback' . (!empty($details) ? ' (' . implode(', ', $details) . ')' : '');
                                } elseif ($data['action'] === 'cron-rollback') {
                                    $desc = 'Automated Task Rollback';
                                } elseif ($data['action'] === 'plugin-backup') {
                                    $desc = 'Plugin Backup: ' . esc_html($data['slug']);
                                    $extra_html .= '<li><strong>File:</strong> ' . esc_html(basename($data['zip_path'])) . '</li>';
                                }
                                
                                if ($extra_html) {
                                    $extra_html = '<ul style="margin: 4px 0 0 10px; font-size: 11px; list-style-type: disc; opacity: 0.85;">' . $extra_html . '</ul>';
                                }

                                if ($desc) {
                                    $backup_actions[] = [
                                        'id' => $filename,
                                        'date' => $date,
                                        'desc' => $desc,
                                        'extra' => $extra_html
                                    ];
                                }
                            }
                        }
                        usort($backup_actions, function($a, $b) { return $b['date'] - $a['date']; });
                    }
                }
                if (!empty($backup_actions)): 
                ?>
                <div class="card wai-backups-card wai-column-card">
                    <details>
                        <summary class="wai-card-summary-wrap">
                            <h2>
                                <span class="dashicons dashicons-update-alt"></span> 
                                <?php esc_html_e('Backups', 'wizard-ai'); ?>
                            </h2>
                        </summary>
                <div id="wai-backups-container" class="wai-backups-container">
                    <p class="wai-backups-summary"><?php 
                        /* translators: %d: Number of backups */
                        echo sprintf(esc_html__('There are %d available backups', 'wizard-ai'), count($backup_actions)); 
                    ?></p>
                    <ul class="wai-backups-list">
                            <?php foreach ($backup_actions as $action): ?>
                                <li class="wai-backup-row" style="flex-direction: column; align-items: flex-start; padding: 8px;">
                                    <div style="display: flex; justify-content: space-between; width: 100%; align-items: flex-start;">
                                        <span class="wai-backup-desc" title="<?php echo esc_attr($action['desc']); ?>" style="line-height: 1.4; max-width: 80%;">
                                            <strong>[<?php echo esc_html(date_i18n('Y-m-d H:i:s', $action['date'])); ?>]</strong><br>
                                            <?php echo esc_html($action['desc']); ?>
                                        </span>
                                        <button type="button" class="button button-small wai-rollback-btn" data-backup-id="<?php echo esc_attr($action['id']); ?>">↩️ Restore</button>
                                    </div>
                                    <?php if (!empty($action['extra'])) echo wp_kses_post($action['extra']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <div class="wai-backups-actions">
                        <button type="button" id="wai-clear-backups" class="button button-secondary wai-btn-danger">
                            <?php esc_html_e('🗑️ Clear All Rollback Backups', 'wizard-ai'); ?>
                        </button>
                    </div>
                </div>
                </details>
                </div>
                <?php else: ?>
                <div class="wai-empty-column"></div>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            <div class="notice notice-warning inline">
                <p><strong><?php esc_html_e('Access Denied:', 'wizard-ai'); ?></strong> <?php esc_html_e('Please configure an AI Connector to access the Playground.', 'wizard-ai'); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="card wai-safemode-card" style="display: none;">
                <details>
                    <summary class="wai-card-summary-wrap">
                        <h2>
                            <span class="dashicons dashicons-shield"></span> 
                            <?php esc_html_e('Safe Mode', 'wizard-ai'); ?>
                        </h2>
                    </summary>
                    <div class="wai-safemode-info" style="padding: 0 15px 15px 15px;">
                        <p><?php esc_html_e('The Playground UI runs in a strictly isolated environment (Safe Mode). All other plugins and the active theme are temporarily disabled on this page to ensure maximum stability and prevent third-party fatal errors from crashing the chat.', 'wizard-ai'); ?></p>
                        <p><strong><?php esc_html_e('AI Auto-Recovery:', 'wizard-ai'); ?></strong> <?php esc_html_e('By default, the AI executes tasks with ALL plugins loaded, so it can access WooCommerce, WPML, etc. freely. If a third-party plugin causes a Fatal Error (Error 500) during execution, the system will automatically enforce Strict Safe Mode on the AI to recover without breaking the chat.', 'wizard-ai'); ?></p>
                        <p><strong><?php esc_html_e('Post-Task Verification:', 'wizard-ai'); ?></strong> <?php esc_html_e('After every critical task (like editing PHP or Database), the system verifies the frontend. If the site is broken, it immediately alerts the AI to fix it or prompts you to rollback.', 'wizard-ai'); ?></p>
                        <div id="wai-safemode-status-wrap" style="margin-top: 10px; padding: 10px; background: #f0f0f1; border-left: 4px solid #72aee6; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php esc_html_e('Current AI Status:', 'wizard-ai'); ?></strong> <span id="wai-safemode-status"><?php esc_html_e('Native (All Plugins Active)', 'wizard-ai'); ?></span>
                            </div>
                        </div>
                    </div>
                </details>
            </div>
            
            <div class="notice notice-info inline wai-api-config-notice">
                <p>
                    <?php esc_html_e('The AI API configuration is managed directly by WordPress Core.', 'wizard-ai'); ?>
                    <a href="<?php echo esc_url(admin_url('options-general.php?page=options-connectors-wp-admin')); ?>"><?php esc_html_e('Manage Core AI Settings', 'wizard-ai'); ?> &rarr;</a>
                </p>
            </div>
        </div>

        <?php
        $safe_token = get_option('wai_mcp_token');
        if (empty($safe_token)) {
            $safe_token = wp_generate_password(24, false);
            update_option('wai_mcp_token', $safe_token);
        }
        $playground_url = admin_url('admin.php?page=wizard-ai');
        $safe_mode_url = add_query_arg(['wai_enforce_safe_mode' => '1', 'token' => $safe_token], wp_login_url($playground_url));
        ?>
        <div class="notice notice-error inline" style="margin-left: 0;">
            <p><strong><?php esc_html_e('Emergency Safe Mode Login', 'wizard-ai'); ?>:</strong> <?php esc_html_e('If a plugin or theme causes a fatal 500 error that locks you out of the WordPress admin, use this URL to safely log in with all plugins/themes disabled:', 'wizard-ai'); ?></p>
            <p style="background: #fff; padding: 10px; font-weight: bold; overflow-x: auto;">
                <a href="<?php echo esc_url($safe_mode_url); ?>" target="_blank" style="text-decoration: none;">
                    <?php echo esc_url($safe_mode_url); ?>
                </a>
            </p>
            <p><em><?php esc_html_e('Save this URL somewhere safe. The unique token prevents bots from bypassing system protections (like Wordfence 2FA) by forcing safe mode.', 'wizard-ai'); ?></em></p>
        </div>
        <?php
    }

    public function enqueue_playground_scripts($hook) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (strpos($hook, 'wizard-ai') !== false || (isset($_GET['page']) && wp_unslash($_GET['page']) === 'wizard-ai')) {
            $user = wp_get_current_user();
            $prev = $user->syntax_highlighting;
            $user->syntax_highlighting = 'true';
            
            \WizardAi\Modules\Ai\Ai::instance()->cm_settings = wp_enqueue_code_editor(array('type' => 'application/x-httpd-php'));
            \WizardAi\Modules\Ai\Ai::instance()->cm_sql_settings = wp_enqueue_code_editor(array('type' => 'text/x-sql'));
            
            $user->syntax_highlighting = $prev;

            // Force manual enqueue to guarantee loading
            wp_enqueue_script('wp-codemirror');
            wp_enqueue_style('wp-codemirror');
            wp_enqueue_script('code-editor');
            wp_enqueue_style('code-editor');
            
            wp_enqueue_script('jquery-ui-resizable');
            
            wp_enqueue_style('wbai-playground-style', WIZARD_AI_URL . 'modules/playground/assets/css/playground.css', array(), filemtime(WIZARD_AI_PATH . 'modules/playground/assets/css/playground.css'));
            
            // The JS file was probably enqueued elsewhere previously or was missing. Let's make sure it's enqueued here.
            wp_enqueue_script('wbai-playground-script', WIZARD_AI_URL . 'modules/playground/assets/js/playground.js', array('jquery', 'wai-select2'), filemtime(WIZARD_AI_PATH . 'modules/playground/assets/js/playground.js'), true);
            
            $wai_settings = [
                'nonceTest' => wp_create_nonce("wai_test_nonce"),
                'nonceRest' => wp_create_nonce("wp_rest"),
                'restUrl' => esc_url_raw(rest_url('wizard-ai/v1/ai-chat')),
                'homeUrl' => esc_url_raw(home_url('/')),
                'textTesting' => __('Testing...', 'wizard-ai'),
                'textAiThinking' => __('AI is thinking...', 'wizard-ai'),
                'preferredModel' => get_user_meta(get_current_user_id(), '_wai_preferred_model', true),
                'objectType' => 'toplevel_page_wizard-ai-playground',
                'cmSettings' => \WizardAi\Modules\Ai\Ai::instance()->cm_settings ?: ['codemirror' => ['mode' => 'php', 'lineNumbers' => true]],
                'cmSqlSettings' => \WizardAi\Modules\Ai\Ai::instance()->cm_sql_settings ?: ['codemirror' => ['mode' => 'sql', 'lineNumbers' => true]],
                'debugMode' => (defined('WP_DEBUG') && WP_DEBUG) ? true : false,
                'ragUrl' => wp_upload_dir()['baseurl'] . '/wai/rag.json'
            ];
            wp_add_inline_script('wbai-playground-script', 'window.waiSettings = ' . wp_json_encode($wai_settings) . ';', 'before');
            
            // Enqueue native WordPress media uploader
            wp_enqueue_media();
        }
    }
}
