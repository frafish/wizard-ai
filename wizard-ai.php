<?php
/**
 * Plugin Name: Wizard AI
 * Description: AI capabilities suite for WordPress.
 * Version: 1.0.1
 * Author: frapesce
 * Text Domain: wizard-ai
 * Requires at least: 7.0
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WIZARD_AI_FILE', __FILE__);
define('WIZARD_AI_URL', plugins_url(DIRECTORY_SEPARATOR, __FILE__));
define('WIZARD_AI_PATH', str_replace('/', DIRECTORY_SEPARATOR, plugin_dir_path(__FILE__)));

add_action('plugins_loaded', function() {

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
    
    \WizardAi\Modules\Ai\Ai::instance();
    new \WizardAi\Modules\Providers\Providers();
    \WizardAi\Modules\Playground\Playground::instance();
    new \WizardAi\Modules\Editor\Editor();
    new \WizardAi\Modules\Block\Block();
    new \WizardAi\Modules\Chatbot\Chatbot();
    new \WizardAi\Modules\Seo\Seo();
    new \WizardAi\Modules\Markdown\Markdown();
    new \WizardAi\Modules\Mcp\Mcp();
    new \WizardAi\Modules\Wpml\Wpml();

});

register_deactivation_hook(WIZARD_AI_FILE, 'wizard_ai_deactivate');
function wizard_ai_deactivate() {
    if (class_exists('\WizardAi\Modules\Playground\Playground')) {
        \WizardAi\Modules\Playground\Playground::instance()->disable_safe_mode();
    }
}