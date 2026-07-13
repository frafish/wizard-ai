<?php
namespace WizardAi\Modules\Editor;
if ( ! defined( 'ABSPATH' ) ) exit;
class Editor {
    use Traits\Init;
    use Traits\Ui;
    use Traits\Settings;
    use Traits\InlinePrompt;

    public function __construct() {
        if (method_exists($this, 'register_agent_hooks')) {
            $this->register_agent_hooks();
        }
        if (method_exists($this, 'register_inline_prompt_hooks')) {
            $this->register_inline_prompt_hooks();
        }
    }
}
