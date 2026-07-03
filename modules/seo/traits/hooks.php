<?php
namespace WizardAi\Modules\Seo\Traits;

trait Hooks {
    public function add_seo_media_button($form_fields, $post) {
        if (strpos($post->post_mime_type, 'image/') === 0) {
            $html = '<div style="margin-top: 10px;">
                <button type="button" class="button button-primary wai-seo-optimize-btn" data-id="' . esc_attr($post->ID) . '">' . esc_html__('Generate SEO Meta (AI)', 'wizard-ai') . '</button>
                <span class="spinner wai-seo-spinner" style="float:none; margin-top: 4px;"></span>
                <p class="description">' . esc_html__('Auto-generate Title, Alt Text, Description, and Caption using AI Vision.', 'wizard-ai') . '</p>
            </div>
            <script>
            jQuery(function($) {
                var btn = $(".wai-seo-optimize-btn[data-id=\'' . esc_js($post->ID) . '\']");
                btn.off("click.waiseo").on("click.waiseo", function(e) {
                    e.preventDefault();
                    var spinner = btn.siblings(".wai-seo-spinner");
                    
                    btn.prop("disabled", true);
                    spinner.addClass("is-active");
                    
                    $.ajax({
                        url: "' . esc_url_raw(rest_url('wizard-blocks/v1/optimize-media-seo')) . '",
                        method: "POST",
                        headers: { "X-WP-Nonce": "' . wp_create_nonce('wp_rest') . '" },
                        data: { attachment_id: "' . esc_js($post->ID) . '" },
                        success: function(res) {
                            spinner.removeClass("is-active");
                            btn.prop("disabled", false).text("' . esc_js(__('Optimized!', 'wizard-ai')) . '");
                            setTimeout(function(){ btn.text("' . esc_js(__('Generate SEO Meta (AI)', 'wizard-ai')) . '"); }, 3000);
                            
                            if (typeof wp !== "undefined" && wp.media && wp.media.model && wp.media.model.Attachment) {
                                var attachment = wp.media.model.Attachment.get("' . esc_js($post->ID) . '");
                                if (attachment) {
                                    attachment.fetch();
                                }
                            } else {
                                location.reload();
                            }
                        },
                        error: function(err) {
                            spinner.removeClass("is-active");
                            btn.prop("disabled", false).text("' . esc_js(__('Error', 'wizard-ai')) . '");
                            alert("Error: " + (err.responseJSON ? err.responseJSON.message : err.statusText));
                        }
                    });
                });
            });
            </script>';
            
            $form_fields['wai_seo_btn'] = [
                'label' => __('Wizard AI SEO', 'wizard-ai'),
                'input' => 'html',
                'html'  => $html,
            ];
        }
        return $form_fields;
    }

    public function on_add_attachment($post_id) {
        $auto_optimize = get_option('wai_auto_optimize_media', false);
        if ($auto_optimize && wp_attachment_is_image($post_id)) {
            // Schedule an immediate event to process this image
            wp_schedule_single_event(time() + 10, 'wai_cron_optimize_media', [$post_id]);
        }
    }

    public function cron_optimize_media($attachment_id = null) {
        if ($attachment_id) {
            $this->process_image_seo($attachment_id, get_option('wai_seo_preferred_model', ''));
        } else {
            // Bulk cron (e.g. process 5 oldest unoptimized images)
            $args = [
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'post_status' => 'inherit',
                'posts_per_page' => 5,
                'meta_query' => [
                    [
                        'key' => '_wai_seo_optimized',
                        'compare' => 'NOT EXISTS'
                    ]
                ]
            ];
            $query = new \WP_Query($args);
            foreach ($query->posts as $post) {
                $this->process_image_seo($post->ID, get_option('wai_seo_preferred_model', ''));
            }
        }
    }

    function custom_gutenberg_image_alt($block_content, $block) {
        if (isset($block['blockName']) && $block['blockName'] === 'core/image') {
            $attributes = $block['attrs'];
            if (!empty($attributes['id']) && empty($attributes['alt'])) {
                $image_id = $attributes['id'];
                $final_alt = $post_title = '';

                $media_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);

                if (!empty($media_alt)) {
                    $final_alt = $media_alt;
                } else {
                    $current_post_id = get_the_ID();
                    $is_first_image = $is_featured = false;
                    if ($current_post_id) {
                        $post_title = get_the_title($current_post_id);
                        $is_featured = (int) get_post_thumbnail_id($current_post_id) === (int) $image_id;
                    
                        $all_blocks = parse_blocks(get_the_content());
                        foreach ($all_blocks as $current_block) {
                            if ($current_block['blockName'] === 'core/image' && isset($current_block['attrs']['id'])) {
                                if ((int) $current_block['attrs']['id'] === (int) $image_id) {
                                    $is_first_image = true;
                                }
                                break;
                            }
                        }
                    }

                    if ($is_featured || $is_first_image) {
                        $final_alt = $post_title;
                    } else {
                        $attachment = get_post($image_id);
                        if ($attachment) {
                            $file_name = basename($attachment->guid);
                            $file_name_without_ext = pathinfo($file_name, PATHINFO_FILENAME);
                            $generated_alt = ucwords(str_replace(['-', '_'], ' ', $file_name_without_ext));
                            $final_alt = $generated_alt;
                        }
                    }
                }

                if (!empty($final_alt)) {
                    $block_content = str_replace(' alt="" ', ' alt="' . esc_attr($final_alt) . '" ', $block_content);
                }
            }
        }
        return $block_content;
    }
}
