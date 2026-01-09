<?php
/**
 * REST Router
 * Maps REST endpoints to handler classes
 */

class APIRouter {
    private $routes = [];
    private $base_path = '/API/v1';
    private $current_path;
    private $method;
    
    public function __construct() {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }
    
    /**
     * Register route
     */
    public function register($method, $pattern, $handler_class) {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler_class
        ];
    }
    
    /**
     * Dispatch request to handler
     */
    public function dispatch() {
        // Load CORS middleware
        require_once __DIR__ . '/../middleware/cors.php';
        CORSMiddleware::handlePreflight();
        CORSMiddleware::setHeaders();
        
        // Remove base path
        $path = str_replace($this->base_path, '', $this->current_path);
        $path = trim($path, '/');
        $segments = $path ? explode('/', $path) : [];
        
        // Try to match route
        foreach ($this->routes as $route) {
            if ($route['method'] !== $this->method && $route['method'] !== 'ANY') {
                continue;
            }
            
            if ($this->matchRoute($route['pattern'], $segments)) {
                $this->executeHandler($route['handler'], $segments);
                return;
            }
        }
        
        // No route matched - 404
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
                'timestamp' => date('c')
            ]
        ]);
        exit;
    }
    
    /**
     * Match route pattern with segments
     */
    private function matchRoute($pattern, $segments) {
        $pattern_segments = explode('/', trim($pattern, '/'));
        
        if (count($pattern_segments) !== count($segments)) {
            // Check for wildcard/catch-all
            if (end($pattern_segments) === '*') {
                return count($segments) >= count($pattern_segments) - 1;
            }
            return false;
        }
        
        foreach ($pattern_segments as $i => $pattern_seg) {
            // Parameter placeholder (e.g., {id})
            if (preg_match('/^{(\w+)}$/', $pattern_seg, $matches)) {
                continue; // Match any segment
            }
            
            // Exact match required
            if ($pattern_seg !== $segments[$i]) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Execute handler
     */
    private function executeHandler($handler_class, $segments) {
        // Extract parameters from segments
        $params = $this->extractParams($handler_class['pattern'] ?? '', $segments);
        
        // Include handler file
        $handler_file = __DIR__ . '/../v1/' . $handler_class['file'];
        if (!file_exists($handler_file)) {
            http_response_code(500);
            echo json_encode(['error' => 'Handler file not found']);
            exit;
        }
        
        require_once $handler_file;
        
        // Instantiate handler
        $handler_name = $handler_class['class'];
        if (!class_exists($handler_name)) {
            http_response_code(500);
            echo json_encode(['error' => 'Handler class not found']);
            exit;
        }
        
        $handler = new $handler_name();
        
        // Pass parameters
        if (method_exists($handler, 'setParams')) {
            $handler->setParams($params);
        }
        
        // Execute
        if (method_exists($handler, 'handle')) {
            $handler->handle();
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Handler does not implement handle() method']);
            exit;
        }
    }
    
    /**
     * Extract parameters from URL segments
     */
    private function extractParams($pattern, $segments) {
        $params = [];
        $pattern_segments = explode('/', trim($pattern, '/'));
        
        foreach ($pattern_segments as $i => $pattern_seg) {
            if (preg_match('/^{(\w+)}$/', $pattern_seg, $matches)) {
                $param_name = $matches[1];
                $params[$param_name] = $segments[$i] ?? null;
            }
        }
        
        return $params;
    }
}

