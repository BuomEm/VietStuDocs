<?php
/**
 * Master Setup Script for the Advanced Comment System and General Settings
 * 
 * This script consolidates all previous setup and update scripts into one.
 * It handles:
 * 1. Creation of the main document_comments table.
 * 2. Addition of parent_id (replies) and is_pinned columns.
 * 3. Creation of comment_likes and comment_reports tables.
 * 4. Extension of notifications table for interaction alerts.
 * 5. Update settings table with new configuration keys.
 */

require_once __DIR__ . '/../config/function.php';

if (!isset($VSD)) {
    die("\$VSD object not found. Ensure config/function.php is correct.\n");
}

/**
 * Helper: Check if a column exists in a table
 */
function columnExists($VSD, $table, $column) {
    return $VSD->num_rows("SHOW COLUMNS FROM `$table` LIKE '$column'") > 0;
}

echo "--- STARTING MASTER SYSTEM SETUP ---\n";

// 1. Create or verify main document_comments table
echo "Phase 1: Checking 'document_comments' table...\n";
$sql_main = "CREATE TABLE IF NOT EXISTS `document_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if ($VSD->query($sql_main)) {
    echo "[+] Table 'document_comments' is verified.\n";
} else {
    echo "[!] Error in Phase 1: " . $VSD->error() . "\n";
}

// 2. Add structural updates (Replies and Pinning)
echo "Phase 2: Updating structure for replies and pinning...\n";
if (!columnExists($VSD, 'document_comments', 'parent_id')) {
    $VSD->query("ALTER TABLE `document_comments` ADD COLUMN `parent_id` int(11) DEFAULT NULL AFTER `user_id` ");
    $VSD->query("ALTER TABLE `document_comments` ADD INDEX `idx_parent_id` (`parent_id`)");
    echo "[+] Added 'parent_id' for nested comments.\n";
} else {
    echo "[-] 'parent_id' already exists.\n";
}

if (!columnExists($VSD, 'document_comments', 'is_pinned')) {
    $VSD->query("ALTER TABLE `document_comments` ADD COLUMN `is_pinned` tinyint(1) DEFAULT 0 AFTER `content` ");
    echo "[+] Added 'is_pinned' column.\n";
} else {
    echo "[-] 'is_pinned' already exists.\n";
}

// 3. Create Interaction Tables (Likes & Reports)
echo "Phase 3: Setting up Interaction tables (Likes/Reports)...\n";

$sql_likes = "CREATE TABLE IF NOT EXISTS `comment_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `comment_id` (`comment_id`),
  KEY `user_id` (`user_id`),
  UNIQUE KEY `unique_like` (`comment_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($VSD->query($sql_likes)) echo "[+] Table 'comment_likes' is ready.\n";
else echo "[!] Error 'comment_likes': " . $VSD->error() . "\n";

$sql_reports = "CREATE TABLE IF NOT EXISTS `comment_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `comment_id` (`comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($VSD->query($sql_reports)) echo "[+] Table 'comment_reports' is ready.\n";
else echo "[!] Error 'comment_reports': " . $VSD->error() . "\n";

// 4. Update Notifications table
echo "Phase 4: Enhancing 'notifications' table...\n";
$notif_cols = [
    'type' => "varchar(50) DEFAULT NULL",
    'icon' => "varchar(100) DEFAULT NULL",
    'link' => "varchar(255) DEFAULT NULL"
];
foreach ($notif_cols as $col => $definition) {
    if (!columnExists($VSD, 'notifications', $col)) {
        $VSD->query("ALTER TABLE `notifications` ADD COLUMN `$col` $definition");
        echo "[+] Added '$col' to 'notifications'.\n";
    } else {
        echo "[-] '$col' already exists in 'notifications'.\n";
    }
}

// 5. Update settings table
echo "Phase 5: Updating 'settings' table...\n";

// Ensure settings table exists
$sql_settings = "CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL UNIQUE,
  `value` text,
  `category` varchar(50) DEFAULT 'general',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($VSD->query($sql_settings)) {
    echo "[+] Table 'settings' verified.\n";
} else {
    echo "[!] Error verifying 'settings' table: " . $VSD->error() . "\n";
}

// New settings to insert if not exist
$new_settings = [
    // Streak Rewards
    ['name' => 'streak_reward_1_3', 'value' => '1', 'category' => 'streak'],
    ['name' => 'streak_reward_4', 'value' => '2', 'category' => 'streak'],
    ['name' => 'streak_reward_5_6', 'value' => '1', 'category' => 'streak'],
    ['name' => 'streak_reward_7', 'value' => '3', 'category' => 'streak'],
    // Document Approval Reward
    ['name' => 'reward_points_on_approval', 'value' => 'off', 'category' => 'shop']
];

foreach ($new_settings as $setting) {
    $name = $VSD->escape($setting['name']);
    $value = $VSD->escape($setting['value']);
    $category = $VSD->escape($setting['category']);
    
    // Check if exists
    $exists = $VSD->get_row("SELECT id FROM settings WHERE name='$name'");
    
    if (!$exists) {
        if ($VSD->query("INSERT INTO settings (name, value, category) VALUES ('$name', '$value', '$category')")) {
            echo "[+] Inserted setting '$name' = '$value'\n";
        } else {
            echo "[!] Error inserting setting '$name': " . $VSD->error() . "\n";
        }
    } else {
        echo "[-] Setting '$name' already exists.\n";
    }
}

echo "--- MASTER SETUP COMPLETED SUCCESSFULLY ---\n";
?>
