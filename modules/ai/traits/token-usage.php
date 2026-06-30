<?php
namespace WizardAi\Modules\Ai\Traits;

trait TokenUsage {
    public function register_token_usage_hooks() {
        add_action('admin_menu', function() {
            add_submenu_page(
                'wizard-ai',
                __('Token Usage', 'wizard-ai'),
                __('Token Usage', 'wizard-ai'),
                'manage_options',
                'wizard-ai-token-usage',
                [$this, 'wbai_token_usage_page_html']
            );
        }, 40); // Priority 40 to add it at the end
    }

    public function wbai_token_usage_page_html() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpai_request_logs';

        // Check if the Core AI plugin logging table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;

        ?>
        <div class="wrap">
            <h1>
                <span class="dashicons dashicons-chart-bar"></span> 
                <?php esc_html_e('AI Token Usage Dashboard', 'wizard-ai'); ?>
            </h1>

            <?php if (!$table_exists): ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('The AI Request Logging table does not exist. Please go to the Core AI plugin settings and enable the "AI Request Logging" experiment to start tracking tokens.', 'wizard-ai'); ?></p>
                </div>
            <?php else: 
                // Aggregate by Provider
                $provider_stats = $wpdb->get_results("
                    SELECT 
                        provider, 
                        COUNT(id) as total_requests,
                        SUM(tokens_input) as total_input,
                        SUM(tokens_output) as total_output,
                        SUM(tokens_total) as total_tokens
                    FROM {$table_name}
                    WHERE status = 'success'
                    GROUP BY provider
                    ORDER BY total_tokens DESC
                ");

                // Aggregate by Model
                $model_stats = $wpdb->get_results("
                    SELECT 
                        provider, 
                        model,
                        COUNT(id) as total_requests,
                        SUM(tokens_input) as total_input,
                        SUM(tokens_output) as total_output,
                        SUM(tokens_total) as total_tokens
                    FROM {$table_name}
                    WHERE status = 'success'
                    GROUP BY provider, model
                    ORDER BY total_tokens DESC
                ");
            ?>
                <p class="description">
                    <?php esc_html_e('This dashboard shows the total tokens consumed by all AI requests logged by the WordPress Core AI plugin. Note: Providers do not expose "available tokens" or account balance via the generation API, so you must check your remaining credits directly on the provider\'s website (e.g. platform.openai.com).', 'wizard-ai'); ?>
                </p>

                <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px;">
                    <!-- Provider Stats -->
                    <div class="card" style="flex: 1; min-width: 300px; padding: 20px; margin: 0;">
                        <h2><?php esc_html_e('Usage by Provider', 'wizard-ai'); ?></h2>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Provider', 'wizard-ai'); ?></th>
                                    <th><?php esc_html_e('Requests', 'wizard-ai'); ?></th>
                                    <th><?php esc_html_e('Input Tokens', 'wizard-ai'); ?></th>
                                    <th><?php esc_html_e('Output Tokens', 'wizard-ai'); ?></th>
                                    <th><?php esc_html_e('Total Tokens', 'wizard-ai'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($provider_stats)): ?>
                                    <tr><td colspan="5"><?php esc_html_e('No data available yet.', 'wizard-ai'); ?></td></tr>
                                <?php else: ?>
                                    <?php foreach ($provider_stats as $stat): ?>
                                        <tr>
                                            <td><strong><?php echo esc_html(ucfirst($stat->provider ?: 'Unknown')); ?></strong></td>
                                            <td><?php echo esc_html(number_format_i18n($stat->total_requests)); ?></td>
                                            <td><?php echo esc_html(number_format_i18n($stat->total_input)); ?></td>
                                            <td><?php echo esc_html(number_format_i18n($stat->total_output)); ?></td>
                                            <td><strong><?php echo esc_html(number_format_i18n($stat->total_tokens)); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Model Stats -->
                    <div class="card" style="flex: 1; min-width: 400px; padding: 20px; margin: 0;">
                        <h2><?php esc_html_e('Usage by Model', 'wizard-ai'); ?></h2>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Model', 'wizard-ai'); ?></th>
                                    <th><?php esc_html_e('Provider', 'wizard-ai'); ?></th>
                                    <th><?php esc_html_e('Requests', 'wizard-ai'); ?></th>
                                    <th><?php esc_html_e('Total Tokens', 'wizard-ai'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($model_stats)): ?>
                                    <tr><td colspan="4"><?php esc_html_e('No data available yet.', 'wizard-ai'); ?></td></tr>
                                <?php else: ?>
                                    <?php foreach ($model_stats as $stat): ?>
                                        <tr>
                                            <td><strong><?php echo esc_html($stat->model ?: 'Unknown'); ?></strong></td>
                                            <td><?php echo esc_html(ucfirst($stat->provider ?: 'Unknown')); ?></td>
                                            <td><?php echo esc_html(number_format_i18n($stat->total_requests)); ?></td>
                                            <td><strong><?php echo esc_html(number_format_i18n($stat->total_tokens)); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
