-- API Keys Table
CREATE TABLE IF NOT EXISTS api_keys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    api_key_hash VARCHAR(64) UNIQUE NOT NULL COMMENT 'SHA256 hash of the API key',
    name VARCHAR(255) NOT NULL COMMENT 'Human-readable name for the key',
    description TEXT COMMENT 'Description of what this key is for',
    permissions JSON COMMENT 'Array of permissions: ["documents:read", "documents:write", "*"]',
    rate_limit INT DEFAULT 100 COMMENT 'Requests per hour',
    ip_whitelist JSON COMMENT 'Array of allowed IPs, empty means all IPs allowed',
    status ENUM('active', 'suspended', 'expired') DEFAULT 'active',
    expires_at DATETIME NULL COMMENT 'NULL means never expires',
    last_used_at DATETIME NULL,
    last_ip VARCHAR(45) NULL,
    usage_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_api_key_hash (api_key_hash),
    INDEX idx_status (status),
    INDEX idx_expires (expires_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Logs Table
CREATE TABLE IF NOT EXISTS api_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    api_key_id INT NULL COMMENT 'NULL for session-based requests',
    user_id INT NULL,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    response_code INT NOT NULL,
    error_message TEXT NULL,
    execution_time_ms DECIMAL(10, 2) NULL,
    request_id VARCHAR(32) UNIQUE NOT NULL COMMENT 'UUID for request tracking',
    request_body JSON NULL COMMENT 'Optional: store request body for debugging (be careful with sensitive data)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_api_key_id (api_key_id),
    INDEX idx_user_id (user_id),
    INDEX idx_endpoint (endpoint),
    INDEX idx_response_code (response_code),
    INDEX idx_request_id (request_id),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Rate Cache Table (for rate limiting)
CREATE TABLE IF NOT EXISTS api_rate_cache (
    cache_key VARCHAR(255) PRIMARY KEY,
    value INT DEFAULT 1,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: Clean up expired cache entries (run via cron)
-- DELETE FROM api_rate_cache WHERE expires_at < NOW();

-- Optional: Clean up old logs (keep last 90 days)
-- DELETE FROM api_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

