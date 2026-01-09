<?php
/**
 * Base API Class
 * Production-ready với security best practices
 */

// Load dependencies
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/RateLimiter.php';

abstract class BaseAPI {
    protected $method;
    protected $auth_type;
    protected $user_id = null;
    protected $user_role = null;
    protected $api_key_id = null;
    protected $api_key_permissions = null;
    protected $rate_limiter;
    protected $request_id;
    protected $start_time;
    protected $requires_auth;
    
    public function __construct($requires_auth = true) {
        try {
            $this->start_time = microtime(true);
            $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $this->request_id = $this->generateRequestId();
            
            // Set request ID header for client tracking
            if (!headers_sent()) {
                header('X-Request-ID: ' . $this->request_id);
            }
            
            // Initialize rate limiter
            try {
                $this->rate_limiter = new RateLimiter();
            } catch (Throwable $e) {
                error_log('RateLimiter initialization failed: ' . $e->getMessage());
                // Continue without rate limiting if it fails
                $this->rate_limiter = null;
            }
            
            $this->requires_auth = $requires_auth;
            
            if ($requires_auth) {
                $this->detectAuthType();
            }
        } catch (Throwable $e) {
            // If constructor fails, return error response
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'code' => 500,
                'error' => [
                    'message' => 'API initialization failed',
                    'type' => 'Initialization Error',
                    'details' => $e->getMessage()
                ],
                'meta' => [
                    'request_id' => $this->request_id ?? bin2hex(random_bytes(16)),
                    'timestamp' => date('c')
                ]
            ]);
            exit;
        }
    }
    
    /**
     * Generate unique request ID for logging
     */
    protected function generateRequestId() {
        return bin2hex(random_bytes(16)); // 32 char hex
    }
    
    /**
     * Detect authentication type (API Key ưu tiên, fallback Session)
     */
    protected function detectAuthType() {
        // API Key: Chỉ nhận qua header (KHÔNG qua query string)
        $api_key = null;
        
        // Check Authorization header (Bearer token)
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
            if (preg_match('/Bearer\s+(.+)/i', $auth_header, $matches)) {
                $api_key = $matches[1];
            }
        }
        
        // Check X-API-Key header
        if (!$api_key && isset($_SERVER['HTTP_X_API_KEY'])) {
            $api_key = $_SERVER['HTTP_X_API_KEY'];
        }
        
        if ($api_key) {
            $this->auth_type = 'api_key';
            $this->authenticateApiKey($api_key);
            return;
        }
        
        // Fallback: Session-based (cho web app)
        // QUAN TRỌNG: session_start() phải gọi TRƯỚC khi đọc $_SESSION
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user_id'])) {
            $this->auth_type = 'session';
            $this->authenticateSession();
            return;
        }
        
        // Không có auth nào
        $this->respondError(401, 'Authentication required. Provide API key in Authorization header or valid session.');
    }
    
    /**
     * Authenticate API Key (với hash verification)
     */
    protected function authenticateApiKey($api_key) {
        global $VSD;
        
        if (empty($api_key) || strlen($api_key) < 32) {
            $this->respondError(401, 'Invalid API key format');
        }
        
        // Hash the provided key để so sánh
        $api_key_hash = hash_hmac('sha256', $api_key, getenv('API_KEY_SECRET') ?: 'your-server-secret-key-change-this');
        
        // Tìm key trong database (so sánh hash) - Dùng prepared statement
        // Lưu ý: VSD->prepare() trả về VSDStmt object
        // Check if users table has status column
        $has_user_status = false;
        try {
            $check_stmt = $VSD->query("SHOW COLUMNS FROM users LIKE 'status'");
            $has_user_status = ($check_stmt && mysqli_num_rows($check_stmt) > 0);
        } catch (Exception $e) {
            // Column doesn't exist or error checking
            $has_user_status = false;
        }
        
        $user_status_select = $has_user_status ? ', u.status as user_status' : '';
        $stmt = $VSD->prepare("
            SELECT ak.*, u.id as user_id, u.username, u.role{$user_status_select}
            FROM api_keys ak
            JOIN users u ON ak.user_id = u.id
            WHERE ak.api_key_hash = ?
            AND ak.status = 'active'
            AND (ak.expires_at IS NULL OR ak.expires_at > NOW())
        ");
        
        if ($stmt) {
            try {
                $stmt->execute([$api_key_hash]);
                $key_data = $stmt->fetch_assoc();
                $stmt->close();
            } catch (Throwable $e) {
                error_log('API Key authentication error: ' . $e->getMessage());
                $key_data = false;
            }
        } else {
            error_log('Failed to prepare API key query: ' . ($VSD->error() ?? 'Unknown error'));
            $key_data = false;
        }
        
        if (!$key_data) {
            $this->logRequest(null, 401, 'Invalid API key');
            $this->respondError(401, 'Invalid or expired API key');
        }
        
        // Check user status (if column exists)
        if (isset($key_data['user_status']) && $key_data['user_status'] !== 'active') {
            $this->logRequest($key_data['id'], 403, 'User account is suspended');
            $this->respondError(403, 'User account is suspended or deleted');
        }
        
        // Check IP allowlist (nếu có)
        if (!empty($key_data['ip_whitelist'])) {
            $allowed_ips = json_decode($key_data['ip_whitelist'], true);
            $client_ip = $this->getClientIP();
            
            if (!empty($allowed_ips) && !in_array($client_ip, $allowed_ips)) {
                $this->logRequest($key_data['id'], 403, 'IP not in whitelist: ' . $client_ip);
                $this->respondError(403, 'IP address not authorized');
            }
        }
        
        // Rate limiting (if rate limiter is available)
        if ($this->rate_limiter) {
            $rate_limit = intval($key_data['rate_limit'] ?? 100);
            if (!$this->rate_limiter->check($key_data['id'], $rate_limit)) {
                $this->logRequest($key_data['id'], 429, 'Rate limit exceeded');
                $this->respondError(429, 'Rate limit exceeded. Maximum ' . $rate_limit . ' requests per hour.');
            }
        }
        
        // Update last used - Dùng prepared statement
        $update_stmt = $VSD->prepare("
            UPDATE api_keys 
            SET last_used_at = NOW(), 
                usage_count = usage_count + 1,
                last_ip = ?
            WHERE id = ?
        ");
        if ($update_stmt) {
            $update_stmt->execute([$this->getClientIP(), $key_data['id']]);
            $update_stmt->close();
        }
        
        $this->user_id = intval($key_data['user_id']);
        $this->user_role = $key_data['role'] ?? 'user';
        $this->api_key_id = intval($key_data['id']);
        $this->api_key_permissions = json_decode($key_data['permissions'] ?? '[]', true);
    }
    
    /**
     * Authenticate Session (web app)
     */
    protected function authenticateSession() {
        if (!isset($_SESSION['user_id'])) {
            $this->respondError(401, 'Session expired or invalid');
        }
        
        global $VSD;
        
        // Verify user still exists and active - Dùng prepared statement
        // Check if status column exists
        $has_user_status = false;
        try {
            $check_stmt = $VSD->query("SHOW COLUMNS FROM users LIKE 'status'");
            $has_user_status = ($check_stmt && mysqli_num_rows($check_stmt) > 0);
        } catch (Exception $e) {
            $has_user_status = false;
        }
        
        $status_select = $has_user_status ? ', status' : '';
        $stmt = $VSD->prepare("SELECT id, role{$status_select} FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch_assoc();
        } else {
            $user = false;
        }
        
        if (!$user) {
            session_destroy();
            $this->respondError(401, 'User account not found');
        }
        
        // Check status only if column exists
        if ($has_user_status && isset($user['status']) && $user['status'] !== 'active') {
            session_destroy();
            $this->respondError(401, 'User account is suspended or deleted');
        }
        
        $this->user_id = intval($user['id']);
        $this->user_role = $user['role'] ?? 'user';
        
        // Session auth có full permissions (web app internal)
    }
    
    /**
     * Get client IP address
     */
    protected function getClientIP() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Check permission (API Key only)
     */
    protected function checkPermission($endpoint, $action = null) {
        // Session auth có full permissions
        if ($this->auth_type === 'session') {
            return true;
        }
        
        // API Key: check permissions
        if (empty($this->api_key_permissions)) {
            return false;
        }
        
        // Wildcard permission
        if (in_array('*', $this->api_key_permissions)) {
            return true;
        }
        
        // Specific permission: "endpoint" or "endpoint:action"
        $permission_key = $endpoint;
        if ($action) {
            $permission_key .= ':' . $action;
        }
        
        return in_array($permission_key, $this->api_key_permissions) || 
               in_array($endpoint . ':*', $this->api_key_permissions);
    }
    
    /**
     * Require specific permission
     */
    protected function requirePermission($endpoint, $action = null) {
        if (!$this->checkPermission($endpoint, $action)) {
            $this->respondError(403, "Insufficient permissions. Required: {$endpoint}" . ($action ? ":{$action}" : ""));
        }
    }
    
    /**
     * Require HTTP method
     */
    protected function requireMethod($method) {
        if ($this->method !== strtoupper($method)) {
            $this->respondError(405, "Method {$this->method} not allowed. Use {$method}");
        }
    }
    
    /**
     * Require user role
     */
    protected function requireRole($roles) {
        $roles = is_array($roles) ? $roles : [$roles];
        if (!in_array($this->user_role, $roles)) {
            $this->respondError(403, 'Insufficient role. Required: ' . implode(', ', $roles));
        }
    }
    
    /**
     * Get request data (GET or POST body)
     */
    protected function getRequestData() {
        if ($this->method === 'GET') {
            return $_GET;
        }
        
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        $raw_input = file_get_contents('php://input');
        
        // JSON
        if (strpos($content_type, 'application/json') !== false) {
            $data = json_decode($raw_input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->respondError(400, 'Invalid JSON: ' . json_last_error_msg());
            }
            return $data ?? [];
        }
        
        // Form data
        if (strpos($content_type, 'application/x-www-form-urlencoded') !== false) {
            parse_str($raw_input, $data);
            return $data ?? [];
        }
        
        // Default: try JSON
        if (!empty($raw_input)) {
            $data = json_decode($raw_input, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data ?? [];
            }
        }
        
        return [];
    }
    
    /**
     * Validate and sanitize input
     */
    protected function validateInput($data, $rules) {
        $errors = [];
        $sanitized = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $required = $rule['required'] ?? false;
            
            // Use default if value not provided
            if (($value === null || $value === '') && isset($rule['default'])) {
                $value = $rule['default'];
            }
            
            // Required check
            if ($required && ($value === null || $value === '')) {
                $errors[$field] = "Field '{$field}' is required";
                continue;
            }
            
            // Skip validation if not required and empty
            if (!$required && ($value === null || $value === '')) {
                $sanitized[$field] = $rule['default'] ?? null;
                continue;
            }
            
            // Type validation
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'int':
                        $value = filter_var($value, FILTER_VALIDATE_INT);
                        if ($value === false) {
                            $errors[$field] = "Field '{$field}' must be an integer";
                            continue 2;
                        }
                        // Min/Max check
                        if (isset($rule['min']) && $value < $rule['min']) {
                            $errors[$field] = "Field '{$field}' must be at least {$rule['min']}";
                            continue 2;
                        }
                        if (isset($rule['max']) && $value > $rule['max']) {
                            $errors[$field] = "Field '{$field}' must not exceed {$rule['max']}";
                            continue 2;
                        }
                        break;
                        
                    case 'string':
                        $value = is_string($value) ? trim($value) : (string)$value;
                        if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                            $errors[$field] = "Field '{$field}' must be at least {$rule['min_length']} characters";
                            continue 2;
                        }
                        if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                            $errors[$field] = "Field '{$field}' must not exceed {$rule['max_length']} characters";
                            continue 2;
                        }
                        break;
                        
                    case 'email':
                        $value = filter_var($value, FILTER_VALIDATE_EMAIL);
                        if ($value === false) {
                            $errors[$field] = "Field '{$field}' must be a valid email";
                            continue 2;
                        }
                        break;
                        
                    case 'enum':
                        if (!in_array($value, $rule['values'] ?? [])) {
                            $errors[$field] = "Field '{$field}' must be one of: " . implode(', ', $rule['values']);
                            continue 2;
                        }
                        break;
                }
            }
            
            $sanitized[$field] = $value;
        }
        
        if (!empty($errors)) {
            $this->respondError(400, 'Validation failed', $errors);
        }
        
        return $sanitized;
    }
    
    /**
     * Success response
     */
    protected function respondSuccess($data, $message = null, $code = 200) {
        $execution_time = round((microtime(true) - $this->start_time) * 1000, 2);
        
        http_response_code($code);
        header('Content-Type: application/json');
        
        $response = [
            'success' => true,
            'code' => $code,
            'data' => $data,
            'meta' => [
                'request_id' => $this->request_id,
                'timestamp' => date('c'),
                'execution_time_ms' => $execution_time,
                'auth_type' => $this->auth_type
            ]
        ];
        
        if ($message) {
            $response['message'] = $message;
        }
        
        // Log success
        $this->logRequest($this->api_key_id, $code, null, $execution_time);
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Error response
     */
    protected function respondError($code, $message, $errors = null) {
        $execution_time = round((microtime(true) - $this->start_time) * 1000, 2);
        
        http_response_code($code);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'code' => $code,
            'error' => [
                'message' => $message,
                'type' => $this->getErrorType($code)
            ],
            'meta' => [
                'request_id' => $this->request_id,
                'timestamp' => date('c'),
                'execution_time_ms' => $execution_time
            ]
        ];
        
        if ($errors) {
            $response['error']['details'] = $errors;
        }
        
        // Log error (không log API key raw)
        $this->logRequest($this->api_key_id, $code, $message, $execution_time);
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Get error type name
     */
    protected function getErrorType($code) {
        $types = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable'
        ];
        return $types[$code] ?? 'Error';
    }
    
    /**
     * Log API request
     */
    protected function logRequest($api_key_id, $response_code, $error_message = null, $execution_time = null) {
        global $VSD;
        
        $endpoint = $this->getCurrentEndpoint();
        $method = $this->method;
        $ip = $this->getClientIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $execution_time = $execution_time ?? round((microtime(true) - $this->start_time) * 1000, 2);
        
        // Chỉ log nếu có API key (session-based có thể skip nếu muốn)
        if ($api_key_id || $this->auth_type === 'session') {
            $stmt = $VSD->prepare("
                INSERT INTO api_logs (
                    api_key_id, user_id, endpoint, method, ip_address, 
                    user_agent, response_code, error_message, execution_time_ms, request_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            if ($stmt) {
                $stmt->execute([
                    $api_key_id,
                    $this->user_id,
                    $endpoint,
                    $method,
                    $ip,
                    $user_agent,
                    $response_code,
                    $error_message,
                    $execution_time,
                    $this->request_id
                ]);
            }
        }
    }
    
    /**
     * Get current endpoint (override in child class)
     */
    abstract protected function getCurrentEndpoint();
}

