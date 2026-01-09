<?php
namespace App\Modules\Payments\Controllers;

use App\Controllers\Controller;
use App\Support\Database;
use App\Modules\Users\Services\UserService;

/**
 * PaymentController - Quản lý Gói Premium và Giao dịch
 */
class PaymentController extends Controller
{
    private $db;
    private $userService;

    public function __construct()
    {
        $this->db = new Database();
        $this->userService = new UserService($this->db);
    }

    public function premium()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $user_id = $this->userService->isLoggedIn() ? $_SESSION['user_id'] : null;

        return $this->view('payments.premium', [
            'page_title' => 'Nâng cấp Premium',
            'is_logged_in' => !!$user_id
        ]);
    }

    public function details()
    {
        AuthMiddleware::requireLogin();
        return $this->view('payments.details', [
            'page_title' => 'Chi tiết giao dịch'
        ]);
    }

    public function purchase()
    {
        header('Content-Type: application/json');
        AuthMiddleware::requireLogin();
        
        $doc_id = intval($_POST['document_id'] ?? 0);
        $user_id = $_SESSION['user_id'];

        // Logic xử lý mua hàng (Sử dụng UserService hoặc DocumentService)
        // ... (Gọi logic từ purchase_handler.php cũ)

        echo json_encode(['success' => true, 'message' => 'Mua tài liệu thành công']);
        exit;
    }
}

