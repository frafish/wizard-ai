<?php
namespace WizardAi\Modules\Ai\Abilities;

trait WizardBlocks {
    public function register_wizard_blocks_abilities() {
        if (!class_exists('\WizardBlocks\Modules\Block\Block')) {
            return;
        }

        wp_register_ability('wizard-blocks/create-block', [
            'label' => __('Create Wizard Block', 'wizard-ai'),
            'description' => __('Dynamically create a new custom Gutenberg block using Wizard Blocks. Provide HTML/PHP (render.php), CSS (style.css), and JS (script.js) to generate the block. The AI should use this when advanced layouts or custom logic are required. The created block can then be inserted using <!-- wp:username/block-slug /-->.', 'wizard-ai'),
            'category' => 'wizard-blocks',
            'meta' => ['plugin_name' => 'Wizard AI'],
            'execute_callback' => function($input) {
                if (!class_exists('\WizardBlocks\Modules\Block\Block')) {
                    return new \WP_Error('plugin_missing', 'Wizard Blocks plugin is not installed or active.');
                }
                
                $wb = \WizardBlocks\Modules\Block\Block::instance();
                $block_slug = sanitize_title($input['slug']);
                
                $block_textdomain = $wb->get_plugin_textdomain();
                if ($user = wp_get_current_user()) {
                    $block_textdomain = $user->user_nicename;
                }
                
                $basepath = $wb->get_ensure_blocks_dir($block_slug, $block_textdomain);
                
                // Check if block already exists
                $existing_post = $wb->get_block_post($block_slug);
                if ($existing_post) {
                    $post_id = $existing_post->ID;
                    wp_update_post([
                        'ID' => $post_id,
                        'post_title' => $input['title'] ?? 'Custom Block',
                        'post_excerpt' => $input['description'] ?? '',
                    ]);
                } else {
                    $post_id = wp_insert_post([
                        'post_title' => $input['title'] ?? 'Custom Block',
                        'post_name' => $block_slug,
                        'post_type' => \WizardBlocks\Modules\Block\Block::get_cpt_name(),
                        'post_status' => 'publish',
                        'post_excerpt' => $input['description'] ?? '',
                    ]);
                }
                
                if (is_wp_error($post_id)) {
                    return $post_id;
                }
                
                $attributes = isset($input['attributes']) ? $input['attributes'] : [];
                
                $json = [
                    "\$schema" => "https://schemas.wp.org/trunk/block.json",
                    "apiVersion" => 3,
                    "name" => $block_textdomain . "/" . $block_slug,
                    "title" => $input['title'] ?? 'Custom Block',
                    "category" => "design",
                    "description" => $input['description'] ?? '',
                    "textdomain" => $block_textdomain,
                    "attributes" => $attributes,
                    "supports" => [
                        "html" => false,
                        "className" => true,
                        "color" => ["background" => true, "text" => true],
                        "typography" => ["fontSize" => true],
                        "spacing" => ["margin" => true, "padding" => true]
                    ]
                ];
                
                // Write files
                if (!empty($input['html'])) {
                    $wb->get_filesystem()->put_contents($basepath . 'render.php', $input['html']);
                    $json['render'] = "file:./render.php";
                } else {
                    if (file_exists($basepath . 'render.php')) $wb->get_filesystem()->delete($basepath . 'render.php');
                }
                
                if (!empty($input['css'])) {
                    $wb->get_filesystem()->put_contents($basepath . 'style.css', $input['css']);
                    $json['style'] = "file:./style.css";
                } else {
                    if (file_exists($basepath . 'style.css')) $wb->get_filesystem()->delete($basepath . 'style.css');
                }
                
                if (!empty($input['js'])) {
                    $wb->get_filesystem()->put_contents($basepath . 'script.js', $input['js']);
                    $json['script'] = "file:./script.js";
                } else {
                    if (file_exists($basepath . 'script.js')) $wb->get_filesystem()->delete($basepath . 'script.js');
                }
                
                $wb->get_filesystem()->put_contents($basepath . 'block.json', wp_json_encode($json, JSON_PRETTY_PRINT));
                
                // Dry Run Verification
                if (!empty($input['html'])) {
                    $mock_attributes = [];
                    $attr_schema = $input['attributes'] ?? [];
                    if (is_array($attr_schema)) {
                        foreach ($attr_schema as $key => $schema) {
                            if (isset($schema['default'])) {
                                $mock_attributes[$key] = $schema['default'];
                            } else {
                                $t = $schema['type'] ?? 'string';
                                if ($t === 'array' || $t === 'object') $mock_attributes[$key] = [];
                                elseif ($t === 'boolean') $mock_attributes[$key] = false;
                                elseif ($t === 'number' || $t === 'integer') $mock_attributes[$key] = 0;
                                else $mock_attributes[$key] = '';
                            }
                        }
                    }
                    
                    $error_caught = null;
                    set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$error_caught) {
                        $error_caught = new \ErrorException($errstr, 0, $errno, $errfile, $errline);
                        return true;
                    });
                    
                    ob_start();
                    try {
                        (function() use ($basepath, $mock_attributes) {
                            $attributes = $mock_attributes;
                            $content = '';
                            $block = new \stdClass();
                            include $basepath . 'render.php';
                        })();
                        ob_end_clean();
                    } catch (\Throwable $e) {
                        ob_end_clean();
                        $error_caught = $e;
                    }
                    restore_error_handler();
                    
                    if ($error_caught) {
                        // Revert everything
                        $wb->get_filesystem()->delete($basepath . 'render.php');
                        if (!empty($input['css'])) $wb->get_filesystem()->delete($basepath . 'style.css');
                        if (!empty($input['js'])) $wb->get_filesystem()->delete($basepath . 'script.js');
                        $wb->get_filesystem()->delete($basepath . 'block.json');
                        
                        return new \WP_Error('render_failed', 'Render Verification Failed! Your render.php code threw an error/warning: "' . $error_caught->getMessage() . '" on line ' . $error_caught->getLine() . '. This usually happens when you access $attributes keys without checking isset() or using the ?? operator. The block files were NOT saved. Please fix your PHP code to be strictly safe and try again.');
                    }
                }
                
                return [
                    'success' => true, 
                    'message' => 'Block successfully created.',
                    'block_name' => $json['name'],
                    'insert_example' => '<!-- wp:' . $json['name'] . ' /-->'
                ];
            },
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'slug' => ['type' => 'string', 'description' => 'The slug of the block (e.g. my-custom-block)'],
                    'title' => ['type' => 'string', 'description' => 'The human-readable title of the block'],
                    'description' => ['type' => 'string', 'description' => 'A short description of the block'],
                    'html' => ['type' => 'string', 'description' => 'The HTML/PHP markup for render.php'],
                    'css' => ['type' => 'string', 'description' => 'The CSS for style.css'],
                    'js' => ['type' => 'string', 'description' => 'The Javascript for script.js'],
                    'attributes' => [
                        'type' => 'object', 
                        'description' => 'Key-value pairs for Gutenberg block attributes (optional). Example: {"myText": {"type": "string", "default": "Hello"}}',
                        'additionalProperties' => true
                    ]
                ],
                'required' => ['slug', 'title', 'html']
            ]
        ]);
        wp_register_ability('wizard-blocks/modify-block', [
            'label' => __('Modify Wizard Block', 'wizard-ai'),
            'description' => __('Modify an existing custom Gutenberg block. Only provide the files (html, css, or js) that you want to change. Omitted files will remain untouched.', 'wizard-ai'),
            'category' => 'wizard-blocks',
            'meta' => ['plugin_name' => 'Wizard AI'],
            'execute_callback' => function($input) {
                if (!class_exists('\WizardBlocks\Modules\Block\Block')) {
                    return new \WP_Error('plugin_missing', 'Wizard Blocks plugin is not installed or active.');
                }
                
                $wb = \WizardBlocks\Modules\Block\Block::instance();
                $block_slug = sanitize_title($input['slug']);
                
                $block_textdomain = $wb->get_plugin_textdomain();
                if ($user = wp_get_current_user()) {
                    $block_textdomain = $user->user_nicename;
                }
                
                $existing_post = $wb->get_block_post($block_slug);
                if (!$existing_post) {
                    return new \WP_Error('block_not_found', 'Block not found. Please create it first.');
                }
                
                $basepath = $wb->get_ensure_blocks_dir($block_slug, $block_textdomain);
                
                if (file_exists($basepath . 'block.json')) {
                    $json = json_decode(file_get_contents($basepath . 'block.json'), true);
                } else {
                    $json = [];
                }
                
                // Write files
                $backup_html = null;
                if (isset($input['html'])) {
                    if (!empty($input['html'])) {
                        $backup_html = file_exists($basepath . 'render.php') ? file_get_contents($basepath . 'render.php') : null;
                        $wb->get_filesystem()->put_contents($basepath . 'render.php', $input['html']);
                        $json['render'] = "file:./render.php";
                    } else {
                        if (file_exists($basepath . 'render.php')) $wb->get_filesystem()->delete($basepath . 'render.php');
                        unset($json['render']);
                    }
                }
                
                if (isset($input['css'])) {
                    if (!empty($input['css'])) {
                        $wb->get_filesystem()->put_contents($basepath . 'style.css', $input['css']);
                        $json['style'] = "file:./style.css";
                    } else {
                        if (file_exists($basepath . 'style.css')) $wb->get_filesystem()->delete($basepath . 'style.css');
                        unset($json['style']);
                    }
                }
                
                if (isset($input['js'])) {
                    if (!empty($input['js'])) {
                        $wb->get_filesystem()->put_contents($basepath . 'script.js', $input['js']);
                        $json['script'] = "file:./script.js";
                    } else {
                        if (file_exists($basepath . 'script.js')) $wb->get_filesystem()->delete($basepath . 'script.js');
                        unset($json['script']);
                    }
                }
                
                $wb->get_filesystem()->put_contents($basepath . 'block.json', wp_json_encode($json, JSON_PRETTY_PRINT));
                
                // Dry Run Verification
                if (isset($input['html']) && !empty($input['html'])) {
                    $mock_attributes = [];
                    $attr_schema = $json['attributes'] ?? [];
                    if (is_array($attr_schema)) {
                        foreach ($attr_schema as $key => $schema) {
                            if (isset($schema['default'])) {
                                $mock_attributes[$key] = $schema['default'];
                            } else {
                                $t = $schema['type'] ?? 'string';
                                if ($t === 'array' || $t === 'object') $mock_attributes[$key] = [];
                                elseif ($t === 'boolean') $mock_attributes[$key] = false;
                                elseif ($t === 'number' || $t === 'integer') $mock_attributes[$key] = 0;
                                else $mock_attributes[$key] = '';
                            }
                        }
                    }
                    
                    $error_caught = null;
                    set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$error_caught) {
                        $error_caught = new \ErrorException($errstr, 0, $errno, $errfile, $errline);
                        return true;
                    });
                    
                    ob_start();
                    try {
                        (function() use ($basepath, $mock_attributes) {
                            $attributes = $mock_attributes;
                            $content = '';
                            $block = new \stdClass();
                            include $basepath . 'render.php';
                        })();
                        ob_end_clean();
                    } catch (\Throwable $e) {
                        ob_end_clean();
                        $error_caught = $e;
                    }
                    restore_error_handler();
                    
                    if ($error_caught) {
                        // Revert HTML
                        if ($backup_html !== null) {
                            $wb->get_filesystem()->put_contents($basepath . 'render.php', $backup_html);
                        } else {
                            $wb->get_filesystem()->delete($basepath . 'render.php');
                        }
                        
                        return new \WP_Error('render_failed', 'Render Verification Failed! Your modified render.php code threw an error/warning: "' . $error_caught->getMessage() . '" on line ' . $error_caught->getLine() . '. The file was NOT saved. Please fix your PHP code (e.g. check isset() for attributes) and try again.');
                    }
                }
                
                return [
                    'success' => true, 
                    'message' => 'Block successfully modified.',
                    'block_name' => $json['name'] ?? ''
                ];
            },
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'slug' => ['type' => 'string', 'description' => 'The slug of the block to modify (e.g. my-custom-block)'],
                    'html' => ['type' => 'string', 'description' => 'The updated HTML/PHP markup for render.php'],
                    'css' => ['type' => 'string', 'description' => 'The updated CSS for style.css'],
                    'js' => ['type' => 'string', 'description' => 'The updated Javascript for script.js']
                ],
                'required' => ['slug']
            ]
        ]);
    }
}
