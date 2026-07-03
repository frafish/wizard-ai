<?php
namespace WizardAi\Modules\Ai\Traits;

trait AbilitiesUi {
    public function wb_ai_abilities_page_html() {
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-superhero"></span> <?php esc_html_e('AI Abilities', 'wizard-ai'); ?></h1>
            <p><?php esc_html_e('The following abilities are registered by Wizard AI and other plugins. These abilities can be used by the Editor Agent, Chatbot, and Playground.', 'wizard-ai'); ?></p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 25%;"><?php esc_html_e('Name', 'wizard-ai'); ?></th>
                        <th style="width: 50%;"><?php esc_html_e('Description', 'wizard-ai'); ?></th>
                        <th style="width: 25%;"><?php esc_html_e('Category', 'wizard-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (function_exists('wp_get_abilities')) {
                        $abilities = wp_get_abilities();
                        if (empty($abilities)) {
                            echo '<tr><td colspan="3">' . esc_html__('No abilities found.', 'wizard-ai') . '</td></tr>';
                        } else {
                            foreach ($abilities as $ability) {
                                echo '<tr>';
                                echo '<td><strong>' . esc_html($ability->get_label()) . '</strong></td>';
                                echo '<td>' . esc_html($ability->get_description()) . '</td>';
                                echo '<td>' . esc_html($ability->get_category()) . '</td>';
                                echo '</tr>';
                            }
                        }
                    } else {
                        echo '<tr><td colspan="3">' . esc_html__('Abilities API not found. Please install the WordPress Abilities API plugin.', 'wizard-ai') . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
