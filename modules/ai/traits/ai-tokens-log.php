<?php
namespace WizardAi\Modules\Ai\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI
 *
 * @package     ai
 * @author      WordPress.org Contributors
 * @copyright   2025 Plugin Contributors
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       AI
 * Plugin URI:        https://github.com/WordPress/ai
 * Description:       AI features, experiments and capabilities for WordPress.
 * Version:           1.1.0
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Author:            WordPress.org Contributors
 * Author URI:        https://make.wordpress.org/ai/
 * License:           GPL-2.0-or-later
 * License URI:       https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain:       ai
 */

use WizardAi\Modules\Ai\Classes\TokensLog\AI_Request_Log_Manager;
use WizardAi\Modules\Ai\Classes\TokensLog\AI_Request_Log_Page;
use WizardAi\Modules\Ai\Classes\TokensLog\REST\AI_Request_Log_Controller;
use WizardAi\Modules\Ai\Classes\TokensLog\Logging_Integration;

trait AiTokensLog {

    public function register_ai_tokens_log_hooks() {
        require_once WIZARD_AI_PATH . 'modules/ai/classes/tokens-log/AI_Request_Log_Schema.php';
        require_once WIZARD_AI_PATH . 'modules/ai/classes/tokens-log/AI_Request_Log_Repository.php';
        require_once WIZARD_AI_PATH . 'modules/ai/classes/tokens-log/AI_Request_Log_Manager.php';
        require_once WIZARD_AI_PATH . 'modules/ai/classes/tokens-log/Log_Data_Extractor.php';
        require_once WIZARD_AI_PATH . 'modules/ai/classes/tokens-log/Logging_Http_Transporter.php';
        require_once WIZARD_AI_PATH . 'modules/ai/classes/tokens-log/Logging_Integration.php';
        require_once WIZARD_AI_PATH . 'modules/ai/classes/tokens-log/AI_Request_Log_Page.php';
        require_once WIZARD_AI_PATH . 'modules/ai/classes/tokens-log/REST/AI_Request_Log_Controller.php';

        $manager = new AI_Request_Log_Manager();
        $manager->init();
        Logging_Integration::init($manager);

        $controller = new AI_Request_Log_Controller($manager);
        $page       = new AI_Request_Log_Page($manager);

        add_action('rest_api_init', [$controller, 'register_routes']);
        add_action('admin_menu', [$page, 'register_menu'], 999);
    }
}
