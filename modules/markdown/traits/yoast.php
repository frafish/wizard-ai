<?php
namespace WizardAi\Modules\Markdown\Traits;

trait Yoast {
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
        $allowed_cpts = get_option('wbai_markdown_cpts', false);
        if ($allowed_cpts === false) {
            $allowed_cpts = array_values(array_diff(array_keys(get_post_types(['public' => true])), ['attachment']));
        }
        if (!in_array($post_type, $allowed_cpts)) return $url;
        return rtrim($url, '/') . '.md';
    }

}
