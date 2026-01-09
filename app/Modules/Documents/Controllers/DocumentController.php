<?php
namespace App\Modules\Documents\Controllers;

use App\Controllers\Controller;
use App\Support\Database;
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Users\Services\UserService;
use App\Middleware\AuthMiddleware;

/**
 * DocumentController - Xử lý hiển thị và tương tác tài liệu
 */
class DocumentController extends Controller
{
    private $db;
    private $docService;
    private $userService;

    public function __construct()
    {
        $this->db = new Database();
        $this->docService = new DocumentService($this->db);
        $this->userService = new UserService($this->db);
    }

    public function show()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        $doc_id = intval($_GET['id'] ?? 0);
        $user_id = $this->userService->isLoggedIn() ? $_SESSION['user_id'] : null;

        // Lấy thông tin tài liệu
        $doc = $this->db->get_row("SELECT d.*, u.username, u.avatar FROM documents d 
                                   JOIN users u ON d.user_id = u.id 
                                   WHERE d.id=$doc_id");

        if (!$doc) {
            http_response_code(404);
            return $this->view('errors.404', ['message' => 'Tài liệu không tồn tại']);
        }

        return $this->view('documents.view', [
            'doc' => $doc,
            'is_logged_in' => !!$user_id,
            'user_id' => $user_id,
            'page_title' => $doc['original_name']
        ]);
    }

    public function showUpload()
    {
        AuthMiddleware::requireLogin();

        $user_id = $_SESSION['user_id'];
        $user_points = $this->userService->getPoints($user_id);
        
        return $this->view('documents.upload', [
            'page_title' => 'Tải lên tài liệu',
            'user_points' => $user_points,
            'pending_count' => $this->db->num_rows("SELECT id FROM documents WHERE user_id=$user_id AND status='pending'"),
            'approved_count' => $this->db->num_rows("SELECT id FROM documents WHERE user_id=$user_id AND status='approved'")
        ]);
    }

    public function handleUpload()
    {
        AuthMiddleware::requireLogin();
        // Logic xử lý upload file
    }

    public function edit()
    {
        AuthMiddleware::requireLogin();
        $doc_id = intval($_GET['id'] ?? 0);
        $user_id = $_SESSION['user_id'];
        
        $doc = $this->db->get_row("SELECT * FROM documents WHERE id=$doc_id AND user_id=$user_id");
        if (!$doc) die("Truy cập bị từ chối hoặc tài liệu không tồn tại.");

        return $this->view('documents.edit', [
            'page_title' => 'Chỉnh sửa tài liệu',
            'doc' => $doc
        ]);
    }

    public function saved()
    {
        AuthMiddleware::requireLogin();

        $user_id = $_SESSION['user_id'];
        $saved_docs = $this->db->get_list("SELECT d.* FROM documents d 
                                           JOIN document_interactions di ON d.id = di.document_id 
                                           WHERE di.user_id=$user_id AND di.type='save' 
                                           ORDER BY di.created_at DESC");

        return $this->view('documents.saved', [
            'page_title' => 'Tài liệu đã lưu',
            'docs' => $saved_docs
        ]);
    }

    public function report()
    {
        header('Content-Type: application/json');
        AuthMiddleware::requireLogin();
        
        $doc_id = intval($_POST['document_id'] ?? 0);
        $reason = $_POST['reason'] ?? '';
        
        // Logic lưu báo cáo vào DB
        $this->db->query("INSERT INTO document_reports (document_id, user_id, reason, created_at) VALUES ($doc_id, {$_SESSION['user_id']}, '{$this->db->escape($reason)}', NOW())");

        echo json_encode(['success' => true, 'message' => 'Đã gửi báo cáo']);
        exit;
    }
}

