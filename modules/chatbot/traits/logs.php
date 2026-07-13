<?php
namespace WizardAi\Modules\Chatbot\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.Security.ValidatedSanitizedInput
// phpcs:disable WordPress.Security.NonceVerification.Recommended
// phpcs:disable WordPress.DB.DirectDatabaseQuery
// phpcs:disable WordPress.DB.SlowDBQuery



trait Logs {
    public function wb_ai_chatbot_logs_page_html() {
        global $wpdb;

        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';

        if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete' && isset($_POST['session_ids']) && is_array($_POST['session_ids'])) {
            check_admin_referer('wai_bulk_delete_logs');
            foreach ($_POST['session_ids'] as $sid) {
                $sid = sanitize_text_field($sid);
                $comment_ids = $wpdb->get_col($wpdb->prepare("
                    SELECT comment_id 
                    FROM {$wpdb->commentmeta} 
                    WHERE meta_key = 'wai_session_id' AND meta_value = %s
                ", $sid));
                
                foreach ($comment_ids as $c_id) {
                    wp_delete_comment(intval($c_id), true);
                }
                delete_transient('wai_chatbot_' . $sid);
                delete_transient('wai_chatbot_' . $sid . '_email');
                delete_transient('wai_chatbot_manual_' . $sid);
            }
            echo '<script>window.location.replace("' . esc_url_raw(admin_url('admin.php?page=wizard-ai-chatbot-logs')) . '");</script>';
            exit;
        }

        if (in_array($action, ['delete_message', 'delete_session'])) {
            check_admin_referer('wai_delete_log');
            if ($action === 'delete_message' && !empty($_GET['comment_id'])) {
                wp_delete_comment(intval($_GET['comment_id']), true);
                echo '<script>window.location.replace("' . esc_url_raw(admin_url('admin.php?page=wizard-ai-chatbot-logs&action=view&session_id=' . urlencode($session_id))) . '");</script>';
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
                delete_transient('wai_chatbot_' . $session_id);
                delete_transient('wai_chatbot_' . $session_id . '_email');
                delete_transient('wai_chatbot_manual_' . $session_id);
                echo '<script>window.location.replace("' . esc_url_raw(admin_url('admin.php?page=wizard-ai-chatbot-logs')) . '");</script>';
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
                $user_ip = '';
                $user_agent = '';
                foreach ($comments as $c) {
                    if ($c->comment_author === 'Wizard AI' || strpos($c->comment_author, 'Operator') !== false) {
                        continue;
                    }
                    if (!empty($c->comment_author_email) && empty($email)) {
                        $email = $c->comment_author_email;
                    }
                    // Try extracting from text if email is not yet set natively
                    if (empty($email) && preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $c->comment_content, $m)) {
                        $email = $m[0];
                    }
                    if (!empty($c->user_id) && $c->user_id > 0 && empty($session_user_id)) {
                        $session_user_id = $c->user_id;
                    }
                    if (!empty($c->comment_author_IP) && empty($user_ip)) {
                        $user_ip = $c->comment_author_IP;
                    }
                    if (!empty($c->comment_agent) && empty($user_agent)) {
                        $user_agent = $c->comment_agent;
                    }
                }
                
                /* translators: %s: Session ID */
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
                                xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>');
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
                
                $total_sessions = 1;
                $last_seen = '';
                
                if (!empty($email)) {
                    $other_sessions_query = $wpdb->prepare("
                        SELECT m.meta_value AS session_id, MAX(c.comment_date) as last_activity
                        FROM {$wpdb->comments} c
                        INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id
                        WHERE c.comment_type = 'wai_chat' AND m.meta_key = 'wai_session_id' AND c.comment_author_email = %s AND c.comment_author != 'Wizard AI'
                        GROUP BY m.meta_value
                        ORDER BY last_activity DESC
                    ", $email);
                } else if (!empty($user_ip)) {
                    $other_sessions_query = $wpdb->prepare("
                        SELECT m.meta_value AS session_id, MAX(c.comment_date) as last_activity
                        FROM {$wpdb->comments} c
                        INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id
                        WHERE c.comment_type = 'wai_chat' AND m.meta_key = 'wai_session_id' AND c.comment_author_IP = %s AND c.comment_author != 'Wizard AI'
                        GROUP BY m.meta_value
                        ORDER BY last_activity DESC
                    ", $user_ip);
                }
                
                if (isset($other_sessions_query)) {
                    $user_sessions = $wpdb->get_results($other_sessions_query);
                    if (!empty($user_sessions)) {
                        $total_sessions = count($user_sessions);
                        $last_seen = $user_sessions[0]->last_activity;
                    }
                }
                
                echo '<div class="wai-user-panel" style="background:#f8f9fa; border: 1px solid #ccd0d4; padding: 15px; margin: 15px 0; border-radius: 4px; display: flex; flex-wrap: wrap; gap: 20px;">';
                
                // Column 1: Info
                echo '<div style="flex: 1; min-width: 250px;">';
                echo '<h4 style="margin-top:0;">' . esc_html__('User Info', 'wizard-ai') . '</h4>';
                if ($session_user_id > 0) {
                    $user_link = get_edit_user_link($session_user_id);
                    $user_obj = get_userdata($session_user_id);
                    $user_name = $user_obj ? $user_obj->display_name : __('User', 'wizard-ai');
                    echo '<p style="margin: 0 0 5px 0;"><strong>' . esc_html__('Name:', 'wizard-ai') . '</strong> <a href="' . esc_url($user_link) . '">' . esc_html($user_name) . '</a></p>';
                } else {
                    $name_to_show = '';
                    foreach ($comments as $c) {
                        if ($c->comment_author !== 'Wizard AI' && $c->comment_author !== 'Visitor' && strpos($c->comment_author, 'Operator') === false) {
                            $name_to_show = $c->comment_author; break;
                        } else if (preg_match('/^My name is (.*?) and my email is/', $c->comment_content, $nm)) {
                            $name_to_show = trim($nm[1]); break;
                        }
                    }
                    echo '<p style="margin: 0 0 5px 0;"><strong>' . esc_html__('Name:', 'wizard-ai') . '</strong> ' . esc_html($name_to_show ?: 'Visitor') . '</p>';
                }
                if ($email) {
                    echo '<p style="margin: 0 0 5px 0;"><strong>' . esc_html__('Email:', 'wizard-ai') . '</strong> <a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></p>';
                }
                if ($user_ip) {
                    echo '<p style="margin: 0 0 5px 0;"><strong>' . esc_html__('IP:', 'wizard-ai') . '</strong> ' . esc_html($user_ip) . '</p>';
                }
                if ($user_agent) {
                    echo '<p style="margin: 0; font-size: 12px; color: #666;" title="' . esc_attr($user_agent) . '"><strong>' . esc_html__('Browser:', 'wizard-ai') . '</strong> ' . esc_html(substr($user_agent, 0, 50)) . '...</p>';
                }
                echo '</div>';

                // Column 2: Stats
                echo '<div style="flex: 1; min-width: 200px;">';
                echo '<h4 style="margin-top:0;">' . esc_html__('Chat Stats', 'wizard-ai') . '</h4>';
                echo '<p style="margin: 0 0 5px 0;"><strong>' . esc_html__('Total Sessions:', 'wizard-ai') . '</strong> ' . esc_html($total_sessions) . '</p>';
                if ($last_seen) {
                    echo '<p style="margin: 0 0 5px 0;"><strong>' . esc_html__('Last Seen:', 'wizard-ai') . '</strong> ' . esc_html($last_seen) . '</p>';
                }
                
                $filter_url = admin_url('admin.php?page=wizard-ai-chatbot-logs');
                if ($email) {
                    $filter_url = add_query_arg('filter_email', urlencode($email), $filter_url);
                } else if ($user_ip) {
                    $filter_url = add_query_arg('filter_ip', urlencode($user_ip), $filter_url);
                }
                echo '<p style="margin-top: 10px;"><a href="' . esc_url($filter_url) . '" class="button button-secondary button-small">' . esc_html__('View All User Sessions', 'wizard-ai') . '</a></p>';
                echo '</div>';
                
                // Column 3: WooCommerce
                if (class_exists('WooCommerce') && $email) {
                    echo '<div style="flex: 1; min-width: 200px;">';
                    echo '<h4 style="margin-top:0;">' . esc_html__('WooCommerce', 'wizard-ai') . '</h4>';
                    $customer_orders = wc_get_orders([
                        'email' => $email,
                        'limit' => -1,
                        'return' => 'ids'
                    ]);
                    $order_count = count($customer_orders);
                    echo '<p style="margin: 0 0 5px 0;"><strong>' . esc_html__('Orders:', 'wizard-ai') . '</strong> ' . esc_html($order_count) . '</p>';
                    
                    if ($session_user_id > 0) {
                        $cart_meta = get_user_meta($session_user_id, '_woocommerce_persistent_cart_' . get_current_blog_id(), true);
                        if (!empty($cart_meta['cart'])) {
                            $cart_count = array_sum(wp_list_pluck($cart_meta['cart'], 'quantity'));
                            echo '<p style="margin: 5px 0 2px 0;"><strong>' . esc_html__('Items in Cart:', 'wizard-ai') . '</strong> ' . esc_html($cart_count) . '</p>';
                            echo '<ul style="margin: 0 0 10px 15px; font-size: 12px; padding: 0;">';
                            foreach ($cart_meta['cart'] as $item) {
                                $product = wc_get_product($item['variation_id'] > 0 ? $item['variation_id'] : $item['product_id']);
                                if ($product) {
                                    echo '<li style="margin-bottom: 2px;">' . esc_html($item['quantity']) . 'x <a href="' . esc_url(get_edit_post_link($product->get_id())) . '">' . esc_html($product->get_name()) . '</a></li>';
                                }
                            }
                            echo '</ul>';
                        } else {
                            echo '<p style="margin: 5px 0 5px 0;"><strong>' . esc_html__('Cart:', 'wizard-ai') . '</strong> <em>' . esc_html__('Empty', 'wizard-ai') . '</em></p>';
                        }
                    } else {
                        echo '<p style="margin: 5px 0 5px 0; font-size: 11px; color: #666; font-style: italic;">' . esc_html__('Cart items only visible for logged-in users.', 'wizard-ai') . '</p>';
                    }
                    
                    if ($order_count > 0) {
                        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                            $woo_link = admin_url('admin.php?page=wc-orders&_billing_email=' . urlencode($email));
                        } else {
                            $woo_link = admin_url('edit.php?post_type=shop_order&s=' . urlencode($email));
                        }
                        echo '<p style="margin-top: 10px;"><a href="' . esc_url($woo_link) . '" class="button button-secondary button-small">' . esc_html__('View Orders', 'wizard-ai') . '</a></p>';
                    }
                    echo '</div>';
                }

                echo '</div>'; // close panel
                echo '<div id="wai_chat_history_container">';
                foreach ($comments as $c) {
                    $is_ai = $c->comment_author === 'Wizard AI';
                    $bg = $is_ai ? '#f1f1f1' : '#e3f2fd';
                    $align = $is_ai ? 'left' : 'right';
                    $margin = $is_ai ? '0 50px 15px 0' : '0 0 15px 50px';
                    
                    if (strpos($c->comment_author, 'Operator') !== false) {
                        $bg = '#fff3cd'; // Yellowish for operator
                        $margin = '0 50px 15px 0'; // AI side
                    }
                    
                    if ($is_ai) {
                        $ai_name = get_option('wai_chatbot_name', 'AI Bot');
                        if (empty($ai_name)) {
                            $ai_name = 'AI Bot';
                        }
                        $ai_name = apply_filters('wpml_translate_single_string', $ai_name, 'wizard-ai', 'chatbot_name');
                        $author_name_html = esc_html($ai_name);
                    } else if (strpos($c->comment_author, 'Operator') !== false) {
                        $author_name_html = esc_html($c->comment_author);
                    } else {
                        $author_display = ($c->comment_author === 'Visitor') ? 'You' : $c->comment_author;
                        $author_name_html = esc_html($author_display);
                        if (!empty($c->user_id) && $c->user_id > 0) {
                            $user_link = get_edit_user_link($c->user_id);
                            $author_name_html = '<a href="' . esc_url($user_link) . '">' . esc_html($author_display) . '</a>';
                        }
                    }
                    
                    $del_msg_url = wp_nonce_url(admin_url('admin.php?page=wizard-ai-chatbot-logs&action=delete_message&comment_id=' . $c->comment_ID . '&session_id=' . urlencode($session_id)), 'wai_delete_log');
                    $edit_msg_url = get_edit_comment_link($c->comment_ID);
                    
                    echo '<div class="wai-chat-message-row" data-date="' . esc_attr($c->comment_date_gmt) . '" style="background: ' . esc_attr($bg) . '; padding: 15px; border-radius: 8px; margin: ' . esc_attr($margin) . '; text-align: left; position: relative;">';
                    echo '<div style="position: absolute; top: 10px; right: 10px;">';
                    echo '<a href="' . esc_url($edit_msg_url) . '" style="color: #2271b1; text-decoration: none; margin-right: 8px;" title="' . esc_attr__('Edit Message natively', 'wizard-ai') . '"><span class="dashicons dashicons-edit"></span></a>';
                    echo '<a href="' . esc_url($del_msg_url) . '" onclick="return confirm(\'' . esc_js(__('Delete this message?', 'wizard-ai')) . '\');" style="color: #b32d2e; text-decoration: none;" title="' . esc_attr__('Delete Message', 'wizard-ai') . '"><span class="dashicons dashicons-trash"></span></a>';
                    echo '</div>';
                    echo '<strong>' . wp_kses_post($author_name_html) . '</strong> <span style="font-size: 11px; color: #888;">(' . esc_html($c->comment_date) . ')</span>';
                    echo '<div style="margin-top: 8px;">' . wp_kses_post($c->comment_content) . '</div>';
                    echo '</div>';
                }
                echo '</div>';
                
                $manual_mode = get_transient('wai_chatbot_manual_' . $session_id);
                
                echo '<hr>';
                echo '<h4>' . esc_html__('Operator Takeover', 'wizard-ai') . '</h4>';
                echo '<p>';
                echo '<label><input type="checkbox" id="wai_manual_mode_toggle" data-session="' . esc_attr($session_id) . '" ' . checked($manual_mode, true, false) . '> ' . esc_html__('Enable Manual Mode (AI will stop replying)', 'wizard-ai') . '</label>';
                echo '</p>';
                
                echo '<div id="wai_operator_chat_area" style="display: ' . ($manual_mode ? 'block' : 'none') . ';">';
                echo '<textarea id="wai_operator_message" style="width: 100%; height: 80px;" placeholder="' . esc_attr__('Type your message here...', 'wizard-ai') . '"></textarea>';
                echo '<br><button id="wai_operator_send_btn" class="button button-primary" style="margin-top: 10px;" data-session="' . esc_attr($session_id) . '">' . esc_html__('Send Message', 'wizard-ai') . '</button>';
                echo '</div>';
                ?>
                <script>
                jQuery(document).ready(function($) {
                    $('#wai_manual_mode_toggle').on('change', function() {
                        var isChecked = $(this).is(':checked');
                        var sessionId = $(this).data('session');
                        if (isChecked) {
                            $('#wai_operator_chat_area').slideDown();
                        } else {
                            $('#wai_operator_chat_area').slideUp();
                        }
                        
                        $.ajax({
                            url: '<?php echo esc_url_raw(rest_url('wizard-ai/v1/chatbot/toggle-manual')); ?>',
                            method: 'POST',
                            data: { session_id: sessionId, manual: isChecked ? 1 : 0 },
                            beforeSend: function(xhr) {
                                xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>');
                            }
                        });
                    });
                    
                    $('#wai_operator_send_btn').on('click', function(e) {
                        e.preventDefault();
                        var btn = $(this);
                        var sessionId = btn.data('session');
                        var msg = $('#wai_operator_message').val().trim();
                        if (!msg) return;
                        
                        btn.prop('disabled', true).text('<?php echo esc_js(__('Sending...', 'wizard-ai')); ?>');
                        $.ajax({
                            url: '<?php echo esc_url_raw(rest_url('wizard-ai/v1/chatbot/operator-send')); ?>',
                            method: 'POST',
                            data: { session_id: sessionId, message: msg },
                            beforeSend: function(xhr) {
                                xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>');
                            },
                            success: function(response) {
                                btn.prop('disabled', false).text('<?php echo esc_js(__('Send Message', 'wizard-ai')); ?>');
                                if (response.success) {
                                    $('#wai_operator_message').val('');
                                    // Reload just the container via AJAX
                                    $.get(window.location.href, function(html) {
                                        var newContainer = $(html).find('#wai_chat_history_container');
                                        if (newContainer.length) {
                                            $('#wai_chat_history_container').replaceWith(newContainer);
                                        }
                                    });
                                } else {
                                    alert(response.message || 'Error sending message.');
                                }
                            },
                            error: function() {
                                btn.prop('disabled', false).text('<?php echo esc_js(__('Send Message', 'wizard-ai')); ?>');
                                alert('<?php echo esc_js(__('Failed to communicate with server.', 'wizard-ai')); ?>');
                            }
                        });
                    });
                    
                    // Simple Polling for backend view to see live user messages
                    setInterval(function() {
                        var lastDateStr = $('.wai-chat-message-row').last().data('date');
                        if (!lastDateStr) return;
                        
                        $.ajax({
                            url: '<?php echo esc_url_raw(rest_url('wizard-ai/v1/chatbot/poll')); ?>',
                            method: 'POST',
                            data: { session_id: '<?php echo esc_js($session_id); ?>', last_time: lastDateStr },
                            beforeSend: function(xhr) {
                                xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>');
                            },
                            success: function(response) {
                                if (response.success && response.messages && response.messages.length > 0) {
                                    $.get(window.location.href, function(html) {
                                        var newContainer = $(html).find('#wai_chat_history_container');
                                        if (newContainer.length) {
                                            $('#wai_chat_history_container').replaceWith(newContainer);
                                        }
                                    });
                                }
                            }
                        });
                    }, 5000);
                });
                </script>
                <?php
                echo '</div>';
            }
        } else {
            // List Sessions
            echo '<h1 class="wp-heading-inline">' . esc_html__('Chatbot Logs', 'wizard-ai') . '</h1>';
            
            $search_val = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
            if (!empty($_GET['filter_email']) || !empty($_GET['filter_ip']) || !empty($search_val)) {
                echo ' <a href="' . esc_url(admin_url('admin.php?page=wizard-ai-chatbot-logs')) . '" class="page-title-action">' . esc_html__('Clear Filter', 'wizard-ai') . '</a>';
            }
            
            // Search Form
            echo '<form method="get" style="float: right;">';
            echo '<input type="hidden" name="page" value="wizard-ai-chatbot-logs">';
            echo '<p class="search-box">';
            echo '<label class="screen-reader-text" for="post-search-input">' . esc_html__('Search Users', 'wizard-ai') . ':</label>';
            echo '<input type="search" id="post-search-input" name="s" value="' . esc_attr($search_val) . '" placeholder="' . esc_attr__('Search User/Email', 'wizard-ai') . '">';
            echo '<input type="submit" id="search-submit" class="button" value="' . esc_html__('Search Sessions', 'wizard-ai') . '">';
            echo '</p>';
            echo '</form>';
            
            echo '<hr class="wp-header-end">';
            
            $per_page = 20;
            $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
            $offset = ($paged - 1) * $per_page;
            
            $where_clause = "c.comment_type = 'wai_chat' AND m.meta_key = 'wai_session_id'";
            $having_clause = "HAVING MAX(CASE WHEN c.comment_author != 'Wizard AI' THEN 1 ELSE 0 END) = 1 AND m.meta_value != ''";
            
            $op_like = 'Operator%';
            if (!empty($_GET['filter_email'])) {
                $having_clause .= $wpdb->prepare(" AND MAX(CASE WHEN c.comment_author != 'Wizard AI' AND c.comment_author NOT LIKE %s THEN c.comment_author_email ELSE NULL END) = %s", $op_like, sanitize_text_field($_GET['filter_email']));
            } else if (!empty($_GET['filter_ip'])) {
                $having_clause .= $wpdb->prepare(" AND MAX(CASE WHEN c.comment_author != 'Wizard AI' AND c.comment_author NOT LIKE %s THEN c.comment_author_IP ELSE NULL END) = %s", $op_like, sanitize_text_field($_GET['filter_ip']));
            }
            
            if (!empty($search_val)) {
                $like_val = '%' . $wpdb->esc_like($search_val) . '%';
                $having_clause .= $wpdb->prepare(" AND (
                    MAX(CASE WHEN c.comment_author != 'Wizard AI' AND c.comment_author NOT LIKE %s THEN c.comment_author ELSE NULL END) LIKE %s
                    OR 
                    MAX(CASE WHEN c.comment_author != 'Wizard AI' AND c.comment_author NOT LIKE %s THEN c.comment_author_email ELSE NULL END) LIKE %s
                )", $op_like, $like_val, $op_like, $like_val);
            }

            // Get unique session IDs using SQL since WP_Comment_Query doesn't support GROUP BY meta_value natively
            $query = "
                SELECT m.meta_value AS session_id, 
                       MAX(c.comment_date) AS last_activity, 
                        MAX(CASE WHEN c.comment_author != 'Wizard AI' AND c.comment_author NOT LIKE %s THEN c.comment_author_email ELSE NULL END) AS comment_author_email, 
                        MAX(CASE WHEN c.comment_author != 'Wizard AI' AND c.comment_author NOT LIKE %s THEN c.comment_author ELSE NULL END) AS comment_author,
                        MAX(CASE WHEN c.comment_author != 'Wizard AI' AND c.comment_author NOT LIKE %s THEN c.user_id ELSE 0 END) AS user_id,
                        MAX(CASE WHEN c.comment_author != 'Wizard AI' AND c.comment_author NOT LIKE %s THEN c.comment_author_IP ELSE NULL END) AS comment_author_IP,
                        MAX(CASE WHEN c.comment_author != 'Wizard AI' AND c.comment_author NOT LIKE %s THEN c.comment_agent ELSE NULL END) AS comment_agent
                 FROM {$wpdb->comments} c 
                 INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id 
                 WHERE {$where_clause} 
                 GROUP BY m.meta_value 
                 {$having_clause}
                 ORDER BY last_activity DESC 
                 LIMIT %d OFFSET %d
             ";
             // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
             $sessions = $wpdb->get_results($wpdb->prepare($query, $op_like, $op_like, $op_like, $op_like, $op_like, $per_page, $offset));

            $total_query = "
                SELECT COUNT(*) FROM (
                    SELECT m.meta_value 
                    FROM {$wpdb->comments} c 
                    INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id 
                    WHERE {$where_clause}
                    GROUP BY m.meta_value
                    {$having_clause}
                ) AS count_table
            ";
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $total_sessions = $wpdb->get_var($total_query);
            $total_pages = ceil($total_sessions / $per_page);

            if (empty($sessions)) {
                echo '<p>' . esc_html__('No chat sessions found.', 'wizard-ai') . '</p>';
            } else {
                echo '<form method="post" action="">';
                wp_nonce_field('wai_bulk_delete_logs');
                echo '<div class="tablenav top">';
                echo '<div class="alignleft actions bulkactions">';
                echo '<select name="action">';
                echo '<option value="-1">' . esc_html__('Bulk actions', 'wizard-ai') . '</option>';
                echo '<option value="bulk_delete">' . esc_html__('Delete', 'wizard-ai') . '</option>';
                echo '</select>';
                echo '<input type="submit" class="button action" value="' . esc_html__('Apply', 'wizard-ai') . '" onclick="if(document.querySelector(\'select[name=action]\').value === \'bulk_delete\'){ return confirm(\'' . esc_js(__('Are you sure you want to delete selected sessions?', 'wizard-ai')) . '\'); } return true;">';
                echo '</div>';
                echo '</div>';

                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr>';
                echo '<td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-1"></td>';
                echo '<th>' . esc_html__('Session ID', 'wizard-ai') . '</th>';
                echo '<th>' . esc_html__('User', 'wizard-ai') . '</th>';
                echo '<th>' . esc_html__('Email', 'wizard-ai') . '</th>';
                echo '<th>' . esc_html__('IP', 'wizard-ai') . '</th>';
                echo '<th>' . esc_html__('Browser', 'wizard-ai') . '</th>';
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
                    echo '<th scope="row" class="check-column"><input type="checkbox" name="session_ids[]" value="' . esc_attr($s->session_id) . '"></th>';
                    echo '<td><strong><a href="' . esc_url($view_url) . '">' . esc_html($s->session_id) . '</a></strong></td>';
                    echo '<td>' . wp_kses_post($display_name_html) . '</td>';
                    echo '<td>' . esc_html($s->comment_author_email) . '</td>';
                    echo '<td>' . esc_html($s->comment_author_IP) . '</td>';
                    echo '<td style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="' . esc_attr($s->comment_agent) . '">' . esc_html($s->comment_agent) . '</td>';
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
                        echo '<div class="tablenav"><div class="tablenav-pages" style="float:left; margin-top:10px;">' . wp_kses_post($page_links) . '</div></div>';
                    }
                }
                echo '</form>';
            }
        }
        
        // Add new activity alert logic
        ?>
        <div id="wai-new-activity-alert" class="notice notice-info is-dismissible" style="display:none; border-left-color: #2271b1;">
            <p><strong><?php esc_html_e('New chat activity detected!', 'wizard-ai'); ?></strong> <a href="javascript:void(0)" onclick="window.location.reload();" style="margin-left: 10px; color: #2271b1; text-decoration: underline; font-weight: 500;"><?php esc_html_e('Refresh page to see updates', 'wizard-ai'); ?></a></p>
        </div>
        <script>
        jQuery(document).ready(function($) {
            var checkInterval = 10000; // Check every 10 seconds
            var lastCheck = '<?php echo esc_js(gmdate('Y-m-d H:i:s')); ?>';
            
            // Insert alert dynamically after header
            if ($('.wp-header-end').length) {
                $('#wai-new-activity-alert').insertAfter('.wp-header-end');
            } else {
                $('#wai-new-activity-alert').prependTo('.wrap');
            }
            
            setInterval(function() {
                // If the alert is already visible, don't keep polling
                if ($('#wai-new-activity-alert').is(':visible')) {
                    return;
                }
                
                $.ajax({
                    url: '<?php echo esc_url_raw(rest_url('wizard-ai/v1/chatbot/check-new-activity')); ?>',
                    method: 'POST',
                    data: { last_check: lastCheck },
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>');
                    },
                    success: function(response) {
                        if (response.success && response.has_new) {
                            $('#wai-new-activity-alert').fadeIn();
                        }
                    }
                });
            }, checkInterval);
        });
        </script>
        <?php
        
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
            $chat_transcript .= "{$author}: " . wp_strip_all_tags($c->comment_content) . "\n";
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
            
            $configured_model = get_option('wai_chatbot_model', '');
            if ($configured_model && strpos($configured_model, '|') !== false) {
                list($selectedProvider, $selectedModel) = explode('|', $configured_model);
                $ai_query->usingModelPreference([$selectedProvider, $selectedModel]);
            }
            
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

    public function handle_chatbot_toggle_manual(\WP_REST_Request $request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $manual = (bool)$request->get_param('manual');
        if (empty($session_id)) {
            return new \WP_REST_Response(['success' => false, 'message' => __('Missing session ID.', 'wizard-ai')], 400);
        }

        if ($manual) {
            set_transient('wai_chatbot_manual_' . $session_id, 1, 12 * HOUR_IN_SECONDS);
        } else {
            delete_transient('wai_chatbot_manual_' . $session_id);
        }

        return new \WP_REST_Response(['success' => true], 200);
    }

    public function handle_chatbot_operator_send(\WP_REST_Request $request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $message = sanitize_text_field($request->get_param('message'));

        if (empty($session_id) || empty($message)) {
            return new \WP_REST_Response(['success' => false, 'message' => __('Missing parameters.', 'wizard-ai')], 400);
        }

        $current_user = wp_get_current_user();
        $author_name = 'Operator (' . $current_user->display_name . ')';

        global $wpdb;
        $post_id = (int) $wpdb->get_var($wpdb->prepare("
            SELECT c.comment_post_ID 
            FROM {$wpdb->comments} c
            INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id
            WHERE m.meta_key = 'wai_session_id' AND m.meta_value = %s
            LIMIT 1
        ", $session_id));

        $comment_id = wp_insert_comment([
            'comment_post_ID' => $post_id,
            'comment_author' => wp_slash($author_name),
            'comment_author_email' => $current_user->user_email,
            'user_id' => $current_user->ID,
            'comment_content' => wp_slash($message),
            'comment_type' => 'wai_chat',
            'comment_approved' => 1
        ]);

        if ($comment_id) {
            update_comment_meta($comment_id, 'wai_session_id', $session_id);
            update_comment_meta($comment_id, 'wai_chat_log', 1);
            return new \WP_REST_Response(['success' => true, 'comment_id' => $comment_id], 200);
        }

        return new \WP_REST_Response(['success' => false, 'message' => __('Failed to insert message.', 'wizard-ai')], 500);
    }
}
