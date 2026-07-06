<?php
namespace WizardAi\Modules\Ai;

class Abilities {
    use Abilities\Core;
    use Abilities\WordPress;
    use Abilities\WooCommerce;
    use Abilities\Wpml;
    use Abilities\WizardBlocks;

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
        $this->register_core_abilities();
        $this->register_wordpress_abilities();
        $this->register_woocommerce_abilities();
        $this->register_wpml_abilities();
        $this->register_wizard_blocks_abilities();
    }
}
