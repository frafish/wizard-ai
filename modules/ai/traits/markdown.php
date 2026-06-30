<?php
namespace WizardAi\Modules\Ai\Traits;

trait Markdown {
    
    private $is_yoast_generating = false;

    public function register_markdown_hooks() {
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
        add_rewrite_rule('^index\.md$', 'index.php?pagename=index&wbai_md=1', 'top');
        add_rewrite_rule('(.+?)\.md$', 'index.php?pagename=$matches[1]&wbai_md=1', 'top');
    }

    public function add_markdown_query_vars($vars) {
        $vars[] = 'wbai_md';
        return $vars;
    }

    public function prevent_markdown_redirect($redirect_url, $requested_url) {
        if (get_query_var('wbai_md')) {
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
                if ($post && $post->post_status === 'publish') {
                    $allowed_cpts = get_option('wbai_markdown_cpts', ['post', 'page']);
                    if (in_array($post->post_type, $allowed_cpts)) {
                        $wp->query_vars['p'] = $post->ID;
                        $wp->query_vars['wbai_md'] = '1';
                        unset($wp->query_vars['pagename']);
                    }
                }
            }
        }
    }

    public function inject_markdown_alternate_link() {
        if (!is_singular() || get_query_var('wbai_md')) return;
        
        $post = get_queried_object();
        if (!$post instanceof \WP_Post || $post->post_status !== 'publish' || post_password_required($post)) return;

        $allowed_cpts = get_option('wbai_markdown_cpts', ['post', 'page']);
        if (!in_array($post->post_type, $allowed_cpts)) return;

        $canonical = get_permalink($post);
        if (!$canonical) return;
        
        $md_url = rtrim($canonical, '/') . '.md';
        printf('<link rel="alternate" type="text/markdown" title="%s" href="%s" />' . "\n", esc_attr__('Markdown version', 'wizard-ai'), esc_url($md_url));
    }

    public function handle_accept_markdown_header() {
        if (get_query_var('wbai_md')) return;

        $accept = isset($_SERVER['HTTP_ACCEPT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT'])) : '';
        if (strpos($accept, 'text/markdown') !== false) {
            if (is_singular()) {
                $post = get_queried_object();
                if ($post instanceof \WP_Post && $post->post_status === 'publish') {
                    $allowed_cpts = get_option('wbai_markdown_cpts', ['post', 'page']);
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
        if (!get_query_var('wbai_md')) return;
        
        // Prevent loopback
        if (isset($_SERVER['HTTP_X_WBAI_RENDER'])) {
            status_header(404);
            exit;
        }

        $post = get_queried_object();
        if (!$post instanceof \WP_Post || $post->post_status !== 'publish') {
            status_header(404);
            exit;
        }

        $allowed_cpts = get_option('wbai_markdown_cpts', ['post', 'page']);
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

    private function detect_and_log_ai_bot(\WP_Post $post) {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))) : '';
        if (!$ua) return;
        
        $bots = [
            'chatgpt-user' => 'ChatGPT',
            'oai-searchbot' => 'OpenAI SearchBot',
            'gptbot' => 'GPTBot',
            'claudebot' => 'ClaudeBot',
            'claude-web' => 'Claude Web',
            'perplexitybot' => 'PerplexityBot',
            'google-extended' => 'Google Gemini',
            'apis-google' => 'Google API',
            'bytespider' => 'ByteDance/TikTok',
            'anthropic' => 'Anthropic'
        ];
        
        foreach ($bots as $sig => $name) {
            if (strpos($ua, $sig) !== false) {
                // Log bot hit using action hook (can be hooked elsewhere to save to db)
                do_action('wizard_ai_markdown_bot_hit', $name, $post->ID, $ua);
                break;
            }
        }
    }

    private function generate_post_markdown(\WP_Post $post_obj, $canonical_url) {
        // Fetch fully rendered HTML using output buffering to avoid loopback network requests
        global $wp_query, $wp_the_query, $post;
        $original_post = $post;
        $original_wp_query = $wp_query;
        $original_wp_the_query = $wp_the_query;

        $html = '';
        $query = new \WP_Query([
            'p' => $post_obj->ID,
            'post_type' => $post_obj->post_type,
            'posts_per_page' => 1
        ]);

        if ($query->have_posts()) {
            $query->the_post();
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            $wp_query = $query;
            $wp_the_query = $query;
            $post = $query->post;
            
            ob_start();
            try {
                $template = '';
                if ($post_obj->ID == get_option('page_on_front') && function_exists('get_front_page_template')) $template = get_front_page_template();
                if (empty($template) && $post_obj->post_type === 'page' && function_exists('get_page_template')) $template = get_page_template();
                if (empty($template) && function_exists('get_single_template')) $template = get_single_template();
                if (empty($template) && function_exists('get_singular_template')) $template = get_singular_template();
                if (empty($template) && function_exists('get_index_template')) $template = get_index_template();
                
                if (!empty($template) && file_exists($template)) {
                    include $template;
                } else {
                    echo apply_filters('the_content', $post_obj->post_content);
                }
            } catch (\Throwable $e) {
                // Ignore render errors
            }
            $html = ob_get_clean();
            wp_reset_postdata();
        } else {
            $html = apply_filters('the_content', $post_obj->post_content);
        }
        
        // Restore globals
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        $post = $original_post;
        $wp_query = $original_wp_query;
        $wp_the_query = $original_wp_the_query;
        
        if (empty($html)) {
            $html = apply_filters('the_content', $post_obj->post_content);
        }
        
        $md_body = $this->extract_markdown_from_html($html);
        
        $author = get_the_author_meta('display_name', $post_obj->post_author);
        $modified = get_the_modified_date('Y-m-d', $post_obj);
        
        $frontmatter = "---\n";
        $frontmatter .= "title: \"" . str_replace('"', '\"', $post_obj->post_title) . "\"\n";
        $frontmatter .= "url: " . esc_url_raw($canonical_url) . "\n";
        $frontmatter .= "date_modified: " . $modified . "\n";
        $frontmatter .= "author: \"" . str_replace('"', '\"', $author) . "\"\n";
        
        // Extract Yoast Description if available
        $desc = get_post_meta($post_obj->ID, '_yoast_wpseo_metadesc', true);
        if ($desc) {
            $frontmatter .= "description: \"" . str_replace('"', '\"', $desc) . "\"\n";
        }
        
        $frontmatter .= "---\n\n";

        return $frontmatter . $md_body;
    }

    private function extract_markdown_from_html($html) {
        if (!class_exists('DOMDocument') || empty(trim($html))) {
            return wp_strip_all_tags($html);
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new \DOMXPath($dom);
        $body = $xpath->query('//body')->item(0);
        if (!$body) return wp_strip_all_tags($html);
        
        // Find candidate containers
        $candidates = [];
        $nodes = $xpath->query('//main | //article | //div | //section');
        foreach ($nodes as $node) {
            if ($node instanceof \DOMElement) {
                $score = $this->score_dom_node($node, $xpath);
                if ($score > 0) {
                    $candidates[] = ['node' => $node, 'score' => $score];
                }
            }
        }

        $root = $body;
        if (!empty($candidates)) {
            usort($candidates, function($a, $b) { return $b['score'] <=> $a['score']; });
            $root = $candidates[0]['node'];
        }

        // Remove chrome
        $remove = ['//script', '//style', '//noscript', '//header', '//footer', '//nav', '//aside', '//comment()', '//a[starts-with(@href, "#")]'];
        $remove[] = '//*[@role="navigation"]';
        $remove[] = '//*[@role="complementary"]';
        $remove[] = '//*[@role="banner"]';
        $remove[] = '//*[@role="contentinfo"]';
        
        foreach ($remove as $query) {
            $nodes = $xpath->query($query, $root);
            if ($nodes) {
                foreach ($nodes as $node) {
                    if ($node->parentNode) $node->parentNode->removeChild($node);
                }
            }
        }

        // Basic HTML to Markdown conversion
        $markdown = '';
        foreach ($root->childNodes as $child) {
            $markdown .= $this->node_to_markdown($child);
        }

        $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown);
        return trim($markdown);
    }

    private function score_dom_node(\DOMElement $node, \DOMXPath $xpath) {
        $text = trim(preg_replace('/\s+/', ' ', $node->textContent ?? ''));
        if (strlen($text) < 100) return -100;
        
        $score = min(4000, strlen($text));
        
        $score += $xpath->evaluate('count(.//p)', $node) * 100;
        $score += $xpath->evaluate('count(.//h1 | .//h2 | .//h3)', $node) * 150;
        $score += $xpath->evaluate('count(.//pre | .//code)', $node) * 150;
        
        $score -= $xpath->evaluate('count(.//form | .//input | .//button)', $node) * 200;
        
        $links_text_len = 0;
        $links = $xpath->query('.//a', $node);
        if ($links) {
            foreach ($links as $link) {
                $links_text_len += strlen($link->textContent ?? '');
            }
        }
        $link_density = $links_text_len / max(1, strlen($text));
        $score -= $link_density * 2000;
        
        $tag = strtolower($node->nodeName);
        if ($tag === 'article') $score += 500;
        if ($tag === 'main') $score += 500;
        
        $class = strtolower($node->getAttribute('class'));
        if (strpos($class, 'content') !== false) $score += 300;
        if (strpos($class, 'entry') !== false) $score += 300;
        if (strpos($class, 'post') !== false) $score += 300;
        
        return (int)$score;
    }

    private function node_to_markdown(\DOMNode $node) {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = preg_replace('/\s+/', ' ', $node->nodeValue);
            return trim($text) === '' ? '' : $text;
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) return '';

        $tag = strtolower($node->nodeName);
        $children = '';
        foreach ($node->childNodes as $child) {
            $children .= $this->node_to_markdown($child);
        }

        switch ($tag) {
            case 'h1': return "\n# " . trim($children) . "\n\n";
            case 'h2': return "\n## " . trim($children) . "\n\n";
            case 'h3': return "\n### " . trim($children) . "\n\n";
            case 'h4': return "\n#### " . trim($children) . "\n\n";
            case 'h5': return "\n##### " . trim($children) . "\n\n";
            case 'h6': return "\n###### " . trim($children) . "\n\n";
            case 'p':
            case 'div':
            case 'section':
            case 'article': return trim($children) . "\n\n";
            case 'br': return "  \n";
            case 'strong':
            case 'b': return '**' . trim($children) . '**';
            case 'em':
            case 'i': return '*' . trim($children) . '*';
            case 'code': 
                if ($node->parentNode && strtolower($node->parentNode->nodeName) === 'pre') return trim($children);
                return '`' . trim($children) . '`';
            case 'pre': return "```\n" . trim($children) . "\n```\n\n";
            case 'a':
                $href = $node->getAttribute('href');
                return $href ? '[' . trim($children) . '](' . $href . ')' : trim($children);
            case 'img':
                $src = $node->getAttribute('src') ?: $node->getAttribute('data-src');
                $alt = $node->getAttribute('alt');
                return $src ? '![' . $alt . '](' . $src . ')' : '';
            case 'ul':
            case 'ol':
                $list = '';
                $index = 1;
                foreach ($node->childNodes as $li) {
                    if ($li->nodeType === XML_ELEMENT_NODE && strtolower($li->nodeName) === 'li') {
                        $prefix = $tag === 'ol' ? $index . '. ' : '- ';
                        $li_content = '';
                        foreach ($li->childNodes as $c) $li_content .= $this->node_to_markdown($c);
                        $list .= $prefix . trim(preg_replace('/\s+/', ' ', $li_content)) . "\n";
                        $index++;
                    }
                }
                return "\n" . $list . "\n";
            case 'blockquote':
                $lines = explode("\n", trim($children));
                return "\n" . implode("\n", array_map(function($l) { return '> ' . $l; }, $lines)) . "\n\n";
            default:
                return $children;
        }
    }

    public function start_yoast_llms_rewrite() {
        if ($this->is_yoast_generating) return;
        $this->is_yoast_generating = true;
        add_filter('wpseo_canonical', [$this, 'rewrite_yoast_canonical'], 10, 2);
        add_filter('post_link', [$this, 'rewrite_post_link'], 10, 2);
        add_filter('page_link', [$this, 'rewrite_post_link'], 10, 2);
        add_filter('post_type_link', [$this, 'rewrite_post_link'], 10, 2);
    }

    public function stop_yoast_llms_rewrite() {
        if (!$this->is_yoast_generating) return;
        $this->is_yoast_generating = false;
        remove_filter('wpseo_canonical', [$this, 'rewrite_yoast_canonical'], 10);
        remove_filter('post_link', [$this, 'rewrite_post_link'], 10);
        remove_filter('page_link', [$this, 'rewrite_post_link'], 10);
        remove_filter('post_type_link', [$this, 'rewrite_post_link'], 10);
    }

    public function rewrite_yoast_canonical($canonical, $presentation) {
        if (empty($canonical) || !is_object($presentation) || !isset($presentation->model)) return $canonical;
        if ($presentation->model->object_type !== 'post') return $canonical;
        return rtrim($canonical, '/') . '.md';
    }

    public function rewrite_post_link($url, $post) {
        $post_type = get_post_type($post);
        if (!$post_type) return $url;
        $allowed_cpts = get_option('wbai_markdown_cpts', ['post', 'page']);
        if (!in_array($post_type, $allowed_cpts)) return $url;
        return rtrim($url, '/') . '.md';
    }

    public function wb_ai_markdown_page_html() {
        if (!current_user_can('manage_options')) return;

        if (isset($_POST['wbai_markdown_save'])) {
            check_admin_referer('wbai_markdown_nonce');
            $enabled = isset($_POST['wbai_markdown_enabled']) ? '1' : '0';
            $cpts = isset($_POST['wbai_markdown_cpts']) && is_array($_POST['wbai_markdown_cpts']) ? array_map('sanitize_text_field', $_POST['wbai_markdown_cpts']) : [];
            update_option('wbai_markdown_enabled', $enabled);
            update_option('wbai_markdown_cpts', $cpts);
            
            // Flush rewrite rules to ensure .md routes are updated
            flush_rewrite_rules();
            
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'wizard-ai') . '</p></div>';
        }

        $enabled = get_option('wbai_markdown_enabled', '1');
        $selected_cpts = get_option('wbai_markdown_cpts', ['post', 'page']);
        
        $post_types = get_post_types(['public' => true], 'objects');

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Markdown Settings', 'wizard-ai'); ?></h1>
            <p><?php esc_html_e('Configure how Wizard AI exposes your site content as Markdown for AI crawlers.', 'wizard-ai'); ?></p>
            <form method="post" action="">
                <?php wp_nonce_field('wbai_markdown_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Markdown', 'wizard-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wbai_markdown_enabled" value="1" <?php checked('1', $enabled); ?>>
                                <?php esc_html_e('Enable Markdown conversion and .md endpoints', 'wizard-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enabled Post Types', 'wizard-ai'); ?></th>
                        <td>
                            <fieldset>
                                <?php foreach ($post_types as $pt): ?>
                                    <label style="display:block; margin-bottom: 5px;">
                                        <input type="checkbox" name="wbai_markdown_cpts[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $selected_cpts)); ?>>
                                        <?php echo esc_html($pt->labels->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description"><?php esc_html_e('Select which post types should be exposed as Markdown.', 'wizard-ai'); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="wbai_markdown_save" id="submit" class="button button-primary" value="<?php esc_attr_e('Save Changes', 'wizard-ai'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
}
