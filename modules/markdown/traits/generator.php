<?php
namespace WizardAi\Modules\Markdown\Traits;

trait Generator {
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

}
