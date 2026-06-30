<?php
namespace WizardAi\Modules\Ai\Traits;

trait Playground {

    public function wb_ai_page_html() {
        $is_ai_configured = false;
        if (class_exists('\WordPress\AiClient\AiClient')) {
            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            if (count($registry->getRegisteredProviderIds()) > 0) {
                $is_ai_configured = true;
            }
        }

        $channel = get_option('wizard_blocks_gemini_api_channel', 'v1beta');
        $cm_settings = property_exists($this, 'cm_settings') ? $this->cm_settings : false;
        $cm_sql_settings = property_exists($this, 'cm_sql_settings') ? $this->cm_sql_settings : false;
        ?>
        <div class="wrap">
            <h1>
                <span class="dashicons dashicons-superhero"></span>
                <?php esc_html_e('Wizard Blocks AI Playground', 'wizard-ai'); ?>
            </h1>
            
            <?php if ($is_ai_configured): ?>
            
            <div class="card wbai-playground-card">
                <div id="wbai-playground-chat-wrapper">
                    <div id="wbai-playground-chat"></div>
                    <button type="button" class="button dashicons dashicons-fullscreen-alt toggle-distraction-free"></button>
                </div>
                <div class="wbai-playground-prompt-wrapper">
                    <textarea id="wbai-playground-prompt" placeholder="<?php esc_attr_e('Ask the AI to do something (e.g. create a file, run a query)...', 'wizard-ai'); ?>"></textarea>
                    <button type="button" id="wbai-speech-to-text" title="<?php esc_attr_e('Speech to text', 'wizard-ai'); ?>">🎤</button>
                    <div class="wbai-prompt-hint"><?php esc_html_e('Ctrl + Enter to send', 'wizard-ai'); ?></div>
                </div>
                <div class="wbai-toolbar">
                    <div class="wbai-toolbar-left">
                        <select id="wbai-playground-model">
                            <option value=""><?php esc_html_e('Automatic (Default)', 'wizard-ai'); ?></option>
                        </select>
                        <label class="wbai-auto-approve-label">
                            <input type="checkbox" id="wbai-global-auto-approve"> <?php esc_html_e('Auto-approve tasks for this session', 'wizard-ai'); ?>
                        </label>
                        <label class="wbai-fallback-models-label" id="wbai-fallback-models-container">
                            <input type="checkbox" id="wbai-fallback-models"> <?php esc_html_e('Auto-retry with other models on failure', 'wizard-ai'); ?>
                        </label>
                        <div class="wbai-toolbar-separator"></div>
                        <button type="button" id="wbai-toggle-safe-mode" class="button button-secondary wbai-session-btn <?php echo file_exists(ABSPATH . '.wb_ai_safe') ? 'wbai-safe-mode-active' : ''; ?>" title="<?php esc_attr_e('Toggle AI Safe Mode (forces AI into isolated environment)', 'wizard-ai'); ?>" data-active="<?php echo file_exists(ABSPATH . '.wb_ai_safe') ? '1' : '0'; ?>">
                            <span class="dashicons dashicons-shield"></span>
                        </button>
                        <button type="button" id="wbai-export-session" class="button button-secondary wbai-session-btn" title="<?php esc_attr_e('Export session prompts to a JSON file', 'wizard-ai'); ?>">
                            <span class="dashicons dashicons-download"></span>
                        </button>
                        <button type="button" id="wbai-import-session" class="button button-secondary wbai-session-btn" title="<?php esc_attr_e('Import prompts from JSON file and execute', 'wizard-ai'); ?>">
                            <span class="dashicons dashicons-upload"></span>
                        </button>
                        <input type="file" id="wbai-import-file" accept=".json" class="wbai-hidden">
                    </div>
                    <button type="button" id="wbai-playground-send" class="button button-primary button-large">
                        <?php esc_html_e('Send', 'wizard-ai'); ?>
                    </button>
                </div>
            </div>
                
            <div class="card wbai-context-card">
                <details>
                    <summary class="wbai-card-summary-wrap">
                        <h2>
                            <span class="dashicons dashicons-admin-settings"></span> 
                            <?php esc_html_e('Context', 'wizard-ai'); ?>
                        </h2>
                    </summary>
                    <div class="wbai-context-columns">
                        <div class="wbai-context-col">
                            <label for="wbai-system-info-context">
                                <strong><?php esc_html_e('System Info', 'wizard-ai'); ?></strong>
                                <input type="checkbox" id="wbai-include-system-info" value="1" checked style="margin-left:5px; margin-top:-2px;">
                                <span style="font-size:11px; font-weight:normal;"><?php esc_html_e('Pass with prompt', 'wizard-ai'); ?></span>
                            </label>
                            <textarea id="wbai-system-info-context" class="wbai-context-textarea" readonly><?php echo esc_textarea($this->get_environment_details()); ?></textarea>
                        </div>

                        <div class="wbai-context-col">
                            <label for="wbai-session-context"><strong><?php esc_html_e('Session', 'wizard-ai'); ?></strong></label>
                            <textarea id="wbai-session-context" class="wbai-context-textarea"></textarea>
                        </div>
                        <div class="wbai-context-col">
                            <label for="wbai-permanent-context"><strong><?php esc_html_e('Permanent', 'wizard-ai'); ?></strong></label>
                            <textarea id="wbai-permanent-context" class="wbai-context-textarea" placeholder="<?php esc_attr_e('Add your site-specific context here. E.g. \'This site uses TailwindCSS\'', 'wizard-ai'); ?>"><?php echo esc_textarea(get_option('wbai_permanent_context', '')); ?></textarea>
                        </div>
                    </div>
                </details>
            </div>
                
            <?php
            $upload_dir = wp_upload_dir();
            $db_path = $upload_dir['basedir'] . '/wbai/rag.sqlite';
            $has_rag_data = false;
            if (file_exists($db_path)) {
                try {
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
            <div class="card wbai-rag-card">
                <details>
                    <summary class="wbai-card-summary-wrap">
                        <h2>
                            <span class="dashicons dashicons-database"></span> 
                            <?php esc_html_e('RAG Data', 'wizard-ai'); ?>
                        </h2>
                    </summary>
                    <div class="wbai-context-columns">
                        <?php 
                        $rag_json = wp_upload_dir()['basedir'] . '/wbai/rag.json';
                        $grouped_rag = [];
                        if (file_exists($rag_json)) {
                            $json_content = file_get_contents($rag_json);
                            if ($json_content) {
                                $data = json_decode($json_content, true);
                                if (is_array($data)) {
                                    foreach($data as $item) {
                                        $type = isset($item['post_type']) ? $item['post_type'] : 'other';
                                        if ($type === 'post') $type = 'Posts';
                                        else if ($type === 'product') $type = 'Products';
                                        else if ($type === 'user') $type = 'Users';
                                        else if ($type === 'term') $type = 'Terms';
                                        else if ($type === 'plugin') $type = 'Plugins';
                                        else $type = ucfirst($type);
                                        
                                        if (!isset($grouped_rag[$type])) {
                                            $grouped_rag[$type] = [];
                                        }
                                        $grouped_rag[$type][] = $item;
                                    }
                                }
                            }
                        }

                        if (empty($grouped_rag)) {
                            echo '<div class="wbai-context-col"><p>' . esc_html__('No RAG data found.', 'wizard-ai') . '</p></div>';
                        } else {
                            foreach ($grouped_rag as $type => $items) {
                                $safe_type = esc_attr(strtolower(str_replace(' ', '_', $type)));
                                ?>
                                <div class="wbai-context-col">
                                    <label for="wbai-rag-context-<?php echo $safe_type; ?>">
                                        <strong><?php echo esc_html($type); ?></strong>
                                        <input type="checkbox" class="wbai-rag-checkbox" data-type="<?php echo $safe_type; ?>" id="wbai-include-rag-info-<?php echo $safe_type; ?>" value="1" style="margin-left:5px; margin-top:-2px;">
                                        <span style="font-size:11px; font-weight:normal;"><?php esc_html_e('Pass with prompt', 'wizard-ai'); ?></span>
                                    </label>
                                    <textarea id="wbai-rag-context-<?php echo $safe_type; ?>" class="wbai-context-textarea" readonly><?php echo esc_textarea(wp_json_encode($items, JSON_PRETTY_PRINT)); ?></textarea>
                                </div>
                                <?php
                            }
                        }
                        ?>
                    </div>
                    <div style="padding: 0 15px 15px 15px;">
                        <p style="font-size: 12px; margin-top: 0; color: #666;"><em>Note: RAG Data checkboxes are automatically unchecked after the prompt is sent to save tokens on subsequent requests.</em></p>
                    </div>
                </details>
            </div>
            <?php endif; ?>
                
            <div class="wbai-cards-row">
                <div class="card wbai-prompts-card wbai-column-card">
                    <?php $recent_prompts = $this->get_last_prompts(10); ?>
                <?php if (empty($recent_prompts)): ?>
                <details>
                    <summary class="wbai-card-summary-wrap">
                        <h2>
                            <span class="dashicons dashicons-lightbulb"></span> 
                            <?php esc_html_e('Try these examples:', 'wizard-ai'); ?>
                        </h2>
                    </summary>
                <div class="wbai-examples-wrapper">
                    <div class="wbai-examples-list">
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
                            echo '<button type="button" class="button button-secondary wbai-example-btn" onclick="document.getElementById(\'wbai-playground-prompt\').value = this.innerText; document.getElementById(\'wbai-playground-prompt\').focus();">' . esc_html($example) . '</button>';
                        }
                        ?>
                    </div>
                </div>
                </details>
                <?php else: ?>
                <details>
                    <summary class="wbai-card-summary-wrap">
                        <h2>
                            <span class="dashicons dashicons-clock"></span> 
                            <?php esc_html_e('Recent', 'wizard-ai'); ?>
                        </h2>
                    </summary>
                <div class="wbai-recent-prompts-wrapper">
                    <div style="margin-bottom: 15px;">
                        <button type="button" id="wbai-restore-last-session" class="button button-primary" style="width:100%; display:none;">
                            <?php esc_html_e('Restore Last Session', 'wizard-ai'); ?>
                        </button>
                    </div>
                    <div class="wbai-recent-prompts-list">
                        <?php
                        foreach ($recent_prompts as $log_line) {
                            if (preg_match('/^\[(.*?)\] (.*?) : (.*)$/', $log_line, $matches)) {
                                $prompt_text = $matches[3];
                                $date_text = $matches[1];
                                $user_text = $matches[2];
                                $display_text = sprintf(__('On %s by %s', 'wizard-ai'), $date_text, $user_text);
                                echo '<button type="button" class="button button-secondary wbai-recent-prompt-btn" onclick="document.getElementById(\'wbai-playground-prompt\').value = this.dataset.prompt; document.getElementById(\'wbai-playground-prompt\').focus();" data-prompt="' . esc_attr($prompt_text) . '" title="' . esc_attr($display_text) . '">' . esc_html($prompt_text) . '</button>';
                            }
                        }
                        ?>
                    </div>
                    <?php 
                    $upload_dir = wp_upload_dir();
                    $log_url = $upload_dir['baseurl'] . '/wbai/logs/prompts.log';
                    ?>
                    <div class="wbai-recent-prompts-log-link">
                        <a href="<?php echo esc_url($log_url); ?>" target="_blank" class="button button-small"><?php esc_html_e('View prompts.log', 'wizard-ai'); ?></a>
                    </div>
                </div>
                </details>
                <?php endif; ?>
            </div>
                
                <?php
                $upload_dir = wp_upload_dir();
                $backup_dir = $upload_dir['basedir'] . '/wbai/backup';
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
                                                $dl_url = rest_url('wizard-blocks/v1/download-ai-backup?id=' . $filename . '&type=file&index=' . $i . '&_wpnonce=' . wp_create_nonce('wp_rest'));
                                                $file_links[] = esc_html($base) . ' <a href="' . esc_url($dl_url) . '" target="_blank" title="Download Original">(Download)</a>';
                                            }
                                        }
                                        $extra_html .= '<li><strong>Files:</strong> ' . implode(', ', $file_links) . '</li>';
                                    }
                                    if (!empty($data['db_changes'])) {
                                        $details[] = count($data['db_changes']) . ' DB changes';
                                        $tables = array_unique(array_column($data['db_changes'], 'table'));
                                        $dl_url = rest_url('wizard-blocks/v1/download-ai-backup?id=' . $filename . '&type=sql&_wpnonce=' . wp_create_nonce('wp_rest'));
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
                <div class="card wbai-backups-card wbai-column-card">
                    <details>
                        <summary class="wbai-card-summary-wrap">
                            <h2>
                                <span class="dashicons dashicons-update-alt"></span> 
                                <?php esc_html_e('Backups', 'wizard-ai'); ?>
                            </h2>
                        </summary>
                <div id="wbai-backups-container" class="wbai-backups-container">
                    <p class="wbai-backups-summary"><?php echo sprintf(esc_html__('There are %d available backups', 'wizard-ai'), count($backup_actions)); ?></p>
                    <ul class="wbai-backups-list">
                            <?php foreach ($backup_actions as $action): ?>
                                <li class="wbai-backup-row" style="flex-direction: column; align-items: flex-start; padding: 8px;">
                                    <div style="display: flex; justify-content: space-between; width: 100%; align-items: flex-start;">
                                        <span class="wbai-backup-desc" title="<?php echo esc_attr($action['desc']); ?>" style="line-height: 1.4; max-width: 80%;">
                                            <strong>[<?php echo esc_html(date_i18n('Y-m-d H:i:s', $action['date'])); ?>]</strong><br>
                                            <?php echo esc_html($action['desc']); ?>
                                        </span>
                                        <button type="button" class="button button-small wbai-rollback-btn" data-backup-id="<?php echo esc_attr($action['id']); ?>">↩️ Restore</button>
                                    </div>
                                    <?php if (!empty($action['extra'])) echo $action['extra']; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <div class="wbai-backups-actions">
                        <button type="button" id="wbai-clear-backups" class="button button-secondary wbai-btn-danger">
                            <?php esc_html_e('🗑️ Clear All Rollback Backups', 'wizard-ai'); ?>
                        </button>
                    </div>
                </div>
                </details>
                </div>
                <?php else: ?>
                <div class="wbai-empty-column"></div>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            <div class="notice notice-warning inline">
                <p><strong><?php esc_html_e('Access Denied:', 'wizard-ai'); ?></strong> <?php esc_html_e('Please configure an AI Connector to access the Playground.', 'wizard-ai'); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="card wbai-safemode-card" style="display: none;">
                <details>
                    <summary class="wbai-card-summary-wrap">
                        <h2>
                            <span class="dashicons dashicons-shield"></span> 
                            <?php esc_html_e('Safe Mode', 'wizard-ai'); ?>
                        </h2>
                    </summary>
                    <div class="wbai-safemode-info" style="padding: 0 15px 15px 15px;">
                        <p><?php esc_html_e('The Playground UI runs in a strictly isolated environment (Safe Mode). All other plugins and the active theme are temporarily disabled on this page to ensure maximum stability and prevent third-party fatal errors from crashing the chat.', 'wizard-ai'); ?></p>
                        <p><strong><?php esc_html_e('AI Auto-Recovery:', 'wizard-ai'); ?></strong> <?php esc_html_e('By default, the AI executes tasks with ALL plugins loaded, so it can access WooCommerce, WPML, etc. freely. If a third-party plugin causes a Fatal Error (Error 500) during execution, the system will automatically enforce Strict Safe Mode on the AI to recover without breaking the chat.', 'wizard-ai'); ?></p>
                        <p><strong><?php esc_html_e('Post-Task Verification:', 'wizard-ai'); ?></strong> <?php esc_html_e('After every critical task (like editing PHP or Database), the system verifies the frontend. If the site is broken, it immediately alerts the AI to fix it or prompts you to rollback.', 'wizard-ai'); ?></p>
                        <div id="wbai-safemode-status-wrap" style="margin-top: 10px; padding: 10px; background: #f0f0f1; border-left: 4px solid #72aee6; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php esc_html_e('Current AI Status:', 'wizard-ai'); ?></strong> <span id="wbai-safemode-status"><?php esc_html_e('Native (All Plugins Active)', 'wizard-ai'); ?></span>
                            </div>
                        </div>
                    </div>
                </details>
            </div>
            
            <div class="notice notice-info inline wbai-api-config-notice">
                <p>
                    <?php esc_html_e('The AI API configuration is managed directly by WordPress Core.', 'wizard-ai'); ?>
                    <a href="<?php echo esc_url(admin_url('options-general.php?page=options-connectors-wp-admin')); ?>"><?php esc_html_e('Manage Core AI Settings', 'wizard-ai'); ?> &rarr;</a>
                </p>
            </div>
        </div>
        <script>
        window.wbaiSettings = {
            nonceTest: '<?php echo esc_js(wp_create_nonce("wbai_test_nonce")); ?>',
            nonceRest: '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>',
            restUrl: '<?php echo esc_js(esc_url_raw(rest_url('wizard-blocks/v1/ai-chat'))); ?>',
            homeUrl: '<?php echo esc_js(esc_url_raw(home_url('/'))); ?>',
            textTesting: '<?php echo esc_js(__('Testing...', 'wizard-ai')); ?>',
            textAiThinking: '<?php echo esc_js(__('AI is thinking...', 'wizard-ai')); ?>',
            preferredModel: '<?php echo esc_js(get_user_meta(get_current_user_id(), '_wbai_preferred_model', true)); ?>',
            cmSettings: <?php echo empty($cm_settings) ? json_encode(['codemirror' => ['mode' => 'php', 'lineNumbers' => true]]) : wp_json_encode($cm_settings); ?>,
            cmSqlSettings: <?php echo empty($cm_sql_settings) ? json_encode(['codemirror' => ['mode' => 'sql', 'lineNumbers' => true]]) : wp_json_encode($cm_sql_settings); ?>,
            debugMode: <?php echo (defined('WP_DEBUG') && WP_DEBUG) ? 'true' : 'false'; ?>
        };
        </script>
        <!-- Load Select2 -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="<?php echo esc_url(WIZARD_AI_URL . 'modules/ai/assets/js/playground.js?v=' . time()); ?>"></script>
        <?php
    }


    private function get_prompts_log_file() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wbai/logs/prompts';
        if (!is_dir($log_dir)) wp_mkdir_p($log_dir);
        $date = current_time('Y-m-d');
        return $log_dir . '/' . $date . '.log';
    }

    private function get_last_prompts($limit = 10) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wbai/logs/prompts';
        if (!is_dir($log_dir)) return [];
        
        $files = glob($log_dir . '/*.log');
        if (empty($files)) return [];
        
        rsort($files); // Sort descending (latest date first)
        
        $all_lines = [];
        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines) {
                $all_lines = array_merge($all_lines, array_reverse($lines));
                if (count($all_lines) >= $limit) {
                    break;
                }
            }
        }
        return array_slice($all_lines, 0, $limit);
    }

    private function get_directory_tree($dir, $max_files = 200, &$file_count = 0, $prefix = '') {
        $tree = '';
        if (!is_dir($dir)) return $tree;
        
        $excludes = ['.git', 'node_modules', 'vendor', 'assets', 'images', 'dist', 'build', '.DS_Store'];
        $files = array_diff(scandir($dir), ['.', '..']);
        $files = array_filter($files, function($f) use ($excludes) {
            return !in_array($f, $excludes);
        });
        
        if (empty($files)) return $tree;
        $last_key = array_key_last($files);
        
        foreach ($files as $key => $file) {
            if ($file_count >= $max_files) {
                if ($file_count === $max_files) {
                    $tree .= $prefix . "└── ... (tree truncated, exceeded {$max_files} files)\n";
                    $file_count++;
                }
                break;
            }
            
            $path = $dir . '/' . $file;
            $is_last = ($key === $last_key);
            $pointer = $is_last ? '└── ' : '├── ';
            
            $tree .= $prefix . $pointer . $file . "\n";
            $file_count++;
            
            if (is_dir($path)) {
                $extension_prefix = $is_last ? '    ' : '│   ';
                $tree .= $this->get_directory_tree($path, $max_files, $file_count, $prefix . $extension_prefix);
            }
        }
        return $tree;
    }

    private function get_environment_details() {
        global $wpdb;
        $active_blocks = [];
        if (class_exists('\WizardBlocks\Modules\Block\Block')) {
            $wb = \WizardBlocks\Modules\Block\Block::instance();
            $block_posts = get_posts(['post_type' => 'block', 'posts_per_page' => -1, 'post_status' => 'publish']);
            foreach ($block_posts as $post) {
                $json = $wb->get_json_data($post->post_name);
                $textdomain = $wb->get_block_textdomain($json);
                $block_dir = $wb->get_blocks_dir($post->post_name, $textdomain);
                $active_blocks[] = $textdomain . '/' . $post->post_name . ' (Path: ' . wp_normalize_path($block_dir) . '/block.json)';
            }
        } else {
            $active_blocks = array_map(function($post) { return $post->post_name; }, get_posts(['post_type' => 'block', 'posts_per_page' => -1, 'post_status' => 'publish']));
        }
        
        $latest_errors = '';
        $debug_log_path = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($debug_log_path) && is_readable($debug_log_path)) {
            $filesize = filesize($debug_log_path);
            $read_size = min(100000, $filesize);
            $file = @fopen($debug_log_path, 'r');
            if ($file && $read_size > 0) {
                fseek($file, -$read_size, SEEK_END);
                $log_content = fread($file, $read_size);
                fclose($file);
                
                if ($log_content) {
                    $lines = explode("\n", $log_content);
                    $lines = array_reverse($lines);
                    $unique_errors = [];
                    foreach ($lines as $line) {
                        if (stripos($line, 'Parse error') !== false || stripos($line, 'Fatal error') !== false || stripos($line, 'Uncaught Error') !== false || stripos($line, 'database error') !== false) {
                            $error_msg = preg_replace('/^\[.*?\]\s*/', '', $line);
                            if (!in_array($error_msg, $unique_errors)) {
                                $unique_errors[] = $error_msg;
                                if (count($unique_errors) >= 5) break;
                            }
                        }
                    }
                    if (!empty($unique_errors)) {
                        $latest_errors = "\n- LATEST ERRORS (from debug.log):\n  * " . implode("\n  * ", $unique_errors) . "\n";
                    }
                }
            }
        }

        $real_plugins_str = $wpdb->get_var("SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'active_plugins'");
        $real_plugins = $real_plugins_str ? unserialize($real_plugins_str) : [];
        if (!is_array($real_plugins)) $real_plugins = [];
        
        $real_sw_plugins_str = $wpdb->get_var("SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'active_sitewide_plugins'");
        $real_sw_plugins = $real_sw_plugins_str ? unserialize($real_sw_plugins_str) : [];
        if (!is_array($real_sw_plugins)) $real_sw_plugins = [];
        $real_plugins = array_merge($real_plugins, array_keys($real_sw_plugins));
        
        $real_stylesheet = $wpdb->get_var("SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'stylesheet'");
        $real_template = $wpdb->get_var("SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'template'");
        $real_theme = wp_get_theme($real_stylesheet);
        $real_theme_name = $real_theme->exists() ? $real_theme->get('Name') : $real_stylesheet;
        
        $theme_dir = get_theme_root() . '/' . $real_stylesheet;
        $theme_tree = $this->get_directory_tree($theme_dir);
        $theme_info = "";
        if (!empty($theme_tree)) {
            $theme_info = "- Active Theme Folder Tree (Excluding vendor/node_modules):\n" . $theme_tree;
        }
        
        $db_tables = $wpdb->get_col("SHOW TABLES");
        $tables_info = "- Database Tables: " . (!empty($db_tables) ? implode(', ', $db_tables) : 'None') . "\n";

        $wpml_info = "";
        if (defined('ICL_SITEPRESS_VERSION') || has_filter('wpml_active_languages')) {
            $default_lang = apply_filters('wpml_default_language', null);
            $active_langs = apply_filters('wpml_active_languages', null, 'orderby=id&order=desc');
            $langs_str = [];
            if (is_array($active_langs)) {
                foreach ($active_langs as $lang) {
                    $langs_str[] = isset($lang['code']) ? $lang['code'] . ($lang['code'] === $default_lang ? ' (Main)' : '') : '';
                }
                $langs_str = array_filter($langs_str);
                if (!empty($langs_str)) {
                    $wpml_info = "- WPML Languages: " . implode(', ', $langs_str) . "\n";
                }
            }
        }

        $patterns_info = "";
        if (class_exists('\WP_Block_Patterns_Registry')) {
            $patterns = \WP_Block_Patterns_Registry::get_instance()->get_all_registered();
            if (!empty($patterns)) {
                $patterns_list = [];
                foreach ($patterns as $pattern) {
                    $patterns_list[] = isset($pattern['name']) ? $pattern['name'] : '';
                }
                $patterns_list = array_filter($patterns_list);
                if (!empty($patterns_list)) {
                    $patterns_info = "- Gutenberg Patterns (insert using <!-- wp:pattern {\"slug\":\"PATTERN_NAME\"} /-->):\n  " . implode(', ', $patterns_list) . "\n\n";
                }
            }
        }

        $templates_info = "";
        if (function_exists('get_block_templates')) {
            $templates = get_block_templates();
            if (!empty($templates)) {
                $templates_list = [];
                foreach ($templates as $template) {
                    $templates_list[] = isset($template->id) ? $template->id : '';
                }
                $templates_list = array_filter($templates_list);
                if (!empty($templates_list)) {
                    $templates_info = "- Gutenberg Templates:\n  " . implode(', ', $templates_list) . "\n\n";
                }
            }
        }

        return "ENVIRONMENT DETAILS:\n"
            . "- WordPress Version: " . get_bloginfo('version') . "\n"
            . "- Site URL: " . get_bloginfo('url') . "\n"
            . "- ABSPATH (Root Directory): " . ABSPATH . "\n"
            . "- WP_CONTENT_DIR: " . WP_CONTENT_DIR . "\n\n"
            . "- Active Plugins: " . implode(', ', $real_plugins) . "\n\n"
            . "- Active Blocks: " . (!empty($active_blocks) ? implode(', ', $active_blocks) : 'None') . "\n\n"
            . $patterns_info
            . $templates_info
            . "- Active Theme: " . $real_theme_name . " (" . $real_template . ")\n"
            . $theme_info . "\n"
            . "- Database Prefix: " . $wpdb->prefix . "\n"
            . $tables_info
            . $wpml_info
            . $latest_errors;
    }


    public function handle_chat_request(\WP_REST_Request $request) {
        $params = $request->get_json_params();
        $prompt = sanitize_textarea_field($params['prompt'] ?? '');
        $requested_model = sanitize_text_field($params['model'] ?? '');
        
        if (!empty($requested_model)) {
            update_user_meta(get_current_user_id(), '_wbai_preferred_model', $requested_model);
        }
        
        $conversation_id = sanitize_text_field($params['conversation_id'] ?? '');
        $execute_tools = !empty($params['execute_tools']);
        $cancel_tools = !empty($params['cancel_tools']);
        $modified_tools = $params['modified_tools'] ?? [];
        $fallback_models = !empty($params['fallback_models']);
        
        if (class_exists('\WordPress\AiClient\AiClient')) {
            set_time_limit(300);
            $timeout_filter = function($timeout, $url) {
                return 300;
            };
            add_filter('http_request_timeout', $timeout_filter, 999, 2);

            $messages = [];
            
            if (empty($conversation_id)) {
                $conversation_id = uniqid('wbai_');
                $messages = [];
            } else {
                $stored = get_transient($conversation_id);
                if ($stored !== false) {
                    $unserialized = unserialize($stored);
                    if (is_array($unserialized)) {
                        foreach ($unserialized as $msg) {
                            if (method_exists($msg, 'getParts')) {
                                $new_parts = [];
                                foreach ($msg->getParts() as $part) {
                                    if (method_exists($part, 'toArray')) {
                                        $new_parts[] = \WordPress\AiClient\Messages\DTO\MessagePart::fromArray($part->toArray());
                                    } else {
                                        $new_parts[] = clone $part;
                                    }
                                }
                                
                                $role = method_exists($msg, 'getRole') ? $msg->getRole() : null;
                                $role_str = is_object($role) && method_exists($role, '__toString') ? (string)$role : (is_string($role) ? $role : '');
                                $is_model = ($role_str === 'model' || $role_str === 'assistant');
                                
                                if ($is_model) {
                                    $messages[] = new \WordPress\AiClient\Messages\DTO\ModelMessage($new_parts);
                                } else {
                                    $messages[] = new \WordPress\AiClient\Messages\DTO\UserMessage($new_parts);
                                }
                            } else {
                                $messages[] = clone $msg;
                            }
                        }
                    }
                } else {
                    remove_filter('http_request_timeout', $timeout_filter, 999);
                    return new \WP_REST_Response(['success' => false, 'message' => __('Conversation expired or invalid.', 'wizard-ai')], 400);
                }
            }

            if (!$execute_tools && !$cancel_tools && !empty($prompt)) {
                $messages[] = new \WordPress\AiClient\Messages\DTO\UserMessage([
                    new \WordPress\AiClient\Messages\DTO\MessagePart($prompt)
                ]);

                // Log the prompt
                $log_file = $this->get_prompts_log_file();
                $current_user = wp_get_current_user();
                $username = $current_user->exists() ? $current_user->user_login : 'unknown';
                $log_entry = sprintf("[%s] %s : %s\n", current_time('Y-m-d H:i:s'), $username, str_replace("\n", " ", $prompt));
                file_put_contents($log_file, $log_entry, FILE_APPEND);
            }

            if (isset($params['session_context'])) {
                $env_info = sanitize_textarea_field($params['session_context']);
            } else {
                $env_info = '';
            }
            
            $object_id = isset($params['object_id']) ? intval($params['object_id']) : 0;
            $object_type = isset($params['object_type']) ? sanitize_text_field($params['object_type']) : '';
            
            if ($object_id > 0) {
                $meta_info = "";
                $acf_id = $object_id;
                if ($object_type === 'term' || $object_type === 'edit-tags') {
                    $acf_id = 'term_' . $object_id;
                } elseif (in_array($object_type, ['user', 'profile', 'user-edit'])) {
                    $acf_id = 'user_' . $object_id;
                }
                
                if (function_exists('get_fields')) {
                    $acf_fields = get_fields($acf_id);
                    if (!empty($acf_fields)) {
                        $meta_info .= "SCF/ACF Custom Fields:\n" . wp_json_encode($acf_fields, JSON_PRETTY_PRINT) . "\n";
                    }
                } else {
                    if ($object_type === 'post') {
                        $all_meta = get_post_meta($object_id);
                        $filtered_meta = [];
                        foreach ($all_meta as $k => $v) {
                            if (strpos($k, '_') !== 0) {
                                $filtered_meta[$k] = maybe_unserialize($v[0]);
                            }
                        }
                        if (!empty($filtered_meta)) {
                            $meta_info .= "Custom Fields:\n" . wp_json_encode($filtered_meta, JSON_PRETTY_PRINT) . "\n";
                        }
                    }
                }
                if (!empty($meta_info)) {
                    $env_info .= "\n\nOBJECT METADATA (ID: {$object_id}):\n" . $meta_info;
                }
            }

            if (!empty($params['system_info_context'])) {
                $env_info = sanitize_textarea_field($params['system_info_context']) . "\n\n" . $env_info;
            }
            if (!empty($params['rag_context'])) {
                $env_info .= "\n\nRAG VECTOR DATA CONTEXT:\n" . sanitize_textarea_field($params['rag_context']);
            }
            if (isset($params['permanent_context'])) {
                $permanent_info = sanitize_textarea_field($params['permanent_context']);
                update_option('wbai_permanent_context', $permanent_info);
            } else {
                $permanent_info = get_option('wbai_permanent_context', '');
            }
            
            $abilities = function_exists('wp_get_abilities') ? wp_get_abilities() : [];
            $abilities = apply_filters('wizard_blocks_ai_abilities', $abilities);

            $tools_list = "AVAILABLE TOOLS:\n";
            if (!empty($abilities) && class_exists('\WP_AI_Client_Ability_Function_Resolver')) {
                foreach ($abilities as $ability) {
                    $function_name = \WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name($ability->get_name());
                    $tools_list .= "- " . $function_name . ": " . $ability->get_description() . "\n";
                }
            } else {
                $tools_list .= "None\n";
            }

            $env_info = "\n\n" . $env_info;
            if (!empty($permanent_info)) {
                $env_info .= "\n\nPERMANENT CONTEXT:\n" . $permanent_info;
            }
            $env_info .= "\n\n" . $tools_list;

            $system_instruction = "You are an advanced, agentic AI Assistant specialized in WordPress, integrated natively into WizardBlocks. "
                . "You are empowered to act autonomously directly within the WordPress instance using your tools when needed. "
                . "You have full access to native WordPress Core functions and main plugin APIs (such as WooCommerce). When performing actions, querying data, or manipulating the environment, you are strongly encouraged to use your `execute-php` tool to leverage native WordPress or WooCommerce PHP functions rather than relying solely on raw SQL or basic file modifications. You are an expert in the WordPress ecosystem, its hooks, filters, and best practices."
                . "\n\nCRITICAL RULE FOR USING ABILITIES/TOOLS:\n"
                . "You MUST ONLY call ONE tool per response! DO NOT execute multiple tools in parallel in a single response. "
                . "When given a task, you must decompose it into multiple smaller actions (divide et impera). "
                . "You MUST call the first tool, wait for its result, and then call the next tool in subsequent turns until all actions are complete. "
                . "Violating this rule will cause a fatal API error ('The API only allows a single function response').\n"
                //. "IMPORTANT: DO NOT hallucinate tools. If the user asks a general question, for a joke, or says hello, DO NOT attempt to use a tool (e.g., never call a non-existent 'wpab__joke' tool). Just answer natively with plain text.\n"
                . "When answering natively in plain text, use standard conversational human language. DO NOT output JSON dictionaries or structured data unless explicitly requested by the user.\n"
                //. "CRITICAL: NEVER use placeholders like `<post-id>` or `[insert data]` in tool arguments. If you don't know a required parameter, you MUST first use another tool (like execute-php or db-query) to find the correct data, or ask the user to provide it.\n"
                //. "ERROR RECOVERY: If a tool call fails or returns an error, DO NOT give up or output an empty error JSON template (e.g. {\"name\": null}). Instead, try to answer the user's original request natively in plain text, or apologize and explain to the user what went wrong in standard conversational language.\n"
                . "When you have finished executing all necessary actions, your final response (without tool calls) must be a comprehensive confirmation report that lists each action you took and describes what was done.\n"
                . "IMPORTANT: If the user asked you to modify, append, or generate content for their editor, you MUST include the required ```gutenberg-insert or ```gutenberg-replace code blocks inside your FINAL confirmation response. The editor is ONLY updated if you explicitly output these code blocks in your final turn.\n"
                //. "IMPORTANT RULES FOR POSTS: When you search, list, or manipulate WordPress posts/pages (using any tool including db-query, execute-php, etc.), you MUST ALWAYS ignore and filter out post revisions (post_type = 'revision') unless the user explicitly asks for them.\n"
                . "CRITICAL: You MUST NEVER edit WordPress core files. Additionally, NEVER modify any files belonging to the Wizard Blocks (free) or Wizard Blocks PRO plugins. Confine your file modifications to other plugins or themes only.\n"
                . $env_info;

                //$system_instruction = '';

            $functions = [];
            $resolver = null;
            
            if (!empty($abilities) && class_exists('\WP_AI_Client_Ability_Function_Resolver')) {
                $resolver = new \WP_AI_Client_Ability_Function_Resolver(...$abilities);
                $clean_schema = function(&$schema) use (&$clean_schema) {
                    if (!is_array($schema)) return;
                    $allowed_keys = ['type', 'description', 'properties', 'required', 'items', 'enum'];
                    foreach ($schema as $k => $v) {
                        if (!in_array($k, $allowed_keys, true)) {
                            unset($schema[$k]);
                        } elseif (is_array($v)) {
                            if ($k === 'type') {
                                // Google API doesn't support array types (like ['string', 'null']) in JSON schema
                                $schema[$k] = is_array($v) && !empty($v) ? $v[0] : 'string';
                            } elseif ($k === 'properties') {
                                if (empty($v)) {
                                    $schema[$k] = new \stdClass();
                                } else {
                                    foreach ($schema[$k] as $prop_name => &$prop_val) {
                                        $clean_schema($prop_val);
                                        if (is_array($prop_val) && empty($prop_val)) {
                                            $prop_val = new \stdClass();
                                        }
                                    }
                                }
                            } elseif ($k === 'items') {
                                if (empty($v)) {
                                    $schema[$k] = new \stdClass();
                                } else {
                                    if (is_array($v) && isset($v[0])) {
                                        $schema[$k] = $v[0];
                                    }
                                    $clean_schema($schema[$k]);
                                    if (is_array($schema[$k]) && empty($schema[$k])) {
                                        $schema[$k] = new \stdClass();
                                    }
                                }
                            }
                        }
                    }
                    if (isset($schema['type']) && is_string($schema['type'])) {
                        if (strtolower($schema['type']) === 'array' && !isset($schema['items'])) {
                            $schema['items'] = new \stdClass();
                        } elseif (strtolower($schema['type']) !== 'array' && isset($schema['items'])) {
                            unset($schema['items']);
                        }
                    }
                };

                foreach ($abilities as $ability) {
                    $function_name = \WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name($ability->get_name());
                    $input_schema = $ability->get_input_schema();
                    if (empty($input_schema)) {
                        $input_schema = ['type' => 'object', 'properties' => new \stdClass()];
                    } else {
                        $clean_schema($input_schema);
                        if (empty($input_schema['properties'])) {
                            $input_schema['properties'] = new \stdClass();
                        }
                        if (empty($input_schema['type'])) {
                            $input_schema['type'] = 'object';
                        }
                    }
                    $functions[] = new \WordPress\AiClient\Tools\DTO\FunctionDeclaration(
                        $function_name,
                        $ability->get_description(),
                        $input_schema
                    );
                }
            }

            $previous_results = [];
            
            if ($cancel_tools && !empty($messages)) {
                return new \WP_REST_Response([
                    'success' => true,
                    'action' => 'done',
                    'response' => __('Task execution aborted by user.', 'wizard-ai'),
                    'previous_results' => []
                ], 200);
            } elseif ($execute_tools && !empty($messages)) {
                $last_message = end($messages);
                $has_any_call = false;
                if ($last_message) {
                    foreach ($last_message->getParts() as $part) {
                        if ($part->getFunctionCall() !== null) {
                            $has_any_call = true;
                            break;
                        }
                    }
                }
                if ($last_message && $resolver && $has_any_call) {
                    if (!empty($modified_tools)) {
                        $new_last_parts = [];
                        foreach ($last_message->getParts() as $part) {
                            if ($part->getFunctionCall() !== null) {
                                $fc = $part->getFunctionCall();
                                $id = $fc->getId();
                                if (isset($modified_tools[$id])) {
                                    $fc = new \WordPress\AiClient\Tools\DTO\FunctionCall(
                                        $id,
                                        $fc->getName(),
                                        array_merge($fc->getArgs(), $modified_tools[$id])
                                    );
                                    $part = new \WordPress\AiClient\Messages\DTO\MessagePart($fc);
                                }
                            }
                            $new_last_parts[] = $part;
                        }
                        $last_message = new \WordPress\AiClient\Messages\DTO\ModelMessage($new_last_parts);
                        $messages[count($messages) - 1] = $last_message;
                    }
                    $needs_safe_mode = false;
                    foreach ($last_message->getParts() as $part) {
                        if ($part->getFunctionCall() !== null) {
                            $name = $part->getFunctionCall()->getName();
                            if (strpos($name, 'execute-php') !== false || strpos($name, 'execute_php') !== false || strpos($name, 'modify-file') !== false || strpos($name, 'modify_file') !== false || strpos($name, 'db-query') !== false || strpos($name, 'db_query') !== false) {
                                $needs_safe_mode = true;
                                break;
                            }
                        }
                    }
                    if ($needs_safe_mode) {
                        $this->enable_safe_mode();
                    }

                    $upload_dir = wp_upload_dir();
                    $backup_dir = $upload_dir['basedir'] . '/wbai/backup';
                    if (!is_dir($backup_dir)) wp_mkdir_p($backup_dir);
                    
                    $backup_data = [
                        'action' => 'global-rollback',
                        'options' => [],
                        'posts' => [],
                        'files' => [],
                        'db_changes' => []
                    ];

                    foreach ($last_message->getParts() as $part) {
                        if ($part->getFunctionCall() !== null) {
                            $fc = $part->getFunctionCall();
                            $name = $fc->getName();
                            $args = $fc->getArgs();
                            
                            if (strpos($name, 'modify-file') !== false || strpos($name, 'modify_file') !== false) {
                                if (isset($args['path'])) {
                                    $path = wp_normalize_path($args['path']);
                                    if (file_exists($path)) {
                                        $rel_path = str_replace(wp_normalize_path(WP_CONTENT_DIR), '', $path);
                                        $safe_name = ltrim(str_replace(['/', '\\'], '-', $rel_path), '-');
                                        $physical_backup = $backup_dir . '/' . $safe_name . '_' . time() . '.backup';
                                        copy($path, $physical_backup);
                                        $backup_data['files'][] = [
                                            'path' => $path,
                                            'physical_backup' => basename($physical_backup),
                                            'is_new' => false
                                        ];
                                    } else {
                                        $backup_data['files'][] = [
                                            'path' => $path,
                                            'is_new' => true
                                        ];
                                    }
                                }
                            }
                            
                            if (strpos($name, 'execute-php') !== false || strpos($name, 'execute_php') !== false) {
                                if (isset($args['code'])) {
                                    $code = $args['code'];
                                    if (preg_match_all('/\bfile_put_contents\s*\(\s*[\'"]([^\'"]+)[\'"]/i', $code, $file_matches)) {
                                        foreach ($file_matches[1] as $file_path) {
                                            $file_path = wp_normalize_path($file_path);
                                            if (file_exists($file_path)) {
                                                $rel_path = str_replace(wp_normalize_path(WP_CONTENT_DIR), '', $file_path);
                                                $safe_name = ltrim(str_replace(['/', '\\'], '-', $rel_path), '-');
                                                $physical_backup = $backup_dir . '/' . $safe_name . '_' . time() . '.backup';
                                                copy($file_path, $physical_backup);
                                                $backup_data['files'][] = [
                                                    'path' => $file_path,
                                                    'physical_backup' => basename($physical_backup),
                                                    'is_new' => false
                                                ];
                                            } else {
                                                $backup_data['files'][] = [
                                                    'path' => $file_path,
                                                    'is_new' => true
                                                ];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $opt_logger = function($option, $old_value, $value) use (&$backup_data) {
                        if (!isset($backup_data['options'][$option])) {
                            $backup_data['options'][$option] = $old_value;
                        }
                    };
                    add_action('updated_option', $opt_logger, 10, 3);
                    
                    $opt_deleted_logger = function($option) use (&$backup_data) {
                        if (!isset($backup_data['options'][$option])) {
                            $backup_data['options'][$option] = get_option($option);
                        }
                    };
                    add_action('pre_delete_option', $opt_deleted_logger, 10, 1);
                    
                    $opt_added_logger = function($option, $value) use (&$backup_data) {
                        if (!isset($backup_data['options'][$option])) {
                            $backup_data['options'][$option] = false;
                        }
                    };
                    add_action('added_option', $opt_added_logger, 10, 2);
                    
                    $pre_post_update = function($post_ID) {
                        wp_save_post_revision($post_ID);
                    };
                    add_action('pre_post_update', $pre_post_update, 10, 1);

                    $post_logger = function($post_ID, $post_after, $post_before) use (&$backup_data) {
                        if (!isset($backup_data['posts'][$post_ID])) {
                            $backup_data['posts'][$post_ID] = $post_before;
                        }
                    };
                    add_action('post_updated', $post_logger, 10, 3);
                    
                    $post_deleted_logger = function($post_ID, $post) use (&$backup_data) {
                        if (!isset($backup_data['posts'][$post_ID])) {
                            $backup_data['posts'][$post_ID] = $post;
                        }
                    };
                    add_action('before_delete_post', $post_deleted_logger, 10, 2);

                    $query_logger = function($query) use (&$backup_data) {
                        static $is_logging = false;
                        if ($is_logging) return $query;
                        $is_logging = true;
                        
                        global $wpdb;
                        $query_upper = strtoupper(trim($query));
                        if (strpos($query_upper, 'UPDATE ') === 0 || strpos($query_upper, 'DELETE ') === 0) {
                            $table = '';
                            $where = '';
                            $type = '';
                            if (preg_match('/^\s*UPDATE\s+([`\'"]?\w+[`\'"]?)\s+SET.*?\s+WHERE\s+(.*)/is', $query, $matches)) {
                                $table = str_replace(['`', "'", '"'], '', $matches[1]);
                                $where = rtrim(trim($matches[2]), ';');
                                $type = 'UPDATE';
                            } elseif (preg_match('/^\s*DELETE\s+FROM\s+([`\'"]?\w+[`\'"]?)\s+WHERE\s+(.*)/is', $query, $matches)) {
                                $table = str_replace(['`', "'", '"'], '', $matches[1]);
                                $where = rtrim(trim($matches[2]), ';');
                                $type = 'DELETE';
                            }
                            if ($table && $where) {
                                if (strpos($table, $wpdb->prefix) !== 0 && strpos($table, 'wp_') === 0) {
                                    $table = $wpdb->prefix . substr($table, 3);
                                }
                                $select_query = "SELECT * FROM `{$table}` WHERE {$where}";
                                $rows = $wpdb->get_results($select_query, ARRAY_A);
                                if (!empty($rows)) {
                                    $backup_data['db_changes'][] = [
                                        'type' => $type,
                                        'table' => $table,
                                        'rows' => $rows
                                    ];
                                    if ($table === $wpdb->posts && $type === 'UPDATE') {
                                        foreach ($rows as $row) {
                                            $pk = isset($row['ID']) ? 'ID' : array_key_first($row);
                                            if (isset($row[$pk])) {
                                                wp_save_post_revision($row[$pk]);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        $is_logging = false;
                        return $query;
                    };
                    add_filter('query', $query_logger, 1);

                    global $wbai_is_executing;
                    $wbai_is_executing = true;

                    ob_start();
                    $response = $resolver->execute_abilities($last_message);
                    $stray_output = ob_get_clean();
                    
                    $wbai_is_executing = false;

                    remove_action('updated_option', $opt_logger, 10);
                    remove_action('pre_delete_option', $opt_deleted_logger, 10);
                    remove_action('added_option', $opt_added_logger, 10);
                    remove_action('pre_post_update', $pre_post_update, 10);
                    remove_action('post_updated', $post_logger, 10);
                    remove_action('before_delete_post', $post_deleted_logger, 10);
                    remove_filter('query', $query_logger, 1);

                    $backup_id = null;
                    if (!empty($backup_data['options']) || !empty($backup_data['posts']) || !empty($backup_data['db_changes']) || !empty($backup_data['files'])) {
                        $filename = 'global_' . time() . '_' . uniqid() . '.json';
                        file_put_contents($backup_dir . '/' . $filename, json_encode($backup_data));
                        $backup_id = $filename;
                    }

                    $new_parts = [];
                    foreach ($response->getParts() as $part) {
                        if ($part->getFunctionResponse() !== null) {
                            $fr = $part->getFunctionResponse();
                            $name = $fr->getName();
                            $fr_response = $fr->getResponse();
                            
                            if ($backup_id && !isset($fr_response['backup_id'])) {
                                if (!is_array($fr_response)) $fr_response = ['result' => $fr_response];
                                $fr_response['backup_id'] = $backup_id;
                                $fr_response['_rollback_notice'] = 'Backup created for rollback.';
                                
                                $fr = new \WordPress\AiClient\Tools\DTO\FunctionResponse(
                                    $fr->getId(),
                                    $name,
                                    $fr_response
                                );
                                $part = new \WordPress\AiClient\Messages\DTO\MessagePart($fr);
                            }

                            if (isset($fr_response['code']) && $fr_response['code'] === 'ability_invalid_permissions') {
                                if (in_array($name, ['wpab__ai__get-post-details', 'wpab__ai__get-post-terms'])) {
                                    $post_id = null;
                                    foreach ($last_message->getParts() as $call_part) {
                                        if ($call_part->getType()->isFunctionCall()) {
                                            $fc = $call_part->getFunctionCall();
                                            if ($fc->getId() === $fr->getId()) {
                                                $args = $fc->getArgs();
                                                $post_id = isset($args['post_id']) ? absint($args['post_id']) : null;
                                                break;
                                            }
                                        }
                                    }
                                    
                                    if ($post_id !== null) {
                                        if (!get_post($post_id)) {
                                            $fr_response['error'] = 'Post not found.';
                                            $fr_response['code'] = 'post_not_found';
                                            $fr = new \WordPress\AiClient\Tools\DTO\FunctionResponse(
                                                $fr->getId(),
                                                $name,
                                                $fr_response
                                            );
                                            $part = new \WordPress\AiClient\Messages\DTO\MessagePart($fr);
                                        }
                                    }
                                }
                            }
                            
                            $previous_results[] = [
                                'name' => $fr->getName(),
                                'response' => $fr->getResponse()
                            ];
                        }
                        $new_parts[] = $part;
                    }
                    
                    if (empty($new_parts)) {
                        $new_parts[] = new \WordPress\AiClient\Messages\DTO\MessagePart("System: Tool execution returned no output.");
                    }
                    $messages[] = new \WordPress\AiClient\Messages\DTO\UserMessage($new_parts);
                }
            }

            $ai_text = '';
            $retry_without_tools = false;
            
            $models_to_try = [];
            if (empty($requested_model) && $fallback_models) {
                $models_response = $this->get_ai_models($request);
                $models_data = $models_response->get_data();
                if (!empty($models_data['models'])) {
                    $models_to_try = array_keys($models_data['models']);
                }
            }
            if (empty($models_to_try)) {
                $models_to_try = [$requested_model];
            }
            
            $model_index = 0;
            $current_model_to_try = $models_to_try[$model_index];
            
            for ($i = 0; $i < 3; $i++) {
                if ($retry_without_tools) {
                    $functions = [];
                }
                
                $ai_query = \WordPress\AiClient\AiClient::prompt( $messages );
                if (!empty($current_model_to_try)) {
                    if (strpos($current_model_to_try, '|') !== false) {
                        list($provider_id, $model_id) = explode('|', $current_model_to_try, 2);
                        $ai_query->usingModelPreference([$provider_id, $model_id]);
                    } else {
                        $ai_query->usingModelPreference($current_model_to_try);
                    }
                }
                $ai_query->usingSystemInstruction($system_instruction);
                
                if (!empty($functions)) {
                    $ai_query->usingFunctionDeclarations(...$functions);
                }
                
                try {
                    $result = $ai_query->generateResult();
                    $response_message = $result->toMessage();
                    
                    // Fix Gemini dropped namespaces
                    if ($resolver && $resolver->has_ability_calls($response_message)) {
                        $rewritten_parts = [];
                        $modified = false;
                        foreach ($response_message->getParts() as $part) {
                            if ($part->getFunctionCall() !== null) {
                                $fc = $part->getFunctionCall();
                                $name = $fc->getName();
                                $original_name = $name;
                                
                                $missing_namespace_map = [
                                    'wpab__execute-php' => 'wpab__ai__execute-php',
                                    'wpab__execute_php' => 'wpab__ai__execute_php',
                                    'wpab__db-query' => 'wpab__ai__db-query',
                                    'wpab__db_query' => 'wpab__ai__db_query',
                                    'wpab__modify-file' => 'wpab__ai__modify-file',
                                    'wpab__modify_file' => 'wpab__ai__modify_file',
                                    'wpab__read-file' => 'wpab__ai__read-file',
                                    'wpab__read_file' => 'wpab__ai__read_file',
                                    'wpab__list-directory' => 'wpab__ai__list-directory',
                                    'wpab__list_directory' => 'wpab__ai__list_directory',
                                    'wpab__generate-image' => 'wpab__ai__generate-image',
                                    'wpab__generate_image' => 'wpab__ai__generate_image'
                                ];
                                
                                if (isset($missing_namespace_map[$name])) {
                                    $name = $missing_namespace_map[$name];
                                }
                                
                                if ($name !== $original_name) {
                                    $modified = true;
                                    $fc = new \WordPress\AiClient\Tools\DTO\FunctionCall($fc->getId(), $name, $fc->getArgs());
                                    $part = new \WordPress\AiClient\Messages\DTO\MessagePart($fc);
                                }
                            }
                            $rewritten_parts[] = $part;
                        }
                        
                        if ($modified) {
                            $response_message = new \WordPress\AiClient\Messages\DTO\ModelMessage($rewritten_parts);
                        }
                    }

                    $messages[] = $response_message;
                    
                    set_transient($conversation_id, serialize($messages), 3600);
                    
                    if ($resolver && $resolver->has_ability_calls($response_message)) {
                        $tool_info = [];
                        foreach ($response_message->getParts() as $part) {
                            if ($part->getFunctionCall() !== null) {
                                $tool_info[] = [
                                    'id' => $part->getFunctionCall()->getId(),
                                    'name' => $part->getFunctionCall()->getName(),
                                    'args' => $part->getFunctionCall()->getArgs()
                                ];
                            }
                        }
                        remove_filter('http_request_timeout', $timeout_filter, 999);
                        return new \WP_REST_Response([
                            'success' => true,
                            'action' => 'tool_calls',
                            'conversation_id' => $conversation_id,
                            'tools' => $tool_info,
                            'previous_results' => $previous_results,
                            'token_usage' => $result->getTokenUsage()->toArray()
                        ], 200);
                    }
                    
                    $ai_text = trim($result->toText());
                    
                    // Fallback for models (like some local ones) that output raw JSON instead of native tool calls
                    $clean_json_text = preg_replace('/^```json\s*|```\s*$/i', '', $ai_text);
                    $clean_json_text = trim($clean_json_text);
                    if (strpos($clean_json_text, '{') === 0) {
                        $parsed_json = json_decode($clean_json_text, true);
                        if (json_last_error() !== JSON_ERROR_NONE && strpos($clean_json_text, '"name"') !== false) {
                            $parsed_json = [
                                'name' => 'wpab__syntax_error',
                                'arguments' => ['error' => 'Invalid JSON structure or unescaped placeholders in tool arguments. Ensure you use strict JSON.']
                            ];
                        }
                        
                        if (is_array($parsed_json) && array_key_exists('name', $parsed_json)) {
                            if (empty($parsed_json['name'])) {
                                $parsed_json = [
                                    'name' => 'wpab__syntax_error',
                                    'arguments' => ['error' => 'Tool name cannot be null or empty. Please provide a valid tool name or answer natively.']
                                ];
                            }
                            if (!array_key_exists('arguments', $parsed_json)) {
                                $parsed_json['arguments'] = new \stdClass();
                            }
                            
                            $tool_info = [
                                [
                                    'id' => 'call_' . substr(md5(uniqid()), 0, 8),
                                    'name' => $parsed_json['name'],
                                    'args' => is_string($parsed_json['arguments']) ? json_decode($parsed_json['arguments'], true) : $parsed_json['arguments']
                                ]
                            ];
                            
                            $fc = new \WordPress\AiClient\Tools\DTO\FunctionCall($tool_info[0]['id'], $tool_info[0]['name'], empty($tool_info[0]['args']) ? new \stdClass() : $tool_info[0]['args']);
                            $part = new \WordPress\AiClient\Messages\DTO\MessagePart($fc);
                            $messages[count($messages) - 1] = new \WordPress\AiClient\Messages\DTO\ModelMessage([$part]);
                            set_transient($conversation_id, serialize($messages), 3600);
                            
                            remove_filter('http_request_timeout', $timeout_filter, 999);
                            return new \WP_REST_Response([
                                'success' => true,
                                'action' => 'tool_calls',
                                'conversation_id' => $conversation_id,
                                'tools' => $tool_info,
                                'previous_results' => $previous_results,
                                'token_usage' => $result->getTokenUsage()->toArray()
                            ], 200);
                        }
                        
                        if (is_array($parsed_json)) {
                            $extracted_text = "";
                            if (isset($parsed_json['response'])) {
                                $extracted_text = is_string($parsed_json['response']) ? $parsed_json['response'] : json_encode($parsed_json['response']);
                            } elseif (isset($parsed_json['joke'])) {
                                $extracted_text = is_string($parsed_json['joke']) ? $parsed_json['joke'] : json_encode($parsed_json['joke']);
                            }
                            
                            if (empty(trim($extracted_text))) {
                                $ai_text = "I'm sorry, I couldn't figure out how to process that request.";
                            } else {
                                $ai_text = $extracted_text;
                            }
                        }
                    }
                    
                    $display_text = $ai_text;
                    if (class_exists('\League\CommonMark\CommonMarkConverter')) {
                        if (class_exists('\League\CommonMark\GithubFlavoredMarkdownConverter')) {
                            $converter = new \League\CommonMark\GithubFlavoredMarkdownConverter(['html_input' => 'escape', 'allow_unsafe_links' => false]);
                        } else {
                            $converter = new \League\CommonMark\CommonMarkConverter(['html_input' => 'escape', 'allow_unsafe_links' => false]);
                        }
                        $display_text = $converter->convert($ai_text)->getContent();
                    }

                    remove_filter('http_request_timeout', $timeout_filter, 999);
                    return new \WP_REST_Response([
                        'success' => true,
                        'action' => 'done',
                        'conversation_id' => $conversation_id,
                        'response' => $display_text,
                        'previous_results' => $previous_results,
                        'token_usage' => $result->getTokenUsage()->toArray()
                    ], 200);
                    
                } catch (\Throwable $e) {
                    $error_msg = str_replace('`', '', strtolower($e->getMessage()));
                    $error_code = $e->getCode();

                    if (empty($requested_model) && $fallback_models) {
                        $is_fallback_error = ($error_code >= 400 && $error_code < 500) || preg_match('/\b4[0-9]{2}\b/', $error_msg);
                        if ($is_fallback_error && isset($models_to_try[$model_index + 1])) {
                            $model_index++;
                            $current_model_to_try = $models_to_try[$model_index];
                            $i--; // Don't count this as a schema retry failure
                            continue;
                        }
                    }

                    if (!empty($functions) && (strpos($error_msg, 'tool calling is not supported') !== false || strpos($error_msg, 'tools are not supported') !== false || strpos($error_msg, 'support tool use') !== false || strpos($error_msg, 'failed to call a function') !== false || strpos($error_msg, 'does not support tools') !== false)) {
                        $retry_without_tools = true;
                        continue;
                    }
                    if (strpos($error_msg, 'does not support chat completions') !== false) {
                        remove_filter('http_request_timeout', $timeout_filter, 999);
                        return new \WP_REST_Response(['success' => false, 'message' => __('The selected model does not support chat completions. Please select a different model from the dropdown.', 'wizard-ai')], 400);
                    }
                    if (strpos($error_msg, 'tool call validation failed') !== false || strpos($error_msg, 'did not match schema') !== false) {
                        if ($i < 2) {
                            $messages[] = new \WordPress\AiClient\Messages\DTO\UserMessage([
                                new \WordPress\AiClient\Messages\DTO\MessagePart("System Error: Your previous function call failed validation: " . $e->getMessage() . "\nPlease strictly adhere to the declared schema and enum values, and try again.")
                            ]);
                            continue;
                        }
                    }
                    if (strpos($error_msg, 'reduce the length') !== false || strpos($error_msg, 'context length') !== false || strpos($error_msg, 'maximum context') !== false) {
                        remove_filter('http_request_timeout', $timeout_filter, 999);
                        return new \WP_REST_Response(['success' => false, 'message' => __('The context is too large for the selected model. This usually happens when tool outputs (like reading large files) exceed the model\'s memory limit. Please try using a model with a larger context window.', 'wizard-ai')], 400);
                    }
                    remove_filter('http_request_timeout', $timeout_filter, 999);
                    return new \WP_REST_Response(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 500);
                }
            }
        }
        
        return new \WP_REST_Response(['success' => false, 'message' => __('AI Client not available', 'wizard-ai')], 400);
    }
}
