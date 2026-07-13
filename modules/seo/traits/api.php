<?php
namespace WizardAi\Modules\Seo\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait Api {
    public function api_save_seo_settings(\WP_REST_Request $request) {
        if ($request->has_param('auto_optimize')) {
            update_option('wai_auto_optimize_media', $request->get_param('auto_optimize'));
        }
        if ($request->has_param('preferred_model')) {
            update_option('wai_seo_preferred_model', $request->get_param('preferred_model'));
        }
        if ($request->has_param('text_model')) {
            update_option('wai_seo_text_model', $request->get_param('text_model'));
        }

        if ($request->has_param('markdown_enabled')) {
            update_option('wbai_markdown_enabled', $request->get_param('markdown_enabled') === 'true' ? '1' : '0');
            update_option('wbai_markdown_llmstxt_enabled', $request->get_param('markdown_llmstxt_enabled') === 'true' ? '1' : '0');
            $cpts = $request->get_param('markdown_cpts');
            update_option('wbai_markdown_cpts', is_array($cpts) ? array_map('sanitize_text_field', $cpts) : []);
            flush_rewrite_rules();
        }

        return new \WP_REST_Response(['success' => true], 200);
    }

    public function api_optimize_media_seo(\WP_REST_Request $request) {
        $attachment_id = intval($request->get_param('attachment_id'));
        $model = sanitize_text_field($request->get_param('model'));

        if (!$attachment_id) {
            return new \WP_Error('invalid_id', 'Invalid attachment ID', ['status' => 400]);
        }

        $result = $this->process_image_seo($attachment_id, $model);
        
        if (is_wp_error($result)) {
            return $result;
        }

        return new \WP_REST_Response(['success' => true, 'data' => $result], 200);
    }

    private function process_image_seo($attachment_id, $model = '') {
        if (!class_exists('\WordPress\AiClient\AiClient') || !function_exists('wp_ai_client_prompt')) {
            return new \WP_Error('ai_missing', 'AI Client not configured', ['status' => 500]);
        }

        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return new \WP_Error('file_missing', 'Image file not found', ['status' => 404]);
        }

        $mime_type = wp_check_filetype($file_path)['type'];
        if (!$mime_type) {
            return new \WP_Error('invalid_file', 'Invalid file type', ['status' => 400]);
        }

        $contents = file_get_contents($file_path);
        if (!$contents) {
            return new \WP_Error('read_error', 'Cannot read image', ['status' => 500]);
        }

        $data_uri = 'data:' . $mime_type . ';base64,' . base64_encode($contents);

        $prompt = "You are an SEO expert. Analyze this image and generate SEO-optimized metadata. 
Respond ONLY with a valid JSON object strictly matching this schema, without markdown formatting or preamble:
{
    \"title\": \"A concise, descriptive title for the image file\",
    \"alt_text\": \"A highly descriptive alt text for accessibility and SEO\",
    \"description\": \"A longer description explaining the context of the image\",
    \"caption\": \"A short caption that could be displayed under the image\"
}";

        $prompt_builder = wp_ai_client_prompt($prompt)->with_file($data_uri);
        
        if (!empty($model)) {
            $parts = explode('|', $model);
            if (count($parts) === 2) {
                $prompt_builder->using_model($parts[1]);
            }
        }

        $response = $prompt_builder->generate_text();

        if (is_wp_error($response)) {
            return $response;
        }

        $json_str = trim($response);
        $json_str = preg_replace('/^```json\s*/i', '', $json_str);
        $json_str = preg_replace('/```$/i', '', $json_str);
        $json_str = trim($json_str);

        $data = json_decode($json_str, true);

        if (!$data || !isset($data['alt_text'])) {
            return new \WP_Error('invalid_json', 'AI did not return valid JSON: ' . $json_str, ['status' => 500]);
        }

        // Update attachment metadata
        $update_data = [
            'ID' => $attachment_id
        ];
        
        if (!empty($data['title'])) $update_data['post_title'] = sanitize_text_field($data['title']);
        if (!empty($data['description'])) $update_data['post_content'] = sanitize_textarea_field($data['description']);
        if (!empty($data['caption'])) $update_data['post_excerpt'] = sanitize_text_field($data['caption']);

        wp_update_post($update_data);

        if (!empty($data['alt_text'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($data['alt_text']));
        }

        update_post_meta($attachment_id, '_wai_seo_optimized', time());

        return $data;
    }

    public function api_content_seo_list(\WP_REST_Request $request) {
        $post_type = $request->get_param('post_type') ?: 'post';
        $paged = intval($request->get_param('paged')) ?: 1;
        $per_page = 20;

        $args = [
            'post_type' => $post_type,
            'post_status' => 'any',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'orderby' => 'ID',
            'order' => 'DESC'
        ];

        $query = new \WP_Query($args);
        $posts = [];

        foreach ($query->posts as $post) {
            $has_yoast = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true) ? true : false;
            $has_rankmath = get_post_meta($post->ID, 'rank_math_description', true) ? true : false;
            $has_aioseo = get_post_meta($post->ID, '_aioseo_description', true) ? true : false;
            
            $status = 'unoptimized';
            if ($has_yoast || $has_rankmath || $has_aioseo || !empty($post->post_excerpt)) {
                $status = 'optimized';
            }

            $posts[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'slug' => urldecode($post->post_name),
                'excerpt' => $post->post_excerpt,
                'status' => $status
            ];
        }

        return new \WP_REST_Response([
            'success' => true, 
            'data' => $posts, 
            'total' => $query->found_posts,
            'max_pages' => $query->max_num_pages
        ], 200);
    }

    public function api_optimize_content_seo(\WP_REST_Request $request) {
        $post_id = intval($request->get_param('post_id'));
        $model = sanitize_text_field($request->get_param('model'));

        if (!$post_id) {
            return new \WP_Error('invalid_id', 'Invalid post ID', ['status' => 400]);
        }

        $post = get_post($post_id);
        if (!$post) {
            return new \WP_Error('not_found', 'Post not found', ['status' => 404]);
        }

        if (!class_exists('\WordPress\AiClient\AiClient') || !function_exists('wp_ai_client_prompt')) {
            return new \WP_Error('ai_missing', 'AI Client not configured', ['status' => 500]);
        }

        $content = strip_tags(strip_shortcodes($post->post_content));
        if (empty($content)) {
            $content = $post->post_title; // Fallback to title if no content
        }

        // Limit content length to avoid exceeding context limits
        $content = mb_substr($content, 0, 8000);

        $prompt = "You are an SEO expert. Read the following content and generate SEO-optimized metadata. 
Respond ONLY with a valid JSON object strictly matching this schema, without markdown formatting or preamble:
{
    \"title\": \"An SEO optimized meta title (max 60 chars)\",
    \"meta_description\": \"An SEO optimized meta description (max 160 chars)\",
    \"excerpt\": \"A short excerpt summary (max 160 chars)\",
    \"slug\": \"An-seo-optimized-url-slug\"
}

Content to analyze:
" . $content;

        $prompt_builder = wp_ai_client_prompt($prompt);
        
        if (!empty($model)) {
            $prompt_builder->using_model_preference($model);
        }

        $response = $prompt_builder->generate_text();

        if (is_wp_error($response)) {
            return $response;
        }

        $json_str = trim($response);
        $json_str = preg_replace('/^```json\s*/i', '', $json_str);
        $json_str = preg_replace('/```$/i', '', $json_str);
        $json_str = trim($json_str);

        $data = json_decode($json_str, true);

        if (!$data || !isset($data['meta_description'])) {
            return new \WP_Error('invalid_json', 'AI did not return valid JSON: ' . $json_str, ['status' => 500]);
        }

        // Update post excerpt and slug
        $update_data = [
            'ID' => $post_id
        ];
        
        if (!empty($data['excerpt'])) {
            $update_data['post_excerpt'] = sanitize_textarea_field($data['excerpt']);
        }
        if (!empty($data['slug'])) {
            $update_data['post_name'] = sanitize_title($data['slug']);
        }

        wp_update_post($update_data);

        // Update SEO Plugin Meta
        if (!empty($data['title'])) {
            $title = sanitize_text_field($data['title']);
            update_post_meta($post_id, '_yoast_wpseo_title', $title);
            update_post_meta($post_id, 'rank_math_title', $title);
            update_post_meta($post_id, '_aioseo_title', $title);
        }

        if (!empty($data['meta_description'])) {
            $desc = sanitize_textarea_field($data['meta_description']);
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $desc);
            update_post_meta($post_id, 'rank_math_description', $desc);
            update_post_meta($post_id, '_aioseo_description', $desc);
        }

        update_post_meta($post_id, '_wai_seo_optimized', time());

        return new \WP_REST_Response(['success' => true, 'data' => $data], 200);
    }
}
