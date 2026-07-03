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
    }
}
