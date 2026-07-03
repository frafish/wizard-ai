<?php
namespace WizardAi\Modules\Playground\Traits;

trait Context {
    private function get_directory_tree($dir, $max_files = 200, &$file_count = 0, $prefix = '') {
        $tree = '';
        if (!is_dir($dir)) return $tree;
        
        $excludes = ['.git', 'node_modules', 'vendor', 'assets', 'images', 'dist', 'build', '.DS_Store'];
        $files = array_diff(scandir($dir), ['.', '..']);
        $files = array_filter($files, function($f) use ($excludes) {
            return !in_array($f, $excludes);
        });
        
        if (empty($files)) return $tree;
        $last_key = array_key_last($files);
        
        foreach ($files as $key => $file) {
            if ($file_count >= $max_files) {
                if ($file_count === $max_files) {
                    $tree .= $prefix . "└── ... (tree truncated, exceeded {$max_files} files)\n";
                    $file_count++;
                }
                break;
            }
            
            $path = $dir . '/' . $file;
            $is_last = ($key === $last_key);
            $pointer = $is_last ? '└── ' : '├── ';
            
            $tree .= $prefix . $pointer . $file . "\n";
            $file_count++;
            
            if (is_dir($path)) {
                $extension_prefix = $is_last ? '    ' : '│   ';
                $tree .= $this->get_directory_tree($path, $max_files, $file_count, $prefix . $extension_prefix);
            }
        }
        return $tree;
    }

    private function get_environment_details() {
        global $wpdb;
        $active_blocks = [];
        if (class_exists('\WizardBlocks\Modules\Block\Block')) {
            $wb = \WizardBlocks\Modules\Block\Block::instance();
            $block_posts = get_posts(['post_type' => 'block', 'posts_per_page' => -1, 'post_status' => 'publish']);
            foreach ($block_posts as $post) {
                $json = $wb->get_json_data($post->post_name);
                $textdomain = $wb->get_block_textdomain($json);
                $block_dir = $wb->get_blocks_dir($post->post_name, $textdomain);
                $active_blocks[] = $textdomain . '/' . $post->post_name . ' (Path: ' . wp_normalize_path($block_dir) . '/block.json)';
            }
        } else {
            $active_blocks = array_map(function($post) { return $post->post_name; }, get_posts(['post_type' => 'block', 'posts_per_page' => -1, 'post_status' => 'publish']));
        }
        
        $latest_errors = '';
        $debug_log_path = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($debug_log_path) && is_readable($debug_log_path)) {
            $filesize = filesize($debug_log_path);
            $read_size = min(100000, $filesize);
            $file = @fopen($debug_log_path, 'r');
            if ($file && $read_size > 0) {
                fseek($file, -$read_size, SEEK_END);
                $log_content = fread($file, $read_size);
                fclose($file);
                
                if ($log_content) {
                    $lines = explode("\n", $log_content);
                    $lines = array_reverse($lines);
                    $unique_errors = [];
                    foreach ($lines as $line) {
                        if (stripos($line, 'Parse error') !== false || stripos($line, 'Fatal error') !== false || stripos($line, 'Uncaught Error') !== false || stripos($line, 'database error') !== false) {
                            $error_msg = preg_replace('/^\[.*?\]\s*/', '', $line);
                            if (!in_array($error_msg, $unique_errors)) {
                                $unique_errors[] = $error_msg;
                                if (count($unique_errors) >= 5) break;
                            }
                        }
                    }
                    if (!empty($unique_errors)) {
                        $latest_errors = "\n- LATEST ERRORS (from debug.log):\n  * " . implode("\n  * ", $unique_errors) . "\n";
                    }
                }
            }
        }

        $real_plugins_str = $wpdb->get_var("SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'active_plugins'");
        $real_plugins = $real_plugins_str ? unserialize($real_plugins_str) : [];
        if (!is_array($real_plugins)) $real_plugins = [];
        
        $real_sw_plugins_str = $wpdb->get_var("SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'active_sitewide_plugins'");
        $real_sw_plugins = $real_sw_plugins_str ? unserialize($real_sw_plugins_str) : [];
        if (!is_array($real_sw_plugins)) $real_sw_plugins = [];
        $real_plugins = array_merge($real_plugins, array_keys($real_sw_plugins));
        
        $real_stylesheet = $wpdb->get_var("SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'stylesheet'");
        $real_template = $wpdb->get_var("SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'template'");
        $real_theme = wp_get_theme($real_stylesheet);
        $real_theme_name = $real_theme->exists() ? $real_theme->get('Name') : $real_stylesheet;
        
        $theme_dir = get_theme_root() . '/' . $real_stylesheet;
        $theme_tree = $this->get_directory_tree($theme_dir);
        $theme_info = "";
        if (!empty($theme_tree)) {
            $theme_info = "- Active Theme Folder Tree (Excluding vendor/node_modules):\n" . $theme_tree;
        }
        
        $db_tables = $wpdb->get_col("SHOW TABLES");
        $tables_info = "- Database Tables: " . (!empty($db_tables) ? implode(', ', $db_tables) : 'None') . "\n";

        $wpml_info = "";
        if (defined('ICL_SITEPRESS_VERSION') || has_filter('wpml_active_languages')) {
            $default_lang = apply_filters('wpml_default_language', null);
            $active_langs = apply_filters('wpml_active_languages', null, 'orderby=id&order=desc');
            $langs_str = [];
            if (is_array($active_langs)) {
                foreach ($active_langs as $lang) {
                    $langs_str[] = isset($lang['code']) ? $lang['code'] . ($lang['code'] === $default_lang ? ' (Main)' : '') : '';
                }
                $langs_str = array_filter($langs_str);
                if (!empty($langs_str)) {
                    $wpml_info = "- WPML Languages: " . implode(', ', $langs_str) . "\n";
                }
            }
        }

        $patterns_info = "";
        if (class_exists('\WP_Block_Patterns_Registry')) {
            $patterns = \WP_Block_Patterns_Registry::get_instance()->get_all_registered();
            if (!empty($patterns)) {
                $patterns_list = [];
                foreach ($patterns as $pattern) {
                    $patterns_list[] = isset($pattern['name']) ? $pattern['name'] : '';
                }
                $patterns_list = array_filter($patterns_list);
                if (!empty($patterns_list)) {
                    $patterns_info = "- Gutenberg Patterns (insert using <!-- wp:pattern {\"slug\":\"PATTERN_NAME\"} /-->):\n  " . implode(', ', $patterns_list) . "\n\n";
                }
            }
        }

        $templates_info = "";
        if (function_exists('get_block_templates')) {
            $templates = get_block_templates();
            if (!empty($templates)) {
                $templates_list = [];
                foreach ($templates as $template) {
                    $templates_list[] = isset($template->id) ? $template->id : '';
                }
                $templates_list = array_filter($templates_list);
                if (!empty($templates_list)) {
                    $templates_info = "- Gutenberg Templates:\n  " . implode(', ', $templates_list) . "\n\n";
                }
            }
        }

        return "ENVIRONMENT DETAILS:\n"
            . "- WordPress Version: " . get_bloginfo('version') . "\n"
            . "- Site URL: " . get_bloginfo('url') . "\n"
            . "- ABSPATH (Root Directory): " . ABSPATH . "\n"
            . "- WP_CONTENT_DIR: " . WP_CONTENT_DIR . "\n\n"
            . "- Active Plugins: " . implode(', ', $real_plugins) . "\n\n"
            . "- Active Blocks: " . (!empty($active_blocks) ? implode(', ', $active_blocks) : 'None') . "\n\n"
            . $patterns_info
            . $templates_info
            . "- Active Theme: " . $real_theme_name . " (" . $theme_dir . ")\n"
            . $theme_info . "\n"
            . "- Database Prefix: " . $wpdb->prefix . "\n"
            . $tables_info
            . $wpml_info
            . $latest_errors;
    }


}
