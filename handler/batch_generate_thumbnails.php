<?php
// Prevent any output before JSON
// Ensure all existing buffers are cleared and a fresh buffer is started
while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

// Suppress warnings/notices that might break JSON
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0);

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

// Clear any output buffer
ob_clean();

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Check if user is logged in and is admin
if(!isUserLoggedIn()) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = getCurrentUserId();
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role FROM users WHERE id=$user_id"));
if(!$user || $user['role'] !== 'admin') {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required'], JSON_UNESCAPED_UNICODE);
    exit;
}

if($_SERVER["REQUEST_METHOD"] == "GET") {
    // Get list of documents without thumbnails OR missing total_pages
    // Exclude DOCX files that already have converted_pdf_path AND both thumbnail and total_pages
    $query = "
        SELECT 
            d.id,
            d.file_name,
            d.original_name,
            CONCAT('uploads/', d.file_name) as file_path,
            LOWER(SUBSTRING_INDEX(d.file_name, '.', -1)) as file_ext,
            d.converted_pdf_path,
            d.thumbnail,
            d.total_pages
        FROM documents d
        WHERE ((d.thumbnail IS NULL OR d.thumbnail = '') 
           OR (d.total_pages IS NULL OR d.total_pages <= 1))
        AND d.file_name IS NOT NULL
        AND d.file_name != ''
        AND LOWER(SUBSTRING_INDEX(d.file_name, '.', -1)) NOT IN ('jpg', 'jpeg', 'png', 'gif', 'webp')
        ORDER BY d.id DESC
    ";
    
    $result = mysqli_query($conn, $query);
    $documents = [];
    
    while($row = mysqli_fetch_assoc($result)) {
        // Check if file actually exists
        $full_path = __DIR__ . '/../' . $row['file_path'];
        if(!file_exists($full_path)) {
            continue; // Skip if original file doesn't exist
        }
        
        // For DOCX files: 
        
        // - Include DOCX files that DON'T have a PDF file yet (need conversion)
        // - Also include DOCX files that have PDF but missing thumbnail/total_pages (will generate from existing PDF, not convert)
        // - Skip DOCX files that already have PDF AND both thumbnail and total_pages (fully processed)
        if(in_array($row['file_ext'], ['docx', 'doc'])) {
            if(!empty($row['converted_pdf_path'])) {
                $converted_pdf_full_path = __DIR__ . '/../' . $row['converted_pdf_path'];
                if(file_exists($converted_pdf_full_path)) {
                    // This DOCX already has a PDF file
                    // Check if it already has both thumbnail and total_pages
                    if(!empty($row['thumbnail']) && !empty($row['total_pages']) && $row['total_pages'] > 0) {
                        // Fully processed - skip it
                        continue;
                    }
                    // Otherwise, include it (will generate thumbnail from existing PDF in POST, NOT convert again)
                } else {
                    // converted_pdf_path exists in DB but file doesn't exist - include it (needs re-conversion)
                }
            }
            // If no converted_pdf_path, include it (needs conversion)
        }
        
        $documents[] = [
            'id' => (int)$row['id'],
            'file_name' => $row['file_name'],
            'original_name' => $row['original_name'],
            'file_path' => $row['file_path'],
            'file_ext' => $row['file_ext'],
            'total_pages' => (int)$row['total_pages']
        ];
    }
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'documents' => $documents,
        'total' => count($documents)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// POST method: Get document info for thumbnail generation
if($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Check buffer content before cleaning
        $buffer_content = ob_get_contents();
        if ($buffer_content && trim($buffer_content) !== '') {
            error_log("WARNING: Output buffer contains data before ob_clean: " . substr($buffer_content, 0, 200));
        }
        
        // Clear any previous output
        ob_clean();
        
        // Log incoming request
        error_log("Batch thumbnail POST request received at " . date('Y-m-d H:i:s'));
        
        $raw_input = file_get_contents('php://input');
        error_log("Raw POST input: " . $raw_input);
        
        $input = json_decode($raw_input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_msg = 'Invalid JSON input: ' . json_last_error_msg();
            error_log("JSON decode error: " . $error_msg);
            throw new Exception($error_msg);
        }
        
        $doc_id = isset($input['doc_id']) ? intval($input['doc_id']) : 0;
        error_log("Processing doc_id: " . $doc_id);
        
        if($doc_id <= 0) {
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid document ID'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Get document info
        $doc_query = mysqli_query($conn, "
            SELECT 
                d.id,
                d.file_name,
                d.original_name,
                CONCAT('uploads/', d.file_name) as file_path,
                LOWER(SUBSTRING_INDEX(d.file_name, '.', -1)) as file_ext,
                d.total_pages,
                d.converted_pdf_path
            FROM documents d
            WHERE d.id = $doc_id
        ");
        
        if(!$doc_query) {
            throw new Exception('Database query failed: ' . mysqli_error($conn));
        }
        
        $doc = mysqli_fetch_assoc($doc_query);
        
        if(!$doc) {
            ob_clean();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Document not found'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Check if file exists
        $full_path = __DIR__ . '/../' . $doc['file_path'];
        if(!file_exists($full_path)) {
            ob_clean();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'File not found'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    
        // For DOCX files, check if converted_pdf_path exists, otherwise convert to PDF, then generate thumbnail
        if(in_array($doc['file_ext'], ['docx', 'doc'])) {
            // Suppress any output from require
            ob_start();
            require_once __DIR__ . '/../config/file.php';
            ob_end_clean();
        
        $converted_pdf_full_path = null;
        $converted_pdf_path = null;
        $used_existing_pdf = false;
        
        // Check if converted_pdf_path already exists and file is available
        if (!empty($doc['converted_pdf_path'])) {
            $existing_pdf_path = __DIR__ . '/../' . $doc['converted_pdf_path'];
            if (file_exists($existing_pdf_path)) {
                // Use existing PDF - no conversion needed
                $converted_pdf_full_path = $existing_pdf_path;
                $converted_pdf_path = $doc['converted_pdf_path'];
                $used_existing_pdf = true;
                error_log("Document {$doc['id']}: Using existing converted PDF at $converted_pdf_path (skipping conversion)");
            } else {
                // converted_pdf_path exists in DB but file doesn't exist - need to convert again
                error_log("Document {$doc['id']}: converted_pdf_path in DB but file not found at $existing_pdf_path - will convert again");
            }
        }
        
        // If no existing PDF, convert DOCX to PDF
        if (!$converted_pdf_full_path) {
            // Convert DOCX to PDF - this will try Adobe API first, then fallback
            // PDF will be saved to uploads/ directory and NOT deleted
            $pdf_filename = $doc['id'] . '_converted_' . time() . '.pdf';
            $conversion_error = '';
            $conversion_result = convertDocxToPdf($full_path, UPLOAD_DIR, $pdf_filename, $conversion_error);
            
            if($conversion_result && isset($conversion_result['pdf_path']) && file_exists($conversion_result['pdf_path'])) {
                $converted_pdf_full_path = $conversion_result['pdf_path'];
                $converted_pdf_path = $conversion_result['pdf_url']; // Relative path for database
                error_log("Document {$doc['id']}: Converted DOCX to PDF at $converted_pdf_path");
            } else {
                // PDF conversion failed
                $error_message = $conversion_error ?: 'PDF conversion failed (Adobe API and fallback methods)';
                
                // Try to get more specific error
                if (!function_exists('curl_init')) {
                    $error_message = 'cURL extension not available';
                } elseif (!file_exists($full_path)) {
                    $error_message = 'DOCX file not found';
                }
                
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'doc_id' => (int)$doc['id'],
                    'file_name' => $doc['file_name'],
                    'original_name' => $doc['original_name'],
                    'file_path' => $doc['file_path'],
                    'file_ext' => $doc['file_ext'],
                    'thumbnail_generated' => false,
                    'skip' => true,
                    'message' => $error_message
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        
        // At this point, we have a PDF (either existing or newly converted)
        // NOTE: Thumbnail generation and page counting are now done client-side using PDF.js
        // We just need to return the converted PDF path for client-side processing
        
        // Only update converted_pdf_path if it's new (not already in database)
        if($converted_pdf_path && !$used_existing_pdf) {
            $converted_pdf_path_escaped = mysqli_real_escape_string($conn, $converted_pdf_path);
            $update_query = "UPDATE documents SET converted_pdf_path = '$converted_pdf_path_escaped' WHERE id = " . (int)$doc['id'];
            mysqli_query($conn, $update_query);
            error_log("Document {$doc['id']}: Updated database with converted_pdf_path = $converted_pdf_path");
        }
        
            // Return the converted PDF path for client-side processing
            // Client will handle thumbnail generation and page counting using PDF.js
            ob_clean();
            
            $response = [
                'success' => true,
                'doc_id' => (int)$doc['id'],
                'file_name' => $doc['file_name'],
                'original_name' => $doc['original_name'],
                'file_path' => $converted_pdf_path, // Return converted PDF path for client-side processing
                'file_ext' => 'pdf', // It's now a PDF
                'thumbnail_generated' => false, // Client will generate it
                'converted_pdf_path' => $converted_pdf_path,
                'used_existing_pdf' => $used_existing_pdf,
                'skip' => false // Not skipped, ready for client-side processing
            ];
            
            error_log("Returning DOCX response: " . json_encode($response));
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
    }
    
        // For PDF files
        // NOTE: Page counting and thumbnail generation are now done client-side using PDF.js
        if($doc['file_ext'] === 'pdf') {
            // Just return the file path for client-side processing
            ob_clean();
            
            $response = [
                'success' => true,
                'doc_id' => (int)$doc['id'],
                'file_name' => $doc['file_name'],
                'original_name' => $doc['original_name'],
                'file_path' => $doc['file_path'],
                'file_ext' => $doc['file_ext'],
                'converted_pdf_path' => null,
                'is_docx_converted' => false,
                'total_pages' => $doc['total_pages'] ?? 0 // Return existing value, client will update if needed
            ];
            
            error_log("Returning PDF response: " . json_encode($response));
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
    
        // For other file types (images, etc.) - skip, already handled server-side
        ob_clean();
        echo json_encode([
            'success' => true,
            'doc_id' => (int)$doc['id'],
            'file_name' => $doc['file_name'],
            'original_name' => $doc['original_name'],
            'file_path' => $doc['file_path'],
            'file_ext' => $doc['file_ext'],
            'skip' => true,
            'message' => 'File type not supported for client-side thumbnail generation'
        ], JSON_UNESCAPED_UNICODE);
        exit;
        
    } catch (Exception $e) {
        // Clear any output
        ob_clean();
        http_response_code(500);
        error_log("Batch thumbnail generation Exception caught: " . $e->getMessage());
        error_log("Exception trace: " . $e->getTraceAsString());
        
        $error_response = [
            'success' => false,
            'message' => 'Server error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        ];
        
        error_log("Returning error response: " . json_encode($error_response));
        echo json_encode($error_response, JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Error $e) {
        // Clear any output
        ob_clean();
        http_response_code(500);
        error_log("Batch thumbnail generation Error caught: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        
        $error_response = [
            'success' => false,
            'message' => 'Fatal error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        ];
        
        error_log("Returning error response: " . json_encode($error_response));
        echo json_encode($error_response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

ob_clean();
http_response_code(405);
ob_clean();
echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
exit;
