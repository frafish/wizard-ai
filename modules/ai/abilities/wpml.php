<?php
namespace WizardAi\Modules\Ai\Abilities;

trait Wpml {
    public function register_wpml_abilities() {
        if (defined('ICL_SITEPRESS_VERSION')) {
            wp_register_ability('ai/manage-wpml', [
                'label' => __('Manage WPML', 'wizard-ai'),
                'description' => __('Translate posts, media, terms, and strings using WPML.', 'wizard-ai'),
                'category' => 'wizard-blocks',
                'execute_callback' => function($input) {
                    global $sitepress;
                    $action = $input['action'];
                    $args = isset($input['args']) ? $input['args'] : [];
                    
                    if ($action === 'translate_post') {
                        $post_id = intval($args['post_id'] ?? 0);
                        $lang = $args['language'] ?? '';
                        $title = $args['title'] ?? '';
                        $content = $args['content'] ?? '';
                        
                        if (!$post_id || !$lang) return new \WP_Error('missing_args', 'post_id and language are required.');
                        
                        $post = get_post($post_id);
                        if (!$post) return new \WP_Error('invalid_post', 'Post not found.');
                        
                        $trid = $sitepress->get_element_trid($post_id, 'post_' . $post->post_type);
                        
                        $new_post_data = [
                            'post_title' => $title ?: $post->post_title,
                            'post_content' => $content ?: $post->post_content,
                            'post_type' => $post->post_type,
                            'post_status' => $post->post_status,
                        ];
                        
                        if ($post->post_type === 'attachment') {
                            $new_post_data['post_mime_type'] = $post->post_mime_type;
                            $new_post_data['guid'] = $post->guid;
                        }
                        
                        $new_post_id = wp_insert_post($new_post_data);
                        
                        if (is_wp_error($new_post_id)) return $new_post_id;
                        
                        if ($post->post_type === 'attachment') {
                            $attached_file = get_post_meta($post_id, '_wp_attached_file', true);
                            if ($attached_file) update_post_meta($new_post_id, '_wp_attached_file', $attached_file);
                            $attachment_metadata = get_post_meta($post_id, '_wp_attachment_metadata', true);
                            if ($attachment_metadata) update_post_meta($new_post_id, '_wp_attachment_metadata', $attachment_metadata);
                        }
                        
                        $sitepress->set_element_language_details($new_post_id, 'post_' . $post->post_type, $trid, $lang, $sitepress->get_default_language());
                        return ['success' => true, 'new_post_id' => $new_post_id];
                    } elseif ($action === 'translate_term') {
                        $term_id = intval($args['term_id'] ?? 0);
                        $taxonomy = $args['taxonomy'] ?? '';
                        $lang = $args['language'] ?? '';
                        $name = $args['name'] ?? '';
                        
                        if (!$term_id || !$taxonomy || !$lang || !$name) return new \WP_Error('missing_args', 'term_id, taxonomy, language, and name are required.');
                        
                        $trid = $sitepress->get_element_trid($term_id, 'tax_' . $taxonomy);
                        $new_term = wp_insert_term($name, $taxonomy);
                        
                        if (is_wp_error($new_term)) return $new_term;
                        
                        $sitepress->set_element_language_details($new_term['term_id'], 'tax_' . $taxonomy, $trid, $lang, $sitepress->get_default_language());
                        return ['success' => true, 'new_term_id' => $new_term['term_id']];
                    } elseif ($action === 'translate_string') {
                        $context = $args['context'] ?? 'wizard_blocks';
                        $name = $args['name'] ?? '';
                        $value = $args['value'] ?? '';
                        $lang = $args['language'] ?? '';
                        
                        if (!$name || !$value || !$lang) return new \WP_Error('missing_args', 'name, value, and language are required.');
                        
                        if (function_exists('icl_register_string') && function_exists('icl_add_string_translation') && function_exists('icl_get_string_id')) {
                            icl_register_string($context, $name, $args['original_value'] ?? $value);
                            $string_id = icl_get_string_id($args['original_value'] ?? $value, $context, $name);
                            if ($string_id) {
                                icl_add_string_translation($string_id, $lang, $value, ICL_TM_COMPLETE);
                                return ['success' => true, 'message' => 'String translated successfully.'];
                            }
                        }
                        return new \WP_Error('string_error', 'String translation functions not available or string not found.');
                    }
                    
                    return new \WP_Error('invalid_action', 'Unsupported action.');
                },
                'permission_callback' => function() { return current_user_can('manage_options'); },
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'action' => ['type' => 'string', 'enum' => ['translate_post', 'translate_term', 'translate_string'], 'description' => 'Action to perform'],
                        'args' => ['type' => 'object', 'description' => 'Arguments. E.g. {"post_id":123, "language":"fr", "title":"Bonjour", "content":"..."}']
                    ],
                    'required' => ['action']
                ]
            ]);
        }

    }
}
