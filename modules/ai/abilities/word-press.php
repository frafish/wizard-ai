<?php
namespace WizardAi\Modules\Ai\Abilities;

trait WordPress {
    public function register_wordpress_abilities() {
        wp_register_ability('wizard-ai/generate-image', [
            'label' => __('Generate Image', 'wizard-ai'),
            'description' => __('Generates an image based on a prompt and saves it to the WordPress media library. If post_id is provided, sets it as the thumbnail. Otherwise, returns the media ID so you can use it later.', 'wizard-ai'),
            'category' => 'wizard-ai',
            'execute_callback' => function($input) {
                $post_id = isset($input['post_id']) ? intval($input['post_id']) : 0;
                $prompt = sanitize_text_field($input['prompt']);

                if (!$prompt) {
                    return new \WP_Error('invalid_input', 'Prompt is required to generate an image.');
                }

                if ($post_id && !get_post($post_id)) {
                    return new \WP_Error('invalid_post', 'The specified post does not exist.');
                }

                $image_url = 'https://image.pollinations.ai/prompt/' . urlencode($prompt) . '?width=1024&height=1024&nologo=true';

                require_once(ABSPATH . 'wp-admin/includes/image.php');
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');

                $tmp_file = download_url($image_url);
                if (is_wp_error($tmp_file)) {
                    return $tmp_file;
                }

                $file_array = [
                    'name' => sanitize_title($prompt) . '-' . time() . '.jpg',
                    'tmp_name' => $tmp_file
                ];

                $attach_id = media_handle_sideload($file_array, $post_id ?: 0);

                if (is_wp_error($attach_id)) {
                    @unlink($file_array['tmp_name']);
                    return $attach_id;
                }

                if ($post_id) {
                    $result = set_post_thumbnail($post_id, $attach_id);
                    if (!$result) {
                        return new \WP_Error('thumbnail_error', 'Failed to set the image as the post thumbnail.');
                    }
                }

                $media_url = wp_get_attachment_url($attach_id);

                return [
                    'success' => true,
                    'message' => $post_id ? 'Image generated and set as thumbnail successfully.' : 'Image generated successfully.',
                    'media_id' => $attach_id,
                    'media_url' => $media_url
                ];
            },
            'permission_callback' => function() { return current_user_can('upload_files') && current_user_can('edit_posts'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'post_id' => ['type' => 'integer', 'description' => 'Optional. The ID of the post to set the thumbnail for.'],
                    'prompt' => ['type' => 'string', 'description' => 'The prompt to generate the image.']
                ],
                'required' => ['prompt']
            ]
        ]);

        wp_register_ability('wizard-ai/manage-posts', [
            'label' => __('Manage Posts & Pages', 'wizard-ai'),
            'description' => __('Create, read, update, or delete posts and pages.', 'wizard-ai'),
            'category' => 'wizard-ai',
            'execute_callback' => function($input) {
                $action = $input['action'];
                $args = $input['args'] ?? [];
                
                if ($action === 'get') {
                    $posts = get_posts(array_merge(['post_type' => 'any', 'post_status' => 'any', 'posts_per_page' => 10], $args));
                    $data = array_map(function($p) { return $p->to_array(); }, $posts);
                    return ['success' => true, 'posts' => $data];
                } elseif ($action === 'create' || $action === 'update') {
                    $post_id = wp_insert_post($args);
                    if (is_wp_error($post_id)) return $post_id;
                    return ['success' => true, 'post_id' => $post_id];
                } elseif ($action === 'delete') {
                    $id = $args['ID'] ?? 0;
                    if (!$id) return new \WP_Error('missing_id', 'Post ID is required.');
                    $result = wp_delete_post($id, $args['force_delete'] ?? false);
                    if (!$result) return new \WP_Error('delete_failed', 'Failed to delete post.');
                    return ['success' => true];
                }
                return new \WP_Error('invalid_action', 'Unsupported action.');
            },
            'permission_callback' => function() { return current_user_can('edit_posts'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['get', 'create', 'update', 'delete'], 'description' => 'Action to perform'],
                    'args' => ['type' => 'object', 'description' => 'Arguments. E.g. {"post_title": "Hello", "post_content": "World", "post_type": "page"} for create.']
                ],
                'required' => ['action']
            ]
        ]);

        wp_register_ability('wizard-ai/manage-comments', [
            'label' => __('Manage Comments', 'wizard-ai'),
            'description' => __('Get, approve, spam, or trash comments.', 'wizard-ai'),
            'category' => 'wizard-ai',
            'execute_callback' => function($input) {
                $action = $input['action'];
                $args = $input['args'] ?? [];
                
                if ($action === 'get') {
                    $comments = get_comments($args);
                    $data = array_map(function($c) { return $c->to_array(); }, $comments);
                    return ['success' => true, 'comments' => $data];
                } elseif ($action === 'set_status') {
                    $comment_id = $args['comment_id'] ?? 0;
                    $status = $args['status'] ?? ''; // 'approve', 'hold', 'spam', 'trash', 'delete'
                    if (!$comment_id || !$status) return new \WP_Error('missing_args', 'comment_id and status are required.');
                    $result = wp_set_comment_status($comment_id, $status);
                    if (is_wp_error($result)) return $result;
                    return ['success' => true];
                }
                return new \WP_Error('invalid_action', 'Unsupported action.');
            },
            'permission_callback' => function() { return current_user_can('moderate_comments'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['get', 'set_status'], 'description' => 'Action to perform'],
                    'args' => ['type' => 'object', 'description' => 'Arguments. E.g. {"status": "hold"} for get, or {"comment_id": 12, "status": "approve"} for set_status.']
                ],
                'required' => ['action']
            ]
        ]);

        wp_register_ability('wizard-ai/manage-users', [
            'label' => __('Manage Users & Roles', 'wizard-ai'),
            'description' => __('Manage WordPress users, roles, and capabilities.', 'wizard-ai'),
            'category' => 'wizard-ai',
            'execute_callback' => function($input) {
                $action = $input['action'];
                $args = $input['args'] ?? [];
                
                if ($action === 'create_user') {
                    $user_id = wp_insert_user($args);
                    if (is_wp_error($user_id)) return $user_id;
                    return ['success' => true, 'user_id' => $user_id];
                } elseif ($action === 'update_user') {
                    $user_id = wp_update_user($args);
                    if (is_wp_error($user_id)) return $user_id;
                    return ['success' => true, 'user_id' => $user_id];
                } elseif ($action === 'delete_user') {
                    require_once ABSPATH . 'wp-admin/includes/user.php';
                    $reassign = $args['reassign'] ?? null;
                    $result = wp_delete_user($args['user_id'], $reassign);
                    return ['success' => $result];
                } elseif ($action === 'get_users') {
                    $users = get_users($args);
                    $data = array_map(function($u) { return $u->to_array(); }, $users);
                    return ['success' => true, 'users' => $data];
                } elseif ($action === 'add_role') {
                    add_role($args['role'], $args['display_name'], $args['capabilities'] ?? []);
                    return ['success' => true];
                } elseif ($action === 'remove_role') {
                    remove_role($args['role']);
                    return ['success' => true];
                } elseif ($action === 'add_cap' || $action === 'remove_cap') {
                    $role = get_role($args['role']);
                    if (!$role) return new \WP_Error('invalid_role', 'Role not found.');
                    if ($action === 'add_cap') $role->add_cap($args['cap']);
                    else $role->remove_cap($args['cap']);
                    return ['success' => true];
                }
                return new \WP_Error('invalid_action', 'Unsupported action.');
            },
            'permission_callback' => function() { return current_user_can('edit_users'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['create_user', 'update_user', 'delete_user', 'get_users', 'add_role', 'remove_role', 'add_cap', 'remove_cap'], 'description' => 'Action to perform'],
                    'args' => ['type' => 'object', 'description' => 'Arguments for the action. For create_user/update_user use user data array. For add_role use {"role":"editor", "display_name":"Editor"}. For add_cap/remove_cap use {"role":"editor", "cap":"edit_theme_options"}.']
                ],
                'required' => ['action']
            ]
        ]);

        wp_register_ability('wizard-ai/manage-media', [
            'label' => __('Manage Media', 'wizard-ai'),
            'description' => __('Manage WordPress media library (update metadata, alt text, sideload images).', 'wizard-ai'),
            'category' => 'wizard-ai',
            'execute_callback' => function($input) {
                $action = $input['action'];
                $args = $input['args'] ?? [];
                
                if ($action === 'get_media') {
                    $posts = get_posts(array_merge(['post_type' => 'attachment', 'post_status' => 'inherit'], $args));
                    $data = [];
                    foreach ($posts as $p) {
                        $data[] = [
                            'id' => $p->ID,
                            'title' => $p->post_title,
                            'caption' => $p->post_excerpt,
                            'description' => $p->post_content,
                            'alt_text' => get_post_meta($p->ID, '_wp_attachment_image_alt', true),
                            'url' => wp_get_attachment_url($p->ID)
                        ];
                    }
                    return ['success' => true, 'media' => $data];
                } elseif ($action === 'update_meta') {
                    $id = $args['id'] ?? 0;
                    if (!$id) return new \WP_Error('missing_id', 'Media ID is required.');
                    
                    $update = ['ID' => $id];
                    if (isset($args['title'])) $update['post_title'] = $args['title'];
                    if (isset($args['caption'])) $update['post_excerpt'] = $args['caption'];
                    if (isset($args['description'])) $update['post_content'] = $args['description'];
                    
                    if (count($update) > 1) wp_update_post($update);
                    if (isset($args['alt_text'])) update_post_meta($id, '_wp_attachment_image_alt', $args['alt_text']);
                    
                    return ['success' => true];
                } elseif ($action === 'upload_from_url') {
                    require_once ABSPATH . 'wp-admin/includes/media.php';
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                    
                    $url = $args['url'] ?? '';
                    $post_id = $args['post_id'] ?? 0;
                    $desc = $args['description'] ?? '';
                    
                    if (!$url) return new \WP_Error('missing_url', 'URL is required.');
                    
                    $id = media_sideload_image($url, $post_id, $desc, 'id');
                    if (is_wp_error($id)) return $id;
                    return ['success' => true, 'media_id' => $id, 'url' => wp_get_attachment_url($id)];
                }
                return new \WP_Error('invalid_action', 'Unsupported action.');
            },
            'permission_callback' => function() { return current_user_can('upload_files'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['get_media', 'update_meta', 'upload_from_url'], 'description' => 'Action to perform'],
                    'args' => ['type' => 'object', 'description' => 'Arguments. E.g. for update_meta {"id": 12, "alt_text": "A cool image", "title": "Cool Image"}. For upload_from_url {"url": "https://..."}.']
                ],
                'required' => ['action']
            ]
        ]);

        wp_register_ability('wizard-ai/manage-menus', [
            'label' => __('Manage Menus', 'wizard-ai'),
            'description' => __('Manage WordPress menus, locations, and nav items.', 'wizard-ai'),
            'category' => 'wizard-ai',
            'execute_callback' => function($input) {
                $action = $input['action'];
                $args = $input['args'] ?? [];
                
                if ($action === 'get_menus') {
                    $menus = wp_get_nav_menus();
                    $locations = get_nav_menu_locations();
                    return ['success' => true, 'menus' => $menus, 'locations' => $locations];
                } elseif ($action === 'create_menu') {
                    $id = wp_create_nav_menu($args['name']);
                    if (is_wp_error($id)) return $id;
                    if (!empty($args['location'])) {
                        $locations = get_nav_menu_locations();
                        $locations[$args['location']] = $id;
                        set_theme_mod('nav_menu_locations', $locations);
                    }
                    return ['success' => true, 'menu_id' => $id];
                } elseif ($action === 'add_menu_item') {
                    $menu_id = $args['menu_id'] ?? 0;
                    $item_data = $args['item_data'] ?? [];
                    $id = wp_update_nav_menu_item($menu_id, 0, $item_data);
                    if (is_wp_error($id)) return $id;
                    return ['success' => true, 'item_id' => $id];
                }
                return new \WP_Error('invalid_action', 'Unsupported action.');
            },
            'permission_callback' => function() { return current_user_can('edit_theme_options'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['get_menus', 'create_menu', 'add_menu_item'], 'description' => 'Action to perform'],
                    'args' => ['type' => 'object', 'description' => 'Arguments. E.g. {"name": "Main Menu", "location": "primary"} or {"menu_id": 12, "item_data": {"menu-item-title": "Home", "menu-item-url": "/", "menu-item-status": "publish"}}.']
                ],
                'required' => ['action']
            ]
        ]);
        
        wp_register_ability('wizard-ai/wp-patterns', [
            'label' => __('WordPress Patterns Library', 'wizard-ai'),
            'description' => __('Search and fetch official block patterns from the WordPress.org pattern directory. Returns the ready-to-use Gutenberg HTML content for each pattern.', 'wizard-ai'),
            'category' => 'wizard-ai',
            'execute_callback' => function($input) {
                $search = urlencode($input['search'] ?? '');
                $category = urlencode($input['category'] ?? '');
                $url = 'https://api.wordpress.org/patterns/1.0/?';
                
                if (!empty($search)) $url .= 'search=' . $search . '&';
                if (!empty($category)) $url .= 'pattern-categories=' . $category . '&';
                
                $response = wp_remote_get($url, ['timeout' => 15]);
                if (is_wp_error($response)) {
                    return $response;
                }
                
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (!is_array($data)) {
                    return new \WP_Error('api_error', 'Invalid response from WordPress.org API.');
                }
                
                $results = [];
                // Return top 5 to avoid token exhaustion
                foreach (array_slice($data, 0, 5) as $pattern) {
                    $results[] = [
                        'title' => $pattern['title']['rendered'] ?? '',
                        'content' => $pattern['content'] ?? '',
                        'categories' => $pattern['pattern-categories'] ?? [],
                        'viewport_width' => $pattern['viewport_width'] ?? ''
                    ];
                }
                
                if (empty($results)) {
                    return ['success' => true, 'message' => 'No patterns found. Try a different search term.'];
                }
                
                return [
                    'success' => true,
                    'count' => count($results),
                    'patterns' => $results
                ];
            },
            'permission_callback' => function() { return current_user_can('edit_posts'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'search' => ['type' => 'string', 'description' => 'Keyword to search for patterns (e.g. "header", "hero", "pricing")'],
                    'category' => ['type' => 'string', 'description' => 'Optional category slug or ID (e.g. "header", "footer", "buttons", "gallery", "text")']
                ],
                'required' => []
            ]
        ]);

    }
}
