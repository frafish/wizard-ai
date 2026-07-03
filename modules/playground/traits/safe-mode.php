<?php
namespace WizardAi\Modules\Playground\Traits;

trait SafeMode {

    public function enable_safe_mode() {
        $mu_dir = WPMU_PLUGIN_DIR;
        if (!is_dir($mu_dir)) {
            wp_mkdir_p($mu_dir);
        }
        $plugin_file = trailingslashit($mu_dir) . 'wizard-ai-safe-mode.php';
        
        $theme_dir = get_theme_root() . '/wizard-ai-safe-theme';
        if (!is_dir($theme_dir)) {
            wp_mkdir_p($theme_dir);
            file_put_contents($theme_dir . '/style.css', "/*\nTheme Name: Wizard AI Safe Theme\n*/");
            file_put_contents($theme_dir . '/index.php', "");
        }

        $code = "<?php\n" .
            "/*\n" .
            "Plugin Name: Wizard AI Safe Mode\n" .
            "Description: Forces empty theme and disables other plugins on the Playground page.\n" .
            "*/\n" .
            "\$is_playground_page = isset(\$_GET['page']) && \$_GET['page'] === 'wizard-ai';\n" .
            "\$is_ai_rest = strpos(\$_SERVER['REQUEST_URI'] ?? '', '/wizard-blocks/v1/ai') !== false;\n" .
            "\$is_toggle_rest = strpos(\$_SERVER['REQUEST_URI'] ?? '', '/wizard-blocks/v1/toggle-safe-mode') !== false;\n" .
            "\$enforce_ai = file_exists(ABSPATH . '.wb_ai_safe') || (isset(\$_GET['wai_enforce_safe_mode']) && \$_GET['wai_enforce_safe_mode'] === '1');\n" .
            "\$is_ai_redirect = (\$_SERVER['REQUEST_URI'] ?? '') === '/wai' || (\$_SERVER['REQUEST_URI'] ?? '') === '/wai/';\n" .
            "if (\$is_ai_redirect) {\n" .
            "    header('Location: /wp-admin/admin.php?page=wizard-ai');\n" .
            "    exit;\n" .
            "}\n" .
            "if (\$is_playground_page || ((\$is_ai_rest || \$is_toggle_rest) && \$enforce_ai)) {\n" .
            "    add_filter('option_active_plugins', function(\$plugins) {\n" .
            "        \$allowed = [];\n" .
            "        foreach (\$plugins as \$plugin) {\n" .
            "            if (strpos(\$plugin, 'wizard-blocks') !== false || strpos(\$plugin, 'wizard-ai') !== false || strpos(\$plugin, 'ai-provider') !== false) {\n" .
            "                \$allowed[] = \$plugin;\n" .
            "            }\n" .
            "        }\n" .
            "        return \$allowed;\n" .
            "    });\n" .
            "    add_filter('option_active_sitewide_plugins', function(\$plugins) {\n" .
            "        \$allowed = [];\n" .
            "        if (is_array(\$plugins)) {\n" .
            "            foreach (\$plugins as \$plugin => \$time) {\n" .
            "                if (strpos(\$plugin, 'wizard-blocks') !== false || strpos(\$plugin, 'wizard-ai') !== false || strpos(\$plugin, 'ai-provider') !== false) {\n" .
            "                    \$allowed[\$plugin] = \$time;\n" .
            "                }\n" .
            "            }\n" .
            "        }\n" .
            "        return \$allowed;\n" .
            "    });\n" .
            "    add_filter('stylesheet', function(\$theme) { return 'wizard-ai-safe-theme'; });\n" .
            "    add_filter('template', function(\$theme) { return 'wizard-ai-safe-theme'; });\n" .
            "}\n";

        if (!file_exists($plugin_file) || file_get_contents($plugin_file) !== $code) {
            file_put_contents($plugin_file, $code);
        }
    }

    public function disable_safe_mode() {
        $mu_dir = WPMU_PLUGIN_DIR;
        $plugin_file = trailingslashit($mu_dir) . 'wizard-ai-safe-mode.php';
        $theme_dir = get_theme_root() . '/wizard-ai-safe-theme';

        if (file_exists($plugin_file)) {
            unlink($plugin_file);
        }
        if (is_dir($theme_dir)) {
            if (file_exists($theme_dir . '/style.css')) unlink($theme_dir . '/style.css');
            if (file_exists($theme_dir . '/index.php')) unlink($theme_dir . '/index.php');
            rmdir($theme_dir);
        }
    }

    public function toggle_safe_mode(\WP_REST_Request $request) {
        $flag_file = ABSPATH . '.wb_ai_safe';
        $force = $request->get_param('force');
        
        if ($force === 'enable') {
            file_put_contents($flag_file, '1');
            return new \WP_REST_Response(['success' => true, 'safe_mode' => true], 200);
        } elseif ($force === 'disable') {
            if (file_exists($flag_file)) @unlink($flag_file);
            return new \WP_REST_Response(['success' => true, 'safe_mode' => false], 200);
        }

        if (file_exists($flag_file)) {
            @unlink($flag_file);
            return new \WP_REST_Response(['success' => true, 'safe_mode' => false], 200);
        } else {
            file_put_contents($flag_file, '1');
            return new \WP_REST_Response(['success' => true, 'safe_mode' => true], 200);
        }
    }
}
