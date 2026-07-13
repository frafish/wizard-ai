<?php
namespace WizardAi\Modules\Ai\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait AiOutputValidator {

    public function extract_and_validate_json($ai_text) {
        $extracted = trim($ai_text);
        if (preg_match('/```json\s*([\s\S]*?)\s*```/i', $extracted, $matches)) {
            $extracted = $matches[1];
        } elseif (preg_match('/```\s*([\s\S]*?)\s*```/i', $extracted, $matches)) {
            $extracted = $matches[1];
        }
        
        $extracted = preg_replace('/^```json\s*|```$/i', '', trim($extracted));
        $extracted = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $extracted);
        
        $decoded = json_decode($extracted, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('invalid_json', json_last_error_msg());
        }
        
        return $decoded;
    }

    public function validate_php_syntax($code) {
        if (empty(trim($code))) return true;
        
        // Remove open/close tags if present just for tokenization check
        $clean_code = preg_replace('/^<\?php|<\?|\?>$/i', '', trim($code));
        
        try {
            // token_get_all can sometimes throw ParseError in PHP 8 if syntax is entirely invalid (e.g. unclosed comments or invalid chars)
            $tokens = token_get_all("<?php\n" . $clean_code);
            
            // We can also do a basic bracket balancing check
            $brackets = 0;
            $parentheses = 0;
            foreach ($tokens as $token) {
                if (is_string($token)) {
                    if ($token === '{') $brackets++;
                    if ($token === '}') $brackets--;
                    if ($token === '(') $parentheses++;
                    if ($token === ')') $parentheses--;
                }
            }
            if ($brackets !== 0) {
                return new \WP_Error('php_syntax_error', 'Unbalanced curly brackets {} in PHP code. Please fix.');
            }
            if ($parentheses !== 0) {
                return new \WP_Error('php_syntax_error', 'Unbalanced parentheses () in PHP code. Please fix.');
            }
            
            return true;
        } catch (\Throwable $e) {
            return new \WP_Error('php_syntax_error', $e->getMessage());
        }
    }
}
