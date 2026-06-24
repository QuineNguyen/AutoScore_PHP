<?php
require_once __DIR__ . '/../models/config.php';
require_once __DIR__ . '/../models/Submission.php';

$submission = new Submission();
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$pdo = Config::getConnection();
$stmt = $pdo->prepare('SELECT * FROM submissions ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $pdo->query("SELECT COUNT(*) as total FROM submissions");
$totalRecords = $countStmt->fetch()['total'];
$totalPages = ceil($totalRecords / $limit);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử chấm điểm</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: Arial, sans-serif; 
            margin: 0;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 0;
            box-shadow: none;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 30px;
            border-radius: 0;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            margin: 0;
            font-size: 32px;
            font-weight: bold;
        }
        
        .header p {
            margin: 10px 0 0 0;
            font-size: 16px;
            opacity: 0.95;
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
            display: inline-block;
            padding: 8px 0;
            transition: color 0.3s, border-bottom 0.3s;
            border-bottom: 2px solid transparent;
        }
        
        .nav a:hover {
            color: #c82333;
            border-bottom: 2px solid #dc3545;
        }
        
        .content {
            flex: 1;
            padding: 30px;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            color: #666;
            border-top: 1px solid #dee2e6;
            margin-top: 30px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 25px;
            text-align: center;
        }
        
        table { 
            border-collapse: collapse; 
            width: 100%;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        th, td { 
            border: 1px solid #ddd; 
            padding: 12px;
            text-align: left;
        }
        
        th { 
            background-color: #dc3545;
            color: white;
            font-weight: bold;
            text-align: center;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        tr:hover {
            background-color: #f0f0f0;
        }
        
        .view-btn {
            background-color: #dc3545;
            color: white;
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 13px;
            display: inline-block;
        }
        
        .view-btn:hover {
            background-color: #c82333;
        }
        
        .score-cell {
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            color: #2e7d32;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #4CAF50;
            text-decoration: none;
            font-size: 16px;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .pagination {
            text-align: center;
            margin-top: 20px;
        }
        
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 4px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        
        .pagination a:hover {
            background-color: #4CAF50;
            color: white;
        }
        
        .pagination .current {
            background-color: #4CAF50;
            color: white;
            font-weight: bold;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .truncate {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>
    <div class="container" style="background: white; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); overflow: hidden;">
        <div style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 30px; text-align: center;">
            <h1 style="margin: 0; font-size: 2em; color: white;"><img src="../assets/logo.png" alt="AutoScore Logo" style="height: 40px; vertical-align: middle; margin-bottom: 10px;"> Lịch sử chấm điểm</h1>
        </div>
        <div style="background: #f8f9fa; padding: 20px 30px; border-bottom: 2px solid #dee2e6; text-align: center;">
            <a href="index.php" style="color: #dc3545; text-decoration: none; margin: 0 20px; font-weight: 600; display: inline-block;">Chấm điểm mới</a>
            <a href="create_question.php" style="color: #dc3545; text-decoration: none; margin: 0 20px; font-weight: 600; display: inline-block;">Tạo câu hỏi</a>
            <a href="history.php" style="color: #dc3545; text-decoration: none; margin: 0 20px; font-weight: 600; display: inline-block;">Lịch sử</a>
            <a href="documents.php" style="color: #dc3545; text-decoration: none; margin: 0 20px; font-weight: 600; display: inline-block;">Tài liệu</a>
        </div>
        <div style="padding: 30px;">
        
        <?php if (empty($submissions)): ?>
            <div class="no-data">
                <p>Chưa có dữ liệu chấm điểm nào.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%">ID</th>
                        <th style="width: 25%">Đề bài</th>
                        <th style="width: 25%">Bài làm</th>
                        <th style="width: 10%">Điểm</th>
                        <th style="width: 15%">Ngày chấm</th>
                        <th style="width: 10%">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $sub): ?>
                        <tr>
                            <td style="text-align: center;"><?php echo $sub['id']; ?></td>
                            <td><div class="truncate" title="<?php echo htmlspecialchars($sub['requirement']); ?>">
                                <?php echo htmlspecialchars($sub['requirement']); ?>
                            </div></td>
                            <td><div class="truncate" title="<?php echo htmlspecialchars($sub['student_work']); ?>">
                                <?php echo htmlspecialchars($sub['student_work']); ?>
                            </div></td>
                            <td class="score-cell">
                                <?php 
                                if ($sub['score'] !== null) {
                                    echo number_format($sub['score'], 2);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td style="text-align: center;">
                                <?php echo date('d/m/Y H:i', strtotime($sub['created_at'])); ?>
                            </td>
                            <td style="text-align: center;">
                                <a href="result.php?id=<?php echo $sub['id']; ?>" class="view-btn">Xem chi tiết</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1">« Đầu</a>
                        <a href="?page=<?php echo $page - 1; ?>">‹ Trước</a>
                    <?php endif; ?>
                    
                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++): 
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>">Sau ›</a>
                        <a href="?page=<?php echo $totalPages; ?>">Cuối »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        </div>
        <div style="background: #f8f9fa; padding: 20px; text-align: center; color: #666; border-top: 1px solid #dee2e6;">
            <p>&copy; 2025 AutoScore System | Powered by AI</p>
        </div>
    </div>
</body>
</html>