<?php
require_once __DIR__ . '/function.php';
global $VSD;

// 1. Update users table with last_seen column
try {
    $VSD->query("ALTER TABLE users ADD COLUMN last_seen DATETIME DEFAULT NULL");
} catch (Exception $e) {
    // Column might already exist
}

// 2. Create messages table
$VSD->query("CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  from_user INT,
  to_user INT,
  content TEXT,
  is_read TINYINT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// 3. Create notifications table (for all users)
// Note: We use user_notifications to distinguish from admin_notifications if needed
$VSD->query("CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  type VARCHAR(20),
  ref_id INT,
  message TEXT,
  is_read TINYINT DEFAULT 0,
  is_pushed TINYINT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// 4. Create push_subscriptions table
$VSD->query("CREATE TABLE IF NOT EXISTS push_subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  subscription JSON
)");

echo "Database migration completed.";
?>
