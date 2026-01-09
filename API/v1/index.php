<?php
/**
 * API v1 Entry Point
 * Routes requests to appropriate handlers
 */

// Error handling for API
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, return JSON instead

// Set error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'code' => 500,
        'error' => [
            'message' => 'Internal Server Error',
            'type' => 'Server Error',
            'details' => 'An error occurred while processing your request'
        ],
        'meta' => [
            'request_id' => bin2hex(random_bytes(16)),
            'timestamp' => date('c')
        ]
    ]);
    exit;
}, E_ALL);

// Set exception handler
set_exception_handler(function($exception) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'code' => 500,
        'error' => [
            'message' => 'Internal Server Error',
            'type' => 'Exception',
            'details' => $exception->getMessage()
        ],
        'meta' => [
            'request_id' => bin2hex(random_bytes(16)),
            'timestamp' => date('c'),
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine()
        ]
    ]);
    exit;
});

// Load core files
try {
    require_once __DIR__ . '/../core/BaseAPI.php';
    require_once __DIR__ . '/../middleware/cors.php';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'code' => 500,
        'error' => [
            'message' => 'Failed to load core files',
            'type' => 'Bootstrap Error',
            'details' => $e->getMessage()
        ],
        'meta' => [
            'request_id' => bin2hex(random_bytes(16)),
            'timestamp' => date('c')
        ]
    ]);
    exit;
}

// Handle CORS
try {
    CORSMiddleware::handlePreflight();
    CORSMiddleware::setHeaders();
} catch (Throwable $e) {
    // CORS error shouldn't block request, but log it
    error_log('CORS Error: ' . $e->getMessage());
}

// Get request path
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path = '/API/v1';

// Remove base path
$path = str_replace($base_path, '', $request_uri);
$path = trim($path, '/');

// Remove query string if present
$path = preg_replace('/\?.*$/', '', $path);

// Remove 'api' prefix if present (for /API/v1/api/documents -> documents)
if (strpos($path, 'api/') === 0) {
    $path = substr($path, 4); // Remove 'api/'
}

$segments = $path ? explode('/', $path) : [];

// Debug logging (remove in production)
if (getenv('APP_ENV') === 'development') {
    error_log("API v1 Request: URI={$request_uri}, Path={$path}, Segments=" . json_encode($segments));
}

// Route mapping
$routes = [
    // Documents API (API Key hoặc Session)
    ['method' => 'GET', 'pattern' => 'documents', 'file' => 'api/documents.php', 'class' => 'DocumentsAPI'],
    ['method' => 'GET', 'pattern' => 'documents/{id}', 'file' => 'api/documents.php', 'class' => 'DocumentsAPI'],
    ['method' => 'POST', 'pattern' => 'documents', 'file' => 'api/documents.php', 'class' => 'DocumentsAPI'],
    ['method' => 'PUT', 'pattern' => 'documents/{id}', 'file' => 'api/documents.php', 'class' => 'DocumentsAPI'],
    ['method' => 'PATCH', 'pattern' => 'documents/{id}', 'file' => 'api/documents.php', 'class' => 'DocumentsAPI'],
    ['method' => 'DELETE', 'pattern' => 'documents/{id}', 'file' => 'api/documents.php', 'class' => 'DocumentsAPI'],
    
    // Auth endpoints (Session-based only)
    ['method' => 'POST', 'pattern' => 'auth/login', 'file' => 'auth/login.php', 'class' => 'AuthAPI'],
    ['method' => 'POST', 'pattern' => 'auth/logout', 'file' => 'auth/logout.php', 'class' => 'AuthAPI'],
    ['method' => 'GET', 'pattern' => 'auth/profile', 'file' => 'auth/profile.php', 'class' => 'AuthAPI'],
    
    // User endpoints
    ['method' => 'GET', 'pattern' => 'users/{id}', 'file' => 'api/users.php', 'class' => 'UsersAPI'],
    
    // Categories
    ['method' => 'GET', 'pattern' => 'categories', 'file' => 'api/categories.php', 'class' => 'CategoriesAPI'],
];

// Simple router
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$matched = false;
$params = [];

foreach ($routes as $route) {
    if ($route['method'] !== $method) {
        continue;
    }
    
    $pattern_segments = explode('/', trim($route['pattern'], '/'));
    
    // Check if pattern matches
    if (count($pattern_segments) !== count($segments)) {
        continue;
    }
    
    $match = true;
    $route_params = [];
    
    for ($i = 0; $i < count($pattern_segments); $i++) {
        $pattern_seg = $pattern_segments[$i];
        $request_seg = $segments[$i] ?? null;
        
        // Parameter placeholder
        if (preg_match('/^{(\w+)}$/', $pattern_seg, $matches)) {
            $route_params[$matches[1]] = $request_seg;
            continue;
        }
        
        // Exact match required
        if ($pattern_seg !== $request_seg) {
            $match = false;
            break;
        }
    }
    
    if ($match) {
        $matched = true;
        $params = $route_params;
        
        // Load and execute handler
        $handler_file = __DIR__ . '/' . $route['file'];
        if (!file_exists($handler_file)) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'code' => 500,
                'error' => ['message' => 'Handler file not found', 'type' => 'Internal Error']
            ]);
            exit;
        }
        
        require_once $handler_file;
        
        $handler_class = $route['class'];
        if (!class_exists($handler_class)) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'code' => 500,
                'error' => ['message' => 'Handler class not found', 'type' => 'Internal Error']
            ]);
            exit;
        }
        
        try {
            $handler = new $handler_class();
            
            // Set parameters if method exists
            if (method_exists($handler, 'setParams')) {
                $handler->setParams($params);
            }
            
            // Execute handler
            if (method_exists($handler, 'handle')) {
                $handler->handle();
            } else {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'code' => 500,
                    'error' => ['message' => 'Handler does not implement handle() method', 'type' => 'Internal Error'],
                    'meta' => [
                        'request_id' => bin2hex(random_bytes(16)),
                        'timestamp' => date('c')
                    ]
                ]);
                exit;
            }
        } catch (Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'code' => 500,
                'error' => [
                    'message' => 'Handler execution failed',
                    'type' => 'Execution Error',
                    'details' => $e->getMessage()
                ],
                'meta' => [
                    'request_id' => bin2hex(random_bytes(16)),
                    'timestamp' => date('c'),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ]
            ]);
            exit;
        }
        
        break;
    }
}

// No route matched
if (!$matched) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'code' => 404,
        'error' => [
            'message' => 'Endpoint not found',
            'type' => 'Not Found'
        ],
        'meta' => [
            'request_id' => bin2hex(random_bytes(16)),
            'timestamp' => date('c'),
            'path' => $path
        ]
    ]);
    exit;
}

