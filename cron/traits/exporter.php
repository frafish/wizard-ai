<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait WizardAI_RAG_Exporter {

    private function export_to_json() {
        $this->log("Exporting vectors to JSON file...");
        $stmt = $this->db->query("SELECT post_id, chunk_index, post_type, post_title, post_url, text_content, embedding FROM document_embeddings");
        
        $data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Decode the JSON embedding back to an array for the final JSON structure
            $row['embedding'] = json_decode($row['embedding'], true);
            $data[] = $row;
        }

        $json_path = $this->db_dir . '/rag_embeddings.json';
        $json_content = wp_json_encode($data);
        
        if (file_put_contents($json_path, $json_content) !== false) {
            $this->log("Successfully exported " . count($data) . " vectors to {$json_path}");
        } else {
            $this->log("Failed to export vectors to {$json_path}");
        }
    }
}
