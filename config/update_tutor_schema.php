<?php
require_once __DIR__ . '/db.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected. Updating schema...\n";

    // Add rating columns check
    try {
        $pdo->exec("ALTER TABLE tutor_requests ADD COLUMN rating INT NULL, ADD COLUMN review TEXT NULL");
        echo "Columns 'rating' and 'review' added to 'tutor_requests'.\n";
    } catch (PDOException $e) {
        // SQLSTATE[42S21]: Column already exists
        if ($e->getCode() == '42S21') {
            echo "Columns already exist.\n";
        } else {
            echo "Notice: " . $e->getMessage() . "\n";
        }
    }

    // Update status enum
    try {
        // Change to VARCHAR to be flexible, or update ENUM
        $pdo->exec("ALTER TABLE tutor_requests MODIFY COLUMN status ENUM('pending', 'answered', 'completed', 'cancelled', 'disputed') DEFAULT 'pending'");
        echo "Updated 'status' column to include 'disputed'.\n";
    } catch (PDOException $e) {
        echo "Notice (Status Update): " . $e->getMessage() . "\n";
    }

} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
