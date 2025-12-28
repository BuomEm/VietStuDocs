<?php
session_start();

// Fix relative path - go up one level from handler folder
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';
require_once __DIR__ . '/../config/file.php';
require_once __DIR__ . '/../config/categories.php';

// Check if user is logged in
if(!isUserLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$user_id = getCurrentUserId();
$file = $_FILES['file'];
$document_name = !empty($_POST['document_name']) ? trim($_POST['document_name']) : '';
$description = !empty($_POST['description']) ? mysqli_real_escape_string($conn, trim($_POST['description'])) : '';
$is_public = !empty($_POST['is_public']) && $_POST['is_public'] == 1 ? 1 : 0;

// Validate document name (required, minimum 40 characters)
if(empty($document_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tên tài liệu là bắt buộc']);
    mysqli_close($conn);
    exit;
}

if(mb_strlen($document_name) < 40) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tên tài liệu phải có ít nhất 40 ký tự (hiện tại: ' . mb_strlen($document_name) . ' ký tự)']);
    mysqli_close($conn);
    exit;
}

// Validate description (required)
if(empty($description)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Mô tả tài liệu là bắt buộc']);
    mysqli_close($conn);
    exit;
}

// Get and validate category data (required - new cascade format)
$category_data = null;
if (!empty($_POST['category_data'])) {
    $category_data = json_decode($_POST['category_data'], true);
}

// Validate category data
if (empty($category_data) || empty($category_data['education_level'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vui lòng chọn cấp học']);
    mysqli_close($conn);
    exit;
}

$education_level = $category_data['education_level'];
$is_pho_thong = in_array($education_level, ['tieu_hoc', 'thcs', 'thpt']);

if ($is_pho_thong) {
    if (empty($category_data['grade_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vui lòng chọn lớp']);
        mysqli_close($conn);
        exit;
    }
    if (empty($category_data['subject_code'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vui lòng chọn môn học']);
        mysqli_close($conn);
        exit;
    }
} else {
    if (empty($category_data['major_group_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vui lòng chọn nhóm ngành']);
        mysqli_close($conn);
        exit;
    }
    if (empty($category_data['major_code'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vui lòng chọn ngành học']);
        mysqli_close($conn);
        exit;
    }
}

if (empty($category_data['doc_type_code'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vui lòng chọn loại tài liệu']);
    mysqli_close($conn);
    exit;
}

// Validate and upload file
$upload_result = uploadFile($file);

if(!$upload_result['success']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $upload_result['message'] ?? 'Upload failed']);
    mysqli_close($conn);
    exit;
}

// Insert into database with 'pending' status
// Use the user-provided document name instead of original filename
$original_name = mysqli_real_escape_string($conn, $document_name);
$file_name = $upload_result['file_name'];

// NEW: Insert with status='pending' - awaiting admin review
$query = "INSERT INTO documents (user_id, original_name, file_name, description, is_public, status, created_at) 
         VALUES ('$user_id', '$original_name', '$file_name', '$description', '$is_public', 'pending', NOW())";

if(!mysqli_query($conn, $query)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    mysqli_close($conn);
    exit;
}

$doc_id = mysqli_insert_id($conn);

// Handle DOCX conversion and prepare for client-side processing
// NOTE: Page counting and thumbnail generation for PDF/DOCX now done client-side using PDF.js
$file_path = UPLOAD_DIR . $file_name;
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
$total_pages = 0;
$converted_pdf_path = null;
$converted_pdf_url = null;

if (file_exists($file_path)) {
    // Handle DOCX files: Convert to PDF for preview (view.php uses this)
    // Client-side will handle page counting and thumbnail generation
    if (in_array($file_ext, ['docx', 'doc'])) {
        try {
            // Convert DOCX to PDF - this will try Adobe API first, then fallback
            // PDF will be saved to uploads/ directory for preview purposes
            $pdf_filename = $doc_id . '_converted_' . time() . '.pdf';
            $conversion_result = convertDocxToPdf($file_path, UPLOAD_DIR, $pdf_filename);
            
            if ($conversion_result && isset($conversion_result['pdf_path']) && file_exists($conversion_result['pdf_path'])) {
                $converted_pdf_path = $conversion_result['pdf_url']; // Relative path for database
                error_log("Document $doc_id: DOCX converted to PDF at $converted_pdf_path (client-side will generate thumbnail and count pages)");
            } else {
                error_log("Document $doc_id: PDF conversion failed - client will handle DOCX preview");
            }
        } catch (Exception $e) {
            error_log("Error converting DOCX for document $doc_id: " . $e->getMessage());
        }
    }
    // For PDF files: Skip server-side processing, let client handle
    elseif ($file_ext === 'pdf') {
        error_log("Document $doc_id: PDF file - client-side processing will handle page count and thumbnail");
        // $total_pages and $thumbnail_path remain unset - client will handle
    }
    // For images: Generate thumbnail server-side and set page count to 1
    elseif (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
        $total_pages = 1;
        try {
            $thumbnail_result = generateThumbnail($file_path, $file_ext, $doc_id);
            if ($thumbnail_result) {
                $thumbnail_path = mysqli_real_escape_string($conn, $thumbnail_result);
                error_log("Document $doc_id: Image thumbnail generated at $thumbnail_path");
            }
        } catch (Exception $e) {
            error_log("Error generating image thumbnail for document $doc_id: " . $e->getMessage());
        }
    }
    // For other file types: Try server-side thumbnail generation
    else {
        try {
            $thumbnail_result = generateThumbnail($file_path, $file_ext, $doc_id);
            if ($thumbnail_result) {
                $thumbnail_path = mysqli_real_escape_string($conn, $thumbnail_result);
                error_log("Document $doc_id: Thumbnail generated at $thumbnail_path");
            }
        } catch (Exception $e) {
            error_log("Error generating thumbnail for document $doc_id: " . $e->getMessage());
        }
    }
    
    // Update document with total_pages, thumbnail, and converted_pdf_path
    $update_fields = [];
    if (isset($thumbnail_path) && $thumbnail_path) {
        $thumbnail_path_escaped = mysqli_real_escape_string($conn, $thumbnail_path);
        $update_fields[] = "thumbnail = '$thumbnail_path_escaped'";
    }
    if ($total_pages > 0) {
        $update_fields[] = "total_pages = $total_pages";
    }
    if (isset($converted_pdf_path) && $converted_pdf_path) {
        $converted_pdf_path_escaped = mysqli_real_escape_string($conn, $converted_pdf_path);
        $update_fields[] = "converted_pdf_path = '$converted_pdf_path_escaped'";
    }
    
    if (!empty($update_fields)) {
        $update_query = "UPDATE documents SET " . implode(', ', $update_fields) . " WHERE id = $doc_id";
        $update_result = mysqli_query($conn, $update_query);
        if (!$update_result) {
            error_log("Error updating document $doc_id: " . mysqli_error($conn));
        } else {
            error_log("Document $doc_id: Updated with thumbnail, total_pages" . (isset($converted_pdf_path) ? ", and converted_pdf_path" : ""));
        }
    }
}

// Save document category (new cascade format)
if ($category_data) {
    saveDocumentCategory(
        $doc_id,
        $category_data['education_level'],
        $category_data['grade_id'] ?? null,
        $category_data['subject_code'] ?? null,
        $category_data['major_group_id'] ?? null,
        $category_data['major_code'] ?? null,
        $category_data['doc_type_code']
    );
}

// Create admin notification for new document
$admins = mysqli_query($conn, "SELECT id FROM users WHERE role='admin'");
while($admin = mysqli_fetch_assoc($admins)) {
    mysqli_query($conn, "INSERT INTO admin_notifications (admin_id, notification_type, document_id, message)
                        VALUES ({$admin['id']}, 'new_document', $doc_id, 'New document submitted for review: " . addslashes($original_name) . "')");
}

// Initialize user points if not exists (start with 0 points)
$points_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM user_points WHERE user_id=$user_id"));
if(!$points_check) {
    mysqli_query($conn, "INSERT INTO user_points (user_id, current_points, total_earned, total_spent) VALUES ($user_id, 0, 0, 0)");
}

mysqli_close($conn);

http_response_code(200);
$response = [
    'success' => true, 
    'message' => 'File uploaded successfully! Awaiting admin review.',
    'file_name' => $original_name,
    'uploaded_file_name' => $file_name,
    'file_path' => 'uploads/' . $file_name,
    'doc_id' => $doc_id,
    'file_ext' => $file_ext,
    'total_pages' => $total_pages,
    'status' => 'pending'
];

// Add converted PDF path for DOCX files
if ($converted_pdf_path) {
    $response['converted_pdf_path'] = $converted_pdf_path;
    $response['is_docx_converted'] = true;
}

// Signal to client that it needs to process PDF/DOCX
if ($file_ext === 'pdf' || ($file_ext === 'docx' || $file_ext === 'doc')) {
    $response['needs_client_processing'] = true;
    // Use converted PDF path for DOCX, otherwise use original file path
    $response['pdf_path_for_processing'] = $converted_pdf_path ? $converted_pdf_path : $response['file_path'];
}

echo json_encode($response);
exit;
?>