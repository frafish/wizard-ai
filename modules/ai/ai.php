<?php
namespace WizardAi\Modules\Ai;

class Ai {
    
    use Traits\Playground;
    use Traits\Block;
    use Traits\Agent;
    use Traits\Chatbot;
    use Traits\Mcp;
    use Traits\Markdown;
    use Traits\Seo;
    use Traits\TokenUsage;

    public $cm_settings = null;
    public $cm_sql_settings = null;

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_ai_routes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_playground_scripts']);

        
        add_action('admin_menu', [$this, 'add_ai_settings_menu']);
        
        new Abilities();
        
        add_action('init', [$this, 'register_subplugins_providers'], 5);

        $ai_enabled = class_exists('\WordPress\AiClient\AiClient');
        if ($ai_enabled) {
            if (class_exists('\WizardBlocks\Modules\Block\Block')) {
                $wb = \WizardBlocks\Modules\Block\Block::instance();
                if ($wb->is_block_edit()) {
                    add_action('post_submitbox_start', [$this, 'add_ai_button_to_submitbox']);
                }
            }
            $this->register_agent_hooks();
            $this->register_chatbot_hooks();
            $this->register_mcp_routes();
            $this->register_markdown_hooks();
            $this->register_seo_hooks();
            $this->register_token_usage_hooks();
        }
    }
    
    public static function _description() {
        return __('Get real-time AI assistance directly during block editing to generate code snippets, troubleshoot logic, or brainstorm block structures instantly', 'wizard-ai');
    }

    public function add_ai_settings_menu() {
        add_menu_page(
            __('Wizard AI', 'wizard-ai'),
            __('Wizard AI', 'wizard-ai'),
            'manage_options',
            'wizard-ai',
            [$this, 'wb_ai_page_html'],
            'dashicons-superhero',
            30
        );
        // Rename first submenu item
        add_submenu_page(
            'wizard-ai',
            __('Playground', 'wizard-ai'),
            __('Playground', 'wizard-ai'),
            'manage_options',
            'wizard-ai',
            [$this, 'wb_ai_page_html']
        );
        add_submenu_page(
            'wizard-ai',
            __('Agent Settings', 'wizard-ai'),
            __('Editor Agent', 'wizard-ai'),
            'manage_options',
            'wizard-ai-agent',
            [$this, 'wb_ai_agent_page_html']
        );
        add_submenu_page(
            'wizard-ai',
            __('Frontend Chatbot', 'wizard-ai'),
            __('Frontend Chatbot', 'wizard-ai'),
            'manage_options',
            'wizard-ai-chatbot',
            [$this, 'wb_ai_chatbot_page_html']
        );
        add_submenu_page(
            'wizard-ai',
            __('MCP & GPT Integrations', 'wizard-ai'),
            __('MCP & GPT', 'wizard-ai'),
            'manage_options',
            'wizard-ai-mcp',
            [$this, 'wb_ai_mcp_page_html']
        );
        add_submenu_page(
            'wizard-ai',
            __('Markdown Settings', 'wizard-ai'),
            __('Markdown', 'wizard-ai'),
            'manage_options',
            'wizard-ai-markdown',
            [$this, 'wb_ai_markdown_page_html']
        );
        add_submenu_page(
            'wizard-ai',
            __('Media SEO', 'wizard-ai'),
            __('Media SEO', 'wizard-ai'),
            'manage_options',
            'wizard-ai-seo',
            [$this, 'wb_ai_seo_page_html']
        );
        add_submenu_page(
            null, // Hides the page from the WordPress sidebar menu
            __('Chatbot Logs', 'wizard-ai'),
            __('Chatbot Logs', 'wizard-ai'),
            'manage_options',
            'wizard-ai-chatbot-logs',
            [$this, 'wb_ai_chatbot_logs_page_html']
        );
    }



    public function enqueue_playground_scripts($hook) {
        if (strpos($hook, 'wizard-ai') !== false || (isset($_GET['page']) && $_GET['page'] === 'wizard-ai')) {
            $user = wp_get_current_user();
            $prev = $user->syntax_highlighting;
            $user->syntax_highlighting = 'true';
            
            $this->cm_settings = wp_enqueue_code_editor(array('type' => 'application/x-httpd-php'));
            $this->cm_sql_settings = wp_enqueue_code_editor(array('type' => 'text/x-sql'));
            
            $user->syntax_highlighting = $prev;

            // Force manual enqueue to guarantee loading
            wp_enqueue_script('wp-codemirror');
            wp_enqueue_style('wp-codemirror');
            wp_enqueue_script('code-editor');
            wp_enqueue_style('code-editor');
            
            wp_enqueue_script('jquery-ui-resizable');
            wp_enqueue_style('wbai-playground-style', WIZARD_AI_URL . 'modules/ai/assets/css/playground.css', array(), time());
        }
    }

    public function register_subplugins_providers() {
        if (!class_exists('\WordPress\AiClient\AiClient')) {
            return;
        }

        if (file_exists(__DIR__ . '/providers/groq/src/autoload.php')) {
            require_once __DIR__ . '/providers/groq/src/autoload.php';
        }
        if (file_exists(__DIR__ . '/providers/openrouter/src/autoload.php')) {
            require_once __DIR__ . '/providers/openrouter/src/autoload.php';
        }
        if (file_exists(__DIR__ . '/providers/huggingface/src/autoload.php')) {
            require_once __DIR__ . '/providers/huggingface/src/autoload.php';
        }
        if (file_exists(__DIR__ . '/providers/github/src/autoload.php')) {
            require_once __DIR__ . '/providers/github/src/autoload.php';
        }
        if (file_exists(__DIR__ . '/providers/mistral/src/autoload.php')) {
            require_once __DIR__ . '/providers/mistral/src/autoload.php';
        }
        if (file_exists(__DIR__ . '/providers/cohere/src/autoload.php')) {
            require_once __DIR__ . '/providers/cohere/src/autoload.php';
        }

        $registry = \WordPress\AiClient\AiClient::defaultRegistry();

        if (class_exists('\WordPress\GroqAiProvider\Provider\GroqProvider') && !$registry->hasProvider('\WordPress\GroqAiProvider\Provider\GroqProvider')) {
            $registry->registerProvider('\WordPress\GroqAiProvider\Provider\GroqProvider');
        }
        if (class_exists('\WordPress\HuggingFaceAiProvider\Provider\HuggingFaceProvider') && !$registry->hasProvider('\WordPress\HuggingFaceAiProvider\Provider\HuggingFaceProvider')) {
            $registry->registerProvider('\WordPress\HuggingFaceAiProvider\Provider\HuggingFaceProvider');
        }
        if (class_exists('\WordPress\OpenRouterAiProvider\Provider\OpenRouterProvider') && !$registry->hasProvider('\WordPress\OpenRouterAiProvider\Provider\OpenRouterProvider')) {
            $registry->registerProvider('\WordPress\OpenRouterAiProvider\Provider\OpenRouterProvider');
        }
        if (class_exists('\WordPress\GithubAiProvider\Provider\GithubProvider') && !$registry->hasProvider('\WordPress\GithubAiProvider\Provider\GithubProvider')) {
            $registry->registerProvider('\WordPress\GithubAiProvider\Provider\GithubProvider');
        }
        if (class_exists('\WordPress\MistralAiProvider\Provider\MistralProvider') && !$registry->hasProvider('\WordPress\MistralAiProvider\Provider\MistralProvider')) {
            $registry->registerProvider('\WordPress\MistralAiProvider\Provider\MistralProvider');
        }
        if (class_exists('\WordPress\CohereAiProvider\Provider\CohereProvider') && !$registry->hasProvider('\WordPress\CohereAiProvider\Provider\CohereProvider')) {
            $registry->registerProvider('\WordPress\CohereAiProvider\Provider\CohereProvider');
        }
    }


    public function register_ai_routes() {
        register_rest_route('wizard-blocks/v1', '/process-ai', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_ai_request'],
            'permission_callback' => function () { return current_user_can('edit_posts'); }
        ]);
        
        register_rest_route('wizard-blocks/v1', '/ai-chat', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_chat_request'],
            'permission_callback' => [$this, 'chat_permission_check']
        ]);
        register_rest_route('wizard-blocks/v1', '/ai-models', [
            'methods' => 'GET',
            'callback' => [$this, 'get_ai_models'],
            'permission_callback' => function () { return current_user_can('manage_options'); }
        ]);
        register_rest_route('wizard-blocks/v1', '/toggle-safe-mode', [
            'methods' => 'POST',
            'callback' => [$this, 'toggle_safe_mode'],
            'permission_callback' => function () { return current_user_can('manage_options'); }
        ]);
        register_rest_route('wizard-blocks/v1', '/rollback-ai-action', [
            'methods' => 'POST',
            'callback' => [$this, 'rollback_ai_action'],
            'permission_callback' => function () { return current_user_can('manage_options'); }
        ]);
        register_rest_route('wizard-blocks/v1', '/delete-ai-backups', [
            'methods' => 'POST',
            'callback' => [$this, 'delete_ai_backups'],
            'permission_callback' => function () { return current_user_can('manage_options'); }
        ]);
        register_rest_route('wizard-blocks/v1', '/download-ai-backup', [
            'methods' => 'GET',
            'callback' => [$this, 'download_ai_backup'],
            'permission_callback' => function () { return current_user_can('manage_options'); }
        ]);
    }

    public function chat_permission_check() {
        $user = wp_get_current_user();
        if (empty($user) || !$user->exists()) return false;
        if (current_user_can('manage_options')) return true;

        $selected_roles = get_option('wbai_agent_roles', ['administrator']);
        if (!empty($user->roles)) {
            foreach ($user->roles as $role) {
                if (in_array($role, $selected_roles)) {
                    return true;
                }
            }
        }
        return false;
    }

    public function enable_safe_mode() {
        $mu_dir = WPMU_PLUGIN_DIR;
        if (!is_dir($mu_dir)) {
            wp_mkdir_p($mu_dir);
        }
        $plugin_file = trailingslashit($mu_dir) . 'wizard-blocks-safe-mode.php';
        
        $theme_dir = get_theme_root() . '/wizard-blocks-safe-theme';
        if (!is_dir($theme_dir)) {
            wp_mkdir_p($theme_dir);
            file_put_contents($theme_dir . '/style.css', "/*\nTheme Name: Wizard Blocks Safe Theme\n*/");
            file_put_contents($theme_dir . '/index.php', "");
        }

        if (!file_exists($plugin_file) || strpos(file_get_contents($plugin_file), 'wizard-ai') === false) {
            $code = "<?php\n" .
                "/*\n" .
                "Plugin Name: Wizard Blocks Safe Mode\n" .
                "Description: Forces empty theme and disables other plugins on the Playground page.\n" .
                "*/\n" .
                "\$is_playground_page = isset(\$_GET['page']) && \$_GET['page'] === 'wbai';\n" .
                "\$is_ai_rest = strpos(\$_SERVER['REQUEST_URI'] ?? '', '/wizard-blocks/v1/ai') !== false;\n" .
                "\$is_toggle_rest = strpos(\$_SERVER['REQUEST_URI'] ?? '', '/wizard-blocks/v1/toggle-safe-mode') !== false;\n" .
                "\$enforce_ai = file_exists(ABSPATH . '.wb_ai_safe') || (isset(\$_GET['wbai_enforce_safe_mode']) && \$_GET['wbai_enforce_safe_mode'] === '1');\n" .
                "if (\$is_playground_page || ((\$is_ai_rest || \$is_toggle_rest) && \$enforce_ai)) {\n" .
                "    add_filter('option_active_plugins', function(\$plugins) {\n" .
                "        \$allowed = [];\n" .
                "        foreach (\$plugins as \$plugin) {\n" .
                "            if (strpos(\$plugin, 'wizard-blocks') !== false || strpos(\$plugin, 'wizard-ai') !== false || strpos(\$plugin, 'ai-provider') !== false) {\n" .
                "                \$allowed[] = \$plugin;\n" .
                "            }\n" .
                "        }\n" .
                "        return \$allowed;\n" .
                "    });\n" .
                "    add_filter('option_active_sitewide_plugins', function(\$plugins) {\n" .
                "        \$allowed = [];\n" .
                "        if (is_array(\$plugins)) {\n" .
                "            foreach (\$plugins as \$plugin => \$time) {\n" .
                "                if (strpos(\$plugin, 'wizard-blocks') !== false || strpos(\$plugin, 'wizard-ai') !== false || strpos(\$plugin, 'ai-provider') !== false) {\n" .
                "                    \$allowed[\$plugin] = \$time;\n" .
                "                }\n" .
                "            }\n" .
                "        }\n" .
                "        return \$allowed;\n" .
                "    });\n" .
                "    add_filter('stylesheet', function(\$theme) { return 'wizard-blocks-safe-theme'; });\n" .
                "    add_filter('template', function(\$theme) { return 'wizard-blocks-safe-theme'; });\n" .
                "}\n";
            file_put_contents($plugin_file, $code);
        }
    }

    public function disable_safe_mode() {
        $mu_dir = WPMU_PLUGIN_DIR;
        $plugin_file = trailingslashit($mu_dir) . 'wizard-blocks-safe-mode.php';
        $theme_dir = get_theme_root() . '/wizard-blocks-safe-theme';

        if (file_exists($plugin_file)) {
            unlink($plugin_file);
        }
        if (is_dir($theme_dir)) {
            if (file_exists($theme_dir . '/style.css')) unlink($theme_dir . '/style.css');
            if (file_exists($theme_dir . '/index.php')) unlink($theme_dir . '/index.php');
            rmdir($theme_dir);
        }
    }

    public function rollback_ai_action(\WP_REST_Request $request) {
        $backup_id = sanitize_text_field($request->get_param('backup_id'));
        if (!$backup_id) return new \WP_Error('missing_param', 'backup_id is required');
        
        $upload_dir = wp_upload_dir();
        $backup_file = $upload_dir['basedir'] . '/wbai/backup/' . basename($backup_id);
        
        if (!file_exists($backup_file)) return new \WP_Error('not_found', 'Backup file not found');
        
        $data = json_decode(file_get_contents($backup_file), true);
        if (!$data) return new \WP_Error('invalid_backup', 'Invalid backup format');
        
        if ($data['action'] === 'modify-file') {
            $path = $data['original_path'];
            if ($data['is_new']) {
                if (file_exists($path)) unlink($path);
            } else {
                if (isset($data['physical_backup']) && $data['physical_backup']) {
                    $physical_file = $upload_dir['basedir'] . '/wbai/backup/' . basename($data['physical_backup']);
                    if (file_exists($physical_file)) {
                        copy($physical_file, $path);
                    }
                } elseif (isset($data['content'])) {
                    file_put_contents($path, base64_decode($data['content']));
                }
            }
        } elseif ($data['action'] === 'db-query') {
            global $wpdb;
            $table = $data['table'];
            if ($data['type'] === 'UPDATE') {
                foreach ($data['rows'] as $row) {
                    $common_pks = ['ID', 'id', 'post_id', 'meta_id', 'umeta_id', 'term_id', 'option_id', 'comment_ID'];
                    $pk = array_key_first($row);
                    foreach ($common_pks as $p) {
                        if (isset($row[$p])) { $pk = $p; break; }
                    }
                    if ($pk) {
                        $wpdb->update($table, $row, [$pk => $row[$pk]]);
                    }
                }
            } elseif ($data['type'] === 'DELETE') {
                foreach ($data['rows'] as $row) {
                    $wpdb->insert($table, $row);
                }
            }
        } elseif ($data['action'] === 'update-options') {
            if (isset($data['options']) && is_array($data['options'])) {
                foreach ($data['options'] as $opt => $val) {
                    if ($val === false) {
                        delete_option($opt);
                    } else {
                        update_option($opt, $val);
                    }
                }
            }
        } elseif ($data['action'] === 'execute-php-rollback' || $data['action'] === 'global-rollback') {
            if (!empty($data['options'])) {
                foreach ($data['options'] as $opt => $val) {
                    if ($val === false) delete_option($opt);
                    else update_option($opt, $val);
                }
            }
            if (!empty($data['posts'])) {
                foreach ($data['posts'] as $post_id => $post_data) {
                    if (is_array($post_data)) {
                        wp_update_post($post_data);
                    } elseif (is_object($post_data)) {
                        wp_update_post(get_object_vars($post_data));
                    }
                }
            }
            if (!empty($data['files'])) {
                foreach ($data['files'] as $f) {
                    $path = $f['path'];
                    if (!empty($f['is_new'])) {
                        if (file_exists($path)) unlink($path);
                    } elseif (!empty($f['physical_backup'])) {
                        $physical_file = $upload_dir['basedir'] . '/wbai/backup/' . basename($f['physical_backup']);
                        if (file_exists($physical_file)) {
                            copy($physical_file, $path);
                        }
                    }
                }
            }
            if (!empty($data['db_changes'])) {
                global $wpdb;
                foreach ($data['db_changes'] as $change) {
                    $table = $change['table'];
                    $type = $change['type'];
                    if ($type === 'UPDATE') {
                        foreach ($change['rows'] as $row) {
                            $common_pks = ['ID', 'id', 'post_id', 'meta_id', 'umeta_id', 'term_id', 'option_id', 'comment_ID'];
                            $pk = array_key_first($row);
                            foreach ($common_pks as $p) {
                                if (isset($row[$p])) { $pk = $p; break; }
                            }
                            if ($pk) {
                                $wpdb->update($table, $row, [$pk => $row[$pk]]);
                            }
                        }
                    } elseif ($type === 'DELETE') {
                        foreach ($change['rows'] as $row) {
                            $wpdb->insert($table, $row);
                        }
                    }
                }
            }
        } elseif ($data['action'] === 'plugin-backup') {
            $plugin_dir = $data['plugin_dir'];
            $zip_path = $data['zip_path'];
            if (file_exists($zip_path) && class_exists('ZipArchive')) {
                if (is_dir($plugin_dir)) {
                    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
                    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
                    $fs = new \WP_Filesystem_Direct(null);
                    $fs->delete($plugin_dir, true);
                }
                wp_mkdir_p($plugin_dir);
                $zip = new \ZipArchive();
                if ($zip->open($zip_path) === true) {
                    $zip->extractTo($plugin_dir);
                    $zip->close();
                }
            }
        }
        
        unlink($backup_file);
        return new \WP_REST_Response(['success' => true, 'message' => 'Rollback completed successfully.'], 200);
    }

    public function delete_ai_backups(\WP_REST_Request $request) {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/wbai/backup';
        if (is_dir($backup_dir)) {
            $files = glob($backup_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) unlink($file);
            }
        }
        return new \WP_REST_Response(['success' => true, 'message' => 'Temporary backups cleared.'], 200);
    }

    public function toggle_safe_mode(\WP_REST_Request $request) {
        $flag_file = ABSPATH . '.wb_ai_safe';
        $force = $request->get_param('force');
        
        if ($force === 'enable') {
            file_put_contents($flag_file, '1');
            return new \WP_REST_Response(['success' => true, 'safe_mode' => true], 200);
        } elseif ($force === 'disable') {
            if (file_exists($flag_file)) @unlink($flag_file);
            return new \WP_REST_Response(['success' => true, 'safe_mode' => false], 200);
        }

        if (file_exists($flag_file)) {
            @unlink($flag_file);
            return new \WP_REST_Response(['success' => true, 'safe_mode' => false], 200);
        } else {
            file_put_contents($flag_file, '1');
            return new \WP_REST_Response(['success' => true, 'safe_mode' => true], 200);
        }
    }

    public function get_ai_models(\WP_REST_Request $request) {
        if (class_exists('\WordPress\AiClient\AiClient')) {
            // Providers are automatically loaded via register_subplugins_providers on init
            
            // Caching removed to ensure real-time model discovery for local and newly added providers

            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            
            // Invalidate AiClient internal caches if requested, or for local providers
            $forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
            
            foreach ($registry->getRegisteredProviderIds() as $providerId) {
                if ($forceRefresh || in_array($providerId, ['local', 'ollama'])) {
                    try {
                        $className = $registry->getProviderClassName($providerId);
                        $directory = $className::modelMetadataDirectory();
                        if ($directory instanceof \WordPress\AiClient\Common\Contracts\CachesDataInterface) {
                            $directory->invalidateCaches();
                        }
                    } catch (\Exception $e) {
                        // Ignore
                    }
                }
            }

            if ($request->get_param('vision') === '1') {
                $capabilities = [
                    \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration()
                ];
                $options = [
                    new \WordPress\AiClient\Providers\Models\DTO\RequiredOption(
                        \WordPress\AiClient\Providers\Models\Enums\OptionEnum::inputModalities(),
                        [
                            \WordPress\AiClient\Messages\Enums\ModalityEnum::text(),
                            \WordPress\AiClient\Messages\Enums\ModalityEnum::image()
                        ]
                    )
                ];
            } else {
                $capabilities = [
                    \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration(),
                    \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::chatHistory()
                ];
                $options = [];
            }
            
            $requirements = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
                $capabilities,
                $options
            );
            $providerModels = $registry->findModelsMetadataForSupport($requirements);
            
            $models = [];
            foreach ($providerModels as $providerMetadata) {
                $providerName = $providerMetadata->getProvider()->getName();
                if (!isset($models[$providerName])) {
                    $models[$providerName] = [];
                }
                foreach ($providerMetadata->getModels() as $modelMeta) {
                    $id = $modelMeta->getId();
                    $name = $modelMeta->getName() ?: $id;
                    $providerId = $providerMetadata->getProvider()->getId();
                    $models[$providerName][$providerId . '|' . $id] = '[' . $providerName . '] ' . $name;
                }
            }
            
            if (!empty($models)) {
                set_transient('wbai_client_models', $models, HOUR_IN_SECONDS);
            }
            return new \WP_REST_Response(['success' => true, 'models' => $models], 200);
            
        }
        
        return new \WP_REST_Response(['success' => true, 'models' => []], 200);
    }

    public function download_ai_backup($request) {
        $backup_id = $request->get_param('id');
        $type = $request->get_param('type'); // 'file' or 'sql'
        $index = (int)$request->get_param('index');

        if (!$backup_id || !$type) {
            return new \WP_Error('invalid_params', 'Missing required parameters.', ['status' => 400]);
        }

        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/wbai/backup';
        $json_file = $backup_dir . '/' . basename($backup_id);

        if (!file_exists($json_file)) {
            return new \WP_Error('not_found', 'Backup not found.', ['status' => 404]);
        }

        $data = json_decode(file_get_contents($json_file), true);
        if (!$data) {
            return new \WP_Error('invalid_backup', 'Invalid backup file.', ['status' => 500]);
        }

        if ($type === 'file') {
            if (!isset($data['files'][$index])) {
                return new \WP_Error('not_found', 'File backup not found.', ['status' => 404]);
            }
            $file_info = $data['files'][$index];
            if ($file_info['is_new']) {
                return new \WP_Error('not_found', 'This file was created by AI, no previous version exists.', ['status' => 404]);
            }
            $physical = $backup_dir . '/' . basename($file_info['physical_backup']);
            if (!file_exists($physical)) {
                return new \WP_Error('not_found', 'Physical backup file not found.', ['status' => 404]);
            }

            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_info['path']) . '"');
            header('Content-Length: ' . filesize($physical));
            readfile($physical);
            exit;

        } elseif ($type === 'sql') {
            if (!isset($data['db_changes'])) {
                return new \WP_Error('not_found', 'No DB changes in this backup.', ['status' => 404]);
            }
            
            $sql_dump = "-- AI Action Rollback SQL Dump\n";
            $sql_dump .= "-- Original Backup ID: " . $backup_id . "\n\n";

            foreach ($data['db_changes'] as $change) {
                if ($change['type'] === 'UPDATE' || $change['type'] === 'DELETE' || $change['type'] === 'INSERT') {
                    $table = $change['table'];
                    $sql_dump .= "-- Restore original rows for table: {$table}\n";
                    if (!empty($change['rows'])) {
                        foreach ($change['rows'] as $row) {
                            $cols = array_keys($row);
                            $vals = array_map(function($v) {
                                if ($v === null) return 'NULL';
                                return "'" . esc_sql($v) . "'";
                            }, array_values($row));
                            $sql_dump .= "REPLACE INTO `{$table}` (`" . implode("`, `", $cols) . "`) VALUES (" . implode(", ", $vals) . ");\n";
                        }
                    }
                    $sql_dump .= "\n";
                }
            }

            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="rollback_' . $backup_id . '.sql"');
            echo $sql_dump;
            exit;
        }

        return new \WP_Error('invalid_type', 'Invalid download type.', ['status' => 400]);
    }
}