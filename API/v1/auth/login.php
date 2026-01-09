<?php
/**
 * Login API (Session-based only)
 */

require_once __DIR__ . '/../../core/BaseAPI.php';
require_once __DIR__ . '/../../../config/auth.php';

class AuthLoginAPI extends BaseAPI {
    
    public function __construct() {
        // Skip parent constructor auth check for login endpoint
        $this->start_time = microtime(true);
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->request_id = $this->generateRequestId();
        header('X-Request-ID: ' . $this->request_id);
        // Don't call detectAuthType() - login is public
    }
    
    protected function generateRequestId() {
        return bin2hex(random_bytes(16));
    }
    
    protected function getCurrentEndpoint() {
        return 'auth/login';
    }
    
    public function handle() {
        $this->requireMethod('POST');
        
        // Session-based login không cần auth trước
        $data = $this->getRequestData();
        
        $validated = $this->validateInput($data, [
            'email' => ['type' => 'email', 'required' => true],
            'password' => ['type' => 'string', 'required' => true, 'min_length' => 6]
        ]);
        
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Attempt login
        if (loginUser($validated['email'], $validated['password'])) {
            // Login successful
            global $VSD;
            
            // Get user info
            $user_stmt = $VSD->prepare("
                SELECT id, username, email, role, avatar, status 
                FROM users 
                WHERE id = ?
            ");
            $user_stmt->execute([$_SESSION['user_id']]);
            $user = $user_stmt->fetch_assoc();
            $user_stmt->close();
            
            if (!$user) {
                session_destroy();
                $this->respondError(401, 'User account not found');
            }
            
            // Check status only if column exists
            if ($has_user_status && isset($user['status']) && $user['status'] !== 'active') {
                session_destroy();
                $this->respondError(401, 'Account is suspended or deleted');
            }
            
            $execution_time = round((microtime(true) - $this->start_time) * 1000, 2);
            
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'code' => 200,
                'data' => [
                    'user' => [
                        'id' => intval($user['id']),
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'avatar_url' => $user['avatar'] ? "/uploads/avatars/{$user['avatar']}" : null
                    ],
                    'session_id' => session_id()
                ],
                'message' => 'Login successful',
                'meta' => [
                    'request_id' => $this->request_id,
                    'timestamp' => date('c'),
                    'execution_time_ms' => $execution_time,
                    'auth_type' => 'session'
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        } else {
            $execution_time = round((microtime(true) - $this->start_time) * 1000, 2);
            
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'code' => 401,
                'error' => [
                    'message' => 'Invalid email or password',
                    'type' => 'Unauthorized'
                ],
                'meta' => [
                    'request_id' => $this->request_id,
                    'timestamp' => date('c'),
                    'execution_time_ms' => $execution_time
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
}

// Login là public endpoint, không cần authentication
$api = new AuthLoginAPI();
$api->handle();

