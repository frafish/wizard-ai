<?php
namespace WizardAi\Modules\Playground\Traits;

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
                                <th><?php esc_html_e('Schedule', 'wizard-ai'); ?></th>
                                <th><?php esc_html_e('Last Run', 'wizard-ai'); ?></th>
                                <th><?php esc_html_e('Status', 'wizard-ai'); ?></th>
                                <th><?php esc_html_e('Actions', 'wizard-ai'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tasks)): ?>
                                <tr><td colspan="6"><?php esc_html_e('No automated tasks scheduled.', 'wizard-ai'); ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($tasks as $id => $task): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($task['name']); ?></strong></td>
                                    <td><code><?php echo esc_html(mb_strimwidth($task['prompt'], 0, 50, '...')); ?></code></td>
                                    <td><?php echo esc_html(ucfirst($task['schedule'])); ?></td>
                                    <td><?php echo $task['last_run'] ? esc_html(date_i18n('Y-m-d H:i:s', $task['last_run'])) : __('Never', 'wizard-ai'); ?></td>
                                    <td><?php echo empty($task['active']) ? '<span style="color:red;">Paused</span>' : '<span style="color:green;">Active</span>'; ?></td>
                                    <td>
                                        <button class="button button-small wai-run-task" data-id="<?php echo esc_attr($id); ?>"><?php esc_html_e('Run Now', 'wizard-ai'); ?></button>
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
                        <label><strong><?php esc_html_e('Prompt', 'wizard-ai'); ?></strong></label><br>
                        <textarea id="wai-task-prompt" style="width: 100%; height: 100px;" placeholder="e.g. Find 3 products without description and write one..."></textarea>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <label><strong><?php esc_html_e('Schedule', 'wizard-ai'); ?></strong></label><br>
                        <select id="wai-task-schedule" style="width: 100%;">
                            <option value="hourly"><?php esc_html_e('Hourly', 'wizard-ai'); ?></option>
                            <option value="twicedaily"><?php esc_html_e('Twice Daily', 'wizard-ai'); ?></option>
                            <option value="daily"><?php esc_html_e('Daily', 'wizard-ai'); ?></option>
                            <option value="weekly"><?php esc_html_e('Weekly', 'wizard-ai'); ?></option>
                        </select>
                    </div>
                    <button class="button button-primary" id="wai-save-task"><?php esc_html_e('Save Task', 'wizard-ai'); ?></button>
                </div>
            </div>
        </div>

        <script>
        document.getElementById('wai-save-task').addEventListener('click', function() {
            const btn = this;
            const name = document.getElementById('wai-task-name').value;
            const prompt = document.getElementById('wai-task-prompt').value;
            const schedule = document.getElementById('wai-task-schedule').value;
            
            if (!name || !prompt) { alert('Name and prompt are required'); return; }
            btn.disabled = true;
            btn.innerText = 'Saving...';
            
            fetch(ajaxurl.replace('admin-ajax.php', '') + 'wp-json/wizard-ai/v1/automation/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>'
                },
                body: JSON.stringify({ name: name, prompt: prompt, schedule: schedule })
            }).then(r => r.json()).then(res => {
                location.reload();
            });
        });

        document.querySelectorAll('.wai-delete-task').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Delete this task?')) return;
                const id = this.dataset.id;
                fetch(ajaxurl.replace('admin-ajax.php', '') + 'wp-json/wizard-ai/v1/automation/delete', {
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
                fetch(ajaxurl.replace('admin-ajax.php', '') + 'wp-json/wizard-ai/v1/automation/run-now', {
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

    public function save_automated_task(\WP_REST_Request $request) {
        $tasks = get_option('wizard_ai_automated_tasks', []);
        $id = uniqid('task_');
        $tasks[$id] = [
            'name' => sanitize_text_field($request->get_param('name')),
            'prompt' => sanitize_textarea_field($request->get_param('prompt')),
            'schedule' => sanitize_text_field($request->get_param('schedule')),
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

    public function run_automated_tasks() {
        $tasks = get_option('wizard_ai_automated_tasks', []);
        if (empty($tasks)) return;

        $now = time();
        $updated = false;

        foreach ($tasks as $id => &$task) {
            if (empty($task['active'])) continue;

            $intervals = [
                'hourly' => 3600,
                'twicedaily' => 43200,
                'daily' => 86400,
                'weekly' => 604800
            ];
            $interval = isset($intervals[$task['schedule']]) ? $intervals[$task['schedule']] : 86400;

            if ($now - $task['last_run'] >= $interval) {
                // Time to run
                $this->_execute_task($id, true);
                $task['last_run'] = $now;
                $updated = true;
            }
        }

        if ($updated) {
            update_option('wizard_ai_automated_tasks', $tasks);
        }
    }

    private function _execute_task($task_id, $is_cron = false) {
        $tasks = get_option('wizard_ai_automated_tasks', []);
        if (!isset($tasks[$task_id])) return false;

        $task = $tasks[$task_id];
        $prompt = "CRON AUTOMATED TASK: " . $task['name'] . "\n\n" . $task['prompt'];

        if (!class_exists('\WordPress\AiClient\AiClient')) return false;

        // Auto approve and safe mode overrides
        add_filter('wizard_ai_auto_approve', '__return_true');

        $request = new \WP_REST_Request('POST', '/wizard-ai/v1/ai-chat');
        $request->set_param('prompt', $prompt);
        $request->set_param('conversation_id', 'cron_' . $task_id . '_' . time());
        
        $this->handle_chat_request($request);
        
        if (!$is_cron) {
            $tasks[$task_id]['last_run'] = time();
            update_option('wizard_ai_automated_tasks', $tasks);
        }

        return true;
    }
}
