<?php
namespace App\Modules\Users\Controllers;

use App\Controllers\Controller;
use App\Support\Database;
use App\Modules\Users\Services\UserService;

/**
 * AuthController - Xử lý Đăng nhập & Đăng ký
 */
class AuthController extends Controller
{
    private $userService;
    private $db;

    public function __construct()
    {
        $this->db = new Database();
        $this->userService = new UserService($this->db);
    }

    public function showLogin()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if ($this->userService->isLoggedIn()) {
            header("Location: /dashboard");
            exit;
        }

        return $this->view('auth.login', [
            'page_title' => 'Đăng nhập',
            'error' => $_SESSION['auth_error'] ?? '',
            'success' => $_SESSION['auth_success'] ?? ''
        ]);
    }

    public function handleLogin()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($this->userService->login($email, $password)) {
            unset($_SESSION['auth_error']);
            header("Location: /dashboard");
            exit;
        } else {
            $_SESSION['auth_error'] = "Email hoặc mật khẩu không chính xác";
            header("Location: /login");
            exit;
        }
    }

    public function showSignup()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if ($this->userService->isLoggedIn()) {
            header("Location: /dashboard");
            exit;
        }

        return $this->view('auth.signup', [
            'page_title' => 'Đăng ký',
            'error' => $_SESSION['auth_error'] ?? '',
            'success' => $_SESSION['auth_success'] ?? ''
        ]);
    }

    public function handleSignup()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        // Logic xử lý đăng ký (Tôi sẽ wrap logic từ signup.php cũ)
        // ... (Validate, Upload Avatar, Gọi Service)
        
        // Tạm thời điều hướng nếu thành công
        $_SESSION['auth_success'] = "Đăng ký thành công!";
        header("Location: /login");
        exit;
    }

    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
        header("Location: /login");
        exit;
    }
}

