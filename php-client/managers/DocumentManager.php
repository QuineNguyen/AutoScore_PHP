<?php
require_once __DIR__ . '/../models/config.php';
require_once __DIR__ . '/../utils/PDFExtractor.php';

/**
 * Class DocumentManager
 * Quản lý tài liệu tham khảo (Knowledge Base)
 */
class DocumentManager {
    private $pdo;
    private $uploadDir;
    
    public function __construct() {
        $this->pdo = Config::getConnection();
        $this->uploadDir = __DIR__ . '/uploads/documents/';
        
        // Tạo thư mục upload nếu chưa có
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Upload và lưu tài liệu (hỗ trợ PDF, TXT, DOCX)
     * 
     * @param array $file $_FILES['file']
     * @param string $title Tiêu đề tài liệu
     * @return array ['success' => bool, 'document_id' => int, 'error' => string]
     */
    public function uploadDocument($file, $title) {
        try {
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                return ['success' => false, 'document_id' => 0, 'error' => 'File upload không hợp lệ'];
            }
            
            $fileName = $file['name'];
            $fileTmpPath = $file['tmp_name'];
            $fileSize = $file['size'];
            $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            $allowedTypes = ['pdf', 'txt', 'docx'];
            if (!in_array($fileType, $allowedTypes)) {
                return ['success' => false, 'document_id' => 0, 'error' => 'Chỉ chấp nhận file PDF, TXT, DOCX'];
            }
            
            if ($fileSize > 10 * 1024 * 1024) {
                return ['success' => false, 'document_id' => 0, 'error' => 'File quá lớn (tối đa 10MB)'];
            }
            
            $newFileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $fileName);
            $destPath = $this->uploadDir . $newFileName;
            
            if (!move_uploaded_file($fileTmpPath, $destPath)) {
                return ['success' => false, 'document_id' => 0, 'error' => 'Lỗi di chuyển file'];
            }
            
            $extractResult = $this->extractTextFromFile($destPath, $fileType);
            
            if (!$extractResult['success']) {
                unlink($destPath); // Xóa file nếu không extract được
                return ['success' => false, 'document_id' => 0, 'error' => $extractResult['error']];
            }
            
            $documentId = $this->saveDocument(
                $title,
                $extractResult['text'],
                $destPath,
                $fileType,
                $fileSize,
                $extractResult['metadata']
            );
            
            return [
                'success' => true,
                'document_id' => $documentId,
                'error' => '',
                'text_preview' => mb_substr($extractResult['text'], 0, 200)
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'document_id' => 0, 'error' => 'Lỗi: ' . $e->getMessage()];
        }
    }
    
    /**
     * Extract text từ file theo loại
     */
    private function extractTextFromFile($filePath, $fileType) {
        switch ($fileType) {
            case 'pdf':
                return PDFExtractor::extractText($filePath, null);
                
            case 'txt':
                $text = file_get_contents($filePath);
                return [
                    'success' => true,
                    'text' => $text,
                    'metadata' => ['type' => 'text/plain'],
                    'error' => ''
                ];
                
            case 'docx':
                return [
                    'success' => false,
                    'text' => '',
                    'metadata' => [],
                    'error' => 'DOCX extraction chưa được implement'
                ];
                
            default:
                return [
                    'success' => false,
                    'text' => '',
                    'metadata' => [],
                    'error' => 'Loại file không được hỗ trợ'
                ];
        }
    }
    
    /**
     * Lưu document vào database
     */
    private function saveDocument($title, $content, $filePath, $fileType, $fileSize, $metadata) {
        $sql = "INSERT INTO reference_documents 
                (title, file_path, file_type, file_size, metadata) 
                VALUES (:title, :file_path, :file_type, :file_size, :metadata)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':title' => $title,
            ':file_path' => $filePath,
            ':file_type' => $fileType,
            ':file_size' => $fileSize,
            ':metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE)
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }
    
    /**
     * Lấy document theo ID
     */
    public function getDocumentById($id) {
        $sql = "SELECT * FROM reference_documents WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        $doc = $stmt->fetch();
        if ($doc && $doc['metadata']) {
            $doc['metadata'] = json_decode($doc['metadata'], true);
        }
        
        return $doc ?: null;
    }
    
    /**
     * Lấy danh sách documents
     */
    public function getDocuments($page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT 
                    id, 
                    title,
                    file_type,
                    file_size,
                    created_at
                FROM reference_documents 
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Tìm kiếm documents
     */
    public function searchDocuments($keyword) {
        $sql = "SELECT id, title, 
                       file_type,
                       created_at
                FROM reference_documents 
                WHERE title LIKE :like_keyword
                ORDER BY created_at DESC
                LIMIT 20";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':like_keyword' => '%' . $keyword . '%'
        ]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Xóa document
     */
    public function deleteDocument($id) {
        $doc = $this->getDocumentById($id);
        if (!$doc) {
            return false;
        }
        
        // Xóa file vật lý
        if (file_exists($doc['file_path'])) {
            unlink($doc['file_path']);
        }
        
        // Xóa trong database
        $sql = "DELETE FROM reference_documents WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}
?>
