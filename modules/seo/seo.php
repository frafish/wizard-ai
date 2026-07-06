<?php
namespace WizardAi\Modules\Seo;

class Seo {
    use Traits\Init;
    use Traits\Hooks;
    use Traits\Api;
    use Traits\Settings;

    public function __construct() {
        if (method_exists($this, 'register_seo_hooks')) {
            $this->register_seo_hooks();
        }

        // We are already inside plugins_loaded (from wizard-ai.php)
        if (!class_exists('WordPress\AI\Main')) {
            require_once __DIR__ . '/wp-ai/ai.php';
        }

        add_filter('pre_option_wpai_features_enabled', function($default) { return '1'; });
        add_filter('wpai_feature_ai-request-logging_enabled', '__return_true');
    }
}
