<?php
namespace App\Modules\Tutors\Controllers;

use App\Controllers\Controller;
use App\Support\Database;
use App\Modules\Users\Services\UserService;
use App\Middleware\AuthMiddleware;

/**
 * TutorController - Quản lý hệ thống Gia sư
 */
class TutorController extends Controller
{
    private $db;
    private $userService;

    public function __construct()
    {
        $this->db = new Database();
        $this->userService = new UserService($this->db);
    }

    public function dashboard()
    {
        return $this->view('tutors.dashboard', [
            'page_title' => 'Thuê Gia Sư'
        ]);
    }

    public function apply()
    {
        AuthMiddleware::requireLogin();
        return $this->view('tutors.apply', [
            'page_title' => 'Đăng ký làm Gia sư'
        ]);
    }

    public function request()
    {
        AuthMiddleware::requireLogin();
        return $this->view('tutors.request', [
            'page_title' => 'Gửi yêu cầu hỗ trợ'
        ]);
    }
}

