<?php
namespace WizardAi\Modules\Chatbot\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.Security.ValidatedSanitizedInput
// phpcs:disable WordPress.DB.DirectDatabaseQuery
// phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared



trait Chat {
    public function handle_chatbot_request(\WP_REST_Request $request) {
        if (!class_exists('\WordPress\AiClient\AiClient')) {
            return new \WP_Error('ai_not_found', 'AI Client not found', ['status' => 500]);
        }
        
        $session_id = sanitize_text_field($request->get_param('session_id') ?: wp_generate_uuid4());
        
        $prompt = sanitize_text_field($request->get_param('prompt'));
        if (empty($prompt)) {
            return new \WP_Error('missing_prompt', 'Prompt is required', ['status' => 400]);
        }
        
        $hp = sanitize_text_field($request->get_param('wai_hp'));
        if (!empty($hp)) {
            return new \WP_Error('bot_detected', 'Bot activity detected', ['status' => 403]);
        }

        $request_post_id = absint($request->get_param('current_post_id'));

        $chat_id = "wai_chatbot_" . $session_id;
        $stored_name = get_transient($chat_id . '_name') ?: 'Visitor';
        $stored_email = get_transient($chat_id . '_email') ?: '';
        
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        if (!$user_id && !empty($stored_email)) {
            $matched_user = get_user_by('email', $stored_email);
            if ($matched_user) {
                $user_id = $matched_user->ID;
                if ($stored_name === 'Visitor') {
                    $stored_name = $matched_user->display_name;
                }
            }
        }
        
        // Intercept form data sent by the email hint
        $info_updated = false;
        if (preg_match('/^My name is (.*?) and my email is (.*?)$/', $prompt, $matches)) {
            $stored_name = trim($matches[1]);
            $stored_email = trim($matches[2]);
            set_transient($chat_id . '_name', $stored_name, 12 * HOUR_IN_SECONDS);
            set_transient($chat_id . '_email', $stored_email, 12 * HOUR_IN_SECONDS);
            $info_updated = true;
        } else if (preg_match('/^My email is (.*?)$/', $prompt, $matches)) {
            $stored_email = trim($matches[1]);
            set_transient($chat_id . '_email', $stored_email, 12 * HOUR_IN_SECONDS);
            $info_updated = true;
        } else if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $prompt, $matches)) {
            if (empty($stored_email)) {
                $stored_email = trim($matches[0]);
                set_transient($chat_id . '_email', $stored_email, 12 * HOUR_IN_SECONDS);
                $info_updated = true;
            }
        }
        
        if ($info_updated) {
            global $wpdb;
            $op_like = 'Operator%';
            $comment_ids = $wpdb->get_col($wpdb->prepare("
                SELECT c.comment_ID FROM {$wpdb->comments} c
                INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id
                WHERE m.meta_key = 'wai_session_id' AND m.meta_value = %s 
                AND c.comment_author != 'Wizard AI' AND c.comment_author NOT LIKE %s
            ", $session_id, $op_like));
            
            if (!empty($comment_ids)) {
                $update_data = [];
                if (!empty($stored_name) && $stored_name !== 'Visitor') {
                    $update_data['comment_author'] = $stored_name;
                }
                if (!empty($stored_email)) {
                    $update_data['comment_author_email'] = $stored_email;
                }
                if (!empty($update_data)) {
                    foreach ($comment_ids as $cid) {
                        $wpdb->update($wpdb->comments, $update_data, ['comment_ID' => $cid]);
                        clean_comment_cache($cid);
                    }
                }
            }
        }
        
        $author_name = $user_id ? $current_user->display_name : $stored_name;
        $author_email = $user_id ? $current_user->user_email : $stored_email;

        $comment_ip = preg_replace('/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] ?? '');
        if ($comment_ip === '::1') {
            $comment_ip = '127.0.0.1';
        }
        $comment_agent = isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 254) : '';
        
        $comment_data = [
            'comment_post_ID' => $request_post_id,
            'comment_author' => wp_slash($author_name),
            'comment_author_email' => wp_slash($author_email),
            'user_id' => $user_id,
            'comment_content' => wp_slash($prompt),
            'comment_type' => 'wai_chat',
            'comment_author_IP' => $comment_ip,
            'comment_agent' => $comment_agent,
            'comment_date' => current_time('mysql'),
            'comment_date_gmt' => current_time('mysql', 1)
        ];
        
        $filtered_comment = wp_filter_comment($comment_data);
        if (is_wp_error($filtered_comment)) {
            return new \WP_Error('spam_detected', $filtered_comment->get_error_message(), ['status' => 403]);
        }
        $approved = wp_allow_comment($filtered_comment, true);
        if (is_wp_error($approved)) {
            return new \WP_Error('spam_detected', $approved->get_error_message(), ['status' => 403]);
        }
        if ($approved === 'spam' || $approved === 'trash') {
            return new \WP_Error('spam_detected', __('Message flagged as spam.', 'wizard-ai'), ['status' => 403]);
        }

        $manual_mode = get_transient('wai_chatbot_manual_' . $session_id);
        if ($manual_mode) {
            // Save user message
            if (get_option('wai_chatbot_track_sessions', 0)) {
                $user_comment_id = wp_insert_comment([
                    'comment_post_ID' => $request_post_id,
                    'comment_author' => wp_slash($author_name),
                    'comment_author_email' => $author_email,
                    'user_id' => $user_id,
                    'comment_content' => wp_slash($prompt),
                    'comment_type' => 'wai_chat',
                    'comment_author_IP' => $comment_ip,
                    'comment_agent' => $comment_agent,
                    'comment_approved' => 1
                ]);
                if ($user_comment_id) {
                    update_comment_meta($user_comment_id, 'wai_session_id', $session_id);
                    update_comment_meta($user_comment_id, 'wai_chat_log', 1);
                }
            }
            
            // Do not reply from AI, wait for polling
            return rest_ensure_response([
                'success' => true,
                'reply' => '',
                'session_id' => $session_id,
                'frontend_actions' => [],
                'manual_mode' => true,
                'date_gmt' => current_time('mysql', 1)
            ]);
        }

        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        // We fetch models configured by admin or just a default text generation model.
        // Try getting preferred model from admin, or fallback to first available.
        $registry = \WordPress\AiClient\AiClient::defaultRegistry();
        $requirements = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
            [
                \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration(),
                \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::chatHistory()
            ],
            []
        );
        $providerModels = $registry->findModelsMetadataForSupport($requirements);
        if (empty($providerModels)) {
            return new \WP_Error('no_models', 'No text generation models available.', ['status' => 500]);
        }
        
        $selectedProvider = null;
        $selectedModel = null;
        
        $configured_model = get_option('wai_chatbot_model', '');
        if ($configured_model && strpos($configured_model, '|') !== false) {
            list($selectedProvider, $selectedModel) = explode('|', $configured_model);
        } else {
            // Fallback to RAG provider or first available
            $rag_provider = get_option('wai_rag_embedding_provider', '');
            if ($rag_provider) {
                foreach ($providerModels as $meta) {
                    if ($meta->getProvider()->getId() === $rag_provider) {
                        $selectedProvider = $meta->getProvider()->getId();
                        $models = $meta->getModels();
                        if (!empty($models)) {
                            $selectedModel = $models[0]->getId();
                        }
                        break;
                    }
                }
            }
            
            if (!$selectedProvider || !$selectedModel) {
                $first = $providerModels[0];
                $selectedProvider = $first->getProvider()->getId();
                $selectedModel = $first->getModels()[0]->getId();
            }
        }
        
        $all_models = [];
        foreach ($providerModels as $meta) {
            $pId = $meta->getProvider()->getId();
            foreach ($meta->getModels() as $m) {
                $all_models[] = $pId . '|' . $m->getId();
            }
        }
        
        $auto_fallback = get_option('wai_chatbot_auto_fallback', 0);
        $fallback_models = get_option('wai_chatbot_fallback_models', []);
        
        $models_to_try = [];
        if ($selectedProvider && $selectedModel) {
            $models_to_try[] = $selectedProvider . '|' . $selectedModel;
        }
        
        if ($auto_fallback) {
            $candidates = !empty($fallback_models) ? $fallback_models : $all_models;
            foreach ($candidates as $cand) {
                if (!in_array($cand, $models_to_try)) {
                    $models_to_try[] = $cand;
                }
            }
        }
        
        if (empty($models_to_try)) {
            $models_to_try[] = $all_models[0];
        }

        $chat_id = "wai_chatbot_" . $session_id;
        $messages = [];
        $stored = get_transient($chat_id);
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
        }
        $is_first_message = empty($messages);
        
        if ($is_first_message && get_option('wai_chatbot_notify_new_session', 0)) {
            $notify_email = get_option('wai_chatbot_notify_email', get_option('admin_email'));
            if (!empty($notify_email)) {
                $subject = __('New Chatbot Session Started', 'wizard-ai');
                /* translators: %s: Site name */
                $message_body = sprintf(__('A new chatbot session has been started on your website %s.', 'wizard-ai'), get_bloginfo('name')) . "\n\n";
                /* translators: %s: Session ID */
                $message_body .= sprintf(__('Session ID: %s', 'wizard-ai'), $session_id) . "\n\n";
                
                $log_url = admin_url('admin.php?page=wizard-ai-chatbot-logs&action=view&session_id=' . urlencode($session_id));
                /* translators: %s: Log URL */
                $message_body .= sprintf(__('You can view the conversation and optionally take over here: %s', 'wizard-ai'), $log_url) . "\n\n";
                /* translators: %s: User message */
                $message_body .= sprintf(__('User Message: %s', 'wizard-ai'), $prompt);
                
                wp_mail($notify_email, $subject, $message_body);
            }
        }

        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $prompt, $matches)) {
            $chat_id = "wai_chatbot_" . $session_id;
            if (!get_transient($chat_id . '_email')) {
                set_transient($chat_id . '_email', $matches[0], 12 * HOUR_IN_SECONDS);
            }
        }

        // Fetch RAG context, filtering out reserved data
        $rag_context = "";
        if (get_option('wai_chatbot_use_rag', 1)) {
            $upload_dir = wp_upload_dir();
            $db_path = $upload_dir['basedir'] . '/wai/rag.sqlite';
            if (file_exists($db_path)) {
                try {
                    $db = new \PDO('sqlite:' . $db_path);
                    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
                    
                    $public_post_types = array_values(get_post_types(['public' => true], 'names'));
                    $allowed_types = array_unique(array_merge(['block', 'knowledgebase', 'product'], $public_post_types));
                    $in_clause = implode("', '", array_map('esc_sql', $allowed_types));
                    $post_type_sql = "post_type IN ('" . $in_clause . "')";
                    
                    $rows = [];
                    $matched_post_ids = [];
                    $post_types = [];

                    if (get_option('wai_chatbot_full_rag_on_first', 0) && $is_first_message) {
                        // Load full context for the first message
                        $first_limit = absint(get_option('wai_chatbot_rag_first_limit', 30));
                        if ($first_limit < 1) $first_limit = 30;
                        
                        $stmt = $db->query("SELECT DISTINCT post_id, post_type FROM document_embeddings WHERE {$post_type_sql} LIMIT {$first_limit}");
                        if ($stmt) {
                            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                                $matched_post_ids[] = $row['post_id'];
                                $post_types[$row['post_id']] = $row['post_type'];
                            }
                        }
                    } else {
                        // Simple Keyword Search for RAG
                        $search_limit = absint(get_option('wai_chatbot_rag_search_limit', 5));
                        if ($search_limit < 1) $search_limit = 5;
                        $clean_prompt = preg_replace('/[^\p{L}\p{N}\s]/u', '', $prompt);
                        $keywords = array_filter(explode(' ', $clean_prompt), function($k) { return mb_strlen($k) > 2; });
                        $conditions = [];
                        foreach ($keywords as $k) {
                            $conditions[] = "text_content LIKE " . $db->quote('%' . $k . '%');
                        }
                        
                        $where = count($conditions) > 0 ? " AND (" . implode(" OR ", $conditions) . ")" : "";
                        
                        $stmt = $db->query("SELECT DISTINCT post_id, post_type FROM document_embeddings WHERE {$post_type_sql} $where LIMIT {$search_limit}");
                        
                        if ($stmt) {
                            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                                $matched_post_ids[] = $row['post_id'];
                                $post_types[$row['post_id']] = $row['post_type'];
                            }
                        }
                        
                        // If keyword search didn't find anything, fallback to general context
                        if (empty($matched_post_ids)) {
                            $stmt = $db->query("SELECT DISTINCT post_id, post_type FROM document_embeddings WHERE {$post_type_sql} LIMIT 3");
                            if ($stmt) {
                                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                                    $matched_post_ids[] = $row['post_id'];
                                    $post_types[$row['post_id']] = $row['post_type'];
                                }
                            }
                        }
                    }

                    // CONTEXT REASSEMBLY: Reconstruct full pages from matched post IDs
                    if (!empty($matched_post_ids)) {
                        $in_ids = implode(',', array_map('intval', array_unique($matched_post_ids)));
                        $full_doc_stmt = $db->query("SELECT post_id, text_content FROM document_embeddings WHERE post_id IN ({$in_ids}) ORDER BY post_id, chunk_index ASC");
                        
                        $assembled_docs = [];
                        if ($full_doc_stmt) {
                            while ($row = $full_doc_stmt->fetch(\PDO::FETCH_ASSOC)) {
                                $pid = $row['post_id'];
                                if (!isset($assembled_docs[$pid])) {
                                    $assembled_docs[$pid] = "";
                                }
                                $assembled_docs[$pid] .= $row['text_content'] . "\n";
                            }
                        }
                        
                        foreach ($assembled_docs as $pid => $content) {
                            $rows[] = [
                                'post_type' => $post_types[$pid],
                                'text_content' => trim($content)
                            ];
                        }
                    }

                    if (!empty($rows)) {
                        $rag_context .= "Website Knowledge Base Context:\n";
                        foreach ($rows as $row) {
                            $rag_context .= "Type: " . $row['post_type'] . "\n" . $row['text_content'] . "\n\n";
                        }
                    }
                } catch (\Exception $e) {}
            }
        }
        $site_info = "Website Name: " . get_bloginfo('name') . "\n";
        $site_info .= "Website URL: " . get_site_url() . "\n";
        $site_info .= "Website Description: " . get_bloginfo('description') . "\n";
        $site_info .= "Current System Date and Time: " . current_time('mysql') . "\n\n";

        if (class_exists('SitePress')) {
            $langs = apply_filters('wpml_active_languages', null, 'orderby=id&order=desc');
            if (!empty($langs)) {
                $site_info .= "Supported Website Languages: ";
                $lang_codes = [];
                foreach ($langs as $l) {
                    $lang_codes[] = $l['code'] . ' (' . $l['native_name'] . ')';
                }
                $site_info .= implode(', ', $lang_codes) . "\n";
            }
            $current_lang = apply_filters('wpml_current_language', null);
            if ($current_lang) {
                $site_info .= "Visitor's Current Page Language Code: " . $current_lang . "\n";
            }
            $site_info .= "\n";
        }

        $current_url = sanitize_text_field($request->get_param('current_url'));
        $current_title = sanitize_text_field($request->get_param('current_title'));
        $current_content = sanitize_textarea_field($request->get_param('current_content'));
        
        $request_post_id = absint($request->get_param('current_post_id'));
        $is_editor_user = $request_post_id && current_user_can('edit_post', $request_post_id);
        $is_elementor = $is_editor_user && get_post_meta($request_post_id, '_elementor_edit_mode', true) === 'builder';
        
        if (!empty($current_url) || !empty($current_title) || !empty($current_content)) {
            $site_info .= "--- Visitor's Current Page ---\n";
            if (!empty($current_url)) $site_info .= "URL: " . $current_url . "\n";
            if (!empty($current_title)) $site_info .= "Title: " . $current_title . "\n";
            if (!empty($current_content)) $site_info .= "Page Content:\n" . $current_content . "\n";
            $site_info .= "--------------------------------\n\n";
        }

        $system_prompt = "You are a helpful assistant for this website. You are speaking to a frontend visitor. Answer their questions using the context provided below. If the provided context does not contain the answer, you MUST use the `search_site_content` tool to find the relevant information on the site before giving up.\n"
                       . "To optimize token usage, you MUST provide extremely concise and direct answers. Avoid unnecessary pleasantries or long explanations.\n"
                       . "IMPORTANT: Your primary scope is to provide a brief SUMMARY of the requested information, and if a URL is available in the context include a raw HTML button link (e.g. <a href=\"URL\" class=\"wai-chatbot-btn\" target=\"_blank\">Read Full Info</a>) to direct the user to the relevant page where they can find the full details.\n"
                       . "CRITICAL RULE: DO NOT provide a button link to a page if the URL exactly matches or corresponds to the \"Visitor's Current Page URL\" provided in the context below. The user is already on that page, so linking to it is redundant.\n"
                       . "IMPORTANT LANGUAGE RULE: If the user is speaking a language that differs from the current page language, or if they explicitly ask for a page in a specific language, ALWAYS use the `wpml_get_translated_url` tool to fetch the correct localized link before providing it.\n\n";
                       
        if (class_exists('WooCommerce') && get_option('wai_chatbot_woocommerce', 1)) {
            $system_prompt .= "WOOCOMMERCE RULE: If the user asks for a product, you MUST try to find the best match using the `wc_search_products` tool. When you find the product(s), you MUST provide a raw HTML button link to the product using the URL returned by the tool, AND ask the user if they want you to add it to their cart. If they say yes and the product is a 'variable' product, you MUST ask the user which variant they need by showing them the available variations BEFORE adding it to the cart. Use `wc_add_to_cart` to add the chosen product/variation to the cart.\n\n";
        }
        
        $system_prompt .= "CRITICAL TOOL RULE: You MUST ONLY call ONE tool per response! DO NOT execute multiple tools in parallel in a single response. If multiple actions are needed, execute the first one, wait for the response, and then execute the next. Violating this rule will cause a fatal API error.\n\n";
        

        
        $system_prompt .= $site_info . $rag_context;

        $custom_context_setting = get_option('wai_chatbot_custom_context', '');
        if (!empty($custom_context_setting)) {
            $system_prompt .= "Custom Site Instructions:\n" . $custom_context_setting . "\n\n";
        }

        $user_msg_content = $prompt;
        
        if ($is_first_message && get_option('wai_chatbot_full_rag_on_first', 0) && !empty($rag_context)) {
            $user_msg_content = "Please learn this initial context about the website:\n\n" . $rag_context . "\n\nUser Request: " . $prompt;
            // Clear it from the system prompt so it's not duplicated on every request
            $system_prompt = str_replace($rag_context, "", $system_prompt);
        }

        $messages[] = new \WordPress\AiClient\Messages\DTO\UserMessage([
            new \WordPress\AiClient\Messages\DTO\MessagePart($user_msg_content)
        ]);

        // Trim History to optimize tokens, but always keep the first message if RAG is injected there
        $history_limit = absint(get_option('wai_chatbot_history_limit', 10));
        if ($history_limit > 0 && count($messages) > $history_limit) {
            $first_msg = $messages[0];
            
            $slice_start = count($messages) - ($history_limit - 1);
            $adjust_start = $slice_start;
            
            while ($adjust_start > 1 && $adjust_start < count($messages)) {
                $check_msg = $messages[$adjust_start];
                $has_function_response = false;
                if (method_exists($check_msg, 'getParts')) {
                    foreach ($check_msg->getParts() as $part) {
                        if (method_exists($part, 'getFunctionResponse') && $part->getFunctionResponse() !== null) {
                            $has_function_response = true;
                            break;
                        }
                    }
                }
                
                if ($has_function_response) {
                    $adjust_start--; // Step back to include the preceding ModelMessage with FunctionCall
                } else {
                    break;
                }
            }
            
            $sliced = array_slice($messages, $adjust_start);
            $messages = array_merge([$first_msg], $sliced);
        }

        set_time_limit(300);
        $timeout_filter = function($timeout, $url) {
            return 300;
        };
        add_filter('http_request_timeout', $timeout_filter, 999, 2);

        $last_exception = null;
        $frontend_actions = [];
        
        $tools = [];
        if (class_exists('SitePress')) {
            $tools[] = new \WordPress\AiClient\Tools\DTO\FunctionDeclaration(
                'wpml_get_translated_url',
                'Get the translated URL of a page or product in a specific language. Use this to provide links in the user\'s requested language.',
                ['type' => 'object', 'properties' => ['url' => ['type' => 'string', 'description' => 'The original URL'], 'language_code' => ['type' => 'string', 'description' => 'The 2-letter language code (e.g. it, en, fr)']], 'required' => ['url', 'language_code']]
            );
        }
        
        if (class_exists('WooCommerce') && get_option('wai_chatbot_woocommerce', 1)) {
            $tools[] = new \WordPress\AiClient\Tools\DTO\FunctionDeclaration(
                'wc_add_to_cart',
                'Add a specific product or variation to the shopping cart.',
                ['type' => 'object', 'properties' => [
                    'product_id' => ['type' => 'integer', 'description' => 'The ID of the WooCommerce product to add.'],
                    'variation_id' => ['type' => 'integer', 'description' => 'Optional. The ID of the specific variation to add, if the product is a variable product.']
                ], 'required' => ['product_id']]
            );
            $tools[] = new \WordPress\AiClient\Tools\DTO\FunctionDeclaration(
                'wc_remove_from_cart',
                'Remove a product from the shopping cart.',
                ['type' => 'object', 'properties' => ['product_id' => ['type' => 'integer', 'description' => 'The ID of the product to remove.']], 'required' => ['product_id']]
            );
            $tools[] = new \WordPress\AiClient\Tools\DTO\FunctionDeclaration(
                'wc_apply_coupon',
                'Apply a discount coupon code to the shopping cart.',
                ['type' => 'object', 'properties' => ['coupon_code' => ['type' => 'string', 'description' => 'The coupon code to apply.']], 'required' => ['coupon_code']]
            );
            $tools[] = new \WordPress\AiClient\Tools\DTO\FunctionDeclaration(
                'wc_get_user_orders',
                'Get the current logged-in user\'s past orders and their status. Use this to check order status or check if a refund is possible.',
                ['type' => 'object', 'properties' => new \stdClass()]
            );
            $default_lang = class_exists('SitePress') ? apply_filters('wpml_default_language', NULL) : get_bloginfo('language');
            $tools[] = new \WordPress\AiClient\Tools\DTO\FunctionDeclaration(
                'wc_search_products',
                'Search for WooCommerce products by name or keyword. Returns product ID, name, price, type, and available variations. CRITICAL INSTRUCTION: You MUST search using the user\'s exact original words first. If and ONLY if the tool returns an error that no products were found, you MUST then translate the search query to the website\'s primary language (' . $default_lang . ') and CALL THIS TOOL AGAIN immediately with the translated word.',
                ['type' => 'object', 'properties' => ['query' => ['type' => 'string', 'description' => 'The search query for the product.']], 'required' => ['query']]
            );
        }

        $default_lang = class_exists('SitePress') ? apply_filters('wpml_default_language', NULL) : get_bloginfo('language');
        $tools[] = new \WordPress\AiClient\Tools\DTO\FunctionDeclaration(
            'search_site_content',
            'Search the website for pages, posts, or products matching a query. CRITICAL INSTRUCTION: You MUST search using the user\'s exact original words first. If and ONLY if the tool returns an error that no results were found, you MUST then translate the search query to the website\'s primary language (' . $default_lang . ') and CALL THIS TOOL AGAIN immediately with the translated word.',
            ['type' => 'object', 'properties' => ['query' => ['type' => 'string', 'description' => 'The search query string.']], 'required' => ['query']]
        );

        $tools[] = new \WordPress\AiClient\Tools\DTO\FunctionDeclaration(
            'request_human_contact',
            'Call this tool ONLY if you have tried multiple times and cannot provide a valid reply to the user, and you want to offer them the possibility to be contacted by a human operator.',
            ['type' => 'object', 'properties' => new \stdClass()]
        );



        $tools[] = new \WordPress\AiClient\Tools\DTO\FunctionDeclaration(
            'wpab__ai__fill_form_field',
            'Fill a frontend HTML form field with a specific value. Useful when assisting a user to complete a form.',
            ['type' => 'object', 'properties' => [
                'field_name' => ['type' => 'string', 'description' => 'The name attribute or ID attribute of the form field to fill.'],
                'value' => ['type' => 'string', 'description' => 'The value to fill into the field.']
            ], 'required' => ['field_name', 'value']]
        );

        for ($i = 0; $i < count($models_to_try); $i++) {
            list($tryProvider, $tryModel) = explode('|', $models_to_try[$i]);
            
            $max_retries = 2;
            for ($attempt = 0; $attempt <= $max_retries; $attempt++) {
                try {
                    $max_iterations = 5;
                    $loop_messages = $messages;
                    $display_text = "";
                    
                    while ($max_iterations > 0) {
                        $ai_query = \WordPress\AiClient\AiClient::prompt($loop_messages);
                        $ai_query->usingModelPreference([$tryProvider, $tryModel]);
                        $ai_query->usingSystemInstruction($system_prompt);
                        
                        if (!empty($tools)) {
                            $ai_query->usingFunctionDeclarations(...$tools);
                        }

                        $ai = \WizardAi\Modules\Ai\Ai::instance();
                        if (!$ai->check_budget_cap()) {
                            throw new \Exception(__('Monthly token budget cap exceeded. Please upgrade your budget or wait until next month.', 'wizard-ai'));
                        }
                        $result = $ai_query->generateResult();
                        $response_message = $result->toMessage();
                        
                        $has_tools = false;
                        $tool_responses = [];
                        $filtered_parts = [];
                        $tool_count = 0;
                        
                        foreach ($response_message->getParts() as $part) {
                            if ($part->getFunctionCall() !== null) {
                                $tool_count++;
                                if ($tool_count > 1) {
                                    continue;
                                }
                                
                                $filtered_parts[] = $part;
                                $has_tools = true;
                                $fc = $part->getFunctionCall();
                                $name = $fc->getName();
                                $args = is_string($fc->getArgs()) ? json_decode($fc->getArgs(), true) : (array)$fc->getArgs();
                                $tool_result = ['error' => 'Tool not found.'];
                                
                                if (class_exists('WooCommerce')) {
                                    if ($name === 'wc_add_to_cart') {
                                        $pid = isset($args['product_id']) ? intval($args['product_id']) : 0;
                                        $vid = isset($args['variation_id']) ? intval($args['variation_id']) : 0;
                                        if ($pid > 0 && function_exists('wc_get_product') && wc_get_product($pid)) {
                                            if ($vid > 0) {
                                                WC()->cart->add_to_cart($pid, 1, $vid);
                                            } else {
                                                WC()->cart->add_to_cart($pid);
                                            }
                                            $checkout_url = wc_get_checkout_url();
                                            $tool_result = ['success' => true, 'message' => 'Product added to cart successfully. IMPORTANT: You MUST inform the user and append this exact HTML button to your reply so they can checkout: <a href="' . esc_url($checkout_url) . '" class="wai-chatbot-btn" target="_parent">Proceed to Checkout</a>'];
                                        } else {
                                            $tool_result = ['error' => 'Invalid product ID or product not found.'];
                                        }
                                    } elseif ($name === 'wc_remove_from_cart') {
                                        $pid = isset($args['product_id']) ? intval($args['product_id']) : 0;
                                        $removed = false;
                                        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                                            if ($cart_item['product_id'] == $pid) {
                                                WC()->cart->remove_cart_item($cart_item_key);
                                                $removed = true;
                                            }
                                        }
                                        $tool_result = $removed ? ['success' => true, 'message' => 'Product removed from cart.'] : ['error' => 'Product not found in cart.'];
                                    } elseif ($name === 'wc_apply_coupon') {
                                        $code = isset($args['coupon_code']) ? sanitize_text_field($args['coupon_code']) : '';
                                        if (!empty($code)) {
                                            $res = WC()->cart->apply_coupon($code);
                                            $tool_result = $res ? ['success' => true, 'message' => 'Coupon applied successfully.'] : ['error' => 'Invalid or expired coupon.'];
                                        }
                                    } elseif ($name === 'wc_get_user_orders') {
                                        $user_id = get_current_user_id();
                                        if ($user_id > 0) {
                                            $orders = wc_get_orders(['customer' => $user_id, 'limit' => 5]);
                                            $o_data = [];
                                            foreach ($orders as $o) {
                                                $o_data[] = ['id' => $o->get_id(), 'status' => $o->get_status(), 'total' => $o->get_total(), 'date' => $o->get_date_created() ? $o->get_date_created()->date('Y-m-d') : ''];
                                            }
                                            $tool_result = ['orders' => $o_data];
                                        } else {
                                            $tool_result = ['error' => 'User is not logged in.'];
                                        }
                                    } elseif ($name === 'wc_search_products') {
                                        $q = isset($args['query']) ? sanitize_text_field($args['query']) : '';
                                        if (!empty($q)) {
                                            $search_queries = [$q];
                                            $p_data = [];
                                            $default_lang = class_exists('SitePress') ? apply_filters('wpml_default_language', NULL) : get_bloginfo('language');
                                            
                                            for ($idx = 0; $idx < count($search_queries); $idx++) {
                                                $current_q = $search_queries[$idx];
                                                $products = wc_get_products([
                                                    's' => $current_q,
                                                    'status' => 'publish',
                                                    'limit' => 5,
                                                ]);
                                                
                                                foreach ($products as $p) {
                                                    $item = [
                                                        'id' => $p->get_id(),
                                                        'name' => $p->get_name(),
                                                        'type' => $p->get_type(),
                                                        'price' => $p->get_price(),
                                                        'url' => get_permalink($p->get_id()),
                                                    ];
                                                    if ($p->is_type('variable')) {
                                                        $item['variations'] = [];
                                                        $available_variations = $p->get_available_variations();
                                                        foreach ($available_variations as $var) {
                                                            $attr_string = [];
                                                            foreach ($var['attributes'] as $attr_k => $attr_v) {
                                                                $attr_string[] = str_replace('attribute_', '', $attr_k) . ': ' . $attr_v;
                                                            }
                                                            $item['variations'][] = [
                                                                'variation_id' => $var['variation_id'],
                                                                'attributes' => implode(', ', $attr_string),
                                                                'price' => $var['display_price']
                                                            ];
                                                        }
                                                    }
                                                    $p_data[] = $item;
                                                }
                                                
                                                if (!empty($p_data)) {
                                                    break; // Results found!
                                                }
                                                
                                                // If empty and it's the first try, attempt translation
                                                if ($idx === 0 && $tryProvider && $tryModel) {
                                                    try {
                                                        $t_prompt = "If the following text is not in $default_lang, translate it to $default_lang. Provide up to 3 common synonyms or related product keywords in $default_lang, separated by commas. Output ONLY the comma-separated list. If it is already in $default_lang, output EXACTLY the word 'SAME'. Text: \"$current_q\"";
                                                        $t_msg = [
                                                            new \WordPress\AiClient\Messages\DTO\UserMessage([
                                                                new \WordPress\AiClient\Messages\DTO\MessagePart($t_prompt)
                                                            ])
                                                        ];
                                                        $t_query = \WordPress\AiClient\AiClient::prompt($t_msg);
                                                        $t_query->usingModelPreference([$tryProvider, $tryModel]);
                                                        $t_res = trim($t_query->generateResult()->toText());
                                                        $t_res = trim($t_res, '"\'');
                                                        error_log("WIZARD AI SEARCH TRANSLATION: " . $current_q . " -> " . $t_res);
                                                        if ($t_res !== 'SAME' && !empty($t_res)) {
                                                            $synonyms = array_map('trim', explode(',', $t_res));
                                                            foreach ($synonyms as $syn) {
                                                                if (!empty($syn) && strtolower($syn) !== strtolower($current_q) && !in_array($syn, $search_queries)) {
                                                                    $search_queries[] = $syn;
                                                                }
                                                            }
                                                        }
                                                    } catch (\Exception $e) {}
                                                }
                                            }
                                            
                                            if (empty($p_data)) {
                                                $tool_result = ['error' => 'No products found. Inform the user.'];
                                            } else {
                                                $tool_result = ['products' => $p_data, '_translated_query' => count($search_queries) > 1 ? $search_queries[1] : false];
                                            }
                                        } else {
                                            $tool_result = ['error' => 'Query is required.'];
                                        }
                                    }
                                }
                                
                                if ($name === 'search_site_content') {
                                    $q = isset($args['query']) ? sanitize_text_field($args['query']) : '';
                                    if (!empty($q)) {
                                        $search_queries = [$q];
                                        $search_results = [];
                                        $default_lang = class_exists('SitePress') ? apply_filters('wpml_default_language', NULL) : get_bloginfo('language');
                                        
                                        for ($idx = 0; $idx < count($search_queries); $idx++) {
                                            $current_q = $search_queries[$idx];
                                            $search_query = new \WP_Query([
                                                's' => $current_q,
                                                'post_type' => ['post', 'page', 'product'],
                                                'post_status' => 'publish',
                                                'posts_per_page' => 5,
                                            ]);
                                            
                                            if ($search_query->have_posts()) {
                                                foreach ($search_query->posts as $p) {
                                                    $search_results[] = [
                                                        'title' => $p->post_title,
                                                        'type' => $p->post_type,
                                                        'url' => get_permalink($p->ID),
                                                        'snippet' => wp_trim_words(strip_shortcodes(wp_strip_all_tags($p->post_content)), 100)
                                                    ];
                                                }
                                                break; // Found results!
                                            }
                                            
                                            // If empty and it's the first try, attempt translation
                                            if ($idx === 0 && $tryProvider && $tryModel) {
                                                try {
                                                    $t_prompt = "If the following text is not in $default_lang, translate it to $default_lang. Provide up to 3 common synonyms or related keywords in $default_lang, separated by commas. Output ONLY the comma-separated list. If it is already in $default_lang, output EXACTLY the word 'SAME'. Text: \"$current_q\"";
                                                    $t_msg = [
                                                        new \WordPress\AiClient\Messages\DTO\UserMessage([
                                                            new \WordPress\AiClient\Messages\DTO\MessagePart($t_prompt)
                                                        ])
                                                    ];
                                                    $t_query = \WordPress\AiClient\AiClient::prompt($t_msg);
                                                    $t_query->usingModelPreference([$tryProvider, $tryModel]);
                                                    $t_res = trim($t_query->generateResult()->toText());
                                                    $t_res = trim($t_res, '"\'');
                                                    error_log("WIZARD AI CONTENT SEARCH TRANSLATION: " . $current_q . " -> " . $t_res);
                                                    if ($t_res !== 'SAME' && !empty($t_res)) {
                                                        $synonyms = array_map('trim', explode(',', $t_res));
                                                        foreach ($synonyms as $syn) {
                                                            if (!empty($syn) && strtolower($syn) !== strtolower($current_q) && !in_array($syn, $search_queries)) {
                                                                $search_queries[] = $syn;
                                                            }
                                                        }
                                                    }
                                                } catch (\Exception $e) {}
                                            }
                                        }
                                        
                                        if (empty($search_results)) {
                                            $tool_result = ['error' => 'No results found. Inform the user.'];
                                        } else {
                                            $tool_result = ['results' => $search_results, '_translated_query' => count($search_queries) > 1 ? $search_queries[1] : false];
                                        }
                                    } else {
                                        $tool_result = ['error' => 'Query is required.'];
                                    }
                                }

                                if (class_exists('SitePress')) {
                                    if ($name === 'wpml_get_translated_url') {
                                        $url = isset($args['url']) ? sanitize_text_field($args['url']) : '';
                                        $lang = isset($args['language_code']) ? sanitize_text_field($args['language_code']) : '';
                                        if (!empty($url) && !empty($lang)) {
                                            $translated_url = apply_filters('wpml_permalink', $url, $lang);
                                            $tool_result = ['success' => true, 'translated_url' => $translated_url];
                                        } else {
                                            $tool_result = ['error' => 'URL and language_code are required.'];
                                        }
                                    }
                                }

                                if ($name === 'request_human_contact') {
                                    $chat_id = "wai_chatbot_" . $session_id;
                                    $stored_email = get_transient($chat_id . '_email');
                                    $has_email = is_user_logged_in() || !empty($stored_email);
                                    
                                    if ($has_email) {
                                        $tool_result = ['success' => true, 'message' => 'We already have the user\'s email on file. Inform them that a human operator will contact them shortly.'];
                                    } else {
                                        $frontend_actions[] = [
                                            'type' => 'show_email_form'
                                        ];
                                        $tool_result = ['success' => true, 'message' => 'The email form has been shown to the user on their screen. Ask them to fill it out to be contacted by a human.'];
                                    }
                                }

                                if ($name === 'wpab__ai__fill_form_field') {
                                    $field_name = isset($args['field_name']) ? sanitize_text_field($args['field_name']) : '';
                                    $val = isset($args['value']) ? sanitize_text_field($args['value']) : '';
                                    
                                    if (!$field_name) {
                                        $tool_result = ['error' => 'Missing field_name.'];
                                    } else {
                                        $frontend_actions[] = [
                                            'type' => 'fill_form',
                                            'fieldName' => $field_name,
                                            'fieldValue' => $val
                                        ];
                                        $tool_result = ['success' => true, 'message' => "Field '$field_name' filled with '$val'. Inform the user."];
                                    }
                                }

                                $tool_responses[] = new \WordPress\AiClient\Messages\DTO\MessagePart(
                                    new \WordPress\AiClient\Tools\DTO\FunctionResponse($fc->getId(), $name, (object)$tool_result)
                                );
                            } else {
                                $filtered_parts[] = $part;
                            }
                        }
                        
                        $loop_messages[] = new \WordPress\AiClient\Messages\DTO\ModelMessage($filtered_parts);
                        
                        if ($has_tools) {
                            foreach ($tool_responses as $tr) {
                                $loop_messages[] = new \WordPress\AiClient\Messages\DTO\UserMessage([$tr]);
                            }
                            $max_iterations--;
                            continue;
                        } else {
                            $ai_text = trim($result->toText());
                            $display_text = $ai_text;
                            
                            $stored_name = get_transient($chat_id . '_name') ?: '';
                            $stored_email = get_transient($chat_id . '_email') ?: '';
                            
                            if (preg_match('/My name is (.*?) and my email is ([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $prompt, $matches)) {
                                $stored_name = trim($matches[1]);
                                $stored_email = trim($matches[2]);
                                set_transient($chat_id . '_name', $stored_name, 3600);
                                set_transient($chat_id . '_email', $stored_email, 3600);
                            } elseif (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $prompt, $matches)) {
                                $stored_email = $matches[0];
                                set_transient($chat_id . '_email', $stored_email, 3600);
                            }

                            $current_user = wp_get_current_user();
                            $user_id = $current_user->ID;
                            
                            if (class_exists('\League\CommonMark\CommonMarkConverter')) {
                                if (class_exists('\League\CommonMark\GithubFlavoredMarkdownConverter')) {
                                    $converter = new \League\CommonMark\GithubFlavoredMarkdownConverter(['html_input' => 'allow', 'allow_unsafe_links' => false]);
                                } else {
                                    $converter = new \League\CommonMark\CommonMarkConverter(['html_input' => 'allow', 'allow_unsafe_links' => false]);
                                }
                                $display_text = $converter->convert($ai_text)->getContent();
                            }
                            
                            set_transient($chat_id, serialize($loop_messages), 3600);

                            if (get_option('wai_chatbot_track_sessions', 0)) {
                                $author_name = $user_id ? $current_user->display_name : ($stored_name ?: 'Visitor');
                                $author_email = $user_id ? $current_user->user_email : $stored_email;

                                if (!$user_id && !empty($author_email)) {
                                    $matched_user = get_user_by('email', $author_email);
                                    if ($matched_user) {
                                        $user_id = $matched_user->ID;
                                        if ($author_name === 'Visitor') {
                                            $author_name = $matched_user->display_name;
                                        }
                                    }
                                }

                                $user_comment_id = wp_insert_comment([
                                    'comment_post_ID' => $request_post_id,
                                    'comment_author' => wp_slash($author_name),
                                    'comment_author_email' => $author_email,
                                    'user_id' => $user_id,
                                    'comment_content' => wp_slash($prompt),
                                    'comment_type' => 'wai_chat',
                                    'comment_author_IP' => $comment_ip,
                                    'comment_agent' => $comment_agent,
                                    'comment_approved' => 1
                                ]);
                                
                                // Removed $tool_info debug text

                                if ($user_comment_id) {
                                    update_comment_meta($user_comment_id, 'wai_session_id', $session_id);
                                    update_comment_meta($user_comment_id, 'wai_chat_log', 1);
                                }
                                
                                $ai_comment_id = wp_insert_comment([
                                    'comment_post_ID' => $request_post_id,
                                    'comment_author' => 'Wizard AI',
                                    'comment_content' => wp_slash($ai_text),
                                    'comment_type' => 'wai_chat',
                                    'comment_approved' => 1
                                ]);
                                if ($ai_comment_id) {
                                    update_comment_meta($ai_comment_id, 'wai_session_id', $session_id);
                                    update_comment_meta($ai_comment_id, 'wai_chat_log', 1);
                                }
                            }
                            
                            break;
                        }
                    }

                    remove_filter('http_request_timeout', $timeout_filter, 999);

                    return rest_ensure_response([
                        'success' => true,
                        'reply' => $display_text,
                        'session_id' => $session_id,
                        'frontend_actions' => $frontend_actions,
                        'date_gmt' => current_time('mysql', 1)
                    ]);
                } catch (\Exception $e) {
                    $last_exception = $e;
                    $error_msg = strtolower($e->getMessage());
                    
                    if ($attempt < $max_retries && (strpos($error_msg, '429') !== false || strpos($error_msg, '408') !== false || strpos($error_msg, '500') !== false || strpos($error_msg, '502') !== false || strpos($error_msg, '503') !== false || strpos($error_msg, 'rate limit') !== false || strpos($error_msg, 'timeout') !== false)) {
                        sleep(2);
                        continue;
                    } else {
                        break;
                    }
                }
            }
        }
        
        remove_filter('http_request_timeout', $timeout_filter, 999);
        return new \WP_Error('ai_error', $last_exception ? $last_exception->getMessage() : 'All models failed.', ['status' => 500]);
    }

    public function handle_chatbot_poll(\WP_REST_Request $request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $last_time = sanitize_text_field($request->get_param('last_time')); // format: Y-m-d H:i:s
        
        if (empty($session_id) || empty($last_time)) {
            return new \WP_Error('invalid_params', 'Missing parameters', ['status' => 400]);
        }
        
        global $wpdb;
        $query = $wpdb->prepare("
            SELECT c.* 
            FROM {$wpdb->comments} c
            INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id
            WHERE c.comment_type = 'wai_chat' 
            AND m.meta_key = 'wai_session_id' AND m.meta_value = %s
            AND c.comment_date_gmt > %s
            ORDER BY c.comment_date_gmt ASC
        ", $session_id, $last_time);
        
        $comments = $wpdb->get_results($query);
        $messages = [];
        
        foreach ($comments as $c) {
            $role = 'ai';
            $author = $c->comment_author;
            
            if ($c->comment_author !== 'Wizard AI' && strpos($c->comment_author, 'Agent') === false) {
                // Determine if it's the user or the admin
                $chat_id = "wai_chatbot_" . $session_id;
                $stored_email = get_transient($chat_id . '_email');
                if ($c->comment_author_email !== $stored_email && user_can($c->user_id, 'manage_options')) {
                    $role = 'ai'; // operator
                } else {
                    $role = 'user';
                    if ($author === 'Visitor') {
                        $author = 'You';
                    }
                }
            } else {
                $author = get_option('wai_chatbot_name', 'AI Bot');
                if (empty($author)) {
                    $author = 'AI Bot';
                }
                $author = apply_filters('wpml_translate_single_string', $author, 'wizard-ai', 'chatbot_name');
            }
            
            $messages[] = [
                'text' => wp_kses_post($c->comment_content),
                'role' => $role,
                'author' => esc_html($author),
                'date_gmt' => $c->comment_date_gmt
            ];
        }
        
        $manual_mode = get_transient('wai_chatbot_manual_' . $session_id) ? true : false;
        
        return rest_ensure_response([
            'success' => true,
            'messages' => $messages,
            'manual_mode' => $manual_mode
        ]);
    }

    public function handle_chatbot_check_new_activity(\WP_REST_Request $request) {
        $last_check = sanitize_text_field($request->get_param('last_check'));
        $session_id = sanitize_text_field($request->get_param('session_id'));
        
        if (empty($last_check)) {
            return new \WP_Error('invalid_params', 'Missing last_check', ['status' => 400]);
        }
        
        global $wpdb;
        
        if (!empty($session_id)) {
            $query = $wpdb->prepare("
                SELECT COUNT(c.comment_ID) 
                FROM {$wpdb->comments} c
                INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id
                WHERE c.comment_type = 'wai_chat' 
                AND c.comment_date_gmt > %s
                AND m.meta_key = 'wai_session_id' 
                AND m.meta_value = %s
            ", $last_check, $session_id);
        } else {
            $query = $wpdb->prepare("
                SELECT COUNT(comment_ID) 
                FROM {$wpdb->comments} 
                WHERE comment_type = 'wai_chat' AND comment_date_gmt > %s
            ", $last_check);
        }
        
        $count = $wpdb->get_var($query);
        
        return rest_ensure_response([
            'success' => true,
            'has_new' => $count > 0
        ]);
    }

}
