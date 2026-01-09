<?php
namespace App\Modules\Users\Controllers;

use App\Controllers\Controller;
use App\Support\Database;
use App\Modules\Users\Services\UserService;

use App\Middleware\AuthMiddleware;

/**
 * UserController - Quản lý thông tin cá nhân và lịch sử
 */
class UserController extends Controller
{
    private $db;
    private $userService;

    public function __construct()
    {
        $this->db = new Database();
        $this->userService = new UserService($this->db);
    }

    public function profile()
    {
        AuthMiddleware::requireLogin();

        $user_id = $_SESSION['user_id'];
        $user_info = $this->userService->getUserInfo($user_id);

        return $this->view('users.profile', [
            'page_title' => 'Hồ sơ của tôi',
            'user' => $user_info
        ]);
    }

    public function history()
    {
        AuthMiddleware::requireLogin();

        $user_id = $_SESSION['user_id'];
        $transactions = $this->db->get_list("SELECT * FROM point_transactions WHERE user_id=$user_id ORDER BY created_at DESC LIMIT 50");

        return $this->view('users.history', [
            'page_title' => 'Lịch sử giao dịch',
            'transactions' => $transactions
        ]);
    }

    public function publicProfile()
    {
        $user_id = intval($_GET['id'] ?? 0);
        $user_info = $this->userService->getUserInfo($user_id);

        if (!$user_info) {
            header("Location: /dashboard");
            exit;
        }

        return $this->view('users.public_profile', [
            'page_title' => 'Hồ sơ: ' . $user_info['username'],
            'user' => $user_info
        ]);
    }
}

