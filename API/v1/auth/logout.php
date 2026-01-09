<?php
/**
 * Logout API (Session-based)
 */

require_once __DIR__ . '/../../core/BaseAPI.php';

class AuthLogoutAPI extends BaseAPI {
    
    protected function getCurrentEndpoint() {
        return 'auth/logout';
    }
    
    public function handle() {
        $this->requireMethod('POST');
        
        // Destroy session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        $this->respondSuccess(['logged_out' => true], 'Logout successful');
    }
}

$api = new AuthLogoutAPI();
$api->handle();

