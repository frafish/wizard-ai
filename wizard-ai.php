<?php
/**
 * Plugin Name: Wizard AI
 * Description: AI capabilities for Wizard Blocks.
 * Version: 1.0.0
 * Author: frapesce
 * Text Domain: wizard-ai
 * Requires at least: 7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WIZARD_AI_FILE', __FILE__);
define('WIZARD_AI_URL', plugins_url(DIRECTORY_SEPARATOR, __FILE__));
define('WIZARD_AI_PATH', str_replace('/', DIRECTORY_SEPARATOR, plugin_dir_path(__FILE__)));

add_action('plugins_loaded', function() {
    load_plugin_textdomain('wizard-ai');

    if (file_exists(WIZARD_AI_PATH . 'vendor/autoload.php')) {
        require_once WIZARD_AI_PATH . 'vendor/autoload.php';
    }

    spl_autoload_register(function ($class) {
        $prefix = 'WizardAi\\';
        $base_dir = WIZARD_AI_PATH;
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relative_class = substr($class, $len);
        $path = str_replace('\\', '/', $relative_class);
        $file = $base_dir . strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $path)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    });
    
    new \WizardAi\Modules\Ai\Ai();
});

// --- RAG CRON INTEGRATION ---
require_once WIZARD_AI_PATH . 'cron/init.php';

// Add Settings Link on Plugin Page
add_filter('plugin_action_links_' . plugin_basename(WIZARD_AI_FILE), 'wizard_ai_plugin_action_links');
function wizard_ai_plugin_action_links($links) {
    if (current_user_can('manage_options')) {
        $settings_url = admin_url('admin.php?page=wizard-ai');
        $links[] = '<a href="' . esc_url($settings_url) . '" style="color:#2271b1;font-weight:600;">' . esc_html__('Settings', 'wizard-ai') . '</a>';
    }
    return $links;
}
