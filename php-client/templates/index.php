<?php
require_once __DIR__ . '/../models/config.php';
require_once __DIR__ . '/../services/GradingService.php';

Config::initDatabase();

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requirement = trim($_POST['requirement'] ?? '');
    $studentWork = trim($_POST['student_work'] ?? '');
    $modelAnswer = trim($_POST['model_answer'] ?? '');
    
    $uploadedImages = $_FILES['student_images'] ?? null;
    
    if ($uploadedImages && !empty($uploadedImages['tmp_name'][0])) {
        try {
            $ch = curl_init('http://127.0.0.1:8001/ocr');
            
            $postData = [];
            for ($i = 0; $i < count($uploadedImages['tmp_name']); $i++) {
                if ($uploadedImages['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpPath = $uploadedImages['tmp_name'][$i];
                    $fileName = $uploadedImages['name'][$i];
                    
                    $cfile = new CURLFile($tmpPath, $uploadedImages['type'][$i], $fileName);
                    $postData["files[{$i}]"] = $cfile;
                }
            }

            error_log("Sending " . json_encode($postData) . " images to OCR server.");

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_TIMEOUT => 60
            ]);
            
            $ocrResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            
            if ($curlError) {
                throw new Exception("Lỗi kết nối OCR server: " . $curlError);
            }
            
            if ($httpCode !== 200) {
                throw new Exception("OCR server trả về lỗi HTTP {$httpCode}");
            }
            
            $ocrData = json_decode($ocrResponse, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Lỗi parse OCR response: " . json_last_error_msg());
            }
            
            $ocrText = $ocrData['text'] ?? '';
            if (!empty($ocrText)) {
                $studentWork = trim($studentWork . "\n\n" . $ocrText);
            }
        } catch (Exception $e) {
            $error = "Lỗi OCR: " . $e->getMessage();
        }
    }

    if (empty($requirement) || empty($studentWork)) {
        $error = "Vui lòng điền đầy đủ yêu cầu và bài làm (hoặc upload ảnh)!";
    } else {
        try {
            $gradingService = new GradingService();

            // Nếu có đáp án chuẩn, sử dụng gradeWithModelAnswer, ngược lại dùng gradeNew
            if (!empty($modelAnswer)) {
                $result = $gradingService->gradeWithModelAnswer($requirement, $studentWork, $modelAnswer);
            } else {
                $result = $gradingService->gradeNew($requirement, $studentWork);
            }

            header("Location: result.php?id=" . $result['submission_id']);
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ thống Chấm điểm Tự động - AutoScore</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1em;
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
            transition: color 0.3s;
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
            font-size: 1.05em;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            transition: border-color 0.3s;
            resize: vertical;
        }
        
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 0.9em;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .example {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            border-left: 4px solid #667eea;
        }
        
        .example h4 {
            color: #667eea;
            margin-bottom: 8px;
        }
        
        .example pre {
            white-space: pre-wrap;
            font-size: 0.9em;
            color: #555;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #666;
            border-top: 1px solid #dee2e6;
        }
        
        /* Action Cards Styles */
        .action-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 20px;
        }
        
        .action-card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 2px solid transparent;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .documents-card {
            border-color: #ffc107;
            background: linear-gradient(135deg, #fff9e6 0%, #fff 100%);
        }
        
        .documents-card:hover {
            border-color: #ffca2c;
        }
        
        .questions-card {
            border-color: #17a2b8;
            background: linear-gradient(135deg, #e8f7fa 0%, #fff 100%);
        }
        
        .questions-card:hover {
            border-color: #138496;
        }
        
        .card-icon {
            font-size: 3em;
            margin-bottom: 15px;
        }
        
        .action-card h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.3em;
        }
        
        .action-card p {
            color: #666;
            font-size: 0.95em;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .btn-documents {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: #333;
        }
        
        .btn-documents:hover {
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.4);
        }
        
        .btn-questions {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }
        
        .btn-questions:hover {
            box-shadow: 0 5px 15px rgba(23, 162, 184, 0.4);
        }
        
        /* Image Preview Styles */
        .image-upload-section {
            border: 2px dashed #667eea;
            border-radius: 8px;
            padding: 20px;
            background: #f8f9fa;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .image-upload-section:hover {
            border-color: #764ba2;
            background: #f0f0f5;
        }
        
        .image-upload-section.dragover {
            border-color: #764ba2;
            background: #e8e8f0;
        }
        
        #student_images {
            display: none;
        }
        
        .upload-icon {
            font-size: 3em;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .image-queue {
            margin-top: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .image-preview-item {
            position: relative;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            background: white;
            transition: all 0.3s;
        }
        
        .image-preview-item:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .image-preview-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            display: block;
        }
        
        .image-preview-item .image-name {
            padding: 8px;
            font-size: 0.85em;
            color: #333;
            text-overflow: ellipsis;
            overflow: hidden;
            white-space: nowrap;
        }
        
        .image-preview-item .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255, 0, 0, 0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .image-preview-item .remove-image:hover {
            background: rgba(200, 0, 0, 1);
            transform: scale(1.1);
        }
        
        .image-count-badge {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .clear-all-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            margin-top: 10px;
            transition: all 0.3s;
        }
        
        .clear-all-btn:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>AutoScore</h1>
            <p>Hệ thống Chấm điểm Tự động với AI</p>
        </div>
        
        <div class="nav">
            <a href="index.php">Trang chủ</a>
            <a href="create_question.php">Quản lý câu hỏi</a>
            <a href="history.php">Lịch sử</a>
            <a href="api.php">API Documentation</a>
        </div>
        
        <div class="content">
            <!-- Action Cards -->
            <div class="action-cards">
                <div class="action-card documents-card">
                    <h3>Upload & Sync Tài liệu</h3>
                    <p>Upload tài liệu tham khảo (PDF, TXT) và đồng bộ vào Vector Database để AI sử dụng khi chấm điểm</p>
                    <a href="documents.php" class="btn btn-documents">
                        Quản lý tài liệu
                    </a>
                </div>
                
                <div class="action-card questions-card">
                    <h3>Tạo câu hỏi</h3>
                    <p>Tạo câu hỏi mới với đáp án mẫu, cấu hình chiến lược chấm điểm và thang điểm</p>
                    <a href="create_question.php" class="btn btn-questions">
                        Tạo câu hỏi mới
                    </a>
                </div>
            </div>
            
            <hr style="margin: 30px 0; border: none; border-top: 2px solid #eee;">
            
            <h2 style="margin-bottom: 20px; color: #333;">Chấm điểm nhanh</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="requirement">Yêu cầu đề bài *</label>
                    <textarea 
                        id="requirement" 
                        name="requirement" 
                        rows="5" 
                        placeholder="Nhập yêu cầu đề bài, mô tả chi tiết những gì học sinh cần thực hiện..."
                        required
                    ><?= htmlspecialchars($_POST['requirement'] ?? '') ?></textarea>
                    <small>Mô tả rõ ràng yêu cầu của bài tập, dự án hoặc câu hỏi</small>
                </div>
                
                <div class="form-group">
                    <label for="model_answer">Đáp án chuẩn (tùy chọn)</label>
                    <textarea 
                        id="model_answer" 
                        name="model_answer" 
                        rows="6" 
                        placeholder="Nhập đáp án mẫu/chuẩn để AI có thể so sánh và chấm chính xác hơn..."
                    ><?= htmlspecialchars($_POST['model_answer'] ?? '') ?></textarea>
                    <small>Nếu có đáp án chuẩn, hệ thống sẽ so sánh trực tiếp. Nếu bỏ trống, AI sẽ tìm kiếm từ cơ sở dữ liệu.</small>
                </div>
                
                <div class="form-group">
                    <label for="student_work">Bài làm của học sinh (Nhập text)</label>
                    <textarea 
                        id="student_work" 
                        name="student_work" 
                        rows="8" 
                        placeholder="Nhập bài làm của học sinh..."
                    ><?= htmlspecialchars($_POST['student_work'] ?? '') ?></textarea>
                    <small id="textInputHint">Nội dung bài làm cần được đánh giá</small>
                </div>
                
                <div style="text-align: center; margin: 20px 0;">
                    <div style="display: inline-block; background: #f0f0f5; padding: 8px 20px; border-radius: 20px; color: #667eea; font-weight: 600;">
                        HOẶC
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="student_images">
                        Upload ảnh bài làm
                        <span class="image-count-badge" id="imageCountBadge" style="display: none;">0 ảnh</span>
                    </label>
                    
                    <div class="image-upload-section" id="uploadSection">
                        <div class="upload-icon">📸</div>
                        <p style="color: #667eea; font-weight: 600; margin-bottom: 5px;">Nhấn để chọn ảnh hoặc kéo thả vào đây</p>
                        <small style="color: #666;">Hỗ trợ: PNG, JPG, JPEG. Có thể chọn nhiều ảnh cùng lúc.</small>
                        <input 
                            type="file" 
                            id="student_images" 
                            name="student_images[]" 
                            accept="image/*"
                            multiple
                        >
                    </div>
                    
                    <div id="imageQueue" class="image-queue"></div>
                    
                    <button type="button" class="clear-all-btn" id="clearAllBtn" style="display: none;">
                        Xóa tất cả ảnh
                    </button>
                    
                    <small style="display: block; margin-top: 10px;">Hệ thống sẽ tự động nhận diện text từ tất cả ảnh bằng OCR.</small>
                </div>
                
                <button type="submit" class="btn">Gửi chấm điểm</button>
            </form>
        </div>
        
        <div class="footer">
            <p>&copy; 2025 AutoScore System | Powered by N8N AI Agent</p>
        </div>
    </div>
    
    <script>
        let imageFiles = [];
        const uploadSection = document.getElementById('uploadSection');
        const fileInput = document.getElementById('student_images');
        const imageQueue = document.getElementById('imageQueue');
        const imageCountBadge = document.getElementById('imageCountBadge');
        const clearAllBtn = document.getElementById('clearAllBtn');
        const studentWorkTextarea = document.getElementById('student_work');
        
        function toggleInputMethod() {
            if (imageFiles.length > 0) {
                studentWorkTextarea.disabled = true;
                studentWorkTextarea.style.opacity = '0.5';
                studentWorkTextarea.style.cursor = 'not-allowed';
                studentWorkTextarea.placeholder = 'Đã chọn upload ảnh. Xóa ảnh để nhập text.';
                document.getElementById('textInputHint').innerHTML = '<strong style="color: #dc3545;">Đã vô hiệu hóa vì đang dùng ảnh</strong>';
            } else {
                studentWorkTextarea.disabled = false;
                studentWorkTextarea.style.opacity = '1';
                studentWorkTextarea.style.cursor = 'text';
                studentWorkTextarea.placeholder = 'Nhập bài làm của học sinh...';
                document.getElementById('textInputHint').innerHTML = 'Nội dung bài làm cần được đánh giá';
            }
            
            if (studentWorkTextarea.value.trim().length > 0) {
                uploadSection.style.pointerEvents = 'none';
                uploadSection.style.opacity = '0.5';
                uploadSection.querySelector('p').innerHTML = '<strong style="color: #dc3545;">Đã vô hiệu hóa vì đang dùng text</strong>';
                uploadSection.querySelector('small').textContent = 'Xóa nội dung text để upload ảnh.';
            } else {
                uploadSection.style.pointerEvents = 'auto';
                uploadSection.style.opacity = '1';
                uploadSection.querySelector('p').innerHTML = '<span style="color: #667eea; font-weight: 600;">Nhấn để chọn ảnh hoặc kéo thả vào đây</span>';
                uploadSection.querySelector('small').textContent = 'Hỗ trợ: PNG, JPG, JPEG. Có thể chọn nhiều ảnh cùng lúc.';
            }
        }
        
        studentWorkTextarea.addEventListener('input', toggleInputMethod);
        
        uploadSection.addEventListener('click', (e) => {
            if (studentWorkTextarea.value.trim().length === 0) {
                fileInput.click();
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });
        
        uploadSection.addEventListener('dragover', (e) => {
            e.preventDefault();
            if (studentWorkTextarea.value.trim().length === 0) {
                uploadSection.classList.add('dragover');
            }
        });
        
        uploadSection.addEventListener('dragleave', () => {
            uploadSection.classList.remove('dragover');
        });
        
        uploadSection.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadSection.classList.remove('dragover');
            if (studentWorkTextarea.value.trim().length === 0) {
                handleFiles(e.dataTransfer.files);
            } else {
                alert('Vui lòng xóa nội dung text trước khi upload ảnh!');
            }
        });
        
        function handleFiles(files) {
            if (studentWorkTextarea.value.trim().length > 0) {
                alert('Vui lòng xóa nội dung text trước khi upload ảnh!');
                return;
            }
            const newFiles = Array.from(files).filter(file => file.type.startsWith('image/'));
            imageFiles = imageFiles.concat(newFiles);
            updateImageQueue();
            updateFileInput();
            toggleInputMethod();
        }
        
        function updateImageQueue() {
            imageQueue.innerHTML = '';
            
            imageFiles.forEach((file, index) => {
                const reader = new FileReader();
                
                reader.onload = (e) => {
                    const previewItem = document.createElement('div');
                    previewItem.className = 'image-preview-item';
                    previewItem.innerHTML = `
                        <img src="${e.target.result}" alt="${file.name}">
                        <div class="image-name" title="${file.name}">${file.name}</div>
                        <button type="button" class="remove-image" data-index="${index}">×</button>
                    `;
                    
                    imageQueue.appendChild(previewItem);
                };
                
                reader.readAsDataURL(file);
            });
            
            if (imageFiles.length > 0) {
                imageCountBadge.textContent = `${imageFiles.length} ảnh`;
                imageCountBadge.style.display = 'inline-block';
                clearAllBtn.style.display = 'block';
            } else {
                imageCountBadge.style.display = 'none';
                clearAllBtn.style.display = 'none';
            }
        }
        
        imageQueue.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-image')) {
                const index = parseInt(e.target.getAttribute('data-index'));
                imageFiles.splice(index, 1);
                updateImageQueue();
                updateFileInput();
                toggleInputMethod();
            }
        });
        
        clearAllBtn.addEventListener('click', () => {
            if (confirm('Bạn có chắc muốn xóa tất cả ảnh đã chọn?')) {
                imageFiles = [];
                updateImageQueue();
                updateFileInput();
                toggleInputMethod();
            }
        });
        
        function updateFileInput() {
            const dataTransfer = new DataTransfer();
            imageFiles.forEach(file => {
                dataTransfer.items.add(file);
            });
            fileInput.files = dataTransfer.files;
        }
        
        toggleInputMethod();
    </script>
</body>
</html>
