<?php
/**
 * Global Error Handler
 * Catch all errors and exceptions, log them, notify via Telegram, and show a user-friendly error page.
 */

// Only register if not already registered to avoid duplicates
if (!defined('ERROR_HANDLER_REGISTERED')) {
    define('ERROR_HANDLER_REGISTERED', true);

    function systemErrorHandler($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            // This error code is not included in error_reporting
            return;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    function systemExceptionHandler($key) {
        // Log the error
        error_log("Uncaught Exception: " . $key->getMessage() . " in " . $key->getFile() . " on line " . $key->getLine());
        
        // Notify Telegram
        try {
            if (file_exists(__DIR__ . '/../config/telegram_notifications.php')) {
                require_once __DIR__ . '/../config/telegram_notifications.php';
                
                $errorData = [
                    'title' => 'SYSTEM ALERT',
                    'message' => $key->getMessage(),
                    'file' => $key->getFile(),
                    'line' => $key->getLine(),
                    'url' => ($_SERVER['REQUEST_URI'] ?? 'Unknown'),
                    'footer' => 'Vui lòng kiểm tra log hệ thống để biết thêm chi tiết.'
                ];

                // Send notification system_alert
                sendTelegramNotification($errorData, 'system_alert');
            }
        } catch (Exception $e) {
            // If notification fails, just log it
            error_log("Failed to send Telegram error notification: " . $e->getMessage());
        }

        // Redirect to error page if not already there
        if (strpos($_SERVER['REQUEST_URI'], 'error.php') === false && !headers_sent()) {
            // Determine path to error.php
            $path = '/error.php?code=500';
            
            // Check if we are in admin folder
            if (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) {
                 $path = '/error.php?code=500';
            }
            
            header("Location: " . $path);
            exit;
        } else {
             // Fallback if headers sent or already on error page
             echo "<div style='padding: 20px; background: #fee; color: #c00; font-family: sans-serif; text-align: center;'>";
             echo "<h1>System Error</h1>";
             echo "<p>Something went wrong. Please try again later.</p>";
             echo "</div>";
        }
    }
    
    function systemShutdownHandler() {
        $error = error_get_last();
        if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            // Create a fake exception object to reuse the handler
            $e = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
            systemExceptionHandler($e);
        }
    }

    set_error_handler('systemErrorHandler');
    set_exception_handler('systemExceptionHandler');
    register_shutdown_function('systemShutdownHandler');
}
