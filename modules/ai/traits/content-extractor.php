<?php
namespace WizardAi\Modules\Ai\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait ContentExtractor {

    public function extract_post_content($post_id) {
        $post = get_post($post_id);
        if (!$post) return '';

        $body = wp_strip_all_tags(strip_shortcodes($post->post_content));
        if (empty(trim($body))) return '';

        $signals = [];
        $signals[] = "Type: " . ucfirst($post->post_type);
        $signals[] = "Title: " . $post->post_title;
        $signals[] = "URL: " . get_permalink($post_id);

        // Taxonomies
        $taxonomies = get_object_taxonomies($post->post_type);
        $tax_strings = [];
        foreach ($taxonomies as $tax) {
            $terms = get_the_terms($post_id, $tax);
            if ($terms && !is_wp_error($terms)) {
                $term_names = wp_list_pluck($terms, 'name');
                $tax_strings[] = ucfirst($tax) . ": " . implode(', ', $term_names);
            }
        }
        if (!empty($tax_strings)) {
            $signals[] = implode(" | ", $tax_strings);
        }

        // WooCommerce Data
        if ($post->post_type === 'product' && function_exists('wc_get_product')) {
            $product = wc_get_product($post_id);
            if ($product) {
                $signals[] = "Price: " . $product->get_price() . " " . get_woocommerce_currency();
                $signals[] = "SKU: " . $product->get_sku();
                $signals[] = "Stock: " . $product->get_stock_status();
                if ($product->get_average_rating() > 0) {
                    $signals[] = "Rating: " . $product->get_average_rating() . " (" . $product->get_review_count() . " reviews)";
                }
            }
        }

        $header = "[" . implode("] [", $signals) . "]";
        return $header . "\n\n" . trim($body);
    }
}
