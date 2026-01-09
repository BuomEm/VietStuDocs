<?php
namespace App\Modules\Admin\Controllers;

use App\Controllers\Controller;
use App\Support\Database;
use App\Middleware\AuthMiddleware;

/**
 * AdminController - Quản trị hệ thống
 */
class AdminController extends Controller
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
        // Bảo vệ toàn bộ Controller này (Chỉ Admin mới được vào)
        AuthMiddleware::requireAdmin();
    }

    public function index()
    {
        return $this->view('admin.dashboard', [
            'page_title' => 'Admin Dashboard',
            'stats' => [
                'total_docs' => $this->db->num_rows("SELECT id FROM documents"),
                'pending_docs' => $this->db->num_rows("SELECT id FROM documents WHERE status='pending'"),
                'total_users' => $this->db->num_rows("SELECT id FROM users")
            ]
        ]);
    }

    public function documents()
    {
        return $this->view('admin.documents', [
            'page_title' => 'Quản lý tài liệu'
        ]);
    }

    public function pendingDocs()
    {
        return $this->view('admin.pending_docs', [
            'page_title' => 'Tài liệu chờ duyệt'
        ]);
    }

    public function settings()
    {
        return $this->view('admin.settings', [
            'page_title' => 'Cài đặt hệ thống'
        ]);
    }

    public function users()
    {
        return $this->view('admin.users', [
            'page_title' => 'Quản lý người dùng'
        ]);
    }
}

