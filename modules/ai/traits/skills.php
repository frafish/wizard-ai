<?php
namespace WizardAi\Modules\Ai\Traits;

trait Skills {

    public function register_skills_hooks() {
        add_action('rest_api_init', function() {
            register_rest_route('wizard-blocks/v1', '/skills', [
                'methods' => 'GET',
                'callback' => [$this, 'api_get_skills'],
                'permission_callback' => function () { return current_user_can('manage_options'); }
            ]);
            register_rest_route('wizard-blocks/v1', '/skills', [
                'methods' => 'POST',
                'callback' => [$this, 'api_save_skill'],
                'permission_callback' => function () { return current_user_can('manage_options'); }
            ]);
            register_rest_route('wizard-blocks/v1', '/skills', [
                'methods' => 'DELETE',
                'callback' => [$this, 'api_delete_skill'],
                'permission_callback' => function () { return current_user_can('manage_options'); }
            ]);
        });
    }

    private function get_builtin_skills() {
        return [
            [
                'id' => 'builtin_how_to_create_blocks.md',
                'is_builtin' => true,
                'content' => "When creating a new Gutenberg block, ensure full compatibility with WordPress standards (via block.json) and WizardBlocks.\nSave the block files in `/wp-content/uploads/blocks/{domain}/{name}`.\n\nBlock files description:\n- `render.php`: Used for frontend PHP output logic.\n- `style.css`: Used for frontend and editor styles.\n- `editorScript.js`: Used for custom Javascript execution inside the WordPress Editor.\n- `script.js`: Used for frontend Javascript logic.\n\nIf the `{domain}/{name}` path is already taken, verify the existing block. If it provides the same feature, improve the existing code. If it's just a namesake for a completely different feature, generate a new unique name.\n\nIf the block requires no attributes, you MUST use the `auto_register` supports parameter in your `block.json`."
            ],
            [
                'id' => 'builtin_wordpress_coding_standards.md',
                'is_builtin' => true,
                'content' => "Follow WordPress PHP Coding Standards. Always prefix custom functions with the plugin slug. Use `wp_safe_redirect` instead of `wp_redirect` where applicable. Sanitize all inputs and escape all outputs. On common user requests for custom snippets or functionality, you should insert the code into the functions.php of the active theme."
            ],
            [
                'id' => 'builtin_wordpress_patterns.md',
                'is_builtin' => true,
                'content' => "When asked to create page content with advanced graphics or layouts, use the official WordPress Patterns directory. You can use your search web tools or directly fetch them. You can also fetch patterns from the WordPress API (https://api.wordpress.org/patterns/1.0/ or https://wordpress.org/patterns/wp-json/wp/v2/wporg-pattern) to find suitable block patterns to insert into the editor."
            ]
        ];
    }

    private function get_skills_dir() {
        $upload_dir = wp_upload_dir();
        $dir = $upload_dir['basedir'] . '/wai/skills';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        return $dir;
    }

    public function api_get_skills(\WP_REST_Request $request) {
        $dir = $this->get_skills_dir();
        $files = glob($dir . '/*.{txt,md}', GLOB_BRACE);
        $skills = $this->get_builtin_skills();
        
        if (!empty($files)) {
            foreach ($files as $file) {
                if (basename($file) === 'README.txt') continue;
                $skills[] = [
                    'id' => basename($file),
                    'is_builtin' => false,
                    'content' => file_get_contents($file)
                ];
            }
        }
        return new \WP_REST_Response(['success' => true, 'skills' => $skills], 200);
    }

    public function api_save_skill(\WP_REST_Request $request) {
        $params = $request->get_json_params();
        $id = sanitize_file_name($params['id'] ?? '');
        $content = $params['content'] ?? '';
        $old_id = sanitize_file_name($params['old_id'] ?? '');

        if (empty($id)) {
            return new \WP_REST_Response(['success' => false, 'message' => __('ID is required.', 'wizard-ai')], 400);
        }

        if (strpos($id, 'builtin_') === 0 || strpos($old_id, 'builtin_') === 0) {
            return new \WP_REST_Response(['success' => false, 'message' => __('Built-in skills cannot be modified.', 'wizard-ai')], 403);
        }

        if (strpos($id, '.md') === false && strpos($id, '.txt') === false) {
            $id .= '.md';
        }

        $dir = $this->get_skills_dir();
        
        if (!empty($old_id) && $old_id !== $id) {
            $old_path = $dir . '/' . $old_id;
            if (file_exists($old_path)) {
                unlink($old_path);
            }
        }

        $path = $dir . '/' . $id;
        file_put_contents($path, $content);

        return new \WP_REST_Response(['success' => true, 'message' => __('Skill saved.', 'wizard-ai')], 200);
    }

    public function api_delete_skill(\WP_REST_Request $request) {
        $params = $request->get_json_params();
        $id = sanitize_file_name($params['id'] ?? '');

        if (empty($id)) {
            return new \WP_REST_Response(['success' => false, 'message' => __('ID is required.', 'wizard-ai')], 400);
        }
        
        if (strpos($id, 'builtin_') === 0) {
            return new \WP_REST_Response(['success' => false, 'message' => __('Built-in skills cannot be deleted.', 'wizard-ai')], 403);
        }

        $dir = $this->get_skills_dir();
        $path = $dir . '/' . $id;

        if (file_exists($path)) {
            unlink($path);
            return new \WP_REST_Response(['success' => true, 'message' => __('Skill deleted.', 'wizard-ai')], 200);
        }

        return new \WP_REST_Response(['success' => false, 'message' => __('Skill not found.', 'wizard-ai')], 404);
    }

    public function wb_ai_skills_page_html() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap wai-wrap">
            <h1><span class="dashicons dashicons-superhero"></span> <?php esc_html_e('AI Skills', 'wizard-ai'); ?></h1>
            <p class="description"><?php esc_html_e('Manage custom skills and guidelines for your AI Agents. These skills will be injected into the system prompt of the Playground, Editor Agent, and Block Agent.', 'wizard-ai'); ?></p>

            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <!-- Sidebar: List of skills -->
                <div style="width: 300px; background: #fff; border: 1px solid #ccd0d4; padding: 15px; border-radius: 4px;">
                    <h3><?php esc_html_e('Your Skills', 'wizard-ai'); ?></h3>
                    <ul id="wai-skills-list" style="margin: 0; padding: 0; list-style: none;">
                        <!-- Populated by JS -->
                    </ul>
                    <button type="button" class="button button-primary" id="wai-add-skill-btn" style="margin-top: 15px; width: 100%;">+ <?php esc_html_e('Create New Skill', 'wizard-ai'); ?></button>
                </div>

                <!-- Main area: Editor -->
                <div style="flex-grow: 1; background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px; display: none;" id="wai-skill-editor">
                    <input type="hidden" id="wai-skill-old-id" value="">
                    
                    <div style="margin-bottom: 15px;">
                        <label for="wai-skill-id" style="font-weight: bold; display: block; margin-bottom: 5px;"><?php esc_html_e('Skill Name (filename)', 'wizard-ai'); ?></label>
                        <input type="text" id="wai-skill-id" class="regular-text" placeholder="e.g. how_to_create_blocks.md" style="width: 100%;">
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label for="wai-skill-content" style="font-weight: bold; display: block; margin-bottom: 5px;"><?php esc_html_e('Instructions / Content', 'wizard-ai'); ?></label>
                        <textarea id="wai-skill-content" rows="15" style="width: 100%; font-family: monospace;"></textarea>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between;">
                        <button type="button" class="button button-primary" id="wai-save-skill-btn"><?php esc_html_e('Save Skill', 'wizard-ai'); ?></button>
                        <button type="button" class="button button-link-delete" id="wai-delete-skill-btn" style="color: #a00;"><?php esc_html_e('Delete Skill', 'wizard-ai'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    public function get_ai_skills() {
        $skills_text = "";
        $upload_dir = wp_upload_dir();
        $skills_dir = $upload_dir['basedir'] . '/wai/skills';
        
        // Auto-create directory if it doesn't exist
        if (!is_dir($skills_dir)) {
            wp_mkdir_p($skills_dir);
            // Optionally, create a readme file inside
            file_put_contents($skills_dir . '/README.txt', "Drop your .txt or .md files here to give custom skills to your AI Agents.\nFor example, create a 'how_to_create_blocks.txt' and describe your block architecture preferences.");
        }
        
        if (is_dir($skills_dir)) {
            $files = glob($skills_dir . '/*.{txt,md}', GLOB_BRACE);
            $has_custom = !empty($files) && count(array_filter($files, function($f) { return basename($f) !== 'README.txt'; })) > 0;
            
            if ($has_custom || method_exists($this, 'get_builtin_skills')) {
                $skills_text .= "\n\nCUSTOM SKILLS & INSTRUCTIONS:\n";
                
                if (method_exists($this, 'get_builtin_skills')) {
                    $builtins = $this->get_builtin_skills();
                    foreach ($builtins as $builtin) {
                        $skills_text .= "--- Skill: " . $builtin['id'] . " ---\n";
                        $skills_text .= $builtin['content'] . "\n\n";
                    }
                }
                
                if (!empty($files)) {
                    foreach ($files as $file) {
                        if (basename($file) === 'README.txt') continue;
                        $skills_text .= "--- Skill: " . basename($file) . " ---\n";
                        $skills_text .= file_get_contents($file) . "\n\n";
                    }
                }
            }
        }
        return $skills_text;
    }
}
