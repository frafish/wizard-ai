<?php
require 'wp-load.php';

$plugin_dir = 'woocommerce';
$plugin_full_dir = WP_PLUGIN_DIR . '/' . $plugin_dir;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_full_dir, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        if (!$content) continue;
        
        preg_match_all('/(?:do_action|apply_filters)\s*\(\s*[\'"]([a-zA-Z0-9_.-]+)[\'"]/', $content, $matches);
        preg_match_all('/(?:^|\s)class\s+([a-zA-Z0-9_]+)/', $content, $matches);
        preg_match_all('/(?:^|\s)function\s+([a-zA-Z0-9_]+)\s*\(([^)]*)\)/', $content, $matches);
    }
}
echo "Done WooCommerce parsing.\n";
