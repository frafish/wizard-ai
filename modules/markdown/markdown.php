<?php
namespace WizardAi\Modules\Markdown;

class Markdown {
    use Traits\Init;
    use Traits\Controller;
    use Traits\Generator;
    use Traits\Yoast;
    use Traits\Settings;

    private $is_yoast_generating = false;

    public function __construct() {
        if (method_exists($this, 'register_markdown_hooks')) {
            $this->register_markdown_hooks();
        }
    }
}
