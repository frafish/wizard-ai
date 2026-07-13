<?php
namespace WizardAi\Modules\Ai;
if ( ! defined( 'ABSPATH' ) ) exit;
class Abilities {

    public function __construct() {
        if (function_exists('wp_register_ability_category')) {
            add_action('wp_abilities_api_categories_init', [$this, 'register_abilities_categories']);
            add_action('wp_abilities_api_init', [$this, 'register_abilities']);
        }
    }

    public function register_abilities_categories() {
        if (!wp_has_ability_category('wizard-ai')) {
            wp_register_ability_category('wizard-ai', [
                'label' => __('Wizard AI', 'wizard-ai'),
                'description' => __('Wizard AI Abilities', 'wizard-ai')
            ]);
        }
        if (!wp_has_ability_category('wizard-blocks')) {
            wp_register_ability_category('wizard-blocks', [
                'label' => __('Wizard Blocks', 'wizard-ai'),
                'description' => __('Wizard Blocks Abilities', 'wizard-ai')
            ]);
        }
        if (!wp_has_ability_category('wpml')) {
            wp_register_ability_category('wpml', [
                'label' => __('WPML', 'wizard-ai'),
                'description' => __('WPML Abilities', 'wizard-ai')
            ]);
        }
        if (!wp_has_ability_category('woocommerce')) {
            wp_register_ability_category('woocommerce', [
                'label' => __('WooCommerce', 'wizard-ai'),
                'description' => __('WooCommerce Abilities', 'wizard-ai')
            ]);
        }
    }

    public function register_abilities() {
        $registrar = new class {
            use Abilities\Core;
            use Abilities\WordPress;
            use Abilities\WooCommerce;
            use Abilities\Wpml;
            use Abilities\WizardBlocks;
        };

        $registrar->register_core_abilities();
        $registrar->register_wordpress_abilities();
        $registrar->register_woocommerce_abilities();
        $registrar->register_wpml_abilities();
        $registrar->register_wizard_blocks_abilities();
    }
}
