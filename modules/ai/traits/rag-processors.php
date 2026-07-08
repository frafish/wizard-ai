<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait WizardAI_RAG_Processors {
    
    private function cleanup_deleted_objects() {
        global $wpdb;
        
        $stmt = $this->db->query("SELECT DISTINCT post_id, post_type FROM document_embeddings");
        $embedded_objects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($embedded_objects)) {
            return;
        }

        $deleted_objects = [];
        $categorized = [];
        
        foreach ($embedded_objects as $obj) {
            $categorized[$obj['post_type']][] = $obj['post_id'];
        }

        foreach ($categorized as $type => $ids) {
            $chunked_ids = array_chunk($ids, 100);
            
            if (in_array($type, ['term_category', 'term_post_tag', 'term_product_cat'])) {
                foreach ($chunked_ids as $id_chunk) {
                    $placeholders = implode(',', array_fill(0, count($id_chunk), '%d'));
                    $query = $wpdb->prepare("SELECT term_id FROM {$wpdb->terms} WHERE term_id IN ({$placeholders})", ...$id_chunk);
                    $valid_ids = $wpdb->get_col($query);
                    $missing = array_diff($id_chunk, $valid_ids);
                    foreach ($missing as $m_id) $deleted_objects[] = ['id' => $m_id, 'type' => $type];
                }
            } elseif ($type === 'global_setting' || strpos($type, 'plugin_api_') === 0) {
                // Settings and plugins don't get deleted here, they update or get cleaned up during processing
            } else {
                // Regular Post Types (post, page, product, block)
                foreach ($chunked_ids as $id_chunk) {
                    $placeholders = implode(',', array_fill(0, count($id_chunk), '%d'));
                    $query = $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID IN ({$placeholders}) AND post_status = 'publish'", ...$id_chunk);
                    $valid_ids = $wpdb->get_col($query);
                    $missing = array_diff($id_chunk, $valid_ids);
                    foreach ($missing as $m_id) $deleted_objects[] = ['id' => $m_id, 'type' => $type];
                }
            }
        }

        if (!empty($deleted_objects)) {
            $this->log("Cleaning up " . count($deleted_objects) . " deleted or unpublished objects from vector DB.");
            foreach ($deleted_objects as $del) {
                $del_stmt = $this->db->prepare("DELETE FROM document_embeddings WHERE post_id = ? AND post_type = ?");
                $del_stmt->execute([$del['id'], $del['type']]);
            }
        }
    }

    private function process_posts() {
        global $wpdb;
        
        $types_to_sync = [];
        if (get_option('wai_rag_sync_contents', 1) == 1) {
            $types_to_sync = array_merge($types_to_sync, ['post', 'page', 'block', 'knowledgebase']);
        }
        if (get_option('wai_rag_sync_products', 1) == 1) {
            $types_to_sync[] = 'product';
        }
        
        if (empty($types_to_sync)) return;
        
        $types_sql = "'" . implode("', '", $types_to_sync) . "'";
        $query = "
            SELECT ID, post_modified_gmt 
            FROM {$wpdb->posts} 
            WHERE post_type IN ({$types_sql})
            AND post_status = 'publish'
            ORDER BY post_modified_gmt DESC
        ";
        
        $posts = $wpdb->get_results($query);
        $processed_count = 0;
        $updated_count = 0;

        foreach ($posts as $post) {
            if ($processed_count >= $this->batch_size) break;

            $post_id = $post->ID;
            $wp_post = get_post($post_id);
            
            $content = $this->extract_post_content($post_id);
            
            if (empty($content)) continue;
            
            $content_hash = md5($content . $wp_post->post_title);
            
            $stmt = $this->db->prepare("SELECT content_hash FROM document_embeddings WHERE post_id = :post_id AND post_type = :post_type LIMIT 1");
            $stmt->execute([':post_id' => $post_id, ':post_type' => $wp_post->post_type]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing && $existing['content_hash'] === $content_hash) continue;

            $this->log("Updating Post ID: {$post_id} - {$wp_post->post_title} ({$wp_post->post_type})");

            if ($existing) {
                $del_stmt = $this->db->prepare("DELETE FROM document_embeddings WHERE post_id = :post_id AND post_type = :post_type");
                $del_stmt->execute([':post_id' => $post_id, ':post_type' => $wp_post->post_type]);
            }

            echo "Length: " . strlen($content) . "\n"; $this->insert_chunks($post_id, $wp_post->post_type, $wp_post->post_title, get_permalink($post_id), $content, $content_hash);
            
            $processed_count++;
            $updated_count++;
            clean_post_cache($wp_post);
        }
        $this->log("Processed {$updated_count} advanced posts/products this run.");
    }
    
    private function process_terms() {
        $taxonomies = get_taxonomies(['public' => true]);
        $terms = get_terms([
            'taxonomy' => $taxonomies,
            'hide_empty' => true,
        ]);
        
        $updated_count = 0;
        foreach ($terms as $term) {
            $content = "Taxonomy: {$term->taxonomy}\nName: {$term->name}\nDescription: " . wp_strip_all_tags($term->description);
            $content_hash = md5($content);
            $type = 'term_' . $term->taxonomy;
            
            $stmt = $this->db->prepare("SELECT content_hash FROM document_embeddings WHERE post_id = :post_id AND post_type = :post_type LIMIT 1");
            $stmt->execute([':post_id' => $term->term_id, ':post_type' => $type]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing && $existing['content_hash'] === $content_hash) continue;

            if ($existing) {
                $del_stmt = $this->db->prepare("DELETE FROM document_embeddings WHERE post_id = :post_id AND post_type = :post_type");
                $del_stmt->execute([':post_id' => $term->term_id, ':post_type' => $type]);
            }

            $this->log("Updating Term ID: {$term->term_id} - {$term->name} ({$term->taxonomy})");
            echo "Length: " . strlen($content) . "\n"; $this->insert_chunks($term->term_id, $type, 'Taxonomy: ' . $term->name, get_term_link($term), $content, $content_hash);
            $updated_count++;
        }
        $this->log("Processed {$updated_count} taxonomies this run.");
    }
    
    private function process_settings() {
        $site_name = get_bloginfo('name');
        $site_description = get_bloginfo('description');
        $active_plugins = get_option('active_plugins', []);
        
        $content = "Site Name: {$site_name}\nSite Description: {$site_description}\nActive Plugins: " . implode(', ', $active_plugins);
        $content_hash = md5($content);
        
        $stmt = $this->db->prepare("SELECT content_hash FROM document_embeddings WHERE post_type = 'global_setting' LIMIT 1");
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing && $existing['content_hash'] === $content_hash) return;
        
        if ($existing) {
            $this->db->query("DELETE FROM document_embeddings WHERE post_type = 'global_setting'");
        }
        
        $this->log("Updating Global Site Settings Context");
        echo "Length: " . strlen($content) . "\n"; $this->insert_chunks(1, 'global_setting', 'Global Site Configuration', home_url(), $content, $content_hash);
    }
    
    private function process_plugins_apis() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);
        
        // Clean up deactivated plugins
        $stmt = $this->db->query("SELECT DISTINCT post_type FROM document_embeddings WHERE post_type LIKE 'plugin_api_%'");
        if ($stmt) {
            $existing_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $active_plugin_dirs = [];
            foreach ($active_plugins as $p) {
                $active_plugin_dirs[] = explode('/', $p)[0];
            }
            foreach ($existing_types as $type) {
                $dir = str_replace('plugin_api_', '', $type);
                if (!in_array($dir, $active_plugin_dirs)) {
                    $this->db->query("DELETE FROM document_embeddings WHERE post_type = '{$type}'");
                }
            }
        }

        $processed_count = 0;

        foreach ($active_plugins as $plugin_path) {
            $plugin_dir = explode('/', $plugin_path)[0];
            $plugin_name = isset($all_plugins[$plugin_path]['Name']) ? $all_plugins[$plugin_path]['Name'] : $plugin_dir;
            
            $post_type = 'plugin_api_' . $plugin_dir;
            $plugin_full_dir = WP_PLUGIN_DIR . '/' . $plugin_dir;
            
            if (!is_dir($plugin_full_dir)) continue;

            $apis = [
                'hooks' => [],
                'classes' => [],
                'functions' => []
            ];
            
            try {
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_full_dir, RecursiveDirectoryIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $content = file_get_contents($file->getPathname());
                        if (!$content) continue;
                        
                        // Match hooks
                        if (preg_match_all('/(?:do_action|apply_filters)\s*\(\s*[\'"]([a-zA-Z0-9_.-]+)[\'"]/', $content, $matches)) {
                            foreach ($matches[1] as $hook_name) {
                                $apis['hooks'][$hook_name] = "Hook: {$hook_name}";
                            }
                        }
                        
                        // Match classes
                        if (preg_match_all('/(?:^|\s)class\s+([a-zA-Z0-9_]+)/', $content, $matches)) {
                            foreach ($matches[1] as $class_name) {
                                $apis['classes'][$class_name] = "class {$class_name}";
                            }
                        }
                        
                        // Match functions
                        if (preg_match_all('/(?:^|\s)function\s+([a-zA-Z0-9_]+)\s*\(([^)]*)\)/', $content, $matches)) {
                            foreach ($matches[1] as $idx => $func_name) {
                                $apis['functions'][$func_name] = "function {$func_name}(" . trim($matches[2][$idx]) . ")";
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Ignore directory iteration errors
            }
            
            $flat_apis = [];
            $flat_apis = array_merge($flat_apis, array_slice(array_values($apis['hooks']), 0, 800));
            $flat_apis = array_merge($flat_apis, array_slice(array_values($apis['classes']), 0, 400));
            $flat_apis = array_merge($flat_apis, array_slice(array_values($apis['functions']), 0, 1000));
            
            if (empty($flat_apis)) continue;
            
            $content = "Plugin API Reference for: {$plugin_name}\n\n" . implode("\n", $flat_apis);
            $content_hash = md5($content);
            
            $stmt = $this->db->prepare("SELECT content_hash FROM document_embeddings WHERE post_type = :post_type LIMIT 1");
            $stmt->execute([':post_type' => $post_type]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing && $existing['content_hash'] === $content_hash) {
                continue;
            }
            
            if ($existing) {
                $this->db->query("DELETE FROM document_embeddings WHERE post_type = '{$post_type}'");
            }
            
            $this->log("Updating Plugin API Context: {$plugin_name}");
            $this->insert_chunks(1, $post_type, "Plugin API: {$plugin_name}", home_url(), $content, $content_hash);
            $processed_count++;
        }
        
        $this->log("Processed {$processed_count} plugin APIs this run.");
    }

    private function export_to_json() {
        $this->log("Exporting vectors to JSON file...");
        $stmt = $this->db->query("SELECT post_id, chunk_index, post_type, post_title, post_url, text_content, embedding FROM document_embeddings");
        
        $data = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $row['embedding'] = json_decode($row['embedding'], true);
            
            // Dynamic WooCommerce Enrichment
            if ($row['post_type'] === 'product' && function_exists('wc_get_product')) {
                $product = wc_get_product($row['post_id']);
                if ($product) {
                    $row['text_content'] .= "\n[Live Data] Product Price: " . $product->get_price();
                    $row['text_content'] .= "\n[Live Data] Product SKU: " . $product->get_sku();
                    $row['text_content'] .= "\n[Live Data] Stock Status: " . $product->get_stock_status();
                }
            }
            
            $data[] = $row;
        }

        $json_path = $this->db_dir . '/rag.json';
        $json_content = wp_json_encode($data);
        
        if (file_put_contents($json_path, $json_content) !== false) {
            $this->log("Successfully exported " . count($data) . " vectors to {$json_path}");
        } else {
            $this->log("Failed to export vectors to {$json_path}");
        }
    }
}
