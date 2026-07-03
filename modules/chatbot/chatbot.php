<?php
namespace WizardAi\Modules\Chatbot;

class Chatbot {
    use Traits\Init;
    use Traits\Ui;
    use Traits\Chat;
    use Traits\Settings;
    use Traits\Logs;

    public function __construct() {
        if (method_exists($this, 'register_chatbot_hooks')) {
            $this->register_chatbot_hooks();
        }
    }
}
