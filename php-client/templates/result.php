<?php
require_once __DIR__ . '/../models/config.php';
require_once __DIR__ . '/../models/Submission.php';
require_once __DIR__ . '/../services/GradingService.php';

$submissionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = null;
$submission = null;
$result = null;

if ($submissionId <= 0) {
    $error = "ID không hợp lệ";
} else {
    $submissionModel = new Submission();
    $submission = $submissionModel->getById($submissionId);
    
    if (!$submission) {
        $error = "Không tìm thấy submission với ID: {$submissionId}";
    } else {
        if (!empty($submission['result'])) {
            $result = json_decode($submission['result'], true);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['regrade']) && $submission) {
    try {
        $gradingService = new GradingService();
        $gradingService->regrade($submissionId);
        header("Location: result.php?id={$submissionId}");
        exit;
    } catch (Exception $e) {
        $error = "Lỗi chấm lại: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả chấm điểm #<?= $submissionId ?> - AutoScore</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
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
            padding: 20px 30px;
            border-bottom: 2px solid #dee2e6;
            text-align: center;
        }
        
        .nav a {
            color: #dc3545;
            text-decoration: none;
            margin: 0 20px;
            font-weight: 600;
            transition: color 0.3s;
            display: inline-block;
        }
        
        .nav a:hover {
            color: #c82333;
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
        
        .score-badge {
            display: inline-block;
            font-size: 3em;
            font-weight: bold;
            padding: 20px 40px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: center;
        }
        
        .score-excellent {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        
        .score-good {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }
        
        .score-average {
            background: linear-gradient(135deg, #f39c12 0%, #f1c40f 100%);
            color: white;
        }
        
        .score-poor {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }
        
        .section {
            margin: 25px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        
        .section h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        
        .section-content {
            color: #555;
            line-height: 1.8;
            white-space: pre-wrap;
        }
        
        .criteria-item {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 3px solid #667eea;
        }
        
        .criteria-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .criteria-score {
            color: #667eea;
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .list-item {
            padding: 10px 15px;
            margin: 8px 0;
            background: white;
            border-radius: 6px;
            border-left: 3px solid #38ef7d;
        }
        
        .list-item.improvement {
            border-left-color: #f39c12;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            margin: 5px;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }
        
        .meta-info {
            background: #e8eaf6;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .meta-info div {
            margin: 5px 0;
            color: #555;
        }
        
        .meta-info strong {
            color: #333;
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
            <h1><img src="../assets/logo.png" alt="AutoScore Logo" style="height: 40px; vertical-align: middle; margin-bottom: 10px;"> Kết quả chấm điểm</h1>
            <p>Submission #<?= $submissionId ?></p>
        </div>
        
        <div class="nav">
            <a href="index.php">Chấm điểm mới</a>
            <a href="create_question.php">Tạo câu hỏi</a>
            <a href="history.php">Lịch sử</a>
        </div>
        
        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <a href="index.php" class="btn">← Quay lại trang chủ</a>
            <?php elseif ($submission): ?>
                
                <div class="meta-info">
                    <div><strong>ID Submission:</strong> #<?= $submission['id'] ?></div>
                    <div><strong>Thời gian gửi:</strong> <?= date('d/m/Y H:i:s', strtotime($submission['created_at'])) ?></div>
                    <div><strong>Cập nhật lần cuối:</strong> <?= date('d/m/Y H:i:s', strtotime($submission['updated_at'])) ?></div>
                </div>
                
                <?php if ($result): ?>
                    <div style="text-align: center;">
                        <?php
                        $score = $result['total_score'];
                        $scoreClass = 'score-poor';
                        if ($score >= 8) $scoreClass = 'score-excellent';
                        elseif ($score >= 6.5) $scoreClass = 'score-good';
                        elseif ($score >= 5) $scoreClass = 'score-average';
                        ?>
                        <div class="score-badge <?= $scoreClass ?>">
                            <?= number_format($score, 2) ?> / 10
                        </div>
                    </div>
                    
                    <?php if (!empty($result['feedback'])): ?>
                    <div class="section">
                        <h3>💬 Nhận xét tổng quan</h3>
                        <div class="section-content"><?= htmlspecialchars($result['feedback']) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($result['criteria_scores'])): ?>
                    <div class="section">
                        <h3>Điểm theo tiêu chí</h3>
                        <?php foreach ($result['criteria_scores'] as $criteria => $score): ?>
                        <div class="criteria-item">
                            <div class="criteria-name"><?= htmlspecialchars($criteria) ?></div>
                            <div class="criteria-score"><?= htmlspecialchars($score) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($result['strengths'])): ?>
                    <div class="section">
                        <h3>Điểm mạnh</h3>
                        <?php foreach ($result['strengths'] as $strength): ?>
                        <div class="list-item"><?= htmlspecialchars($strength) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($result['improvements'])): ?>
                    <div class="section">
                        <h3>Cần cải thiện</h3>
                        <?php foreach ($result['improvements'] as $improvement): ?>
                        <div class="list-item improvement"><?= htmlspecialchars($improvement) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="alert alert-error">Chưa có kết quả chấm điểm. Vui lòng thử chấm lại.</div>
                <?php endif; ?>
                
                <div class="section">
                    <h3>Yêu cầu đề bài</h3>
                    <div class="section-content"><?= htmlspecialchars($submission['requirement']) ?></div>
                </div>
                
                <div class="section">
                    <h3>Bài làm của học sinh</h3>
                    <div class="section-content"><?= htmlspecialchars($submission['student_work']) ?></div>
                </div>
                
                <div style="margin-top: 30px; text-align: center;">
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="regrade" class="btn">Chấm lại</button>
                    </form>
                    <a href="history.php" class="btn btn-secondary">Xem lịch sử</a>
                    <a href="index.php" class="btn btn-secondary">Chấm bài mới</a>
                </div>
                
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>&copy; 2025 AutoScore System | Powered by N8N AI Agent</p>
        </div>
    </div>
</body>
</html>
