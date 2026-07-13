<?php
namespace WizardAi\Modules\Ai\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait CacheBypass {

    public function register_cache_bypass_hooks() {
        // WP Rocket
        add_filter('rocket_rucss_inline_atts_exclusions', [$this, 'exclude_from_cache']);
        add_filter('rocket_exclude_css', [$this, 'exclude_assets_from_cache']);
        add_filter('rocket_exclude_js', [$this, 'exclude_assets_from_cache']);
        add_filter('rocket_exclude_defer_js', [$this, 'exclude_assets_from_cache']);
        add_filter('rocket_delay_js_exclusions', [$this, 'exclude_from_cache']);
        add_filter('rocket_cache_reject_uri', [$this, 'exclude_api_uri_from_cache']);

        // LiteSpeed Cache
        add_filter('litespeed_optimize_css_excludes', [$this, 'exclude_from_cache']);
        add_filter('litespeed_optm_ccss_exc', [$this, 'exclude_from_cache']);
        add_filter('litespeed_optm_js_defer_exc', [$this, 'exclude_from_cache']);
        add_filter('litespeed_optm_js_exc', [$this, 'exclude_from_cache']);
        add_filter('litespeed_optm_js_delay_exc', [$this, 'exclude_from_cache']);
        add_filter('litespeed_guest_optm_exc', [$this, 'exclude_from_cache']);
        add_filter('litespeed_cache_no_cache_for_request', [$this, 'litespeed_no_cache_api']);

        // Autoptimize
        add_filter('autoptimize_filter_css_exclude', [$this, 'autoptimize_exclude']);
        add_filter('autoptimize_filter_js_exclude', [$this, 'autoptimize_exclude']);

        // SG Optimizer (SiteGround)
        add_filter('sgo_js_minify_exclude', [$this, 'exclude_assets_from_cache']);
        add_filter('sgo_javascript_combine_exclude', [$this, 'exclude_assets_from_cache']);
        add_filter('sgo_js_async_exclude', [$this, 'exclude_assets_from_cache']);

        // W3 Total Cache
        add_filter('w3tc_minify_js_do_tag_minification', [$this, 'w3tc_minify_js'], 10, 3);
        add_filter('w3tc_pgcache_request_skip_uri', [$this, 'w3tc_skip_api']);

        // WP Super Cache
        add_filter('wpsc_rejected_uri', [$this, 'wpsc_exclude_api']);

        // FlyingPress
        add_filter('flying_press_cacheable', [$this, 'flying_press_skip_api']);
    }

    public function exclude_from_cache($exclusions) {
        if (!is_array($exclusions)) $exclusions = [];
        $exclusions[] = 'wizard-ai';
        return $exclusions;
    }

    public function exclude_assets_from_cache($excluded) {
        if (!is_array($excluded)) $excluded = [];
        $excluded[] = 'wizard-ai';
        return $excluded;
    }

    public function exclude_api_uri_from_cache($uris) {
        if (!is_array($uris)) $uris = [];
        $uris[] = '/wp-json/wizard-ai/.*';
        $uris[] = '/wp-admin/admin-ajax\.php\?action=wizard_ai_.*';
        return $uris;
    }

    public function litespeed_no_cache_api($no_cache) {
        if ($no_cache) return $no_cache;
        if (!empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/wizard-ai/') !== false) {
            return true;
        }
        return $no_cache;
    }

    public function autoptimize_exclude($excluded) {
        if (!is_string($excluded)) $excluded = '';
        return $excluded . ', wizard-ai';
    }

    public function w3tc_minify_js($do_minify, $script_tag, $file) {
        if (strpos($file, 'wizard-ai') !== false) {
            return false;
        }
        return $do_minify;
    }

    public function w3tc_skip_api($skip) {
        if ($skip) return $skip;
        if (!empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/wizard-ai/') !== false) {
            return true;
        }
        return $skip;
    }

    public function wpsc_exclude_api($rejected) {
        if (!is_array($rejected)) $rejected = [];
        $rejected[] = 'wp-json/wizard-ai/';
        return $rejected;
    }

    public function flying_press_skip_api($cacheable) {
        if (!$cacheable) return $cacheable;
        if (!empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/wizard-ai/') !== false) {
            return false;
        }
        return $cacheable;
    }
}
