<?php
$content = file_get_contents(dirname(__FILE__) . '/wizard-ai.php');
$tokens = token_get_all($content);
$functions = [];
$hooks = [];
$count = count($tokens);
for ($i=0; $i<$count; $i++) {
    if (is_array($tokens[$i]) && $tokens[$i][0] === T_FUNCTION) {
        // find name
        for ($j=$i+1; $j<$count; $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $functions[] = $tokens[$j][1];
                break;
            }
        }
    }
}
print_r($functions);
