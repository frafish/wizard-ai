<?php
namespace WizardAi\Modules\Ai\Traits;

trait AbilitiesUi {
    public function wb_ai_abilities_page_html() {
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-superhero"></span> <?php esc_html_e('AI Abilities', 'wizard-ai'); ?></h1>
            <p><?php esc_html_e('The following abilities are registered by Wizard AI and other plugins. These abilities can be used by the Editor Agent, Chatbot, and Playground.', 'wizard-ai'); ?></p>
            
            <?php
            $abilities = [];
            $categories = [];
            if (function_exists('wp_get_abilities')) {
                $abilities = wp_get_abilities();
                if (!empty($abilities)) {
                    foreach ($abilities as $ability) {
                        $cat = $ability->get_category();
                        if ($cat && !in_array($cat, $categories)) {
                            $categories[] = $cat;
                        }
                    }
                    sort($categories);
                }
            }
            ?>

            <div style="margin-bottom: 15px; display: flex; gap: 10px;">
                <input type="text" id="wai-abilities-search" placeholder="<?php esc_attr_e('Search abilities by name...', 'wizard-ai'); ?>" style="width: 100%; max-width: 400px; padding: 6px 12px; font-size: 14px;">
                <select id="wai-abilities-category-filter" style="padding: 6px 12px; font-size: 14px;">
                    <option value=""><?php esc_html_e('All Categories', 'wizard-ai'); ?></option>
                    <?php foreach ($categories as $cat) : ?>
                        <option value="<?php echo esc_attr(strtolower($cat)); ?>"><?php echo esc_html($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <table class="wp-list-table widefat fixed striped" id="wai-abilities-table">
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
                        if (empty($abilities)) {
                            echo '<tr class="wai-no-abilities"><td colspan="3">' . esc_html__('No abilities found.', 'wizard-ai') . '</td></tr>';
                        } else {
                            foreach ($abilities as $ability) {
                                echo '<tr class="wai-ability-row">';
                                echo '<td class="wai-ability-name"><strong>' . esc_html($ability->get_label()) . '</strong></td>';
                                echo '<td>' . esc_html($ability->get_description()) . '</td>';
                                echo '<td class="wai-ability-category">' . esc_html($ability->get_category()) . '</td>';
                                echo '</tr>';
                            }
                        }
                    } else {
                        echo '<tr class="wai-no-abilities"><td colspan="3">' . esc_html__('Abilities API not found. Please install the WordPress Abilities API plugin.', 'wizard-ai') . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const searchInput = document.getElementById('wai-abilities-search');
                const categorySelect = document.getElementById('wai-abilities-category-filter');
                if (!searchInput || !categorySelect) return;

                function filterTable() {
                    const nameFilter = searchInput.value.toLowerCase();
                    const categoryFilter = categorySelect.value;
                    const rows = document.querySelectorAll('#wai-abilities-table tbody tr.wai-ability-row');
                    
                    rows.forEach(row => {
                        const nameEl = row.querySelector('.wai-ability-name');
                        const categoryEl = row.querySelector('.wai-ability-category');
                        if (nameEl && categoryEl) {
                            const name = nameEl.textContent.toLowerCase();
                            const category = categoryEl.textContent.toLowerCase();
                            
                            const matchName = name.includes(nameFilter);
                            const matchCategory = categoryFilter === '' || category === categoryFilter;
                            
                            if (matchName && matchCategory) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        }
                    });
                }

                searchInput.addEventListener('input', filterTable);
                categorySelect.addEventListener('change', filterTable);
            });
        </script>
        <?php
    }
}
