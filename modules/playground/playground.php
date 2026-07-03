<?php
namespace WizardAi\Modules\Playground;

class Playground {
    use Traits\Init;
    use Traits\Ui;
    use Traits\History;
    use Traits\Context;
    use Traits\Chat;
    use Traits\SafeMode;

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        if (self::$instance !== null) return;
        self::$instance = $this;
        if (method_exists($this, 'register_playground_hooks')) {
            $this->register_playground_hooks();
        }
    }
}
