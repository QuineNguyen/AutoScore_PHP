<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', 'php://stdout');  // <-- đẩy log ra stdout (hiển thị khi chạy `php -S`)

class Config {
    private const DB_HOST = 'localhost';
    private const DB_PORT = '3306';
    private const DB_NAME = 'autoscore';
    private const DB_USER = 'root';
    private const DB_PASS = '123456';
    
    public const WEBHOOK_URL = 'http://127.0.0.1:8002/grade';
    
    public const LLM_API_URL = 'http://127.0.0.1:1234/v1/chat/completions';
    
    public const LLM_MODEL = 'qwen2.5-3b-instruct';
    
    public const REQUEST_TIMEOUT = 120;
    
    private static $pdo = null;
    
    public static function getConnection() {
        if (self::$pdo === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    self::DB_HOST,
                    self::DB_PORT,
                    self::DB_NAME
                );
                
                self::$pdo = new PDO($dsn, self::DB_USER, self::DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                die('Kết nối database thất bại: ' . $e->getMessage());
            }
        }
        
        return self::$pdo;
    }
    
    public static function initDatabase() {
        $pdo = self::getConnection();
        
        $sql = "CREATE TABLE IF NOT EXISTS submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            requirement TEXT NOT NULL COMMENT 'Yêu cầu đề bài / Requirement',
            student_work TEXT NOT NULL COMMENT 'Bài làm của học sinh',
            result TEXT NULL COMMENT 'Kết quả JSON trả về từ n8n AI Agent',
            score DECIMAL(5,2) NULL COMMENT 'Điểm số tổng kết',
            feedback TEXT NULL COMMENT 'Nhận xét từ AI',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Thời gian gửi chấm',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at),
            INDEX idx_score (score)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        
        // Bảng exam_questions - Quản lý câu hỏi tự luận
        $sql = "CREATE TABLE IF NOT EXISTS exam_questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question_text TEXT NOT NULL COMMENT 'Nội dung câu hỏi tự luận',
            model_answer TEXT NULL COMMENT 'Đáp án mẫu (có thể NULL nếu dùng RAG)',
            reference_doc_ids JSON NULL COMMENT 'Mảng ID tài liệu tham khảo cho RAG',
            grading_strategy ENUM('compare_answer', 'rag_verification') NOT NULL COMMENT 'Chiến lược chấm điểm',
            max_score DECIMAL(5,2) DEFAULT 10.00 COMMENT 'Điểm tối đa',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_grading_strategy (grading_strategy),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        
        // Bảng reference_documents - Lưu trữ tài liệu tham khảo cho RAG
        $sql = "CREATE TABLE IF NOT EXISTS reference_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL COMMENT 'Tiêu đề tài liệu',
            file_path VARCHAR(500) NULL COMMENT 'Đường dẫn file gốc',
            file_type VARCHAR(50) NULL COMMENT 'Loại file (pdf, txt, docx)',
            file_size INT NULL COMMENT 'Kích thước file (bytes)',
            metadata JSON NULL COMMENT 'Metadata bổ sung (author, subject, pages, etc)',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_title (title),
            INDEX idx_file_type (file_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        
        // Bảng vector_collections - Quản lý collections cho vector embeddings
        $sql = "CREATE TABLE IF NOT EXISTS vector_collections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) UNIQUE NOT NULL COMMENT 'Tên collection',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        
        // Bảng vector_documents - Lưu trữ documents và embeddings (768 chiều)
        $sql = "CREATE TABLE IF NOT EXISTS vector_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            collection_id INT NOT NULL COMMENT 'ID của collection',
            content LONGTEXT NOT NULL COMMENT 'Nội dung văn bản gốc',
            embedding VECTOR(768) NOT NULL COMMENT 'Vector embedding 768 chiều (lưu dạng JSON array)',
            metadata JSON NULL COMMENT 'Metadata bổ sung',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (collection_id) REFERENCES vector_collections(id) ON DELETE CASCADE,
            INDEX idx_collection_id (collection_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
    }
}
?>