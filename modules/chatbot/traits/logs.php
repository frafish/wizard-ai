<?php
namespace WizardAi\Modules\Chatbot\Traits;

trait Logs {
    public function wb_ai_chatbot_logs_page_html() {
        global $wpdb;

        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';

        if (in_array($action, ['delete_message', 'delete_session'])) {
            check_admin_referer('wai_delete_log');
            if ($action === 'delete_message' && !empty($_GET['comment_id'])) {
                wp_delete_comment(intval($_GET['comment_id']), true);
                echo '<script>window.location.replace("' . admin_url('admin.php?page=wizard-ai-chatbot-logs&action=view&session_id=' . urlencode($session_id)) . '");</script>';
                exit;
            } elseif ($action === 'delete_session' && !empty($session_id)) {
                // Fetch comment IDs directly via SQL to bypass WPML filtering which hides comments with post_ID = 0
                $comment_ids = $wpdb->get_col($wpdb->prepare("
                    SELECT comment_id 
                    FROM {$wpdb->commentmeta} 
                    WHERE meta_key = 'wai_session_id' AND meta_value = %s
                ", $session_id));
                
                foreach ($comment_ids as $c_id) {
                    wp_delete_comment(intval($c_id), true);
                }
                echo '<script>window.location.replace("' . admin_url('admin.php?page=wizard-ai-chatbot-logs') . '");</script>';
                exit;
            }
        }

        echo '<div class="wrap">';
        if ($action === 'view' && !empty($session_id)) {
            // View Single Thread
            echo '<h1 class="wp-heading-inline">' . esc_html__('Chat Session', 'wizard-ai') . '</h1>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=wizard-ai-chatbot-logs')) . '" class="page-title-action">' . esc_html__('Back to Logs', 'wizard-ai') . '</a>';
            echo '<hr class="wp-header-end">';
            
            $comments = get_comments([
                'type' => 'wai_chat',
                'meta_key' => 'wai_session_id',
                'meta_value' => $session_id,
                'orderby' => 'comment_date_gmt',
                'order' => 'ASC',
                'status' => 'all'
            ]);

            if (empty($comments)) {
                echo '<p>' . esc_html__('No messages found for this session.', 'wizard-ai') . '</p>';
            } else {
                echo '<div style="max-width: 800px; background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px; margin-top: 20px;">';
                $email = '';
                $session_user_id = 0;
                foreach ($comments as $c) {
                    if (!empty($c->comment_author_email) && empty($email)) {
                        $email = $c->comment_author_email;
                    }
                    if (!empty($c->user_id) && $c->user_id > 0 && empty($session_user_id)) {
                        $session_user_id = $c->user_id;
                    }
                }
                echo '<h3>' . sprintf(esc_html__('Session ID: %s', 'wizard-ai'), esc_html($session_id)) . '</h3>';
                
                $del_session_url = wp_nonce_url(admin_url('admin.php?page=wizard-ai-chatbot-logs&action=delete_session&session_id=' . urlencode($session_id)), 'wai_delete_log');
                echo '<p>';
                echo '<button id="wai_summarize_session_btn" class="button button-primary" data-session="' . esc_attr($session_id) . '" style="margin-right: 10px;">' . esc_html__('Summarize Session', 'wizard-ai') . '</button>';
                echo '<a href="' . esc_url($del_session_url) . '" class="button button-link-delete" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this entire session?', 'wizard-ai')) . '\');" style="color: #b32d2e;">' . esc_html__('Delete Session', 'wizard-ai') . '</a>';
                echo '</p>';
                echo '<div id="wai_session_summary_result" style="display:none; padding: 15px; background: #f0f6fc; border-left: 4px solid #72aee6; margin-bottom: 15px; border-radius: 4px;"></div>';
                ?>
                <script>
                jQuery(document).ready(function($) {
                    $('#wai_summarize_session_btn').on('click', function(e) {
                        e.preventDefault();
                        var btn = $(this);
                        var sessionId = btn.data('session');
                        btn.prop('disabled', true).text('<?php echo esc_js(__('Generating...', 'wizard-ai')); ?>');
                        $('#wai_session_summary_result').hide().html('');
                        
                        $.ajax({
                            url: '<?php echo esc_url_raw(rest_url('wizard-ai/v1/chatbot/summarize-session')); ?>',
                            method: 'POST',
                            data: { session_id: sessionId },
                            beforeSend: function(xhr) {
                                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                            },
                            success: function(response) {
                                btn.prop('disabled', false).text('<?php echo esc_js(__('Summarize Session', 'wizard-ai')); ?>');
                                if (response.success && response.summary) {
                                    $('#wai_session_summary_result').html('<strong><?php echo esc_js(__('Session Digest:', 'wizard-ai')); ?></strong><br>' + response.summary).slideDown();
                                } else {
                                    alert(response.message || 'Error summarizing session.');
                                }
                            },
                            error: function() {
                                btn.prop('disabled', false).text('<?php echo esc_js(__('Summarize Session', 'wizard-ai')); ?>');
                                alert('<?php echo esc_js(__('Failed to communicate with server.', 'wizard-ai')); ?>');
                            }
                        });
                    });
                });
                </script>
                <?php
                
                if ($session_user_id > 0) {
                    $user_link = get_edit_user_link($session_user_id);
                    $user_obj = get_userdata($session_user_id);
                    $user_name = $user_obj ? $user_obj->display_name : __('User', 'wizard-ai');
                    echo '<p><strong>' . esc_html__('User:', 'wizard-ai') . '</strong> <a href="' . esc_url($user_link) . '">' . esc_html($user_name) . '</a></p>';
                }
                if ($email) {
                    echo '<p><strong>' . esc_html__('User Email:', 'wizard-ai') . '</strong> ' . esc_html($email) . '</p>';
                }
                echo '<hr>';

                foreach ($comments as $c) {
                    $is_ai = $c->comment_author === 'Wizard AI';
                    $bg = $is_ai ? '#f1f1f1' : '#e3f2fd';
                    $align = $is_ai ? 'left' : 'right';
                    $margin = $is_ai ? '0 50px 15px 0' : '0 0 15px 50px';
                    
                    $author_name_html = esc_html($c->comment_author);
                    if (!$is_ai && !empty($c->user_id) && $c->user_id > 0) {
                        $user_link = get_edit_user_link($c->user_id);
                        $author_name_html = '<a href="' . esc_url($user_link) . '">' . esc_html($c->comment_author) . '</a>';
                    }
                    
                    $del_msg_url = wp_nonce_url(admin_url('admin.php?page=wizard-ai-chatbot-logs&action=delete_message&comment_id=' . $c->comment_ID . '&session_id=' . urlencode($session_id)), 'wai_delete_log');
                    $edit_msg_url = get_edit_comment_link($c->comment_ID);
                    
                    echo '<div style="background: ' . esc_attr($bg) . '; padding: 15px; border-radius: 8px; margin: ' . esc_attr($margin) . '; text-align: left; position: relative;">';
                    echo '<div style="position: absolute; top: 10px; right: 10px;">';
                    echo '<a href="' . esc_url($edit_msg_url) . '" style="color: #2271b1; text-decoration: none; margin-right: 8px;" title="' . esc_attr__('Edit Message natively', 'wizard-ai') . '"><span class="dashicons dashicons-edit"></span></a>';
                    echo '<a href="' . esc_url($del_msg_url) . '" onclick="return confirm(\'' . esc_js(__('Delete this message?', 'wizard-ai')) . '\');" style="color: #b32d2e; text-decoration: none;" title="' . esc_attr__('Delete Message', 'wizard-ai') . '"><span class="dashicons dashicons-trash"></span></a>';
                    echo '</div>';
                    echo '<strong>' . $author_name_html . '</strong> <span style="font-size: 11px; color: #888;">(' . esc_html($c->comment_date) . ')</span>';
                    echo '<div style="margin-top: 8px;">' . wp_kses_post($c->comment_content) . '</div>';
                    echo '</div>';
                }
                echo '</div>';
            }
        } else {
            // List Sessions
            echo '<h1 class="wp-heading-inline">' . esc_html__('Chatbot Logs', 'wizard-ai') . '</h1>';
            echo '<hr class="wp-header-end">';
            
            $per_page = 20;
            $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
            $offset = ($paged - 1) * $per_page;

            // Get unique session IDs using SQL since WP_Comment_Query doesn't support GROUP BY meta_value natively
            $query = "
                SELECT m.meta_value AS session_id, 
                       MAX(c.comment_date) AS last_activity, 
                       MAX(CASE WHEN c.comment_author != 'Wizard AI' THEN c.comment_author_email ELSE NULL END) AS comment_author_email, 
                       MAX(CASE WHEN c.comment_author != 'Wizard AI' THEN c.comment_author ELSE NULL END) AS comment_author,
                       MAX(CASE WHEN c.comment_author != 'Wizard AI' THEN c.user_id ELSE 0 END) AS user_id
                FROM {$wpdb->comments} c 
                INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id 
                WHERE c.comment_type = 'wai_chat' AND m.meta_key = 'wai_session_id' 
                GROUP BY m.meta_value 
                HAVING MAX(CASE WHEN c.comment_author != 'Wizard AI' THEN 1 ELSE 0 END) = 1 AND m.meta_value != ''
                ORDER BY last_activity DESC 
                LIMIT %d OFFSET %d
            ";
            $sessions = $wpdb->get_results($wpdb->prepare($query, $per_page, $offset));

            $total_query = "
                SELECT COUNT(*) FROM (
                    SELECT m.meta_value 
                    FROM {$wpdb->comments} c 
                    INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id 
                    WHERE c.comment_type = 'wai_chat' AND m.meta_key = 'wai_session_id'
                    GROUP BY m.meta_value
                    HAVING MAX(CASE WHEN c.comment_author != 'Wizard AI' THEN 1 ELSE 0 END) = 1 AND m.meta_value != ''
                ) AS count_table
            ";
            $total_sessions = $wpdb->get_var($total_query);
            $total_pages = ceil($total_sessions / $per_page);

            if (empty($sessions)) {
                echo '<p>' . esc_html__('No chat sessions found.', 'wizard-ai') . '</p>';
            } else {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr>';
                echo '<th>' . esc_html__('Session ID', 'wizard-ai') . '</th>';
                echo '<th>' . esc_html__('User', 'wizard-ai') . '</th>';
                echo '<th>' . esc_html__('Email', 'wizard-ai') . '</th>';
                echo '<th>' . esc_html__('Last Activity', 'wizard-ai') . '</th>';
                echo '<th>' . esc_html__('Actions', 'wizard-ai') . '</th>';
                echo '</tr></thead>';
                echo '<tbody>';
                foreach ($sessions as $s) {
                    $view_url = admin_url('admin.php?page=wizard-ai-chatbot-logs&action=view&session_id=' . urlencode($s->session_id));
                    
                    $display_name = !empty($s->comment_author) ? $s->comment_author : 'Visitor';
                    
                    if (!empty($s->user_id) && $s->user_id > 0) {
                        $user_link = get_edit_user_link($s->user_id);
                        $display_name_html = '<a href="' . esc_url($user_link) . '">' . esc_html($display_name) . '</a>';
                    } else {
                        $display_name_html = esc_html($display_name);
                    }
                    
                    echo '<tr>';
                    echo '<td><strong>' . esc_html($s->session_id) . '</strong></td>';
                    echo '<td>' . $display_name_html . '</td>';
                    echo '<td>' . esc_html($s->comment_author_email) . '</td>';
                    echo '<td>' . esc_html($s->last_activity) . '</td>';
                    
                    $del_session_url = wp_nonce_url(admin_url('admin.php?page=wizard-ai-chatbot-logs&action=delete_session&session_id=' . urlencode($s->session_id)), 'wai_delete_log');
                    
                    echo '<td>';
                    echo '<a href="' . esc_url($view_url) . '" class="button dashicons-before dashicons-visibility" style="margin-right: 5px;" title="' . esc_attr__('View Thread', 'wizard-ai') . '"></a>';
                    echo '<a href="' . esc_url($del_session_url) . '" class="button dashicons-before dashicons-trash" style="color: #b32d2e; border-color: #b32d2e;" title="' . esc_attr__('Delete', 'wizard-ai') . '" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this entire session?', 'wizard-ai')) . '\');"></a>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';

                if ($total_pages > 1) {
                    $page_links = paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;', 'wizard-ai'),
                        'next_text' => __('&raquo;', 'wizard-ai'),
                        'total' => $total_pages,
                        'current' => $paged
                    ]);
                    if ($page_links) {
                        echo '<div class="tablenav"><div class="tablenav-pages" style="float:left; margin-top:10px;">' . $page_links . '</div></div>';
                    }
                }
            }
        }
        echo '</div>';
    }

    public function handle_summarize_session_request(\WP_REST_Request $request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        if (empty($session_id)) {
            return new \WP_REST_Response(['success' => false, 'message' => __('Missing session ID.', 'wizard-ai')], 400);
        }

        $comments = get_comments([
            'type' => 'wai_chat',
            'meta_key' => 'wai_session_id',
            'meta_value' => $session_id,
            'orderby' => 'comment_date_gmt',
            'order' => 'ASC',
            'status' => 'all'
        ]);

        if (empty($comments)) {
            return new \WP_REST_Response(['success' => false, 'message' => __('No messages found for this session.', 'wizard-ai')], 404);
        }

        $chat_transcript = "";
        foreach ($comments as $c) {
            $author = ($c->comment_author === 'Wizard AI') ? 'AI' : 'User';
            $chat_transcript .= "{$author}: " . strip_tags($c->comment_content) . "\n";
        }

        if (!class_exists('\WordPress\AiClient\AiClient')) {
            return new \WP_REST_Response(['success' => false, 'message' => __('AI Client not available.', 'wizard-ai')], 500);
        }

        $prompt = "Please provide a brief and concise summary (digest) of the following chat session between a User and an AI Assistant. Highlight the main topics discussed and any conclusions or resolutions.\n\n";
        $prompt .= "CHAT TRANSCRIPT:\n" . $chat_transcript;

        try {
            $ai_query = \WordPress\AiClient\AiClient::prompt([
                new \WordPress\AiClient\Messages\DTO\UserMessage([
                    new \WordPress\AiClient\Messages\DTO\MessagePart($prompt)
                ])
            ]);
            $res = $ai_query->generateResult();
            $summary = $res->toText();

            // Format markdown to simple HTML for display
            if (class_exists('Parsedown')) {
                $parsedown = new \Parsedown();
                $summary_html = $parsedown->text($summary);
            } else {
                $summary_html = nl2br(esc_html($summary));
            }

            return new \WP_REST_Response(['success' => true, 'summary' => wp_kses_post($summary_html)], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
