<?php
namespace WizardAi\Modules\Block;
if ( ! defined( 'ABSPATH' ) ) exit;
class Block {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_block_routes']);
        if (method_exists($this, 'register_block_hooks')) {
            $this->register_block_hooks();
        }
    }


    
    public function register_block_routes() {
        register_rest_route('wizard-ai/v1', '/process-ai', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_ai_request'],
            'permission_callback' => function () { return current_user_can('edit_posts'); }
        ]);
    }

    public function handle_ai_request(\WP_REST_Request $request) {
        $params = $request->get_json_params();
        $bundle = $params['block_json'] ?? [];
        $prompt = sanitize_text_field($params['prompt'] ?? '');
        $instructions = "You are a senior WordPress and Gutenberg developer. "
        . "You will receive a Gutenberg block bundle JSON. "
        . "Your task is to update it according to the request. "
        . "Rules: "
        . "1) DELTA JSON RESPONSE: DO NOT return the entire bundle. You MUST return ONLY a JSON object containing the specific keys that need to be changed or added. We will automatically merge your response with the existing bundle. "
        . "2) Add comments like // AI:... (for PHP/JS) or /* AI:... */ (for CSS) ONLY inside string values that represent code fields, never as separate keys. "
        . "3) Your RESPONSE MUST BE ONLY a single valid JSON object with no markdown, no backticks, and no extra text. "
        . "4) Use the 'render' key (representing render.php) to generate the HTML output. The 'render' code is executed inside an existing function context and has access to \$attributes, \$content, and \$block. Do NOT wrap the code in a function declaration. Just write the raw PHP logic and echo the HTML output directly. "
        . "5) Use the latest WordPress and Gutenberg best practices. "
        . "6) If you add an attribute with type 'object' or 'array', you must generate/edit the editorScript javascript to manage them. "
        . "7) If there are existing comments inside the code, do NOT touch or change them; leave them intact. "
        . "8) If the input JSON is empty (brand new block), you MUST generate all core keys (title, description, attributes, render, etc). "
        . "9) Do NOT include empty keys or placeholder comments like '// No javascript needed' if a component is unused. Leave those keys out entirely. "
        . "10) If you modify the 'attributes' key, you MUST include ALL existing attributes in your response, otherwise the omitted ones will be deleted.\n";
        
        $instructions .= \WizardAi\Modules\Ai\Ai::instance()->get_ai_skills();
        
        $bundle_context = $bundle;
        // Truncate empty values from context to save tokens
        foreach ($bundle_context as $k => $v) {
            if (empty($v) && $v !== '0' && $v !== 0 && $v !== false) {
                unset($bundle_context[$k]);
            }
        }
        $bundle_text = json_encode($bundle_context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        // WP 7.x
        set_time_limit(300);
            $timeout_filter = function($timeout, $url) {
                return 300;
            };
            add_filter('http_request_timeout', $timeout_filter, 999, 2);

            $messages = [
                new \WordPress\AiClient\Messages\DTO\UserMessage([
                    new \WordPress\AiClient\Messages\DTO\MessagePart("REQUEST:\n" . $prompt . "\n\nGUTENBERG_BLOCK_BUNDLE_JSON:\n" . $bundle_text)
                ])
            ];

            $abilities = function_exists('wp_get_abilities') ? wp_get_abilities() : [];
            $abilities = apply_filters('wizard_ai/abilities', $abilities);
            $abilities = apply_filters('wizard_blocks_ai_abilities', $abilities);
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
                                    $schema[$k] = ['type' => 'string'];
                                } else {
                                    if (is_array($v) && isset($v[0])) {
                                        $schema[$k] = $v[0];
                                    }
                                    $clean_schema($schema[$k]);
                                    if (is_array($schema[$k]) && empty($schema[$k])) {
                                        $schema[$k] = ['type' => 'string'];
                                    }
                                }
                            }
                        }
                    }
                    if (isset($schema['type']) && is_string($schema['type'])) {
                        if (strtolower($schema['type']) === 'array' && !isset($schema['items'])) {
                            $schema['items'] = ['type' => 'string'];
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

            $requested_model = sanitize_text_field($params['model'] ?? '');
            if (!empty($requested_model)) {
                update_user_meta(get_current_user_id(), '_wai_preferred_model', $requested_model);
            }
            
            $ai_text = '';
            for ($i = 0; $i < 10; $i++) {
                $ai_query = \WordPress\AiClient\AiClient::prompt( $messages );
                if (!empty($requested_model)) {
                    $ai_query->usingModelPreference($requested_model);
                }
                $ai_query->usingSystemInstruction( $instructions );
                $ai_query->asJsonResponse();
                
                if (!empty($functions)) {
                    $ai_query->usingFunctionDeclarations(...$functions);
                }
                
                try {
                    $ai = \WizardAi\Modules\Ai\Ai::instance();
                    if (!$ai->check_budget_cap()) {
                        return new \WP_REST_Response([
                            'success' => false,
                            'message' => __('Monthly token budget cap exceeded. Please upgrade your budget or wait until next month.', 'wizard-ai')
                        ], 403);
                    }
                    $result = $ai_query->generateResult();
                    $response_message = $result->toMessage();
                    $messages[] = $response_message;
                    
                    $has_any_call = false;
                    foreach ($response_message->getParts() as $part) {
                        if ($part->getFunctionCall() !== null) {
                            $has_any_call = true;
                            // Log the tool execution
                            $fc = $part->getFunctionCall();
                            $ai_logger = \WizardAi\Modules\Ai\Ai::instance();
                            if (method_exists($ai_logger, 'log_audit_event')) {
                                $ai_logger->log_audit_event('editor_agent', $fc->getName(), $fc->getArgs(), 'success');
                            }
                            break;
                        }
                    }
                    if ($resolver && $has_any_call) {
                        $response = $resolver->execute_abilities($response_message);
                        $messages[] = $response;
                        continue;
                    }
                    
                    $ai_text = trim($result->toText());
                    
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
                            $fc = new \WordPress\AiClient\Tools\DTO\FunctionCall('call_' . substr(md5(uniqid()), 0, 8), $parsed_json['name'], empty($parsed_json['arguments']) ? new \stdClass() : (is_string($parsed_json['arguments']) ? json_decode($parsed_json['arguments'], true) : $parsed_json['arguments']));
                            $part = new \WordPress\AiClient\Messages\DTO\MessagePart($fc);
                            $response_message = new \WordPress\AiClient\Messages\DTO\ModelMessage([$part]);
                            $messages[count($messages) - 1] = $response_message;
                            
                            if ($resolver) {
                                $ai_logger = \WizardAi\Modules\Ai\Ai::instance();
                                if (method_exists($ai_logger, 'log_audit_event')) {
                                    $ai_logger->log_audit_event('editor_agent_fallback', $fc->getName(), $fc->getArgs(), 'success');
                                }
                                $response = $resolver->execute_abilities($response_message);
                                $messages[] = $response;
                                continue;
                            }
                        }
                    }

                    // Self-Correction Loop for Output Validation
                    $ai = \WizardAi\Modules\Ai\Ai::instance();
                    if (method_exists($ai, 'extract_and_validate_json')) {
                        $validation_result = $ai->extract_and_validate_json($ai_text);
                        if (is_wp_error($validation_result)) {
                            // JSON is invalid, push error to AI and continue loop
                            $error_msg = 'ERROR: Your response was not a valid JSON object. Error: ' . $validation_result->get_error_message() . '. Please fix the syntax and ONLY return the JSON.';
                            $messages[] = clone $response_message;
                            $messages[] = new \WordPress\AiClient\Messages\DTO\UserMessage([new \WordPress\AiClient\Messages\DTO\MessagePart($error_msg)]);
                            
                            if ($i < 9) { // If not last iteration
                                continue;
                            }
                        } else {
                            $decoded = $validation_result;
                            
                            // Check PHP syntax if present
                            if (isset($decoded['render']) && method_exists($ai, 'validate_php_syntax')) {
                                $php_check = $ai->validate_php_syntax($decoded['render']);
                                if (is_wp_error($php_check)) {
                                    $error_msg = 'ERROR: The PHP code in the "render" key has a syntax error: ' . $php_check->get_error_message() . '. Please fix the PHP syntax.';
                                    $messages[] = clone $response_message;
                                    $messages[] = new \WordPress\AiClient\Messages\DTO\UserMessage([new \WordPress\AiClient\Messages\DTO\MessagePart($error_msg)]);
                                    if ($i < 9) continue;
                                }
                            }
                            
                            // Validated successfully
                            $updated_json = empty($bundle) ? $decoded : array_merge($bundle, $decoded);
                            remove_filter('http_request_timeout', $timeout_filter, 999);
                            return new \WP_REST_Response(['success' => true, 'updated_json' => $updated_json], 200);
                        }
                    }

                    break;
                } catch (\Exception $e) {
                    $error_msg = str_replace('`', '', strtolower($e->getMessage()));
                    if (!empty($functions) && (strpos($error_msg, 'tool calling is not supported') !== false || strpos($error_msg, 'tools are not supported') !== false || strpos($error_msg, 'does not support tools') !== false)) {
                        // The model doesn't support tools, clear functions and retry
                        $functions = [];
                        continue;
                    }
                    if (strpos($error_msg, 'reduce the length') !== false || strpos($error_msg, 'context length') !== false || strpos($error_msg, 'maximum context') !== false) {
                        remove_filter('http_request_timeout', $timeout_filter, 999);
                        return new \WP_REST_Response(['success' => false, 'message' => __('The request is too large for the selected model. Please try using a model with a larger context window.', 'wizard-ai')], 400);
                    }
                    remove_filter('http_request_timeout', $timeout_filter, 999);
                    return new \WP_REST_Response(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 500);
                }
            }
            
            remove_filter('http_request_timeout', $timeout_filter, 999);

        return new \WP_REST_Response([
            'success' => false,
            'message' => __('AI failed to return valid code after multiple attempts. Please check your prompt or model capacity.', 'wizard-ai')
        ], 400);
    }

    public function add_ai_button_to_submitbox($post) {
        if ($post) {
            $handle = 'wizard-block-ai-script';
            wp_enqueue_script($handle, WIZARD_AI_URL . 'modules/block/assets/js/block.js', [], '1.0.0', true);
            wp_enqueue_style('wizard-block-ai-style', WIZARD_AI_URL . 'modules/block/assets/css/block.css', [], '1.0.0');
            wp_localize_script(
                $handle,
                'wizardData',
                [
                    'rest_url'               => get_rest_url(),
                    'nonce'                  => wp_create_nonce('wp_rest'),
                    'analyzingLabel'         => __('Analyzing...', 'wizard-ai'),
                    'processLabel'           => __('Process', 'wizard-ai'),
                    'emptyPromptError'       => __('Please enter an instruction.', 'wizard-ai'),
                    'invalidJsonError'       => __('Main JSON is not valid.', 'wizard-ai'),
                    'aiErrorPrefix'          => __('AI Error: ', 'wizard-ai'),
                    'invalidAiResponseError' => __('Invalid AI response.', 'wizard-ai'),
                    'preferredModel'         => get_user_meta(get_current_user_id(), '_wai_preferred_model', true),
                    'debugMode'              => (defined('WP_DEBUG') && WP_DEBUG),
                ]
            );
            ?>
            <div id="ai-action" class="button-ai">
                <button type="button" id="wizard-blocks-ai-trigger" class="button button-primary dashicons-before dashicons-superhero">
                    <?php 
                    if (empty($post->post_name)) {
                        esc_html_e('Build with Wizard AI', 'wizard-ai'); 
                    } else {
                        esc_html_e('Edit with Wizard AI', 'wizard-ai'); 
                    }
                    ?>
                </button>
            </div>
            <div id="wizard-ai-modal">
                <div id="wizard-ai-container">
                    <h2>
                        <span class="dashicons dashicons-superhero"></span>
                        <?php esc_html_e('Wizard AI', 'wizard-ai'); ?>
                        <button
                            type="button"
                            id="wizard-ai-close"
                            class="wizard-ai-close"
                            aria-label="<?php echo esc_attr(__('Close', 'wizard-ai')); ?>"
                        >
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </h2>
                    <div style="margin-bottom: 10px;">
                        <select id="ai-model-select" style="width: 100%; border-radius: 4px; padding: 4px 8px; border: 1px solid #8c8f94; font-size: 13px;">
                            <option value=""><?php esc_html_e('Loading models...', 'wizard-ai'); ?></option>
                        </select>
                    </div>
                    <div style="position: relative;">
                        <textarea
                            id="ai-user-prompt"
                            placeholder="<?php esc_attr_e('Eg: Add a color parameter to the PHP render and update the style...', 'wizard-ai'); ?>"
                            style="padding-right: 40px;"
                        ></textarea>
                        <button type="button" id="wizard-ai-speech-btn" style="position: absolute; right: 5px; top: 20px; background: none; border: none; cursor: pointer; font-size: 20px; padding: 0; display: none;" title="<?php esc_attr_e('Speech to text', 'wizard-ai'); ?>">🎤</button>
                    </div>
                    <div class="wizard-ai-actions">
                        <button type="button" id="wizard-ai-cancel" class="button">
                            <?php esc_html_e('Cancel', 'wizard-ai'); ?>
                        </button>
                        <button
                            id="ai-submit-btn"
                            class="button button-primary"
                            type="button"
                        >
                            ✨ <?php esc_html_e('Process', 'wizard-ai'); ?>
                        </button>
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    document.getElementById('wizard-blocks-ai-trigger')?.addEventListener('click', e => { 
                        e.preventDefault(); if (typeof openAiPopup === 'function') openAiPopup(); 
                    });
                });
            </script>
            <?php
        }
    }
}
