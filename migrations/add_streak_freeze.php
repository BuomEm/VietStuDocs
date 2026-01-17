<?php
require_once __DIR__ . '/../config/function.php';

function addStreakFreezeColumn($conn) {
    if (!$conn) {
        echo "✗ Database connection failed.\n";
        return;
    }
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `users` LIKE 'streak_freezes'");
    if (mysqli_num_rows($result) == 0) {
        $sql = "ALTER TABLE `users` ADD COLUMN `streak_freezes` INT DEFAULT 0 COMMENT 'Number of streak protection items'";
        if (mysqli_query($conn, $sql)) {
            echo "✓ Added column: streak_freezes\n";
        } else {
            echo "✗ Error adding streak_freezes: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "ℹ Column streak_freezes already exists\n";
    }
}

addStreakFreezeColumn($VSD->get_conn());
echo "Migration complete.\n";
