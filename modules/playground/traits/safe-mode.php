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
            "\$is_ai_rest = strpos(\$_SERVER['REQUEST_URI'] ?? '', '/wizard-ai/v1/ai') !== false;\n" .
            "\$is_toggle_rest = strpos(\$_SERVER['REQUEST_URI'] ?? '', '/wizard-ai/v1/toggle-safe-mode') !== false;\n" .
            "\$is_cron = strpos(\$_SERVER['REQUEST_URI'] ?? '', '/wp-cron.php') !== false;\n" .
            "\$is_login = strpos(\$_SERVER['REQUEST_URI'] ?? '', 'wp-login.php') !== false;\n" .
            "\$saved_token = get_option('wai_mcp_token', '');\n" .
            "\$valid_token = !empty(\$saved_token) && isset(\$_REQUEST['token']) && \$_REQUEST['token'] === \$saved_token;\n" .
            "\$enforce_ai = file_exists(ABSPATH . '.wai_safe') || (isset(\$_REQUEST['wai_enforce_safe_mode']) && \$_REQUEST['wai_enforce_safe_mode'] === '1' && (!\$is_login || \$valid_token));\n" .
            "\n" .
            "// Autonomous Cron Crash Recovery\n" .
            "if (\$is_cron) {\n" .
            "    \$cron_flag = ABSPATH . '.wb_ai_cron_running';\n" .
            "    if (file_exists(\$cron_flag)) {\n" .
            "        \$enforce_ai = true;\n" .
            "    }\n" .
            "    file_put_contents(\$cron_flag, '1');\n" .
            "    register_shutdown_function(function() use (\$cron_flag) {\n" .
            "        \$error = error_get_last();\n" .
            "        if (\$error === null || !in_array(\$error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {\n" .
            "            @wp_delete_file(\$cron_flag);\n" .
            "        }\n" .
            "    });\n" .
            "}\n" .
            "\$is_ai_redirect = (\$_SERVER['REQUEST_URI'] ?? '') === '/wai' || (\$_SERVER['REQUEST_URI'] ?? '') === '/wai/';\n" .
            "if (\$is_ai_redirect) {\n" .
            "    header('Location: /wp-admin/admin.php?page=wizard-ai');\n" .
            "    exit;\n" .
            "}\n" .
            "if (\$is_playground_page || ((\$is_ai_rest || \$is_toggle_rest || \$is_cron || \$is_login) && \$enforce_ai)) {\n" .
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
            "    if (\$is_login) {\n" .
            "        add_action('login_form', function() {\n" .
            "            echo '<input type=\"hidden\" name=\"wai_enforce_safe_mode\" value=\"1\" />';\n" .
            "            if (isset(\$_REQUEST['token'])) {\n" .
            "                echo '<input type=\"hidden\" name=\"token\" value=\"' . esc_attr(\$_REQUEST['token']) . '\" />';\n" .
            "            }\n" .
            "        });\n" .
            "    }\n" .
            "}\n" .
            "\n" .
            "// --- Wizard AI Sandbox ---\n" .
            "\$sandbox_dir = WP_CONTENT_DIR . '/wai-sandbox/';\n" .
            "if (is_dir(\$sandbox_dir) && !file_exists(\$sandbox_dir . '.wai_crash')) {\n" .
            "    \$sandbox_files = glob(\$sandbox_dir . '*.php');\n" .
            "    if (\$sandbox_files) {\n" .
            "        \$crashed_file = \$sandbox_dir . '.wai_crash';\n" .
            "        global \$wai_current_sandbox_file;\n" .
            "        \$wai_current_sandbox_file = null;\n" .
            "        register_shutdown_function(function() use (\$crashed_file) {\n" .
            "            global \$wai_current_sandbox_file;\n" .
            "            if (\$wai_current_sandbox_file !== null) {\n" .
            "                \$error = error_get_last();\n" .
            "                if (\$error !== null && (\$error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {\n" .
            "                    file_put_contents(\$crashed_file, wp_json_encode(['error' => \$error, 'file' => \$wai_current_sandbox_file]));\n" .
            "                }\n" .
            "            }\n" .
            "        });\n" .
            "        foreach (\$sandbox_files as \$file) {\n" .
            "            \$wai_current_sandbox_file = \$file;\n" .
            "            require_once \$file;\n" .
            "        }\n" .
            "        \$wai_current_sandbox_file = null;\n" .
            "    }\n" .
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
            wp_delete_file($plugin_file);
        }
        if (is_dir($theme_dir)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
            global $wp_filesystem;
            $wp_filesystem->rmdir($theme_dir, true);
        }
    }

    public function toggle_safe_mode(\WP_REST_Request $request) {
        $flag_file = ABSPATH . '.wai_safe';
        $force = $request->get_param('force');
        
        if ($force === 'enable') {
            file_put_contents($flag_file, '1');
            return new \WP_REST_Response(['success' => true, 'safe_mode' => true], 200);
        } elseif ($force === 'disable') {
            if (file_exists($flag_file)) @wp_delete_file($flag_file);
            return new \WP_REST_Response(['success' => true, 'safe_mode' => false], 200);
        }

        if (file_exists($flag_file)) {
            @wp_delete_file($flag_file);
            return new \WP_REST_Response(['success' => true, 'safe_mode' => false], 200);
        } else {
            file_put_contents($flag_file, '1');
            return new \WP_REST_Response(['success' => true, 'safe_mode' => true], 200);
        }
    }
}
