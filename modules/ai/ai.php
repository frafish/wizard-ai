<?php
namespace WizardAi\Modules\Ai;

class Ai {
    


    use Traits\Skills;
    use Traits\AbilitiesUi;
    use Traits\ModelsUi;

    public $cm_settings = null;
    public $cm_sql_settings = null;
    use Traits\Rag;

    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        if (self::$instance !== null) return;
        self::$instance = $this;
        
        $this->register_rag_hooks();
        
        add_action('rest_api_init', [$this, 'register_ai_routes']);

        
        add_action('admin_menu', [$this, 'add_ai_settings_menu']);
        add_action('admin_menu', [$this, 'add_ai_skills_abilities_menu'], 99);
        
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
            $this->register_skills_hooks();
        }

        add_action('wbai_update_models_cron', [$this, 'run_update_models_cron']);
        
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function enqueue_admin_assets($hook) {
        if ($hook === 'wizard-ai_page_wizard-ai-models') {
            wp_enqueue_style('wbai-models-ui-css', WIZARD_AI_URL . 'modules/ai/assets/css/models-ui.css', [], filemtime(WIZARD_AI_PATH . 'modules/ai/assets/css/models-ui.css'));
            wp_enqueue_script('wbai-models-ui-js', WIZARD_AI_URL . 'modules/ai/assets/js/models-ui.js', [], filemtime(WIZARD_AI_PATH . 'modules/ai/assets/js/models-ui.js'), true);
        }
        
        if ($hook === 'wizard-ai_page_wizard-ai-skills') {
            wp_enqueue_script('wbai-skills-ui-js', WIZARD_AI_URL . 'modules/ai/assets/js/skills-ui.js', [], filemtime(WIZARD_AI_PATH . 'modules/ai/assets/js/skills-ui.js'), true);
            wp_localize_script('wbai-skills-ui-js', 'waiSkillsData', [
                'nonce' => wp_create_nonce('wp_rest'),
                'apiUrl' => rest_url('wizard-blocks/v1/skills'),
                'loading' => __('Loading...', 'wizard-ai'),
                'noSkills' => __('No skills found. Create one!', 'wizard-ai'),
                'saving' => __('Saving...', 'wizard-ai'),
                'saveSkill' => __('Save Skill', 'wizard-ai'),
                'saveSuccess' => __('Skill saved successfully!', 'wizard-ai'),
                'confirmDelete' => __('Are you sure you want to delete this skill?', 'wizard-ai')
            ]);
        }
        
        if ($hook === 'wizard-ai_page_wizard-ai-abilities') {
            wp_enqueue_style('wbai-abilities-ui-css', WIZARD_AI_URL . 'modules/ai/assets/css/abilities-ui.css', [], filemtime(WIZARD_AI_PATH . 'modules/ai/assets/css/abilities-ui.css'));
            wp_enqueue_script('wbai-abilities-ui-js', WIZARD_AI_URL . 'modules/ai/assets/js/abilities-ui.js', ['jquery'], filemtime(WIZARD_AI_PATH . 'modules/ai/assets/js/abilities-ui.js'), true);
            wp_localize_script('wbai-abilities-ui-js', 'waiAbilitiesData', [
                'nonce' => wp_create_nonce("ai_ability_explorer_invoke")
            ]);
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
            [ \WizardAi\Modules\Playground\Playground::instance(), 'wb_ai_page_html' ],
            'dashicons-superhero',
            30
        );
        add_submenu_page(
            'wizard-ai',
            __('Playground', 'wizard-ai'),
            __('Playground', 'wizard-ai'),
            'manage_options',
            'wizard-ai',
            [ \WizardAi\Modules\Playground\Playground::instance(), 'wb_ai_page_html' ]
        );
    }

    public function add_ai_skills_abilities_menu() {
        add_submenu_page(
            'wizard-ai',
            __('AI Abilities', 'wizard-ai'),
            __('AI Abilities', 'wizard-ai'),
            'manage_options',
            'wizard-ai-abilities',
            [$this, 'wb_ai_abilities_page_html']
        );
        add_submenu_page(
            'wizard-ai',
            __('AI Skills', 'wizard-ai'),
            __('AI Skills', 'wizard-ai'),
            'manage_options',
            'wizard-ai-skills',
            [$this, 'wb_ai_skills_page_html']
        );
        add_submenu_page(
            'wizard-ai',
            __('AI Models', 'wizard-ai'),
            __('AI Models', 'wizard-ai'),
            'manage_options',
            'wizard-ai-models',
            [$this, 'wb_ai_models_page_html']
        );
    }
    public function register_subplugins_providers() {
        if (!class_exists('\WordPress\AiClient\AiClient')) {
            return;
        }

        if (file_exists(WIZARD_AI_PATH . 'modules/providers/groq/src/autoload.php')) {
            require_once WIZARD_AI_PATH . 'modules/providers/groq/src/autoload.php';
        }
        if (file_exists(WIZARD_AI_PATH . 'modules/providers/openrouter/src/autoload.php')) {
            require_once WIZARD_AI_PATH . 'modules/providers/openrouter/src/autoload.php';
        }
        if (file_exists(WIZARD_AI_PATH . 'modules/providers/huggingface/src/autoload.php')) {
            require_once WIZARD_AI_PATH . 'modules/providers/huggingface/src/autoload.php';
        }
        if (file_exists(WIZARD_AI_PATH . 'modules/providers/github/src/autoload.php')) {
            require_once WIZARD_AI_PATH . 'modules/providers/github/src/autoload.php';
        }
        if (file_exists(WIZARD_AI_PATH . 'modules/providers/mistral/src/autoload.php')) {
            require_once WIZARD_AI_PATH . 'modules/providers/mistral/src/autoload.php';
        }
        if (file_exists(WIZARD_AI_PATH . 'modules/providers/cohere/src/autoload.php')) {
            require_once WIZARD_AI_PATH . 'modules/providers/cohere/src/autoload.php';
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
        register_rest_route('wizard-ai/v1', '/process-ai', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_ai_request'],
            'permission_callback' => function () { return current_user_can('edit_posts'); }
        ]);
        
        register_rest_route('wizard-ai/v1', '/ai-models', [
            'methods' => 'GET',
            'callback' => [$this, 'get_ai_models'],
            'permission_callback' => [$this, 'chat_permission_check']
        ]);
        register_rest_route('wizard-ai/v1', '/ai-models/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'save_ai_models_settings'],
            'permission_callback' => function () { return current_user_can('manage_options'); }
        ]);
        register_rest_route('wizard-ai/v1', '/rollback-ai-action', [
            'methods' => 'POST',
            'callback' => [$this, 'rollback_ai_action'],
            'permission_callback' => function () { return current_user_can('manage_options'); }
        ]);
        register_rest_route('wizard-ai/v1', '/delete-ai-backups', [
            'methods' => 'POST',
            'callback' => [$this, 'delete_ai_backups'],
            'permission_callback' => function () { return current_user_can('manage_options'); }
        ]);
        register_rest_route('wizard-ai/v1', '/download-ai-backup', [
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

    public function save_ai_models_settings(\WP_REST_Request $request) {
        $enabled_models = $request->get_param('enabled_models');
        if (is_array($enabled_models)) {
            update_option('wbai_enabled_models', $enabled_models);
        }
        
        $budget_cap = $request->get_param('budget_cap');
        if (isset($budget_cap)) {
            update_option('wbai_token_budget_cap', intval($budget_cap));
        }

        $cron_enabled = $request->get_param('cron_enabled');
        if ($cron_enabled) {
            if (!wp_next_scheduled('wbai_update_models_cron')) {
                wp_schedule_event(time(), 'daily', 'wbai_update_models_cron');
            }
        } else {
            $timestamp = wp_next_scheduled('wbai_update_models_cron');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'wbai_update_models_cron');
            }
        }

        return new \WP_REST_Response(['success' => true], 200);
    }

    public function check_budget_cap() {
        $budget_cap = (int) get_option('wbai_token_budget_cap', 0);
        if ($budget_cap <= 0) {
            return true;
        }

        if (class_exists('\WordPress\AI\Logging\AI_Request_Log_Manager')) {
            $manager = new \WordPress\AI\Logging\AI_Request_Log_Manager();
            $manager->init();
            $summary = $manager->get_summary('month');
            $total_tokens = isset($summary['total_tokens']) ? (int) $summary['total_tokens'] : 0;
            
            if ($total_tokens >= $budget_cap) {
                return false;
            }
        }
        
        return true;
    }

    public function run_update_models_cron() {
        if (!class_exists('\WordPress\AiClient\AiClient')) return;
        $registry = \WordPress\AiClient\AiClient::defaultRegistry();
        foreach ($registry->getRegisteredProviderIds() as $providerId) {
            try {
                $className = $registry->getProviderClassName($providerId);
                $directory = $className::modelMetadataDirectory();
                if ($directory instanceof \WordPress\AiClient\Common\Contracts\CachesDataInterface) {
                    $directory->invalidateCaches();
                }
            } catch (\Exception $e) {}
        }
        
        $requirements = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements([], []);
        $registry->findModelsMetadataForSupport($requirements);
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
            
            $enabled_models = get_option('wbai_enabled_models', []);
            $models = [];
            foreach ($providerModels as $providerMetadata) {
                $providerName = $providerMetadata->getProvider()->getName();
                foreach ($providerMetadata->getModels() as $modelMeta) {
                    $id = $modelMeta->getId();
                    $name = $modelMeta->getName() ?: $id;
                    $providerId = $providerMetadata->getProvider()->getId();
                    $uid = $providerId . '|' . $id;
                    
                    if (!empty($enabled_models) && !in_array($uid, $enabled_models)) {
                        continue;
                    }

                    if (!isset($models[$providerName])) {
                        $models[$providerName] = [];
                    }
                    $models[$providerName][$uid] = '[' . $providerName . '] ' . $name;
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