<?php
namespace WizardAi\Modules\Wpml;

class Wpml {

    public function __construct() {
        if (!class_exists('SitePress')) return;
        
        add_action('admin_footer', [$this, 'inject_wpml_ai_buttons']);
        add_action('rest_api_init', [$this, 'register_wpml_translate_route']);
        add_action('admin_menu', [$this, 'add_wpml_ai_menu'], 5);

        // Hooks for auto-updating translations
        add_action('post_updated', [$this, 'schedule_translation_updates'], 10, 3);
        add_action('saved_term', [$this, 'schedule_term_translation_updates'], 10, 3);
        
        // Cron handlers
        add_action('wbai_wpml_update_translations', [$this, 'process_translation_updates'], 10, 1);
        add_action('wbai_wpml_update_term_translations', [$this, 'process_term_translation_updates'], 10, 2);
        
        // WPML Native Translation Management Interceptor
        add_action('init', [$this, 'register_wizard_ai_translator_user']);
        add_action('wpml_added_local_translation_job', [$this, 'intercept_wpml_tm_job'], 10, 1);
        add_action('wbai_process_wpml_tm_job', [$this, 'process_wpml_tm_job'], 10, 1);
    }

    public function register_wpml_translate_route() {
        register_rest_route('wizard-blocks/v1', '/wpml-translate', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_wpml_translate'],
            'permission_callback' => function () { return current_user_can('edit_posts'); }
        ]);
        register_rest_route('wizard-blocks/v1', '/wpml-get-missing', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_wpml_get_missing'],
            'permission_callback' => function () { return current_user_can('edit_posts'); }
        ]);
        register_rest_route('wizard-blocks/v1', '/wpml-strings-get-missing', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_wpml_strings_get_missing'],
            'permission_callback' => function () { return current_user_can('edit_posts'); }
        ]);
        register_rest_route('wizard-blocks/v1', '/wpml-strings-translate', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_wpml_strings_translate'],
            'permission_callback' => function () { return current_user_can('edit_posts'); }
        ]);
    }

    public function add_wpml_ai_menu() {
        // Register using WPML's native custom menu API
        do_action('wpml_admin_menu_register_item', [
            'order' => 800,
            'page_title' => __('AI Bulk Translator', 'wizard-ai'),
            'menu_title' => __('AI Bulk Translator', 'wizard-ai'),
            'capability' => 'manage_options',
            'menu_slug' => 'wbai-wpml-bulk',
            'function' => [$this, 'render_wpml_bulk_page']
        ]);
    }

    public function render_wpml_bulk_page() {
        if (isset($_POST['wbai_wpml_settings_nonce']) && wp_verify_nonce($_POST['wbai_wpml_settings_nonce'], 'wbai_wpml_settings')) {
            if (isset($_POST['wbai_clear_logs'])) {
                $upload_dir = wp_upload_dir();
                $log_file = $upload_dir['basedir'] . '/wbai/logs/wpml.log';
                if (file_exists($log_file)) {
                    unlink($log_file);
                }
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Logs cleared.', 'wizard-ai') . '</p></div>';
            } else {
                update_option('wbai_wpml_model', sanitize_text_field($_POST['wbai_wpml_model']));
                update_option('wbai_wpml_auto_fallback', isset($_POST['wbai_wpml_auto_fallback']) ? 1 : 0);
                
                $fallback_models = isset($_POST['wbai_wpml_fallback_models']) && is_array($_POST['wbai_wpml_fallback_models']) 
                    ? array_map('sanitize_text_field', $_POST['wbai_wpml_fallback_models']) : [];
                update_option('wbai_wpml_fallback_models', $fallback_models);
                
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'wizard-ai') . '</p></div>';
            }
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'translate';

        global $sitepress, $wpdb;
        $active_langs = $sitepress->get_active_languages();
        $default_lang = $sitepress->get_default_language();
        
        $post_types = [];
        if ($sitepress) {
            $translatable_docs = $sitepress->get_translatable_documents(true);
            $excluded_pts = ['product_variation', 'shop_order', 'shop_order_refund', 'shop_coupon', 'shop_order_placehold', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_font_family', 'wp_font_face', 'wp_sync_storage', 'acf-field-group'];
            foreach ($translatable_docs as $pt_name => $pt_obj) {
                if (in_array($pt_name, $excluded_pts)) continue; // Exclude system, transactional, and HPOS order types
                
                $has_items = $wpdb->get_var($wpdb->prepare("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type = %s AND language_code = %s LIMIT 1", 'post_' . $pt_name, $default_lang));
                if (!$has_items) continue;
                
                if (isset($pt_obj->label)) {
                    $post_types[] = $pt_obj;
                }
            }
        }
        
        $taxonomies = [];
        if ($sitepress) {
            $translatable_tax = $sitepress->get_translatable_taxonomies(true);
            foreach ($translatable_tax as $tax_name) {
                $has_items = $wpdb->get_var($wpdb->prepare("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type = %s AND language_code = %s LIMIT 1", 'tax_' . $tax_name, $default_lang));
                if (!$has_items) continue;
                
                $tax_obj = get_taxonomy($tax_name);
                if ($tax_obj) {
                    $taxonomies[] = $tax_obj;
                }
            }
        }
        $string_domains = [];
        $string_domains_grouped = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}icl_strings'")) {
            $string_domains = $wpdb->get_col("SELECT DISTINCT context FROM {$wpdb->prefix}icl_strings ORDER BY context ASC");
            
            $string_domains_grouped = [
                'Theme' => [],
                'Plugins' => [],
                'Blocks' => [],
                'Settings / Admin' => [],
                'Other' => []
            ];
            
            $theme_slugs = [get_stylesheet(), get_template()];
            $plugin_slugs = [];
            foreach (get_option('active_plugins', []) as $plugin) {
                $plugin_slugs[] = dirname($plugin);
            }
            
            foreach ($string_domains as $domain) {
                if (strpos($domain, 'admin_texts_') === 0) {
                    $string_domains_grouped['Settings / Admin'][] = $domain;
                } elseif (strpos($domain, 'gutenberg_') === 0 || strpos($domain, 'block') !== false) {
                    $string_domains_grouped['Blocks'][] = $domain;
                } elseif (in_array($domain, $theme_slugs)) {
                    $string_domains_grouped['Theme'][] = $domain;
                } elseif (in_array($domain, $plugin_slugs) || strpos($domain, 'plugin-') === 0) {
                    $string_domains_grouped['Plugins'][] = $domain;
                } else {
                    $string_domains_grouped['Other'][] = $domain;
                }
            }
        }
        
        wp_enqueue_style('wpml-dashboard', plugins_url('sitepress-multilingual-cms/vendor/wpml/wpml/public/css/dashboard.css'), [], null);
        ?>
        <div class="wrap" id="wpml-dashboard">
            <h1 style="margin-bottom: 16px;"><?php esc_html_e('Wizard AI Translation Dashboard', 'wizard-ai'); ?></h1>
            
            <h2 class="nav-tab-wrapper icl-translation-management-menu">
                <a href="?page=wbai-wpml-bulk&tab=translate" class="nav-tab <?php echo $active_tab === 'translate' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Content Translation', 'wizard-ai'); ?>
                </a>
                <?php if (!empty($string_domains)): ?>
                <a href="?page=wbai-wpml-bulk&tab=strings" class="nav-tab <?php echo $active_tab === 'strings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('String Translation', 'wizard-ai'); ?>
                </a>
                <?php endif; ?>
                <a href="?page=wbai-wpml-bulk&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('AI Models & Settings', 'wizard-ai'); ?>
                </a>
            </h2>

            <?php if ($active_tab === 'translate'): ?>
            <div class="icl_tm_wrap">
                <div class="wpml-tm-dashboard-message info with-close-button" style="margin-bottom: 24px;">
                    <div class="wpml-tm-dashboard-message-content">
                        <?php esc_html_e('Select an object type and target language to find untranslated items and generate translations automatically via AI.', 'wizard-ai'); ?>
                    </div>
                </div>
                
                <div class="wpml-global-filter">
                    <div class="wpml-flex-space-between">
                        <div class="wpml-flex-sb-container global-filters-wrapper" style="width: 100%; display:flex; gap:16px;">
                            
                            <div class="select-dropdown">
                                <label for="wbai_wpml_type" style="font-weight: 500; margin-right: 8px;"><?php esc_html_e('Type:', 'wizard-ai'); ?></label>
                                <select id="wbai_wpml_type">
                                    <optgroup label="Post Types">
                                        <?php foreach ($post_types as $pt) {
                                            echo '<option value="post_' . esc_attr($pt->name) . '">' . esc_html($pt->label) . ' (' . esc_html($pt->name) . ')</option>';
                                        } ?>
                                    </optgroup>
                                    <optgroup label="Taxonomies">
                                        <?php foreach ($taxonomies as $tax) {
                                            echo '<option value="tax_' . esc_attr($tax->name) . '">' . esc_html($tax->label) . ' (' . esc_html($tax->name) . ')</option>';
                                        } ?>
                                    </optgroup>
                                </select>
                            </div>
                            
                            <div class="select-dropdown">
                                <label for="wbai_wpml_lang" style="font-weight: 500; margin-right: 8px;"><?php esc_html_e('Language:', 'wizard-ai'); ?></label>
                                <select id="wbai_wpml_lang">
                                    <?php foreach ($active_langs as $lang) {
                                        if ($lang['code'] === $default_lang) continue;
                                        echo '<option value="' . esc_attr($lang['code']) . '">' . esc_html($lang['display_name']) . '</option>';
                                    } ?>
                                </select>
                            </div>

                            <div class="select-dropdown">
                                <label for="wbai_wpml_status" style="font-weight: 500; margin-right: 8px;"><?php esc_html_e('Status:', 'wizard-ai'); ?></label>
                                <select id="wbai_wpml_status">
                                    <option value="both"><?php esc_html_e('Not Translated & Needs Update (Both)', 'wizard-ai'); ?></option>
                                    <option value="not_translated"><?php esc_html_e('Not Translated', 'wizard-ai'); ?></option>
                                    <option value="needs_update"><?php esc_html_e('Needs Update', 'wizard-ai'); ?></option>
                                </select>
                            </div>
                            
                            <div style="margin-left: auto;">
                                <button class="wpml-button base-btn wpml-button--outlined filter-button" id="wbai_scan_missing">
                                    <?php esc_html_e('Filter', 'wizard-ai'); ?>
                                </button>
                            </div>
                            
                        </div>
                    </div>
                </div>
                
                <div id="wbai_bulk_results" class="wpml-item-type wpml-content-box string-section" style="display:none; margin-top: 24px; padding:0;">
                    <div class="wpml-item-type-element-list" style="padding:0;">
                        <div style="padding: 15px; border-bottom: 2px solid #373737; display:flex; justify-content:space-between; align-items:center; background: #fafafa;">
                            <div style="display: flex; align-items: center; gap: 20px;">
                                <label class="wpml-checkbox" style="margin: 0;"><input type="checkbox" id="wbai_select_all" checked> <span><?php esc_html_e('Select All', 'wizard-ai'); ?> (<span id="wbai_missing_count">0</span> items)</span></label>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <label for="wbai_local_search" style="font-weight: 500;"><?php esc_html_e('Filter by:', 'wizard-ai'); ?></label>
                                    <input type="text" id="wbai_local_search" placeholder="<?php esc_attr_e('Title...', 'wizard-ai'); ?>" style="padding: 4px 8px; border: 1px solid #ccc; border-radius: 4px; min-width: 250px;">
                                </div>
                            </div>
                            <button class="wpml-button base-btn" id="wbai_start_bulk">
                                <?php esc_html_e('Translate Selected via AI', 'wizard-ai'); ?>
                            </button>
                        </div>
                        <div id="wbai_missing_list" style="max-height: 400px; overflow-y: auto;"></div>
                        <div id="wbai_bulk_progress" style="padding:15px; font-weight:500;"></div>
                    </div>
                </div>
            </div>
            <?php elseif ($active_tab === 'strings'): ?>
            <div class="icl_tm_wrap">
                <div class="wpml-tm-dashboard-message info with-close-button" style="margin-bottom: 24px;">
                    <div class="wpml-tm-dashboard-message-content">
                        <?php esc_html_e('Select a domain and target language to find untranslated strings and generate translations automatically via AI.', 'wizard-ai'); ?>
                    </div>
                </div>
                
                <div class="wpml-global-filter">
                    <div class="wpml-flex-space-between">
                        <div class="wpml-flex-sb-container global-filters-wrapper" style="width: 100%; display:flex; gap:16px;">
                            
                            <div class="select-dropdown">
                                <label for="wbai_wpml_string_domain" style="font-weight: 500; margin-right: 8px;"><?php esc_html_e('Domain:', 'wizard-ai'); ?></label>
                                <select id="wbai_wpml_string_domain">
                                    <?php 
                                    foreach ($string_domains_grouped as $group_name => $domains) {
                                        if (empty($domains)) continue;
                                        echo '<optgroup label="' . esc_attr($group_name) . '">';
                                        foreach ($domains as $domain) {
                                            echo '<option value="' . esc_attr($domain) . '">' . esc_html($domain) . '</option>';
                                        }
                                        echo '</optgroup>';
                                    } 
                                    ?>
                                </select>
                            </div>

                            <div class="select-dropdown">
                                <label for="wbai_wpml_string_lang" style="font-weight: 500; margin-right: 8px;"><?php esc_html_e('Target Language:', 'wizard-ai'); ?></label>
                                <select id="wbai_wpml_string_lang">
                                    <?php foreach ($active_langs as $lang) {
                                        if ($lang['code'] === $default_lang) continue;
                                        echo '<option value="' . esc_attr($lang['code']) . '">' . esc_html($lang['display_name']) . '</option>';
                                    } ?>
                                </select>
                            </div>
                            
                            <button class="wpml-button base-btn wpml-button--outlined filter-button" id="wbai_scan_strings">
                                <?php esc_html_e('Filter Strings', 'wizard-ai'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div id="wbai_bulk_strings_results" style="display:none; margin-top: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <div style="padding: 15px; border-bottom: 2px solid #373737; display:flex; justify-content:space-between; align-items:center; background: #fafafa;">
                        <div style="display: flex; align-items: center; gap: 20px;">
                            <label class="wpml-checkbox" style="margin: 0;"><input type="checkbox" id="wbai_select_all_strings" checked> <span><?php esc_html_e('Select All', 'wizard-ai'); ?> (<span id="wbai_missing_strings_count">0</span> items)</span></label>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <label for="wbai_local_search_strings" style="font-weight: 500;"><?php esc_html_e('Filter by:', 'wizard-ai'); ?></label>
                                <input type="text" id="wbai_local_search_strings" placeholder="<?php esc_attr_e('String name or text...', 'wizard-ai'); ?>" style="padding: 4px 8px; border: 1px solid #ccc; border-radius: 4px; min-width: 250px;">
                            </div>
                        </div>
                        <button class="wpml-button base-btn" id="wbai_start_bulk_strings">
                            <?php esc_html_e('Translate Strings via AI', 'wizard-ai'); ?>
                        </button>
                    </div>
                    <div id="wbai_missing_strings_list" style="max-height: 500px; overflow-y: auto;"></div>
                    <div id="wbai_bulk_strings_progress" style="padding:15px; font-weight:500;"></div>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                let pendingStrings = [];
                let isTranslatingStrings = false;
                let currentStringLang = '';
                
                $('#wbai_scan_strings').on('click', function(e) {
                    e.preventDefault();
                    if (isTranslatingStrings) return;
                    
                    let domain = $('#wbai_wpml_string_domain').val();
                    let lang = $('#wbai_wpml_string_lang').val();
                    currentStringLang = lang;
                    
                    let btn = $(this);
                    btn.prop('disabled', true).text('Scanning...');
                    
                    $.ajax({
                        url: '<?php echo esc_url_raw(rest_url('wizard-blocks/v1/wpml-strings-get-missing')); ?>',
                        method: 'GET',
                        data: { domain: domain, target_lang: lang },
                        beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>'); },
                        success: function(res) {
                            btn.prop('disabled', false).text('Filter Strings');
                            if (res.success) {
                                pendingStrings = res.items;
                                $('#wbai_missing_strings_count').text(pendingStrings.length);
                                
                                let html = '';
                                if (pendingStrings.length > 0) {
                                    pendingStrings.forEach(function(item) {
                                        html += '<div class="string-item" style="padding:12px 10px; border-bottom:1px solid #eee; display: flex; align-items: flex-start;">' + 
                                                '<label class="wpml-checkbox" style="margin-right: 12px; margin-bottom: 0;"><input type="checkbox" class="wbai-string-checkbox" value="'+item.id+'" checked> <span></span></label> ' +
                                                '<div style="flex-grow:1; max-width:80%;">' +
                                                    '<div style="font-weight:600; color:#1d2327; margin-bottom:4px;" class="string-name">'+item.name+'</div>' +
                                                    '<div style="color:#50575e; font-size:13px; line-height:1.4; word-wrap: break-word;" class="string-value">'+item.value+'</div>' +
                                                '</div>' +
                                                '<div style="margin-left: auto; white-space:nowrap;"><span class="dashicons dashicons-plus" style="color: #2271b1;" title="Missing Translation"></span></div>' +
                                                '</div>';
                                    });
                                    $('#wbai_start_bulk_strings').show();
                                    $('#wbai_select_all_strings').prop('disabled', false).prop('checked', true).parent().show();
                                } else {
                                    html = '<p style="padding:15px;">No missing string translations found.</p>';
                                    $('#wbai_start_bulk_strings').hide();
                                    $('#wbai_select_all_strings').prop('disabled', false).parent().hide();
                                }
                                $('#wbai_missing_strings_list').html(html);
                                $('#wbai_local_search_strings').val('');
                                $('#wbai_bulk_strings_results').fadeIn();
                                $('#wbai_bulk_strings_progress').text('');
                            } else {
                                alert('Error: ' + res.message);
                            }
                        },
                        error: function() {
                            btn.prop('disabled', false).text('Filter Strings');
                            alert('Error communicating with server.');
                        }
                    });
                });
                
                $('#wbai_local_search_strings').on('keyup', function() {
                    let searchVal = $(this).val().toLowerCase();
                    $('#wbai_missing_strings_list > div').each(function() {
                        let text = $(this).find('.string-name').text().toLowerCase() + ' ' + $(this).find('.string-value').text().toLowerCase();
                        if (text.indexOf(searchVal) > -1) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                });
                
                $('#wbai_select_all_strings').on('change', function() {
                    $('.wbai-string-checkbox').prop('checked', $(this).is(':checked'));
                });
                
                $('#wbai_start_bulk_strings').on('click', function() {
                    let selectedIds = [];
                    $('.wbai-string-checkbox:checked').each(function() {
                        selectedIds.push($(this).val().toString());
                    });
                    
                    if (selectedIds.length === 0) {
                        alert('Please select at least one string to translate.');
                        return;
                    }
                    
                    pendingStrings = pendingStrings.filter(item => selectedIds.includes(item.id.toString()));
                    
                    if (!confirm('Are you sure you want to translate ' + pendingStrings.length + ' strings via AI?')) return;
                    
                    $(this).prop('disabled', true);
                    $('.wbai-string-checkbox').prop('disabled', true);
                    $('#wbai_select_all_strings').prop('disabled', true);
                    isTranslatingStrings = true;
                    processNextString();
                });
                
                function processNextString() {
                    if (pendingStrings.length === 0) {
                        $('#wbai_bulk_strings_progress').text('String Translation Complete!');
                        $('#wbai_start_bulk_strings').prop('disabled', false).hide();
                        isTranslatingStrings = false;
                        return;
                    }
                    
                    let item = pendingStrings.shift();
                    $('#wbai_bulk_strings_progress').text('Translating: ' + item.name + ' (' + pendingStrings.length + ' remaining...)');
                    
                    $.ajax({
                        url: '<?php echo esc_url_raw(rest_url('wizard-blocks/v1/wpml-strings-translate')); ?>',
                        method: 'POST',
                        data: { string_id: item.id, target_lang: currentStringLang },
                        beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>'); },
                        success: function(res) {
                            if (res.success) {
                                $('#wbai_missing_strings_list').find('input[value="'+item.id+'"]').closest('div').css('opacity', '0.5').find('.dashicons').removeClass('dashicons-plus').addClass('dashicons-yes-alt').css('color', '#46b450');
                            } else {
                                $('#wbai_missing_strings_list').find('input[value="'+item.id+'"]').closest('div').find('.dashicons').removeClass('dashicons-plus').addClass('dashicons-warning').css('color', '#dc3232');
                            }
                            processNextString();
                        },
                        error: function() {
                            $('#wbai_missing_strings_list').find('input[value="'+item.id+'"]').closest('div').find('.dashicons').removeClass('dashicons-plus').addClass('dashicons-warning').css('color', '#dc3232');
                            processNextString();
                        }
                    });
                }
            });
            </script>
            <?php elseif ($active_tab === 'settings'):
                $models = [];
                if (class_exists('\WordPress\AiClient\AiClient')) {
                    $registry = \WordPress\AiClient\AiClient::defaultRegistry();
                    $requirements = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
                        [\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration()], []
                    );
                    $providerModels = $registry->findModelsMetadataForSupport($requirements);
                    foreach ($providerModels as $providerMetadata) {
                        $providerName = $providerMetadata->getProvider()->getName();
                        foreach ($providerMetadata->getModels() as $modelMeta) {
                            $id = $modelMeta->getId();
                            $modelName = $modelMeta->getName() ?: $id;
                            $providerId = $providerMetadata->getProvider()->getId();
                            
                            // Extra safety filter against any accidentally matched non-text models
                            if (stripos($id, 'dall-e') !== false || stripos($id, 'midjourney') !== false) continue;
                            
                            $models[$providerId . '|' . $id] = '[' . $providerName . '] ' . $modelName;
                        }
                    }
                }
                $selected_model = get_option('wbai_wpml_model', '');
                $auto_fallback = get_option('wbai_wpml_auto_fallback', 0);
                $saved_fallback_models = get_option('wbai_wpml_fallback_models', []);
            ?>
            <div class="icl_tm_wrap">
                <form method="post" action="">
                    <?php wp_nonce_field('wbai_wpml_settings', 'wbai_wpml_settings_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="wbai_wpml_model"><?php esc_html_e('Preferred Translation Model', 'wizard-ai'); ?></label></th>
                            <td>
                                <select name="wbai_wpml_model" id="wbai_wpml_model" style="min-width: 300px;">
                                    <option value=""><?php esc_html_e('&mdash; Auto Detect Best Model &mdash;', 'wizard-ai'); ?></option>
                                    <?php foreach ($models as $val => $label): ?>
                                        <option value="<?php echo esc_attr($val); ?>" <?php selected($selected_model, $val); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('Select the primary AI model to use for translations. These models support Text Generation. If empty, your globally preferred model will be used.', 'wizard-ai'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Auto-Fallback', 'wizard-ai'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="wbai_wpml_auto_fallback" value="1" <?php checked($auto_fallback, 1); ?>>
                                    <?php esc_html_e('Automatically switch to another model if the selected model fails (e.g. rate limit reached, out of tokens)', 'wizard-ai'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Trusted Fallback Models', 'wizard-ai'); ?></th>
                            <td>
                                <p class="description" style="margin-bottom:10px;"><?php esc_html_e('Select which models are allowed to be used as fallbacks during batch translation to prevent failures. If none are selected, all available text models will be used.', 'wizard-ai'); ?></p>
                                <select name="wbai_wpml_fallback_models[]" multiple style="min-width: 300px; height: 150px;">
                                    <?php foreach ($models as $val => $label): ?>
                                        <option value="<?php echo esc_attr($val); ?>" <?php echo in_array($val, $saved_fallback_models) ? 'selected' : ''; ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'wizard-ai'); ?></button>
                    </p>
                </form>
                
                <h3 style="margin-top: 30px;"><?php esc_html_e('Recent Error Logs', 'wizard-ai'); ?></h3>
                <?php 
                $upload_dir = wp_upload_dir();
                $log_file = $upload_dir['basedir'] . '/wbai/logs/wpml.log';
                $logs = [];
                if (file_exists($log_file)) {
                    $log_content = file_get_contents($log_file);
                    $logs = array_filter(explode(PHP_EOL, $log_content));
                    $logs = array_slice(array_reverse($logs), 0, 100);
                }
                ?>
                <div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; max-height: 300px; overflow-y: auto;">
                    <?php if (empty($logs)): ?>
                        <p><?php esc_html_e('No errors logged.', 'wizard-ai'); ?></p>
                    <?php else: ?>
                        <ul style="list-style: none; padding: 0; margin: 0; font-family: monospace;">
                            <?php foreach ($logs as $log): ?>
                                <li style="border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 5px; color: #d63638;">
                                    <?php echo esc_html($log); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <?php if (!empty($logs)): ?>
                    <form method="post" action="" style="margin-top: 10px;">
                        <?php wp_nonce_field('wbai_wpml_settings', 'wbai_wpml_settings_nonce'); ?>
                        <input type="hidden" name="wbai_clear_logs" value="1">
                        <button type="submit" class="button"><?php esc_html_e('Clear Logs', 'wizard-ai'); ?></button>
                    </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            let pendingItems = [];
            let isTranslating = false;
            let currentLang = '';
            
            $('#wbai_scan_missing').on('click', function() {
                let typeStr = $('#wbai_wpml_type').val();
                let lang = $('#wbai_wpml_lang').val();
                let statusVal = $('#wbai_wpml_status').val();
                currentLang = lang;
                
                $('#wbai_refresh_bulk').hide();
                
                let btn = $(this);
                btn.prop('disabled', true).text('Scanning...');
                
                $.ajax({
                    url: '<?php echo esc_url_raw(rest_url('wizard-blocks/v1/wpml-get-missing')); ?>',
                    method: 'GET',
                    data: { type: typeStr, target_lang: lang, status: statusVal },
                    beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>'); },
                    success: function(res) {
                        btn.prop('disabled', false).text('Filter');
                        if (res.success) {
                            pendingItems = res.items;
                            $('#wbai_missing_count').text(pendingItems.length);
                            
                            let html = '';
                            if (pendingItems.length > 0) {
                                pendingItems.forEach(function(item) {
                                    let icon = item.status === 'needs_update' 
                                        ? '<span class="dashicons dashicons-update" style="color: #f0b849; vertical-align: text-bottom;" title="<?php esc_attr_e('Needs Update', 'wizard-ai'); ?>"></span>' 
                                        : '<span class="dashicons dashicons-plus" style="color: #2271b1; vertical-align: text-bottom;" title="<?php esc_attr_e('Missing Translation', 'wizard-ai'); ?>"></span>';
                                    
                                    html += '<div id="item-'+item.id+'" style="padding:12px 10px; border-bottom:1px solid #eee; display: flex; align-items: center;">' + 
                                            '<label class="wpml-checkbox" style="margin-right: 12px; margin-bottom: 0;"><input type="checkbox" class="wbai-item-checkbox" value="'+item.id+'" checked> <span></span></label> ' +
                                            '<a href="'+item.edit_url+'" target="_blank" style="text-decoration:none; font-weight:500; margin-right: 12px;">'+item.title+'</a>' + 
                                            item.langs_html +
                                            '<div style="margin-left: auto; display: flex; align-items: center; gap: 8px;">' + icon + ' <span style="color:#888;">ID: '+item.id+'</span></div>' +
                                            '</div>';
                                });
                                $('#wbai_start_bulk').show();
                                $('#wbai_select_all').prop('disabled', false).prop('checked', true).parent().show();
                            } else {
                                html = '<p>No missing translations found for this language.</p>';
                                $('#wbai_start_bulk').hide();
                                $('#wbai_select_all').prop('disabled', false).parent().hide();
                            }
                            $('#wbai_missing_list').html(html);
                            $('#wbai_local_search').val('');
                            $('#wbai_bulk_results').fadeIn();
                            $('#wbai_bulk_progress').text('');
                        } else {
                            alert('Error: ' + res.message);
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false).text('Filter');
                        alert('Error communicating with server.');
                    }
                });
            });
            
            $('#wbai_local_search').on('keyup', function() {
                let searchVal = $(this).val().toLowerCase();
                $('#wbai_missing_list > div').each(function() {
                    let title = $(this).find('a').first().text().toLowerCase();
                    if (title.indexOf(searchVal) > -1) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
            
            $('#wbai_select_all').on('change', function() {
                $('.wbai-item-checkbox').prop('checked', $(this).is(':checked'));
            });
            
            $('#wbai_start_bulk').on('click', function() {
                let selectedIds = [];
                $('.wbai-item-checkbox:checked').each(function() {
                    selectedIds.push($(this).val().toString());
                });
                
                if (selectedIds.length === 0) {
                    alert('Please select at least one item to translate.');
                    return;
                }
                
                // Filter pendingItems to only those selected
                pendingItems = pendingItems.filter(item => selectedIds.includes(item.id.toString()));
                
                if (!confirm('Are you sure you want to translate ' + pendingItems.length + ' items? This may consume AI tokens.')) return;
                
                $(this).prop('disabled', true);
                $('.wbai-item-checkbox').prop('disabled', true);
                $('#wbai_select_all').prop('disabled', true);
                isTranslating = true;
                processNext();
            });
            
            function processNext() {
                if (pendingItems.length === 0) {
                    $('#wbai_bulk_progress').text('Translation Complete!');
                    $('#wbai_start_bulk').prop('disabled', false).hide();
                    isTranslating = false;
                    
                    if ($('#wbai_refresh_bulk').length === 0) {
                        $('#wbai_start_bulk').after('<button class="wpml-button base-btn wpml-button--outlined" id="wbai_refresh_bulk" style="margin-left:10px;">Refresh List</button>');
                        $('#wbai_refresh_bulk').on('click', function() {
                            $('#wbai_scan_missing').trigger('click');
                        });
                    }
                    $('#wbai_refresh_bulk').show();
                    
                    return;
                }
                
                let item = pendingItems.shift();
                $('#wbai_bulk_progress').text('Translating: ' + item.title + ' (' + pendingItems.length + ' remaining...)');
                $('#item-'+item.id).css('background', '#fff8e5');
                
                $.ajax({
                    url: '<?php echo esc_url_raw(rest_url('wizard-blocks/v1/wpml-translate')); ?>',
                    method: 'POST',
                    data: {
                        object_id: item.id,
                        object_type: item.object_type,
                        taxonomy: item.taxonomy,
                        target_lang: currentLang
                    },
                    beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>'); },
                    success: function(res) {
                        if (res.success) {
                            $('#item-'+item.id).css('background', '#e5ffe5').append('<span style="background:#00a32a; color:#fff; padding:2px 8px; border-radius:10px; font-size:11px; margin-left:10px; font-weight:600; text-transform:uppercase;">Success</span>');
                            if (res.edit_url) {
                                let flagImg = $('#item-'+item.id).find('img[alt="'+currentLang+'"]');
                                if (flagImg.length > 0) {
                                    flagImg.css({ opacity: '1', filter: 'none' });
                                    let parent = flagImg.parent();
                                    if (parent.is('span') && !parent.attr('title')) {
                                        parent.replaceWith('<a href="'+res.edit_url+'" target="_blank" style="display:flex;">' + flagImg.prop('outerHTML') + '</a>');
                                    }
                                }
                            }
                        } else {
                            $('#item-'+item.id).css('background', '#ffe5e5').append('<span style="background:#d63638; color:#fff; padding:2px 8px; border-radius:10px; font-size:11px; margin-left:10px; font-weight:600; text-transform:uppercase;">Failed</span>');
                            console.error('Translation error for item ID ' + item.id + ':', res.message || res);
                        }
                        processNext();
                    },
                    error: function(xhr, status, error) {
                        $('#item-'+item.id).css('background', '#ffe5e5').append('<span style="background:#d63638; color:#fff; padding:2px 8px; border-radius:10px; font-size:11px; margin-left:10px; font-weight:600; text-transform:uppercase;">Failed</span>');
                        console.error('Translation AJAX failed for item ID ' + item.id + ':', error);
                        processNext();
                    }
                });
            }
        });
        </script>
        <?php
    }

    public function handle_wpml_get_missing(\WP_REST_Request $request) {
        $type_str = sanitize_text_field($request->get_param('type'));
        $target_lang = sanitize_text_field($request->get_param('target_lang'));
        $status_filter = sanitize_text_field($request->get_param('status'));
        if (!$status_filter) $status_filter = 'both';
        
        if (!$type_str || !$target_lang) return new \WP_REST_Response(['success' => false, 'message' => 'Missing parameters.'], 400);
        
        global $sitepress, $wpdb;
        if (!$sitepress) return new \WP_REST_Response(['success' => false, 'message' => 'WPML not active.'], 500);
        
        $items = [];
        $default_lang = $sitepress->get_default_language();
        
        if (strpos($type_str, 'post_') === 0) {
            $post_type = substr($type_str, 5);
            $el_type = 'post_' . $post_type;
            
            $source_elements = $wpdb->get_results($wpdb->prepare(
                "SELECT p.ID, p.post_title, t.trid, t.language_code AS orig_lang
                FROM {$wpdb->posts} p
                JOIN {$wpdb->prefix}icl_translations t ON p.ID = t.element_id 
                WHERE t.element_type = %s AND p.post_type = %s AND p.post_status IN ('publish', 'draft') AND t.source_language_code IS NULL",
                $el_type, $post_type
            ));
            
            foreach ($source_elements as $el) {
                $trans = $wpdb->get_row($wpdb->prepare(
                    "SELECT t.translation_id, t.element_id, s.needs_update 
                     FROM {$wpdb->prefix}icl_translations t
                     LEFT JOIN {$wpdb->prefix}icl_translation_status s ON t.translation_id = s.translation_id
                     WHERE t.trid = %d AND t.language_code = %s",
                    $el->trid, $target_lang
                ));
                
                $is_missing = empty($trans);
                $needs_update = !empty($trans) && (int)$trans->needs_update === 1;
                
                $match = false;
                if ($status_filter === 'not_translated' && $is_missing) $match = true;
                elseif ($status_filter === 'needs_update' && $needs_update) $match = true;
                elseif ($status_filter === 'both' && ($is_missing || $needs_update)) $match = true;
                
                if ($match) {
                    $translations = $sitepress->get_element_translations($el->trid, $el_type);
                    $langs_html = '<span style="margin-left:15px; display:inline-flex; align-items:center; gap:3px;">';
                    foreach ($sitepress->get_active_languages() as $lcode => $lang) {
                        $is_original = ($lcode === $el->orig_lang);
                        $has_trans = isset($translations[$lcode]) && !empty($translations[$lcode]->element_id);
                        $flag_url = $sitepress->get_flag_url($lcode);
                        $style = 'vertical-align:middle; width:18px; height:12px;';
                        if (!$has_trans && !$is_original) $style .= ' opacity:0.3; filter:grayscale(100%);';
                        
                        $img = '<img src="' . esc_url($flag_url) . '" alt="' . esc_attr($lcode) . '" title="' . esc_attr($lang['display_name']) . '" style="' . $style . '">';
                        if ($is_original) $img = '<span style="border:1px solid #2271b1; padding:1px; border-radius:2px; display:flex;" title="' . esc_attr__('Main Language', 'wizard-ai') . '">' . $img . '</span>';
                        
                        if ($has_trans) {
                            $edit_url = get_edit_post_link($translations[$lcode]->element_id, 'raw');
                            $langs_html .= '<a href="' . esc_url($edit_url) . '" target="_blank" style="display:flex;">' . $img . '</a>';
                        } else {
                            $langs_html .= '<span style="display:flex;">' . $img . '</span>';
                        }
                    }
                    $langs_html .= '</span>';

                    $items[] = [
                        'id' => $el->ID,
                        'title' => $el->post_title,
                        'object_type' => 'post',
                        'taxonomy' => '',
                        'edit_url' => get_edit_post_link($el->ID, 'raw'),
                        'status' => $needs_update ? 'needs_update' : 'missing',
                        'langs_html' => $langs_html
                    ];
                }
            }
            
        } elseif (strpos($type_str, 'tax_') === 0) {
            $taxonomy = substr($type_str, 4);
            $el_type = 'tax_' . $taxonomy;
            
            $source_elements = $wpdb->get_results($wpdb->prepare(
                "SELECT tm.term_id, t.name, trans.trid, trans.language_code AS orig_lang
                FROM {$wpdb->term_taxonomy} tm
                JOIN {$wpdb->terms} t ON tm.term_id = t.term_id
                JOIN {$wpdb->prefix}icl_translations trans ON tm.term_taxonomy_id = trans.element_id 
                WHERE trans.element_type = %s AND tm.taxonomy = %s AND trans.source_language_code IS NULL",
                $el_type, $taxonomy
            ));
            
            foreach ($source_elements as $el) {
                $trans = $wpdb->get_row($wpdb->prepare(
                    "SELECT t.translation_id, t.element_id, s.needs_update 
                     FROM {$wpdb->prefix}icl_translations t
                     LEFT JOIN {$wpdb->prefix}icl_translation_status s ON t.translation_id = s.translation_id
                     WHERE t.trid = %d AND t.language_code = %s",
                    $el->trid, $target_lang
                ));
                
                $is_missing = empty($trans);
                $needs_update = !empty($trans) && (int)$trans->needs_update === 1;
                
                $match = false;
                if ($status_filter === 'not_translated' && $is_missing) $match = true;
                elseif ($status_filter === 'needs_update' && $needs_update) $match = true;
                elseif ($status_filter === 'both' && ($is_missing || $needs_update)) $match = true;
                
                if ($match) {
                    $translations = $sitepress->get_element_translations($el->trid, $el_type);
                    $langs_html = '<span style="margin-left:15px; display:inline-flex; align-items:center; gap:3px;">';
                    foreach ($sitepress->get_active_languages() as $lcode => $lang) {
                        $is_original = ($lcode === $el->orig_lang);
                        $has_trans = isset($translations[$lcode]) && !empty($translations[$lcode]->element_id);
                        $flag_url = $sitepress->get_flag_url($lcode);
                        $style = 'vertical-align:middle; width:18px; height:12px;';
                        if (!$has_trans && !$is_original) $style .= ' opacity:0.3; filter:grayscale(100%);';
                        
                        $img = '<img src="' . esc_url($flag_url) . '" alt="' . esc_attr($lcode) . '" title="' . esc_attr($lang['display_name']) . '" style="' . $style . '">';
                        if ($is_original) $img = '<span style="border:1px solid #2271b1; padding:1px; border-radius:2px; display:flex;" title="' . esc_attr__('Main Language', 'wizard-ai') . '">' . $img . '</span>';
                        
                        if ($has_trans) {
                            $edit_url = html_entity_decode(get_edit_term_link((int)$translations[$lcode]->element_id, $taxonomy, $post_type ?? ''));
                            $langs_html .= '<a href="' . esc_url($edit_url) . '" target="_blank" style="display:flex;">' . $img . '</a>';
                        } else {
                            $langs_html .= '<span style="display:flex;">' . $img . '</span>';
                        }
                    }
                    $langs_html .= '</span>';

                    $items[] = [
                        'id' => $el->term_id,
                        'title' => $el->name,
                        'object_type' => 'term',
                        'taxonomy' => $taxonomy,
                        'edit_url' => html_entity_decode(get_edit_term_link($el->term_id, $taxonomy, $post_type ?? '')),
                        'status' => $needs_update ? 'needs_update' : 'missing',
                        'langs_html' => $langs_html
                    ];
                }
            }
        }
        
        return new \WP_REST_Response(['success' => true, 'items' => $items], 200);
    }

    public function inject_wpml_ai_buttons() {
        $screen = get_current_screen();
        if (!$screen) return;
        
        if ($screen->base !== 'post' && $screen->base !== 'term' && $screen->base !== 'edit-tags') return;
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            function injectButtons() {
                // Post edit screen (icl_div)
                $('#icl_div .icl_lang_row').each(function() {
                    let $row = $(this);
                    if ($row.find('.wbai-translate-btn').length > 0) return;
                    
                    let langCode = $row.find('input[name^="icl_multi_"]').attr('name');
                    if (langCode) {
                        langCode = langCode.replace('icl_multi_', '');
                    } else {
                        // Sometimes WPML structures this differently, check for translation links
                        let $link = $row.find('a[href*="lang="]');
                        if ($link.length) {
                            let url = new URL($link.attr('href'), window.location.origin);
                            langCode = url.searchParams.get('lang');
                        }
                    }
                    
                    if (langCode && $row.find('.dashicons-plus').length) {
                        let $btn = $('<button type="button" class="button button-small wbai-translate-btn" data-lang="'+langCode+'" style="margin-left:5px;" title="Translate with AI"><span class="dashicons dashicons-admin-site-alt3" style="font-size:14px;line-height:22px;margin-top:2px;"></span> AI</button>');
                        $row.find('.icl_lang_row_status').append($btn);
                    }
                });
                
                // Taxonomy edit screen
                $('[id^="icl_tax_"]').each(function() {
                    let $wrapper = $(this);
                    $wrapper.find('tr').each(function() {
                        let $tr = $(this);
                        if ($tr.find('.wbai-translate-btn').length > 0) return;
                        
                        let $link = $tr.find('a[href*="lang="]');
                        if ($link.length && $tr.find('.dashicons-plus').length) {
                            let url = new URL($link.attr('href'), window.location.origin);
                            let langCode = url.searchParams.get('lang');
                            
                            if (langCode) {
                                let $btn = $('<button type="button" class="button button-small wbai-translate-btn" data-lang="'+langCode+'" style="margin-left:5px;" title="Translate with AI"><span class="dashicons dashicons-admin-site-alt3" style="font-size:14px;line-height:22px;margin-top:2px;"></span> AI</button>');
                                $tr.find('td').last().append($btn);
                            }
                        }
                    });
                });
                
                // WP List Table (Posts and Terms)
                $('table.wp-list-table tbody tr').each(function() {
                    let $tr = $(this);
                    let rowId = $tr.attr('id');
                    if (!rowId) return;
                    
                    let objectId = '';
                    let objectType = '';
                    let tax = '';
                    
                    if (rowId.startsWith('post-')) {
                        objectId = rowId.replace('post-', '');
                        objectType = 'post';
                    } else if (rowId.startsWith('tag-')) {
                        objectId = rowId.replace('tag-', '');
                        objectType = 'term';
                    } else {
                        return;
                    }
                    
                    $tr.find('a[href*="source_lang="]').each(function() {
                        let $addLink = $(this);
                        if ($addLink.next('.wbai-list-translate-btn').length > 0) return;
                        
                        let href = $addLink.attr('href');
                        let url;
                        try {
                            url = new URL(href, window.location.origin);
                        } catch(e) { return; }
                        
                        let langCode = url.searchParams.get('lang');
                        if (objectType === 'term') tax = url.searchParams.get('taxonomy');
                        
                        if (langCode) {
                            let $btn = $('<button type="button" class="button button-small wbai-list-translate-btn" data-lang="'+langCode+'" data-id="'+objectId+'" data-type="'+objectType+'" data-tax="'+tax+'" style="margin-left:4px; padding: 0 4px; min-height: 20px; line-height: 18px;" title="Translate with AI"><span class="dashicons dashicons-admin-site-alt3" style="font-size:12px;line-height:18px;"></span></button>');
                            $addLink.after($btn);
                        }
                    });
                });
            }
            
            injectButtons();
            
            $(document).on('click', '.wbai-translate-btn', function(e) {
                e.preventDefault();
                let $btn = $(this);
                let lang = $btn.data('lang');
                let postId = $('#post_ID').val();
                let tagId = $('input[name="tag_ID"]').val();
                let tax = $('input[name="taxonomy"]').val();
                
                let objectId = postId ? postId : tagId;
                let objectType = postId ? 'post' : 'term';
                
                if (!objectId) {
                    alert('Could not determine object ID. Please save first.');
                    return;
                }
                
                $btn.html('<span class="dashicons dashicons-update-alt" style="font-size:14px;line-height:22px;margin-top:2px;animation: rotation 2s infinite linear;"></span>');
                $btn.prop('disabled', true);
                
                $.ajax({
                    url: '<?php echo esc_url_raw(rest_url('wizard-blocks/v1/wpml-translate')); ?>',
                    method: 'POST',
                    headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' },
                    data: {
                        object_id: objectId,
                        object_type: objectType,
                        taxonomy: tax,
                        target_lang: lang
                    },
                    success: function(res) {
                        if (res.success) {
                            alert('Translated successfully!');
                            window.location.reload();
                        } else {
                            alert('Error: ' + res.message);
                            $btn.html('<span class="dashicons dashicons-admin-site-alt3" style="font-size:14px;line-height:22px;margin-top:2px;"></span> AI');
                            $btn.prop('disabled', false);
                        }
                    },
                    error: function(err) {
                        alert('Error communicating with server.');
                        $btn.html('<span class="dashicons dashicons-admin-site-alt3" style="font-size:14px;line-height:22px;margin-top:2px;"></span> AI');
                        $btn.prop('disabled', false);
                    }
                });
            });
            
            $(document).on('click', '.wbai-list-translate-btn', function(e) {
                e.preventDefault();
                let $btn = $(this);
                let lang = $btn.data('lang');
                let objId = $btn.data('id');
                let objType = $btn.data('type');
                let tax = $btn.data('tax');
                
                $btn.html('<span class="dashicons dashicons-update-alt" style="font-size:12px;line-height:18px;animation: rotation 2s infinite linear;"></span>');
                $btn.prop('disabled', true);
                
                $.ajax({
                    url: '<?php echo esc_url_raw(rest_url('wizard-blocks/v1/wpml-translate')); ?>',
                    method: 'POST',
                    headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' },
                    data: {
                        object_id: objId,
                        object_type: objType,
                        taxonomy: tax,
                        target_lang: lang
                    },
                    success: function(res) {
                        if (res.success) {
                            $btn.replaceWith('<span class="dashicons dashicons-yes-alt" style="color:green;font-size:14px;line-height:20px;margin-left:4px;" title="Translated!"></span>');
                        } else {
                            alert('Error: ' + res.message);
                            $btn.html('<span class="dashicons dashicons-admin-site-alt3" style="font-size:12px;line-height:18px;"></span>');
                            $btn.prop('disabled', false);
                        }
                    },
                    error: function() {
                        alert('Error communicating with server.');
                        $btn.html('<span class="dashicons dashicons-admin-site-alt3" style="font-size:12px;line-height:18px;"></span>');
                        $btn.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <style>
        @keyframes rotation { from { transform: rotate(0deg); } to { transform: rotate(359deg); } }
        </style>
        <?php
    }

    public function handle_wpml_translate(\WP_REST_Request $request) {
        $object_id = intval($request->get_param('object_id'));
        $object_type = sanitize_text_field($request->get_param('object_type'));
        $target_lang = sanitize_text_field($request->get_param('target_lang'));
        $taxonomy = sanitize_text_field($request->get_param('taxonomy'));
        
        if (!$object_id || !$target_lang) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Missing parameters.'], 400);
        }
        
        if (!class_exists('\WordPress\AiClient\AiClient')) {
            return new \WP_REST_Response(['success' => false, 'message' => 'AI Client not available.'], 500);
        }
        
        global $sitepress, $wpdb;
        if (!$sitepress) {
            return new \WP_REST_Response(['success' => false, 'message' => 'WPML not active.'], 500);
        }

        $source_lang = $sitepress->get_default_language();
        
        $translated_title = '';
        $translated_content = '';
        $translated_excerpt = '';
        $edit_url = '';
        $new_post_id = 0;
        $trid = 0;

        if ($object_type === 'post') {
            $post = get_post($object_id);
            if (!$post) return new \WP_REST_Response(['success' => false, 'message' => 'Post not found.'], 404);
            
            $trid = $sitepress->get_element_trid($post->ID, 'post_' . $post->post_type);
            if (!$trid) return new \WP_REST_Response(['success' => false, 'message' => 'Translation group not found.'], 404);
            
            $translatable_meta_keys = $sitepress->get_custom_fields_translation_settings(2); // WPML_TRANSLATE_CUSTOM_FIELD = 2
            $custom_fields_to_translate = [];
            foreach ($translatable_meta_keys as $meta_key) {
                $meta_values = get_post_meta($post->ID, $meta_key);
                foreach ($meta_values as $val) {
                    if (is_string($val) && trim($val) !== '') {
                        if (!isset($custom_fields_to_translate[$meta_key])) {
                            $custom_fields_to_translate[$meta_key] = [];
                        }
                        $custom_fields_to_translate[$meta_key][] = $val;
                    }
                }
            }
            
            $prompt = "You are an expert translator. Translate the following WordPress post into the language represented by the ISO code '{$target_lang}' (e.g. 'it' = Italian, 'fr' = French, 'es' = Spanish).\n";
            $prompt .= "IMPORTANT RULES:\n";
            $prompt .= "1. You MUST preserve ALL HTML tags, attributes, and Gutenberg block comments (e.g. <!-- wp:paragraph -->) EXACTLY as they are. Do not translate or modify them.\n";
            $prompt .= "2. ONLY translate the visible text content inside the tags/blocks. DO NOT alter ANY image URLs (src), link URLs (href), or image/block IDs (e.g. {\"id\":123}).\n";
            
            if (!empty($custom_fields_to_translate)) {
                $prompt .= "3. Respond ONLY with a valid JSON object matching this structure:\n";
                $prompt .= "{\n  \"title\": \"...\",\n  \"content\": \"...\",\n  \"excerpt\": \"...\",\n  \"custom_fields\": {\n";
                foreach ($custom_fields_to_translate as $k => $v) {
                    $prompt .= "    \"$k\": " . (count($v) > 1 ? "[\"...\", \"...\"]" : "\"...\"") . "\n";
                }
                $prompt .= "  }\n}\nDo not include any other text.\n\n";
            } else {
                $prompt .= "3. Respond ONLY with a valid JSON object containing the keys: 'title', 'content', and 'excerpt'. Do not include any other text.\n\n";
            }
            
            $prompt .= "TITLE:\n" . $post->post_title . "\n\n";
            $prompt .= "EXCERPT:\n" . $post->post_excerpt . "\n\n";
            $prompt .= "CONTENT:\n" . $post->post_content . "\n\n";
            
            if (!empty($custom_fields_to_translate)) {
                $prompt .= "CUSTOM FIELDS (translate the values):\n";
                $prompt .= json_encode($custom_fields_to_translate, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
            
            $result = $this->call_ai_for_json($prompt);
            
            if (is_wp_error($result)) {
                return new \WP_REST_Response(['success' => false, 'message' => $result->get_error_message()], 500);
            }
            if (!$result || !isset($result['title']) || !isset($result['content'])) {
                return new \WP_REST_Response(['success' => false, 'message' => 'Failed to generate valid translation from AI.'], 500);
            }
            
            $existing_trans_id = $wpdb->get_var($wpdb->prepare(
                "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND language_code = %s",
                $trid, $target_lang
            ));
            
            $new_post_args = [
                'post_title' => $result['title'],
                'post_content' => $result['content'],
                'post_excerpt' => isset($result['excerpt']) ? $result['excerpt'] : ''
            ];
            
            // Inject language to bypass WPML default-language fallback and auto-syncing content from original
            $orig_lang_post = isset($_POST['icl_post_language']) ? $_POST['icl_post_language'] : null;
            $_POST['icl_post_language'] = $target_lang;
            
            if ($existing_trans_id) {
                $new_post_args['ID'] = $existing_trans_id;
                remove_action('post_updated', [$this, 'schedule_translation_updates'], 10);
                $new_post_id = wp_update_post($new_post_args);
                add_action('post_updated', [$this, 'schedule_translation_updates'], 10, 3);
            } else {
                $new_post_args['post_type'] = $post->post_type;
                $new_post_args['post_status'] = $post->post_status;
                $new_post_args['post_author'] = $post->post_author;
                $new_post_id = wp_insert_post($new_post_args);
            }
            
            if ($orig_lang_post !== null) {
                $_POST['icl_post_language'] = $orig_lang_post;
            } else {
                unset($_POST['icl_post_language']);
            }
            
            if (is_wp_error($new_post_id)) {
                return new \WP_REST_Response(['success' => false, 'message' => $new_post_id->get_error_message()], 500);
            }
            
            $thumbnail_id = get_post_thumbnail_id($post->ID);
            if ($thumbnail_id) {
                $translated_thumbnail_id = apply_filters('wpml_object_id', $thumbnail_id, 'attachment', true, $target_lang);
                update_post_meta($new_post_id, '_thumbnail_id', $translated_thumbnail_id);
            }
            
            if (!empty($custom_fields_to_translate) && isset($result['custom_fields']) && is_array($result['custom_fields'])) {
                foreach ($result['custom_fields'] as $meta_key => $translated_val) {
                    if (is_array($translated_val)) {
                        delete_post_meta($new_post_id, $meta_key);
                        foreach ($translated_val as $v) {
                            add_post_meta($new_post_id, $meta_key, $v);
                        }
                    } else {
                        update_post_meta($new_post_id, $meta_key, $translated_val);
                    }
                }
            }
            
            // Explicitly sync 'Copy' and 'Copy Once' meta fields based on WPML settings
            $copy_meta_keys = $sitepress->get_custom_fields_translation_settings(1); // WPML_COPY_CUSTOM_FIELD
            $copy_once_meta_keys = $sitepress->get_custom_fields_translation_settings(3); // WPML_COPY_ONCE_CUSTOM_FIELD
            $meta_to_copy = [];
            if (is_array($copy_meta_keys)) $meta_to_copy = array_merge($meta_to_copy, $copy_meta_keys);
            if (!$existing_trans_id && is_array($copy_once_meta_keys)) $meta_to_copy = array_merge($meta_to_copy, $copy_once_meta_keys);
            $meta_to_copy = array_unique($meta_to_copy);
            foreach ($meta_to_copy as $meta_key) {
                if ($meta_key === '_thumbnail_id') continue;
                $meta_values = get_post_meta($post->ID, $meta_key);
                delete_post_meta($new_post_id, $meta_key);
                foreach ($meta_values as $v) {
                    if ($meta_key === '_product_image_gallery' && !empty($v)) {
                        $gallery_ids = explode(',', $v);
                        $translated_gallery_ids = [];
                        foreach ($gallery_ids as $gid) {
                            $translated_gallery_ids[] = apply_filters('wpml_object_id', trim($gid), 'attachment', true, $target_lang);
                        }
                        $v = implode(',', $translated_gallery_ids);
                    }
                    add_post_meta($new_post_id, $meta_key, $v);
                }
            }
            
            // Auto-translate missing taxonomy terms and assign them to the new post
            $translatable_tax = $sitepress->get_translatable_taxonomies(true);
            foreach ($translatable_tax as $tax_name) {
                $terms = wp_get_post_terms($post->ID, $tax_name);
                if (empty($terms) || is_wp_error($terms)) continue;
                
                $translated_term_ids = [];
                foreach ($terms as $term) {
                    $term_trid = $sitepress->get_element_trid($term->term_taxonomy_id, 'tax_' . $tax_name);
                    if (!$term_trid) continue;
                    
                    $translated_term_tax_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND language_code = %s",
                        $term_trid, $target_lang
                    ));
                    
                    if (!$translated_term_tax_id) {
                        $term_prompt = "You are an expert translator. Translate the following WordPress term into the language represented by the ISO code '{$target_lang}'.\n";
                        $term_prompt .= "Respond ONLY with a valid JSON object containing the keys: 'name' and 'description'. Do not include any other text.\n\n";
                        $term_prompt .= "NAME:\n" . $term->name . "\n\n";
                        $term_prompt .= "DESCRIPTION:\n" . $term->description . "\n";
                        
                        $term_result = $this->call_ai_for_json($term_prompt);
                        if (!is_wp_error($term_result) && isset($term_result['name'])) {
                            $new_term = wp_insert_term($term_result['name'], $tax_name, [
                                'description' => isset($term_result['description']) ? $term_result['description'] : ''
                            ]);
                            
                            if (!is_wp_error($new_term)) {
                                $source_term_lang = $sitepress->get_language_for_element($term->term_taxonomy_id, 'tax_' . $tax_name);
                                if (!$source_term_lang) $source_term_lang = $sitepress->get_default_language();
                                $sitepress->set_element_language_details($new_term['term_taxonomy_id'], 'tax_' . $tax_name, $term_trid, $target_lang, $source_term_lang);
                                $this->resolve_wpml_tm_job($new_term['term_taxonomy_id'], $target_lang);
                                $translated_term_ids[] = (int)$new_term['term_id'];
                            }
                        }
                    } else {
                        $translated_term = get_term_by('term_taxonomy_id', $translated_term_tax_id, $tax_name);
                        if ($translated_term) {
                            $translated_term_ids[] = (int)$translated_term->term_id;
                        }
                    }
                }
                
                if (!empty($translated_term_ids)) {
                    wp_set_object_terms($new_post_id, $translated_term_ids, $tax_name);
                }
            }
            
            // Force WPML to use the Native WordPress Editor for this post, 
            // since we translated the raw Gutenberg content directly bypassing the field-by-field Translation Editor
            update_post_meta($post->ID, '_wpml_post_translation_editor_native', 1);
            update_post_meta($post->ID, '_wpml_post_translation_editor_wpml', 0);
            update_post_meta($new_post_id, '_wpml_post_translation_editor_native', 1);
            update_post_meta($new_post_id, '_wpml_post_translation_editor_wpml', 0);
            
            if (!$existing_trans_id) {
                $sitepress->set_element_language_details($new_post_id, 'post_' . $post->post_type, $trid, $target_lang, $sitepress->get_language_for_element($post->ID, 'post_' . $post->post_type));
            }
            $this->resolve_wpml_tm_job($new_post_id, $target_lang);
            $edit_url = get_edit_post_link($new_post_id, 'raw');
            
        } elseif ($object_type === 'term') {
            $term = get_term($object_id, $taxonomy);
            if (!$term || is_wp_error($term)) return new \WP_REST_Response(['success' => false, 'message' => 'Term not found.'], 404);
            
            $trid = $sitepress->get_element_trid($term->term_taxonomy_id, 'tax_' . $taxonomy);
            if (!$trid) return new \WP_REST_Response(['success' => false, 'message' => 'Translation group not found.'], 404);
            
            $prompt = "You are an expert translator. Translate the following WordPress term into the language represented by the ISO code '{$target_lang}' (e.g. 'it' = Italian, 'fr' = French, 'es' = Spanish).\n";
            $prompt .= "Respond ONLY with a valid JSON object containing the keys: 'name' and 'description'. Do not include any other text.\n\n";
            $prompt .= "NAME:\n" . $term->name . "\n\n";
            $prompt .= "DESCRIPTION:\n" . $term->description . "\n";
            
            $result = $this->call_ai_for_json($prompt);
            
            if (is_wp_error($result)) {
                return new \WP_REST_Response(['success' => false, 'message' => $result->get_error_message()], 500);
            }
            if (!$result || !isset($result['name'])) {
                return new \WP_REST_Response(['success' => false, 'message' => 'Failed to generate valid translation from AI.'], 500);
            }
            
            $existing_term_tax_id = $wpdb->get_var($wpdb->prepare(
                "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND language_code = %s",
                $trid, $target_lang
            ));
            
            if ($existing_term_tax_id) {
                $existing_term = get_term_by('term_taxonomy_id', $existing_term_tax_id, $taxonomy);
                if ($existing_term) {
                    $new_term = wp_update_term($existing_term->term_id, $taxonomy, [
                        'name' => $result['name'],
                        'description' => isset($result['description']) ? $result['description'] : ''
                    ]);
                } else {
                    return new \WP_REST_Response(['success' => false, 'message' => 'Existing term translation not found.'], 500);
                }
            } else {
                $new_term = wp_insert_term($result['name'], $taxonomy, [
                    'description' => isset($result['description']) ? $result['description'] : ''
                ]);
            }
            
            if (is_wp_error($new_term)) {
                return new \WP_REST_Response(['success' => false, 'message' => $new_term->get_error_message()], 500);
            }
            
            if (!$existing_term_tax_id) {
                $sitepress->set_element_language_details($new_term['term_taxonomy_id'], 'tax_' . $taxonomy, $trid, $target_lang, $sitepress->get_language_for_element($term->term_taxonomy_id, 'tax_' . $taxonomy));
            }
            $this->resolve_wpml_tm_job($new_term['term_taxonomy_id'], $target_lang);
            $edit_url = get_edit_term_link($new_term['term_id'], $taxonomy, 'post_type'); 
        }

        return new \WP_REST_Response(['success' => true, 'message' => 'Translated successfully.', 'edit_url' => html_entity_decode($edit_url ?? '')], 200);
    }

    private function resolve_wpml_tm_job($element_id, $target_lang) {
        global $wpdb;
        $translation_id = $wpdb->get_var($wpdb->prepare(
            "SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND language_code = %s",
            $element_id, $target_lang
        ));
        if ($translation_id) {
            $status_row = $wpdb->get_row($wpdb->prepare("SELECT rid FROM {$wpdb->prefix}icl_translation_status WHERE translation_id = %d", $translation_id));
            
            $wpdb->update(
                $wpdb->prefix . 'icl_translation_status',
                [
                    'status' => 10,
                    'translation_service' => 'local',
                    'needs_update' => 0
                ],
                ['translation_id' => $translation_id]
            );
            
            if ($status_row && isset($status_row->rid)) {
                $wpdb->update(
                    $wpdb->prefix . 'icl_translate_job',
                    [
                        'translated' => 1,
                        'completed_date' => current_time('mysql')
                    ],
                    ['rid' => $status_row->rid]
                );
            }
        }
        
        // Cleanup any string translations that WPML might have malformed with translation_service = '0'
        // This causes fatal crashes in the WPML Jobs list if left unchecked.
        $wpdb->query("UPDATE {$wpdb->prefix}icl_string_translations SET translation_service = 'local' WHERE translation_service = '0'");
    }

    public function schedule_translation_updates($post_id, $post_after, $post_before) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post_after->post_status !== 'publish' && $post_after->post_status !== 'draft') return;
        if ($post_after->post_content === $post_before->post_content && $post_after->post_title === $post_before->post_title && $post_after->post_excerpt === $post_before->post_excerpt) return;
        
        global $sitepress, $wpdb;
        $default_lang = $sitepress->get_default_language();
        $el_type = 'post_' . $post_after->post_type;
        $post_lang = $sitepress->get_language_for_element($post_id, $el_type);
        
        if ($post_lang !== $default_lang) return;
        
        $trid = $sitepress->get_element_trid($post_id, $el_type);
        if (!$trid) return;
        
        $translations = $wpdb->get_results($wpdb->prepare(
            "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND language_code != %s",
            $trid, $default_lang
        ));
        
        if (!empty($translations)) {
            // Schedule an async background job so we don't hold up the user's save request
            wp_schedule_single_event(time() + 2, 'wbai_wpml_update_translations', [$post_id]);
        }
    }

    public function process_translation_updates($post_id) {
        global $sitepress, $wpdb;
        if (!$sitepress) return;
        
        $post = get_post($post_id);
        if (!$post) return;
        
        $el_type = 'post_' . $post->post_type;
        $default_lang = $sitepress->get_default_language();
        $trid = $sitepress->get_element_trid($post_id, $el_type);
        
        if (!$trid) return;
        
        $translations = $wpdb->get_results($wpdb->prepare(
            "SELECT element_id, language_code FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND language_code != %s",
            $trid, $default_lang
        ));
        
        foreach ($translations as $trans) {
            $target_lang = $trans->language_code;
            $trans_post_id = $trans->element_id;
            
            $prompt = "You are an expert translator. Translate the following WordPress post into language code '{$target_lang}'.\n";
            $prompt .= "IMPORTANT RULES:\n";
            $prompt .= "1. Preserve ALL HTML tags, Gutenberg block comments (e.g. <!-- wp:paragraph -->), and shortcodes exactly as they are.\n";
            $prompt .= "2. ONLY translate the visible text.\n";
            $prompt .= "3. Respond ONLY with a valid JSON object containing the keys: 'title', 'content', and 'excerpt'. Do not include any other text.\n\n";
            $prompt .= "TITLE:\n" . $post->post_title . "\n\n";
            $prompt .= "EXCERPT:\n" . $post->post_excerpt . "\n\n";
            $prompt .= "CONTENT:\n" . $post->post_content . "\n";
            
            $result = $this->call_ai_for_json($prompt);
            
            if ($result && isset($result['title']) && isset($result['content'])) {
                $update_args = [
                    'ID' => $trans_post_id,
                    'post_title' => $result['title'],
                    'post_content' => $result['content'],
                    'post_excerpt' => isset($result['excerpt']) ? $result['excerpt'] : '',
                ];
                remove_action('post_updated', [$this, 'schedule_translation_updates'], 10);
                wp_update_post($update_args);
                add_action('post_updated', [$this, 'schedule_translation_updates'], 10, 3);
            }
        }
    }

    public function schedule_term_translation_updates($term_id, $tt_id, $taxonomy) {
        global $sitepress, $wpdb;
        if (!$sitepress) return;
        
        $default_lang = $sitepress->get_default_language();
        $el_type = 'tax_' . $taxonomy;
        $term_lang = $sitepress->get_language_for_element($tt_id, $el_type);
        
        if ($term_lang !== $default_lang) return;
        
        $trid = $sitepress->get_element_trid($tt_id, $el_type);
        if (!$trid) return;
        
        $translations = $wpdb->get_results($wpdb->prepare(
            "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND language_code != %s",
            $trid, $default_lang
        ));
        
        if (!empty($translations)) {
            wp_schedule_single_event(time() + 2, 'wbai_wpml_update_term_translations', [$term_id, $taxonomy]);
        }
    }

    public function process_term_translation_updates($term_id, $taxonomy) {
        global $sitepress, $wpdb;
        if (!$sitepress) return;
        
        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) return;
        
        $el_type = 'tax_' . $taxonomy;
        $default_lang = $sitepress->get_default_language();
        $trid = $sitepress->get_element_trid($term->term_taxonomy_id, $el_type);
        
        if (!$trid) return;
        
        $translations = $wpdb->get_results($wpdb->prepare(
            "SELECT element_id, language_code FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND language_code != %s",
            $trid, $default_lang
        ));
        
        foreach ($translations as $trans) {
            $target_lang = $trans->language_code;
            $trans_tt_id = $trans->element_id;
            
            $trans_term = get_term_by('term_taxonomy_id', $trans_tt_id, $taxonomy);
            if (!$trans_term) continue;
            
            $prompt = "You are an expert translator. Translate the following WordPress term into language code '{$target_lang}'.\n";
            $prompt .= "Respond ONLY with a valid JSON object containing the keys: 'name' and 'description'. Do not include any other text.\n\n";
            $prompt .= "NAME:\n" . $term->name . "\n\n";
            $prompt .= "DESCRIPTION:\n" . $term->description . "\n";
            
            $result = $this->call_ai_for_json($prompt);
            
            if ($result && isset($result['name'])) {
                remove_action('saved_term', [$this, 'schedule_term_translation_updates'], 10);
                wp_update_term($trans_term->term_id, $taxonomy, [
                    'name' => $result['name'],
                    'description' => isset($result['description']) ? $result['description'] : ''
                ]);
                add_action('saved_term', [$this, 'schedule_term_translation_updates'], 10, 3);
            }
        }
    }
    
    public function register_wizard_ai_translator_user() {
        if (!function_exists('get_user_by')) return;
        
        $user = get_user_by('login', 'wizard_ai_translator');
        if (!$user) {
            $user_id = wp_insert_user([
                'user_login' => 'wizard_ai_translator',
                'user_pass' => wp_generate_password(32),
                'user_email' => 'ai_translator@wizard.test',
                'display_name' => 'Wizard AI Translator',
                'role' => 'subscriber'
            ]);
            if (!is_wp_error($user_id)) {
                $user = new \WP_User($user_id);
                // WPML translator capability
                $user->add_cap('translate');
                
                // Allow translating to all active languages
                global $sitepress;
                if ($sitepress) {
                    $active_langs = $sitepress->get_active_languages();
                    $pairs = [];
                    foreach ($active_langs as $l1) {
                        foreach ($active_langs as $l2) {
                            if ($l1['code'] !== $l2['code']) {
                                $pairs[$l1['code']][$l2['code']] = 1;
                            }
                        }
                    }
                    update_user_meta($user_id, $sitepress->wpdb()->prefix . 'language_pairs', $pairs);
                }
            }
        }
    }

    public function intercept_wpml_tm_job($job_id) {
        $user = get_user_by('login', 'wizard_ai_translator');
        if (!$user) return;
        
        $job = apply_filters('wpml_get_translation_job', null, $job_id);
        if ($job && isset($job->translator_id) && $job->translator_id == $user->ID) {
            wp_schedule_single_event(time() + 2, 'wbai_process_wpml_tm_job', [$job_id]);
        }
    }

    public function process_wpml_tm_job($job_id) {
        global $sitepress, $wpdb;
        if (!$sitepress) return;

        $job = apply_filters('wpml_get_translation_job', null, $job_id);
        if (!$job) return;

        $original_id = $job->original_doc_id;
        $target_lang = $job->language_code;
        $type = $job->original_post_type;

        if (strpos($type, 'post_') === 0) {
            $post = get_post($original_id);
            if (!$post) return;
            
            $prompt = "You are an expert translator. Translate the following WordPress post into language code '{$target_lang}'.\n";
            $prompt .= "IMPORTANT RULES:\n";
            $prompt .= "1. Preserve ALL HTML tags, Gutenberg block comments (e.g. <!-- wp:paragraph -->), and shortcodes exactly as they are.\n";
            $prompt .= "2. ONLY translate the visible text.\n";
            $prompt .= "3. Respond ONLY with a valid JSON object containing the keys: 'title', 'content', and 'excerpt'. Do not include any other text.\n\n";
            $prompt .= "TITLE:\n" . $post->post_title . "\n\n";
            $prompt .= "EXCERPT:\n" . $post->post_excerpt . "\n\n";
            $prompt .= "CONTENT:\n" . $post->post_content . "\n";
            
            $result = $this->call_ai_for_json($prompt);
            
            if ($result && isset($result['title']) && isset($result['content'])) {
                // Find translation post ID
                $trid = $sitepress->get_element_trid($original_id, $type);
                $trans_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND language_code = %s",
                    $trid, $target_lang
                ));
                
                if ($trans_id) {
                    remove_action('post_updated', [$this, 'schedule_translation_updates'], 10);
                    wp_update_post([
                        'ID' => $trans_id,
                        'post_title' => $result['title'],
                        'post_content' => $result['content'],
                        'post_excerpt' => isset($result['excerpt']) ? $result['excerpt'] : '',
                    ]);
                    add_action('post_updated', [$this, 'schedule_translation_updates'], 10, 3);
                    
                    // Mark WPML job complete
                    $wpdb->update(
                        $wpdb->prefix . 'icl_translation_status',
                        ['status' => 10], // ICL_TM_COMPLETE
                        ['rid' => $job->rid]
                    );
                }
            }
        }
    }
    
    private function call_ai_for_json($prompt) {
        $models_to_try = [];
        
        $requested_model = get_option('wbai_wpml_model', '');
        if (empty($requested_model)) {
            $requested_model = get_user_meta(get_current_user_id(), '_wbai_preferred_model', true);
        }
        if (!empty($requested_model)) {
            $parts = explode('|', $requested_model);
            if (count($parts) === 2) {
                $models_to_try[] = $parts;
            }
        }

        $auto_fallback = get_option('wbai_wpml_auto_fallback', 0);
        if ($auto_fallback) {
            $fallback_models = get_option('wbai_wpml_fallback_models', []);
            if (empty($fallback_models)) {
                $all_models = [];
                $registry = \WordPress\AiClient\AiClient::defaultRegistry();
                $requirements = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
                    [\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration()], []
                );
                foreach ($registry->findModelsMetadataForSupport($requirements) as $p) {
                    foreach ($p->getModels() as $m) {
                        $id = $m->getId();
                        if (stripos($id, 'dall-e') !== false || stripos($id, 'midjourney') !== false) continue;
                        $all_models[] = $p->getProvider()->getId() . '|' . $id;
                    }
                }
                $fallback_models = $all_models;
            }
            foreach ($fallback_models as $fm) {
                $parts = explode('|', $fm);
                if (count($parts) === 2) {
                    $models_to_try[] = $parts;
                }
            }
        }
        
        if (empty($models_to_try)) {
            return new \WP_Error('no_models', 'No eligible AI models found. Please configure a model in Settings.');
        }

        // Remove duplicates so we don't retry the same model
        $models_to_try = array_map("unserialize", array_unique(array_map("serialize", $models_to_try)));
        
        $last_exception = null;
        
        foreach ($models_to_try as $model_parts) {
            try {
                $ai_query = \WordPress\AiClient\AiClient::prompt([
                    new \WordPress\AiClient\Messages\DTO\UserMessage([
                        new \WordPress\AiClient\Messages\DTO\MessagePart($prompt)
                    ])
                ]);
                
                $ai_query->usingModelPreference($model_parts);
                $ai_query->asJsonResponse();
                $res = $ai_query->generateResult();
                $text = $res->toText();
                $text = trim(str_replace(['```json', '```'], '', $text));
                
                $json = json_decode($text, true);
                if ($json) {
                    return $json; // Success!
                } else {
                    throw new \Exception("Invalid JSON returned from model. Raw text: " . $text);
                }
            } catch (\Exception $e) {
                $last_exception = $e;
                $msg = strtolower($e->getMessage());
                
                if (strpos($msg, 'token') !== false || strpos($msg, 'quota') !== false || strpos($msg, 'credit') !== false || strpos($msg, 'rate') !== false || strpos($msg, 'limit') !== false) {
                    $this->log_error("API Limit/Token Error for model " . implode('|', $model_parts) . ": " . $e->getMessage());
                } else {
                    $this->log_error("Model " . implode('|', $model_parts) . " failed: " . $e->getMessage());
                }
                
                // If this is not the last model in the list, we automatically continue and retry the next one.
                continue;
            }
        }
        
        // If we reach here, ALL models have failed.
        if ($last_exception) {
            $msg = $last_exception->getMessage();
            if (strpos(strtolower($msg), 'token') !== false || strpos(strtolower($msg), 'quota') !== false || strpos(strtolower($msg), 'credit') !== false) {
                return new \WP_Error('ai_token_error', 'Insufficient API tokens/credits to complete translation. Please check your provider account.');
            }
            return new \WP_Error('ai_exception', 'All AI models failed. Last error: ' . $msg);
        }
        
        return new \WP_Error('ai_unknown_error', 'Failed to generate translation.');
    }

    public function handle_wpml_strings_get_missing(\WP_REST_Request $request) {
        global $wpdb;
        $domain = $request->get_param('domain');
        $target_lang = $request->get_param('target_lang');
        
        if (empty($domain) || empty($target_lang)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Missing domain or target_lang parameters'], 400);
        }
        
        $sql = $wpdb->prepare("
            SELECT s.id, s.name, s.value 
            FROM {$wpdb->prefix}icl_strings s
            LEFT JOIN {$wpdb->prefix}icl_string_translations st 
                ON s.id = st.string_id AND st.language = %s
            WHERE s.context = %s 
              AND s.language != %s
              AND (st.id IS NULL OR st.status != 10)
            ORDER BY s.id DESC LIMIT 500
        ", $target_lang, $domain, $target_lang);
        
        $results = $wpdb->get_results($sql);
        
        $items = [];
        foreach ($results as $r) {
            $items[] = [
                'id' => $r->id,
                'name' => esc_html(mb_strimwidth($r->name, 0, 80, '...')),
                'value' => esc_html(mb_strimwidth($r->value, 0, 150, '...'))
            ];
        }
        
        return new \WP_REST_Response(['success' => true, 'items' => $items], 200);
    }

    public function handle_wpml_strings_translate(\WP_REST_Request $request) {
        global $wpdb;
        $string_id = (int)$request->get_param('string_id');
        $target_lang = $request->get_param('target_lang');
        
        if (!$string_id || empty($target_lang) || !function_exists('icl_add_string_translation')) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Invalid request or ST not installed'], 400);
        }
        
        $string_value = $wpdb->get_var($wpdb->prepare("SELECT value FROM {$wpdb->prefix}icl_strings WHERE id = %d", $string_id));
        if (empty($string_value)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'String empty or not found'], 400);
        }
        
        $prompt = "You are an expert translator. Translate the following UI/text string into the language represented by the ISO code '{$target_lang}'.\n";
        $prompt .= "IMPORTANT RULES:\n";
        $prompt .= "1. Respond ONLY with the translated text. Do NOT include any explanations, quotes, or JSON.\n";
        $prompt .= "2. Preserve any placeholders like %s, %d, {name}, HTML tags exactly.\n\n";
        $prompt .= "TEXT TO TRANSLATE:\n" . $string_value;
        
        $result = $this->call_ai_for_text($prompt);
        if (is_wp_error($result)) {
            return new \WP_REST_Response(['success' => false, 'message' => $result->get_error_message()], 500);
        }
        
        if (trim($result) === '') {
            return new \WP_REST_Response(['success' => false, 'message' => 'Empty translation received'], 500);
        }
        
        icl_add_string_translation($string_id, $target_lang, trim($result), 10);
        
        return new \WP_REST_Response(['success' => true], 200);
    }

    private function call_ai_for_text($prompt) {
        if (!class_exists('\WordPress\AiClient\AiClient')) {
            return new \WP_Error('ai_missing', 'AI Client not found.');
        }
        
        $preferred_model = get_option('wbai_wpml_model', '');
        $auto_fallback = get_option('wbai_wpml_auto_fallback', 0);
        $fallback_models = get_option('wbai_wpml_fallback_models', []);
        
        $models_to_try = [];
        if (!empty($preferred_model)) {
            $models_to_try[] = $preferred_model;
            if ($auto_fallback && !empty($fallback_models)) {
                $models_to_try = array_merge($models_to_try, $fallback_models);
            }
        }
        
        if (empty($models_to_try)) {
            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            $requirements = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
                [\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration()], []
            );
            $providerModels = $registry->findModelsMetadataForSupport($requirements);
            foreach ($providerModels as $pm) {
                $pid = $pm->getProvider()->getId();
                foreach ($pm->getModels() as $m) {
                    $models_to_try[] = $pid . '|' . $m->getId();
                    break 2; 
                }
            }
        }
        
        $last_exception = null;
        
        foreach ($models_to_try as $model_str) {
            $model_parts = explode('|', $model_str);
            if (count($model_parts) !== 2) continue;
            
            try {
                $ai_query = \WordPress\AiClient\AiClient::prompt([
                    new \WordPress\AiClient\Messages\DTO\UserMessage([
                        new \WordPress\AiClient\Messages\DTO\MessagePart($prompt)
                    ])
                ]);
                
                $ai_query->usingModelPreference($model_parts);
                $res = $ai_query->generateResult();
                $text = $res->toText();
                
                if (!empty($text)) {
                    return $text; 
                } else {
                    throw new \Exception("Empty text returned from model.");
                }
            } catch (\Exception $e) {
                $last_exception = $e;
                continue;
            }
        }
        
        if ($last_exception) {
            return new \WP_Error('ai_exception', 'All AI models failed. Last error: ' . $last_exception->getMessage());
        }
        
        return new \WP_Error('ai_unknown_error', 'Failed to generate text.');
    }

    private function log_error($message) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wbai/logs';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            file_put_contents($log_dir . '/.htaccess', "Deny from all\n");
            file_put_contents($log_dir . '/index.php', "<?php // Silence is golden.");
        }
        $log_file = $log_dir . '/wpml.log';
        $time = current_time('mysql');
        $log_entry = "[{$time}] " . $message . PHP_EOL;
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
}
