<?php
namespace WizardAi\Modules\Ai\Traits\Abilities;

trait Core {
    public function register_core_abilities() {
        wp_register_ability('ai/db-query', [
            'label' => __('Execute DB Query', 'wizard-ai'),
            'description' => __('Execute raw SQL queries. Support SELECT, UPDATE, DELETE. Limit results.', 'wizard-ai'),
            'category' => 'wizard-blocks',
            'execute_callback' => function($input) {
                global $wpdb;
                $query = $input['query'];
                
                $query_upper = strtoupper(trim($query));

                if (strpos($query_upper, 'SELECT ') === 0 || strpos($query_upper, 'SHOW ') === 0) {
                    $result = $wpdb->get_results($query, ARRAY_A);
                } else {
                    $result = $wpdb->query($query);
                    if ($result !== false) {
                        $result = ['success' => true, 'affected_rows' => $result];
                    }
                }

                if ($wpdb->last_error) {
                    return new \WP_Error('db_error', $wpdb->last_error);
                }
                
                if (is_array($result) && !isset($result['success']) && count($result) > 100) {
                    $result = array_slice($result, 0, 100);
                    $result[] = ['_warning' => 'Results truncated to 100 rows to prevent memory exhaustion. Please use a LIMIT clause or more specific WHERE conditions.'];
                }
                
                $response = $result !== null ? $result : ['success' => true];
                return $response;
            },
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Raw SQL query']
                ],
                'required' => ['query']
            ]
        ]);
        
        wp_register_ability('ai/modify-file', [
            'label' => __('Modifying File', 'wizard-ai'),
            'description' => __('Writes or overwrites a file on the server with the provided content. You MUST always provide the full \'content\' parameter. To read a file, use the read-file tool instead. Safely restricted to wp-content.', 'wizard-ai'),
            'category' => 'wizard-blocks',
            'execute_callback' => function($input) {
                $path = wp_normalize_path($input['path']);
                $content = $input['content'];
                
                $wp_content_dir = wp_normalize_path(WP_CONTENT_DIR);
                if (strpos($path, $wp_content_dir) !== 0) {
                    return new \WP_Error('security_error', 'Modification is restricted to the wp-content directory to ensure site safety. You cannot modify: ' . $path);
                }

                if (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                    $tmp_file = tempnam(sys_get_temp_dir(), 'wb_php_');
                    file_put_contents($tmp_file, $content);
                    exec('php -l ' . escapeshellarg($tmp_file) . ' 2>&1', $output, $return_var);
                    unlink($tmp_file);
                    if ($return_var !== 0) {
                        return new \WP_Error('syntax_error', 'PHP Syntax error detected, file save aborted. Please fix the error and try again. Error: ' . implode("\n", $output));
                    }
                }
                
                $dir = dirname($path);
                if (!file_exists($dir)) {
                    if (!mkdir($dir, 0755, true)) {
                        return new \WP_Error('file_error', 'Failed to create directory: ' . $dir);
                    }
                }
                
                if (file_put_contents($path, $content) === false) {
                    return new \WP_Error('file_error', 'Failed to write file: ' . $path);
                }
                return ['success' => true, 'message' => 'File written successfully: ' . $path];
            },
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'description' => 'Absolute file path'],
                    'content' => ['type' => 'string', 'description' => 'File content']
                ],
                'required' => ['path', 'content']
            ]
        ]);

        wp_register_ability('ai/execute-php', [
            'label' => __('Execute PHP Code', 'wizard-ai'),
            'description' => __('Execute arbitrary PHP code within the WordPress environment. Use this to create posts, manage taxonomies, update settings, or call any native WordPress function. Do NOT include opening <?php tags. Return a value to receive it in the response.', 'wizard-ai'),
            'category' => 'wizard-blocks',
            'execute_callback' => function($input) {
                require_once(ABSPATH . 'wp-admin/includes/post.php');
                require_once(ABSPATH . 'wp-admin/includes/taxonomy.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');

                $code = $input['code'];
                
                if (preg_match('/\b(die|exit)\s*\(/i', $code)) {
                    return new \WP_Error('security_error', 'The use of die() or exit() is blocked as it will break the AI API response loop.');
                }
                
                global $wbai_is_executing;
                $wbai_is_executing = true;
                
                global $wbai_shutdown_registered;
                if (!$wbai_shutdown_registered) {
                    register_shutdown_function(function() {
                        global $wbai_is_executing;
                        if ($wbai_is_executing) {
                            $error = error_get_last();
                            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                                while (ob_get_level()) { ob_end_clean(); }
                                wp_send_json_error('Fatal Error during PHP execution: ' . $error['message'] . ' on line ' . $error['line']);
                            }
                        }
                    });
                    $wbai_shutdown_registered = true;
                }
                
                ob_start();
                try {
                    $result = eval($code);
                    $output = ob_get_clean();
                    $wbai_is_executing = false;
                    
                    $response = [
                        'success' => true,
                        'result' => $result,
                        'output' => $output
                    ];
                    return $response;
                } catch (\Throwable $e) {
                    $wbai_is_executing = false;
                    ob_end_clean();
                    return new \WP_Error('php_error', $e->getMessage() . ' on line ' . $e->getLine());
                }
            },
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'string', 'description' => 'PHP code to execute. Can include a return statement.']
                ],
                'required' => ['code']
            ]
        ]);

        wp_register_ability('ai/read-file', [
            'label' => __('Read File', 'wizard-ai'),
            'description' => __('Read a file from the server.', 'wizard-ai'),
            'category' => 'wizard-blocks',
            'execute_callback' => function($input) {
                $path = $input['path'];
                if (!file_exists($path)) {
                    return new \WP_Error('file_error', 'File not found: ' . $path);
                }
                $content = file_get_contents($path);
                if ($content === false) {
                    return new \WP_Error('file_error', 'Failed to read file: ' . $path);
                }
                return ['success' => true, 'content' => $content];
            },
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'description' => 'Absolute file path']
                ],
                'required' => ['path']
            ]
        ]);
        
        wp_register_ability('ai/list-directory', [
            'label' => __('List Directory', 'wizard-ai'),
            'description' => __('List files and folders in a directory.', 'wizard-ai'),
            'category' => 'wizard-blocks',
            'execute_callback' => function($input) {
                $path = $input['path'];
                if (!is_dir($path)) {
                    return new \WP_Error('dir_error', 'Directory not found: ' . $path);
                }
                $files = scandir($path);
                if ($files === false) {
                    return new \WP_Error('dir_error', 'Failed to read directory: ' . $path);
                }
                return ['success' => true, 'files' => array_values(array_diff($files, ['.', '..']))];
            },
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'description' => 'Absolute directory path']
                ],
                'required' => ['path']
            ]
        ]);

        wp_register_ability('ai/manage-plugins', [
            'label' => __('Manage Plugins', 'wizard-ai'),
            'description' => __('Manage WordPress plugins safely (list, install, activate, deactivate, delete).', 'wizard-ai'),
            'category' => 'wizard-blocks',
            'execute_callback' => function($input) {
                if (!function_exists('get_plugins')) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }
                
                $action = $input['action'];
                $slug = isset($input['slug']) ? sanitize_text_field($input['slug']) : '';
                
                if ($action === 'list') {
                    $all_plugins = get_plugins();
                    $active_plugins = get_option('active_plugins', []);
                    $data = [];
                    foreach ($all_plugins as $path => $info) {
                        $data[] = [
                            'path' => $path,
                            'name' => $info['Name'],
                            'version' => $info['Version'],
                            'status' => in_array($path, $active_plugins) ? 'active' : 'inactive'
                        ];
                    }
                    return ['success' => true, 'plugins' => $data];
                }
                
                if (empty($slug)) {
                    return new \WP_Error('missing_slug', 'Plugin slug/path is required for this action.');
                }
                
                $plugin_file = $slug;
                if (strpos($plugin_file, '.php') === false && $action !== 'install') {
                    $plugins = get_plugins();
                    foreach ($plugins as $path => $p) {
                        if (strpos($path, $slug . '/') === 0 || $path === $slug . '.php') {
                            $plugin_file = $path;
                            break;
                        }
                    }
                }
                
                $p_dirname = dirname($plugin_file);
                if (in_array($action, ['update', 'delete', 'rollback']) && $p_dirname && $p_dirname !== '.') {
                    $plugin_dir = wp_normalize_path(WP_PLUGIN_DIR . '/' . $p_dirname);
                    if (is_dir($plugin_dir) && class_exists('ZipArchive')) {
                        $upload_dir = wp_upload_dir();
                        $backup_dir = $upload_dir['basedir'] . '/wbai/backup';
                        if (!is_dir($backup_dir)) wp_mkdir_p($backup_dir);
                        $safe_slug = sanitize_title($p_dirname);
                        $zip_name = $safe_slug . '_' . time() . '.zip';
                        $zip_path = $backup_dir . '/' . $zip_name;
                        
                        $zip = new \ZipArchive();
                        if ($zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($plugin_dir), \RecursiveIteratorIterator::LEAVES_ONLY);
                            foreach ($files as $name => $file) {
                                if (!$file->isDir()) {
                                    $file_path = wp_normalize_path($file->getRealPath());
                                    $relative_path = substr($file_path, strlen($plugin_dir) + 1);
                                    $zip->addFile($file_path, $relative_path);
                                }
                            }
                            $zip->close();
                            
                            $backup_data = [
                                'action' => 'plugin-backup',
                                'plugin_dir' => $plugin_dir,
                                'zip_path' => $zip_path,
                                'slug' => $p_dirname
                            ];
                            file_put_contents($backup_dir . '/plugin_' . $safe_slug . '_' . time() . '.json', json_encode($backup_data));
                        }
                    }
                }
                
                if ($action === 'activate') {
                    $result = activate_plugin($plugin_file);
                    if (is_wp_error($result)) return $result;
                    return ['success' => true, 'message' => "Plugin $plugin_file activated."];
                } elseif ($action === 'deactivate') {
                    deactivate_plugins($plugin_file);
                    return ['success' => true, 'message' => "Plugin $plugin_file deactivated."];
                } elseif ($action === 'delete') {
                    deactivate_plugins($plugin_file);
                    $result = delete_plugins([$plugin_file]);
                    if (is_wp_error($result)) return $result;
                    return ['success' => true, 'message' => "Plugin $plugin_file deleted."];
                } elseif ($action === 'install' || $action === 'update' || $action === 'rollback') {
                    include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
                    include_once ABSPATH . 'wp-admin/includes/file.php';
                    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
                    include_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
                    
                    if ($action === 'update' && empty($input['version'])) {
                        $upgrader = new \Plugin_Upgrader(new \Automatic_Upgrader_Skin());
                        $result = $upgrader->upgrade($plugin_file);
                        if (is_wp_error($result) || $result === false) {
                            return new \WP_Error('update_failed', 'Failed to update plugin.');
                        }
                        return ['success' => true, 'message' => "Plugin $plugin_file updated successfully."];
                    }
                    
                    $api = plugins_api('plugin_information', ['slug' => $slug]);
                    if (is_wp_error($api)) return $api;
                    
                    $download_link = $api->download_link;
                    $version = $input['version'] ?? '';
                    
                    if ($action === 'rollback' || (!empty($version) && $action === 'update')) {
                        if (empty($version)) return new \WP_Error('missing_version', 'Version is required for rollback.');
                        if (!isset($api->versions) || !isset($api->versions[$version])) {
                            return new \WP_Error('invalid_version', "Version $version not found in WordPress repository for $slug.");
                        }
                        $download_link = $api->versions[$version];
                    }
                    
                    $upgrader = new \Plugin_Upgrader(new \Automatic_Upgrader_Skin());
                    $install_args = [];
                    if ($action === 'rollback' || $action === 'update') {
                        $install_args['clear_destination'] = true;
                    }
                    
                    $result = $upgrader->install($download_link, $install_args);
                    
                    if (is_wp_error($result) || $result === false) {
                        return new \WP_Error('action_failed', "Failed to $action plugin.");
                    }
                    return ['success' => true, 'message' => "Plugin $slug successfully processed ($action" . (!empty($version) ? " to version $version" : "") . ")."];
                }
                
                return new \WP_Error('invalid_action', 'Unsupported action.');
            },
            'permission_callback' => function() { return current_user_can('activate_plugins'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['list', 'install', 'activate', 'deactivate', 'delete', 'update', 'rollback'], 'description' => 'Action to perform'],
                    'slug' => ['type' => 'string', 'description' => 'Plugin directory slug (e.g., "woocommerce") or full path (e.g., "woocommerce/woocommerce.php"). Not needed for list action.'],
                    'version' => ['type' => 'string', 'description' => 'Specific version to rollback/update to.']
                ],
                'required' => ['action']
            ]
        ]);

        wp_register_ability('ai/manage-themes', [
            'label' => __('Manage Themes', 'wizard-ai'),
            'description' => __('Manage WordPress themes safely (list, activate).', 'wizard-ai'),
            'category' => 'wizard-blocks',
            'execute_callback' => function($input) {
                $action = $input['action'];
                $slug = isset($input['slug']) ? sanitize_text_field($input['slug']) : '';
                
                if ($action === 'list') {
                    $themes = wp_get_themes();
                    $active = wp_get_theme()->get_stylesheet();
                    $data = [];
                    foreach ($themes as $stylesheet => $theme) {
                        $data[] = [
                            'slug' => $stylesheet,
                            'name' => $theme->get('Name'),
                            'version' => $theme->get('Version'),
                            'status' => ($stylesheet === $active) ? 'active' : 'inactive'
                        ];
                    }
                    return ['success' => true, 'themes' => $data];
                } elseif ($action === 'activate') {
                    if (empty($slug)) return new \WP_Error('missing_slug', 'Theme slug is required.');
                    switch_theme($slug);
                    return ['success' => true, 'message' => "Theme $slug activated."];
                }
                return new \WP_Error('invalid_action', 'Unsupported action.');
            },
            'permission_callback' => function() { return current_user_can('switch_themes'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['list', 'activate'], 'description' => 'Action to perform'],
                    'slug' => ['type' => 'string', 'description' => 'Theme slug. Not needed for list action.']
                ],
                'required' => ['action']
            ]
        ]);

        wp_register_ability('ai/manage-system', [
            'label' => __('Manage System & Cache', 'wizard-ai'),
            'description' => __('Perform system actions like flushing permalinks, clearing cache, and transients.', 'wizard-ai'),
            'category' => 'wizard-blocks',
            'execute_callback' => function($input) {
                $action = $input['action'];
                if ($action === 'flush_rewrite_rules') {
                    flush_rewrite_rules();
                    return ['success' => true, 'message' => 'Rewrite rules flushed.'];
                } elseif ($action === 'clear_transients') {
                    global $wpdb;
                    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%' OR option_name LIKE '\_site\_transient\_%'");
                    return ['success' => true, 'message' => 'Transients cleared.'];
                } elseif ($action === 'clear_cache') {
                    $cleared = [];
                    if (function_exists('rocket_clean_domain')) { rocket_clean_domain(); $cleared[] = 'WP Rocket'; }
                    if (function_exists('w3tc_flush_all')) { w3tc_flush_all(); $cleared[] = 'W3TC'; }
                    if (class_exists('LiteSpeed\Purge')) { \LiteSpeed\Purge::purge_all(); $cleared[] = 'LiteSpeed'; }
                    if (function_exists('sg_cachepress_purge_cache')) { sg_cachepress_purge_cache(); $cleared[] = 'SG Optimizer'; }
                    return ['success' => true, 'message' => 'Cache cleared.', 'cleared_systems' => $cleared];
                }
                return new \WP_Error('invalid_action', 'Unsupported action.');
            },
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['flush_rewrite_rules', 'clear_transients', 'clear_cache'], 'description' => 'Action to perform']
                ],
                'required' => ['action']
            ]
        ]);

        wp_register_ability('ai/manage-debug', [
            'label' => __('Manage Debug Log', 'wizard-ai'),
            'description' => __('Enable or disable WordPress debug logging. When enabled, it sets WP_DEBUG and WP_DEBUG_LOG to true and WP_DEBUG_DISPLAY to false in wp-config.php. Read the debug log file from wp-content/debug.log', 'wizard-ai'),
            'category' => 'wizard-blocks',
            'execute_callback' => function($input) {
                $action = $input['action'];
                $config_path = ABSPATH . 'wp-config.php';
                if (!file_exists($config_path)) {
                    return new \WP_Error('config_error', 'wp-config.php not found.');
                }
                
                if ($action === 'read') {
                    $log_path = WP_CONTENT_DIR . '/debug.log';
                    if (!file_exists($log_path)) {
                        return ['success' => true, 'log' => 'Debug log is empty or does not exist.'];
                    }
                    $filesize = filesize($log_path);
                    $read_size = min(100000, $filesize);
                    $file = @fopen($log_path, 'r');
                    if ($file && $read_size > 0) {
                        fseek($file, -$read_size, SEEK_END);
                        $log_content = fread($file, $read_size);
                        fclose($file);
                        return ['success' => true, 'log' => $log_content];
                    }
                    return new \WP_Error('read_error', 'Could not read debug.log.');
                }

                $config = file_get_contents($config_path);
                
                if ($action === 'enable') {
                    $config = preg_replace('/define\(\s*\'WP_DEBUG\'\s*,\s*(true|false)\s*\);/', '', $config);
                    $config = preg_replace('/define\(\s*\'WP_DEBUG_LOG\'\s*,\s*(true|false)\s*\);/', '', $config);
                    $config = preg_replace('/define\(\s*\'WP_DEBUG_DISPLAY\'\s*,\s*(true|false)\s*\);/', '', $config);
                    $config = preg_replace('/@ini_set\(\s*\'display_errors\'\s*,\s*0\s*\);/', '', $config);
                    
                    $debug_code = "define('WP_DEBUG', true);\ndefine('WP_DEBUG_LOG', true);\ndefine('WP_DEBUG_DISPLAY', false);\n@ini_set('display_errors', 0);";
                    
                    if (strpos($config, "/* That's all, stop editing!") !== false) {
                        $config = str_replace("/* That's all, stop editing!", $debug_code . "\n/* That's all, stop editing!", $config);
                    } else {
                        $config .= "\n" . $debug_code . "\n";
                    }
                    
                    file_put_contents($config_path, $config);
                    return ['success' => true, 'message' => 'Debug log enabled. WP_DEBUG_DISPLAY is set to false.'];
                } elseif ($action === 'disable') {
                    $config = preg_replace('/define\(\s*\'WP_DEBUG\'\s*,\s*(true|false)\s*\);/', '', $config);
                    $config = preg_replace('/define\(\s*\'WP_DEBUG_LOG\'\s*,\s*(true|false)\s*\);/', '', $config);
                    $config = preg_replace('/define\(\s*\'WP_DEBUG_DISPLAY\'\s*,\s*(true|false)\s*\);/', '', $config);
                    $config = preg_replace('/@ini_set\(\s*\'display_errors\'\s*,\s*0\s*\);/', '', $config);
                    
                    $debug_code = "define('WP_DEBUG', false);";
                    
                    if (strpos($config, "/* That's all, stop editing!") !== false) {
                        $config = str_replace("/* That's all, stop editing!", $debug_code . "\n/* That's all, stop editing!", $config);
                    } else {
                        $config .= "\n" . $debug_code . "\n";
                    }
                    
                    file_put_contents($config_path, $config);
                    return ['success' => true, 'message' => 'Debug log disabled.'];
                }
                return new \WP_Error('invalid_action', 'Unsupported action.');
            },
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['enable', 'disable', 'read'], 'description' => 'Action to perform']
                ],
                'required' => ['action']
            ]
        ]);

        wp_register_ability('ai/run-wp-cli', [
            'label' => __('Run WP-CLI Command', 'wizard-ai'),
            'description' => __('Runs a WP-CLI command on the server synchronously. Provide the arguments as an array of strings (e.g. ["plugin", "list", "--format=json"]).', 'wizard-ai'),
            'category' => 'wizard-blocks',
            'execute_callback' => function($input) {
                if (!function_exists('exec')) {
                    return new \WP_Error('process_execution_disabled', 'Process execution (exec) is disabled.');
                }
                
                $wp_path = null;
                $common_paths = ['/usr/local/bin/wp', '/usr/bin/wp', '/bin/wp'];
                foreach ($common_paths as $path) {
                    if (is_file($path) && is_executable($path)) {
                        $wp_path = $path;
                        break;
                    }
                }
                
                if (!$wp_path) {
                    exec('which wp 2>/dev/null', $output, $return_var);
                    if ($return_var === 0 && !empty($output[0])) {
                        $wp_path = trim($output[0]);
                    }
                }
                
                if (!$wp_path) {
                    return new \WP_Error('wp_cli_not_found', 'WP-CLI is not installed or not executable.');
                }

                $args = $input['args'];
                if (!in_array('--allow-root', $args, true)) {
                    array_unshift($args, '--allow-root');
                }
                
                $cmd_args = array_map('escapeshellarg', $args);
                $cmd = escapeshellarg($wp_path) . ' ' . implode(' ', $cmd_args) . ' 2>&1';
                
                $output = [];
                $return_var = 0;
                exec('cd ' . escapeshellarg(ABSPATH) . ' && ' . $cmd, $output, $return_var);
                
                return [
                    'success' => $return_var === 0,
                    'exit_code' => $return_var,
                    'output' => implode("\n", $output)
                ];
            },
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'args' => [
                        'type' => 'array',
                        'description' => 'Arguments to pass to wp (e.g. ["plugin", "list", "--format=json"]).',
                        'items' => ['type' => 'string']
                    ]
                ],
                'required' => ['args']
            ]
        ]);

    }
}
