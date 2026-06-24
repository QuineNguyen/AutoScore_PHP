<?php
require_once __DIR__ . '/../models/config.php';

/**
 * Class DocumentSyncService
 * Đồng bộ nội dung tài liệu tham khảo lên AI Server (vector DB)
 */
class DocumentSyncService {
    private $baseUrl = 'http://127.0.0.1:8000';
    
    /**
     * ========================================
     * SYNC TÀI LIỆU THAM KHẢO (RIÊNG BIỆT)
     * ========================================
     * Đồng bộ tài liệu tham khảo lên Vector DB
     * Endpoint: /webhook/sync-documents
     * 
     * @param array $documentsContent Array of documents with keys: title, content
     * @return array ['success' => bool, 'response' => mixed, 'error' => string]
     */
    public function syncDocuments($documentsContent) {
        try {
            $documents = [];
            foreach ($documentsContent as $doc) {
                $documents[] = [
                    'filename' => $doc['title'] ?? $doc['filename'] ?? 'unknown',
                    'content' => $doc['content'] ?? ''
                ];
            }

            $payload = [
                'action' => 'sync_documents',
                'documents' => $documents,
                'synced_at' => date('Y-m-d H:i:s'),
                'source' => 'autoscore_system'
            ];

            return $this->sendRequest('/webhook/sync-documents', $payload);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'response' => null,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ========================================
     * TẠO CÂU HỎI (RIÊNG BIỆT)
     * ========================================
     * Gửi thông tin câu hỏi lên AI Server
     * Endpoint: /webhook/create-question
     * 
     * @param int $questionId
     * @param string $question
     * @param string|null $modelAnswer
     * @param string $strategy
     * @param float $maxScore
     * @return array ['success' => bool, 'response' => mixed, 'error' => string]
     */
    public function createQuestion($questionId, $question, $modelAnswer, $strategy, $maxScore) {
        try {
            $payload = [
                'action' => 'create_question',
                'question_id' => strval($questionId),
                'question_text' => $question ?? '',
                'model_answer' => $modelAnswer ?? '',
                'grading_strategy' => $strategy ?? '',
                'max_score' => intval($maxScore),
                'synced_at' => date('Y-m-d H:i:s'),
                'source' => 'autoscore_system'
            ];

            return $this->sendRequest('/webhook/create-question', $payload);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'response' => null,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ========================================
     * LEGACY: SYNC CÂU HỎI + TÀI LIỆU CÙNG LÚC
     * ========================================
     * Giữ lại để backward compatibility
     * Endpoint: /webhook/sync-question
     * 
     * @param int $questionId
     * @param string $question
     * @param string|null $modelAnswer
     * @param array $documentsContent
     * @param string $strategy
     * @param float $maxScore
     * @return array
     */
    public function syncQuestion($questionId, $question, $modelAnswer, $documentsContent, $strategy, $maxScore) {
        try {
            $referenceDocuments = [];
            foreach ($documentsContent as $doc) {
                $referenceDocuments[] = [
                    'filename' => $doc['title'] ?? $doc['filename'] ?? 'unknown',
                    'content' => $doc['content'] ?? ''
                ];
            }

            $payload = [
                'action' => 'create_question',
                'question_id' => strval($questionId),
                'question_text' => $question ?? '',
                'model_answer' => $modelAnswer ?? '',
                'grading_strategy' => $strategy ?? '',
                'max_score' => intval($maxScore),
                'reference_documents' => $referenceDocuments,
                'synced_at' => date('Y-m-d H:i:s'),
                'source' => 'autoscore_system'
            ];

            return $this->sendRequest('/webhook/sync-question', $payload);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'response' => null,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ========================================
     * RAG SEARCH: Tìm kiếm tài liệu tham khảo
     * ========================================
     * Embedding câu hỏi và tìm các chunks tương đồng trong Vector DB
     * Endpoint: /webhook/rag-search
     * 
     * Quy trình:
     * 1. Gửi câu hỏi đến AI Server
     * 2. Server embedding câu hỏi thành vector
     * 3. Tìm top_k chunks có vector tương đồng nhất
     * 4. Ghép content thành answer và trả về
     * 
     * @param string $questionText Nội dung câu hỏi cần tìm kiếm
     * @param int $topK Số lượng chunks tương đồng nhất cần lấy (mặc định 5)
     * @return array ['success' => bool, 'response' => ['generated_answer' => string, ...], 'error' => string]
     */
    public function ragSearch($questionText, $topK = 5) {
        try {
            $payload = [
                'question_text' => $questionText,
                'top_k' => intval($topK)
            ];

            return $this->sendRequest('/webhook/rag-search', $payload);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'response' => null,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Gửi HTTP request đến AI Server
     * 
     * @param string $endpoint
     * @param array $payload
     * @return array
     */
    private function sendRequest($endpoint, $payload) {
        $url = $this->baseUrl . $endpoint;
        
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        
        if ($jsonPayload === false) {
            return [
                'success' => false,
                'response' => null,
                'error' => 'Failed to encode JSON: ' . json_last_error_msg()
            ];
        }
        
        error_log("DEBUG: Sending to $endpoint: " . mb_substr($jsonPayload, 0, 500));
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 phút cho embedding
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        if ($error) {
            return [
                'success' => false,
                'response' => null,
                'error' => 'cURL error: ' . $error
            ];
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $responseData = json_decode($response, true);
            return [
                'success' => true,
                'response' => $responseData,
                'error' => ''
            ];
        } else {
            return [
                'success' => false,
                'response' => $response,
                'error' => "HTTP $httpCode: $response"
            ];
        }
    }
}
?>
