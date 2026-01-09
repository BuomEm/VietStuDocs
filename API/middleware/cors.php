<?php
/**
 * CORS Middleware
 * Proper CORS handling with whitelist support
 */

class CORSMiddleware {
    private static $allowed_origins = [];
    private static $config_loaded = false;
    
    /**
     * Load CORS configuration
     */
    private static function loadConfig() {
        if (self::$config_loaded) return;
        
        global $VSD;
        
        // Get allowed origins from settings or env
        $allowed_origins_env = getenv('API_ALLOWED_ORIGINS');
        if ($allowed_origins_env) {
            self::$allowed_origins = explode(',', $allowed_origins_env);
        } else {
            // Default: same origin only
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            self::$allowed_origins = ["{$protocol}://{$host}"];
        }
        
        // Add common localhost origins for development
        // Also add current host with different ports
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Extract hostname and port
        $host_parts = explode(':', $host);
        $hostname = $host_parts[0];
        $port = $host_parts[1] ?? null;
        
        // Add variations for development
        $dev_origins = [
            "{$protocol}://{$hostname}",
            "{$protocol}://{$hostname}:80",
            "{$protocol}://{$hostname}:443",
            'http://localhost',
            'http://localhost:80',
            'http://127.0.0.1',
            'http://127.0.0.1:80'
        ];
        
        if (getenv('APP_ENV') === 'development' || strpos($hostname, 'localhost') !== false || strpos($hostname, '127.0.0.1') !== false) {
            self::$allowed_origins = array_merge(self::$allowed_origins, $dev_origins);
        }
        
        // Remove duplicates
        self::$allowed_origins = array_unique(self::$allowed_origins);
        
        self::$config_loaded = true;
    }
    
    /**
     * Handle CORS preflight request
     */
    public static function handlePreflight() {
        self::loadConfig();
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Check if origin is allowed
        if (!empty($origin) && self::isOriginAllowed($origin)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header("Access-Control-Allow-Credentials: true");
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Request-ID, X-Idempotency-Key');
        header('Access-Control-Max-Age: 86400'); // 24 hours
        header('Access-Control-Expose-Headers: X-Request-ID, X-RateLimit-Remaining, X-RateLimit-Reset');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
    
    /**
     * Set CORS headers for actual request
     */
    public static function setHeaders() {
        self::loadConfig();
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $has_api_key = !empty($_SERVER['HTTP_X_API_KEY']) || 
                      (isset($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Bearer\s+/i', $_SERVER['HTTP_AUTHORIZATION']));
        
        // Get current server origin
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $current_origin = "{$protocol}://{$host}";
        
        // Same-origin request (no origin header) - always allow
        if (empty($origin)) {
            // Same-origin request, no CORS headers needed but set them anyway for consistency
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Request-ID, X-Idempotency-Key');
            header('Access-Control-Expose-Headers: X-Request-ID, X-RateLimit-Remaining, X-RateLimit-Reset');
            return; // Allow same-origin requests
        }
        
        // Cross-origin request - check permissions
        if ($has_api_key) {
            // API Key requests: Allow all origins (server-to-server)
            header("Access-Control-Allow-Origin: {$origin}");
            header("Access-Control-Allow-Credentials: false");
        } else {
            // Session-based requests: Check whitelist
            if (self::isOriginAllowed($origin)) {
                header("Access-Control-Allow-Origin: {$origin}");
                header("Access-Control-Allow-Credentials: true");
            } else {
                // Check if it's same domain (different port or protocol)
                $origin_host = parse_url($origin, PHP_URL_HOST);
                $current_host = parse_url($current_origin, PHP_URL_HOST);
                
                if ($origin_host === $current_host) {
                    // Same domain, different port/protocol - allow
                    header("Access-Control-Allow-Origin: {$origin}");
                    header("Access-Control-Allow-Credentials: true");
                } else {
                    // Reject if origin not in whitelist and not same domain
                    http_response_code(403);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'error' => [
                            'message' => 'Origin not allowed',
                            'type' => 'CORS Error',
                            'origin' => $origin,
                            'allowed_origins' => self::$allowed_origins
                        ]
                    ]);
                    exit;
                }
            }
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Request-ID, X-Idempotency-Key');
        header('Access-Control-Expose-Headers: X-Request-ID, X-RateLimit-Remaining, X-RateLimit-Reset');
    }
    
    /**
     * Check if origin is allowed
     */
    private static function isOriginAllowed($origin) {
        // Allow exact match
        if (in_array($origin, self::$allowed_origins)) {
            return true;
        }
        
        // Allow wildcard subdomains (*.example.com)
        foreach (self::$allowed_origins as $allowed) {
            if (strpos($allowed, '*') !== false) {
                $pattern = str_replace('.', '\.', $allowed);
                $pattern = str_replace('*', '.*', $pattern);
                if (preg_match('/^' . $pattern . '$/', $origin)) {
                    return true;
                }
            }
        }
        
        return false;
    }
}


