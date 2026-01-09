<?php
namespace App\Modules\Users\Services;

use App\Support\Database;

/**
 * User Service - Thuộc Module Users
 */
class UserService
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }

    public function getCurrentUser()
    {
        if (!$this->isLoggedIn()) return null;
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? null
        ];
    }

    public function login($email, $password)
    {
        $email = $this->db->escape($email);
        $user = $this->db->get_row("SELECT id, username, password FROM users WHERE email='$email'");

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            return true;
        }
        return false;
    }
}

