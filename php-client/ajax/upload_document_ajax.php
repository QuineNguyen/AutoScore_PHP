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
    
    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Không có file được upload hoặc upload thất bại');
    }
    
    $file = $_FILES['document_file'];
    
    $title = pathinfo($file['name'], PATHINFO_FILENAME);
    
    $documentManager = new DocumentManager();
    $result = $documentManager->uploadDocument($file, $title);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'document_id' => $result['document_id'],
            'title' => $title,
            'file_type' => strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)),
            'message' => 'Upload thành công!'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception($result['error']);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
