<?php
namespace WizardAi\Modules\Wpml\traits;

trait Elementor {
    public function is_elementor_post($post_id) {
        return get_post_meta($post_id, '_elementor_edit_mode', true) === 'builder';
    }

    public function prepare_elementor_for_translation($post_id, &$custom_fields_to_translate) {
        $elementor_data_raw = get_post_meta($post_id, '_elementor_data', true);
        $elementor_data_json = json_decode($elementor_data_raw, true);
        if ($elementor_data_json) {
            $custom_fields_to_translate['_elementor_data_decoded'] = $elementor_data_json;
        }
    }

    public function get_elementor_prompt_instructions() {
        return "3. This post is built with Elementor. The custom field '_elementor_data_decoded' contains the Elementor widget structure. You MUST recursively translate all visible text values (like title, description, editor, text, html) inside this JSON object, while strictly preserving all IDs, setting keys, and the full JSON array/object structure.\n";
    }

    public function get_elementor_prompt_json_instruction() {
        return "    \"_elementor_data_decoded\": [ ... array of Elementor widgets ... ]\n";
    }

    public function save_translated_elementor_data($new_post_id, $translated_custom_fields) {
        if (isset($translated_custom_fields['_elementor_data_decoded'])) {
            update_post_meta($new_post_id, '_elementor_data', wp_slash(wp_json_encode($translated_custom_fields['_elementor_data_decoded'])));
        }
    }

    public function get_elementor_meta_keys_to_copy() {
        return [
            '_elementor_edit_mode',
            '_elementor_template_type',
            '_elementor_version',
            '_elementor_pro_version',
            '_elementor_page_settings',
            '_elementor_controls_usage'
        ];
    }
}
