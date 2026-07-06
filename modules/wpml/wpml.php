<?php
namespace WizardAi\Modules\Wpml;

class Wpml {
    use traits\Contents;
    use traits\Strings;
    use traits\Settings;
    use traits\Elementor;

    public function __construct() {
        if (!class_exists('SitePress')) return;
        
        add_action('admin_footer', [$this, 'inject_wpml_ai_buttons']);
        add_action('rest_api_init', [$this, 'register_wpml_translate_route']);
        add_action('admin_menu', [$this, 'add_wpml_ai_menu'], 5);

        // Hooks for auto-updating translations
        add_action('post_updated', [$this, 'schedule_translation_updates'], 10, 3);
        add_action('saved_term', [$this, 'schedule_term_translation_updates'], 10, 3);
        
        // Cron handlers
        add_action('wai_wpml_update_translations', [$this, 'process_translation_updates'], 10, 1);
        add_action('wai_wpml_update_term_translations', [$this, 'process_term_translation_updates'], 10, 2);
        
        // WPML Native Translation Management Interceptor
        add_action('init', [$this, 'register_wizard_ai_translator_user']);
        add_action('wpml_added_local_translation_job', [$this, 'intercept_wpml_tm_job'], 10, 1);
        add_action('wai_process_wpml_tm_job', [$this, 'process_wpml_tm_job'], 10, 1);
    }

    public function register_wpml_translate_route() {
        register_rest_route('wizard-ai/v1', '/wpml-translate', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_wpml_translate'],
            'permission_callback' => function () { return current_user_can('edit_posts'); }
        ]);
        register_rest_route('wizard-ai/v1', '/wpml-get-missing', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_wpml_get_missing'],
            'permission_callback' => function () { return current_user_can('edit_posts'); }
        ]);
        register_rest_route('wizard-ai/v1', '/wpml-strings-get-missing', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_wpml_strings_get_missing'],
            'permission_callback' => function () { return current_user_can('edit_posts'); }
        ]);
        register_rest_route('wizard-ai/v1', '/wpml-strings-translate', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_wpml_strings_translate'],
            'permission_callback' => function () { return current_user_can('edit_posts'); }
        ]);
    }

    public function add_wpml_ai_menu() {
        // Register using WPML's native custom menu API
        do_action('wpml_admin_menu_register_item', [
            'order' => 800,
            'page_title' => __('AI Translator', 'wizard-ai'),
            'menu_title' => __('AI Translator', 'wizard-ai'),
            'capability' => 'manage_options',
            'menu_slug' => 'wai-wpml-bulk',
            'function' => [$this, 'render_wpml_bulk_page']
        ]);
    }

    public function render_wpml_bulk_page() {
        if (isset($_POST['wai_wpml_settings_nonce'])) {
            $this->handle_settings_post();
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'translate';
        $has_strings = method_exists($this, 'wai_wpml_has_strings') && $this->wai_wpml_has_strings();
        
        wp_enqueue_style('wpml-dashboard', plugins_url('sitepress-multilingual-cms/vendor/wpml/wpml/public/css/dashboard.css'), [], null);
        ?>
        <div class="wrap" id="wpml-dashboard">
            <h1 style="margin-bottom: 16px;"><?php esc_html_e('Wizard AI Translation Dashboard', 'wizard-ai'); ?></h1>
            
            <h2 class="nav-tab-wrapper icl-translation-management-menu">
                <a href="?page=wai-wpml-bulk&tab=translate" class="nav-tab <?php echo $active_tab === 'translate' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Content Translation', 'wizard-ai'); ?>
                </a>
                <?php if ($has_strings): ?>
                <a href="?page=wai-wpml-bulk&tab=strings" class="nav-tab <?php echo $active_tab === 'strings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('String Translation', 'wizard-ai'); ?>
                </a>
                <?php endif; ?>
                <a href="?page=wai-wpml-bulk&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('AI Models & Settings', 'wizard-ai'); ?>
                </a>
            </h2>

            <?php 
            if ($active_tab === 'translate' && method_exists($this, 'render_content_tab')) {
                $this->render_content_tab();
            } elseif ($active_tab === 'strings' && method_exists($this, 'render_strings_tab')) {
                $this->render_strings_tab();
            } elseif ($active_tab === 'settings' && method_exists($this, 'render_settings_tab')) {
                $this->render_settings_tab();
            }
            ?>
        </div>
        <?php
    }
}
