<?php
require_once __DIR__ . '/../models/config.php';
require_once __DIR__ . '/DocumentManager.php';
require_once __DIR__ . '/../services/DocumentSyncService.php';
require_once __DIR__ . '/../utils/PDFExtractor.php';

/**
 * Class QuestionManager
 * Quản lý câu hỏi tự luận với logic chấm điểm tự động
 */
class QuestionManager {
    private $pdo;
    private $documentManager;
    private $syncService;
    
    public function __construct() {
        $this->pdo = Config::getConnection();
        $this->documentManager = new DocumentManager();
        $this->syncService = new DocumentSyncService();
    }
    
    /**
     * ========================================
     * FUNCTION CORE: createQuestion
     * ========================================
     * 
     * Tạo câu hỏi tự luận mới với logic điều kiện chấm điểm tự động
     * 
     * @param string $question Nội dung câu hỏi (Bắt buộc)
     * @param string|null $answer Đáp án mẫu dạng text (Optional)
     * @param array|null $answerFile File PDF chứa đáp án ['tmp_name', 'name', 'size'] (Optional)
     * @param float $maxScore Điểm tối đa (Default: 10)
     * @return array ['success' => bool, 'question_id' => int, 'strategy' => string, 'error' => string]
     * @throws Exception
     */
    public function createQuestion($question, $answer = null, $answerFile = null, $maxScore = 10.00) {
        try {
            if (empty(trim($question))) {
                throw new Exception("Câu hỏi không được để trống!");
            }
            
            $finalAnswer = null;
            
            if (!empty($answer) && !empty(trim($answer))) {
                $finalAnswer = trim($answer);
            }
            elseif ($answerFile !== null && isset($answerFile['tmp_name'])) {
                $pdfResult = $this->extractAnswerFromPDF($answerFile);
                if (!$pdfResult['success']) {
                    throw new Exception("Lỗi đọc PDF đáp án: " . $pdfResult['error']);
                }
                $finalAnswer = $pdfResult['text'];
            }
            
            $gradingStrategy = $this->determineGradingStrategy($finalAnswer);
            
            // ======================================== 
            // RAG SEARCH: Nếu strategy là rag_verification
            // ========================================
            // Quy trình:
            // 1. Embedding câu hỏi thành vector
            // 2. Tìm các chunks tương đồng trong Vector DB
            // 3. Ghép content thành answer
            // 4. Lưu vào model_answer
            if ($gradingStrategy === 'rag_verification') {
                $ragResult = $this->performRAGSearch($question);
                
                if ($ragResult['success'] && !empty($ragResult['answer'])) {
                    $finalAnswer = $ragResult['answer'];
                    error_log("RAG Search successful: Found {$ragResult['chunks_count']} chunks");
                } else {
                    error_log("RAG Search warning: " . ($ragResult['error'] ?? 'No relevant documents found'));
                }
            }
            
            $this->validateByStrategy($gradingStrategy, $finalAnswer);
            
            $referenceDocJson = null;
            
            $existingQuestion = $this->findExactQuestionByText(trim($question));
            
            if ($existingQuestion) {
                $sql = "UPDATE exam_questions 
                        SET model_answer = :answer, 
                            grading_strategy = :strategy,
                            max_score = :max_score,
                            updated_at = NOW()
                        WHERE id = :id";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    ':answer' => $finalAnswer,
                    ':strategy' => $gradingStrategy,
                    ':max_score' => $maxScore,
                    ':id' => $existingQuestion['id']
                ]);
                
                return [
                    'success' => true,
                    'question_id' => (int)$existingQuestion['id'],
                    'strategy' => $gradingStrategy,
                    'error' => '',
                    'message' => "Câu hỏi đã tồn tại, đã cập nhật đáp án với strategy: {$gradingStrategy}",
                    'is_update' => true
                ];
            }
            
            $sql = "INSERT INTO exam_questions 
                    (question_text, model_answer, reference_doc_ids, grading_strategy, max_score) 
                    VALUES (:question, :answer, :doc_ids, :strategy, :max_score)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':question' => trim($question),
                ':answer' => $finalAnswer,
                ':doc_ids' => $referenceDocJson,
                ':strategy' => $gradingStrategy,
                ':max_score' => $maxScore
            ]);
            
            $questionId = (int)$this->pdo->lastInsertId();
            
            return [
                'success' => true,
                'question_id' => $questionId,
                'strategy' => $gradingStrategy,
                'error' => '',
                'message' => "Câu hỏi đã được tạo với strategy: {$gradingStrategy}"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'question_id' => 0,
                'strategy' => '',
                'error' => $e->getMessage(),
                'message' => ''
            ];
        }
    }
    
    /**
     * ========================================
     * RAG SEARCH: Tìm kiếm tài liệu tham khảo
     * ========================================
     * 
     * Quy trình:
     * 1. Gửi câu hỏi đến AI Server
     * 2. Server embedding câu hỏi thành vector
     * 3. Tìm top_k chunks có vector tương đồng nhất
     * 4. Ghép content thành answer
     * 
     * @param string $questionText Nội dung câu hỏi
     * @param int $topK Số lượng chunks cần lấy (default: 5)
     * @return array ['success' => bool, 'answer' => string, 'chunks_count' => int, 'error' => string]
     */
    private function performRAGSearch($questionText, $topK = 5) {
        try {
            $result = $this->syncService->ragSearch($questionText, $topK);
            
            if (!$result['success']) {
                return [
                    'success' => false,
                    'answer' => '',
                    'chunks_count' => 0,
                    'error' => $result['error']
                ];
            }
            
            $response = $result['response'];
            $generatedAnswer = $response['generated_answer'] ?? '';
            $chunksCount = $response['chunks_count'] ?? 0;
            
            if (empty($generatedAnswer)) {
                return [
                    'success' => false,
                    'answer' => '',
                    'chunks_count' => 0,
                    'error' => 'Không tìm thấy tài liệu tham khảo phù hợp'
                ];
            }
            
            return [
                'success' => true,
                'answer' => $generatedAnswer,
                'chunks_count' => $chunksCount,
                'error' => ''
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'answer' => '',
                'chunks_count' => 0,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ========================================
     * LOGIC CORE: determineGradingStrategy
     * ========================================
     * 
     * Quy tắc nghiệp vụ:
     * 1. Nếu có model_answer (text hoặc từ PDF) → compare_answer
     * 2. Nếu KHÔNG có model_answer → rag_verification
     * 
     * @param string|null $answer
     * @param array|null $documentIds
     * @return string 'compare_answer' hoặc 'rag_verification'
     */
    private function determineGradingStrategy($answer) {
        if (!empty($answer)) {
            return 'compare_answer';
        }
        
        return 'rag_verification';
    }
    
    /**
     * Validate dữ liệu theo strategy
     * 
     * @param string $strategy
     * @param string|null $answer
     * @throws Exception
     */
    private function validateByStrategy($strategy, $answer) {
        if ($strategy === 'rag_verification') {
            error_log("INFO: RAG verification - answer will be retrieved from Vector DB");
        }
        
        if ($strategy === 'compare_answer') {
            if (empty($answer)) {
                throw new Exception("Strategy compare_answer yêu cầu phải có model_answer!");
            }
        }
    }
    
    /**
     * Extract text từ PDF file sử dụng OCR server
     * 
     * @param array $file
     * @return array ['success' => bool, 'text' => string, 'error' => string]
     */
    private function extractAnswerFromPDF($file) {
        try {
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                return ['success' => false, 'text' => '', 'error' => 'File không hợp lệ'];
            }
            
            // Gửi PDF đến OCR server
            $result = $this->extractPDFUsingOCRServer($file['tmp_name'], $file['name'] ?? 'document.pdf');
            
            if (!$result['success']) {
                return ['success' => false, 'text' => '', 'error' => $result['error']];
            }
            
            if (empty(trim($result['text']))) {
                return ['success' => false, 'text' => '', 'error' => 'PDF không chứa text'];
            }
            
            return [
                'success' => true,
                'text' => trim($result['text']),
                'error' => ''
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'text' => '', 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Gửi PDF đến OCR server để xử lý
     * 
     * @param string $pdfPath Đường dẫn file PDF
     * @param string $filename Tên file gốc
     * @return array ['success' => bool, 'text' => string, 'error' => string]
     */
    private function extractPDFUsingOCRServer($pdfPath, $filename) {
        try {
            error_log("Đang gửi PDF đến OCR server: " . basename($filename));
            
            $ocrUrl = 'http://127.0.0.1:8001/ocr-pdf';
            
            // Tạo CURLFile để upload
            $cfile = new CURLFile($pdfPath, 'application/pdf', basename($filename));
            
            $postData = ['file' => $cfile];
            
            $ch = curl_init($ocrUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_TIMEOUT => 300, // 5 phút cho PDF lớn
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            if ($error) {
                error_log("CURL error: " . $error);
                return [
                    'success' => false,
                    'text' => '',
                    'error' => "Lỗi kết nối OCR server: " . $error
                ];
            }
            
            if ($httpCode !== 200) {
                error_log("OCR server HTTP error: " . $httpCode . ", Response: " . $response);
                return [
                    'success' => false,
                    'text' => '',
                    'error' => "OCR server trả về lỗi HTTP {$httpCode}"
                ];
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON parse error: " . json_last_error_msg());
                return [
                    'success' => false,
                    'text' => '',
                    'error' => "Lỗi parse OCR response: " . json_last_error_msg()
                ];
            }
            
            $text = $data['text'] ?? '';
            
            if (empty(trim($text))) {
                return [
                    'success' => false,
                    'text' => '',
                    'error' => 'Không đọc được text từ PDF'
                ];
            }
            
            error_log("PDF OCR thành công, độ dài text: " . strlen($text));
            
            return [
                'success' => true,
                'text' => $text,
                'error' => ''
            ];
            
        } catch (Exception $e) {
            error_log("Exception in extractPDFUsingOCRServer: " . $e->getMessage());
            return [
                'success' => false,
                'text' => '',
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Lấy thông tin câu hỏi theo ID
     */
    public function getQuestionById($questionId) {
        $sql = "SELECT * FROM exam_questions WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $questionId]);
        
        $question = $stmt->fetch();
        
        if ($question && $question['reference_doc_ids']) {
            $question['reference_doc_ids'] = json_decode($question['reference_doc_ids'], true);
        }
        
        return $question ?: null;
    }

    /**
     * Tìm câu hỏi chính xác theo question_text (so sánh chính xác, không phân biệt hoa thường)
     * Dùng để kiểm tra câu hỏi đã tồn tại hay chưa trước khi thêm mới
     * @param string $questionText
     * @return array|null
     */
    public function findExactQuestionByText($questionText) {
        $sql = "SELECT * FROM exam_questions WHERE LOWER(TRIM(question_text)) = LOWER(TRIM(:question_text)) LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':question_text' => $questionText]);

        $question = $stmt->fetch();
        if ($question && $question['reference_doc_ids']) {
            $question['reference_doc_ids'] = json_decode($question['reference_doc_ids'], true);
        }

        return $question ?: null;
    }

    /**
     * Tìm câu hỏi trong DB theo đoạn requirement (khớp chứa, không phân biệt hoa thường)
     * @param string $requirement
     * @return array|null
     */
    public function findQuestionByRequirement($requirement) {
        $pattern = '%' . mb_strtolower(trim($requirement), 'UTF-8') . '%';

        $sql = "SELECT * FROM exam_questions WHERE LOWER(question_text) LIKE :pattern LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':pattern' => $pattern]);

        $question = $stmt->fetch();
        if ($question && $question['reference_doc_ids']) {
            $question['reference_doc_ids'] = json_decode($question['reference_doc_ids'], true);
        }

        return $question ?: null;
    }
    
    /**
     * Lấy danh sách câu hỏi với phân trang
     */
    public function getQuestions($page = 1, $limit = 20, $strategy = null) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT 
                    id,
                    LEFT(question_text, 150) as question_preview,
                    grading_strategy,
                    CASE WHEN model_answer IS NOT NULL THEN 'Có' ELSE 'Không' END as has_answer,
                    CASE WHEN reference_doc_ids IS NOT NULL THEN 'Có' ELSE 'Không' END as has_docs,
                    max_score,
                    created_at
                FROM exam_questions";
        
        $params = [];
        
        if ($strategy) {
            $sql .= " WHERE grading_strategy = :strategy";
            $params[':strategy'] = $strategy;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Thống kê câu hỏi theo strategy
     */
    public function getStrategyStatistics() {
        $sql = "SELECT 
                    grading_strategy,
                    COUNT(*) as count,
                    AVG(max_score) as avg_max_score
                FROM exam_questions
                GROUP BY grading_strategy";
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Xóa câu hỏi
     */
    public function deleteQuestion($questionId) {
        $sql = "DELETE FROM exam_questions WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $questionId]);
    }
    
    /**
     * ========================================
     * SYNC TÀI LIỆU THAM KHẢO (RIÊNG BIỆT)
     * ========================================
     * Đồng bộ tất cả tài liệu tham khảo lên Vector DB
     * 
     * @param array|null $documentIds Mảng ID tài liệu (null = sync tất cả)
     * @return array ['success' => bool, 'error' => string, 'chunks_ingested' => int]
     */
    public function syncDocumentsToVectorDB($documentIds = null) {
        try {
            $documentsContent = [];
            
            if ($documentIds === null || empty($documentIds)) {
                $allDocs = $this->documentManager->getDocuments(1, 1000);
                foreach ($allDocs as $doc) {
                    $content = $this->extractDocumentContent($doc);
                    if (!empty($content)) {
                        $documentsContent[] = [
                            'title' => $doc['title'],
                            'content' => $content
                        ];
                    }
                }
            } else {
                foreach ($documentIds as $docId) {
                    $doc = $this->documentManager->getDocumentById($docId);
                    if ($doc) {
                        $content = $this->extractDocumentContent($doc);
                        if (!empty($content)) {
                            $documentsContent[] = [
                                'title' => $doc['title'],
                                'content' => $content
                            ];
                        }
                    }
                }
            }
            
            if (empty($documentsContent)) {
                return [
                    'success' => false,
                    'error' => 'Không có tài liệu nào để sync',
                    'chunks_ingested' => 0
                ];
            }
            
            $result = $this->syncService->syncDocuments($documentsContent);
            
            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'],
                    'chunks_ingested' => 0
                ];
            }
            
            $response = $result['response'] ?? [];
            return [
                'success' => true,
                'error' => '',
                'documents_count' => count($documentsContent),
                'chunks_ingested' => $response['chunks_ingested'] ?? 0,
                'timing' => $response['timing'] ?? null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'chunks_ingested' => 0
            ];
        }
    }
    
    /**
     * Extract nội dung từ document
     */
    private function extractDocumentContent($doc) {
        $content = '';
        if (!empty($doc['file_path']) && file_exists($doc['file_path'])) {
            if ($doc['file_type'] === 'pdf') {
                $extractResult = PDFExtractor::extractText($doc['file_path']);
                $content = $extractResult['success'] ? $extractResult['text'] : '';
            } elseif ($doc['file_type'] === 'txt') {
                $content = file_get_contents($doc['file_path']);
            }
        }
        return $content;
    }
}
?>
