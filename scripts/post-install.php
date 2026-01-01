#!/usr/bin/env php
<?php
/**
 * Post-install script for Composer
 * Automatically patches vendor libraries for Windows compatibility
 * Reverts patches on Linux/Unix systems
 */

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  Post-Install: Platform-Specific Configuration            ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

$is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$encryption_file = __DIR__ . '/../vendor/minishlink/web-push/src/Encryption.php';

if (!file_exists($encryption_file)) {
    echo "ℹ️  web-push library not found, skipping\n";
    exit(0);
}

// ============================================
// WINDOWS: Apply Patch
// ============================================
if ($is_windows) {
    echo "🪟 Detected: Windows\n";
    echo "🔧 Applying OpenSSL compatibility patch...\n\n";
    
    $content = file_get_contents($encryption_file);
    
    // Detect OpenSSL config path
    $possible_paths = [
        'D:/laragon/bin/php/php-8.3.28-Win32-vs16-x64/extras/ssl/openssl.cnf',
        'C:/laragon/bin/php/php-8.3.28-Win32-vs16-x64/extras/ssl/openssl.cnf',
        'D:/xampp/php/extras/ssl/openssl.cnf',
        'C:/xampp/php/extras/ssl/openssl.cnf',
    ];
    
    $openssl_path = null;
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $openssl_path = $path;
            break;
        }
    }
    
    if (!$openssl_path) {
        echo "⚠️  OpenSSL config not found in standard locations\n";
        echo "   Checked paths:\n";
        foreach ($possible_paths as $path) {
            echo "   - $path\n";
        }
        echo "\n   Please set manually if push notifications don't work.\n";
        exit(0);
    }
    
    // Check if already patched
    if (strpos($content, "'config'") !== false && strpos($content, 'openssl.cnf') !== false) {
        echo "✅ Already patched!\n";
        echo "   Config: $openssl_path\n";
        exit(0);
    }
    
    // Apply patch
    $pattern = "/openssl_pkey_new\(\[\s*'curve_name'\s*=>\s*'prime256v1',\s*'private_key_type'\s*=>\s*OPENSSL_KEYTYPE_EC,\s*\]\)/s";
    
    $replacement = "openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'config'           => '$openssl_path',
        ])";
    
    $new_content = preg_replace($pattern, $replacement, $content, 1, $count);
    
    if ($count > 0) {
        // Create backup
        $backup = $encryption_file . '.original';
        if (!file_exists($backup)) {
            copy($encryption_file, $backup);
            echo "📦 Backup created: Encryption.php.original\n";
        }
        
        file_put_contents($encryption_file, $new_content);
        echo "✅ Successfully patched!\n";
        echo "   Config: $openssl_path\n";
    } else {
        echo "⚠️  Pattern not found - library may have been updated\n";
        echo "   Manual patching may be required\n";
    }
}

// ============================================
// LINUX/UNIX: Revert Patch (if exists)
// ============================================
else {
    echo "🐧 Detected: Linux/Unix\n";
    echo "🔧 Ensuring original library state...\n\n";
    
    $content = file_get_contents($encryption_file);
    $backup = $encryption_file . '.original';
    
    // Check if patched (has 'config' parameter)
    if (strpos($content, "'config'") !== false && strpos($content, 'openssl.cnf') !== false) {
        echo "⚠️  Windows patch detected, reverting...\n";
        
        // Try to restore from backup
        if (file_exists($backup)) {
            copy($backup, $encryption_file);
            echo "✅ Restored from backup\n";
        } else {
            // Revert manually
            $pattern = "/openssl_pkey_new\(\[\s*'curve_name'\s*=>\s*'prime256v1',\s*'private_key_type'\s*=>\s*OPENSSL_KEYTYPE_EC,\s*'config'\s*=>\s*'[^']+',\s*\]\)/s";
            
            $replacement = "openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ])";
            
            $new_content = preg_replace($pattern, $replacement, $content, -1, $count);
            
            if ($count > 0) {
                file_put_contents($encryption_file, $new_content);
                echo "✅ Patch reverted successfully\n";
            } else {
                echo "⚠️  Could not revert automatically\n";
            }
        }
    } else {
        echo "✅ Library is in original state (no patch needed)\n";
    }
    
    echo "\nℹ️  On Linux, OpenSSL works without patches.\n";
}

echo "\n";
echo "✅ Post-install complete!\n";
echo "\n";
