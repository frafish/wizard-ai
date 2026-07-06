<?php
namespace WizardAi\Modules\Seo\Traits;

trait Api {
    public function api_save_seo_settings(\WP_REST_Request $request) {
        $auto = $request->get_param('auto_optimize');
        $model = $request->get_param('preferred_model');
        $text_model = $request->get_param('text_model');
        
        update_option('wai_auto_optimize_media', $auto);
        update_option('wai_seo_preferred_model', $model);
        update_option('wai_seo_text_model', $text_model);

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

}
