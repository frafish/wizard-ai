<?php
require_once '/var/www/html/wp-load.php';
$abilities = function_exists('wp_get_abilities') ? wp_get_abilities() : [];
$abilities = apply_filters('wizard_blocks_ai_abilities', $abilities);

$resolver = new \WP_AI_Client_Ability_Function_Resolver(...$abilities);
$clean_schema = function(&$schema) use (&$clean_schema) {
    if (!is_array($schema)) return;
    $allowed_keys = ['type', 'description', 'properties', 'required', 'items', 'enum'];
    foreach ($schema as $k => $v) {
        if (!in_array($k, $allowed_keys, true)) {
            unset($schema[$k]);
        } elseif (is_array($v)) {
            if ($k === 'type') {
                $schema[$k] = is_array($v) && !empty($v) ? $v[0] : 'string';
            } elseif ($k === 'properties') {
                if (empty($v)) {
                    $schema[$k] = new \stdClass();
                } else {
                    foreach ($schema[$k] as $prop_name => &$prop_val) {
                        $clean_schema($prop_val);
                    }
                }
            } elseif ($k === 'items') {
                if (is_array($v) && isset($v[0])) {
                    $schema[$k] = $v[0];
                }
                $clean_schema($schema[$k]);
            }
        }
    }
};

$functions = [];
foreach ($abilities as $ability) {
    $function_name = \WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name($ability->get_name());
    $input_schema = $ability->get_input_schema();
    if (empty($input_schema)) {
        $input_schema = ['type' => 'object', 'properties' => new \stdClass()];
    } else {
        $clean_schema($input_schema);
        if (empty($input_schema['properties'])) {
            $input_schema['properties'] = new \stdClass();
        }
        if (empty($input_schema['type'])) {
            $input_schema['type'] = 'object';
        }
    }
    $functions[] = [
        'name' => $function_name,
        'description' => $ability->get_description(),
        'parameters' => $input_schema
    ];
}

$json = json_encode(['tools' => [['function_declarations' => $functions]]], JSON_PRETTY_PRINT);
file_put_contents('/var/www/html/wp-content/plugins/wizard-ai/test-schema.json', $json);
echo "Wrote test-schema.json\n";
