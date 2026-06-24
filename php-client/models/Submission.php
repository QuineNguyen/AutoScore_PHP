<?php
require_once __DIR__ . '/config.php';

class Submission {
    private $pdo;
    
    public function __construct() {
        $this->pdo = Config::getConnection();
    }
    
    /**
     * Tạo submission mới
     * @param string $requirement Yêu cầu đề bài
     * @param string $studentWork Bài làm của học sinh
     * @param string $rubricText Rubric dạng văn bản
     * @return int ID của submission vừa tạo
     */
    public function create($requirement, $studentWork) {
        $sql = "INSERT INTO submissions (requirement, student_work) 
                VALUES (:requirement, :student_work)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':requirement' => $requirement,
            ':student_work' => $studentWork
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }
    
    /**
     * Cập nhật kết quả chấm điểm
     * @param int $id ID của submission
     * @param string $result JSON kết quả từ AI
     * @param float $score Điểm số
     * @return bool
     */
    public function updateResult($id, $result, $score) {
        $sql = "UPDATE submissions 
                SET result = :result, score = :score, updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':result' => $result,
            ':score' => $score
        ]);
    }
    
    /**
     * Cập nhật kết quả chấm điểm kèm feedback (lưu vào rubric_text)
     * @param int $id ID của submission
     * @param string $result JSON kết quả từ AI
     * @param float $score Điểm số
     * @param string $feedback Nhận xét từ AI (lưu vào rubric_text)
     * @return bool
     */
    public function updateResultWithFeedback($id, $result, $score, $feedback) {
        $sql = "UPDATE submissions 
                SET result = :result, score = :score, feedback = :feedback, updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':result' => $result,
            ':score' => $score,
            ':feedback' => $feedback
        ]);
    }
    
    /**
     * Lấy thông tin submission theo ID
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        $sql = "SELECT * FROM submissions WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Lấy danh sách submissions với phân trang
     * @param int $page Trang hiện tại
     * @param int $limit Số bản ghi mỗi trang
     * @return array
     */
    public function getList($page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT id, 
                       LEFT(requirement, 100) as requirement_preview,
                       score, 
                       created_at,
                       updated_at
                FROM submissions 
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Đếm tổng số submissions
     * @return int
     */
    public function count() {
        $sql = "SELECT COUNT(*) as total FROM submissions";
        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetch();
        return (int)$result['total'];
    }
    
    /**
     * Xóa submission theo ID
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        $sql = "DELETE FROM submissions WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    /**
     * Lấy thống kê điểm số
     * @return array
     */
    public function getScoreStatistics() {
        $sql = "SELECT 
                    COUNT(*) as total_submissions,
                    AVG(score) as average_score,
                    MAX(score) as highest_score,
                    MIN(score) as lowest_score,
                    COUNT(CASE WHEN score >= 8 THEN 1 END) as excellent,
                    COUNT(CASE WHEN score >= 6.5 AND score < 8 THEN 1 END) as good,
                    COUNT(CASE WHEN score >= 5 AND score < 6.5 THEN 1 END) as average,
                    COUNT(CASE WHEN score < 5 THEN 1 END) as poor
                FROM submissions 
                WHERE score IS NOT NULL";
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetch();
    }
}
?>
