<?php
require_once '/var/www/html/wp-load.php';

// We want to use the actual clean_schema from playground.php
// But it is defined as an anonymous function inside a method.
// So we will just test it exactly as written in the file.
$file = file_get_contents('/var/www/html/wp-content/plugins/wizard-ai/modules/ai/traits/playground.php');
if (strpos($file, 'if (empty($v)) {') !== false && strpos($file, '$schema[$k] = new \stdClass();') !== false) {
    echo "Fix is present in playground.php\n";
} else {
    echo "Fix is MISSING in playground.php\n";
}
