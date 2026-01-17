<?php
require_once __DIR__ . '/function.php';

function isUserLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirectIfNotLoggedIn() {
    if(!isUserLoggedIn()) {
        header("Location: login");
        exit;
    }
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

function loginUser($identifier, $password) {
    $identifier = db_escape($identifier);
    
    // Support login via email or username
    $query = "SELECT id, username, password FROM users WHERE email='$identifier' OR username='$identifier'";
    $user = db_get_row($query);
    
    if($user) {
        if(password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            /*
            // Update login streak
            require_once __DIR__ . '/streak.php';
            updateLoginStreak($user['id']);
            */
            
            return true;
        }
    }
    
    return false;
}

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

function getUserInfo($user_id) {
    $user_id = intval($user_id);
    return db_get_row("SELECT id, username, email, avatar, created_at, verified_documents_count FROM users WHERE id=$user_id");
}

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

function changePassword($user_id, $old_password, $new_password) {
    $user_id = intval($user_id);
    $user = db_get_row("SELECT password FROM users WHERE id=$user_id");
    
    if(!$user || !password_verify($old_password, $user['password'])) {
        return false;
    }
    
    $hashed_pwd = password_hash($new_password, PASSWORD_BCRYPT);
    return db_query("UPDATE users SET password='$hashed_pwd' WHERE id=$user_id");
}

// ============ ADMIN FUNCTIONS ============

function getUserRole($user_id) {
    $user_id = intval($user_id);
    $user = db_get_row("SELECT role FROM users WHERE id=$user_id");
    return $user['role'] ?? 'user';
}

function isAdmin($user_id) {
    return getUserRole($user_id) === 'admin';
}

function redirectIfNotAdmin() {
    if(!isUserLoggedIn() || !isAdmin(getCurrentUserId())) {
        header("Location: /login");
        exit;
    }
}

function hasAdminAccess() {
    return isUserLoggedIn() && isAdmin(getCurrentUserId());
}

function updateLastActivity() {
    if (isUserLoggedIn()) {
        $uid = getCurrentUserId();
        // Update activity timestamp
        db_query("UPDATE users SET last_activity = NOW() WHERE id = $uid");
    }
}
