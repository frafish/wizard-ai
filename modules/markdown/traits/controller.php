<?php
namespace WizardAi\Modules\Markdown\Traits;

trait Controller {
    public function inject_markdown_alternate_link() {
        if (!is_singular() || get_query_var('wai_md')) return;
        
        $post = get_queried_object();
        if (!$post instanceof \WP_Post || $post->post_status !== 'publish' || post_password_required($post)) return;

        $allowed_cpts = get_option('wai_markdown_cpts', ['post', 'page']);
        if (!in_array($post->post_type, $allowed_cpts)) return;

        $canonical = get_permalink($post);
        if (!$canonical) return;
        
        $md_url = rtrim($canonical, '/') . '.md';
        printf('<link rel="alternate" type="text/markdown" title="%s" href="%s" />' . "\n", esc_attr__('Markdown version', 'wizard-ai'), esc_url($md_url));
    }

    public function handle_accept_markdown_header() {
        if (get_query_var('wai_md')) return;

        $accept = isset($_SERVER['HTTP_ACCEPT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT'])) : '';
        if (strpos($accept, 'text/markdown') !== false) {
            if (is_singular()) {
                $post = get_queried_object();
                if ($post instanceof \WP_Post && $post->post_status === 'publish') {
                    $allowed_cpts = get_option('wai_markdown_cpts', ['post', 'page']);
                    if (!in_array($post->post_type, $allowed_cpts)) return;

                    $canonical = get_permalink($post);
                    if ($canonical) {
                        $md_url = rtrim($canonical, '/') . '.md';
                        header('Vary: Accept');
                        wp_safe_redirect($md_url, 303);
                        exit;
                    }
                }
            }
        }
    }

    public function serve_markdown_content() {
        if (!get_query_var('wai_md')) return;
        
        // Prevent loopback
        if (isset($_SERVER['HTTP_X_WAI_RENDER'])) {
            status_header(404);
            exit;
        }

        $post = get_queried_object();
        if (!$post instanceof \WP_Post || $post->post_status !== 'publish') {
            status_header(404);
            exit;
        }

        $allowed_cpts = get_option('wai_markdown_cpts', ['post', 'page']);
        if (!in_array($post->post_type, $allowed_cpts)) {
            status_header(404);
            exit;
        }
        
        if (post_password_required($post)) {
            status_header(403);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Content is password protected.';
            exit;
        }
        
        // Detect AI Bots
        $this->detect_and_log_ai_bot($post);

        $canonical = get_permalink($post);
        $markdown = $this->generate_post_markdown($post, $canonical);

        status_header(200);
        header('Content-Type: text/markdown; charset=' . get_bloginfo('charset'));
        header('Vary: Accept');
        header('Link: <' . $canonical . '>; rel="canonical"');
        header('X-Content-Type-Options: nosniff');
        header('X-Markdown-Tokens: ' . (int)(strlen($markdown) / 4));
        
        echo $markdown;
        exit;
    }

}
