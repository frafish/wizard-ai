<?php
namespace WizardAi\Modules\Ai\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait AuditLogger {
    private $audit_db = null;

    public function init_audit_db() {
        if (!class_exists('SQLite3')) return false;
        
        $upload_dir = wp_upload_dir();
        $wai_dir = $upload_dir['basedir'] . '/wai';
        
        if (!file_exists($wai_dir)) {
            wp_mkdir_p($wai_dir);
        }
        
        $db_path = $wai_dir . '/audit.sqlite';
        $is_new = !file_exists($db_path);
        
        try {
            $this->audit_db = new \SQLite3($db_path);
            $this->audit_db->busyTimeout(5000);
            
            if ($is_new) {
                $this->audit_db->exec('
                    CREATE TABLE IF NOT EXISTS audit_logs (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                        context TEXT NOT NULL,
                        tool_name TEXT NOT NULL,
                        parameters TEXT NOT NULL,
                        status TEXT NOT NULL,
                        error_msg TEXT DEFAULT NULL
                    )
                ');
                $this->audit_db->exec('CREATE INDEX IF NOT EXISTS idx_timestamp ON audit_logs(timestamp)');
                $this->audit_db->exec('CREATE INDEX IF NOT EXISTS idx_context ON audit_logs(context)');
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function log_audit_event($context, $tool_name, $parameters, $status, $error_msg = null) {
        if (!$this->audit_db && !$this->init_audit_db()) {
            return false;
        }

        try {
            $stmt = $this->audit_db->prepare('INSERT INTO audit_logs (context, tool_name, parameters, status, error_msg) VALUES (:context, :tool, :params, :status, :error)');
            if ($stmt) {
                $stmt->bindValue(':context', $context, SQLITE3_TEXT);
                $stmt->bindValue(':tool', $tool_name, SQLITE3_TEXT);
                $stmt->bindValue(':params', is_array($parameters) || is_object($parameters) ? wp_json_encode($parameters) : $parameters, SQLITE3_TEXT);
                $stmt->bindValue(':status', $status, SQLITE3_TEXT);
                $stmt->bindValue(':error', $error_msg, SQLITE3_TEXT);
                $stmt->execute();
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }
    
    public function get_audit_logs($limit = 100, $offset = 0) {
        if (!$this->audit_db && !$this->init_audit_db()) {
            return [];
        }
        
        $results = [];
        try {
            $res = $this->audit_db->query("SELECT * FROM audit_logs ORDER BY timestamp DESC LIMIT $limit OFFSET $offset");
            if ($res) {
                while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                    $results[] = $row;
                }
            }
        } catch (\Exception $e) {}
        return $results;
    }
}
