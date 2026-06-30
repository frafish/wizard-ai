<?php
namespace WizardAi\Modules\Ai\Traits;

trait Chatbot {
    public function register_chatbot_hooks() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_chatbot_scripts']);
        add_action('wp_footer', [$this, 'render_chatbot']);
        add_action('rest_api_init', [$this, 'register_chatbot_routes']);
    }

    public function register_chatbot_routes() {
        register_rest_route('wizard-blocks/v1', '/chatbot', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_chatbot_request'],
            'permission_callback' => function(\WP_REST_Request $request) {
                $nonce = $request->get_header('X-WP-Nonce');
                if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_rest')) {
                    return new \WP_Error('rest_forbidden', __('Bot activity detected. Invalid nonce.', 'wizard-ai'), ['status' => 403]);
                }
                return true;
            }
        ]);
    }

    public function enqueue_chatbot_scripts() {
        if (!get_option('wbai_chatbot_enabled', 1)) return;

        $upload_dir = wp_upload_dir();
        $db_path = $upload_dir['basedir'] . '/wbai/rag.sqlite';
        if (!file_exists($db_path)) return;

        wp_enqueue_style('dashicons');
        wp_enqueue_style('wizard-ai-chatbot-style', WIZARD_AI_URL . 'modules/ai/assets/css/chatbot.css', [], '1.0.1');
        wp_enqueue_script('wizard-ai-chatbot-script', WIZARD_AI_URL . 'modules/ai/assets/js/chatbot.js', ['jquery', 'jquery-ui-draggable', 'jquery-ui-resizable'], '1.0.1', true);
        
        $post_id = get_the_ID();
        if (!$post_id) {
            $post_id = get_option('page_on_front');
        }
        if (!$post_id) {
            $fallback = get_posts(['post_type' => 'any', 'post_status' => 'publish', 'posts_per_page' => 1]);
            $post_id = !empty($fallback) ? $fallback[0]->ID : 0;
        }

        wp_localize_script('wizard-ai-chatbot-script', 'wizardAiChatbotData', [
            'rest_url' => esc_url_raw(rest_url('wizard-blocks/v1/chatbot')),
            'nonce' => wp_create_nonce('wp_rest'),
            'debugMode' => (defined('WP_DEBUG') && WP_DEBUG),
            'resetConfirm' => __('Are you sure you want to start a new chat?', 'wizard-ai'),
            'post_id' => $post_id
        ]);
    }

    public function render_chatbot() {
        if (!get_option('wbai_chatbot_enabled', 1)) return;

        $upload_dir = wp_upload_dir();
        $db_path = $upload_dir['basedir'] . '/wbai/rag.sqlite';
        if (!file_exists($db_path)) return;
        
        $icon = esc_attr(get_option('wbai_chatbot_icon', 'dashicons-format-chat'));
        $color = esc_attr(get_option('wbai_chatbot_color', '#2271b1'));
        $position = get_option('wbai_chatbot_position', 'bottom-right');
        
        $chatbot_name = get_option('wbai_chatbot_name', '');
        $chatbot_name = apply_filters('wpml_translate_single_string', $chatbot_name, 'wizard-ai', 'chatbot_name');
        $chatbot_name = esc_html($chatbot_name);

        $greeting = get_option('wbai_chatbot_greeting', 'Hello! How can I help you today?');
        $greeting = apply_filters('wpml_translate_single_string', $greeting, 'wizard-ai', 'chatbot_greeting');
        $greeting = esc_html($greeting);
        
        $pos_class = $position === 'bottom-left' ? 'wbai-pos-bottom-left' : 'wbai-pos-bottom-right';
        $live_mode = get_option('wbai_chatbot_live_mode', 0);
        ?>
        <style>
            #wbai-chatbot-header, #wbai-chatbot-send, #wbai-chatbot-email-submit { background-color: <?php echo esc_attr($color); ?> !important; }
            .wbai-privacy-link { color: <?php echo esc_attr($color); ?> !important; }
        </style>
        <div id="wbai-chatbot" class="wbai-chatbot-closed <?php echo $pos_class; ?>" data-live-mode="<?php echo esc_attr($live_mode ? '1' : '0'); ?>">
            <div id="wbai-chatbot-header">
                <span class="dashicons <?php echo $icon; ?>"></span>
                <?php if (!empty($chatbot_name)): ?>
                    <span class="wbai-chatbot-title-text"><?php echo $chatbot_name; ?></span>
                <?php endif; ?>
                <button id="wbai-chatbot-reset" title="<?php esc_attr_e('Reset Chat', 'wizard-ai'); ?>"><span class="dashicons dashicons-update-alt"></span></button>
                <button id="wbai-chatbot-toggle"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
            </div>
            <div id="wbai-chatbot-body">
                <div id="wbai-chatbot-messages">
                    <div class="wbai-chatbot-msg wbai-chatbot-sys"><?php echo $greeting; ?></div>
                </div>
                <?php 
                $contact_msg = get_option('wbai_chatbot_contact_msg', '');
                if (!is_user_logged_in() && !empty($contact_msg)): ?>
                    <div id="wbai-chatbot-email-hint">
                        <div class="wbai-chatbot-contact-msg"><?php echo esc_html($contact_msg); ?></div>
                        <div class="wbai-chatbot-email-form">
                            <input type="text" id="wbai-chatbot-name-input" class="wbai-chatbot-email-input" placeholder="<?php esc_attr_e('Your Name', 'wizard-ai'); ?>">
                            <input type="email" id="wbai-chatbot-email-input" class="wbai-chatbot-email-input" placeholder="<?php esc_attr_e('Your Email', 'wizard-ai'); ?>">
                            <button id="wbai-chatbot-email-submit" class="button"><?php esc_html_e('Send', 'wizard-ai'); ?></button>
                        </div>
                    </div>
                <?php endif; ?>
                <div id="wbai-chatbot-input-area">
                    <input type="text" id="wbai-chatbot-hp" value="" tabindex="-1" autocomplete="off">
                    <textarea id="wbai-chatbot-prompt" placeholder="<?php esc_attr_e('Ask a question...', 'wizard-ai'); ?>"></textarea>
                    <?php if ($live_mode): ?>
                        <button id="wbai-chatbot-mic" class="button" title="<?php esc_attr_e('Speech to Text', 'wizard-ai'); ?>"><span class="dashicons dashicons-microphone"></span></button>
                    <?php endif; ?>
                    <button id="wbai-chatbot-send" class="button button-primary"><span class="dashicons dashicons-controls-play"></span></button>
                </div>
                <div id="wbai-chatbot-gdpr-notice">
                    <?php 
                    $default_gdpr_text = __('By chatting, you agree to our processing of conversation logs to assist with your request.', 'wizard-ai');
                    $custom_gdpr_text = get_option('wbai_chatbot_gdpr_text', $default_gdpr_text);
                    $custom_gdpr_text = apply_filters('wpml_translate_single_string', $custom_gdpr_text, 'wizard-ai', 'chatbot_gdpr_text');
                    
                    if (!is_user_logged_in() && get_option('wbai_chatbot_track_sessions', 0)) {
                        echo '<label class="wbai-chatbot-gdpr-label">';
                        echo '<input type="checkbox" id="wbai-chatbot-gdpr-consent">';
                        echo '<span>' . wp_kses_post($custom_gdpr_text) . '</span></label>';
                    } else {
                        echo wp_kses_post($custom_gdpr_text);
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function handle_chatbot_request(\WP_REST_Request $request) {
        if (!class_exists('\WordPress\AiClient\AiClient')) {
            return new \WP_Error('ai_not_found', 'AI Client not found', ['status' => 500]);
        }
        
        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        $hp = sanitize_text_field($request->get_param('wbai_hp'));
        if (!empty($hp)) {
            return new \WP_Error('bot_detected', 'Bot activity detected', ['status' => 403]);
        }

        $prompt = sanitize_text_field($request->get_param('prompt'));
        if (empty($prompt)) {
            return new \WP_Error('missing_prompt', 'Prompt is required', ['status' => 400]);
        }

        $session_id = sanitize_text_field($request->get_param('session_id') ?: wp_generate_uuid4());

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
        
        $configured_model = get_option('wbai_chatbot_model', '');
        if ($configured_model && strpos($configured_model, '|') !== false) {
            list($selectedProvider, $selectedModel) = explode('|', $configured_model);
        } else {
            // Fallback to RAG provider or first available
            $rag_provider = get_option('wbai_rag_embedding_provider', '');
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
        
        $auto_fallback = get_option('wbai_chatbot_auto_fallback', 0);
        $fallback_models = get_option('wbai_chatbot_fallback_models', []);
        
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

        $chat_id = "wbai_chatbot_" . $session_id;
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
        
        // Fetch RAG context, filtering out reserved data
        $rag_context = "";
        if (get_option('wbai_chatbot_use_rag', 1)) {
            $upload_dir = wp_upload_dir();
            $db_path = $upload_dir['basedir'] . '/wbai/rag.sqlite';
            if (file_exists($db_path)) {
                try {
                    $db = new \PDO('sqlite:' . $db_path);
                    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
                    
                    $public_post_types = array_values(get_post_types(['public' => true], 'names'));
                    $allowed_types = array_unique(array_merge(['block', 'knowledgebase', 'product'], $public_post_types));
                    $in_clause = implode("', '", array_map('esc_sql', $allowed_types));
                    $post_type_sql = "post_type IN ('" . $in_clause . "')";
                    
                    $rows = [];
                    if (get_option('wbai_chatbot_full_rag_on_first', 0) && $is_first_message) {
                        // Load everything up to a limit for the first message to build deep context
                        $first_limit = absint(get_option('wbai_chatbot_rag_first_limit', 30));
                        if ($first_limit < 1) $first_limit = 30;
                        
                        $stmt = $db->query("SELECT text_content, post_type FROM document_embeddings WHERE {$post_type_sql} LIMIT {$first_limit}");
                        if ($stmt) {
                            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                                $rows[] = $row;
                            }
                        }
                    } else {
                        // Simple Keyword Search for RAG on subsequent requests or if full-first-message is off
                        $search_limit = absint(get_option('wbai_chatbot_rag_search_limit', 5));
                        if ($search_limit < 1) $search_limit = 5;
                        $clean_prompt = preg_replace('/[^\p{L}\p{N}\s]/u', '', $prompt);
                        $keywords = array_filter(explode(' ', $clean_prompt), function($k) { return mb_strlen($k) > 2; });
                        $conditions = [];
                        foreach ($keywords as $k) {
                            $conditions[] = "text_content LIKE " . $db->quote('%' . $k . '%');
                        }
                        
                        $where = count($conditions) > 0 ? " AND (" . implode(" OR ", $conditions) . ")" : "";
                        
                        $stmt = $db->query("SELECT text_content, post_type FROM document_embeddings WHERE {$post_type_sql} $where LIMIT {$search_limit}");
                        
                        if ($stmt) {
                            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                                $rows[] = $row;
                            }
                        }
                        
                        // If keyword search didn't find anything, fallback to general context
                        if (empty($rows)) {
                            $stmt = $db->query("SELECT text_content, post_type FROM document_embeddings WHERE {$post_type_sql} LIMIT 3");
                            if ($stmt) {
                                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                                    $rows[] = $row;
                                }
                            }
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
        
        if (!empty($current_url) || !empty($current_title) || !empty($current_content)) {
            $site_info .= "--- Visitor's Current Page ---\n";
            if (!empty($current_url)) $site_info .= "URL: " . $current_url . "\n";
            if (!empty($current_title)) $site_info .= "Title: " . $current_title . "\n";
            if (!empty($current_content)) $site_info .= "Page Content:\n" . $current_content . "\n";
            $site_info .= "--------------------------------\n\n";
        }

        $system_prompt = "You are a helpful assistant for this website. You are speaking to a frontend visitor. Answer their questions using the context provided below. If the provided context does not contain the answer, you MUST use the `search_site_content` tool to find the relevant information on the site before giving up.\n"
                       . "IMPORTANT: Your primary scope is to provide a brief SUMMARY of the requested information, and ALWAYS include a raw HTML button link (e.g. <a href=\"URL\" class=\"wbai-chatbot-btn\" target=\"_blank\">Read Full Info</a>) to direct the user to the relevant page where they can find the full details, if a URL is available in the context.\n"
                       . "CRITICAL RULE: DO NOT provide a button link to a page if the URL exactly matches or corresponds to the \"Visitor's Current Page URL\" provided in the context below. The user is already on that page, so linking to it is redundant.\n"
                       . "IMPORTANT LANGUAGE RULE: If the user is speaking a language that differs from the current page language, or if they explicitly ask for a page in a specific language, ALWAYS use the `wpml_get_translated_url` tool to fetch the correct localized link before providing it.\n\n";
                       
        if (class_exists('WooCommerce') && get_option('wbai_chatbot_woocommerce', 1)) {
            $system_prompt .= "WOOCOMMERCE RULE: If the user asks for a product, you MUST try to find the best match using the `wc_search_products` tool. If the matched product is a 'variable' product, you MUST ask the user which variant they need by showing them the available variations BEFORE adding it to the cart. Use `wc_add_to_cart` to add the chosen product/variation to the cart.\n\n";
        }
        
        $system_prompt .= "CRITICAL TOOL RULE: You MUST ONLY call ONE tool per response! DO NOT execute multiple tools in parallel in a single response. If multiple actions are needed, execute the first one, wait for the response, and then execute the next. Violating this rule will cause a fatal API error.\n\n";
        
        $system_prompt .= $site_info . $rag_context;

        $custom_context_setting = get_option('wbai_chatbot_custom_context', '');
        if (!empty($custom_context_setting)) {
            $system_prompt .= "Custom Site Instructions:\n" . $custom_context_setting . "\n\n";
        }

        $user_msg_content = $prompt;
        
        if ($is_first_message && get_option('wbai_chatbot_full_rag_on_first', 0) && !empty($rag_context)) {
            $user_msg_content = "Please learn this initial context about the website:\n\n" . $rag_context . "\n\nUser Request: " . $prompt;
            // Clear it from the system prompt so it's not duplicated on every request
            $system_prompt = str_replace($rag_context, "", $system_prompt);
        }

        $messages[] = new \WordPress\AiClient\Messages\DTO\UserMessage([
            new \WordPress\AiClient\Messages\DTO\MessagePart($user_msg_content)
        ]);

        // Trim History to optimize tokens, but always keep the first message if RAG is injected there
        $history_limit = absint(get_option('wbai_chatbot_history_limit', 10));
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
        
        $tools = [];
        if (class_exists('SitePress')) {
            $tools[] = new \WordPress\AiClient\Tools\DTO\FunctionDeclaration(
                'wpml_get_translated_url',
                'Get the translated URL of a page or product in a specific language. Use this to provide links in the user\'s requested language.',
                ['type' => 'object', 'properties' => ['url' => ['type' => 'string', 'description' => 'The original URL'], 'language_code' => ['type' => 'string', 'description' => 'The 2-letter language code (e.g. it, en, fr)']], 'required' => ['url', 'language_code']]
            );
        }
        
        if (class_exists('WooCommerce') && get_option('wbai_chatbot_woocommerce', 1)) {
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
            $tools[] = new \WordPress\AiClient\Tools\DTO\FunctionDeclaration(
                'wc_search_products',
                'Search for WooCommerce products by name or keyword. Returns product ID, name, price, type, and available variations if it is a variable product.',
                ['type' => 'object', 'properties' => ['query' => ['type' => 'string', 'description' => 'The search query for the product.']], 'required' => ['query']]
            );
        }

        $tools[] = new \WordPress\AiClient\Tools\DTO\FunctionDeclaration(
            'search_site_content',
            'Search the website for pages, posts, or products matching a query.',
            ['type' => 'object', 'properties' => ['query' => ['type' => 'string', 'description' => 'The search query string.']], 'required' => ['query']]
        );

        for ($i = 0; $i < count($models_to_try); $i++) {
            list($tryProvider, $tryModel) = explode('|', $models_to_try[$i]);
            
            try {
                $max_iterations = 3;
                $loop_messages = $messages;
                $display_text = "";
                
                while ($max_iterations > 0) {
                    $ai_query = \WordPress\AiClient\AiClient::prompt($loop_messages);
                    $ai_query->usingModelPreference([$tryProvider, $tryModel]);
                    $ai_query->usingSystemInstruction($system_prompt);
                    
                    if (!empty($tools)) {
                        $ai_query->usingFunctionDeclarations(...$tools);
                    }

                    $result = $ai_query->generateResult();
                    $response_message = $result->toMessage();
                    $loop_messages[] = $response_message;
                    
                    $has_tools = false;
                    $tool_responses = [];
                    foreach ($response_message->getParts() as $part) {
                        if ($part->getFunctionCall() !== null) {
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
                                        $tool_result = ['success' => true, 'message' => 'Product added to cart successfully.'];
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
                                        $products = wc_get_products([
                                            's' => $q,
                                            'status' => 'publish',
                                            'limit' => 5,
                                        ]);
                                        $p_data = [];
                                        foreach ($products as $p) {
                                            $item = [
                                                'id' => $p->get_id(),
                                                'name' => $p->get_name(),
                                                'type' => $p->get_type(),
                                                'price' => $p->get_price(),
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
                                        $tool_result = ['products' => $p_data];
                                    } else {
                                        $tool_result = ['error' => 'Query is required.'];
                                    }
                                }
                            }
                            
                            if ($name === 'search_site_content') {
                                $q = isset($args['query']) ? sanitize_text_field($args['query']) : '';
                                if (!empty($q)) {
                                    $search_query = new \WP_Query([
                                        's' => $q,
                                        'post_type' => ['post', 'page', 'product'],
                                        'post_status' => 'publish',
                                        'posts_per_page' => 5,
                                    ]);
                                    $search_results = [];
                                    if ($search_query->have_posts()) {
                                        foreach ($search_query->posts as $p) {
                                            $search_results[] = [
                                                'title' => $p->post_title,
                                                'type' => $p->post_type,
                                                'url' => get_permalink($p->ID),
                                                'snippet' => wp_trim_words(strip_shortcodes(strip_tags($p->post_content)), 100)
                                            ];
                                        }
                                    }
                                    $tool_result = ['results' => $search_results];
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
                            
                            $tool_responses[] = new \WordPress\AiClient\Messages\DTO\MessagePart(
                                new \WordPress\AiClient\Tools\DTO\FunctionResponse($fc->getId(), $name, (object)$tool_result)
                            );
                        }
                    }
                    
                    if ($has_tools) {
                        $loop_messages[] = new \WordPress\AiClient\Messages\DTO\UserMessage($tool_responses);
                        $max_iterations--;
                        continue;
                    } else {
                        // We have the final text!
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
                        
                        // Save conversation
                        set_transient($chat_id, serialize($loop_messages), 3600);
                        
                        if (get_option('wbai_chatbot_track_sessions', 0)) {
                            // Find the last generated tool info if any to append to AI response
                            $tool_info = '';
                            foreach ($loop_messages as $m) {
                                if ($m->getParts()[0]->getFunctionCall() !== null) {
                                    $tool_info .= "\n*(Used Tool: " . $m->getParts()[0]->getFunctionCall()->getName() . ")*";
                                }
                            }

                            $author_name = $user_id ? $current_user->display_name : ($stored_name ?: 'Visitor');
                            $author_email = $user_id ? $current_user->user_email : $stored_email;

                            // Fallback to fetch user by email if REST API auth drops the user_id
                            if (!$user_id && !empty($author_email)) {
                                $matched_user = get_user_by('email', $author_email);
                                if ($matched_user) {
                                    $user_id = $matched_user->ID;
                                    if ($author_name === 'Visitor') {
                                        $author_name = $matched_user->display_name;
                                    }
                                }
                            }

                            $request_post_id = absint($request->get_param('current_post_id'));

                            $user_comment_id = wp_insert_comment([
                                'comment_post_ID' => $request_post_id,
                                'comment_author' => wp_slash($author_name),
                                'comment_author_email' => $author_email,
                                'user_id' => $user_id,
                                'comment_content' => wp_slash($prompt),
                                'comment_type' => 'wbai_chat',
                                'comment_approved' => 1
                            ]);
                            if ($user_comment_id) {
                                update_comment_meta($user_comment_id, 'wbai_session_id', $session_id);
                                update_comment_meta($user_comment_id, 'wbai_chat_log', 1);
                            }
                            
                            $ai_comment_id = wp_insert_comment([
                                'comment_post_ID' => $request_post_id,
                                'comment_author' => 'Wizard AI',
                                'comment_content' => wp_slash($display_text . $tool_info),
                                'comment_type' => 'wbai_chat',
                                'comment_approved' => 1
                            ]);
                            if ($ai_comment_id) {
                                update_comment_meta($ai_comment_id, 'wbai_session_id', $session_id);
                                update_comment_meta($ai_comment_id, 'wbai_chat_log', 1);
                            }
                        }
                        
                        break;
                    }
                }

                remove_filter('http_request_timeout', $timeout_filter, 999);

                return rest_ensure_response([
                    'success' => true,
                    'reply' => $display_text,
                    'session_id' => $session_id
                ]);
            } catch (\Exception $e) {
                $last_exception = $e;
                continue; // Try next model
            }
        }
        
        remove_filter('http_request_timeout', $timeout_filter, 999);
        return new \WP_Error('ai_error', $last_exception ? $last_exception->getMessage() : 'All models failed.', ['status' => 500]);
    }

    public function wb_ai_chatbot_page_html() {
        if (isset($_POST['wbai_chatbot_settings_nonce']) && wp_verify_nonce($_POST['wbai_chatbot_settings_nonce'], 'wbai_chatbot_settings')) {
            update_option('wbai_chatbot_enabled', isset($_POST['wbai_chatbot_enabled']) ? 1 : 0);
            update_option('wbai_chatbot_live_mode', isset($_POST['wbai_chatbot_live_mode']) ? 1 : 0);
            update_option('wbai_chatbot_icon', sanitize_text_field($_POST['wbai_chatbot_icon']));
            update_option('wbai_chatbot_color', sanitize_hex_color($_POST['wbai_chatbot_color']));
            update_option('wbai_chatbot_position', sanitize_text_field($_POST['wbai_chatbot_position']));
            
            $name = sanitize_text_field($_POST['wbai_chatbot_name']);
            update_option('wbai_chatbot_name', $name);
            do_action('wpml_register_single_string', 'wizard-ai', 'chatbot_name', $name);

            $greeting = sanitize_textarea_field($_POST['wbai_chatbot_greeting']);
            update_option('wbai_chatbot_greeting', $greeting);
            do_action('wpml_register_single_string', 'wizard-ai', 'chatbot_greeting', $greeting);

            $contact_msg = sanitize_textarea_field($_POST['wbai_chatbot_contact_msg']);
            update_option('wbai_chatbot_contact_msg', $contact_msg);
            do_action('wpml_register_single_string', 'wizard-ai', 'chatbot_contact_msg', $contact_msg);
            
            $custom_context = sanitize_textarea_field($_POST['wbai_chatbot_custom_context']);
            update_option('wbai_chatbot_custom_context', $custom_context);
            
            update_option('wbai_chatbot_model', sanitize_text_field($_POST['wbai_chatbot_model']));
            
            update_option('wbai_chatbot_auto_fallback', isset($_POST['wbai_chatbot_auto_fallback']) ? 1 : 0);
            update_option('wbai_chatbot_use_rag', isset($_POST['wbai_chatbot_use_rag']) ? 1 : 0);
            update_option('wbai_chatbot_full_rag_on_first', isset($_POST['wbai_chatbot_full_rag_on_first']) ? 1 : 0);
            update_option('wbai_chatbot_rag_first_limit', absint($_POST['wbai_chatbot_rag_first_limit']));
            update_option('wbai_chatbot_rag_search_limit', absint($_POST['wbai_chatbot_rag_search_limit']));
            update_option('wbai_chatbot_history_limit', absint($_POST['wbai_chatbot_history_limit']));
            update_option('wbai_chatbot_woocommerce', isset($_POST['wbai_chatbot_woocommerce']) ? 1 : 0);
            $fallback_models = isset($_POST['wbai_chatbot_fallback_models']) && is_array($_POST['wbai_chatbot_fallback_models']) 
                ? array_map('sanitize_text_field', $_POST['wbai_chatbot_fallback_models']) : [];
            update_option('wbai_chatbot_fallback_models', $fallback_models);
            
            $track = isset($_POST['wbai_chatbot_track_sessions']) ? 1 : 0;
            update_option('wbai_chatbot_track_sessions', $track);

            $gdpr_text = wp_unslash($_POST['wbai_chatbot_gdpr_text'] ?? '');
            update_option('wbai_chatbot_gdpr_text', $gdpr_text);
            do_action('wpml_register_single_string', 'wizard-ai', 'chatbot_gdpr_text', $gdpr_text);
            
            echo '<div class="updated"><p>' . __('Settings saved.', 'wizard-ai') . '</p></div>';
        }

        $enabled = get_option('wbai_chatbot_enabled', 1);
        $icon = get_option('wbai_chatbot_icon', 'dashicons-format-chat');
        $color = get_option('wbai_chatbot_color', '#2271b1');
        $position = get_option('wbai_chatbot_position', 'bottom-right');
        $live_mode = get_option('wbai_chatbot_live_mode', 0);
        $name = get_option('wbai_chatbot_name', '');
        $greeting = get_option('wbai_chatbot_greeting', 'Hello! How can I help you today?');
        $contact_msg = get_option('wbai_chatbot_contact_msg', '');
        $custom_context = get_option('wbai_chatbot_custom_context', '');
        $selected_model = get_option('wbai_chatbot_model', '');
        $auto_fallback = get_option('wbai_chatbot_auto_fallback', 0);
        $saved_fallback_models = get_option('wbai_chatbot_fallback_models', []);

        $upload_dir = wp_upload_dir();
        $rag_db_path = $upload_dir['basedir'] . '/wbai/rag.sqlite';
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
            <form method="post" action="" id="wbai_chatbot_settings_form">
                <?php wp_nonce_field('wbai_chatbot_settings', 'wbai_chatbot_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Chatbot', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wbai_chatbot_enabled" value="1" <?php checked($enabled, 1); ?>>
                                <?php esc_html_e('Show chatbot on frontend', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Live Mode', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wbai_chatbot_live_mode" value="1" <?php checked($live_mode, 1); ?>>
                                <?php esc_html_e('Adds a microphone button for Speech-to-Text and automatically reads AI responses using Text-to-Speech.', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('AI Model', 'wizard-ai'); ?></th>
                        <td>
                            <select name="wbai_chatbot_model" style="min-width: 300px; margin-bottom: 5px;">
                                <option value=""><?php esc_html_e('Default (Auto-select)', 'wizard-ai'); ?></option>
                                <?php foreach ($models as $val => $label): ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($selected_model, $val); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <br>
                            <a href="#" id="wbai-refresh-models" style="font-size: 12px; text-decoration: none;"><span class="dashicons dashicons-update" style="font-size: 14px; line-height: 1.2;"></span> <?php esc_html_e('Refresh Models List', 'wizard-ai'); ?></a>
                            <script>
                            document.getElementById('wbai-refresh-models').addEventListener('click', function(e) {
                                e.preventDefault();
                                const btn = this;
                                btn.innerHTML = '<span class="dashicons dashicons-update-alt" style="font-size: 14px; line-height: 1.2;"></span> Refreshing...';
                                fetch('<?php echo esc_url(rest_url('wizard-blocks/v1/ai-models?refresh=1')); ?>', {
                                    method: 'GET',
                                    headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' }
                                }).then(() => {
                                    window.location.reload();
                                }).catch(() => {
                                    btn.innerHTML = 'Error refreshing';
                                });
                            });
                            </script>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto-Fallback', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wbai_chatbot_auto_fallback" value="1" <?php checked($auto_fallback, 1); ?>>
                                <?php esc_html_e('Automatically switch to another model if the selected model fails', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Trusted Fallback Models', 'wizard-ai'); ?></th>
                        <td>
                            <p class="description" style="margin-bottom:10px;"><?php esc_html_e('Select which models are allowed to be used as fallbacks. If none are selected, all available models will be used.', 'wizard-ai'); ?></p>
                            <div style="display: flex; gap: 10px; align-items: flex-start;">
                                <select name="wbai_chatbot_fallback_models[]" multiple style="min-width: 300px; height: 150px;">
                                    <?php foreach ($models as $val => $label): ?>
                                        <option value="<?php echo esc_attr($val); ?>" <?php echo in_array($val, $saved_fallback_models) ? 'selected' : ''; ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div style="display: flex; flex-direction: column; gap: 5px;">
                                    <button type="button" class="button" id="wbai_export_btn" title="<?php esc_attr_e('Export Models Configuration', 'wizard-ai'); ?>" style="padding: 0 5px;">
                                        <span class="dashicons dashicons-download" style="margin-top: 4px; height: 20px; width: 20px;"></span>
                                    </button>
                                    <button type="button" class="button" id="wbai_import_btn" title="<?php esc_attr_e('Import Models Configuration', 'wizard-ai'); ?>" style="padding: 0 5px;">
                                        <span class="dashicons dashicons-upload" style="margin-top: 4px; height: 20px; width: 20px;"></span>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php if ($rag_db_exists): ?>
                    <tr>
                        <th colspan="2" style="padding-top: 20px;">
                            <hr style="margin-top: 0; margin-bottom: 20px;">
                            <h2 style="margin: 0; font-size: 1.2em;"><?php esc_html_e('RAG Settings (Website Knowledge Base)', 'wizard-ai'); ?></h2>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Use RAG', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <?php $default_rag = get_option('wbai_rag_cron_enabled', 'no') === 'yes' ? 1 : 1; ?>
                                <input type="checkbox" name="wbai_chatbot_use_rag" id="wbai_chatbot_use_rag" value="1" <?php checked(get_option('wbai_chatbot_use_rag', $default_rag), 1); ?>>
                                <?php esc_html_e('Pass website Knowledge Base context to the Chatbot to improve answers', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr class="wbai-rag-dependent">
                        <th scope="row"><?php esc_html_e('Full RAG on First Message', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wbai_chatbot_full_rag_on_first" value="1" <?php checked(get_option('wbai_chatbot_full_rag_on_first', 0), 1); ?>>
                                <?php esc_html_e('Inject the entire RAG knowledge base into the very first chat message so the AI "learns" all the site content upfront. (Warning: uses more tokens on the first request)', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr class="wbai-rag-dependent">
                        <th scope="row"><?php esc_html_e('Token Optimization: RAG Limits', 'wizard-ai'); ?></th>
                        <td>
                            <div style="margin-bottom: 5px;">
                                <input type="number" name="wbai_chatbot_rag_first_limit" value="<?php echo esc_attr(get_option('wbai_chatbot_rag_first_limit', 30)); ?>" style="width: 80px;" min="1" max="500">
                                <label><?php esc_html_e('Max RAG records to inject on First Message (if enabled)', 'wizard-ai'); ?></label>
                            </div>
                            <div>
                                <input type="number" name="wbai_chatbot_rag_search_limit" value="<?php echo esc_attr(get_option('wbai_chatbot_rag_search_limit', 5)); ?>" style="width: 80px;" min="1" max="50">
                                <label><?php esc_html_e('Max RAG records to fetch on subsequent Search Queries', 'wizard-ai'); ?></label>
                            </div>
                            <p class="description"><?php esc_html_e('Lower values drastically reduce Token usage, but the AI will have less context.', 'wizard-ai'); ?></p>
                        </td>
                    </tr>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const ragToggle = document.getElementById('wbai_chatbot_use_rag');
                            const dependentRows = document.querySelectorAll('.wbai-rag-dependent');
                            
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
                    <tr>
                        <th colspan="2" style="padding-top: 20px;">
                            <hr style="margin-top: 0; margin-bottom: 20px;">
                            <h2 style="margin: 0; font-size: 1.2em;"><?php esc_html_e('Advanced Chatbot Options', 'wizard-ai'); ?></h2>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Token Optimization: Chat History Limit', 'wizard-ai'); ?></th>
                        <td>
                            <input type="number" name="wbai_chatbot_history_limit" value="<?php echo esc_attr(get_option('wbai_chatbot_history_limit', 10)); ?>" style="width: 80px;" min="0">
                            <label><?php esc_html_e('Max previous messages to keep in memory', 'wizard-ai'); ?></label>
                            <p class="description"><?php esc_html_e('Trims the chat history to the last N messages to avoid the payload growing infinitely. Set to 0 to disable trimming. (Note: The first message is always kept if RAG is injected into it).', 'wizard-ai'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Track Sessions', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wbai_chatbot_track_sessions" value="1" <?php checked(get_option('wbai_chatbot_track_sessions', 0), 1); ?>>
                                <?php esc_html_e('Log conversations in the database so you can analyze them in the Chatbot Logs page', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('GDPR Notice Text', 'wizard-ai'); ?></th>
                        <td>
                            <textarea name="wbai_chatbot_gdpr_text" rows="3" style="width: 100%; max-width: 400px;"><?php echo esc_textarea(get_option('wbai_chatbot_gdpr_text', 'By chatting, you agree to our processing of conversation logs to assist with your request. See our Privacy for your data rights.')); ?></textarea>
                            <p class="description"><?php esc_html_e('Enter the text to display in the GDPR consent notice.', 'wizard-ai'); ?></p>
                        </td>
                    </tr>
                    <?php if (class_exists('WooCommerce')): ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('WooCommerce Integration', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wbai_chatbot_woocommerce" value="1" <?php checked(get_option('wbai_chatbot_woocommerce', 1), 1); ?>>
                                <?php esc_html_e('Enable WooCommerce tools (Add to cart, apply coupon, check orders) for the Chatbot', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Chatbot Icon', 'wizard-ai'); ?></th>
                        <td>
                            <?php
                            $dashicons = [
                                'dashicons-format-chat', 'dashicons-smiley', 'dashicons-admin-users', 'dashicons-businessman', 'dashicons-testimonial', 'dashicons-megaphone', 'dashicons-microphone', 'dashicons-info', 'dashicons-editor-help', 'dashicons-lightbulb', 'dashicons-thumbs-up', 'dashicons-heart', 'dashicons-star-filled', 'dashicons-yes', 'dashicons-warning', 'dashicons-sos', 'dashicons-lifesaver', 'dashicons-visibility', 'dashicons-welcome-learn-more', 'dashicons-admin-site', 'dashicons-admin-generic', 'dashicons-admin-customizer', 'dashicons-admin-comments', 'dashicons-admin-network', 'dashicons-welcome-widgets-menus', 'dashicons-welcome-comments', 'dashicons-groups', 'dashicons-store', 'dashicons-format-status', 'dashicons-format-quote', 'dashicons-carrot', 'dashicons-art', 'dashicons-buddicons-buddypress-logo', 'dashicons-buddicons-groups', 'dashicons-buddicons-topics', 'dashicons-buddicons-pm', 'dashicons-email-alt', 'dashicons-email-alt2', 'dashicons-whatsapp', 'dashicons-facebook', 'dashicons-twitter'
                            ];
                            ?>
                            <div class="wbai-icon-selector" style="max-height: 150px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; display: flex; flex-wrap: wrap; gap: 10px; max-width: 400px; background: #fff;">
                                <?php foreach($dashicons as $d): ?>
                                    <label style="cursor: pointer; padding: 5px; border: 1px solid <?php echo $icon === $d ? '#2271b1' : 'transparent'; ?>; border-radius: 4px; background: <?php echo $icon === $d ? '#e3f2fd' : 'transparent'; ?>;" onclick="document.querySelectorAll('.wbai-icon-selector label').forEach(l => {l.style.borderColor='transparent'; l.style.background='transparent';}); this.style.borderColor='#2271b1'; this.style.background='#e3f2fd'; document.getElementById('wbai_chatbot_icon_input').value='<?php echo esc_attr($d); ?>';">
                                        <span class="dashicons <?php echo esc_attr($d); ?>" style="font-size: 24px; width: 24px; height: 24px;"></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="wbai_chatbot_icon" id="wbai_chatbot_icon_input" value="<?php echo esc_attr($icon); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Main Color', 'wizard-ai'); ?></th>
                        <td>
                            <input type="color" name="wbai_chatbot_color" value="<?php echo esc_attr($color); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Position', 'wizard-ai'); ?></th>
                        <td>
                            <select name="wbai_chatbot_position">
                                <option value="bottom-right" <?php selected($position, 'bottom-right'); ?>><?php esc_html_e('Bottom Right', 'wizard-ai'); ?></option>
                                <option value="bottom-left" <?php selected($position, 'bottom-left'); ?>><?php esc_html_e('Bottom Left', 'wizard-ai'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Chatbot Name', 'wizard-ai'); ?></th>
                        <td>
                            <input type="text" name="wbai_chatbot_name" value="<?php echo esc_attr($name); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Initial Greeting', 'wizard-ai'); ?></th>
                        <td>
                            <textarea name="wbai_chatbot_greeting" rows="4" class="large-text"><?php echo esc_textarea($greeting); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Contact Prompt (Optional)', 'wizard-ai'); ?></th>
                        <td>
                            <textarea name="wbai_chatbot_contact_msg" rows="2" class="large-text" placeholder="<?php esc_attr_e('e.g. Do you want to be contacted by us? Leave your email.', 'wizard-ai'); ?>"><?php echo esc_textarea($contact_msg); ?></textarea>
                            <p class="description"><?php esc_html_e('This message will be appended to the AI\'s responses to encourage visitors to leave their email address. It will automatically hide once an email is provided or if the user is logged in.', 'wizard-ai'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Custom Context (Optional)', 'wizard-ai'); ?></th>
                        <td>
                            <textarea name="wbai_chatbot_custom_context" rows="4" class="large-text" placeholder="<?php esc_attr_e('Provide custom instructions or context for the chatbot about your site...', 'wizard-ai'); ?>"><?php echo esc_textarea($custom_context); ?></textarea>
                            <p class="description"><?php esc_html_e('These instructions will be appended to the AI\'s system prompt. Useful for setting specific rules, brand tone, or custom context.', 'wizard-ai'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <?php submit_button('', 'primary', 'submit', false); ?>
                </p>
                
                <input type="file" id="wbai_import_file" style="display:none" accept=".json">

                <script>
                document.getElementById('wbai_export_btn').addEventListener('click', function() {
                    const data = <?php echo $export_json; ?>;
                    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'wizard-ai-chatbot-config.json';
                    a.click();
                    URL.revokeObjectURL(url);
                });
                document.getElementById('wbai_import_btn').addEventListener('click', function() {
                    document.getElementById('wbai_import_file').click();
                });
                document.getElementById('wbai_import_file').addEventListener('change', function(e) {
                    if (!e.target.files.length) return;
                    const reader = new FileReader();
                    reader.onload = function(evt) {
                        try {
                            const config = JSON.parse(evt.target.result);
                            
                            if (config.model) {
                                const modelSelect = document.querySelector('select[name="wbai_chatbot_model"]');
                                if (modelSelect) modelSelect.value = config.model;
                            }
                            
                            if (config.fallback_models && Array.isArray(config.fallback_models)) {
                                const fallbackSelect = document.querySelector('select[name="wbai_chatbot_fallback_models[]"]');
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

    public function wb_ai_chatbot_logs_page_html() {
        global $wpdb;

        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';

        if (in_array($action, ['delete_message', 'delete_session'])) {
            check_admin_referer('wbai_delete_log');
            if ($action === 'delete_message' && !empty($_GET['comment_id'])) {
                wp_delete_comment(intval($_GET['comment_id']), true);
                echo '<script>window.location.replace("' . admin_url('admin.php?page=wizard-ai-chatbot-logs&action=view&session_id=' . urlencode($session_id)) . '");</script>';
                exit;
            } elseif ($action === 'delete_session' && !empty($session_id)) {
                // Fetch comment IDs directly via SQL to bypass WPML filtering which hides comments with post_ID = 0
                $comment_ids = $wpdb->get_col($wpdb->prepare("
                    SELECT comment_id 
                    FROM {$wpdb->commentmeta} 
                    WHERE meta_key = 'wbai_session_id' AND meta_value = %s
                ", $session_id));
                
                foreach ($comment_ids as $c_id) {
                    wp_delete_comment(intval($c_id), true);
                }
                echo '<script>window.location.replace("' . admin_url('admin.php?page=wizard-ai-chatbot-logs') . '");</script>';
                exit;
            }
        }

        echo '<div class="wrap">';
        if ($action === 'view' && !empty($session_id)) {
            // View Single Thread
            echo '<h1 class="wp-heading-inline">' . esc_html__('Chat Session', 'wizard-ai') . '</h1>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=wizard-ai-chatbot-logs')) . '" class="page-title-action">' . esc_html__('Back to Logs', 'wizard-ai') . '</a>';
            echo '<hr class="wp-header-end">';
            
            $comments = get_comments([
                'type' => 'wbai_chat',
                'meta_key' => 'wbai_session_id',
                'meta_value' => $session_id,
                'orderby' => 'comment_date_gmt',
                'order' => 'ASC',
                'status' => 'all'
            ]);

            if (empty($comments)) {
                echo '<p>' . esc_html__('No messages found for this session.', 'wizard-ai') . '</p>';
            } else {
                echo '<div style="max-width: 800px; background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px; margin-top: 20px;">';
                $email = '';
                $session_user_id = 0;
                foreach ($comments as $c) {
                    if (!empty($c->comment_author_email) && empty($email)) {
                        $email = $c->comment_author_email;
                    }
                    if (!empty($c->user_id) && $c->user_id > 0 && empty($session_user_id)) {
                        $session_user_id = $c->user_id;
                    }
                }
                echo '<h3>' . sprintf(esc_html__('Session ID: %s', 'wizard-ai'), esc_html($session_id)) . '</h3>';
                
                $del_session_url = wp_nonce_url(admin_url('admin.php?page=wizard-ai-chatbot-logs&action=delete_session&session_id=' . urlencode($session_id)), 'wbai_delete_log');
                echo '<p><a href="' . esc_url($del_session_url) . '" class="button button-link-delete" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this entire session?', 'wizard-ai')) . '\');" style="color: #b32d2e;">' . esc_html__('Delete Session', 'wizard-ai') . '</a></p>';
                
                if ($session_user_id > 0) {
                    $user_link = get_edit_user_link($session_user_id);
                    $user_obj = get_userdata($session_user_id);
                    $user_name = $user_obj ? $user_obj->display_name : __('User', 'wizard-ai');
                    echo '<p><strong>' . esc_html__('User:', 'wizard-ai') . '</strong> <a href="' . esc_url($user_link) . '">' . esc_html($user_name) . '</a></p>';
                }
                if ($email) {
                    echo '<p><strong>' . esc_html__('User Email:', 'wizard-ai') . '</strong> ' . esc_html($email) . '</p>';
                }
                echo '<hr>';

                foreach ($comments as $c) {
                    $is_ai = $c->comment_author === 'Wizard AI';
                    $bg = $is_ai ? '#f1f1f1' : '#e3f2fd';
                    $align = $is_ai ? 'left' : 'right';
                    $margin = $is_ai ? '0 50px 15px 0' : '0 0 15px 50px';
                    
                    $author_name_html = esc_html($c->comment_author);
                    if (!$is_ai && !empty($c->user_id) && $c->user_id > 0) {
                        $user_link = get_edit_user_link($c->user_id);
                        $author_name_html = '<a href="' . esc_url($user_link) . '">' . esc_html($c->comment_author) . '</a>';
                    }
                    
                    $del_msg_url = wp_nonce_url(admin_url('admin.php?page=wizard-ai-chatbot-logs&action=delete_message&comment_id=' . $c->comment_ID . '&session_id=' . urlencode($session_id)), 'wbai_delete_log');
                    $edit_msg_url = get_edit_comment_link($c->comment_ID);
                    
                    echo '<div style="background: ' . esc_attr($bg) . '; padding: 15px; border-radius: 8px; margin: ' . esc_attr($margin) . '; text-align: left; position: relative;">';
                    echo '<div style="position: absolute; top: 10px; right: 10px;">';
                    echo '<a href="' . esc_url($edit_msg_url) . '" style="color: #2271b1; text-decoration: none; margin-right: 8px;" title="' . esc_attr__('Edit Message natively', 'wizard-ai') . '"><span class="dashicons dashicons-edit"></span></a>';
                    echo '<a href="' . esc_url($del_msg_url) . '" onclick="return confirm(\'' . esc_js(__('Delete this message?', 'wizard-ai')) . '\');" style="color: #b32d2e; text-decoration: none;" title="' . esc_attr__('Delete Message', 'wizard-ai') . '"><span class="dashicons dashicons-trash"></span></a>';
                    echo '</div>';
                    echo '<strong>' . $author_name_html . '</strong> <span style="font-size: 11px; color: #888;">(' . esc_html($c->comment_date) . ')</span>';
                    echo '<div style="margin-top: 8px;">' . wp_kses_post($c->comment_content) . '</div>';
                    echo '</div>';
                }
                echo '</div>';
            }
        } else {
            // List Sessions
            echo '<h1 class="wp-heading-inline">' . esc_html__('Chatbot Logs', 'wizard-ai') . '</h1>';
            echo '<hr class="wp-header-end">';
            
            $per_page = 20;
            $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
            $offset = ($paged - 1) * $per_page;

            // Get unique session IDs using SQL since WP_Comment_Query doesn't support GROUP BY meta_value natively
            $query = "
                SELECT m.meta_value AS session_id, 
                       MAX(c.comment_date) AS last_activity, 
                       MAX(CASE WHEN c.comment_author != 'Wizard AI' THEN c.comment_author_email ELSE NULL END) AS comment_author_email, 
                       MAX(CASE WHEN c.comment_author != 'Wizard AI' THEN c.comment_author ELSE NULL END) AS comment_author,
                       MAX(CASE WHEN c.comment_author != 'Wizard AI' THEN c.user_id ELSE 0 END) AS user_id
                FROM {$wpdb->comments} c 
                INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id 
                WHERE c.comment_type = 'wbai_chat' AND m.meta_key = 'wbai_session_id' 
                GROUP BY m.meta_value 
                HAVING MAX(CASE WHEN c.comment_author != 'Wizard AI' THEN 1 ELSE 0 END) = 1 AND m.meta_value != ''
                ORDER BY last_activity DESC 
                LIMIT %d OFFSET %d
            ";
            $sessions = $wpdb->get_results($wpdb->prepare($query, $per_page, $offset));

            $total_query = "
                SELECT COUNT(*) FROM (
                    SELECT m.meta_value 
                    FROM {$wpdb->comments} c 
                    INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id 
                    WHERE c.comment_type = 'wbai_chat' AND m.meta_key = 'wbai_session_id'
                    GROUP BY m.meta_value
                    HAVING MAX(CASE WHEN c.comment_author != 'Wizard AI' THEN 1 ELSE 0 END) = 1 AND m.meta_value != ''
                ) AS count_table
            ";
            $total_sessions = $wpdb->get_var($total_query);
            $total_pages = ceil($total_sessions / $per_page);

            if (empty($sessions)) {
                echo '<p>' . esc_html__('No chat sessions found.', 'wizard-ai') . '</p>';
            } else {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr>';
                echo '<th>' . esc_html__('Session ID', 'wizard-ai') . '</th>';
                echo '<th>' . esc_html__('User', 'wizard-ai') . '</th>';
                echo '<th>' . esc_html__('Email', 'wizard-ai') . '</th>';
                echo '<th>' . esc_html__('Last Activity', 'wizard-ai') . '</th>';
                echo '<th>' . esc_html__('Actions', 'wizard-ai') . '</th>';
                echo '</tr></thead>';
                echo '<tbody>';
                foreach ($sessions as $s) {
                    $view_url = admin_url('admin.php?page=wizard-ai-chatbot-logs&action=view&session_id=' . urlencode($s->session_id));
                    
                    $display_name = !empty($s->comment_author) ? $s->comment_author : 'Visitor';
                    
                    if (!empty($s->user_id) && $s->user_id > 0) {
                        $user_link = get_edit_user_link($s->user_id);
                        $display_name_html = '<a href="' . esc_url($user_link) . '">' . esc_html($display_name) . '</a>';
                    } else {
                        $display_name_html = esc_html($display_name);
                    }
                    
                    echo '<tr>';
                    echo '<td><strong>' . esc_html($s->session_id) . '</strong></td>';
                    echo '<td>' . $display_name_html . '</td>';
                    echo '<td>' . esc_html($s->comment_author_email) . '</td>';
                    echo '<td>' . esc_html($s->last_activity) . '</td>';
                    
                    $del_session_url = wp_nonce_url(admin_url('admin.php?page=wizard-ai-chatbot-logs&action=delete_session&session_id=' . urlencode($s->session_id)), 'wbai_delete_log');
                    
                    echo '<td>';
                    echo '<a href="' . esc_url($view_url) . '" class="button dashicons-before dashicons-visibility" style="margin-right: 5px;" title="' . esc_attr__('View Thread', 'wizard-ai') . '"></a>';
                    echo '<a href="' . esc_url($del_session_url) . '" class="button dashicons-before dashicons-trash" style="color: #b32d2e; border-color: #b32d2e;" title="' . esc_attr__('Delete', 'wizard-ai') . '" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this entire session?', 'wizard-ai')) . '\');"></a>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';

                if ($total_pages > 1) {
                    $page_links = paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;', 'wizard-ai'),
                        'next_text' => __('&raquo;', 'wizard-ai'),
                        'total' => $total_pages,
                        'current' => $paged
                    ]);
                    if ($page_links) {
                        echo '<div class="tablenav"><div class="tablenav-pages" style="float:left; margin-top:10px;">' . $page_links . '</div></div>';
                    }
                }
            }
        }
        echo '</div>';
    }

}
