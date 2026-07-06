<?php
namespace WizardAi\Modules\Mcp;

class Mcp {
    public function __construct() {
        if (method_exists($this, 'register_mcp_hooks')) {
            $this->register_mcp_hooks();
        } elseif (method_exists($this, 'register_mcp_routes')) {
            $this->register_mcp_hooks();
        }
    }

    public function register_mcp_hooks() {
        add_action('admin_menu', [$this, 'add_mcp_menu']);
        add_action('rest_api_init', [$this, 'register_mcp_routes']);
    }

    public function register_mcp_routes() {
        add_action('rest_api_init', [$this, 'init_mcp_routes']);
    }

    public function add_mcp_menu() {
        add_submenu_page(
            'wizard-ai',
            __('MCP & GPT Integrations', 'wizard-ai'),
            __('MCP & GPT', 'wizard-ai'),
            'manage_options',
            'wizard-ai-mcp',
            [$this, 'wb_ai_mcp_page_html']
        );
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

        // Webhook Receiver Endpoint for AI Logs
        register_rest_route('wizard-blocks/v1', '/mcp/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook_request'],
            'permission_callback' => '__return_true'
        ]);
    }

    public function mcp_permission_check(\WP_REST_Request $request) {
        // Basic Application Password or Cookie Auth is handled by WP REST API natively.
        // We ensure the user is at least logged in, or we can use a custom token.
        $token = $request->get_header('X-WAI-MCP-TOKEN');
        $saved_token = get_option('wai_mcp_token', '');
        
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

        $wai_abilities = function_exists('wp_get_abilities') ? wp_get_abilities() : [];

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
                    foreach ($wai_abilities as $ability) {
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
        $wai_abilities = function_exists('wp_get_abilities') ? wp_get_abilities() : [];

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
                        'name' => 'X-WAI-MCP-TOKEN'
                    ]
                ]
            ],
            'security' => [['ApiKeyAuth' => []]]
        ];

        foreach ($wai_abilities as $ability) {
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

    public function handle_webhook_request(\WP_REST_Request $request) {
        $token = $request->get_header('X-WAI-MCP-TOKEN') ?: $request->get_param('token');
        $saved_token = get_option('wai_mcp_token', '');
        
        if (!empty($saved_token) && $token !== $saved_token) {
            return new \WP_Error('unauthorized', 'Invalid webhook token', ['status' => 401]);
        }

        $body = $request->get_json_params();
        if (empty($body)) {
            $body = json_decode($request->get_body(), true) ?: $request->get_params();
        }
        
        $title = isset($body['title']) ? sanitize_text_field($body['title']) : 'AI Webhook';
        $author = isset($body['agent']) ? sanitize_text_field($body['agent']) : (isset($body['tool']) ? sanitize_text_field($body['tool']) : 'Wizard AI');
        $content_data = isset($body['content']) ? $body['content'] : $body;
        $content = is_string($content_data) ? wp_kses_post($content_data) : wp_json_encode($content_data, JSON_PRETTY_PRINT);
        
        $comment_content = "<strong>" . $title . "</strong><br>\n" . $content;
        $post_id = isset($body['post_id']) ? (int) $body['post_id'] : 0;
        
        $comment_id = wp_insert_comment([
            'comment_post_ID' => $post_id,
            'comment_author' => $author,
            'comment_content' => $comment_content,
            'comment_type' => 'wai_log',
            'comment_approved' => 1,
            'comment_meta' => isset($body['meta']) && is_array($body['meta']) ? $body['meta'] : [],
        ]);
        
        if (!$comment_id) {
            return new \WP_Error('webhook_failed', 'Failed to create comment log', ['status' => 500]);
        }
        
        return rest_ensure_response(['success' => true, 'comment_id' => $comment_id]);
    }

    public function wb_ai_mcp_page_html() {
        if (isset($_POST['wai_mcp_settings_nonce']) && wp_verify_nonce($_POST['wai_mcp_settings_nonce'], 'wai_mcp_settings')) {
            update_option('wai_mcp_token', sanitize_text_field($_POST['wai_mcp_token']));
            echo '<div class="updated"><p>' . __('MCP Settings saved.', 'wizard-ai') . '</p></div>';
        }

        $token = get_option('wai_mcp_token', wp_generate_password(24, false));
        if (empty(get_option('wai_mcp_token'))) {
            update_option('wai_mcp_token', $token);
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('MCP & GPT Integrations', 'wizard-ai'); ?></h1>
            <p><?php esc_html_e('Use this server to expose your WordPress site tools to Claude via MCP, or GPT via Custom Actions.', 'wizard-ai'); ?></p>
            
            <form method="post" action="">
                <?php wp_nonce_field('wai_mcp_settings', 'wai_mcp_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('API Secret Token', 'wizard-ai'); ?></th>
                        <td>
                            <input type="text" name="wai_mcp_token" value="<?php echo esc_attr($token); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Use this token as X-WAI-MCP-TOKEN header for authentication.', 'wizard-ai'); ?></p>
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
                        <strong>Header Name:</strong> X-WAI-MCP-TOKEN<br>
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
                                <li><strong><?php esc_html_e('Custom Header Name:', 'wizard-ai'); ?></strong> <code>X-WAI-MCP-TOKEN</code></li>
                                <li><strong><?php esc_html_e('API Key:', 'wizard-ai'); ?></strong> <em><?php esc_html_e('(Paste your API Secret Token from above)', 'wizard-ai'); ?></em></li>
                            </ul>
                        </li>
                        <li><strong><?php esc_html_e('Save:', 'wizard-ai'); ?></strong> <?php esc_html_e('Save your GPT. It can now access your site tools securely!', 'wizard-ai'); ?></li>
                    </ol>
                </div>

                <!-- Webhook Receiver -->
                <div class="postbox" style="flex: 1; min-width: 300px; padding: 20px;">
                    <h2 style="margin-top: 0; padding: 0; border-bottom: none;"><?php esc_html_e('AI Webhook Receiver', 'wizard-ai'); ?></h2>
                    <p><?php esc_html_e('Use this endpoint to let external AIs (or cron tasks) send logs and statuses directly to your WordPress backend.', 'wizard-ai'); ?></p>
                    <div style="background: #f0f0f0; padding: 15px; border-left: 4px solid #0073aa; margin-bottom: 15px; font-family: monospace; overflow-x: auto;">
                        <strong>POST Endpoint:</strong> <?php echo esc_url(get_site_url() . '/wp-json/wizard-blocks/v1/mcp/webhook'); ?><br><br>
                        <strong>Auth:</strong> Include "token": "<?php echo esc_html($token); ?>" in JSON body, OR header X-WAI-MCP-TOKEN.<br><br>
                        <strong>Payload Example:</strong><br>
                        {<br>
                        &nbsp;&nbsp;"token": "<?php echo esc_html($token); ?>",<br>
                        &nbsp;&nbsp;"post_id": 123, <em>(optional)</em><br>
                        &nbsp;&nbsp;"agent": "Claude 3.5 Sonnet", <em>(optional)</em><br>
                        &nbsp;&nbsp;"title": "Cron Task Completed",<br>
                        &nbsp;&nbsp;"content": "Successfully parsed 10 articles."<br>
                        }
                    </div>
                    <p><em><?php esc_html_e('Logs sent here will appear as Comments (type: wai_log) in WordPress. If you provide a post_id, the comment will be attached to that specific post.', 'wizard-ai'); ?></em></p>
                </div>
            </div>

            <!-- AI Logs Display -->
            <div style="margin-top: 30px;">
                <h2><?php esc_html_e('Recent AI Activity Logs', 'wizard-ai'); ?></h2>
                <?php
                $logs = get_comments([
                    'type' => 'wai_log',
                    'number' => 50,
                    'order' => 'DESC'
                ]);

                if (empty($logs)) {
                    echo '<p>' . esc_html__('No AI logs recorded yet.', 'wizard-ai') . '</p>';
                } else {
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr>';
                    echo '<th style="width: 15%;">' . esc_html__('Date', 'wizard-ai') . '</th>';
                    echo '<th style="width: 15%;">' . esc_html__('Agent / Tool', 'wizard-ai') . '</th>';
                    echo '<th style="width: 10%;">' . esc_html__('Post ID', 'wizard-ai') . '</th>';
                    echo '<th>' . esc_html__('Log Details', 'wizard-ai') . '</th>';
                    echo '<th style="width: 10%;">' . esc_html__('Action', 'wizard-ai') . '</th>';
                    echo '</tr></thead><tbody>';
                    
                    foreach ($logs as $log) {
                        $delete_url = wp_nonce_url(admin_url('comment.php?action=deletecomment&p=' . $log->comment_post_ID . '&c=' . $log->comment_ID), 'delete-comment_' . $log->comment_ID);
                        $post_link = $log->comment_post_ID ? '<a href="' . get_edit_post_link($log->comment_post_ID) . '">#' . esc_html($log->comment_post_ID) . '</a>' : '-';
                        
                        echo '<tr>';
                        echo '<td>' . esc_html($log->comment_date) . '</td>';
                        echo '<td><strong>' . esc_html($log->comment_author) . '</strong></td>';
                        echo '<td>' . $post_link . '</td>';
                        echo '<td>' . wp_kses_post($log->comment_content) . '</td>';
                        echo '<td><a href="' . esc_url($delete_url) . '" class="delete" style="color: #d63638;">' . esc_html__('Delete', 'wizard-ai') . '</a></td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody></table>';
                }
                ?>
            </div>
        </div>
        <?php
    }
}
