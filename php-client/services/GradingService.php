<?php
require_once __DIR__ . '/../models/config.php';
require_once __DIR__ . '/../models/Submission.php';
require_once __DIR__ . '/../managers/QuestionManager.php';

class GradingService {
    private $webhookUrl;
    private $llmApiUrl;
    private $llmModel;
    private $timeout;
    private $submissionModel;
    
    public function __construct() {
        $this->webhookUrl = Config::WEBHOOK_URL;
        $this->llmApiUrl = defined('Config::LLM_API_URL') ? Config::LLM_API_URL : '';
        $this->llmModel = defined('Config::LLM_MODEL') ? Config::LLM_MODEL : 'qwen2.5-3b-instruct';
        $this->timeout = Config::REQUEST_TIMEOUT;
        $this->submissionModel = new Submission();
    }
    
    /**
     * Gửi HTTP request đến LLM server hoặc webhook
     * Ưu tiên LLM server local nếu được cấu hình
     * @param array $payload
     * @return string Response body
     * @throws Exception
     */
    private function sendRequest($payload) {
        if (!empty($this->llmApiUrl)) {
            return $this->sendLLMRequest($payload);
        }
        
        return $this->sendWebhookRequest($payload);
    }
    
    /**
     * Gửi request đến LLM server local (LM Studio, Ollama, etc.)
     * @param array $payload
     * @return string Response body
     * @throws Exception
     */
    private function sendLLMRequest($payload) {
        $requirement = $payload['requirement'];
        $modelAnswer = $payload['model_answer'];
        $studentWork = $payload['student_work'];
        
        $systemInstruction = "Hãy đóng vai trò là một chuyên gia chấm thi khách quan và nghiêm khắc. Nhiệm vụ của bạn là đánh giá câu trả lời của học sinh dựa trên câu hỏi và đáp án chuẩn được cung cấp.

Yêu cầu xử lý:
1. So sánh kỹ lưỡng ý nghĩa, từ khóa và logic của câu trả lời học sinh so với đáp án chuẩn.
2. Chấm điểm trên thang điểm từ 0 đến 10 (có thể dùng số thập phân).
   - 0 điểm: Sai hoàn toàn hoặc không trả lời.
   - 10 điểm: Chính xác hoàn toàn, đầy đủ ý như đáp án chuẩn.
3. Giải thích ngắn gọn lý do tại sao cho số điểm đó (chỉ ra lỗi sai hoặc phần thiếu nếu có).

QUAN TRỌNG:
- Bạn chỉ được phép trả về kết quả dưới dạng JSON thuần túy.
- Không được thêm bất kỳ văn bản, lời chào, hay định dạng markdown (```json) nào khác vào đầu hoặc cuối.
- Cấu trúc JSON bắt buộc như sau: {\"score\": <số_điểm>, \"feedback\": \"<lời_giải_thích>\"}";
        
        $userContent = "[CÂU HỎI]:\n{$requirement}\n\n[ĐÁP ÁN CHUẨN]:\n{$modelAnswer}\n\n[CÂU TRẢ LỜI CỦA HỌC SINH]:\n{$studentWork}";
        
        $data = [
            'model' => $this->llmModel,
            'messages' => [
                ['role' => 'system', 'content' => $systemInstruction],
                ['role' => 'user', 'content' => $userContent]
            ],
            'temperature' => 0.2,
            'max_tokens' => 4096
        ];

        error_log("LLM Request Payload: " . json_encode($data, JSON_UNESCAPED_UNICODE));
        
        $ch = curl_init($this->llmApiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        if ($error) {
            throw new Exception("Lỗi kết nối đến LLM server: " . $error);
        }
        
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("LLM server trả về lỗi HTTP {$httpCode}");
        }
        
        if (empty($response)) {
            throw new Exception("LLM server trả về response rỗng");
        }
        
        error_log("LLM Response: " . $response);
        
        $llmData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Lỗi parse LLM response: " . json_last_error_msg());
        }
        
        $content = $llmData['choices'][0]['message']['content'] ?? '';
        
        if (empty($content)) {
            throw new Exception("LLM không trả về nội dung");
        }
        
        return $content;
    }
    
    /**
     * Gửi request đến AI Grading Server (Gemini) hoặc N8N webhook
     * @param array $payload
     * @return string Response body
     * @throws Exception
     */
    private function sendWebhookRequest($payload) {
        $ch = curl_init($this->webhookUrl);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        if ($error) {
            throw new Exception("Lỗi kết nối đến webhook: " . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("Webhook trả về lỗi HTTP {$httpCode}");
        }
        
        if (empty($response)) {
            throw new Exception("Webhook trả về response rỗng");
        }
        
        return $response;
    }
    
    /**
     * Xử lý response từ AI Grading Server hoặc LLM
     * Response format: { "score": <số>, "feedback": "<nhận xét>" }
     * Có thể có markdown wrapper ```json ... ```
     * 
     * @param string $response JSON response (có thể wrapped trong markdown)
     * @return array Dữ liệu đã được parse và validate
     * @throws Exception
     */
    private function processResponse($response) {
        $cleanResponse = $response;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $response, $matches)) {
            $cleanResponse = trim($matches[1]);
        }
        
        if (preg_match('/\{[^{}]*"score"\s*:\s*[\d.]+[^{}]*\}/s', $cleanResponse, $jsonMatch)) {
            $cleanResponse = $jsonMatch[0];
        }
        
        $data = json_decode($cleanResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Failed to parse response: " . $response);
            throw new Exception("Lỗi parse JSON response: " . json_last_error_msg());
        }
        
        $score = isset($data['score']) ? (float)$data['score'] : 0;
        $feedback = $data['feedback'] ?? '';
        
        $score = max(0, min(10, $score));
        
        $result = [
            'total_score' => round($score, 2),
            'feedback' => $feedback,
            'raw_response' => $data
        ];
        
        return $result;
    }
    
    /**
     * Chấm điểm và tạo submission mới
     * Tự động tìm question tương ứng để lấy model_answer
     * @param string $requirement Yêu cầu đề bài
     * @param string $studentWork Bài làm của học sinh
     * @return array
     * @throws Exception
     */
    public function gradeNew($requirement, $studentWork) {
        $questionManager = new QuestionManager();
        $matchedQuestion = $questionManager->findQuestionByRequirement($requirement);
        
        if ($matchedQuestion) {
            $modelAnswer = $matchedQuestion['model_answer'] ?? '';
        } else {
            $modelAnswer = '';
        }
        
        $submissionId = $this->submissionModel->create($requirement, $studentWork);
        
        $payload = [
            'requirement' => $requirement,
            'student_work' => $studentWork,
            'model_answer' => $modelAnswer,
            'submission_id' => $submissionId
        ];
        
        try {
            $response = $this->sendRequest($payload);
            $result = $this->processResponse($response);
            
            $this->submissionModel->updateResultWithFeedback(
                $submissionId,
                json_encode($result, JSON_UNESCAPED_UNICODE),
                $result['total_score'],
                $result['feedback'] ?? ''
            );
            
            $result['submission_id'] = $submissionId;
            return $result;
        } catch (Exception $e) {
            throw new Exception("Lỗi chấm điểm (Submission ID: {$submissionId}): " . $e->getMessage());
        }
    }

    /**
     * Gửi request chấm điểm khi đã có model_answer tương ứng với requirement
     * Payload gửi sẽ gồm: requirement, student_work, model_answer, submission_id
     * @param string $requirement
     * @param string $studentWork
     * @param string $modelAnswer
     * @return array
     * @throws Exception
     */
    public function gradeUsingModelAnswer($requirement, $studentWork, $modelAnswer) {
        $submissionId = $this->submissionModel->create($requirement, $studentWork, '');

        $payload = [
            'requirement' => $requirement,
            'student_work' => $studentWork,
            'model_answer' => $modelAnswer,
            'submission_id' => $submissionId
        ];

        $response = $this->sendRequest($payload);
        $result = $this->processResponse($response);

        $this->submissionModel->updateResultWithFeedback(
            $submissionId,
            json_encode($result, JSON_UNESCAPED_UNICODE),
            $result['total_score'],
            $result['feedback'] ?? ''
        );

        $result['submission_id'] = $submissionId;
        return $result;
    }
    
    /**
     * Alias của gradeUsingModelAnswer để dễ hiểu hơn
     * Chấm điểm với đáp án chuẩn do người dùng cung cấp
     * @param string $requirement
     * @param string $studentWork
     * @param string $modelAnswer
     * @return array
     * @throws Exception
     */
    public function gradeWithModelAnswer($requirement, $studentWork, $modelAnswer) {
        return $this->gradeUsingModelAnswer($requirement, $studentWork, $modelAnswer);
    }

    /**
     * Chấm một submission đã tồn tại sử dụng model_answer (không tạo submission mới)
     * @param int $submissionId
     * @param string $requirement
     * @param string $studentWork
     * @param string $modelAnswer
     * @return array
     * @throws Exception
     */
    public function gradeExistingUsingModelAnswer($submissionId, $requirement, $studentWork, $modelAnswer) {
        $payload = [
            'requirement' => $requirement,
            'student_work' => $studentWork,
            'model_answer' => $modelAnswer,
            'submission_id' => $submissionId
        ];

        $response = $this->sendRequest($payload);
        $result = $this->processResponse($response);

        $this->submissionModel->updateResultWithFeedback(
            $submissionId,
            json_encode($result, JSON_UNESCAPED_UNICODE),
            $result['total_score'],
            $result['feedback'] ?? ''
        );

        $result['submission_id'] = $submissionId;
        return $result;
    }
    
    /**
     * Chấm lại một submission đã tồn tại
     * Quy trình tương tự chấm mới: tìm question tương ứng để lấy model_answer
     * @param int $submissionId
     * @return array
     * @throws Exception
     */
    public function regrade($submissionId) {
        $submission = $this->submissionModel->getById($submissionId);
        
        if (!$submission) {
            throw new Exception("Không tìm thấy submission ID: {$submissionId}");
        }
        
        $questionManager = new QuestionManager();
        $matchedQuestion = $questionManager->findQuestionByRequirement($submission['requirement']);

        if ($matchedQuestion) {
            $modelAnswer = $matchedQuestion['model_answer'] ?? '';
        }

        return $this->gradeExistingUsingModelAnswer(
            $submissionId,
            $submission['requirement'],
            $submission['student_work'],
            $modelAnswer
        );
    }
}
?>
