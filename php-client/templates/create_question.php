<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../models/config.php';
require_once __DIR__ . '/../managers/QuestionManager.php';

Config::initDatabase();

$error = null;
$success = null;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = trim($_POST['question'] ?? '');
    $answer = trim($_POST['answer'] ?? '');
    $answerType = $_POST['answer_type'] ?? 'none'; // 'none', 'text', 'pdf'
    $maxScore = (float)($_POST['max_score'] ?? 10.00);
    
    try {
        $questionManager = new QuestionManager();
        
        $answerText = null;
        $answerFile = null;
        
        if ($answerType === 'text' && !empty($answer)) {
            $answerText = $answer;
        } elseif ($answerType === 'pdf' && isset($_FILES['answer_pdf']) && $_FILES['answer_pdf']['error'] === UPLOAD_ERR_OK) {
            $answerFile = $_FILES['answer_pdf'];
        }
        
        $result = $questionManager->createQuestion(
            $question,
            $answerText,
            $answerFile,
            $maxScore
        );
        
        if ($result['success']) {
            $success = $result['message'] . " (ID: {$result['question_id']})";
        } else {
            $error = $result['error'];
        }
        
    } catch (Exception $e) {
        $error = "Lỗi: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tạo câu hỏi tự luận - AutoScore</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .nav {
            background: #f8f9fa;
            padding: 15px 30px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .nav a {
            color: #667eea;
            text-decoration: none;
            margin-right: 20px;
            font-weight: 500;
        }
        
        .nav a:hover {
            color: #764ba2;
        }
        
        .content {
            padding: 30px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border-left: 4px solid #3c3;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #17a2b8;
        }
        
        .form-group textarea {
            resize: vertical;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 0.9em;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            margin: 15px 0;
            flex-wrap: wrap;
        }
        
        .radio-group label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: normal;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .radio-group label:hover {
            border-color: #17a2b8;
            background: #f8f9fa;
        }
        
        .radio-group input[type="radio"] {
            margin-right: 8px;
            width: 18px;
            height: 18px;
        }
        
        .answer-section {
            display: none;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #17a2b8;
            margin: 15px 0;
        }
        
        .answer-section.active {
            display: block;
        }
        
        .rag-section {
            display: none;
            padding: 20px;
            background: #fff3cd;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
            margin: 15px 0;
        }
        
        .rag-section.active {
            display: block;
        }
        
        .btn {
            display: inline-block;
            padding: 14px 35px;
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(23, 162, 184, 0.4);
        }
        
        .info-box {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #2196f3;
            margin: 20px 0;
        }
        
        .info-box h4 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        
        .info-box ul {
            margin-left: 20px;
        }
        
        .info-box li {
            margin: 8px 0;
            color: #555;
            line-height: 1.5;
        }
        
        .tip-box {
            background: #fff3cd;
            padding: 15px 20px;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
            margin-bottom: 25px;
        }
        
        .tip-box a {
            color: #856404;
            font-weight: 600;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #666;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Tạo câu hỏi tự luận</h1>
            <p>Tạo câu hỏi mới để hệ thống AI chấm điểm tự động</p>
        </div>
        
        <div class="nav">
            <a href="index.php">← Trang chủ</a>
            <a href="documents.php">Quản lý tài liệu</a>
            <a href="history.php">Lịch sử</a>
            <a href="api.php">API</a>
        </div>
        
        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <div class="tip-box">
                <strong>Lưu ý:</strong> Nếu bạn muốn sử dụng chế độ RAG (tra cứu tài liệu tham khảo), 
                hãy <a href="documents.php">upload và sync tài liệu</a> trước khi tạo câu hỏi.
            </div>
            
            <div class="info-box">
                <h4>Logic chấm điểm tự động</h4>
                <ul>
                    <li><strong>Compare Answer:</strong> Có đáp án mẫu → AI sẽ so sánh câu trả lời học sinh với đáp án mẫu</li>
                    <li><strong>RAG Verification:</strong> Không có đáp án mẫu → AI sẽ tra cứu trong tài liệu tham khảo (Vector DB) để verify câu trả lời</li>
                </ul>
            </div>
        
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="question">Câu hỏi tự luận *</label>
                    <textarea 
                        id="question" 
                        name="question" 
                        rows="5" 
                        placeholder="Nhập nội dung câu hỏi tự luận..."
                        required
                    ><?= htmlspecialchars($_POST['question'] ?? '') ?></textarea>
                    <small>Mô tả rõ ràng câu hỏi mà học sinh cần trả lời</small>
                </div>
                
                <div class="form-group">
                    <label>Loại đáp án</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="answer_type" value="none" checked>
                            <span>Không có đáp án (dùng RAG)</span>
                        </label>
                        <label>
                            <input type="radio" name="answer_type" value="text">
                            <span>Nhập đáp án dạng text</span>
                        </label>
                        <label>
                            <input type="radio" name="answer_type" value="pdf">
                            <span>Upload đáp án từ PDF</span>
                        </label>
                    </div>
                </div>
                
                <!-- Answer Text Section -->
                <div class="answer-section" id="answerTextSection">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="answer">Đáp án mẫu (text)</label>
                        <textarea 
                            id="answer" 
                            name="answer" 
                            rows="8" 
                            placeholder="Nhập đáp án mẫu..."
                        ><?= htmlspecialchars($_POST['answer'] ?? '') ?></textarea>
                        <small>Strategy: <strong>compare_answer</strong> - AI sẽ so sánh câu trả lời với đáp án này</small>
                    </div>
                </div>
                
                <!-- Answer PDF Section -->
                <div class="answer-section" id="answerPdfSection">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="answer_pdf">Upload đáp án PDF</label>
                        <input type="file" id="answer_pdf" name="answer_pdf" accept=".pdf" style="padding: 10px;">
                        <small>Strategy: <strong>compare_answer</strong> - Hệ thống sẽ đọc text từ PDF làm đáp án mẫu</small>
                    </div>
                </div>
                
                <!-- RAG Info Section -->
                <div class="rag-section active" id="ragSection">
                    <p style="color: #856404; margin: 0;">
                        <strong>Chế độ RAG:</strong> Khi không có đáp án mẫu, hệ thống sẽ sử dụng chiến lược <strong>RAG Verification</strong> - 
                        tra cứu trong tài liệu tham khảo đã sync vào Vector Database để đánh giá câu trả lời của học sinh.
                    </p>
                </div>
                
                <div class="form-group">
                    <label for="max_score">Điểm tối đa</label>
                    <input 
                        type="number" 
                        id="max_score" 
                        name="max_score" 
                        step="0.01" 
                        min="0" 
                        max="100" 
                        value="<?= $_POST['max_score'] ?? '10.00' ?>"
                        style="max-width: 150px;"
                    >
                </div>
                
                <button type="submit" class="btn">Tạo câu hỏi</button>
            </form>
        </div>
        
        <div class="footer">
            <p>&copy; 2025 AutoScore System | Powered by AI</p>
        </div>
    </div>
    
    <script>
        const answerTypeRadios = document.querySelectorAll('input[name="answer_type"]');
        const answerTextSection = document.getElementById('answerTextSection');
        const answerPdfSection = document.getElementById('answerPdfSection');
        const ragSection = document.getElementById('ragSection');
        
        answerTypeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                answerTextSection.classList.remove('active');
                answerPdfSection.classList.remove('active');
                ragSection.classList.remove('active');
                
                document.querySelectorAll('.radio-group label').forEach(label => {
                    label.style.borderColor = '#e0e0e0';
                    label.style.background = 'white';
                });
                
                this.parentElement.style.borderColor = '#17a2b8';
                this.parentElement.style.background = '#e8f7fa';
                
                if (this.value === 'text') {
                    answerTextSection.classList.add('active');
                } else if (this.value === 'pdf') {
                    answerPdfSection.classList.add('active');
                } else if (this.value === 'none') {
                    ragSection.classList.add('active');
                }
            });
        });

        const defaultRadio = document.querySelector('input[name="answer_type"]:checked');
        if (defaultRadio) {
            defaultRadio.parentElement.style.borderColor = '#17a2b8';
            defaultRadio.parentElement.style.background = '#e8f7fa';
        }
    </script>
</body>
</html>
