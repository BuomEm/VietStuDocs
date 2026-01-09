<?php
require_once __DIR__ . '/function.php';

if (!function_exists('isUserLoggedIn')) {
    function isUserLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('redirectIfNotLoggedIn')) {
    function redirectIfNotLoggedIn() {
        if(!isUserLoggedIn()) {
            header("Location: /login");
            exit;
        }
    }
}

if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
}

if (!function_exists('getCurrentUsername')) {
    function getCurrentUsername() {
        return $_SESSION['username'] ?? null;
    }
}

if (!function_exists('loginUser')) {
    function loginUser($email, $password) {
        $email = db_escape($email);
        
        $query = "SELECT id, username, password FROM users WHERE email='$email'";
        $user = db_get_row($query);
        
        if($user) {
            if(password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                return true;
            }
        }
        
        return false;
    }
}

if (!function_exists('registerUser')) {
    function registerUser($username, $email, $password) {
        if(strlen($username) < 3) {
            return ['success' => false, 'message' => 'Username must be at least 3 characters'];
        }
        
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        $email = db_escape($email);
        $check = db_num_rows("SELECT id FROM users WHERE email='$email'");
        if($check > 0) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        $hashed_pwd = password_hash($password, PASSWORD_BCRYPT);
        $username = db_escape($username);
        
        $query = "INSERT INTO users (username, email, password) VALUES ('$username', '$email', '$hashed_pwd')";
        
        if(db_query($query)) {
            return ['success' => true, 'message' => 'Registration successful'];
        } else {
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }
}

if (!function_exists('getUserInfo')) {
    function getUserInfo($user_id) {
        $user_id = intval($user_id);
        return db_get_row("SELECT id, username, email, avatar, created_at, verified_documents_count FROM users WHERE id=$user_id");
    }
}

if (!function_exists('updateUserProfile')) {
    function updateUserProfile($user_id, $username, $email) {
        $user_id = intval($user_id);
        $username = db_escape($username);
        $email = db_escape($email);
        
        $result = db_query("UPDATE users SET username='$username', email='$email' WHERE id=$user_id");
        
        if($result) {
            $_SESSION['username'] = $username;
        }
        
        return $result;
    }
}

if (!function_exists('changePassword')) {
    function changePassword($user_id, $old_password, $new_password) {
        $user_id = intval($user_id);
        $user = db_get_row("SELECT password FROM users WHERE id=$user_id");
        
        if(!$user || !password_verify($old_password, $user['password'])) {
            return false;
        }
        
        $hashed_pwd = password_hash($new_password, PASSWORD_BCRYPT);
        return db_query("UPDATE users SET password='$hashed_pwd' WHERE id=$user_id");
    }
}

// ============ ADMIN FUNCTIONS ============

if (!function_exists('getUserRole')) {
    function getUserRole($user_id) {
        $user_id = intval($user_id);
        $user = db_get_row("SELECT role FROM users WHERE id=$user_id");
        return $user['role'] ?? 'user';
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin($user_id = null) {
        if ($user_id === null) {
            return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
        }
        return getUserRole($user_id) === 'admin';
    }
}

if (!function_exists('redirectIfNotAdmin')) {
    function redirectIfNotAdmin() {
        if(!isUserLoggedIn() || !isAdmin(getCurrentUserId())) {
            header("Location: /login");
            exit;
        }
    }
}

if (!function_exists('hasAdminAccess')) {
    function hasAdminAccess() {
        return isUserLoggedIn() && isAdmin(getCurrentUserId());
    }
}

if (!function_exists('updateLastActivity')) {
    function updateLastActivity() {
        if (isUserLoggedIn()) {
            $uid = getCurrentUserId();
            // Use direct query for performance
            db_query("UPDATE users SET last_activity = NOW() WHERE id = $uid");
        }
    }
}
