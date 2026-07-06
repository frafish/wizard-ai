<?php
namespace WizardAi\Modules\Markdown\Traits;

trait Init {
    public function register_markdown_hooks() {
        add_action('admin_menu', [$this, 'add_markdown_menu']);
        if (get_option('wbai_markdown_enabled', '1') !== '1') return;

        add_action('init', [$this, 'add_markdown_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_markdown_query_vars']);
        add_action('parse_request', [$this, 'parse_markdown_url_nginx']);
        add_filter('redirect_canonical', [$this, 'prevent_markdown_redirect'], 10, 2);
        
        add_action('wp_head', [$this, 'inject_markdown_alternate_link'], 1);
        add_action('template_redirect', [$this, 'handle_accept_markdown_header'], 1);
        add_action('template_redirect', [$this, 'serve_markdown_content'], 2);

        // Yoast llms.txt integration
        foreach (['update_option_wpseo', 'update_option_wpseo_llmstxt', 'wpseo_llms_txt_population'] as $action) {
            add_action($action, [$this, 'start_yoast_llms_rewrite'], 9);
            add_action($action, [$this, 'stop_yoast_llms_rewrite'], 11);
        }
    }

    public function add_markdown_rewrite_rules() {
        add_rewrite_rule('^index\.md$', 'index.php?pagename=index&wai_md=1', 'top');
        add_rewrite_rule('(.+?)\.md$', 'index.php?pagename=$matches[1]&wai_md=1', 'top');
    }

    public function add_markdown_query_vars($vars) {
        $vars[] = 'wai_md';
        return $vars;
    }

    public function prevent_markdown_redirect($redirect_url, $requested_url) {
        if (get_query_var('wai_md')) {
            return false;
        }
        return $redirect_url;
    }

    public function parse_markdown_url_nginx(\WP $wp) {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $path = wp_parse_url($request_uri, PHP_URL_PATH);
        if (!$path) return;

        if (preg_match('/^\/(.+)\.md$/i', $path, $matches)) {
            $path_without_md = $matches[1];
            
            if ($path_without_md === 'index') {
                $post_id = url_to_postid(home_url('/index'));
                if (!$post_id) {
                    $page_on_front = get_option('page_on_front');
                    if ($page_on_front) $post_id = (int)$page_on_front;
                }
            } else {
                $post_id = url_to_postid(home_url('/' . $path_without_md));
                if (!$post_id) $post_id = url_to_postid(home_url('/' . $path_without_md . '/'));
            }

            if ($post_id) {
                $post = get_post($post_id);
                if ($post && ($post->post_status === 'publish' || ($post->post_type === 'attachment' && $post->post_status === 'inherit'))) {
                    $allowed_cpts = get_option('wbai_markdown_cpts', false);
                    if ($allowed_cpts === false) {
                        $allowed_cpts = array_values(array_diff(array_keys(get_post_types(['public' => true])), ['attachment']));
                    }
                    if (in_array($post->post_type, $allowed_cpts)) {
                        if ($post->post_type === 'page') {
                            $wp->query_vars['page_id'] = $post->ID;
                        } else {
                            $wp->query_vars['p'] = $post->ID;
                            $wp->query_vars['post_type'] = $post->post_type;
                        }
                        $wp->query_vars['wai_md'] = '1';
                        unset($wp->query_vars['pagename']);
                        unset($wp->query_vars['name']);
                    }
                }
            }
        }
    }
    
    public function add_markdown_menu() {
        add_submenu_page(
            'wizard-ai',
            __('Markdown', 'wizard-ai'),
            __('Markdown', 'wizard-ai'),
            'manage_options',
            'wizard-ai-markdown',
            [$this, 'wb_ai_markdown_page_html']
        );
    }
}
