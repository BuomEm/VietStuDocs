<?php
/**
 * API Key Generator
 * Tạo và quản lý API keys một cách an toàn
 */

class ApiKeyGenerator {
    /**
     * Generate a new API key
     * @return array ['key' => plain key (show once), 'hash' => hash to store]
     */
    public static function generate() {
        // Generate random 32-byte key (64 hex chars)
        $plain_key = bin2hex(random_bytes(32));
        
        // Hash with server secret (similar to password hashing)
        $server_secret = getenv('API_KEY_SECRET') ?: 'your-server-secret-change-this-in-production';
        $hash = hash_hmac('sha256', $plain_key, $server_secret);
        
        return [
            'key' => $plain_key,
            'hash' => $hash
        ];
    }
    
    /**
     * Verify API key against hash
     * @param string $plain_key Plain API key from request
     * @param string $stored_hash Hash stored in database
     * @return bool
     */
    public static function verify($plain_key, $stored_hash) {
        $server_secret = getenv('API_KEY_SECRET') ?: 'your-server-secret-change-this-in-production';
        $computed_hash = hash_hmac('sha256', $plain_key, $server_secret);
        
        return hash_equals($stored_hash, $computed_hash);
    }
    
    /**
     * Create API key for user
     * @param int $user_id User ID
     * @param string $name Key name
     * @param array $options Options: description, permissions, rate_limit, expires_at, ip_whitelist
     * @return array ['success' => bool, 'api_key' => plain key (only shown once), 'key_id' => int, 'message' => string]
     */
    public static function create($user_id, $name, $options = []) {
        global $VSD;
        
        $user_id = intval($user_id);
        $name = trim($name);
        $description = isset($options['description']) ? trim($options['description']) : '';
        $permissions = isset($options['permissions']) ? json_encode($options['permissions']) : '[]';
        $rate_limit = intval($options['rate_limit'] ?? 100);
        $expires_at = isset($options['expires_at']) ? $options['expires_at'] : null;
        $ip_whitelist = isset($options['ip_whitelist']) && !empty($options['ip_whitelist']) ? json_encode($options['ip_whitelist']) : null;
        
        if (empty($name)) {
            return [
                'success' => false,
                'message' => 'Key name is required'
            ];
        }
        
        // Generate key
        $key_data = self::generate();
        $hash = $key_data['hash'];
        $plain_key = $key_data['key'];
        
        // Insert into database with prepared statement
        $stmt = $VSD->prepare("
            INSERT INTO api_keys (
                user_id, api_key_hash, name, description, permissions,
                rate_limit, ip_whitelist, expires_at, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        
        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $VSD->error()
            ];
        }
        
        // Execute with params
        $result = $stmt->execute([
            $user_id,
            $hash,
            $name,
            $description,
            $permissions,
            $rate_limit,
            $ip_whitelist,
            $expires_at
        ]);
        
        if (!$result) {
            $stmt->close();
            return [
                'success' => false,
                'message' => 'Failed to create API key: ' . $VSD->error()
            ];
        }
        
        $key_id = $VSD->insert_id();
        $stmt->close();
        
        return [
            'success' => true,
            'api_key' => $plain_key, // ONLY shown once!
            'key_id' => $key_id,
            'message' => 'API key created successfully. Save it now - it will not be shown again!'
        ];
    }
    
    /**
     * Revoke API key
     */
    public static function revoke($key_id) {
        global $VSD;
        $key_id = intval($key_id);
        
        $stmt = $VSD->prepare("UPDATE api_keys SET status = 'suspended' WHERE id = ?");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $VSD->error()];
        }
        
        $result = $stmt->execute([$key_id]);
        $stmt->close();
        
        if (!$result) {
            return ['success' => false, 'message' => 'Failed to revoke key'];
        }
        
        return ['success' => true, 'message' => 'API key revoked successfully'];
    }
    
    /**
     * Delete API key
     */
    public static function delete($key_id) {
        global $VSD;
        $key_id = intval($key_id);
        
        $stmt = $VSD->prepare("DELETE FROM api_keys WHERE id = ?");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $VSD->error()];
        }
        
        $result = $stmt->execute([$key_id]);
        $stmt->close();
        
        if (!$result) {
            return ['success' => false, 'message' => 'Failed to delete key'];
        }
        
        return ['success' => true, 'message' => 'API key deleted successfully'];
    }
}

