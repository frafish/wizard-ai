<?php
namespace WizardAi\Modules\Markdown\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait LlmsTxt {
    public function register_llmstxt_hooks() {
        if (get_option('wbai_markdown_enabled', '1') !== '1') return;
        if (get_option('wbai_markdown_llmstxt_enabled', '1') !== '1') return;
        
        // Add rewrite rule for llms.txt
        add_action('init', [$this, 'add_llmstxt_rewrite_rule']);
        add_filter('query_vars', [$this, 'add_llmstxt_query_vars']);
        add_action('template_redirect', [$this, 'serve_llmstxt_content'], 1);
    }

    public function add_llmstxt_rewrite_rule() {
        add_rewrite_rule('^llms\.txt$', 'index.php?wai_llmstxt=1', 'top');
    }

    public function add_llmstxt_query_vars($vars) {
        $vars[] = 'wai_llmstxt';
        return $vars;
    }

    public function serve_llmstxt_content() {
        if (get_query_var('wai_llmstxt')) {
            // Clean output buffer to ensure pure text response
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            header('Content-Type: text/plain; charset=utf-8');
            
            echo $this->generate_llmstxt_content();
            exit;
        }
    }

    private function generate_llmstxt_content() {
        $content = "";
        
        // Title and Description
        $site_title = get_bloginfo('name');
        $site_desc = get_bloginfo('description');
        
        $content .= "# " . $site_title . "\n\n";
        if ($site_desc) {
            $content .= "> " . $site_desc . "\n\n";
        }
        
        $allowed_cpts = get_option('wbai_markdown_cpts', false);
        if ($allowed_cpts === false) {
            $allowed_cpts = array_values(array_diff(array_keys(get_post_types(['public' => true])), ['attachment']));
        }
        
        // Output items grouped by post type
        foreach ($allowed_cpts as $post_type) {
            $type_obj = get_post_type_object($post_type);
            if (!$type_obj) continue;
            
            $post_status = ($post_type === 'attachment') ? 'inherit' : 'publish';
            $posts = get_posts([
                'post_type' => $post_type,
                'post_status' => $post_status,
                'posts_per_page' => 100, // Reasonable limit to prevent huge files, or -1
                'orderby' => 'date',
                'order' => 'DESC'
            ]);
            
            if (!empty($posts)) {
                $content .= "## " . $type_obj->labels->name . "\n\n";
                foreach ($posts as $post) {
                    $url = get_permalink($post->ID);
                    // Append .md to URL for Wizard AI Markdown module compatibility
                    $url = rtrim($url, '/') . '.md';
                    
                    $content .= "- [" . esc_html($post->post_title) . "](" . esc_url($url) . ")\n";
                    if (!empty($post->post_excerpt)) {
                        $content .= "  " . esc_html($post->post_excerpt) . "\n";
                    }
                }
                $content .= "\n";
            }
        }
        
        $content .= "[comment]: # (Generated dynamically by Wizard AI)\n";
        
        return $content;
    }
}
