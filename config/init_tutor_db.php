<?php
require_once __DIR__ . '/db.php';

// Check if running from CLI or Admin
if(php_sapi_name() !== 'cli' && (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id']))) {
    // Basic protection to prevent random execution from web if not admin
    // For now we assume this is run via Run Command or by dev
}

try {
    // Create PDO connection using constants from db.php
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected successfully to database.\n";

    $sql = "
    -- Subscribers/Tutors table
    CREATE TABLE IF NOT EXISTS `tutors` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL UNIQUE,
        `subjects` TEXT NOT NULL COMMENT 'Comma separated subjects e.g. Math, Physics',
        `bio` TEXT,
        `price_basic` INT DEFAULT 20 COMMENT 'Price for basic question',
        `price_standard` INT DEFAULT 50 COMMENT 'Price for standard question',
        `price_premium` INT DEFAULT 100 COMMENT 'Price for premium question',
        `rating` DECIMAL(3,2) DEFAULT 0.00,
        `total_answers` INT DEFAULT 0,
        `status` ENUM('pending', 'active', 'rejected', 'banned') DEFAULT 'pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        INDEX `idx_status` (`status`),
        INDEX `idx_rating` (`rating`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Tutor Requests (Questions) table
    CREATE TABLE IF NOT EXISTS `tutor_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `tutor_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `content` TEXT NOT NULL,
        `package_type` ENUM('basic', 'standard', 'premium') NOT NULL,
        `points_used` INT NOT NULL COMMENT 'Points held in escrow',
        `status` ENUM('pending', 'answered', 'completed', 'cancelled') DEFAULT 'pending',
        `attachment` VARCHAR(255) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`tutor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        INDEX `idx_student` (`student_id`),
        INDEX `idx_tutor` (`tutor_id`),
        INDEX `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Tutor Answers table
    CREATE TABLE IF NOT EXISTS `tutor_answers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `request_id` INT NOT NULL,
        `tutor_id` INT NOT NULL,
        `content` TEXT NOT NULL,
        `attachment` VARCHAR(255) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`request_id`) REFERENCES `tutor_requests`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`tutor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "Tables created successfully.\n";

} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
