<?php
namespace WizardAi\Modules\Ai\Traits;

trait Mcp {
    public function register_mcp_routes() {
        add_action('rest_api_init', [$this, 'init_mcp_routes']);
    }

    public function init_mcp_routes() {
        // MCP JSON-RPC Endpoint (Stateless)
        register_rest_route('wizard-blocks/v1', '/mcp', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_mcp_request'],
            'permission_callback' => [$this, 'mcp_permission_check']
        ]);

        // OpenAPI Schema Endpoint (for GPT Custom Actions)
        register_rest_route('wizard-blocks/v1', '/openapi.json', [
            'methods' => 'GET',
            'callback' => [$this, 'generate_openapi_schema'],
            'permission_callback' => '__return_true'
        ]);

        // OpenAPI Tool Execution Endpoint
        register_rest_route('wizard-blocks/v1', '/mcp/tool/(?P<tool_name>[a-zA-Z0-9_-]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_openapi_tool_call'],
            'permission_callback' => [$this, 'mcp_permission_check']
        ]);
    }

    public function mcp_permission_check(\WP_REST_Request $request) {
        // Basic Application Password or Cookie Auth is handled by WP REST API natively.
        // We ensure the user is at least logged in, or we can use a custom token.
        $token = $request->get_header('X-WBAI-MCP-TOKEN');
        $saved_token = get_option('wbai_mcp_token', '');
        
        if (!empty($saved_token) && $token === $saved_token) {
            // Force user to be admin for MCP token requests
            $users = get_users(['role' => 'administrator', 'number' => 1]);
            if (!empty($users)) {
                wp_set_current_user($users[0]->ID);
                return true;
            }
        }
        
        return current_user_can('manage_options');
    }

    public function handle_mcp_request(\WP_REST_Request $request) {
        $body = $request->get_json_params();
        if (empty($body)) {
            $body = json_decode($request->get_body(), true);
        }

        $is_batch = is_array($body) && isset($body[0]);
        $requests = $is_batch ? $body : [$body];
        $responses = [];

        $wbai_abilities = function_exists('wp_get_abilities') ? wp_get_abilities() : [];

        foreach ($requests as $req) {
            $id = isset($req['id']) ? $req['id'] : null;
            $method = isset($req['method']) ? $req['method'] : '';
            $params = isset($req['params']) ? $req['params'] : [];

            $response = ['jsonrpc' => '2.0'];
            if ($id !== null) {
                $response['id'] = $id;
            }

            try {
                if ($method === 'tools/list') {
                    $tools = [];
                    foreach ($wbai_abilities as $ability) {
                        $tools[] = [
                            'name' => str_replace('/', '_', $ability->get_name()),
                            'description' => $ability->get_description() ?: '',
                            'inputSchema' => $ability->get_input_schema() ?: ['type' => 'object', 'properties' => []]
                        ];
                    }
                    $response['result'] = ['tools' => $tools];
                } elseif ($method === 'tools/call') {
                    $name = isset($params['name']) ? str_replace('_', '/', $params['name']) : '';
                    $args = isset($params['arguments']) ? $params['arguments'] : [];

                    $ability = function_exists('wp_get_ability') ? wp_get_ability($name) : null;
                    if ($ability) {
                        $result = $ability->execute($args);
                        if (is_wp_error($result)) {
                            throw new \Exception($result->get_error_message());
                        }

                        $response['result'] = [
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => is_string($result) ? $result : wp_json_encode($result)
                                ]
                            ]
                        ];
                    } else {
                        throw new \Exception("Tool not found: {$name}");
                    }
                } elseif ($method === 'resources/list') {
                    $response['result'] = ['resources' => []];
                } elseif ($method === 'prompts/list') {
                    $response['result'] = ['prompts' => []];
                } else {
                    throw new \Exception("Method not found", -32601);
                }
            } catch (\Exception $e) {
                $response['error'] = [
                    'code' => $e->getCode() ?: -32000,
                    'message' => $e->getMessage()
                ];
            }

            $responses[] = $response;
        }

        return rest_ensure_response($is_batch ? $responses : $responses[0]);
    }

    public function generate_openapi_schema(\WP_REST_Request $request) {
        $wbai_abilities = function_exists('wp_get_abilities') ? wp_get_abilities() : [];

        $site_url = get_site_url();
        $schema = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => get_bloginfo('name') . ' API',
                'description' => 'API for interacting with ' . get_bloginfo('name'),
                'version' => '1.0.0'
            ],
            'servers' => [
                ['url' => $site_url . '/wp-json/wizard-blocks/v1']
            ],
            'paths' => [],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-WBAI-MCP-TOKEN'
                    ]
                ]
            ],
            'security' => [['ApiKeyAuth' => []]]
        ];

        foreach ($wbai_abilities as $ability) {
            $path_name = str_replace('/', '_', $ability->get_name());
            $schema['paths']['/mcp/tool/' . $path_name] = [
                'post' => [
                    'operationId' => $path_name,
                    'summary' => $ability->get_label() ?: $path_name,
                    'description' => $ability->get_description() ?: '',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => $ability->get_input_schema() ?: ['type' => 'object']
                            ]
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                            'content' => ['application/json' => ['schema' => ['type' => 'object']]]
                        ]
                    ]
                ]
            ];
        }

        return rest_ensure_response($schema);
    }
    
    public function handle_openapi_tool_call(\WP_REST_Request $request) {
        $tool_name = $request->get_param('tool_name');
        $name = str_replace('_', '/', $tool_name);
        $args = $request->get_json_params() ?: json_decode($request->get_body(), true);
        
        $ability = function_exists('wp_get_ability') ? wp_get_ability($name) : null;
        if (!$ability) {
            return new \WP_Error('tool_not_found', 'Tool not found', ['status' => 404]);
        }
        
        $result = $ability->execute($args);
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(['success' => true, 'result' => $result]);
    }

    public function wb_ai_mcp_page_html() {
        if (isset($_POST['wbai_mcp_settings_nonce']) && wp_verify_nonce($_POST['wbai_mcp_settings_nonce'], 'wbai_mcp_settings')) {
            update_option('wbai_mcp_token', sanitize_text_field($_POST['wbai_mcp_token']));
            echo '<div class="updated"><p>' . __('MCP Settings saved.', 'wizard-ai') . '</p></div>';
        }

        $token = get_option('wbai_mcp_token', wp_generate_password(24, false));
        if (empty(get_option('wbai_mcp_token'))) {
            update_option('wbai_mcp_token', $token);
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('MCP & GPT Integrations', 'wizard-ai'); ?></h1>
            <p><?php esc_html_e('Use this server to expose your WordPress site tools to Claude via MCP, or GPT via Custom Actions.', 'wizard-ai'); ?></p>
            
            <form method="post" action="">
                <?php wp_nonce_field('wbai_mcp_settings', 'wbai_mcp_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('API Secret Token', 'wizard-ai'); ?></th>
                        <td>
                            <input type="text" name="wbai_mcp_token" value="<?php echo esc_attr($token); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Use this token as X-WBAI-MCP-TOKEN header for authentication.', 'wizard-ai'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px;">
                <!-- Claude Configuration -->
                <div class="postbox" style="flex: 1; min-width: 300px; padding: 20px;">
                    <h2 style="margin-top: 0; padding: 0; border-bottom: none;"><?php esc_html_e('Claude Configuration (via MCP)', 'wizard-ai'); ?></h2>
                    <p><?php esc_html_e('Integrate your site with Claude Desktop or Cursor via the Model Context Protocol (MCP).', 'wizard-ai'); ?></p>
                    <ol style="margin-left: 1.5em;">
                        <li><strong><?php esc_html_e('Locate Configuration:', 'wizard-ai'); ?></strong> <?php esc_html_e('Open your MCP client config (e.g., claude_desktop_config.json for Claude Desktop).', 'wizard-ai'); ?></li>
                        <li><strong><?php esc_html_e('Set Up Bridge:', 'wizard-ai'); ?></strong> <?php esc_html_e('Since Claude Desktop natively uses stdio, use an HTTP-to-stdio bridge script to connect to this endpoint.', 'wizard-ai'); ?></li>
                        <li><strong><?php esc_html_e('Configure Connection:', 'wizard-ai'); ?></strong> <?php esc_html_e('Provide your client or bridge with the following connection details:', 'wizard-ai'); ?></li>
                    </ol>
                    <div style="background: #f0f0f0; padding: 15px; border-left: 4px solid #0073aa; margin-bottom: 15px; font-family: monospace; overflow-x: auto;">
                        <strong>Endpoint URL:</strong> <?php echo esc_url(get_site_url() . '/wp-json/wizard-blocks/v1/mcp'); ?><br><br>
                        <strong>Header Name:</strong> X-WBAI-MCP-TOKEN<br>
                        <strong>Header Value:</strong> <?php echo esc_html($token); ?>
                    </div>
                    <p><em><?php esc_html_e('Once connected, Claude will automatically read the available capabilities and can execute them when prompted.', 'wizard-ai'); ?></em></p>
                </div>

                <!-- GPT Configuration -->
                <div class="postbox" style="flex: 1; min-width: 300px; padding: 20px;">
                    <h2 style="margin-top: 0; padding: 0; border-bottom: none;"><?php esc_html_e('ChatGPT Configuration (Custom Actions)', 'wizard-ai'); ?></h2>
                    <p><?php esc_html_e('Empower a Custom GPT to interact directly with your site using Custom Actions.', 'wizard-ai'); ?></p>
                    <ol style="margin-left: 1.5em;">
                        <li><strong><?php esc_html_e('Create Action:', 'wizard-ai'); ?></strong> <?php esc_html_e('In ChatGPT, edit your Custom GPT, go to the Actions section, and click "Create new action".', 'wizard-ai'); ?></li>
                        <li><strong><?php esc_html_e('Import Schema:', 'wizard-ai'); ?></strong> <?php esc_html_e('Click "Import from URL", paste the link below, and click "Import":', 'wizard-ai'); ?>
                            <input type="text" readonly value="<?php echo esc_url(get_site_url() . '/wp-json/wizard-blocks/v1/openapi.json'); ?>" class="large-text code" style="margin-top: 5px; width: 100%; display: block;" onclick="this.select();">
                        </li>
                        <li><strong><?php esc_html_e('Setup Authentication:', 'wizard-ai'); ?></strong> <?php esc_html_e('Click the gear icon in the Authentication section and set:', 'wizard-ai'); ?>
                            <ul style="list-style-type: disc; margin-left: 20px; margin-top: 5px;">
                                <li><strong><?php esc_html_e('Authentication Type:', 'wizard-ai'); ?></strong> <?php esc_html_e('API Key', 'wizard-ai'); ?></li>
                                <li><strong><?php esc_html_e('Auth Type:', 'wizard-ai'); ?></strong> <?php esc_html_e('Custom', 'wizard-ai'); ?></li>
                                <li><strong><?php esc_html_e('Custom Header Name:', 'wizard-ai'); ?></strong> <code>X-WBAI-MCP-TOKEN</code></li>
                                <li><strong><?php esc_html_e('API Key:', 'wizard-ai'); ?></strong> <em><?php esc_html_e('(Paste your API Secret Token from above)', 'wizard-ai'); ?></em></li>
                            </ul>
                        </li>
                        <li><strong><?php esc_html_e('Save:', 'wizard-ai'); ?></strong> <?php esc_html_e('Save your GPT. It can now access your site tools securely!', 'wizard-ai'); ?></li>
                    </ol>
                </div>
            </div>
        </div>
        <?php
    }
}
