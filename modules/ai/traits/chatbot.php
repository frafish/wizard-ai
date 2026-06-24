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
            'permission_callback' => '__return_true' // Publicly accessible
        ]);
    }

    public function enqueue_chatbot_scripts() {
        if (!get_option('wbai_chatbot_enabled', 1)) return;

        $upload_dir = wp_upload_dir();
        $db_path = $upload_dir['basedir'] . '/wbai/rag_embeddings.sqlite';
        if (!file_exists($db_path)) return;

        wp_enqueue_style('dashicons');
        wp_enqueue_style('wizard-ai-chatbot-style', WIZARD_AI_URL . 'modules/ai/assets/css/chatbot.css', [], '1.0.0');
        wp_enqueue_script('wizard-ai-chatbot-script', WIZARD_AI_URL . 'modules/ai/assets/js/chatbot.js', ['jquery', 'jquery-ui-draggable', 'jquery-ui-resizable'], '1.0.0', true);
        
        wp_localize_script('wizard-ai-chatbot-script', 'wizardAiChatbotData', [
            'rest_url' => esc_url_raw(rest_url('wizard-blocks/v1/chatbot')),
            'nonce' => wp_create_nonce('wp_rest'),
            'debugMode' => (defined('WP_DEBUG') && WP_DEBUG),
            'resetConfirm' => __('Are you sure you want to start a new chat?', 'wizard-ai')
        ]);
    }

    public function render_chatbot() {
        if (!get_option('wbai_chatbot_enabled', 1)) return;

        $upload_dir = wp_upload_dir();
        $db_path = $upload_dir['basedir'] . '/wbai/rag_embeddings.sqlite';
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
        
        $pos_style = $position === 'bottom-left' ? 'left: 20px; right: auto;' : 'right: 20px; left: auto;';
        $live_mode = get_option('wbai_chatbot_live_mode', 0);
        ?>
        <div id="wbai-chatbot" class="wbai-chatbot-closed" style="<?php echo $pos_style; ?>" data-live-mode="<?php echo esc_attr($live_mode ? '1' : '0'); ?>">
            <div id="wbai-chatbot-header" style="background-color: <?php echo $color; ?>;">
                <span class="dashicons <?php echo $icon; ?>"></span>
                <?php if (!empty($chatbot_name)): ?>
                    <span class="wbai-chatbot-title-text"><?php echo $chatbot_name; ?></span>
                <?php endif; ?>
                <button id="wbai-chatbot-reset" title="<?php esc_attr_e('Reset Chat', 'wizard-ai'); ?>" style="background: none; border: none; color: #fff; cursor: pointer; margin-left:auto; margin-right: 5px;"><span class="dashicons dashicons-update-alt"></span></button>
                <button id="wbai-chatbot-toggle" style="background: none; border: none; color: #fff; cursor: pointer;"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
            </div>
            <div id="wbai-chatbot-body">
                <div id="wbai-chatbot-messages">
                    <div class="wbai-chatbot-msg wbai-chatbot-sys"><?php echo $greeting; ?></div>
                </div>
                <?php 
                $contact_msg = get_option('wbai_chatbot_contact_msg', '');
                if (!is_user_logged_in() && !empty($contact_msg)): ?>
                    <div id="wbai-chatbot-email-hint" style="font-size: 12px; padding: 10px; color: #666; text-align: center; border-bottom: 1px solid #eee; background-color: #fafafa;">
                        <div style="margin-bottom: 8px; font-style: italic;"><?php echo esc_html($contact_msg); ?></div>
                        <div style="display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;">
                            <input type="text" id="wbai-chatbot-name-input" placeholder="<?php esc_attr_e('Your Name', 'wizard-ai'); ?>" style="font-size: 11px; padding: 4px; border: 1px solid #ddd; border-radius: 3px; flex: 1; min-width: 80px;">
                            <input type="email" id="wbai-chatbot-email-input" placeholder="<?php esc_attr_e('Your Email', 'wizard-ai'); ?>" style="font-size: 11px; padding: 4px; border: 1px solid #ddd; border-radius: 3px; flex: 1; min-width: 120px;">
                            <button id="wbai-chatbot-email-submit" class="button" style="font-size: 11px; padding: 2px 8px; min-height: 0; line-height: 1.5; background-color: <?php echo esc_attr($color); ?>; color: #fff; border: none; border-radius: 3px; cursor: pointer;"><?php esc_html_e('Send', 'wizard-ai'); ?></button>
                        </div>
                    </div>
                <?php endif; ?>
                <div id="wbai-chatbot-input-area">
                    <textarea id="wbai-chatbot-prompt" placeholder="<?php esc_attr_e('Ask a question...', 'wizard-ai'); ?>"></textarea>
                    <?php if ($live_mode): ?>
                        <button id="wbai-chatbot-mic" class="button" title="<?php esc_attr_e('Speech to Text', 'wizard-ai'); ?>"><span class="dashicons dashicons-microphone"></span></button>
                    <?php endif; ?>
                    <button id="wbai-chatbot-send" class="button button-primary" style="background-color: <?php echo $color; ?>;"><span class="dashicons dashicons-controls-play"></span></button>
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

        // Fetch RAG context, filtering out reserved data
        $rag_context = "";
        if (get_option('wbai_chatbot_use_rag', 1)) {
            $upload_dir = wp_upload_dir();
            $db_path = $upload_dir['basedir'] . '/wbai/rag_embeddings.sqlite';
        if (file_exists($db_path)) {
            try {
                $db = new \PDO('sqlite:' . $db_path);
                $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
                // Simple Keyword Search for RAG
                $clean_prompt = preg_replace('/[^\p{L}\p{N}\s]/u', '', $prompt);
                $keywords = array_filter(explode(' ', $clean_prompt), function($k) { return mb_strlen($k) > 2; });
                $conditions = [];
                foreach ($keywords as $k) {
                    $conditions[] = "text_content LIKE " . $db->quote('%' . $k . '%');
                }
                
                $where = count($conditions) > 0 ? " AND (" . implode(" OR ", $conditions) . ")" : "";
                
                $stmt = $db->query("SELECT text_content, post_type FROM document_embeddings WHERE post_type IN ('post', 'page', 'block', 'knowledgebase', 'product') $where LIMIT 15");
                
                $rows = [];
                if ($stmt) {
                    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                        $rows[] = $row;
                    }
                }
                
                // If keyword search didn't find anything, fallback to general context
                if (empty($rows)) {
                    $stmt = $db->query("SELECT text_content, post_type FROM document_embeddings WHERE post_type IN ('post', 'page', 'block', 'knowledgebase', 'product') LIMIT 5");
                    if ($stmt) {
                        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                            $rows[] = $row;
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
        $site_info = "Website Name: " . get_bloginfo('name') . "\n";
        $site_info .= "Website URL: " . get_site_url() . "\n";
        $site_info .= "Website Description: " . get_bloginfo('description') . "\n\n";

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
                       . "IMPORTANT LANGUAGE RULE: If the user is speaking a language that differs from the current page language, or if they explicitly ask for a page in a specific language, ALWAYS use the `wpml_get_translated_url` tool to fetch the correct localized link before providing it.\n\n" 
                       . $site_info . $rag_context;

        $chat_id = "wbai_chatbot_" . $session_id;
        $messages = [];
        $stored = get_transient($chat_id);
        if ($stored !== false) {
            $messages = unserialize($stored);
        }

        $messages[] = new \WordPress\AiClient\Messages\DTO\UserMessage([
            new \WordPress\AiClient\Messages\DTO\MessagePart($prompt)
        ]);

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
                'Add a specific product to the shopping cart.',
                ['type' => 'object', 'properties' => ['product_id' => ['type' => 'integer', 'description' => 'The ID of the WooCommerce product to add.']], 'required' => ['product_id']]
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
                                    if ($pid > 0 && function_exists('wc_get_product') && wc_get_product($pid)) {
                                        WC()->cart->add_to_cart($pid);
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

                            $user_comment_id = wp_insert_comment([
                                'comment_post_ID' => 0,
                                'comment_author' => wp_slash($author_name),
                                'comment_author_email' => $author_email,
                                'user_id' => $user_id,
                                'comment_content' => wp_slash($prompt),
                                'comment_type' => 'wbai_chat',
                                'comment_approved' => 1
                            ]);
                            if ($user_comment_id) {
                                update_comment_meta($user_comment_id, 'wbai_session_id', $session_id);
                            }
                            
                            $ai_comment_id = wp_insert_comment([
                                'comment_post_ID' => 0,
                                'comment_author' => 'Wizard AI',
                                'comment_content' => wp_slash($display_text . $tool_info),
                                'comment_type' => 'wbai_chat',
                                'comment_approved' => 1
                            ]);
                            if ($ai_comment_id) {
                                update_comment_meta($ai_comment_id, 'wbai_session_id', $session_id);
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
            
            update_option('wbai_chatbot_model', sanitize_text_field($_POST['wbai_chatbot_model']));
            
            update_option('wbai_chatbot_auto_fallback', isset($_POST['wbai_chatbot_auto_fallback']) ? 1 : 0);
            update_option('wbai_chatbot_use_rag', isset($_POST['wbai_chatbot_use_rag']) ? 1 : 0);
            update_option('wbai_chatbot_woocommerce', isset($_POST['wbai_chatbot_woocommerce']) ? 1 : 0);
            $fallback_models = isset($_POST['wbai_chatbot_fallback_models']) && is_array($_POST['wbai_chatbot_fallback_models']) 
                ? array_map('sanitize_text_field', $_POST['wbai_chatbot_fallback_models']) : [];
            update_option('wbai_chatbot_fallback_models', $fallback_models);
            
            $track = isset($_POST['wbai_chatbot_track_sessions']) ? 1 : 0;
            update_option('wbai_chatbot_track_sessions', $track);
            
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
        $selected_model = get_option('wbai_chatbot_model', '');
        $auto_fallback = get_option('wbai_chatbot_auto_fallback', 0);
        $saved_fallback_models = get_option('wbai_chatbot_fallback_models', []);

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
            <h1><?php esc_html_e('Frontend Chatbot Settings', 'wizard-ai'); ?></h1>
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
                    <tr>
                        <th scope="row"><?php esc_html_e('Use RAG', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <?php $default_rag = get_option('wbai_rag_cron_enabled', 'no') === 'yes' ? 1 : 1; ?>
                                <input type="checkbox" name="wbai_chatbot_use_rag" value="1" <?php checked(get_option('wbai_chatbot_use_rag', $default_rag), 1); ?>>
                                <?php esc_html_e('Pass website Knowledge Base context to the Chatbot to improve answers', 'wizard-ai'); ?>
                            </label>
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

}
