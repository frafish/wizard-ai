<?php
/**
 * Cron script to generate and store embeddings for WordPress content.
 * Incremental RAG Vector DB Update.
 * 
 * Usage: php cron/rag.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    // If running as standalone script, try to bootstrap WP
    $wp_load_path = dirname(__FILE__, 5) . '/wp-load.php';
    if (!file_exists($wp_load_path)) {
        die("Error: wp-load.php not found. Must be run within WordPress or provide correct path.\n");
    }
    require_once $wp_load_path;
}

require_once dirname(__FILE__) . '/traits/processors.php';
require_once dirname(__FILE__) . '/traits/embeddings.php';
require_once dirname(__FILE__) . '/traits/exporter.php';

class WizardAI_RAG_Embeddings_Cron {
    use WizardAI_RAG_Processors;
    use WizardAI_RAG_Embeddings;
    use WizardAI_RAG_Exporter;

    private $db;
    private $db_dir;
    private $api_key;
    private $batch_size = 50; 
    private $chunk_size = 1500; 

    private $provider;

    public function __construct() {
        $this->provider = get_option('wai_rag_embedding_provider', '');
        
        if (empty($this->provider)) {
            $error_msg = "Error: No Embedding Provider selected. Please configure a provider in the Wizard AI RAG Settings.";
            if (php_sapi_name() === 'cli' || defined('DOING_CRON')) {
                die($error_msg . "\n");
            } else {
                wp_die($error_msg);
            }
        }
        
        if ($this->provider === 'huggingface') {
            $this->api_key = get_option('connectors_ai_huggingface_api_key', '');
            if (empty($this->api_key)) {
                $error_msg = "Error: HuggingFace API key is missing. Please set it in the WordPress Connectors settings.";
                if (php_sapi_name() === 'cli' || defined('DOING_CRON')) {
                    die($error_msg . "\n");
                } else {
                    wp_die($error_msg);
                }
            }
        } elseif ($this->provider === 'openai') {
            $this->api_key = get_option('connectors_ai_openai_api_key', '');
            if (empty($this->api_key)) {
                $error_msg = "Error: OpenAI API key is missing. Please set it in the WordPress Connectors settings.";
                if (php_sapi_name() === 'cli' || defined('DOING_CRON')) {
                    die($error_msg . "\n");
                } else {
                    wp_die($error_msg);
                }
            }
        } else {
            // Default Gemini
            $this->api_key = get_option('connectors_ai_google_api_key', '');
            if (empty($this->api_key) && defined('GEMINI_API_KEY')) {
                $this->api_key = GEMINI_API_KEY;
            }

            if (empty($this->api_key)) {
                $core_ai_url = admin_url('options-general.php?page=options-connectors-wp-admin');
                $error_msg_cli = "Error: Gemini API key is missing. Please set it in the WordPress Connectors page or define GEMINI_API_KEY in wp-config.php.";
                $error_msg_web = "<h3>Error: Gemini API key is missing</h3>"
                    . "<p>To generate vector embeddings for RAG, you must configure a Gemini API key.</p>"
                    . "<p>You can set it up in one of the following ways:</p>"
                    . "<ul>"
                    . "<li>Use the <a href='" . esc_url($core_ai_url) . "'>WordPress Core AI Connectors</a> settings.</li>"
                    . "<li>Define <code>GEMINI_API_KEY</code> in your <code>wp-config.php</code> file.</li>"
                    . "</ul>"
                    . "<p>If you don't have a key, you can get one for free at <a href='https://aistudio.google.com/app/apikey' target='_blank'>Google AI Studio</a>.</p>";
                
                if (php_sapi_name() === 'cli' || defined('DOING_CRON')) {
                    die($error_msg_cli . "\n");
                } else {
                    wp_die($error_msg_web);
                }
            }
        }

        $this->init_db();
    }

    private function init_db() {
        // Place the SQLite database securely in the plugin's uploads or data directory
        $upload_dir = wp_upload_dir();
        $this->db_dir = $upload_dir['basedir'] . '/wai';
        
        if (!file_exists($this->db_dir)) {
            wp_mkdir_p($this->db_dir);
            // Protect directory from web access
            file_put_contents($this->db_dir . '/.htaccess', "Deny from all\n");
            file_put_contents($this->db_dir . '/index.php', "<?php // Silence is golden.");
        }

        $db_path = $this->db_dir . '/rag.sqlite';
        
        try {
            $this->db = new PDO('sqlite:' . $db_path);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $query = "
                CREATE TABLE IF NOT EXISTS document_embeddings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    post_id INTEGER NOT NULL,
                    chunk_index INTEGER NOT NULL,
                    post_type TEXT NOT NULL,
                    post_title TEXT NOT NULL,
                    post_url TEXT NOT NULL,
                    content_hash TEXT NOT NULL,
                    text_content TEXT NOT NULL,
                    embedding TEXT NOT NULL,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                CREATE INDEX IF NOT EXISTS idx_post_id ON document_embeddings(post_id);
            ";
            $this->db->exec($query);
        } catch (PDOException $e) {
            die("SQLite Connection failed: " . $e->getMessage() . "\n");
        }
    }

    public function run() {
        // Prepare execution limits for batch processing
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        wp_suspend_cache_addition(true);

        $this->log("Starting advanced incremental RAG embeddings update...");
        
        $this->cleanup_deleted_objects();
        
        // Distribute batch limits across different domains
        $this->batch_size = 50; 
        
        if (get_option('wai_rag_sync_contents', 1) == 1 || get_option('wai_rag_sync_products', 1) == 1) {
            $this->process_posts();
        }
        if (get_option('wai_rag_sync_terms', 1) == 1) {
            $this->process_terms();
        }
        if (get_option('wai_rag_sync_settings', 1) == 1) {
            $this->process_settings();
        }
        if (get_option('wai_rag_sync_plugins', 1) == 1) {
            $this->process_plugins_apis();
        }
        
        $this->export_to_json();
        
        $this->log("Update completed.");
    }

    private function log($message) {
        $timestamp = current_time('mysql');
        $log_message = "[{$timestamp}] [Wizard AI RAG] " . $message . "\n";
        
        if (php_sapi_name() === 'cli') {
            echo $log_message;
        }
        
        if (!empty($this->db_dir)) {
            $log_dir = $this->db_dir . '/logs';
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
                file_put_contents($log_dir . '/.htaccess', "Deny from all\n");
                file_put_contents($log_dir . '/index.php', "<?php // Silence is golden.");
            }
            $log_file = $log_dir . '/sync.log';
            file_put_contents($log_file, $log_message, FILE_APPEND);
        }
    }
}

// Support direct CLI execution
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $cron = new WizardAI_RAG_Embeddings_Cron();
    $cron->run();
}
