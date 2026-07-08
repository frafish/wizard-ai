<?php
namespace WizardAi\Modules\Playground\Traits;

trait Chat {
    public function handle_chat_request(\WP_REST_Request $request) {
        $params = $request->get_json_params();
        $prompt = sanitize_textarea_field($params['prompt'] ?? '');
        $requested_model = sanitize_text_field($params['model'] ?? '');
        
        if (!empty($requested_model)) {
            update_user_meta(get_current_user_id(), '_wai_preferred_model', $requested_model);
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
                $conversation_id = uniqid('wai_');
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

                $request_object_type = sanitize_text_field($params['object_type'] ?? '');
                $is_playground_request = in_array($request_object_type, ['toplevel_page_wizard-ai-playground', 'wizard-blocks_page_wizard-ai-playground']);
                
                if ($is_playground_request) {
                    // Log the prompt
                    $log_file = $this->get_prompts_log_file();
                    $current_user = wp_get_current_user();
                    $username = $current_user->exists() ? $current_user->user_login : 'unknown';
                    $log_entry = sprintf("[%s] %s : %s\n", current_time('Y-m-d H:i:s'), $username, str_replace("\n", " ", $prompt));
                    file_put_contents($log_file, $log_entry, FILE_APPEND);
                }
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
                update_option('wai_permanent_context', $permanent_info);
            } else {
                $permanent_info = get_option('wai_permanent_context', '');
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
                . "To optimize token usage, you MUST provide extremely concise and direct answers. Avoid unnecessary pleasantries or long explanations. "
                . "You are empowered to act autonomously directly within the WordPress instance using your tools when needed. "
                . "Your capabilities include: 1) Manage and maintain the site (update WP core, install/remove plugins/themes, manage user roles). 2) Create and organize content (add/edit/delete posts/pages, categories, tags, images, comments). 3) Run WooCommerce stores (manage products, orders, customers). 4) Improve performance and security (identify speed issues, audit vulnerabilities, troubleshoot conflicts). 5) Customize and enhance the site (design guidance, snippets, SEO). 6) Adjust technical settings (permalinks, options, multilanguage). "
                . "You have full access to native WordPress Core functions and main plugin APIs (such as WooCommerce). When performing actions, querying data, or manipulating the environment to achieve these goals, you are strongly encouraged to use your `execute-php` tool to leverage native WordPress or WooCommerce PHP functions rather than relying solely on raw SQL or basic file modifications. You are an expert in the WordPress ecosystem, its hooks, filters, and best practices."
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
                
            $system_instruction .= \WizardAi\Modules\Ai\Ai::instance()->get_ai_skills();

            $is_playground = in_array($object_type, ['toplevel_page_wizard-ai-playground', 'wizard-blocks_page_wizard-ai-playground']);
            if (!$is_playground && !empty($object_type)) {
                $system_instruction .= "\n\nCRITICAL CONFINEMENT RULE: You are currently active as an embedded Agent inside a specific Page/Post editor. You MUST NOT use PHP tools or database queries to directly update the `post_content` of the current post. Instead, you MUST output Gutenberg blocks in your final response using ```gutenberg-insert, ```gutenberg-edit, or ```gutenberg-replace markdown blocks. To update meta fields or ACF/SCF custom fields, you MUST output a JSON block using ```meta-update containing key-value pairs. The editor UI will automatically apply these blocks and meta updates for the user. You may use tools to fetch data or edit the title/status, but DO NOT save the main body blocks or meta directly to the database via tools. If THEME STYLES are provided in the context, you MUST use their CSS utility classes (e.g., `has-[slug]-color`, `has-[slug]-font-size`) instead of inventing inline styles or hex codes.";
            }

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
                        if (strtolower($schema['type']) !== 'object' && isset($schema['properties'])) {
                            unset($schema['properties']);
                        }
                    } elseif (isset($schema['properties'])) {
                        $schema['type'] = 'object';
                    }
                    
                    if (isset($schema['enum']) && is_array($schema['enum'])) {
                        $schema['enum'] = array_values(array_filter($schema['enum'], function($val) {
                            return $val !== '' && $val !== null;
                        }));
                        if (empty($schema['enum'])) {
                            unset($schema['enum']);
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
                    $backup_dir = $upload_dir['basedir'] . '/wai/backup';
                    if (!is_dir($backup_dir)) wp_mkdir_p($backup_dir);
                    
                    $is_cron = $request->get_param('is_cron');
                    $backup_data = [
                        'action' => $is_cron ? 'cron-rollback' : 'global-rollback',
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

                    global $wai_is_executing;
                    $wai_is_executing = true;
                    
                    foreach ($last_message->getParts() as $part) {
                        if ($part->getFunctionCall() !== null) {
                            $fc = $part->getFunctionCall();
                            $ai_logger = \WizardAi\Modules\Ai\Ai::instance();
                            if (method_exists($ai_logger, 'log_audit_event')) {
                                $ctx = $is_cron ? 'cron_agent' : 'playground_agent';
                                $ai_logger->log_audit_event($ctx, $fc->getName(), $fc->getArgs(), 'success');
                            }
                        }
                    }

                    ob_start();
                    $response = $resolver->execute_abilities($last_message);
                    $stray_output = ob_get_clean();
                    
                    $wai_is_executing = false;

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
                        $messages[] = new \WordPress\AiClient\Messages\DTO\UserMessage([new \WordPress\AiClient\Messages\DTO\MessagePart("System: Tool execution returned no output.")]);
                    } else {
                        foreach ($new_parts as $part) {
                            $messages[] = new \WordPress\AiClient\Messages\DTO\UserMessage([$part]);
                        }
                    }
                }
            }

            $ai_text = '';
            $retry_without_tools = false;
            
            $models_to_try = [];
            if (!empty($requested_model)) {
                $models_to_try[] = $requested_model;
            }
            
            if ($fallback_models) {
                $models_response = \WizardAi\Modules\Ai\Ai::instance()->get_ai_models($request);
                $models_data = $models_response->get_data();
                if (!empty($models_data['models'])) {
                    $all_models = [];
                    foreach ($models_data['models'] as $provider_models) {
                        foreach (array_keys($provider_models) as $model_id) {
                            $all_models[] = $model_id;
                        }
                    }
                    foreach ($all_models as $m) {
                        if ($m !== $requested_model && !in_array($m, $models_to_try)) {
                            $models_to_try[] = $m;
                        }
                    }
                }
            }
            
            if (empty($models_to_try)) {
                $models_to_try = [''];
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

                    if ($fallback_models) {
                        $is_fallback_error = ($error_code >= 400 && $error_code < 600) || preg_match('/\b[45][0-9]{2}\b/', $error_msg) || strpos($error_msg, 'api error') !== false || strpos($error_msg, 'upstream') !== false;
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
