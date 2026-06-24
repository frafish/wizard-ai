<?php
namespace WizardAi\Modules\Ai;

class Abilities {
    use Traits\Abilities\Core;
    use Traits\Abilities\WordPress;
    use Traits\Abilities\WooCommerce;
    use Traits\Abilities\Wpml;
    use Traits\Abilities\WizardBlocks;

    public function __construct() {
        if (function_exists('wp_register_ability_category')) {
            add_action('wp_abilities_api_categories_init', [$this, 'register_abilities_categories']);
            add_action('wp_abilities_api_init', [$this, 'register_abilities']);
        }
    }

    public function register_abilities_categories() {
        wp_register_ability_category('wizard-blocks', [
            'label' => __('Wizard Blocks', 'wizard-ai'),
            'description' => __('Wizard Blocks AI Abilities', 'wizard-ai')
        ]);
    }

    public function register_abilities() {
        $this->register_core_abilities();
        $this->register_wordpress_abilities();
        $this->register_woocommerce_abilities();
        $this->register_wpml_abilities();
        $this->register_wizard_blocks_abilities();
    }
}
