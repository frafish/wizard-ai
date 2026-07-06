<?php
namespace WizardAi\Modules\Wpml\traits;

trait Contents {
    public function render_content_tab() {
        global $sitepress, $wpdb;
        $active_langs = $sitepress->get_active_languages();
        $default_lang = $sitepress->get_default_language();
        
        $post_types = [];
        if ($sitepress) {
            $translatable_docs = $sitepress->get_translatable_documents(true);
            $excluded_pts = ['product_variation', 'shop_order', 'shop_order_refund', 'shop_coupon', 'shop_order_placehold', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_font_family', 'wp_font_face', 'wp_sync_storage', 'acf-field-group', 'translation_priority'];
            foreach ($translatable_docs as $pt_name => $pt_obj) {
                if (in_array($pt_name, $excluded_pts)) continue;
                
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
            $excluded_tax = ['translation_priority'];
            foreach ($translatable_tax as $tax_name) {
                if (in_array($tax_name, $excluded_tax)) continue;
                
                $has_items = $wpdb->get_var($wpdb->prepare("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type = %s AND language_code = %s LIMIT 1", 'tax_' . $tax_name, $default_lang));
                if (!$has_items) continue;
                
                $tax_obj = get_taxonomy($tax_name);
                if ($tax_obj) {
                    $taxonomies[] = $tax_obj;
                }
            }
        }
        ?>
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
                            <label for="wai_wpml_type" style="font-weight: 500; margin-right: 8px;"><?php esc_html_e('Type:', 'wizard-ai'); ?></label>
                            <select id="wai_wpml_type">
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
                            <label for="wai_wpml_lang" style="font-weight: 500; margin-right: 8px;"><?php esc_html_e('Language:', 'wizard-ai'); ?></label>
                            <select id="wai_wpml_lang">
                                <?php foreach ($active_langs as $lang) {
                                    if ($lang['code'] === $default_lang) continue;
                                    echo '<option value="' . esc_attr($lang['code']) . '">' . esc_html($lang['display_name']) . '</option>';
                                } ?>
                            </select>
                        </div>

                        <div class="select-dropdown">
                            <label for="wai_wpml_status" style="font-weight: 500; margin-right: 8px;"><?php esc_html_e('Status:', 'wizard-ai'); ?></label>
                            <select id="wai_wpml_status">
                                <option value="both"><?php esc_html_e('Not Translated & Needs Update (Both)', 'wizard-ai'); ?></option>
                                <option value="not_translated"><?php esc_html_e('Not Translated', 'wizard-ai'); ?></option>
                                <option value="needs_update"><?php esc_html_e('Needs Update', 'wizard-ai'); ?></option>
                            </select>
                        </div>
                        
                        <div style="margin-left: auto;">
                            <button class="wpml-button base-btn wpml-button--outlined filter-button" id="wai_scan_missing">
                                <?php esc_html_e('Filter', 'wizard-ai'); ?>
                            </button>
                        </div>
                        
                    </div>
                </div>
            </div>
            
            <div id="wai_bulk_results" class="wpml-item-type wpml-content-box string-section" style="display:none; margin-top: 24px; padding:0;">
                <div class="wpml-item-type-element-list" style="padding:0;">
                    <div style="padding: 15px; border-bottom: 2px solid #373737; display:flex; justify-content:space-between; align-items:center; background: #fafafa;">
                        <div style="display: flex; align-items: center; gap: 20px;">
                            <label class="wpml-checkbox" style="margin: 0;"><input type="checkbox" id="wai_select_all" checked> <span><?php esc_html_e('Select All', 'wizard-ai'); ?> (<span id="wai_missing_count">0</span> items)</span></label>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <label for="wai_local_search" style="font-weight: 500;"><?php esc_html_e('Filter by:', 'wizard-ai'); ?></label>
                                <input type="text" id="wai_local_search" placeholder="<?php esc_attr_e('Title...', 'wizard-ai'); ?>" style="padding: 4px 8px; border: 1px solid #ccc; border-radius: 4px; min-width: 250px;">
                            </div>
                        </div>
                        <button class="wpml-button base-btn" id="wai_start_bulk">
                            <?php esc_html_e('Translate Selected via AI', 'wizard-ai'); ?>
                        </button>
                    </div>
                    <div id="wai_missing_list" style="max-height: 400px; overflow-y: auto;"></div>
                    <div id="wai_bulk_progress" style="padding:15px; font-weight:500;"></div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            let pendingItems = [];
            let isTranslating = false;
            let currentLang = '';
            
            $('#wai_scan_missing').on('click', function() {
                let typeStr = $('#wai_wpml_type').val();
                let lang = $('#wai_wpml_lang').val();
                let statusVal = $('#wai_wpml_status').val();
                currentLang = lang;
                
                $('#wai_refresh_bulk').hide();
                
                let btn = $(this);
                btn.prop('disabled', true).text('Scanning...');
                
                $.ajax({
                    url: '<?php echo esc_url_raw(rest_url('wizard-ai/v1/wpml-get-missing')); ?>',
                    method: 'GET',
                    data: { type: typeStr, target_lang: lang, status: statusVal },
                    beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>'); },
                    success: function(res) {
                        btn.prop('disabled', false).text('Filter');
                        if (res.success) {
                            pendingItems = res.items;
                            $('#wai_missing_count').text(pendingItems.length);
                            
                            let html = '';
                            if (pendingItems.length > 0) {
                                pendingItems.forEach(function(item) {
                                    let icon = item.status === 'needs_update' 
                                        ? '<span class="dashicons dashicons-update" style="color: #f0b849; vertical-align: text-bottom;" title="<?php esc_attr_e('Needs Update', 'wizard-ai'); ?>"></span>' 
                                        : '<span class="dashicons dashicons-plus" style="color: #2271b1; vertical-align: text-bottom;" title="<?php esc_attr_e('Missing Translation', 'wizard-ai'); ?>"></span>';
                                    
                                    html += '<div id="item-'+item.id+'" style="padding:12px 10px; border-bottom:1px solid #eee; display: flex; align-items: center;">' + 
                                            '<label class="wpml-checkbox" style="margin-right: 12px; margin-bottom: 0;"><input type="checkbox" class="wai-item-checkbox" value="'+item.id+'" checked> <span></span></label> ' +
                                            '<a href="'+item.edit_url+'" target="_blank" style="text-decoration:none; font-weight:500; margin-right: 12px;">'+item.title+'</a>' + 
                                            item.langs_html +
                                            '<div style="margin-left: auto; display: flex; align-items: center; gap: 8px;">' + icon + ' <span style="color:#888;">ID: '+item.id+'</span></div>' +
                                            '</div>';
                                });
                                $('#wai_start_bulk').show();
                                $('#wai_select_all').prop('disabled', false).prop('checked', true).parent().show();
                            } else {
                                html = '<p>No missing translations found for this language.</p>';
                                $('#wai_start_bulk').hide();
                                $('#wai_select_all').prop('disabled', false).parent().hide();
                            }
                            $('#wai_missing_list').html(html);
                            $('#wai_local_search').val('');
                            $('#wai_bulk_results').fadeIn();
                            $('#wai_bulk_progress').text('');
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
            
            $('#wai_local_search').on('keyup', function() {
                let searchVal = $(this).val().toLowerCase();
                $('#wai_missing_list > div').each(function() {
                    let title = $(this).find('a').first().text().toLowerCase();
                    if (title.indexOf(searchVal) > -1) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
            
            $('#wai_select_all').on('change', function() {
                $('.wai-item-checkbox').prop('checked', $(this).is(':checked'));
            });
            
            $('#wai_start_bulk').on('click', function() {
                let selectedIds = [];
                $('.wai-item-checkbox:checked').each(function() {
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
                $('.wai-item-checkbox').prop('disabled', true);
                $('#wai_select_all').prop('disabled', true);
                isTranslating = true;
                processNext();
            });
            
            function processNext() {
                if (pendingItems.length === 0) {
                    $('#wai_bulk_progress').text('Translation Complete!');
                    $('#wai_start_bulk').prop('disabled', false).hide();
                    isTranslating = false;
                    
                    if ($('#wai_refresh_bulk').length === 0) {
                        $('#wai_start_bulk').after('<button class="wpml-button base-btn wpml-button--outlined" id="wai_refresh_bulk" style="margin-left:10px;">Refresh List</button>');
                        $('#wai_refresh_bulk').on('click', function() {
                            $('#wai_scan_missing').trigger('click');
                        });
                    }
                    $('#wai_refresh_bulk').show();
                    
                    return;
                }
                
                let item = pendingItems.shift();
                $('#wai_bulk_progress').text('Translating: ' + item.title + ' (' + pendingItems.length + ' remaining...)');
                $('#item-'+item.id).css('background', '#fff8e5');
                
                $.ajax({
                    url: '<?php echo esc_url_raw(rest_url('wizard-ai/v1/wpml-translate')); ?>',
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
                    if ($row.find('.wai-translate-btn').length > 0) return;
                    
                    let langCode = $row.find('input[name^="icl_multi_"]').attr('name');
                    if (langCode) {
                        langCode = langCode.replace('icl_multi_', '');
                    } else {
                        let $link = $row.find('a[href*="lang="]');
                        if ($link.length) {
                            let url = new URL($link.attr('href'), window.location.origin);
                            langCode = url.searchParams.get('lang');
                        }
                    }
                    
                    if (langCode && $row.find('.dashicons-plus').length) {
                        let $btn = $('<button type="button" class="button button-small wai-translate-btn" data-lang="'+langCode+'" style="margin-left:5px;" title="Translate with AI"><span class="dashicons dashicons-admin-site-alt3" style="font-size:14px;line-height:22px;margin-top:2px;"></span> AI</button>');
                        $row.find('.icl_lang_row_status').append($btn);
                    }
                });
                
                // Taxonomy edit screen
                $('[id^="icl_tax_"]').each(function() {
                    let $wrapper = $(this);
                    $wrapper.find('tr').each(function() {
                        let $tr = $(this);
                        if ($tr.find('.wai-translate-btn').length > 0) return;
                        
                        let $link = $tr.find('a[href*="lang="]');
                        if ($link.length && $tr.find('.dashicons-plus').length) {
                            let url = new URL($link.attr('href'), window.location.origin);
                            let langCode = url.searchParams.get('lang');
                            
                            if (langCode) {
                                let $btn = $('<button type="button" class="button button-small wai-translate-btn" data-lang="'+langCode+'" style="margin-left:5px;" title="Translate with AI"><span class="dashicons dashicons-admin-site-alt3" style="font-size:14px;line-height:22px;margin-top:2px;"></span> AI</button>');
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
                        if ($addLink.next('.wai-list-translate-btn').length > 0) return;
                        
                        let href = $addLink.attr('href');
                        let url;
                        try {
                            url = new URL(href, window.location.origin);
                        } catch(e) { return; }
                        
                        let langCode = url.searchParams.get('lang');
                        if (objectType === 'term') tax = url.searchParams.get('taxonomy');
                        
                        if (langCode) {
                            let $btn = $('<button type="button" class="button button-small wai-list-translate-btn" data-lang="'+langCode+'" data-id="'+objectId+'" data-type="'+objectType+'" data-tax="'+tax+'" style="margin-left:4px; padding: 0 4px; min-height: 20px; line-height: 18px;" title="Translate with AI"><span class="dashicons dashicons-admin-site-alt3" style="font-size:12px;line-height:18px;"></span></button>');
                            $addLink.after($btn);
                        }
                    });
                });
            }
            
            injectButtons();
            
            $(document).on('click', '.wai-translate-btn', function(e) {
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
                    url: '<?php echo esc_url_raw(rest_url('wizard-ai/v1/wpml-translate')); ?>',
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
            
            $(document).on('click', '.wai-list-translate-btn', function(e) {
                e.preventDefault();
                let $btn = $(this);
                let lang = $btn.data('lang');
                let objId = $btn.data('id');
                let objType = $btn.data('type');
                let tax = $btn.data('tax');
                
                $btn.html('<span class="dashicons dashicons-update-alt" style="font-size:12px;line-height:18px;animation: rotation 2s infinite linear;"></span>');
                $btn.prop('disabled', true);
                
                $.ajax({
                    url: '<?php echo esc_url_raw(rest_url('wizard-ai/v1/wpml-translate')); ?>',
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

            $is_elementor = false;
            if (method_exists($this, 'is_elementor_post') && $this->is_elementor_post($post->ID)) {
                $is_elementor = true;
                $this->prepare_elementor_for_translation($post->ID, $custom_fields_to_translate);
            }
            
            $prompt = "You are an expert translator. Translate the following WordPress post into the language represented by the ISO code '{$target_lang}' (e.g. 'it' = Italian, 'fr' = French, 'es' = Spanish).\n";
            $prompt .= "IMPORTANT RULES:\n";
            $prompt .= "1. You MUST preserve ALL HTML tags, attributes, and Gutenberg block comments (e.g. <!-- wp:paragraph -->) EXACTLY as they are. Do not translate or modify them.\n";
            $prompt .= "2. ONLY translate the visible text content inside the tags/blocks. DO NOT alter ANY image URLs (src), link URLs (href), or image/block IDs (e.g. {\"id\":123}).\n";
            
            if ($is_elementor && method_exists($this, 'get_elementor_prompt_instructions')) {
                $prompt .= $this->get_elementor_prompt_instructions();
            }

            if (!empty($custom_fields_to_translate)) {
                if ($is_elementor) {
                    $prompt .= "4. Respond ONLY with a valid JSON object matching this structure:\n";
                } else {
                    $prompt .= "3. Respond ONLY with a valid JSON object matching this structure:\n";
                }
                $prompt .= "{\n  \"title\": \"...\",\n  \"content\": \"...\",\n  \"excerpt\": \"...\",\n  \"custom_fields\": {\n";
                foreach ($custom_fields_to_translate as $k => $v) {
                    if ($k === '_elementor_data_decoded' && method_exists($this, 'get_elementor_prompt_json_instruction')) {
                        $prompt .= $this->get_elementor_prompt_json_instruction();
                    } else {
                        $prompt .= "    \"$k\": " . (count($v) > 1 ? "[\"...\", \"...\"]" : "\"...\"") . "\n";
                    }
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
                $prompt .= wp_json_encode($custom_fields_to_translate, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
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
            
                if ($is_elementor && method_exists($this, 'save_translated_elementor_data')) {
                    $this->save_translated_elementor_data($new_post_id, $result['custom_fields']);
                }
                
                foreach ($result['custom_fields'] as $meta_key => $translated_val) {
                    if ($meta_key === '_elementor_data_decoded') continue;
                    
                    if (is_array($translated_val)) {
                        delete_post_meta($new_post_id, $meta_key);
                        foreach ($translated_val as $v) {
                            add_post_meta($new_post_id, $meta_key, $v);
                        }
                    } else {
                        update_post_meta($new_post_id, $meta_key, $translated_val);
                    }
                }
            
            $copy_meta_keys = $sitepress->get_custom_fields_translation_settings(1); // WPML_COPY_CUSTOM_FIELD
            $copy_once_meta_keys = $sitepress->get_custom_fields_translation_settings(3); // WPML_COPY_ONCE_CUSTOM_FIELD
            $meta_to_copy = [];
            if (is_array($copy_meta_keys)) $meta_to_copy = array_merge($meta_to_copy, $copy_meta_keys);
            if (!$existing_trans_id && is_array($copy_once_meta_keys)) $meta_to_copy = array_merge($meta_to_copy, $copy_once_meta_keys);
            
            if ($is_elementor && method_exists($this, 'get_elementor_meta_keys_to_copy')) {
                $meta_to_copy = array_merge($meta_to_copy, $this->get_elementor_meta_keys_to_copy());
            }

            $meta_to_copy = array_unique($meta_to_copy);
            
            foreach ($meta_to_copy as $meta_key) {
                if ($meta_key === '_thumbnail_id' || $meta_key === '_elementor_data') continue;
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
            wp_schedule_single_event(time() + 2, 'wai_wpml_update_translations', [$post_id]);
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
            wp_schedule_single_event(time() + 2, 'wai_wpml_update_term_translations', [$term_id, $taxonomy]);
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
                $user->add_cap('translate');
                
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
            wp_schedule_single_event(time() + 2, 'wai_process_wpml_tm_job', [$job_id]);
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
                    
                    $wpdb->update(
                        $wpdb->prefix . 'icl_translation_status',
                        ['status' => 10], 
                        ['rid' => $job->rid]
                    );
                }
            }
        }
    }
    
    private function call_ai_for_json($prompt) {
        $models_to_try = [];
        
        $requested_model = get_option('wai_wpml_model', '');
        if (empty($requested_model)) {
            $requested_model = get_user_meta(get_current_user_id(), '_wai_preferred_model', true);
        }
        if (!empty($requested_model)) {
            $parts = explode('|', $requested_model);
            if (count($parts) === 2) {
                $models_to_try[] = $parts;
            }
        }

        $auto_fallback = get_option('wai_wpml_auto_fallback', 0);
        if ($auto_fallback) {
            $fallback_models = get_option('wai_wpml_fallback_models', []);
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

        $models_to_try = array_map("unserialize", array_unique(array_map("serialize", $models_to_try)));
        
        $last_exception = null;
        
        foreach ($models_to_try as $model_parts) {
            $max_retries = 2;
            for ($attempt = 0; $attempt <= $max_retries; $attempt++) {
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
                        return $json; 
                    } else {
                        throw new \Exception("Invalid JSON returned from model. Raw text: " . $text);
                    }
                } catch (\Exception $e) {
                    $last_exception = $e;
                    $msg = strtolower($e->getMessage());
                    
                    if (strpos($msg, 'token') !== false || strpos($msg, 'quota') !== false || strpos($msg, 'credit') !== false || strpos($msg, 'rate') !== false || strpos($msg, 'limit') !== false || strpos($msg, '429') !== false || strpos($msg, '408') !== false || strpos($msg, '500') !== false || strpos($msg, '502') !== false || strpos($msg, '503') !== false || strpos($msg, 'timeout') !== false) {
                        $this->log_error("Attempt " . ($attempt + 1) . " - API Error for model " . implode('|', $model_parts) . ": " . $e->getMessage());
                        if ($attempt < $max_retries) {
                            sleep(2);
                            continue;
                        }
                    } else {
                        $this->log_error("Model " . implode('|', $model_parts) . " failed: " . $e->getMessage());
                    }
                    
                    break;
                }
            }
        }
        
        if ($last_exception) {
            $msg = $last_exception->getMessage();
            if (strpos(strtolower($msg), 'token') !== false || strpos(strtolower($msg), 'quota') !== false || strpos(strtolower($msg), 'credit') !== false) {
                return new \WP_Error('ai_token_error', 'Insufficient API tokens/credits to complete translation. Please check your provider account.');
            }
            return new \WP_Error('ai_exception', 'All AI models failed. Last error: ' . $msg);
        }
        
        return new \WP_Error('ai_unknown_error', 'Failed to generate translation.');
    }
}
