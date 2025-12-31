<?php
require_once __DIR__ . '/db.php';

function isUserLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirectIfNotLoggedIn() {
    if(!isUserLoggedIn()) {
        header("Location: index");
        exit;
    }
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

function loginUser($email, $password) {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $email = mysqli_real_escape_string($conn, $email);
    
    $query = "SELECT id, username, password FROM users WHERE email='$email'";
    $result = mysqli_query($conn, $query);
    
    if(mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        if(password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            mysqli_close($conn);
            return true;
        }
    }
    
    mysqli_close($conn);
    return false;
}

function registerUser($username, $email, $password) {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if(strlen($username) < 3) {
        return ['success' => false, 'message' => 'Username must be at least 3 characters'];
    }
    
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format'];
    }
    
    $check = mysqli_query($conn, "SELECT id FROM users WHERE email='" . mysqli_real_escape_string($conn, $email) . "'");
    if(mysqli_num_rows($check) > 0) {
        mysqli_close($conn);
        return ['success' => false, 'message' => 'Email already registered'];
    }
    
    $hashed_pwd = password_hash($password, PASSWORD_BCRYPT);
    $username = mysqli_real_escape_string($conn, $username);
    $email = mysqli_real_escape_string($conn, $email);
    
    $query = "INSERT INTO users (username, email, password) VALUES ('$username', '$email', '$hashed_pwd')";
    
    if(mysqli_query($conn, $query)) {
        mysqli_close($conn);
        return ['success' => true, 'message' => 'Registration successful'];
    } else {
        mysqli_close($conn);
        return ['success' => false, 'message' => 'Registration failed'];
    }
}

function getUserInfo($user_id) {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $user_id = intval($user_id);
    
    $result = mysqli_query($conn, "SELECT id, username, email, created_at, verified_documents_count FROM users WHERE id=$user_id");
    $user = mysqli_fetch_assoc($result);
    
    mysqli_close($conn);
    return $user;
}

function updateUserProfile($user_id, $username, $email) {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $user_id = intval($user_id);
    $username = mysqli_real_escape_string($conn, $username);
    $email = mysqli_real_escape_string($conn, $email);
    
    $result = mysqli_query($conn, "UPDATE users SET username='$username', email='$email' WHERE id=$user_id");
    
    if($result) {
        $_SESSION['username'] = $username;
    }
    
    mysqli_close($conn);
    return $result;
}

function changePassword($user_id, $old_password, $new_password) {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $user_id = intval($user_id);
    
    $result = mysqli_query($conn, "SELECT password FROM users WHERE id=$user_id");
    $user = mysqli_fetch_assoc($result);
    
    if(!password_verify($old_password, $user['password'])) {
        mysqli_close($conn);
        return false;
    }
    
    $hashed_pwd = password_hash($new_password, PASSWORD_BCRYPT);
    $update = mysqli_query($conn, "UPDATE users SET password='$hashed_pwd' WHERE id=$user_id");
    
    mysqli_close($conn);
    return $update;
}

// ============ ADMIN FUNCTIONS ============

function getUserRole($user_id) {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $user_id = intval($user_id);
    
    $result = mysqli_query($conn, "SELECT role FROM users WHERE id=$user_id");
    $user = mysqli_fetch_assoc($result);
    
    mysqli_close($conn);
    return $user['role'] ?? 'user';
}

function isAdmin($user_id) {
    return getUserRole($user_id) === 'admin';
}

function redirectIfNotAdmin() {
    if(!isUserLoggedIn() || !isAdmin(getCurrentUserId())) {
        header("Location: ../error?code=session_expired");
        exit;
    }
}

function hasAdminAccess() {
    return isUserLoggedIn() && isAdmin(getCurrentUserId());
}
?>
