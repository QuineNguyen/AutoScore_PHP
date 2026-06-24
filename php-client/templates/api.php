<?php
/**
 * REST API Endpoint cho Hệ thống AutoScore
 * 
 * Các endpoint:
 * - POST /api.php?action=grade - Chấm điểm mới
 * - GET /api.php?action=result&id={id} - Lấy kết quả theo ID
 * - GET /api.php?action=list - Lấy danh sách submissions
 * - POST /api.php?action=regrade&id={id} - Chấm lại
 * - DELETE /api.php?action=delete&id={id} - Xóa submission
 * - GET /api.php?action=stats - Thống kê
 */

require_once __DIR__ . '/../models/config.php';
require_once __DIR__ . '/../models/Submission.php';
require_once __DIR__ . '/../services/GradingService.php';

Config::initDatabase();

header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function jsonError($message, $statusCode = 400) {
    jsonResponse([
        'success' => false,
        'error' => $message
    ], $statusCode);
}

$action = $_GET['action'] ?? '';

try {
    $submissionModel = new Submission();
    $gradingService = new GradingService();
    
    switch ($action) {
        
        case 'grade':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonError('Method not allowed. Use POST.', 405);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                $input = $_POST;
            }
            
            $requirement = trim($input['requirement'] ?? '');
            $studentWork = trim($input['student_work'] ?? '');
            
            if (empty($requirement) || empty($studentWork)) {
                jsonError('Missing required fields: requirement, student_work');
            }
            
            // gradeNew tự động tìm question để lấy model_answer
            $result = $gradingService->gradeNew($requirement, $studentWork);
            
            jsonResponse([
                'success' => true,
                'data' => $result
            ], 201);
            break;
        
        case 'result':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                jsonError('Method not allowed. Use GET.', 405);
            }
            
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                jsonError('Invalid or missing ID parameter');
            }
            
            $submission = $submissionModel->getById($id);
            if (!$submission) {
                jsonError('Submission not found', 404);
            }
            
            if (!empty($submission['result'])) {
                $submission['result_parsed'] = json_decode($submission['result'], true);
            }
            
            jsonResponse([
                'success' => true,
                'data' => $submission
            ]);
            break;
        
        case 'list':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                jsonError('Method not allowed. Use GET.', 405);
            }
            
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            
            $submissions = $submissionModel->getList($page, $limit);
            $total = $submissionModel->count();
            $totalPages = ceil($total / $limit);
            
            jsonResponse([
                'success' => true,
                'data' => $submissions,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => $totalPages
                ]
            ]);
            break;
        
        case 'regrade':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonError('Method not allowed. Use POST.', 405);
            }
            
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                jsonError('Invalid or missing ID parameter');
            }
            
            $result = $gradingService->regrade($id);
            
            jsonResponse([
                'success' => true,
                'data' => $result
            ]);
            break;
        
        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonError('Method not allowed. Use DELETE or POST.', 405);
            }
            
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                jsonError('Invalid or missing ID parameter');
            }
            
            $deleted = $submissionModel->delete($id);
            
            if (!$deleted) {
                jsonError('Failed to delete submission or submission not found', 404);
            }
            
            jsonResponse([
                'success' => true,
                'message' => 'Submission deleted successfully'
            ]);
            break;
        
        case 'stats':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                jsonError('Method not allowed. Use GET.', 405);
            }
            
            $stats = $submissionModel->getScoreStatistics();
            
            jsonResponse([
                'success' => true,
                'data' => $stats
            ]);
            break;
        
        case 'docs':
        case '':
            jsonResponse([
                'name' => 'AutoScore API',
                'version' => '1.0',
                'description' => 'REST API for AutoScore grading system',
                'endpoints' => [
                    [
                        'method' => 'POST',
                        'path' => '/api.php?action=grade',
                        'description' => 'Create new submission and grade it (auto-finds model_answer from questions)',
                        'body' => [
                            'requirement' => 'string (required)',
                            'student_work' => 'string (required)'
                        ]
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api.php?action=result&id={id}',
                        'description' => 'Get submission result by ID'
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api.php?action=list&page={page}&limit={limit}',
                        'description' => 'Get list of submissions with pagination'
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api.php?action=regrade&id={id}',
                        'description' => 'Regrade existing submission'
                    ],
                    [
                        'method' => 'DELETE',
                        'path' => '/api.php?action=delete&id={id}',
                        'description' => 'Delete submission by ID'
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api.php?action=stats',
                        'description' => 'Get grading statistics'
                    ]
                ],
                'example_usage' => [
                    'curl_grade' => 'curl -X POST -H "Content-Type: application/json" -d \'{"requirement":"...", "student_work":"..."}\' https://yourdomain.com/api.php?action=grade',
                    'curl_result' => 'curl https://yourdomain.com/api.php?action=result&id=1',
                    'curl_list' => 'curl https://yourdomain.com/api.php?action=list&page=1&limit=10'
                ]
            ]);
            break;
        
        default:
            jsonError('Invalid action. Use: grade, result, list, regrade, delete, stats, docs', 400);
    }
    
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
?>
