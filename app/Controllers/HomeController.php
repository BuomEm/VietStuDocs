<?php
namespace App\Controllers;

use App\Support\Database;
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Users\Services\UserService;

/**
 * HomeController - Sử dụng các Service từ Modules
 */
class HomeController extends Controller
{
    public function dashboard()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        $db = new Database();
        $userService = new UserService($db);
        $docService = new DocumentService($db);
        
        $is_logged_in = $userService->isLoggedIn();
        $user = $userService->getCurrentUser();
        $user_id = $user ? $user['id'] : 0;
        
        // Logic lấy dữ liệu Dashboard (Rút gọn để tập trung vào cấu trúc)
        $public_docs = $db->get_list("SELECT d.*, u.username, u.avatar FROM documents d JOIN users u ON d.user_id = u.id WHERE d.status = 'approved' LIMIT 12");
        $my_docs = $is_logged_in ? $db->get_list("SELECT * FROM documents WHERE user_id=$user_id LIMIT 10") : [];
        
        return $this->view('home.dashboard', [
            'is_logged_in' => $is_logged_in,
            'user_id' => $user_id,
            'my_docs' => $my_docs,
            'public_docs' => $public_docs,
            'page_title' => 'Dashboard'
        ]);
    }

    public function terms()
    {
        return $this->view('home.terms', ['page_title' => 'Điều khoản sử dụng']);
    }

    public function privacy()
    {
        return $this->view('home.privacy', ['page_title' => 'Chính sách bảo mật']);
    }
}
