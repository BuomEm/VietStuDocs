<?php
/**
 * Rate Limiter - Multi-bucket support
 * Per-minute (burst) and per-hour (quota) limits
 */

class RateLimiter {
    private $use_redis = false;
    
    public function __construct() {
        // Check if Redis is available (optional)
        // $this->use_redis = class_exists('Redis');
    }
    
    /**
     * Check rate limit with multiple buckets
     * @param string $identifier API key ID or user ID
     * @param int $per_hour_limit Maximum requests per hour
     * @param int $per_minute_limit Optional: Maximum requests per minute (burst control)
     * @return bool True if allowed, false if exceeded
     */
    public function check($identifier, $per_hour_limit = 100, $per_minute_limit = 20) {
        // Check per-minute limit first (burst control)
        if (!$this->checkBucket($identifier, 'minute', $per_minute_limit)) {
            return false;
        }
        
        // Check per-hour limit (quota)
        if (!$this->checkBucket($identifier, 'hour', $per_hour_limit)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check specific time bucket
     */
    private function checkBucket($identifier, $period, $limit) {
        global $VSD;
        
        $period_seconds = $period === 'minute' ? 60 : 3600;
        $cache_key = "rate_limit:{$identifier}:{$period}:" . floor(time() / $period_seconds);
        
        // Get current count
        $count = $this->getCount($cache_key);
        
        if ($count >= $limit) {
            return false; // Rate limit exceeded
        }
        
        // Increment count
        $this->increment($cache_key, $period_seconds);
        return true;
    }
    
    /**
     * Get current count from cache/database
     */
    private function getCount($cache_key) {
        global $VSD;
        
        // Check if cache table exists
        $cache_exists = $VSD->get_row("SHOW TABLES LIKE 'api_rate_cache'");
        
        if ($cache_exists) {
            $result = $VSD->get_row("
                SELECT value FROM api_rate_cache 
                WHERE cache_key = '" . $VSD->escape($cache_key) . "' 
                AND expires_at > NOW()
            ");
            return intval($result['value'] ?? 0);
        }
        
        // Fallback: create cache entry
        return 0;
    }
    
    /**
     * Increment count in cache/database
     */
    private function increment($cache_key, $ttl_seconds) {
        global $VSD;
        
        // Ensure cache table exists
        $this->ensureCacheTable();
        
        $expires_at = date('Y-m-d H:i:s', time() + $ttl_seconds);
        $cache_key_escaped = $VSD->escape($cache_key);
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE for atomic increment
        $VSD->query("
            INSERT INTO api_rate_cache (cache_key, value, expires_at, created_at) 
            VALUES ('$cache_key_escaped', 1, '$expires_at', NOW())
            ON DUPLICATE KEY UPDATE 
                value = value + 1,
                expires_at = '$expires_at'
        ");
    }
    
    /**
     * Ensure rate cache table exists
     */
    private function ensureCacheTable() {
        global $VSD;
        
        $table_exists = $VSD->get_row("SHOW TABLES LIKE 'api_rate_cache'");
        if (!$table_exists) {
            $VSD->query("
                CREATE TABLE api_rate_cache (
                    cache_key VARCHAR(255) PRIMARY KEY,
                    value INT DEFAULT 1,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME DEFAULT NOW(),
                    INDEX idx_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }
    
    /**
     * Clean expired cache entries (should be called by cron)
     */
    public function cleanExpired() {
        global $VSD;
        $VSD->query("DELETE FROM api_rate_cache WHERE expires_at < NOW()");
    }
}

