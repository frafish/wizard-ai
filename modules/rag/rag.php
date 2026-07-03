<?php
namespace WizardAi\Modules\Rag;

class Rag {
    public function __construct() {
        require_once dirname(__FILE__) . '/cron/init.php';
    }
}
