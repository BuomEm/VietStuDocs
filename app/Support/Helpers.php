<?php
/**
 * Global Helper Functions
 */

if (!function_exists('view')) {
    function view($path, $data = []) {
        extract($data);
        $viewFile = D_ROOT . '/resources/views/' . str_replace('.', '/', $path) . '.php';
        if (file_exists($viewFile)) {
            include $viewFile;
        }
    }
}

if (!function_exists('getSetting')) {
    function getSetting($name, $default = '') {
        global $VSD;
        if (isset($VSD) && method_exists($VSD, 'site')) {
            return $VSD->site($name) ?: $default;
        }
        return $default;
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return $_SESSION['user_id'] ?? null;
    }
}

if (!function_exists('getCurrentUsername')) {
    function getCurrentUsername() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return $_SESSION['username'] ?? null;
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}
