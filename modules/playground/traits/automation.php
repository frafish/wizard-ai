<?php
namespace WizardAi\Modules\Playground\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait Automation {

    public function register_automation_hooks() {
        add_action('admin_menu', [$this, 'add_automation_menu']);
        add_action('rest_api_init', [$this, 'register_automation_routes']);
        
        // Custom Cron schedule
        add_filter('cron_schedules', function($schedules) {
            if (!isset($schedules['wizard_ai_every_minute'])) {
                $schedules['wizard_ai_every_minute'] = [
                    'interval' => 60,
                    'display' => __('Every Minute (Wizard AI)', 'wizard-ai')
                ];
            }
            return $schedules;
        });

        // Cron hook
        add_action('wizard_ai_automated_tasks_cron', [$this, 'run_automated_tasks']);

        // Register cron if not registered
        if (!wp_next_scheduled('wizard_ai_automated_tasks_cron')) {
            wp_schedule_event(time(), 'wizard_ai_every_minute', 'wizard_ai_automated_tasks_cron');
        }
    }

    public function add_automation_menu() {
        add_submenu_page(
            'wizard-ai',
            __('Automated Tasks', 'wizard-ai'),
            __('Automated Tasks', 'wizard-ai'),
            'manage_options',
            'wizard-ai-automation',
            [$this, 'wb_ai_automation_page_html']
        );
        add_submenu_page(
            null,
            __('Task Log', 'wizard-ai'),
            __('Task Log', 'wizard-ai'),
            'manage_options',
            'wizard-ai-automation-log',
            [$this, 'wb_ai_automation_log_page_html']
        );
    }

    public function register_automation_routes() {
        register_rest_route('wizard-ai/v1', '/automation/save', [
            'methods' => 'POST',
            'callback' => [$this, 'save_automated_task'],
            'permission_callback' => function () { return current_user_can('manage_options'); }
        ]);
        register_rest_route('wizard-ai/v1', '/automation/delete', [
            'methods' => 'POST',
            'callback' => [$this, 'delete_automated_task'],
            'permission_callback' => function () { return current_user_can('manage_options'); }
        ]);
        register_rest_route('wizard-ai/v1', '/automation/run-now', [
            'methods' => 'POST',
            'callback' => [$this, 'run_task_now'],
            'permission_callback' => function () { return current_user_can('manage_options'); }
        ]);
    }

    public function wb_ai_automation_page_html() {
        $tasks = get_option('wizard_ai_automated_tasks', []);
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-clock"></span> <?php esc_html_e('Automated AI Tasks', 'wizard-ai'); ?></h1>
            <p><?php esc_html_e('Schedule prompts to run autonomously in the background.', 'wizard-ai'); ?></p>
            
            <div style="display:flex; gap: 20px;">
                <div style="flex: 2;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Task Name', 'wizard-ai'); ?></th>
                                <th><?php esc_html_e('Prompt', 'wizard-ai'); ?></th>
                                <th><?php esc_html_e('Model', 'wizard-ai'); ?></th>
                                <th><?php esc_html_e('Schedule', 'wizard-ai'); ?></th>
                                <th><?php esc_html_e('Last Run', 'wizard-ai'); ?></th>
                                <th><?php esc_html_e('Status', 'wizard-ai'); ?></th>
                                <th><?php esc_html_e('Actions', 'wizard-ai'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tasks)): ?>
                                <tr><td colspan="7"><?php esc_html_e('No automated tasks scheduled.', 'wizard-ai'); ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($tasks as $id => $task): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($task['name']); ?></strong>
                                        <?php if (!empty($task['allow_critical'])): ?>
                                            <br><span style="color:#d63638; font-size:11px;">⚠️ Critical Allowed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo esc_html(mb_strimwidth($task['prompt'], 0, 50, '...')); ?></code></td>
                                    <td><?php echo esc_html(!empty($task['model']) ? $task['model'] : __('Default', 'wizard-ai')); ?></td>
                                    <td><?php echo esc_html(ucfirst($task['schedule'])); ?></td>
                                    <td><?php echo $task['last_run'] ? esc_html(date_i18n('Y-m-d H:i:s', $task['last_run'])) : esc_html__('Never', 'wizard-ai'); ?></td>
                                    <td><?php echo empty($task['active']) ? '<span style="color:red;">Paused</span>' : '<span style="color:green;">Active</span>'; ?></td>
                                    <td>
                                        <button class="button button-small wai-run-task" data-id="<?php echo esc_attr($id); ?>"><?php esc_html_e('Run Now', 'wizard-ai'); ?></button>
                                        <?php if (!empty($task['last_log'])): ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=wizard-ai-automation-log&id=' . $id)); ?>" class="button button-small" target="_blank"><?php esc_html_e('View Log', 'wizard-ai'); ?></a>
                                        <?php endif; ?>
                                        <button class="button button-small button-link-delete wai-delete-task" data-id="<?php echo esc_attr($id); ?>"><?php esc_html_e('Delete', 'wizard-ai'); ?></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="flex: 1;" class="card">
                    <h3><?php esc_html_e('Add New Task', 'wizard-ai'); ?></h3>
                    <div style="margin-bottom: 10px;">
                        <label><strong><?php esc_html_e('Task Name', 'wizard-ai'); ?></strong></label><br>
                        <input type="text" id="wai-task-name" style="width: 100%;">
                    </div>
                    <div style="margin-bottom: 10px;">
                        <label><strong><?php esc_html_e('Model (Optional)', 'wizard-ai'); ?></strong></label><br>
                        <select id="wai-task-model" style="width: 100%;">
                            <option value=""><?php esc_html_e('Default Model', 'wizard-ai'); ?></option>
                        </select>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <label><strong><?php esc_html_e('Prompt', 'wizard-ai'); ?></strong></label><br>
                        <textarea id="wai-task-prompt" style="width: 100%; height: 100px;" placeholder="e.g. Find 3 products without description and write one..."></textarea>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <label><strong><?php esc_html_e('Schedule', 'wizard-ai'); ?></strong></label><br>
                        <select id="wai-task-schedule" style="width: 100%;">
                            <option value="hourly"><?php esc_html_e('Hourly (0 * * * *)', 'wizard-ai'); ?></option>
                            <option value="twicedaily"><?php esc_html_e('Twice Daily (0 0,12 * * *)', 'wizard-ai'); ?></option>
                            <option value="daily"><?php esc_html_e('Daily (0 0 * * *)', 'wizard-ai'); ?></option>
                            <option value="weekly"><?php esc_html_e('Weekly (0 0 * * 0)', 'wizard-ai'); ?></option>
                            <option value="custom"><?php esc_html_e('Custom Cron...', 'wizard-ai'); ?></option>
                        </select>
                        <div id="wai-custom-cron-wrap" style="display:none; margin-top:5px;">
                            <input type="text" id="wai-custom-cron" style="width: 100%; font-family: monospace;" placeholder="* * * * *">
                            <p class="description"><?php esc_html_e('Minute, Hour, Day, Month, Day of week.', 'wizard-ai'); ?></p>
                        </div>
                    </div>
                    <div style="margin-bottom: 15px; padding: 10px; background: #fcf0f1; border-left: 4px solid #d63638;">
                        <label>
                            <input type="checkbox" id="wai-task-allow-critical">
                            <strong><?php esc_html_e('Allow Critical Tools', 'wizard-ai'); ?></strong>
                        </label>
                        <p class="description" style="margin-top:5px; color:#d63638;"><?php esc_html_e('Warning: Allows the AI to execute raw PHP and DB Queries during the background task. The task will run as your user.', 'wizard-ai'); ?></p>
                    </div>
                    <button class="button button-primary" id="wai-save-task"><?php esc_html_e('Save Task', 'wizard-ai'); ?></button>
                </div>
            </div>
        </div>

        <script>
        fetch('<?php echo esc_js(esc_url_raw(rest_url("wizard-ai/v1/ai-models"))); ?>', {
            headers: {
                'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>'
            }
        })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.models) {
                    const select = document.getElementById('wai-task-model');
                    Object.entries(res.models).forEach(([provider, modelsObj]) => {
                        const optgroup = document.createElement('optgroup');
                        optgroup.label = provider;
                        Object.entries(modelsObj).forEach(([id, name]) => {
                            const opt = document.createElement('option');
                            opt.value = id;
                            opt.innerText = name;
                            optgroup.appendChild(opt);
                        });
                        select.appendChild(optgroup);
                    });
                    if (typeof jQuery !== 'undefined' && jQuery.fn.select2) jQuery(select).select2();
                }
            });

        document.getElementById('wai-task-schedule').addEventListener('change', function() {
            document.getElementById('wai-custom-cron-wrap').style.display = this.value === 'custom' ? 'block' : 'none';
        });

        document.getElementById('wai-save-task').addEventListener('click', function() {
            const btn = this;
            const name = document.getElementById('wai-task-name').value;
            const prompt = document.getElementById('wai-task-prompt').value;
            let schedule = document.getElementById('wai-task-schedule').value;
            if (schedule === 'custom') {
                schedule = document.getElementById('wai-custom-cron').value;
                if (!schedule) { alert('Custom cron syntax required'); return; }
            }
            
            const allow_critical = document.getElementById('wai-task-allow-critical').checked;
            const model = document.getElementById('wai-task-model').value;
            
            if (!name || !prompt) { alert('Name and prompt are required'); return; }
            btn.disabled = true;
            btn.innerText = 'Saving...';
            
            fetch('<?php echo esc_js(esc_url_raw(rest_url("wizard-ai/v1/automation/save"))); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>'
                },
                body: JSON.stringify({ name: name, prompt: prompt, schedule: schedule, allow_critical: allow_critical, model: model })
            }).then(r => r.json()).then(res => {
                location.reload();
            });
        });

        document.querySelectorAll('.wai-delete-task').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Delete this task?')) return;
                const id = this.dataset.id;
                fetch('<?php echo esc_js(esc_url_raw(rest_url("wizard-ai/v1/automation/delete"))); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>'
                    },
                    body: JSON.stringify({ id: id })
                }).then(r => r.json()).then(res => {
                    location.reload();
                });
            });
        });
        
        document.querySelectorAll('.wai-run-task').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const originalText = this.innerText;
                this.innerText = 'Running...';
                this.disabled = true;
                fetch('<?php echo esc_js(esc_url_raw(rest_url("wizard-ai/v1/automation/run-now"))); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>'
                    },
                    body: JSON.stringify({ id: id })
                }).then(r => r.json()).then(res => {
                    alert('Task completed!');
                    location.reload();
                });
            });
        });
        </script>
        <?php
    }

    public function wb_ai_automation_log_page_html() {
        if (!current_user_can('manage_options')) return;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $id = isset($_GET['id']) ? sanitize_text_field(wp_unslash($_GET['id'])) : '';
        $tasks = get_option('wizard_ai_automated_tasks', []);
        
        if (empty($id) || !isset($tasks[$id])) {
            echo '<div class="wrap"><h1>' . esc_html__('Task Not Found', 'wizard-ai') . '</h1></div>';
            return;
        }
        
        $task = $tasks[$id];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Execution Log:', 'wizard-ai'); ?> <?php echo esc_html($task['name']); ?></h1>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=wizard-ai-automation')); ?>" class="button">&larr; <?php esc_html_e('Back to Automated Tasks', 'wizard-ai'); ?></a></p>
            <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; font-family: monospace; white-space: pre-wrap; font-size: 14px; color: #3c434a; max-width: 800px;">
                <?php echo wp_kses_post($task['last_log'] ?? __('No log available yet.', 'wizard-ai')); ?>
            </div>
        </div>
        <?php
    }

    public function save_automated_task(\WP_REST_Request $request) {
        $tasks = get_option('wizard_ai_automated_tasks', []);
        $id = uniqid('task_');
        $tasks[$id] = [
            'name' => sanitize_text_field($request->get_param('name')),
            'prompt' => sanitize_textarea_field($request->get_param('prompt')),
            'schedule' => sanitize_text_field($request->get_param('schedule')),
            'allow_critical' => rest_sanitize_boolean($request->get_param('allow_critical')),
            'model' => sanitize_text_field($request->get_param('model') ?? ''),
            'user_id' => get_current_user_id(),
            'active' => true,
            'last_run' => 0
        ];
        update_option('wizard_ai_automated_tasks', $tasks);
        return new \WP_REST_Response(['success' => true], 200);
    }

    public function delete_automated_task(\WP_REST_Request $request) {
        $tasks = get_option('wizard_ai_automated_tasks', []);
        $id = $request->get_param('id');
        if (isset($tasks[$id])) {
            unset($tasks[$id]);
            update_option('wizard_ai_automated_tasks', $tasks);
        }
        return new \WP_REST_Response(['success' => true], 200);
    }

    public function run_task_now(\WP_REST_Request $request) {
        $id = $request->get_param('id');
        $this->_execute_task($id);
        return new \WP_REST_Response(['success' => true], 200);
    }

    private function _is_cron_due($expression, $last_run) {
        $parts = explode(' ', preg_replace('/\s+/', ' ', trim($expression)));
        if (count($parts) !== 5) return false;
        
        $now = time();
        if ($now - $last_run < 50) return false;

        $c_min = intval(gmdate('i', $now));
        $c_hour = intval(gmdate('H', $now));
        $c_dom = intval(gmdate('d', $now));
        $c_month = intval(gmdate('m', $now));
        $c_dow = intval(gmdate('w', $now));
        
        $match = function($val, $current) {
            if ($val === '*') return true;
            if (strpos($val, '*/') === 0) {
                $div = intval(substr($val, 2));
                return $div > 0 && $current % $div === 0;
            }
            if (is_numeric($val)) return intval($val) === $current;
            return false;
        };
        
        return $match($parts[0], $c_min) &&
               $match($parts[1], $c_hour) &&
               $match($parts[2], $c_dom) &&
               $match($parts[3], $c_month) &&
               $match($parts[4], $c_dow);
    }

    public function run_automated_tasks() {
        $tasks = get_option('wizard_ai_automated_tasks', []);
        if (empty($tasks)) return;

        $now = time();
        $updated = false;

        $intervals = [
            'hourly' => 3600,
            'twicedaily' => 43200,
            'daily' => 86400,
            'weekly' => 604800
        ];

        foreach ($tasks as $id => &$task) {
            if (empty($task['active'])) continue;

            $should_run = false;
            
            // If it's currently running a long task, continue immediately
            if (!empty($task['running_conversation_id'])) {
                $should_run = true;
            } else {
                $sched = $task['schedule'];
                if (isset($intervals[$sched])) {
                    if ($now - $task['last_run'] >= $intervals[$sched]) {
                        $should_run = true;
                    }
                } else {
                    $should_run = $this->_is_cron_due($sched, $task['last_run']);
                }
            }

            if ($should_run) {
                $this->_execute_task($id, true);
                $updated = true;
            }
        }

        if ($updated) {
            // Update not needed here as _execute_task will update the option
        }
    }

    private function _execute_task($task_id, $is_cron = false) {
        $tasks = get_option('wizard_ai_automated_tasks', []);
        if (!isset($tasks[$task_id])) return false;

        $task = $tasks[$task_id];

        if (!class_exists('\WordPress\AiClient\AiClient')) return false;
        
        $original_user_id = get_current_user_id();
        if (!empty($task['user_id'])) {
            wp_set_current_user($task['user_id']);
        }

        // Crash detection
        $crashed_last_time = !empty($task['is_running']);
        
        // Lock the task
        $tasks[$task_id]['is_running'] = true;
        update_option('wizard_ai_automated_tasks', $tasks);

        $is_resuming = !empty($task['running_conversation_id']);
        $conversation_id = $is_resuming ? $task['running_conversation_id'] : 'cron_' . $task_id . '_' . time();
        
        $request = new \WP_REST_Request('POST', '/wizard-ai/v1/ai-chat');
        $request->set_param('conversation_id', $conversation_id);
        if (!empty($task['model'])) {
            $request->set_param('model', $task['model']);
        }
        
        if ($crashed_last_time) {
            $request->set_param('wai_enforce_safe_mode', 1);
        }
        
        if (!$is_resuming) {
            $prompt = "CRON AUTOMATED TASK: " . $task['name'] . "\n\n" . $task['prompt'];
            if ($crashed_last_time) {
                $prompt .= "\n\n[SYSTEM NOTE: The previous execution crashed with a Fatal 500 Error. Safe Mode is now active. Please use tools carefully to debug and resolve the issue.]";
            }
            $request->set_param('prompt', $prompt);
            $tasks[$task_id]['last_log'] = "[" . current_time('mysql') . "] Task Started.\n";
            update_option('wizard_ai_automated_tasks', $tasks);
        } elseif (!empty($task['running_action']) && $task['running_action'] === 'tool_calls') {
            $request->set_param('execute_tools', true);
            $tasks[$task_id]['last_log'] = ($tasks[$task_id]['last_log'] ?? '') . "[" . current_time('mysql') . "] Resuming task execution (tool calls)...\n";
            update_option('wizard_ai_automated_tasks', $tasks);
        } elseif (!empty($task['running_action']) && $task['running_action'] === 'broken_site') {
            $request->set_param('prompt', "[CRITICAL ALERT]: Your last actions completed, but caused the entire website to return a 500 Fatal Error! Safe Mode has been forcefully activated. Please review the changes you just made and fix the site immediately.");
            $tasks[$task_id]['last_log'] = ($tasks[$task_id]['last_log'] ?? '') . "[" . current_time('mysql') . "] Resuming task execution (Safe Mode Recovery)...\n";
            update_option('wizard_ai_automated_tasks', $tasks);
        }
        
        $request->set_param('is_cron', true);
        
        $response = $this->handle_chat_request($request);
        $iterations = 0;
        $max_iterations = 2; // Process up to 2 steps per cron tick to avoid timeouts
        
        $is_done = false;
        $last_action = '';

        while ($iterations < $max_iterations && !is_wp_error($response) && $response instanceof \WP_REST_Response) {
            $data = $response->get_data();
            $last_action = $data['action'] ?? '';
            
            if ($last_action === 'tool_calls') {
                $iterations++;
                if (!empty($data['tools'])) {
                    foreach ($data['tools'] as $tool) {
                        $tool_args = !empty($tool['args']) ? json_encode($tool['args']) : '';
                        $tasks[$task_id]['last_log'] = ($tasks[$task_id]['last_log'] ?? '') . "[" . current_time('mysql') . "] Executing tool: " . $tool['name'] . (!empty($tool_args) ? " (Args: " . $tool_args . ")" : "") . "\n";
                    }
                    update_option('wizard_ai_automated_tasks', $tasks);
                }

                if ($iterations >= $max_iterations) {
                    $tasks[$task_id]['last_log'] = ($tasks[$task_id]['last_log'] ?? '') . "[" . current_time('mysql') . "] Yielding execution to next cron cycle to prevent timeouts...\n";
                    update_option('wizard_ai_automated_tasks', $tasks);
                    break; // Will resume next tick
                }
                
                $exec_request = new \WP_REST_Request('POST', '/wizard-ai/v1/ai-chat');
                $exec_request->set_param('conversation_id', $conversation_id);
                $exec_request->set_param('execute_tools', true);
                $exec_request->set_param('is_cron', true);
                if ($crashed_last_time) {
                    $exec_request->set_param('wai_enforce_safe_mode', 1);
                }
                
                $response = $this->handle_chat_request($exec_request);
            } else {
                $is_done = true;
                break; // 'done' or error
            }
        }
        
        // Refresh tasks from DB in case other crons modified it
        $tasks = get_option('wizard_ai_automated_tasks', []);
        
        if (!$is_done && !is_wp_error($response) && $last_action === 'tool_calls') {
            // Task needs more time. Save state to resume next tick.
            $tasks[$task_id]['running_conversation_id'] = $conversation_id;
            $tasks[$task_id]['running_action'] = 'tool_calls';
            $tasks[$task_id]['is_running'] = false; // Safely yielded
        } else {
            // Task completed its logic, but let's verify if it broke the site!
            $test_url = add_query_arg('wai_test', time(), home_url());
            $test_response = wp_remote_get($test_url, ['timeout' => 5, 'sslverify' => false]);
            $is_error = is_wp_error($test_response);
            $code = wp_remote_retrieve_response_code($test_response);
            
            if ($is_error || $code >= 500) {
                // The site is broken! Force a recovery iteration!
                $upload_dir = wp_upload_dir();
                file_put_contents($upload_dir['basedir'] . '/.wai_safe', '1');
                $tasks[$task_id]['running_conversation_id'] = $conversation_id;
                $tasks[$task_id]['running_action'] = 'broken_site';
                $tasks[$task_id]['is_running'] = false; // Yield to next cron
                $tasks[$task_id]['last_log'] = ($tasks[$task_id]['last_log'] ?? '') . "\n[" . current_time('mysql') . "] CRITICAL ERROR: The background task finished, but the website is now returning a 500 Error! Forcing an autonomous recovery iteration in Safe Mode.";
            } else {
                $upload_dir = wp_upload_dir();
                @wp_delete_file($upload_dir['basedir'] . '/.wai_safe');
                // Task finished completely and site is healthy
                unset($tasks[$task_id]['running_conversation_id']);
                unset($tasks[$task_id]['running_action']);
                $tasks[$task_id]['is_running'] = false; // Safely finished
                
                // Only update last_run if it fully completed cleanly
                $tasks[$task_id]['last_run'] = time();
                
                if (!is_wp_error($response) && $response instanceof \WP_REST_Response) {
                    $data = $response->get_data();
                    if (!empty($data['response'])) {
                        $tasks[$task_id]['last_log'] = ($tasks[$task_id]['last_log'] ?? '') . "\n[" . current_time('mysql') . "] Task Completed Successfully.\n" . wp_kses_post(is_string($data['response']) ? $data['response'] : (isset($data['response']['text']) ? $data['response']['text'] : json_encode($data['response'])));
                    } elseif (!empty($data['message'])) {
                        $tasks[$task_id]['last_log'] = ($tasks[$task_id]['last_log'] ?? '') . "\n[" . current_time('mysql') . "] Task Completed.\n" . wp_kses_post($data['message']);
                    }
                } elseif (is_wp_error($response)) {
                    $tasks[$task_id]['last_log'] = ($tasks[$task_id]['last_log'] ?? '') . "\n[" . current_time('mysql') . "] Error: " . $response->get_error_message();
                }
            }
        }

        update_option('wizard_ai_automated_tasks', $tasks);
        
        wp_set_current_user($original_user_id);

        return true;
    }
}
