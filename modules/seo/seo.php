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

        // Delay loading the cloned AI plugin until plugins_loaded
        // This ensures any official AI plugin has already been included.
        add_action('plugins_loaded', function() {
            if (!class_exists('WordPress\AI\Main')) {
                require_once __DIR__ . '/wp-ai/ai.php';
            }
        }, 9);
    }
}
