<?php
namespace App\Middleware;

/**
 * AuthMiddleware - Kiểm tra quyền truy cập tập trung
 */
class AuthMiddleware
{
    public static function requireLogin()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        if (!isset($_SESSION['user_id'])) {
            header("Location: /login");
            exit;
        }
    }

    public static function requireAdmin()
    {
        self::requireLogin();
        // Giả sử role admin lưu trong session hoặc database
        if ($_SESSION['role'] !== 'admin') {
            die("Access Denied: Admin only.");
        }
    }
}

