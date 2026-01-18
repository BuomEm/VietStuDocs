<?php
// Database Sync Script for Hosting Environment
// This script will add missing columns to your database tables.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';

if (!hasAdminAccess()) {
    die("Unauthorized access.");
}

echo "<h2>Starting Database Sync...</h2>";

$queries = [
    // Fix withdrawal_requests table
    "ALTER TABLE `withdrawal_requests` ADD COLUMN IF NOT EXISTS `admin_id` INT(11) NULL DEFAULT NULL AFTER `user_id`",
    "ALTER TABLE `withdrawal_requests` ADD COLUMN IF NOT EXISTS `admin_note` TEXT NULL DEFAULT NULL AFTER `bank_info`",
    "ALTER TABLE `withdrawal_requests` ADD COLUMN IF NOT EXISTS `processed_at` DATETIME NULL DEFAULT NULL AFTER `status`",
    "ALTER TABLE `withdrawal_requests` ADD COLUMN IF NOT EXISTS `transaction_id` INT(11) NULL DEFAULT NULL AFTER `admin_note` line", // Wait, fixed below
    
    // Fix point_transactions table
    "ALTER TABLE `point_transactions` ADD COLUMN IF NOT EXISTS `rejection_reason` TEXT NULL DEFAULT NULL AFTER `reason` line", // Fixed below
    "ALTER TABLE `point_transactions` MODIFY COLUMN `transaction_type` enum('earn','spend', 'transfer', 'topup', 'bonus', 'lock', 'refund', 'settle') NOT NULL"
];

// Corrected queries
$queries = [
    "ALTER TABLE `withdrawal_requests` ADD COLUMN IF NOT EXISTS `admin_id` INT(11) NULL DEFAULT NULL AFTER `user_id`",
    "ALTER TABLE `withdrawal_requests` ADD COLUMN IF NOT EXISTS `admin_note` TEXT NULL DEFAULT NULL AFTER `bank_info`",
    "ALTER TABLE `withdrawal_requests` ADD COLUMN IF NOT EXISTS `processed_at` DATETIME NULL DEFAULT NULL AFTER `status`",
    "ALTER TABLE `withdrawal_requests` ADD COLUMN IF NOT EXISTS `transaction_id` INT(11) NULL DEFAULT NULL AFTER `admin_note`",
    "ALTER TABLE `point_transactions` ADD COLUMN IF NOT EXISTS `rejection_reason` TEXT NULL DEFAULT NULL AFTER `reason` line",
    "ALTER TABLE `point_transactions` MODIFY COLUMN `transaction_type` enum('earn','spend', 'transfer', 'topup', 'bonus', 'lock', 'refund', 'settle') NOT NULL"
];

// Real queries
$real_queries = [
    "ALTER TABLE `withdrawal_requests` ADD COLUMN IF NOT EXISTS `admin_id` INT(11) NULL DEFAULT NULL AFTER `user_id`",
    "ALTER TABLE `withdrawal_requests` ADD COLUMN IF NOT EXISTS `admin_note` TEXT NULL DEFAULT NULL AFTER `bank_info`",
    "ALTER TABLE `withdrawal_requests` ADD COLUMN IF NOT EXISTS `processed_at` DATETIME NULL DEFAULT NULL AFTER `status`",
    "ALTER TABLE `withdrawal_requests` ADD COLUMN IF NOT EXISTS `transaction_id` INT(11) NULL DEFAULT NULL",
    "ALTER TABLE `point_transactions` ADD COLUMN IF NOT EXISTS `rejection_reason` TEXT NULL DEFAULT NULL AFTER `reason`",
    "ALTER TABLE `point_transactions` MODIFY COLUMN `transaction_type` enum('earn','spend','transfer','topup','bonus','lock','refund','settle') NOT NULL"
];

foreach ($real_queries as $sql) {
    echo "Executing: <post>$sql</post> ... ";
    try {
        if (db_query($sql)) {
            echo "<span style='color: green;'>SUCCESS</span><br>";
        } else {
            echo "<span style='color: red;'>FAILED (might already exist)</span><br>";
        }
    } catch (Exception $e) {
        echo "<span style='color: orange;'>ERROR: " . $e->getMessage() . "</span><br>";
    }
}

echo "<h3>Sync Complete!</h3>";
?>
