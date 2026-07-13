<?php
namespace WizardAi\Modules\Playground\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait History {
    private function get_prompts_log_file() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wai/logs/prompts';
        if (!is_dir($log_dir)) wp_mkdir_p($log_dir);
        $date = current_time('Y-m-d');
        return $log_dir . '/' . $date . '.log';
    }

    private function get_last_prompts($limit = 10) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wai/logs/prompts';
        if (!is_dir($log_dir)) return [];
        
        $files = glob($log_dir . '/*.log');
        if (empty($files)) return [];
        
        rsort($files); // Sort descending (latest date first)
        
        $all_lines = [];
        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines) {
                $all_lines = array_merge($all_lines, array_reverse($lines));
                if (count($all_lines) >= $limit) {
                    break;
                }
            }
        }
        return array_slice($all_lines, 0, $limit);
    }

}
