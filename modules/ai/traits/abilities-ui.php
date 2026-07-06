<?php
namespace WizardAi\Modules\Ai\Traits;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if (!class_exists('WizardAi\Modules\Ai\Traits\WizardAi_Ability_Table')) {
    class WizardAi_Ability_Table extends \WP_List_Table {
        private $all_abilities = [];

        public function __construct($abilities) {
            $this->all_abilities = $abilities;
            parent::__construct([
                'singular' => 'ability',
                'plural'   => 'abilities',
                'ajax'     => false,
            ]);
        }

        public function get_columns() {
            return [
                'name'        => __('Name', 'wizard-ai'),
                'slug'        => __('Slug', 'wizard-ai'),
                'description' => __('Description', 'wizard-ai'),
                'provider'    => __('Provider', 'wizard-ai'),
                'category'    => __('Category', 'wizard-ai'),
                'actions'     => __('Actions', 'wizard-ai')
            ];
        }

        public function get_sortable_columns() {
            return [
                'name'     => ['name', false],
                'slug'     => ['slug', false],
                'provider' => ['provider', false],
                'category' => ['category', false],
            ];
        }

        public function prepare_items() {
            $columns  = $this->get_columns();
            $hidden   = [];
            $sortable = $this->get_sortable_columns();

            $this->_column_headers = [$columns, $hidden, $sortable];
            $abilities = $this->all_abilities;

            $search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
            if (!empty($search)) {
                $abilities = array_filter($abilities, function($a) use ($search) {
                    return stripos($a['name'], $search) !== false
                        || stripos($a['slug'], $search) !== false
                        || stripos($a['description'], $search) !== false;
                });
            }

            $provider_filter = isset($_REQUEST['provider']) ? sanitize_text_field(wp_unslash($_REQUEST['provider'])) : '';
            if (!empty($provider_filter) && 'all' !== $provider_filter) {
                $abilities = array_filter($abilities, function($a) use ($provider_filter) {
                    if (strpos($provider_filter, 'Plugin:') === 0) {
                        return $a['provider'] === 'Plugin' && ($a['plugin_name'] ?? '') === substr($provider_filter, 7);
                    }
                    if ($provider_filter === 'Plugin') {
                        return $a['provider'] === 'Plugin';
                    }
                    return $a['provider'] === $provider_filter;
                });
            }

            $category_filter = isset($_REQUEST['category']) ? sanitize_text_field(wp_unslash($_REQUEST['category'])) : '';
            if (!empty($category_filter) && 'all' !== $category_filter) {
                $abilities = array_filter($abilities, function($a) use ($category_filter) {
                    return ($a['category'] ?? '') === $category_filter;
                });
            }

            $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'name';
            $order   = isset($_REQUEST['order']) ? sanitize_text_field(wp_unslash($_REQUEST['order'])) : 'asc';

            usort($abilities, function($a, $b) use ($orderby, $order) {
                $result = 0;
                if (isset($a[$orderby]) && isset($b[$orderby])) {
                    $result = strcasecmp($a[$orderby], $b[$orderby]);
                }
                return 'asc' === $order ? $result : -$result;
            });

            $per_page = 20;
            $current_page = $this->get_pagenum();
            $total_items = count($abilities);

            $this->set_pagination_args([
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => (int) ceil($total_items / $per_page),
            ]);

            $this->items = array_slice($abilities, ($current_page - 1) * $per_page, $per_page);
        }

        public function get_unique_categories() {
            $categories = [];
            foreach ($this->all_abilities as $a) {
                if (!empty($a['category'])) {
                    $categories[] = $a['category'];
                }
            }
            $categories = array_unique($categories);
            sort($categories);
            return $categories;
        }

        public function get_unique_plugin_names() {
            $plugins = [];
            foreach ($this->all_abilities as $a) {
                if ($a['provider'] === 'Plugin' && !empty($a['plugin_name'])) {
                    $plugins[] = $a['plugin_name'];
                }
            }
            $plugins = array_unique($plugins);
            sort($plugins);
            return $plugins;
        }

        public function column_default($item, $column_name) {
            return isset($item[$column_name]) ? esc_html($item[$column_name]) : '—';
        }

        public function column_name($item) {
            return sprintf('<strong>%s</strong>', esc_html($item['name']));
        }

        public function column_slug($item) {
            return sprintf('<code>%s</code>', esc_html($item['slug']));
        }

        public function column_description($item) {
            return esc_html($item['description']);
        }

        public function column_provider($item) {
            $provider = $item['provider'];
            $class = 'ability-provider ability-provider-' . strtolower($provider);
            $label = $provider;
            if ($provider === 'Core') $label = __('Core', 'wizard-ai');
            if ($provider === 'Theme') $label = __('Theme', 'wizard-ai');
            if ($provider === 'Plugin') {
                $label = !empty($item['plugin_name']) ? $item['plugin_name'] : __('Plugin', 'wizard-ai');
            }
            return sprintf('<span class="%s">%s</span>', esc_attr($class), esc_html($label));
        }

        public function column_actions($item) {
            $detail_url = add_query_arg([
                'page'    => 'wizard-ai-abilities',
                'action'  => 'view',
                'ability' => $item['slug'],
            ], admin_url('admin.php'));

            $test_url = add_query_arg([
                'page'    => 'wizard-ai-abilities',
                'action'  => 'test',
                'ability' => $item['slug'],
            ], admin_url('admin.php'));

            return sprintf(
                '<a href="%s" class="button button-small">%s</a> <a href="%s" class="button button-small button-primary">%s</a>',
                esc_url($detail_url),
                esc_html__('View', 'wizard-ai'),
                esc_url($test_url),
                esc_html__('Test', 'wizard-ai')
            );
        }

        public function extra_tablenav($which) {
            if ('top' !== $which) return;

            $provider_filter = isset($_REQUEST['provider']) ? sanitize_text_field(wp_unslash($_REQUEST['provider'])) : 'all';
            $category_filter = isset($_REQUEST['category']) ? sanitize_text_field(wp_unslash($_REQUEST['category'])) : 'all';
            ?>
            <div class="alignleft actions">
                <select name="provider">
                    <option value="all" <?php selected($provider_filter, 'all'); ?>><?php esc_html_e('All Providers', 'wizard-ai'); ?></option>
                    <option value="Core" <?php selected($provider_filter, 'Core'); ?>><?php esc_html_e('Core', 'wizard-ai'); ?></option>
                    
                    <optgroup label="<?php esc_attr_e('Plugins', 'wizard-ai'); ?>">
                        <option value="Plugin" <?php selected($provider_filter, 'Plugin'); ?>><?php esc_html_e('All Plugins', 'wizard-ai'); ?></option>
                        <?php foreach ($this->get_unique_plugin_names() as $plugin_name) : ?>
                            <option value="Plugin:<?php echo esc_attr($plugin_name); ?>" <?php selected($provider_filter, 'Plugin:' . $plugin_name); ?>>— <?php echo esc_html($plugin_name); ?></option>
                        <?php endforeach; ?>
                    </optgroup>

                    <option value="Theme" <?php selected($provider_filter, 'Theme'); ?>><?php esc_html_e('Theme', 'wizard-ai'); ?></option>
                </select>

                <select name="category">
                    <option value="all" <?php selected($category_filter, 'all'); ?>><?php esc_html_e('All Categories', 'wizard-ai'); ?></option>
                    <?php foreach ($this->get_unique_categories() as $category) : ?>
                        <option value="<?php echo esc_attr($category); ?>" <?php selected($category_filter, $category); ?>><?php echo esc_html($category); ?></option>
                    <?php endforeach; ?>
                </select>

                <?php submit_button(__('Filter', 'wizard-ai'), '', 'filter_action', false); ?>
            </div>
            <?php
        }
    }
}

trait AbilitiesUi {
    
    private function get_ability_array($slug) {
        $ability_obj = function_exists('wp_get_ability') ? wp_get_ability($slug) : null;
        if (!$ability_obj) return null;
        
        $meta = $ability_obj->get_meta();
        $parts = explode('/', $ability_obj->get_name());
        $provider = 'Plugin';
        $ns = '';
        if (isset($meta['provider'])) {
            $provider = $meta['provider'];
        } elseif (count($parts) === 2) {
            $ns = $parts[0];
            if (in_array($ns, ['wordpress', 'wp', 'core'])) $provider = 'Core';
            elseif (get_stylesheet() === $ns || get_template() === $ns) $provider = 'Theme';
        }
        
        $plugin_name = '';
        if ($provider === 'Plugin') {
            if (!empty($meta['plugin_name'])) {
                $plugin_name = $meta['plugin_name'];
            } elseif (!empty($ns)) {
                if (!function_exists('get_plugins')) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }
                $plugins = get_plugins();
                foreach ($plugins as $plugin_path => $plugin_data) {
                    if (strpos($plugin_path, $ns . '/') === 0 || $plugin_path === $ns . '.php') {
                        $plugin_name = $plugin_data['Name'];
                        break;
                    }
                }
                if (empty($plugin_name)) {
                    $plugin_name = ucwords(str_replace(['-', '_'], ' ', $ns));
                    if (strtolower($plugin_name) === 'wizard ai') $plugin_name = 'Wizard AI';
                }
            }
        }
        
        $cat_slug = $ability_obj->get_category();
        $cat_label = 'Other';
        if (!empty($cat_slug)) {
            if (function_exists('wp_get_ability_category') && function_exists('wp_has_ability_category') && wp_has_ability_category($cat_slug)) {
                $cat_obj = wp_get_ability_category($cat_slug);
                $cat_label = $cat_obj->get_label();
            } else {
                $cat_label = ucwords(str_replace(['-', '_'], ' ', $cat_slug));
                if (strtolower($cat_label) === 'woocommerce') $cat_label = 'WooCommerce';
            }
        } elseif (!empty($ns)) {
            $cat_label = ucwords(str_replace(['-', '_'], ' ', $ns));
            if (strtolower($cat_label) === 'woocommerce') $cat_label = 'WooCommerce';
        }

        return [
            'slug' => $ability_obj->get_name(),
            'name' => $ability_obj->get_label(),
            'description' => $ability_obj->get_description(),
            'provider' => $provider,
            'plugin_name' => $plugin_name,
            'input_schema' => $ability_obj->get_input_schema(),
            'output_schema' => $ability_obj->get_output_schema(),
            'raw_data' => [
                'name' => $ability_obj->get_name(),
                'label' => $ability_obj->get_label(),
                'description' => $ability_obj->get_description(),
                'category' => $cat_slug,
                'input_schema' => $ability_obj->get_input_schema(),
                'output_schema' => $ability_obj->get_output_schema(),
                'meta' => $meta
            ]
        ];
    }

    private function render_detail_view() {
        $ability_slug = isset($_GET['ability']) ? sanitize_text_field(wp_unslash($_GET['ability'])) : '';
        if (empty($ability_slug)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('No ability specified.', 'wizard-ai') . '</p></div>';
            return;
        }

        $ability = $this->get_ability_array($ability_slug);
        if (!$ability) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Ability not found.', 'wizard-ai') . '</p></div>';
            return;
        }

        $back_url = admin_url('admin.php?page=wizard-ai-abilities');
        $test_url = add_query_arg([
            'page'    => 'wizard-ai-abilities',
            'action'  => 'test',
            'ability' => $ability_slug,
        ], admin_url('admin.php'));
        
        $provider_label = $ability['provider'];
        if ($provider_label === 'Core') $provider_label = __('Core', 'wizard-ai');
        if ($provider_label === 'Theme') $provider_label = __('Theme', 'wizard-ai');
        if ($provider_label === 'Plugin') {
            $provider_label = !empty($ability['plugin_name']) ? $ability['plugin_name'] : __('Plugin', 'wizard-ai');
        }

        ?>
        <div class="ability-explorer-detail">
            <div class="ability-detail-header">
                <a href="<?php echo esc_url($back_url); ?>" class="button"><?php echo wp_kses_post(__('&larr; Back to List', 'wizard-ai')); ?></a>
                <a href="<?php echo esc_url($test_url); ?>" class="button button-primary"><?php esc_html_e('Test Ability', 'wizard-ai'); ?></a>
            </div>

            <h2><?php echo esc_html($ability['name']); ?></h2>
            <p class="ability-detail-slug"><code><?php echo esc_html($ability['slug']); ?></code></p>

            <?php if (!empty($ability['description'])) : ?>
                <div class="ability-detail-section">
                    <h3><?php esc_html_e('Description', 'wizard-ai'); ?></h3>
                    <p><?php echo esc_html($ability['description']); ?></p>
                </div>
            <?php endif; ?>

            <div class="ability-detail-section">
                <h3><?php esc_html_e('Details', 'wizard-ai'); ?></h3>
                <table class="ability-detail-table">
                    <tr>
                        <th><?php esc_html_e('Provider', 'wizard-ai'); ?></th>
                        <td><span class="ability-provider ability-provider-<?php echo esc_attr(strtolower($ability['provider'])); ?>"><?php echo esc_html($provider_label); ?></span></td>
                    </tr>
                </table>
            </div>

            <?php if (!empty($ability['input_schema'])) : ?>
                <div class="ability-detail-section">
                    <h3><?php esc_html_e('Input Schema', 'wizard-ai'); ?></h3>
                    <div class="ability-schema-wrapper">
                        <button type="button" class="button button-small ability-copy-btn" data-copy="input-schema"><?php esc_html_e('Copy', 'wizard-ai'); ?></button>
                        <pre class="ability-schema-display" id="input-schema"><?php echo esc_html((string) wp_json_encode($ability['input_schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($ability['output_schema'])) : ?>
                <div class="ability-detail-section">
                    <h3><?php esc_html_e('Output Schema', 'wizard-ai'); ?></h3>
                    <div class="ability-schema-wrapper">
                        <button type="button" class="button button-small ability-copy-btn" data-copy="output-schema"><?php esc_html_e('Copy', 'wizard-ai'); ?></button>
                        <pre class="ability-schema-display" id="output-schema"><?php echo esc_html((string) wp_json_encode($ability['output_schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
                    </div>
                </div>
            <?php endif; ?>

            <div class="ability-detail-section">
                <h3><?php esc_html_e('Raw Data', 'wizard-ai'); ?></h3>
                <div class="ability-schema-wrapper">
                    <button type="button" class="button button-small ability-copy-btn" data-copy="raw-data"><?php esc_html_e('Copy', 'wizard-ai'); ?></button>
                    <pre class="ability-schema-display" id="raw-data"><?php echo esc_html((string) wp_json_encode($ability['raw_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_test_runner() {
        $ability_slug = isset($_GET['ability']) ? sanitize_text_field(wp_unslash($_GET['ability'])) : '';
        if (empty($ability_slug)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('No ability specified.', 'wizard-ai') . '</p></div>';
            return;
        }

        $ability = $this->get_ability_array($ability_slug);
        if (!$ability) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Ability not found.', 'wizard-ai') . '</p></div>';
            return;
        }

        $back_url = admin_url('admin.php?page=wizard-ai-abilities');
        $detail_url = add_query_arg([
            'page'    => 'wizard-ai-abilities',
            'action'  => 'view',
            'ability' => $ability_slug,
        ], admin_url('admin.php'));

        $example_input = $this->generate_example_input($ability['input_schema']);

        ?>
        <div class="ability-explorer-test-runner">
            <div class="ability-detail-header">
                <a href="<?php echo esc_url($back_url); ?>" class="button"><?php echo wp_kses_post(__('&larr; Back to List', 'wizard-ai')); ?></a>
                <a href="<?php echo esc_url($detail_url); ?>" class="button"><?php esc_html_e('View Details', 'wizard-ai'); ?></a>
            </div>

            <h2><?php esc_html_e('Test Ability:', 'wizard-ai'); ?> <?php echo esc_html($ability['name']); ?></h2>
            <p class="ability-detail-slug"><code><?php echo esc_html($ability['slug']); ?></code></p>

            <?php if (!empty($ability['description'])) : ?>
                <p class="description"><?php echo esc_html($ability['description']); ?></p>
            <?php endif; ?>

            <div class="ability-detail-section ability-test-editor">
                <h3><?php esc_html_e('Input Data', 'wizard-ai'); ?></h3>
                <?php if (empty($ability['input_schema'])) : ?>
                    <div class="notice notice-warning inline" style="margin: 10px 0;">
                        <p>
                            <strong><?php esc_html_e('No Input Required', 'wizard-ai'); ?></strong><br>
                            <?php esc_html_e('This ability does not accept any input parameters. Simply click "Invoke Ability" to execute it.', 'wizard-ai'); ?>
                        </p>
                    </div>
                <?php else : ?>
                    <p class="description">
                        <?php esc_html_e('Edit the JSON input below to test the ability. The input will be validated against the input schema if available.', 'wizard-ai'); ?>
                    </p>
                    <div class="notice notice-info inline" style="margin: 10px 0;">
                        <p>
                            <strong><?php esc_html_e('How to test:', 'wizard-ai'); ?></strong><br>
                            <ol>
                                <li><?php esc_html_e('Edit the JSON input below with your test data', 'wizard-ai'); ?></li>
                                <li><?php esc_html_e('Click "Validate Input" to check your JSON is correct', 'wizard-ai'); ?></li>
                                <li><?php esc_html_e('Click "Invoke Ability" to execute the ability with your input', 'wizard-ai'); ?></li>
                                <li><?php esc_html_e('View the results below', 'wizard-ai'); ?></li>
                            </ol>
                        </p>
                    </div>
                <?php endif; ?>

                <label for="ability-test-payload" class="screen-reader-text"><?php esc_html_e('Ability test input (JSON)', 'wizard-ai'); ?></label>
                <textarea id="ability-test-payload" rows="12" style="width:100%; font-family:monospace;"><?php echo esc_textarea((string) wp_json_encode($example_input, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></textarea>

                <div class="ability-test-actions" style="margin-top:10px;">
                    <button type="button" id="ability-test-invoke" class="button button-primary" data-ability="<?php echo esc_attr($ability_slug); ?>">
                        <?php esc_html_e('Invoke Ability', 'wizard-ai'); ?>
                    </button>
                    <button type="button" id="ability-test-validate" class="button">
                        <?php esc_html_e('Validate Input', 'wizard-ai'); ?>
                    </button>
                    <button type="button" id="ability-test-clear" class="button">
                        <?php esc_html_e('Clear Result', 'wizard-ai'); ?>
                    </button>
                </div>

                <div id="ability-test-validation" class="ability-test-validation" style="display: none; margin-top:10px;"></div>
            </div>

            <div class="ability-detail-section ability-test-result-container" id="ability-test-result-container" style="display: none;">
                <h3><?php esc_html_e('Result', 'wizard-ai'); ?></h3>
                <div id="ability-test-result" class="ability-schema-wrapper"></div>
            </div>

            <?php if (!empty($ability['input_schema'])) : ?>
                <div class="ability-detail-section ability-test-schema">
                    <h3><?php esc_html_e('Input Schema Reference', 'wizard-ai'); ?></h3>
                    <div class="ability-schema-wrapper">
                        <button type="button" class="button button-small ability-copy-btn" data-copy="test-input-schema"><?php esc_html_e('Copy', 'wizard-ai'); ?></button>
                        <pre class="ability-schema-display" id="test-input-schema"><?php echo esc_html((string) wp_json_encode($ability['input_schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <script type="application/json" id="ability-input-schema">
            <?php echo wp_json_encode($ability['input_schema'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>
        </script>
        <?php
    }

    private function generate_example_input($schema) {
        if (empty($schema) || !isset($schema['properties'])) return [];
        $input = [];
        foreach ($schema['properties'] as $prop_name => $prop_schema) {
            if (isset($prop_schema['default'])) {
                $input[$prop_name] = $prop_schema['default'];
            } elseif (isset($prop_schema['example'])) {
                $input[$prop_name] = $prop_schema['example'];
            } else {
                $type = $prop_schema['type'] ?? 'string';
                if ($type === 'string') $input[$prop_name] = '';
                elseif ($type === 'integer' || $type === 'number') $input[$prop_name] = 0;
                elseif ($type === 'boolean') $input[$prop_name] = false;
                elseif ($type === 'array') $input[$prop_name] = [];
                elseif ($type === 'object') $input[$prop_name] = new \stdClass();
                else $input[$prop_name] = null;
            }
        }
        return $input;
    }
    public function wb_ai_abilities_page_html() {
                if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wizard-ai'));
        }
        
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';
        
        echo '<div class="wrap ability-explorer-wrap">';
        echo '<h1><span class="dashicons dashicons-superhero"></span> ' . esc_html__('AI Abilities', 'wizard-ai') . '</h1>';
        
        if ($action === 'view') {
            $this->render_detail_view();
            echo '</div>';
            return;
        } elseif ($action === 'test') {
            $this->render_test_runner();
            echo '</div>';
            return;
        }

        $abilities_raw = function_exists('wp_get_abilities') ? wp_get_abilities() : [];
        $abilities = [];
        $stats = [
            'total' => 0,
            'by_provider' => ['Core' => 0, 'Plugin' => 0, 'Theme' => 0]
        ];

        foreach ($abilities_raw as $ability) {
            $name = $ability->get_name();
            $meta = $ability->get_meta();
            
            $parts = explode('/', $name);
            $provider_type = 'Plugin';
            $ns = '';
            if (isset($meta['provider'])) {
                $provider_type = $meta['provider'];
            } elseif (count($parts) === 2) {
                $ns = $parts[0];
                if (in_array($ns, ['wordpress', 'wp', 'core'])) $provider_type = 'Core';
                elseif (get_stylesheet() === $ns || get_template() === $ns) $provider_type = 'Theme';
            }

            $plugin_name = '';
            if ($provider_type === 'Plugin') {
                if (!empty($meta['plugin_name'])) {
                    $plugin_name = $meta['plugin_name'];
                } elseif (!empty($ns)) {
                    if (!function_exists('get_plugins')) {
                        require_once ABSPATH . 'wp-admin/includes/plugin.php';
                    }
                    $plugins = get_plugins();
                    foreach ($plugins as $plugin_path => $plugin_data) {
                        if (strpos($plugin_path, $ns . '/') === 0 || $plugin_path === $ns . '.php') {
                            $plugin_name = $plugin_data['Name'];
                            break;
                        }
                    }
                    if (empty($plugin_name)) {
                        $plugin_name = ucwords(str_replace(['-', '_'], ' ', $ns));
                        if (strtolower($plugin_name) === 'wizard ai') $plugin_name = 'Wizard AI';
                    }
                }
            }
            
            $cat_slug = $ability->get_category();
            $cat_label = 'Other';
            if (!empty($cat_slug)) {
                if (function_exists('wp_get_ability_category') && function_exists('wp_has_ability_category') && wp_has_ability_category($cat_slug)) {
                    $cat_obj = wp_get_ability_category($cat_slug);
                    $cat_label = $cat_obj->get_label();
                } else {
                    $cat_label = ucwords(str_replace(['-', '_'], ' ', $cat_slug));
                    if (strtolower($cat_label) === 'woocommerce') $cat_label = 'WooCommerce';
                }
            } elseif (!empty($ns)) {
                $cat_label = ucwords(str_replace(['-', '_'], ' ', $ns));
                if (strtolower($cat_label) === 'woocommerce') $cat_label = 'WooCommerce';
            }
            
            $abilities[] = [
                'slug' => $name,
                'name' => $ability->get_label(),
                'description' => $ability->get_description(),
                'provider' => $provider_type,
                'plugin_name' => $plugin_name,
                'category' => $cat_label
            ];
            
            $stats['total']++;
            if (!isset($stats['by_provider'][$provider_type])) $stats['by_provider'][$provider_type] = 0;
            $stats['by_provider'][$provider_type]++;
        }
        ?>
            
            <p><?php esc_html_e('The following abilities are registered by Wizard AI and other plugins. These abilities can be used by the Editor Agent, Chatbot, and Playground.', 'wizard-ai'); ?></p>
            
            <div class="ability-explorer-stats">
                <a href="<?php echo esc_url(admin_url('admin.php?page=wizard-ai-abilities')); ?>" class="ability-stat-card">
                    <div class="ability-stat-number"><?php echo absint($stats['total']); ?></div>
                    <div class="ability-stat-label"><?php esc_html_e('Total Abilities', 'wizard-ai'); ?></div>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wizard-ai-abilities&provider=Core')); ?>" class="ability-stat-card">
                    <div class="ability-stat-number"><?php echo absint($stats['by_provider']['Core'] ?? 0); ?></div>
                    <div class="ability-stat-label"><?php esc_html_e('Core', 'wizard-ai'); ?></div>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wizard-ai-abilities&provider=Plugin')); ?>" class="ability-stat-card">
                    <div class="ability-stat-number"><?php echo absint($stats['by_provider']['Plugin'] ?? 0); ?></div>
                    <div class="ability-stat-label"><?php esc_html_e('Plugins', 'wizard-ai'); ?></div>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wizard-ai-abilities&provider=Theme')); ?>" class="ability-stat-card">
                    <div class="ability-stat-number"><?php echo absint($stats['by_provider']['Theme'] ?? 0); ?></div>
                    <div class="ability-stat-label"><?php esc_html_e('Theme', 'wizard-ai'); ?></div>
                </a>
            </div>

            <?php
            $table = new WizardAi_Ability_Table($abilities);
            $table->prepare_items();
            ?>
            <form method="get">
                <input type="hidden" name="page" value="wizard-ai-abilities" />
                <?php
                $table->search_box(__('Search Abilities', 'wizard-ai'), 'ability');
                $table->display();
                ?>
            </form>
        </div>
        <?php
    }
}
