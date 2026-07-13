<?php
namespace WizardAi\Modules\Seo\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait Settings {
    public function wb_ai_seo_page_html() {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'content';
        
        $auto_optimize = get_option('wai_auto_optimize_media', false);
        $preferred_model = get_option('wai_seo_preferred_model', '');
        $text_model = get_option('wai_seo_text_model', '');
        
        $md_enabled = get_option('wbai_markdown_enabled', '1');
        $md_llmstxt_enabled = get_option('wbai_markdown_llmstxt_enabled', '1');
        $md_selected_cpts = get_option('wbai_markdown_cpts', false);
        if ($md_selected_cpts === false) {
            $md_selected_cpts = array_values(array_diff(array_keys(get_post_types(['public' => true])), ['attachment']));
        }
        $post_types = get_post_types(['public' => true], 'objects');
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('SEO', 'wizard-ai'); ?></h1>
            <hr class="wp-header-end">

            <nav class="nav-tab-wrapper wp-clearfix" aria-label="Secondary menu">
                <a href="?page=wizard-ai-seo&tab=content" class="nav-tab <?php echo 'content' === $current_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Content', 'wizard-ai'); ?></a>
                <a href="?page=wizard-ai-seo&tab=media" class="nav-tab <?php echo 'media' === $current_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Media', 'wizard-ai'); ?></a>
                <a href="?page=wizard-ai-seo&tab=markdown" class="nav-tab <?php echo 'markdown' === $current_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Markdown', 'wizard-ai'); ?></a>
            </nav>

            <?php if ('content' === $current_tab): ?>
            <div id="content-settings">
                <div class="card" style="max-width: 1000px; padding: 20px; margin-top: 20px;">
                    <h2><?php esc_html_e('Bulk Content Optimization', 'wizard-ai'); ?></h2>
                    <p><?php esc_html_e('Generate missing slugs, meta titles, descriptions, and excerpts for all your posts, pages, and custom post types. Compatible with Yoast, RankMath, and AIOSEO.', 'wizard-ai'); ?></p>
                    
                    <div style="margin-top: 20px; margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
                        <select id="wai-content-seo-type">
                            <?php foreach ($post_types as $pt): ?>
                                <?php if ($pt->name === 'attachment') continue; ?>
                                <option value="<?php echo esc_attr($pt->name); ?>"><?php echo esc_html($pt->labels->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="wai-content-seo-model">
                            <option value=""><?php esc_html_e('Automatic Text Model', 'wizard-ai'); ?></option>
                        </select>
                        <button type="button" id="wai-content-seo-load" class="button"><?php esc_html_e('Load Content', 'wizard-ai'); ?></button>
                        <button type="button" id="wai-content-seo-bulk" class="button button-primary" style="display:none;"><?php esc_html_e('Optimize Selected', 'wizard-ai'); ?></button>
                    </div>

                    <table class="wp-list-table widefat fixed striped" id="wai-content-seo-table" style="display:none;">
                        <thead>
                            <tr>
                                <td id="cb" class="manage-column column-cb check-column"><input id="cb-select-all" type="checkbox"></td>
                                <th scope="col"><?php esc_html_e('Title', 'wizard-ai'); ?></th>
                                <th scope="col"><?php esc_html_e('Excerpt', 'wizard-ai'); ?></th>
                                <th scope="col"><?php esc_html_e('Status', 'wizard-ai'); ?></th>
                                <th scope="col"><?php esc_html_e('Action', 'wizard-ai'); ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php if ('media' === $current_tab): ?>
            <div id="media-settings">
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2><?php esc_html_e('Media Settings', 'wizard-ai'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('AI Model (Vision)', 'wizard-ai'); ?></th>
                        <td>
                            <select id="wai-seo-model">
                                <option value=""><?php esc_html_e('Automatic (Recommended)', 'wizard-ai'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Select an AI model with vision capabilities (e.g. GPT-4o, Claude 3.5 Sonnet, Gemini 1.5).', 'wizard-ai'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('AI Model (Text)', 'wizard-ai'); ?></th>
                        <td>
                            <select id="wai-seo-text-model">
                                <option value=""><?php esc_html_e('Automatic (Recommended)', 'wizard-ai'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Select an AI model for text and logic processing (e.g. GPT-4, Claude 3, Llama 3).', 'wizard-ai'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Automatic Optimization', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" id="wai-seo-auto" <?php checked($auto_optimize, 'true'); ?>>
                                <?php esc_html_e('Automatically optimize new images uploaded to the Media Library via Cron', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="button" class="button button-primary wai-seo-save-btn"><?php esc_html_e('Save Settings', 'wizard-ai'); ?></button>
                </p>
            </div>

            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2><?php esc_html_e('Bulk Optimization', 'wizard-ai'); ?></h2>
                <p><?php esc_html_e('Scan your media library for images that have missing SEO metadata (Alt Text, Title, Description, Caption) and optimize them using AI.', 'wizard-ai'); ?></p>
                
                <div style="margin-top: 20px;">
                    <button type="button" id="wai-seo-scan" class="button button-secondary"><?php esc_html_e('Scan Media Library', 'wizard-ai'); ?></button>
                    <button type="button" id="wai-seo-start-bulk" class="button button-primary" style="display:none;"><?php esc_html_e('Start Bulk Optimization', 'wizard-ai'); ?></button>
                </div>

                <div id="wai-seo-log" style="margin-top: 20px; max-height: 400px; overflow-y: auto; background: #fff; border: 1px solid #ccc; padding: 10px; display: none;">
                    <ul id="wai-seo-log-list" style="margin: 0;"></ul>
                </div>
            </div>
            </div>
            <?php endif; ?>

            <?php if ('markdown' === $current_tab): ?>
            <div id="markdown-settings">
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2><?php esc_html_e('Markdown Settings', 'wizard-ai'); ?></h2>
                <p><?php esc_html_e('Configure how Wizard AI exposes your site content as Markdown for AI crawlers.', 'wizard-ai'); ?></p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Markdown', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" id="wai-md-enabled" value="1" <?php checked('1', $md_enabled); ?>>
                                <?php esc_html_e('Enable Markdown conversion and .md endpoints', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable llms.txt', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" id="wai-md-llmstxt-enabled" value="1" <?php checked('1', $md_llmstxt_enabled); ?>>
                                <?php esc_html_e('Enable the /llms.txt endpoint for AI crawlers', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enabled Post Types', 'wizard-ai'); ?></th>
                        <td>
                            <fieldset>
                                <?php foreach ($post_types as $pt): ?>
                                    <label style="display:block; margin-bottom: 5px;">
                                        <input type="checkbox" class="wai-md-cpt-checkbox" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $md_selected_cpts)); ?>>
                                        <?php echo esc_html($pt->labels->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description"><?php esc_html_e('Select which post types should be exposed as Markdown.', 'wizard-ai'); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="button" class="button button-primary wai-seo-save-btn"><?php esc_html_e('Save Settings', 'wizard-ai'); ?></button>
                </p>
            </div>
            </div>
            <?php endif; ?>

        </div>
        <script>
        jQuery(document).ready(function($) {
            // Load Models
            fetch('<?php echo esc_js(esc_url_raw(rest_url("wizard-ai/v1/ai-models?vision=1"))); ?>', {
                headers: { 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>' }
            }).then(res => res.json()).then(data => {
                if (data.success && data.models) {
                    const select = $('#wai-seo-model');
                    Object.entries(data.models).forEach(([group, models]) => {
                        const optgroup = $('<optgroup>').attr('label', group);
                        Object.entries(models).forEach(([id, name]) => {
                            const opt = $('<option>').val(id).text(name);
                            if (id === '<?php echo esc_js($preferred_model); ?>') opt.prop('selected', true);
                            optgroup.append(opt);
                        });
                    });
                    if ($.fn.select2) select.select2();
                }
            });

            // Load Text Models
            fetch('<?php echo esc_js(esc_url_raw(rest_url("wizard-ai/v1/ai-models"))); ?>', {
                headers: { 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>' }
            }).then(res => res.json()).then(data => {
                if (data.success && data.models) {
                    const select = $('#wai-seo-text-model');
                    Object.entries(data.models).forEach(([group, models]) => {
                        const optgroup = $('<optgroup>').attr('label', group);
                        Object.entries(models).forEach(([id, name]) => {
                            const opt = $('<option>').val(id).text(name);
                            if (id === '<?php echo esc_js($text_model); ?>') opt.prop('selected', true);
                            optgroup.append(opt);
                        });
                        select.append(optgroup);
                    });
                    if ($.fn.select2) select.select2();
                }
            });

            $('.wai-seo-save-btn').on('click', function() {
                const btn = $('.wai-seo-save-btn');
                btn.prop('disabled', true).text('Saving...');
                
                let dataPayload = {};
                if ($('#wai-seo-auto').length) {
                    dataPayload.auto_optimize = $('#wai-seo-auto').is(':checked') ? 'true' : 'false';
                    dataPayload.preferred_model = $('#wai-seo-model').val();
                    dataPayload.text_model = $('#wai-seo-text-model').val();
                }
                if ($('#wai-md-enabled').length) {
                    dataPayload.markdown_enabled = $('#wai-md-enabled').is(':checked') ? 'true' : 'false';
                    dataPayload.markdown_llmstxt_enabled = $('#wai-md-llmstxt-enabled').is(':checked') ? 'true' : 'false';
                    dataPayload.markdown_cpts = $('.wai-md-cpt-checkbox:checked').map(function() { return this.value; }).get();
                }

                $.ajax({
                    url: '<?php echo esc_js(esc_url_raw(rest_url("wizard-ai/v1/save-seo-settings"))); ?>',
                    method: 'POST',
                    headers: { 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>' },
                    data: dataPayload,
                    success: function(res) {
                        btn.prop('disabled', false).text('<?php esc_html_e("Save Settings", "wizard-ai"); ?>');
                    }
                });
            });

            let unoptimizedIds = [];
            
            $('#wai-seo-scan').on('click', function() {
                const btn = $(this);
                btn.prop('disabled', true).text('Scanning...');
                // Simple WP API query to get images
                $.ajax({
                    url: '<?php echo esc_js(esc_url_raw(rest_url("wp/v2/media?media_type=image&per_page=100"))); ?>',
                    method: 'GET',
                    success: function(media) {
                        // Filter locally for missing alt texts or unoptimized
                        unoptimizedIds = media.map(m => m.id); // For demo, we just grab all of them, but backend tracks _wai_seo_optimized
                        btn.prop('disabled', false).text('Scan Complete: Found ' + unoptimizedIds.length + ' images');
                        if (unoptimizedIds.length > 0) {
                            $('#wai-seo-start-bulk').show();
                        }
                    }
                });
            });

            $('#wai-seo-start-bulk').on('click', async function() {
                const btn = $(this);
                btn.prop('disabled', true).text('Processing...');
                $('#wai-seo-log').show();
                const logList = $('#wai-seo-log-list');
                
                for (let i = 0; i < unoptimizedIds.length; i++) {
                    const id = unoptimizedIds[i];
                    logList.append(`<li id="seo-log-${id}">Processing image ID ${id}... </li>`);
                    
                    try {
                        const res = await $.ajax({
                            url: '<?php echo esc_js(esc_url_raw(rest_url("wizard-ai/v1/optimize-media-seo"))); ?>',
                            method: 'POST',
                            headers: { 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>' },
                            data: {
                                attachment_id: id,
                                model: $('#wai-seo-model').val()
                            }
                        });
                        if (res.success) {
                            $(`#seo-log-${id}`).append(`<span style="color:green;">Done! Alt: "${res.data.alt_text}"</span>`);
                        } else {
                            $(`#seo-log-${id}`).append(`<span style="color:red;">Failed.</span>`);
                        }
                    } catch (e) {
                        $(`#seo-log-${id}`).append(`<span style="color:red;">Error: ${e.responseText || e.statusText}</span>`);
                    }
                }
                btn.text('Finished!');
            });

            // Content SEO Logic
            if ($('#wai-content-seo-model').length) {
                fetch('<?php echo esc_js(esc_url_raw(rest_url("wizard-ai/v1/ai-models"))); ?>', {
                    headers: { 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>' }
                }).then(res => res.json()).then(data => {
                    if (data.success && data.models) {
                        const select = $('#wai-content-seo-model');
                        Object.entries(data.models).forEach(([group, models]) => {
                            const optgroup = $('<optgroup>').attr('label', group);
                            Object.entries(models).forEach(([id, name]) => {
                                const opt = $('<option>').val(id).text(name);
                                optgroup.append(opt);
                            });
                            select.append(optgroup);
                        });
                        if ($.fn.select2) select.select2();
                    }
                });
            }

            $('#wai-content-seo-load').on('click', function() {
                const btn = $(this);
                const type = $('#wai-content-seo-type').val();
                btn.prop('disabled', true).text('Loading...');
                $('#wai-content-seo-table').hide();
                $('#wai-content-seo-table tbody').empty();
                $('#wai-content-seo-bulk').hide();

                $.ajax({
                    url: '<?php echo esc_js(esc_url_raw(rest_url("wizard-ai/v1/content-seo-list"))); ?>',
                    method: 'GET',
                    headers: { 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>' },
                    data: { post_type: type, paged: 1 },
                    success: function(res) {
                        btn.prop('disabled', false).text('<?php esc_html_e("Load Content", "wizard-ai"); ?>');
                        if (res.success && res.data && res.data.length > 0) {
                            res.data.forEach(post => {
                                const editUrl = '<?php echo esc_js(esc_url_raw(admin_url("post.php?action=edit&post="))); ?>' + post.id;
                                const tr = $('<tr>').attr('id', 'content-seo-' + post.id);
                                tr.append('<th scope="row" class="check-column"><input type="checkbox" class="content-seo-cb" value="' + post.id + '"></th>');
                                tr.append('<td><strong><a href="' + editUrl + '" target="_blank">' + post.title + '</a></strong><br><small style="color:#666;">/' + post.slug + '</small></td>');
                                tr.append('<td>' + (post.excerpt ? '<span style="font-size: 12px;">' + post.excerpt.substring(0, 80) + (post.excerpt.length > 80 ? '...' : '') + '</span>' : '<em style="color:#999; font-size:12px;">No excerpt</em>') + '</td>');
                                tr.append('<td class="status-cell">' + post.status + '</td>');
                                tr.append('<td><button type="button" class="button btn-optimize-single" data-id="' + post.id + '">Optimize</button></td>');
                                $('#wai-content-seo-table tbody').append(tr);
                            });
                            $('#wai-content-seo-table').show();
                            $('#wai-content-seo-bulk').show();
                        } else {
                            $('#wai-content-seo-table tbody').append('<tr><td colspan="5">No content found.</td></tr>');
                            $('#wai-content-seo-table').show();
                        }
                    }
                });
            });

            $('#cb-select-all').on('change', function() {
                $('.content-seo-cb').prop('checked', $(this).is(':checked'));
            });

            const optimizePost = async (id) => {
                const tr = $('#content-seo-' + id);
                const btn = tr.find('.btn-optimize-single');
                const status = tr.find('.status-cell');
                
                btn.prop('disabled', true).text('Working...');
                status.html('<span style="color:orange;">Processing...</span>');
                
                try {
                    const res = await $.ajax({
                        url: '<?php echo esc_js(esc_url_raw(rest_url("wizard-ai/v1/optimize-content-seo"))); ?>',
                        method: 'POST',
                        headers: { 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>' },
                        data: {
                            post_id: id,
                            model: $('#wai-content-seo-model').val()
                        }
                    });
                    
                    if (res.success) {
                        status.html('<span style="color:green;">Optimized</span>');
                        btn.text('Done');
                    } else {
                        status.html('<span style="color:red;">Failed</span>');
                        btn.prop('disabled', false).text('Retry');
                    }
                } catch (e) {
                    status.html('<span style="color:red;">Error</span>');
                    btn.prop('disabled', false).text('Retry');
                }
            };

            $(document).on('click', '.btn-optimize-single', function() {
                optimizePost($(this).data('id'));
            });

            $('#wai-content-seo-bulk').on('click', async function() {
                const selected = $('.content-seo-cb:checked').map(function() { return this.value; }).get();
                if (selected.length === 0) {
                    alert('Please select at least one item.');
                    return;
                }
                
                const btn = $(this);
                btn.prop('disabled', true).text('Processing ' + selected.length + ' items...');
                
                for (let i = 0; i < selected.length; i++) {
                    await optimizePost(selected[i]);
                }
                
                btn.prop('disabled', false).text('<?php esc_html_e("Optimize Selected", "wizard-ai"); ?>');
            });
        });
        </script>
        <?php
    }

    // --- FROM WIZARD BLOCKS PRO ---
}
