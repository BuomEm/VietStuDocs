<?php
/**
 * Front Controller - Entry point cho toàn bộ Web UI
 */

// Định nghĩa thư mục gốc
define('D_ROOT', dirname(__DIR__));

// Tự động load các Class (PSR-4)
require_once D_ROOT . '/vendor/autoload.php';

// Load cấu hình & helper cũ để đảm bảo không lỗi
require_once D_ROOT . '/config/db.php';
require_once D_ROOT . '/config/function.php';
require_once D_ROOT . '/config/auth.php';
require_once D_ROOT . '/config/points.php';
require_once D_ROOT . '/config/premium.php';

use App\Support\Router;
use App\Controllers\HomeController;
use App\Modules\Users\Controllers\AuthController;
use App\Modules\Users\Controllers\UserController;
use App\Modules\Documents\Controllers\DocumentController;
use App\Modules\Documents\Controllers\SearchController;
use App\Modules\Payments\Controllers\PaymentController;
use App\Modules\Tutors\Controllers\TutorController;
use App\Modules\Admin\Controllers\AdminController;

$router = new Router();

// Định nghĩa các route cơ bản
$router->get('/', function() {
    header("Location: /dashboard");
    exit;
});

$router->get('/dashboard', [HomeController::class, 'dashboard']);
$router->get('/terms', [HomeController::class, 'terms']);
$router->get('/privacy', [HomeController::class, 'privacy']);

// Document Routes
$router->get('/view', [DocumentController::class, 'show']);
$router->get('/upload', [DocumentController::class, 'showUpload']);
$router->post('/upload', [DocumentController::class, 'handleUpload']);
$router->get('/edit-document', [DocumentController::class, 'edit']);
$router->get('/search', [SearchController::class, 'index']);
$router->get('/api/search/suggestions', [SearchController::class, 'suggestions']);
$router->get('/saved', [DocumentController::class, 'saved']);
$router->post('/api/report', [DocumentController::class, 'report']);

// User Routes
$router->get('/profile', [UserController::class, 'profile']);
$router->get('/history', [UserController::class, 'history']);
$router->get('/user_profile', [UserController::class, 'publicProfile']);

// Tutor Routes
$router->get('/tutors/dashboard', [TutorController::class, 'dashboard']);
$router->get('/tutors/apply', [TutorController::class, 'apply']);
$router->get('/tutors/request', [TutorController::class, 'request']);

// Payment Routes
$router->get('/premium', [PaymentController::class, 'premium']);
$router->get('/transaction-details', [PaymentController::class, 'details']);
$router->post('/api/purchase', [PaymentController::class, 'purchase']);

// Admin Routes
$router->get('/admin', [AdminController::class, 'index']);
$router->get('/admin/index', [AdminController::class, 'index']);
$router->get('/admin/documents', [AdminController::class, 'documents']);
$router->get('/admin/pending-docs', [AdminController::class, 'pendingDocs']);
$router->get('/admin/settings', [AdminController::class, 'settings']);
$router->get('/admin/users', [AdminController::class, 'users']);

// Auth Routes
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'handleLogin']);
$router->get('/signup', [AuthController::class, 'showSignup']);
$router->post('/signup', [AuthController::class, 'handleSignup']);
$router->get('/logout', [AuthController::class, 'logout']);

// Chạy router
$router->dispatch();
