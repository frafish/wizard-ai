<?php

namespace WizardAi\Modules\Ai\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait RAG_Embeddings {

    private function insert_chunks($object_id, $object_type, $title, $url, $content, $content_hash) {
        $chunks = $this->chunk_text($content, $this->chunk_size);
        foreach ($chunks as $index => $chunk) {
            try {
                if ($this->provider === 'huggingface') {
                    $embedding = $this->get_huggingface_embedding($chunk);
                } elseif ($this->provider === 'openai') {
                    $embedding = $this->get_openai_embedding($chunk);
                } else {
                    $embedding = $this->get_gemini_embedding($chunk);
                }
            } catch (Exception $e) {
                $this->log("Exception getting embedding: " . $e->getMessage());
                $embedding = false;
            }
            
            if ($embedding) {
                try {
                    $insert_stmt = $this->db->prepare("
                        INSERT INTO document_embeddings 
                        (post_id, chunk_index, post_type, post_title, post_url, content_hash, text_content, embedding) 
                        VALUES (:post_id, :chunk_index, :post_type, :post_title, :post_url, :content_hash, :text_content, :embedding)
                    ");
                    $insert_stmt->execute([
                        ':post_id'      => $object_id,
                        ':chunk_index'  => $index,
                        ':post_type'    => $object_type,
                        ':post_title'   => $title,
                        ':post_url'     => is_wp_error($url) ? home_url() : $url,
                        ':content_hash' => $content_hash,
                        ':text_content' => $chunk,
                        ':embedding'    => json_encode($embedding)
                    ]);
                } catch (Exception $e) {
                    $this->log("DB Insert Error: " . $e->getMessage());
                }
            }
            usleep(200000); // 200ms
        }
    }

    private function chunk_text($text, $chunk_size) {
        $words = preg_split('/\s+/', trim($text));
        $chunks = [];
        $current_chunk = '';

        foreach ($words as $word) {
            if (mb_strlen($current_chunk) + mb_strlen($word) + 1 > $chunk_size) {
                if (!empty($current_chunk)) {
                    $chunks[] = trim($current_chunk);
                }
                $current_chunk = $word;
            } else {
                $current_chunk .= (empty($current_chunk) ? '' : ' ') . $word;
            }
        }
        
        if (!empty($current_chunk)) {
            $chunks[] = trim($current_chunk);
        }

        return $chunks;
    }

    private function get_openai_embedding($text) {
        $url = 'https://api.openai.com/v1/embeddings';
        $data = [
            'model' => 'text-embedding-3-small',
            'input' => $text
        ];

        $args = [
            'body'        => json_encode($data),
            'headers'     => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout'     => 15,
            'data_format' => 'body',
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            $this->log("  [API Error] " . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (isset($result['error'])) {
             $this->log("  [API Error] " . $result['error']['message']);
             return false;
        }

        if (isset($result['data'][0]['embedding'])) {
            return $result['data'][0]['embedding'];
        }

        return false;
    }

    private function get_huggingface_embedding($text) {
        // Using all-MiniLM-L6-v2 as the default fast RAG model
        $url = 'https://api-inference.huggingface.co/pipeline/feature-extraction/sentence-transformers/all-MiniLM-L6-v2';
        $data = [
            'inputs' => $text,
            'options' => ['wait_for_model' => true]
        ];

        $args = [
            'body'        => json_encode($data),
            'headers'     => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout'     => 15,
            'data_format' => 'body',
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            $this->log("  [API Error] " . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (isset($result['error'])) {
             $this->log("  [API Error] " . $result['error']);
             return false;
        }

        if (is_array($result) && !empty($result)) {
            return $result;
        }

        return false;
    }

    private function get_gemini_embedding($text) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-2:embedContent?key=' . $this->api_key;
        $data = [
            'model' => 'models/gemini-embedding-2',
            'content' => [
                'parts' => [
                    ['text' => $text]
                ]
            ]
        ];

        $args = [
            'body'        => json_encode($data),
            'headers'     => [
                'Content-Type'  => 'application/json',
            ],
            'timeout'     => 15,
            'data_format' => 'body',
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            $this->log("  [API Error] " . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (isset($result['error'])) {
             $this->log("  [API Error] " . $result['error']['message']);
             return false;
        }

        if (isset($result['embedding']['values'])) {
            return $result['embedding']['values'];
        }

        return false;
    }
}
