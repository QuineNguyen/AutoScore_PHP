<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../models/config.php';
require_once __DIR__ . '/../managers/DocumentManager.php';

Config::initDatabase();

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }
    
    $documentId = isset($_POST['document_id']) ? (int)$_POST['document_id'] : 0;
    
    if ($documentId <= 0) {
        throw new Exception('Document ID không hợp lệ');
    }
    
    $documentManager = new DocumentManager();
    $success = $documentManager->deleteDocument($documentId);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Đã xóa tài liệu thành công'
        ]);
    } else {
        throw new Exception('Không thể xóa tài liệu');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
