<?php
/**
 * AJAX endpoint để sync tài liệu tham khảo lên Vector DB
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../models/config.php';
require_once __DIR__ . '/../managers/QuestionManager.php';
require_once __DIR__ . '/../managers/DocumentManager.php';

Config::initDatabase();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }
    
    $documentIds = null;
    
    if (!empty($_POST['document_ids'])) {
        $documentIds = $_POST['document_ids'];
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['document_ids'])) {
            $documentIds = $input['document_ids'];
        }
    }
    
    if ($documentIds !== null && !is_array($documentIds)) {
        throw new Exception('document_ids phải là một mảng');
    }
    
    $questionManager = new QuestionManager();
    $result = $questionManager->syncDocumentsToVectorDB($documentIds);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => "Đã sync {$result['documents_count']} tài liệu ({$result['chunks_ingested']} chunks) vào Vector DB",
            'synced_count' => $result['documents_count'],
            'chunks_ingested' => $result['chunks_ingested'],
            'timing' => $result['timing'] ?? null
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error']
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
