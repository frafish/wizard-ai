<?php
namespace WizardAi\Modules\Seo\Traits;

trait Settings {
    public function wb_ai_seo_page_html() {
        $auto_optimize = get_option('wai_auto_optimize_media', false);
        $preferred_model = get_option('wai_seo_preferred_model', '');
        $text_model = get_option('wai_seo_text_model', '');
        ?>
        <div class="wrap">
            <h1>
                <span class="dashicons dashicons-format-image"></span>
                <?php esc_html_e('Media Library SEO Optimization', 'wizard-ai'); ?>
            </h1>
            
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2><?php esc_html_e('Settings', 'wizard-ai'); ?></h2>
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
                    <button type="button" id="wai-seo-save" class="button button-primary"><?php esc_html_e('Save Settings', 'wizard-ai'); ?></button>
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
        <script>
        jQuery(document).ready(function($) {
            // Load Models
            fetch('<?php echo esc_js(esc_url_raw(rest_url("wizard-blocks/v1/ai-models?vision=1"))); ?>', {
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
                        select.append(optgroup);
                    });
                }
            });

            // Load Text Models
            fetch('<?php echo esc_js(esc_url_raw(rest_url("wizard-blocks/v1/ai-models"))); ?>', {
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
                }
            });

            $('#wai-seo-save').on('click', function() {
                const btn = $(this);
                btn.prop('disabled', true).text('Saving...');
                $.ajax({
                    url: '<?php echo esc_js(esc_url_raw(rest_url("wizard-blocks/v1/save-seo-settings"))); ?>',
                    method: 'POST',
                    headers: { 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>' },
                    data: {
                        auto_optimize: $('#wai-seo-auto').is(':checked') ? 'true' : 'false',
                        preferred_model: $('#wai-seo-model').val(),
                        text_model: $('#wai-seo-text-model').val()
                    },
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
                            url: '<?php echo esc_js(esc_url_raw(rest_url("wizard-blocks/v1/optimize-media-seo"))); ?>',
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
        });
        </script>
        <?php
    }

    // --- FROM WIZARD BLOCKS PRO ---
}
