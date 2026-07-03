<?php
namespace WizardAi\Modules\Seo\Traits;

trait Init {
    public function register_seo_hooks() {
        add_action('admin_menu', [$this, 'add_seo_menu']);
        // Register the old render block logic for fallback alt text
        add_filter('render_block', [$this, 'custom_gutenberg_image_alt'], 10, 2);

        // Add button to Media Library attachment edit fields
        add_filter('attachment_fields_to_edit', [$this, 'add_seo_media_button'], 10, 2);

        // Register REST API endpoints
        add_action('rest_api_init', function () {
            register_rest_route('wizard-blocks/v1', '/optimize-media-seo', [
                'methods' => 'POST',
                'callback' => [$this, 'api_optimize_media_seo'],
                'permission_callback' => function () { return current_user_can('edit_posts'); }
            ]);
            register_rest_route('wizard-blocks/v1', '/save-seo-settings', [
                'methods' => 'POST',
                'callback' => [$this, 'api_save_seo_settings'],
                'permission_callback' => function () { return current_user_can('manage_options'); }
            ]);
        });

        // Cron hooks
        add_action('wai_cron_optimize_media', [$this, 'cron_optimize_media']);
        
        // Auto schedule on attachment addition if enabled
        add_action('add_attachment', [$this, 'on_add_attachment']);
    }
    
    public function add_seo_menu() {
        add_submenu_page(
            'wizard-ai',
            __('Media SEO', 'wizard-ai'),
            __('Media SEO', 'wizard-ai'),
            'manage_options',
            'wizard-ai-seo',
            [$this, 'wb_ai_seo_page_html']
        );
    }
}
