<?php
session_start();
require_once 'config/db.php';
require_once 'config/auth.php';
require_once 'config/premium.php';
require_once 'config/file.php';
require_once 'config/points.php';
require_once 'config/categories.php';
require_once 'config/document_stats.php';

// Cho phép xem mà không cần đăng nhập
$user_id = isset($_SESSION['user_id']) ? getCurrentUserId() : null;
$is_premium = $user_id ? isPremium($user_id) : false;
$is_logged_in = isset($_SESSION['user_id']);
$current_page = 'view';

$doc_id = intval($_GET['id'] ?? 0);

// First check if document exists at all (any status)
$check_query = "SELECT d.*, u.username FROM documents d 
                JOIN users u ON d.user_id = u.id 
                WHERE d.id=$doc_id";
$doc_check = mysqli_fetch_assoc(mysqli_query($conn, $check_query));

// Determine error type
$error_type = null;
if(!$doc_check) {
    $error_type = 'not_found';
} elseif($doc_check['status'] !== 'approved' || !$doc_check['is_public']) {
    // Document exists but not approved or not public
    if($doc_check['user_id'] != $user_id) {
        $error_type = 'not_approved';
    }
}

// If error, show error page
if($error_type) {
    header("HTTP/1.0 404 Not Found");
    $page_title = $error_type === 'not_found' ? 'Document Not Found' : 'Document Not Available';
    include 'includes/head.php';
    include 'includes/sidebar.php';
    ?>
    <div class="drawer-content flex flex-col">
        <?php include 'includes/navbar.php'; ?>
        <main class="flex-1 min-h-screen flex items-center justify-center p-6">
            <div class="card bg-base-100 shadow-xl max-w-2xl w-full">
                <div class="card-body text-center">
                    <?php if($error_type === 'not_found'): ?>
                        <i class="fa-solid fa-magnifying-glass text-6xl text-base-content/50 mb-4"></i>
                        <h1 class="text-3xl font-bold mb-4">Tài Liệu Không Được Tìm Thấy</h1>
                        <p class="text-base-content/70 mb-6">
                            Tài liệu bạn đang tìm kiếm không tồn tại hoặc có thể đã bị xóa. 
                            <br>Vui lòng kiểm tra URL và thử lại.
                        </p>
                    <?php else: ?>
                        <i class="fa-regular fa-clock text-6xl text-base-content/50 mb-4"></i>
                        <h1 class="text-3xl font-bold mb-4">Tài Liệu Chưa Sẵn Sàng</h1>
                        <p class="text-base-content/70 mb-4">
                            Tài liệu này hiện không sẵn sàng để xem.
                        </p>
                        <div class="alert alert-warning text-left">
                            <i class="fa-solid fa-triangle-exclamation shrink-0 text-xl"></i>
                            <div>
                                <strong>Trạng Thái:</strong> 
                                <?php if($doc_check['status'] === 'pending'): ?>
                                    <span class="flex items-center gap-2 mt-1">
                                        <i class="fa-regular fa-clock"></i>
                                        Đang duyệt - Tài liệu này đang chờ được phê duyệt.
                                    </span>
                                <?php elseif($doc_check['status'] === 'rejected'): ?>
                                    <span class="flex items-center gap-2 mt-1">
                                        <i class="fa-solid fa-xmark"></i>
                                        Từ Chối - Tài liệu đã bị từ chối.
                                    </span>
                                <?php else: ?>
                                    <span class="flex items-center gap-2 mt-1">
                                        <i class="fa-solid fa-lock"></i>
                                        Riêng tư - Tài liệu này đang ở chế độ riêng tư.
                                    </span>
                                <?php endif; ?>
                                <div class="text-sm mt-2">Chỉ những tài liệu đã được phê duyệt và công khai mới được phép xem.</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="card-actions justify-center gap-4 mt-6">
                        <a href="dashboard.php" class="btn btn-primary">
                            <i class="fa-solid fa-arrow-left"></i>
                            Back to Dashboard
                        </a>
                        <button onclick="history.back()" class="btn btn-ghost">Go Back</button>
                    </div>
                </div>
            </div>
        </main>
        <?php include 'includes/footer.php'; ?>
    </div>
    </div>
    <?php
    mysqli_close($conn);
    exit;
}

// Document is accessible, continue with normal flow
$doc = $doc_check;
$file_path = UPLOAD_DIR . $doc['file_name'];
$file_ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));

// Increment view count (only once per session)
if (!hasViewedInSession($doc_id)) {
    incrementDocumentViews($doc_id);
    markViewedInSession($doc_id);
}

// Load document categories (new format)
$doc_category = getDocumentCategoryWithNames($doc_id);

// Check if user has purchased the document (or is owner)
$has_purchased = false;
if($user_id) {
    $has_purchased = canUserDownloadDocument($user_id, $doc_id);
} else {
    // Free documents (no price set) can be viewed fully
    $doc_points = getDocumentPoints($doc_id);
    if($doc_points && ($doc_points['user_price'] == 0 || $doc_points['admin_points'] == 0)) {
        $has_purchased = true; // Free document
    }
}

if(!file_exists($file_path)) {
    header("HTTP/1.0 404 Not Found");
    $page_title = 'File Not Found - DocShare';
    include 'includes/head.php';
    include 'includes/sidebar.php';
    ?>
    <div class="drawer-content flex flex-col">
        <?php include 'includes/navbar.php'; ?>
        <main class="flex-1 min-h-screen flex items-center justify-center p-6">
            <div class="card bg-base-100 shadow-xl max-w-2xl w-full">
                <div class="card-body text-center">
                    <div class="flex justify-center mb-4">
                        <i class="fa-solid fa-folder-open text-6xl text-base-content/50"></i>
                    </div>
                    <h1 class="text-3xl font-bold mb-4">File Not Found</h1>
                    <p class="text-base-content/70 mb-6">
                        The document file has been removed or is missing from the server.
                    </p>
                    <div class="card-actions justify-center">
                        <a href="dashboard.php" class="btn btn-primary">
                            <i class="fa-solid fa-arrow-left"></i>
                            Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </main>
        <?php include 'includes/footer.php'; ?>
    </div>
    </div>
    <?php
    mysqli_close($conn);
    exit;
}

// Handle actions (like, dislike, report, save)
if($_SERVER["REQUEST_METHOD"] == "POST" && $is_logged_in) {
    $action = $_POST['action'] ?? '';
    
    if($action === 'like' || $action === 'dislike') {
        // Toggle like/dislike
        $existing = mysqli_fetch_assoc(mysqli_query($conn, 
            "SELECT * FROM document_interactions WHERE document_id=$doc_id AND user_id=$user_id AND type='$action'"));
        
        if($existing) {
            mysqli_query($conn, "DELETE FROM document_interactions WHERE id={$existing['id']}");
        } else {
            // Remove opposite reaction if exists
            mysqli_query($conn, "DELETE FROM document_interactions WHERE document_id=$doc_id AND user_id=$user_id");
            // Add new reaction
            mysqli_query($conn, "INSERT INTO document_interactions (document_id, user_id, type) VALUES ($doc_id, $user_id, '$action')");
        }
        echo json_encode(['success' => true]);
        exit;
    }
    
    if($action === 'save') {
        // Toggle save
        $existing = mysqli_fetch_assoc(mysqli_query($conn, 
            "SELECT * FROM document_interactions WHERE document_id=$doc_id AND user_id=$user_id AND type='save'"));
        
        if($existing) {
            mysqli_query($conn, "DELETE FROM document_interactions WHERE id={$existing['id']}");
            echo json_encode(['success' => true, 'saved' => false]);
        } else {
            mysqli_query($conn, "INSERT INTO document_interactions (document_id, user_id, type) VALUES ($doc_id, $user_id, 'save')");
            echo json_encode(['success' => true, 'saved' => true]);
        }
        exit;
    }
    
    if($action === 'report') {
        $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? 'Inappropriate content');
        mysqli_query($conn, "INSERT INTO document_reports (document_id, user_id, reason) VALUES ($doc_id, $user_id, '$reason')");
        echo json_encode(['success' => true, 'message' => 'Report submitted']);
        exit;
    }
}

// Get interaction stats
$likes = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM document_interactions WHERE document_id=$doc_id AND type='like'"));
$dislikes = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM document_interactions WHERE document_id=$doc_id AND type='dislike'"));
$saves = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM document_interactions WHERE document_id=$doc_id AND type='save'"));

// Get user's current reactions
$user_reaction = null;
$user_saved = false;
if($is_logged_in) {
    $reaction = mysqli_fetch_assoc(mysqli_query($conn, 
        "SELECT type FROM document_interactions WHERE document_id=$doc_id AND user_id=$user_id AND type IN ('like', 'dislike')"));
    $user_reaction = $reaction['type'] ?? null;
    
    $saved = mysqli_fetch_assoc(mysqli_query($conn, 
        "SELECT * FROM document_interactions WHERE document_id=$doc_id AND user_id=$user_id AND type='save'"));
    $user_saved = $saved ? true : false;
}

// Get category IDs for current document
$category_ids = [];
if($doc_category) {
    $cats_array = [['name' => $doc_category['education_level_name']]];
    foreach($cats_array as $cats) {
        foreach($cats as $cat) {
            $category_ids[] = intval($cat['id']);
        }
    }
}

// Get documents from same categories (More from Category)
$category_docs = [];
if(!empty($category_ids)) {
    $ids_str = implode(',', $category_ids);
    $category_query = "
        SELECT DISTINCT d.*, u.username
        FROM documents d
        JOIN users u ON d.user_id = u.id
        JOIN document_categories dc ON d.id = dc.document_id
        WHERE dc.category_id IN ($ids_str)
        AND d.id != $doc_id
        AND d.status = 'approved'
        AND d.is_public = TRUE
        ORDER BY d.created_at DESC
        LIMIT 8
    ";
    $category_result = mysqli_query($conn, $category_query);
    while($row = mysqli_fetch_assoc($category_result)) {
        $category_docs[] = $row;
    }
}

// Get similar documents for "Recommended for you" (only approved)
$similar_docs_result = mysqli_query($conn, "
    SELECT d.*, u.username FROM documents d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.is_public = TRUE AND d.status = 'approved' AND d.id != $doc_id
    ORDER BY (d.views + d.downloads * 2) DESC, d.created_at DESC
    LIMIT 8
");
$similar_docs = [];
while($row = mysqli_fetch_assoc($similar_docs_result)) {
    $similar_docs[] = $row;
}

// Handle download
if(isset($_GET['download'])) {
    if(!$is_logged_in) {
        header("HTTP/1.0 403 Forbidden");
        die("Please login to download");
    }
    
    // Check if user has purchased the document
    if(!$has_purchased) {
        header("HTTP/1.0 403 Forbidden");
        die("You must purchase this document to download");
    }
    
    // Increment download count every time download button is clicked
    incrementDocumentDownloads($doc_id);
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($doc['original_name']) . '"');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit;
}
?>
<?php
$page_title = htmlspecialchars($doc['original_name']) . ' - DocShare';
include 'includes/head.php';
include 'includes/sidebar.php';
?>
<!-- PDF.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

<!-- Shared PDF functions for page counting and thumbnail generation -->
<script src="js/pdf-functions.js"></script>

<!-- DOCX Preview Library - docx-preview with pagination support -->
<!-- JSZip is required dependency for docx-preview -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/docx-preview@0.1.4/dist/docx-preview.min.js"></script>
    
    <style>
        /* PDF Viewer */
        
        .pdf-viewer {
            width: 100%;
            height: 600px;
            background: #f0f0f0;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }
        
        .pdf-page {
            margin: 10px 0;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .pdf-page canvas {
            max-width: 100%;
            display: block;
        }
        
        .image-viewer {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            min-height: 500px;
        }
        
        .image-viewer img {
            max-width: 100%;
            max-height: 100%;
        }
        
        .text-viewer {
            padding: 30px;
            background: white;
            overflow-y: auto;
            max-height: 800px;
        }
        
        .text-viewer pre {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .docx-viewer {
            width: 100%;
            padding: 30px;
            background: #f5f5f5;
            overflow-y: auto;
            min-height: 500px;
            position: relative;
        }
        
        .docx-viewer .docx-wrapper {
            max-width: 100%;
            background: transparent;
            margin: 0 auto;
        }
        
        /* Override docx-preview library default padding */
        .docx-wrapper-wrapper {
            padding: 5px !important;
        }
        
        /* Page styling for DOCX with clear pagination */
        .docx-viewer .docx-page {
            page-break-after: always;
            page-break-inside: avoid;
            min-height: 800px;
            padding: 40px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 4px;
        }
        
        .docx-viewer .docx-page:last-child {
            page-break-after: auto;
        }
        
        /* Protection styles - prevent copy and screenshot */
        #documentViewer {
            position: relative;
        }
        
        .protected {
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            -webkit-touch-callout: none;
            -webkit-tap-highlight-color: transparent;
        }
        
        .protected * {
            user-select: none !important;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
        }
        
        .protected img,
        .protected canvas {
            pointer-events: none;
            -webkit-user-drag: none;
            -khtml-user-drag: none;
            -moz-user-drag: none;
            -o-user-drag: none;
        }
        
        /* Watermark overlay */
        .watermark-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            z-index: 1000;
            background-image: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 100px,
                rgba(0,0,0,0.02) 100px,
                rgba(0,0,0,0.02) 200px
            );
        }
        
        .watermark-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 48px;
            color: rgba(0,0,0,0.05);
            white-space: nowrap;
            font-weight: bold;
            pointer-events: none;
        }
        
        /* Preview limit warning */
        .preview-limit-warning {
            background: #fff3cd;
            border: 2px solid #ff9800;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .preview-limit-warning h3 {
            color: #856404;
            margin-bottom: 10px;
        }
        
        .preview-limit-warning p {
            color: #856404;
            margin: 5px 0;
        }
        
        .preview-limit-warning .btn-purchase {
            display: inline-block;
            margin-top: 15px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .preview-limit-warning .btn-purchase:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        /* Blur effect for pages after limit */
        .docx-viewer .docx-page.page-limit-blur {
            filter: blur(5px) !important;
            opacity: 0.3 !important;
            pointer-events: none !important;
            position: relative !important;
        }
        
        .docx-viewer .docx-wrapper .page-limit-blur {
            filter: blur(5px) !important;
            opacity: 0.3 !important;
            pointer-events: none !important;
            position: relative !important;
        }
        
        .pdf-page.page-limit-blur {
            filter: blur(5px);
            opacity: 0.3;
            pointer-events: none;
            position: relative;
        }
        
        .pdf-page.page-limit-blur::after {
            content: 'Mua tài liệu để xem đầy đủ';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255,255,255,0.9);
            padding: 20px;
            border-radius: 8px;
            font-weight: bold;
            color: #333;
            z-index: 10;
        }
        
        
        /* Share Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-overlay.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 400px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .share-links {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .share-links a {
            flex: 1;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 4px;
            text-align: center;
            text-decoration: none;
            color: #333;
            font-weight: 600;
            font-size: 12px;
            transition: background 0.3s;
        }
        
        .share-links a:hover {
            background: #667eea;
            color: white;
        }
        
        .modal-close {
            float: right;
            font-size: 24px;
            font-weight: bold;
            color: #999;
            cursor: pointer;
            border: none;
            background: none;
        }
        
        .modal-close:hover {
            color: #333;
        }
        
        /* Report Modal */
        .report-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .report-form textarea {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            font-size: 12px;
            resize: vertical;
            min-height: 80px;
        }
        
        .report-form button {
            padding: 10px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .report-form button:hover {
            background: #764ba2;
        }
        
        /* Info Toggle Button */
        .info-toggle-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: none;
            font-size: 14px;
            color: #374151;
            font-weight: 500;
            order: 10;
        }

        .info-toggle-btn:hover {
            background: #e5e7eb;
            border-color: #d1d5db;
        }

        .info-toggle-btn:active {
            background: #d1d5db;
        }

        .info-toggle-icon {
            font-size: 18px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .info-toggle-arrow {
            font-size: 12px;
            line-height: 1;
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
        }

        .info-toggle-arrow.rotated {
            transform: rotate(180deg);
        }

        /* Document Info Section */
        .document-info-section {
            display: none;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
            overflow: hidden;
            opacity: 0;
            transform: translateY(-10px);
            transition: opacity 0.4s ease, transform 0.4s ease;
        }

        .document-info-section.show {
            opacity: 1;
            transform: translateY(0);
        }

        .info-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }

        .info-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }

        .info-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 16px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .info-icon {
            font-size: 24px;
            line-height: 1;
        }

        .info-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            letter-spacing: 0.3px;
        }

        .info-card-content {
            padding: 20px;
        }

        .description-text {
            font-size: 14px;
            line-height: 1.8;
            color: #374151;
            margin: 0;
            white-space: pre-wrap;
        }

        .category-section {
            margin-bottom: 16px;
        }

        .category-section:last-child {
            margin-bottom: 0;
        }

        .category-section-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f3f4f6;
        }

        .category-icon {
            font-size: 18px;
            line-height: 1;
        }

        .category-label-text {
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .category-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .cat-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(102, 126, 234, 0.2);
        }

        .cat-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        }

        @media (max-width: 768px) {
            .pdf-viewer {
                height: 400px;
            }

            .info-toggle-arrow {
                font-size: 11px;
            }

            .document-info-section {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
    </style>
<div class="drawer-content flex flex-col">
    <?php include 'includes/navbar.php'; ?>
    <main class="flex-1 p-6">
        <div class="max-w-7xl mx-auto">
            <div class="space-y-6">
        <!-- Header -->
        <div class="card bg-base-100 shadow-md">
            <div class="card-body">
                <div class="flex flex-col lg:flex-row justify-between items-start gap-4">
                    <div class="flex-1">
                        <h1 class="text-2xl font-bold flex items-center gap-2 flex-wrap">
                            <!-- <i class="fa-solid fa-file-lines"></i> -->
                            <?= htmlspecialchars($doc['original_name']) ?>
                            <?php if($has_purchased && $is_logged_in): ?>
                                <span class="badge badge-success">✓ Đã Mua</span>
                            <?php endif; ?>
                        </h1>
                        <div class="flex gap-4 text-sm text-base-content/70 mt-2 flex-wrap">
                            <span>by <strong><?= htmlspecialchars($doc['username']) ?></strong></span>
                            <span class="flex items-center gap-1">
                                <i class="fa-regular fa-calendar"></i>
                                <?= date('M d, Y', strtotime($doc['created_at'])) ?>
                            </span>
                            <span class="flex items-center gap-1">
                                <i class="fa-regular fa-eye"></i>
                                <?= number_format($doc['views'] ?? 0) ?> lượt xem
                            </span>
                            <span class="flex items-center gap-1">
                                <i class="fa-solid fa-download"></i>
                                <?= number_format($doc['downloads'] ?? 0) ?> tải xuống
                            </span>
                        </div>
                    </div>
                    <?php 
                    // Get document price for use in download button and modal
                    $doc_points = getDocumentPoints($doc_id);
                    $price = 0;
                    if($doc_points) {
                        $price = $doc_points['user_price'] > 0 ? $doc_points['user_price'] : ($doc_points['admin_points'] ?? 0);
                    }
                    ?>
                    <div class="flex flex-col gap-3 w-full lg:w-auto items-end">
                        <div class="flex gap-2 flex-wrap justify-end">
                            <?php
                            $download_class = 'btn btn-primary';
                            if(!$is_logged_in) {
                                $download_class = 'btn btn-ghost btn-disabled';
                            } elseif(!$has_purchased) {
                                $download_class = 'btn btn-warning';
                            }
                            ?>
                            <?php if($is_logged_in && $doc['user_id'] == $user_id): ?>
                                <a href="edit-document.php?id=<?= $doc_id ?>" class="btn btn-success btn-sm">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                    Edit
                                </a>
                            <?php endif; ?>
                            <button class="<?= $download_class ?> btn-sm" onclick="downloadDoc()">
                                <i class="fa-solid fa-download"></i>
                                Download
                            </button>
                            <!-- <button class="btn btn-ghost btn-sm" onclick="goToSaved()">
                                <i class="fa-regular fa-bookmark"></i>
                                Saved
                            </button> -->
                            <?php if(!empty($doc['description']) || $doc_category): ?>
                            <button class="btn btn-ghost btn-sm" onclick="toggleDocInfo()" id="infoToggleBtn">
                                <i class="fa-solid fa-circle-info"></i>
                                Info
                                <span class="info-toggle-arrow" id="infoToggleArrow">▼</span>
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-ghost btn-sm" onclick="document.location='dashboard.php'">
                                <i class="fa-solid fa-arrow-left"></i>
                                Back
                            </button>
                        </div>
                        <?php if($is_logged_in): ?>
                        <div class="flex gap-2 flex-wrap">
                            <button class="btn btn-sm <?= $user_reaction === 'like' ? 'btn-success' : 'btn-ghost' ?>" onclick="toggleReaction('like')">
                                <i class="fa-regular fa-thumbs-up"></i>
                                <?= number_format($likes) ?>
                            </button>
                            <button class="btn btn-sm <?= $user_reaction === 'dislike' ? 'btn-error' : 'btn-ghost' ?>" onclick="toggleReaction('dislike')">
                                <i class="fa-regular fa-thumbs-down"></i>
                                <?= number_format($dislikes) ?>
                            </button>
                            <!-- Save Button (Bookmark Icon) -->
                            <button class="btn btn-sm <?= $user_saved ? 'btn-primary' : 'btn-ghost' ?>" onclick="toggleSave()">
                                <i class="fa-regular fa-bookmark"></i>
                                Save <?= $user_saved ? '✓' : '' ?>
                            </button>
                            <button class="btn btn-sm btn-ghost" onclick="openShareModal()">
                                <i class="fa-solid fa-share-nodes"></i>
                                Share
                            </button>
                            <button class="btn btn-sm btn-ghost" onclick="openReportModal()">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                Report
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="flex gap-2">
                            <button class="btn btn-primary btn-sm" onclick="document.location='index.php'">Đăng Nhập để tương tác</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Info Section (Collapsible) -->
        <?php if(!empty($doc['description']) || $doc_category): ?>
        <div class="document-info-section grid grid-cols-1 md:grid-cols-2 gap-4" id="documentInfoSection" style="display: none;">
            <?php if(!empty($doc['description'])): ?>
            <div class="card bg-base-100 shadow-md">
                <div class="card-title rounded-t-xl bg-primary text-white p-4">
                    <i class="fa-solid fa-file-lines"></i>
                    <h3>Mô Tả Tài Liệu</h3>
                </div>
                <div class="card-body">
                    <p class="text-sm leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($doc['description']) ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if($doc_category): ?>
            <div class="card bg-base-100 shadow-md">
                <div class="card-title rounded-t-xl bg-primary text-white p-4">
                    <i class="fa-solid fa-folder"></i>
                    <h3>Phân Loại</h3>
                </div>
                <div class="card-body">
                    <!-- Cấp học -->
                    <div class="mb-4">
                        <div class="flex items-center gap-2 mb-2 pb-2 border-b-2 border-base-300">
                            <i class="fa-solid fa-graduation-cap text-base-content/70"></i>
                            <span class="text-xs font-semibold uppercase text-base-content/70 tracking-wide">Cấp học</span>
                        </div>
                        <span class="badge badge-primary"><?= htmlspecialchars($doc_category['education_level_name']) ?></span>
                    </div>
                    
                    <?php if(isset($doc_category['grade_name'])): ?>
                    <!-- Lớp -->
                    <div class="mb-4">
                        <div class="flex items-center gap-2 mb-2 pb-2 border-b-2 border-base-300">
                            <i class="fa-solid fa-users text-base-content/70"></i>
                            <span class="text-xs font-semibold uppercase text-base-content/70 tracking-wide">Lớp</span>
                        </div>
                        <span class="badge badge-primary"><?= htmlspecialchars($doc_category['grade_name']) ?></span>
                    </div>
                    
                    <!-- Môn học -->
                    <div class="mb-4">
                        <div class="flex items-center gap-2 mb-2 pb-2 border-b-2 border-base-300">
                            <i class="fa-solid fa-book text-base-content/70"></i>
                            <span class="text-xs font-semibold uppercase text-base-content/70 tracking-wide">Môn học</span>
                        </div>
                        <span class="badge badge-primary"><?= htmlspecialchars($doc_category['subject_name']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(isset($doc_category['major_group_name'])): ?>
                    <!-- Nhóm ngành -->
                    <div class="mb-4">
                        <div class="flex items-center gap-2 mb-2 pb-2 border-b-2 border-base-300">
                            <i class="fa-solid fa-briefcase text-base-content/70"></i>
                            <span class="text-xs font-semibold uppercase text-base-content/70 tracking-wide">Nhóm ngành</span>
                        </div>
                        <span class="badge badge-primary"><?= htmlspecialchars($doc_category['major_group_name']) ?></span>
                    </div>
                    
                    <?php if(!empty($doc_category['major_name'])): ?>
                    <!-- Ngành học -->
                    <div class="mb-4">
                        <div class="flex items-center gap-2 mb-2 pb-2 border-b-2 border-base-300">
                            <i class="fa-solid fa-scroll text-base-content/70"></i>
                            <span class="text-xs font-semibold uppercase text-base-content/70 tracking-wide">Ngành học</span>
                        </div>
                        <span class="badge badge-primary"><?= htmlspecialchars($doc_category['major_name']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Loại tài liệu -->
                    <div class="mb-0">
                        <div class="flex items-center gap-2 mb-2 pb-2 border-b-2 border-base-300">
                            <i class="fa-solid fa-file-lines text-base-content/70"></i>
                            <span class="text-xs font-semibold uppercase text-base-content/70 tracking-wide">Loại tài liệu</span>
                        </div>
                        <span class="badge badge-secondary"><?= htmlspecialchars($doc_category['doc_type_name']) ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Document Viewer -->
        <div class="card bg-base-100 shadow-lg <?= !$has_purchased ? 'protected' : '' ?>" id="documentViewer">
            <?php if(!$has_purchased): ?>
                <div class="alert alert-warning">
                    <i class="fa-solid fa-triangle-exclamation shrink-0 text-xl"></i>
                    <div>
                        <h3 class="font-bold">Bản Xem Trước</h3>
                        <div class="text-sm">Bạn đang xem bản xem trước (3 trang đầu) của tài liệu này.</div>
                        <div class="text-sm mt-1">Để xem toàn bộ tài liệu, vui lòng mua với giá <strong><?= number_format($price) ?> điểm</strong>.</div>
                        <div class="mt-3">
                            <?php if($is_logged_in): ?>
                                <button class="btn btn-primary btn-sm" onclick="openPurchaseModal(<?= $doc_id ?>, <?= $price ?>)">Mua Ngay</button>
                            <?php else: ?>
                                <a href="index.php" class="btn btn-primary btn-sm">Đăng Nhập Để Mua</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php
            // Initialize pdf_path_for_preview
            $pdf_path_for_preview = null;
            
            switch($file_ext) {
                case 'pdf':
                    echo '<div class="pdf-viewer" id="pdfViewer"></div>';
                    $pdf_path_for_preview = 'uploads/' . $doc['file_name'];
                    break;
                case 'docx':
                case 'doc':
                    // Check if converted PDF exists - use it for preview instead of DOCX
                    if (!empty($doc['converted_pdf_path']) && file_exists($doc['converted_pdf_path'])) {
                        // Use PDF preview from converted PDF
                        echo '<div class="pdf-viewer" id="pdfViewer"></div>';
                        // Store converted PDF path for JavaScript
                        $pdf_path_for_preview = $doc['converted_pdf_path'];
                    } else {
                        // Fallback to DOCX preview
                        $file_url = 'uploads/' . $doc['file_name'];
                        echo '<div class="docx-viewer" id="docxViewer"></div>';
                        $pdf_path_for_preview = null;
                    }
                    break;
                case 'jpg':
                case 'jpeg':
                case 'png':
                case 'gif':
                case 'webp':
                    $file_url = 'uploads/' . $doc['file_name'];
                    echo '<div class="image-viewer">
                            <img src="' . $file_url . '" alt="' . htmlspecialchars($doc['original_name']) . '">
                          </div>';
                    break;
                case 'txt':
                case 'log':
                case 'md':
                    $content = file_get_contents($file_path);
                    if(!$has_purchased) {
                        // Limit text preview to first 2000 characters
                        $content = substr($content, 0, 2000);
                        if(strlen($content) >= 2000) {
                            $content .= "\n\n... [Nội dung bị giới hạn. Mua tài liệu để xem đầy đủ] ...";
                        }
                    }
                    echo '<div class="text-viewer">
                            <pre>' . htmlspecialchars($content) . '</pre>
                          </div>';
                    break;
                default:
                    echo '<div class="p-10 text-center text-base-content/50">
                            <i class="fa-solid fa-folder-open text-5xl mb-4"></i>
                            <p>File type: .' . htmlspecialchars($file_ext) . ' cannot be previewed</p>
                          </div>';
            }
            ?>
            <?php if(!$has_purchased): ?>
                <div class="watermark-overlay">
                    <div class="watermark-text">DOCSHARE - BẢN QUYỀN</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Related Documents Sections -->
        <?php if(!empty($category_docs) || !empty($similar_docs)): ?>
            <!-- More from Category -->
            <?php if(!empty($category_docs)):
                // Get category name for display
                $category_name = $doc_category ? htmlspecialchars($doc_category['education_level_name']) : 'Category';
                if ($doc_category && isset($doc_category['subject_name'])) {
                    $category_name = htmlspecialchars($doc_category['subject_name']);
                } elseif ($doc_category && isset($doc_category['major_name'])) {
                    $category_name = htmlspecialchars($doc_category['major_name']);
                }
            ?>
            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <h2 class="card-title text-xl mb-4">
                        <i class="fa-solid fa-folder"></i>
                        More from: <?= $category_name ?>
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <?php
                        foreach($category_docs as $related) {
                            $ext = strtolower(pathinfo($related['file_name'], PATHINFO_EXTENSION));
                            $icon_svg = '';
                            if(in_array($ext, ['pdf', 'doc', 'docx'])) {
                                $icon_svg = '<i class="fa-solid fa-file-lines text-5xl text-primary"></i>';
                            } elseif(in_array($ext, ['xls', 'xlsx', 'ppt', 'pptx'])) {
                                $icon_svg = '<i class="fa-solid fa-file-excel text-5xl text-primary"></i>';
                            } elseif($ext == 'txt') {
                                $icon_svg = '<i class="fa-solid fa-file-lines text-5xl text-primary"></i>';
                            } elseif(in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                $icon_svg = '<i class="fa-solid fa-image text-5xl text-primary"></i>';
                            } else {
                                $icon_svg = '<i class="fa-solid fa-file text-5xl text-primary"></i>';
                            }
                            echo '
                            <a href="view.php?id=' . $related['id'] . '" class="card bg-base-100 shadow-sm hover:shadow-md transition-shadow">
                                <div class="card-body p-4">
                                    <div class="flex justify-center mb-2">' . $icon_svg . '</div>
                                    <h3 class="card-title text-sm line-clamp-2 min-h-[2.5rem]">' . htmlspecialchars($related['original_name']) . '</h3>
                                    <p class="text-xs text-base-content/70 mt-1">by ' . htmlspecialchars($related['username']) . '</p>
                                    <div class="flex items-center gap-2 mt-2 text-xs text-base-content/50">
                                        <i class="fa-regular fa-eye"></i>
                                        ' . number_format($related['views'] ?? 0) . ' views
                                    </div>
                                </div>
                            </a>
                            ';
                        }
                        ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recommended for you -->
            <?php if(!empty($similar_docs)): ?>
            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <h2 class="card-title text-xl mb-4">
                        <i class="fa-solid fa-star"></i>
                        Recommended for you
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <?php
                        foreach($similar_docs as $related) {
                            $ext = strtolower(pathinfo($related['file_name'], PATHINFO_EXTENSION));
                            $icon_svg = '';
                            if(in_array($ext, ['pdf', 'doc', 'docx'])) {
                                $icon_svg = '<i class="fa-solid fa-file-lines text-5xl text-primary"></i>';
                            } elseif(in_array($ext, ['xls', 'xlsx', 'ppt', 'pptx'])) {
                                $icon_svg = '<i class="fa-solid fa-file-excel text-5xl text-primary"></i>';
                            } elseif($ext == 'txt') {
                                $icon_svg = '<i class="fa-solid fa-file-lines text-5xl text-primary"></i>';
                            } elseif(in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                $icon_svg = '<i class="fa-solid fa-image text-5xl text-primary"></i>';
                            } else {
                                $icon_svg = '<i class="fa-solid fa-file text-5xl text-primary"></i>';
                            }
                            echo '
                            <a href="view.php?id=' . $related['id'] . '" class="card bg-base-100 shadow-sm hover:shadow-md transition-shadow">
                                <div class="card-body p-4">
                                    <div class="flex justify-center mb-2">' . $icon_svg . '</div>
                                    <h3 class="card-title text-sm line-clamp-2 min-h-[2.5rem]">' . htmlspecialchars($related['original_name']) . '</h3>
                                    <p class="text-xs text-base-content/70 mt-1">by ' . htmlspecialchars($related['username']) . '</p>
                                    <div class="flex items-center gap-2 mt-2 text-xs text-base-content/50">
                                        <i class="fa-regular fa-eye"></i>
                                        ' . number_format($related['views'] ?? 0) . ' views
                                    </div>
                                </div>
                            </a>
                            ';
                        }
                        ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        </main>
        <?php include 'includes/footer.php'; ?>
    </div>
    </div>

    <!-- Share Modal -->
    <dialog id="shareModal" class="modal">
        <div class="modal-box">
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                <i class="fa-solid fa-share-nodes"></i>
                Share Document
            </h3>
            <div class="flex gap-2 mb-4">
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>" target="_blank" class="btn btn-sm flex-1">Facebook</a>
                <a href="https://twitter.com/intent/tweet?url=<?= urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>" target="_blank" class="btn btn-sm flex-1">Twitter</a>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>" target="_blank" class="btn btn-sm flex-1">LinkedIn</a>
            </div>
            <div class="form-control">
                <label class="label">
                    <span class="label-text">Document Link:</span>
                </label>
                <input type="text" id="docLink" value="<?= 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>" class="input input-bordered input-sm" readonly>
                <button onclick="copyLink()" class="btn btn-primary btn-sm w-full mt-2">
                    <i class="fa-regular fa-copy"></i>
                    Copy Link
                </button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <!-- Purchase Modal -->
    <dialog id="purchaseModal" class="modal">
        <div class="modal-box max-w-md">
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <div class="text-center mb-6">
                <i class="fa-solid fa-cart-shopping text-6xl text-primary mb-4"></i>
                <h3 class="font-bold text-xl mb-2">Mua Tài Liệu</h3>
            </div>
            <div class="text-center mb-6">
                <div class="text-sm text-base-content/70 mb-2">Bạn sẽ mua tài liệu này với giá:</div>
                <div class="text-4xl font-bold text-primary mb-2" id="purchasePrice">0 điểm</div>
                <div class="text-xs text-base-content/50">Sau khi mua, bạn có thể xem và tải xuống tài liệu</div>
            </div>
            <div class="flex gap-2">
                <form method="dialog" class="flex-1">
                    <button class="btn btn-ghost w-full">Hủy</button>
                </form>
                <button id="confirmPurchaseBtn" onclick="confirmPurchase(event)" class="btn btn-primary flex-1">Xác Nhận Mua</button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <!-- Alert Modal -->
    <dialog id="alertModal" class="modal">
        <div class="modal-box max-w-md">
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <div class="text-center mb-6">
                <div class="flex justify-center mb-4" id="alertIcon">
                    <i class="fa-solid fa-circle-info text-6xl text-info"></i>
                </div>
                <h3 class="font-bold text-xl mb-4" id="alertTitle">Thông Báo</h3>
                <div class="text-sm text-base-content/70 leading-relaxed" id="alertMessage"></div>
            </div>
            <form method="dialog" class="flex justify-center">
                <button class="btn btn-primary">Đóng</button>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <!-- Report Modal -->
    <dialog id="reportModal" class="modal">
        <div class="modal-box">
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Report Document
            </h3>
            <form onsubmit="submitReport(event)" class="space-y-4">
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Reason:</span>
                    </label>
                    <textarea id="reportReason" class="textarea textarea-bordered" placeholder="Explain why you're reporting this document..." required></textarea>
                </div>
                <div class="modal-action">
                    <form method="dialog">
                        <button class="btn btn-ghost">Cancel</button>
                    </form>
                    <button type="submit" class="btn btn-primary">Submit Report</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
        
        let pdfDoc = null;
        let currentPage = 1;
        <?php 
        // Set pdfPath based on file type
        if ($file_ext === 'pdf') {
            $pdf_path_js = "uploads/" . $doc['file_name'];
        } elseif (($file_ext === 'docx' || $file_ext === 'doc') && !empty($doc['converted_pdf_path']) && file_exists($doc['converted_pdf_path'])) {
            $pdf_path_js = $doc['converted_pdf_path'];
        } else {
            $pdf_path_js = null;
        }
        ?>
        const pdfPath = <?= $pdf_path_js ? '"' . htmlspecialchars($pdf_path_js, ENT_QUOTES, 'UTF-8') . '"' : 'null' ?>;
        const hasPurchased = <?= $has_purchased ? 'true' : 'false' ?>;
        const maxPreviewPages = 3;
        
        // Protection against copy and screenshot
        <?php if(!$has_purchased): ?>
        // Disable right-click
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Disable copy
        document.addEventListener('copy', function(e) {
            e.preventDefault();
            e.clipboardData.setData('text/plain', '');
            showAlert('Sao chép nội dung bị cấm. Vui lòng mua tài liệu để sử dụng.', 'warning', 'Cảnh Báo');
            return false;
        });
        
        // Disable cut
        document.addEventListener('cut', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Disable select
        document.addEventListener('selectstart', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Disable drag
        document.addEventListener('dragstart', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Disable print screen (F12, Print Screen key)
        document.addEventListener('keydown', function(e) {
            // Disable F12 (Developer Tools)
            if(e.key === 'F12' || (e.ctrlKey && e.shiftKey && e.key === 'I') || 
               (e.ctrlKey && e.shiftKey && e.key === 'C') || 
               (e.ctrlKey && e.key === 'U') || 
               (e.ctrlKey && e.key === 'S')) {
                e.preventDefault();
                return false;
            }
            // Disable Print Screen
            if(e.key === 'PrintScreen') {
                e.preventDefault();
                navigator.clipboard.writeText('');
                showAlert('Chụp màn hình bị cấm. Vui lòng mua tài liệu để sử dụng.', 'warning', 'Cảnh Báo');
                return false;
            }
        });
        
        // Disable screenshot on mobile (iOS/Android)
        document.addEventListener('touchstart', function(e) {
            if(e.touches.length > 1) {
                e.preventDefault();
            }
        }, { passive: false });
        
        // Blur on tab switch (prevents screenshot when switching tabs)
        document.addEventListener('visibilitychange', function() {
            if(document.hidden) {
                document.body.style.filter = 'blur(10px)';
            } else {
                document.body.style.filter = 'none';
            }
        });
        
        // Console warning
        console.log('%c⚠️ CẢNH BÁO!', 'color: red; font-size: 50px; font-weight: bold;');
        console.log('%cSao chép hoặc chỉnh sửa mã này là bất hợp pháp!', 'color: red; font-size: 20px;');
        <?php endif; ?>
        
        // Load PDF if applicable (for PDF files or DOCX files with converted PDF)
        <?php if($file_ext === 'pdf' || (($file_ext === 'docx' || $file_ext === 'doc') && isset($pdf_path_for_preview) && $pdf_path_for_preview)): ?>
        (async () => {
            try {
                if (pdfPath) {
                    pdfDoc = await pdfjsLib.getDocument(pdfPath).promise;
                    await renderAllPages();
                    
                    // Lazy generation: If document is missing page count or thumbnail, generate them now
                    const docId = <?= $doc_id ?>;
                    const totalPages = <?= $doc['total_pages'] ?? 0 ?>;
                    const hasThumbnail = <?= !empty($doc['thumbnail']) ? 'true' : 'false' ?>;
                    
                    if (totalPages === 0 || !hasThumbnail) {
                        console.log('Lazy generating missing data: pages=' + totalPages + ', hasThumbnail=' + hasThumbnail);
                        try {
                            await processPdfDocument(pdfPath, docId, {
                                countPages: totalPages === 0,
                                generateThumbnail: !hasThumbnail,
                                thumbnailWidth: 400
                            });
                            console.log('Lazy generation completed successfully');
                        } catch(error) {
                            console.warn('Lazy generation failed:', error);
                        }
                    }
                } else {
                    document.getElementById("pdfViewer").innerHTML = '<div style="padding: 40px; text-align: center; color: #999;">PDF path not available</div>';
                }
            } catch(error) {
                document.getElementById("pdfViewer").innerHTML = '<div style="padding: 40px; text-align: center; color: #999;">Error loading PDF: ' + error.message + '</div>';
            }
        })();
        
        async function renderAllPages() {
            const viewer = document.getElementById("pdfViewer");
            viewer.innerHTML = '';
            
            for (let pageNum = 1; pageNum <= pdfDoc.numPages; pageNum++) {
                const page = await pdfDoc.getPage(pageNum);
                const scale = 2;
                const viewport = page.getViewport({ scale });
                
                const canvas = document.createElement("canvas");
                const context = canvas.getContext("2d");
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                
                await page.render({
                    canvasContext: context,
                    viewport: viewport
                }).promise;
                
                const pageDiv = document.createElement("div");
                pageDiv.className = "pdf-page";
                
                // Blur pages after limit
                if(!hasPurchased && pageNum > maxPreviewPages) {
                    pageDiv.classList.add("page-limit-blur");
                }
                
                pageDiv.appendChild(canvas);
                viewer.appendChild(pageDiv);
            }
        }
        <?php endif; ?>
        
        // Load DOCX or converted PDF if applicable
        <?php if($file_ext === 'docx' || $file_ext === 'doc'): ?>
        (async () => {
            try {
                <?php if(isset($pdf_path_for_preview) && $pdf_path_for_preview): ?>
                // Use converted PDF for preview
                const pdfPath = "<?= htmlspecialchars($pdf_path_for_preview, ENT_QUOTES, 'UTF-8') ?>";
                pdfDoc = await pdfjsLib.getDocument(pdfPath).promise;
                await renderAllPages();
                
                // Lazy generation: If document is missing page count or thumbnail, generate them now
                const docId = <?= $doc_id ?>;
                const totalPages = <?= $doc['total_pages'] ?? 0 ?>;
                const hasThumbnail = <?= !empty($doc['thumbnail']) ? 'true' : 'false' ?>;
                
                if (totalPages === 0 || !hasThumbnail) {
                    console.log('DOCX lazy generating missing data: pages=' + totalPages + ', hasThumbnail=' + hasThumbnail);
                    try {
                        await processPdfDocument(pdfPath, docId, {
                            countPages: totalPages === 0,
                            generateThumbnail: !hasThumbnail,
                            thumbnailWidth: 400
                        });
                        console.log('DOCX lazy generation completed successfully');
                    } catch(error) {
                        console.warn('DOCX lazy generation failed:', error);
                    }
                }
                <?php else: ?>
                // Fallback to DOCX preview
                // Wait for JSZip and docx library to load
                let retries = 0;
                while ((typeof JSZip === 'undefined' || (typeof docx === 'undefined' && typeof docxPreview === 'undefined')) && retries < 15) {
                    await new Promise(resolve => setTimeout(resolve, 200));
                    retries++;
                }
                
                // Check JSZip
                if (typeof JSZip === 'undefined') {
                    throw new Error('JSZip library not loaded. Please refresh the page.');
                }
                
                // Try to get docx API - it might be exposed as docx, docxPreview, or window.docxPreview
                let docxAPI = null;
                if (typeof docx !== 'undefined' && docx.renderAsync) {
                    docxAPI = docx;
                } else if (typeof docxPreview !== 'undefined' && docxPreview.renderAsync) {
                    docxAPI = docxPreview;
                } else if (window.docxPreview && window.docxPreview.renderAsync) {
                    docxAPI = window.docxPreview;
                } else if (window.docx && window.docx.renderAsync) {
                    docxAPI = window.docx;
                } else {
                    console.error('Available globals:', Object.keys(window).filter(k => k.toLowerCase().includes('docx')));
                    throw new Error('DOCX preview library not loaded correctly. Please refresh the page.');
                }
                
                const docxViewer = document.getElementById("docxViewer");
                if (!docxViewer) {
                    throw new Error('DOCX viewer element not found');
                }
                
                const fileUrl = "uploads/<?= $doc['file_name'] ?>";
                const response = await fetch(fileUrl);
                
                if (!response.ok) {
                    throw new Error('Failed to fetch file: ' + response.statusText);
                }
                
                const arrayBuffer = await response.arrayBuffer();
                
                if (!arrayBuffer || arrayBuffer.byteLength === 0) {
                    throw new Error('File is empty or invalid');
                }
                
                // Check if renderAsync method exists
                if (typeof docxAPI.renderAsync !== 'function') {
                    throw new Error('docx.renderAsync is not available. Library version may be incompatible.');
                }
                
                // Render DOCX with pagination support using docx-preview
                await docxAPI.renderAsync(arrayBuffer, docxViewer, null, {
                    className: "docx-wrapper",
                    inWrapper: true,
                    ignoreWidth: false,
                    ignoreHeight: false,
                    ignoreFonts: false,
                    breakPages: true, // Enable page breaks for clear pagination
                    ignoreLastRenderedPageBreak: false,
                    experimental: false,
                    trimXmlDeclaration: true,
                    useBase64URL: false,
                    showChanges: false,
                    showInsertions: false,
                    showDeletions: false
                });
                
                // After rendering, enhance pagination by wrapping pages
                const wrapper = docxViewer.querySelector('.docx-wrapper');
                if (wrapper) {
                    // Wait a bit for content to render
                    await new Promise(resolve => setTimeout(resolve, 100));
                    
                    // Get all direct children (potential pages)
                    const children = Array.from(wrapper.children);
                    
                    // Simple approach: Split content into pages based on approximate page height
                    let pages = [];
                    let currentPage = [];
                    let currentHeight = 0;
                    const pageHeight = 800; // Approximate page height in pixels
                    
                    children.forEach((child) => {
                        // Move child to current page first to measure
                        currentPage.push(child);
                        
                        // Estimate height (use offsetHeight if available, otherwise default)
                        const childHeight = child.offsetHeight || 150;
                        currentHeight += childHeight;
                        
                        // If we've accumulated enough content for a page, create it
                        if (currentHeight >= pageHeight && currentPage.length > 1) {
                            // Remove last child (it will start next page)
                            const lastChild = currentPage.pop();
                            currentHeight -= (lastChild.offsetHeight || 150);
                            
                            // Create a page wrapper
                            const pageDiv = document.createElement('div');
                            pageDiv.className = 'docx-page';
                            pageDiv.style.minHeight = '800px';
                            pageDiv.style.padding = '40px';
                            pageDiv.style.marginBottom = '20px';
                            pageDiv.style.background = 'white';
                            pageDiv.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
                            pageDiv.style.borderRadius = '4px';
                            
                            currentPage.forEach(el => pageDiv.appendChild(el));
                            pages.push(pageDiv);
                            
                            // Start new page with the last child
                            currentPage = [lastChild];
                            currentHeight = lastChild.offsetHeight || 150;
                        }
                    });
                    
                    // Add remaining content as last page
                    if (currentPage.length > 0) {
                        const pageDiv = document.createElement('div');
                        pageDiv.className = 'docx-page';
                        pageDiv.style.minHeight = '800px';
                        pageDiv.style.padding = '40px';
                        pageDiv.style.marginBottom = '20px';
                        pageDiv.style.background = 'white';
                        pageDiv.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
                        pageDiv.style.borderRadius = '4px';
                        
                        currentPage.forEach(el => pageDiv.appendChild(el));
                        pages.push(pageDiv);
                    }
                    
                    // If no pages were created (no children), create one page with all content
                    if (pages.length === 0 && children.length > 0) {
                        const pageDiv = document.createElement('div');
                        pageDiv.className = 'docx-page';
                        pageDiv.style.minHeight = '800px';
                        pageDiv.style.padding = '40px';
                        pageDiv.style.marginBottom = '20px';
                        pageDiv.style.background = 'white';
                        pageDiv.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
                        pageDiv.style.borderRadius = '4px';
                        
                        children.forEach(el => pageDiv.appendChild(el));
                        pages.push(pageDiv);
                    }
                    
                    // Replace wrapper content with pages
                    if (pages.length > 0) {
                        wrapper.innerHTML = '';
                        pages.forEach((page, index) => {
                            // Limit pages for non-purchased users (0-indexed: 0,1,2 are first 3 pages)
                            if(!hasPurchased && index >= maxPreviewPages) {
                                page.classList.add("page-limit-blur");
                                page.style.filter = "blur(5px) !important";
                                page.style.opacity = "0.3 !important";
                                page.style.pointerEvents = "none !important";
                                page.style.position = "relative !important";
                                
                                // Add overlay message
                                const overlay = document.createElement('div');
                                overlay.style.position = 'absolute';
                                overlay.style.top = '50%';
                                overlay.style.left = '50%';
                                overlay.style.transform = 'translate(-50%, -50%)';
                                overlay.style.background = 'rgba(255,255,255,0.95)';
                                overlay.style.padding = '20px 30px';
                                overlay.style.borderRadius = '8px';
                                overlay.style.fontWeight = 'bold';
                                overlay.style.color = '#333';
                                overlay.style.zIndex = '100';
                                overlay.style.boxShadow = '0 4px 12px rgba(0,0,0,0.2)';
                                overlay.style.textAlign = 'center';
                                overlay.style.fontSize = '16px';
                                overlay.textContent = 'Mua tài liệu để xem đầy đủ';
                                page.appendChild(overlay);
                            }
                            wrapper.appendChild(page);
                        });
                    }
                }
                <?php endif; ?>
                
            } catch(error) {
                console.error('DOCX loading error:', error);
                const docxViewer = document.getElementById("docxViewer");
                if (docxViewer) {
                    docxViewer.innerHTML = '<div style="padding: 40px; text-align: center; color: #999;">Error loading DOCX file: ' + error.message + '<br><small>Please try downloading the file instead.</small></div>';
                }
            }
        })();
        <?php endif; ?>
        
        // Alert Modal Functions
        function showAlert(message, iconType = 'info', title = 'Thông Báo') {
            document.getElementById('alertMessage').textContent = message;
            const alertIcon = document.getElementById('alertIcon');
            
            // Icon mapping
            const icons = {
                'info': '<i class="fa-solid fa-circle-info text-6xl text-info"></i>',
                'warning': '<i class="fa-solid fa-triangle-exclamation text-6xl text-warning"></i>',
                'success': '<i class="fa-solid fa-circle-check text-6xl text-success"></i>',
                'error': '<i class="fa-solid fa-circle-xmark text-6xl text-error"></i>',
                'lock': '<i class="fa-solid fa-lock text-6xl text-warning"></i>'
            };
            
            // Map old emoji to new icon types
            const emojiMap = {
                'ℹ️': 'info',
                '⚠️': 'warning',
                '✓': 'success',
                '❌': 'error',
                '🔒': 'lock'
            };
            
            const iconKey = emojiMap[iconType] || iconType;
            alertIcon.innerHTML = icons[iconKey] || icons['info'];
            document.getElementById('alertTitle').textContent = title;
            document.getElementById('alertModal').showModal();
        }
        
        function closeAlertModal() {
            document.getElementById('alertModal').close();
        }
        
        // Interaction functions
        function toggleReaction(type) {
            if (!<?= $is_logged_in ? 'true' : 'false' ?>) {
                showAlert('Vui lòng đăng nhập để tương tác', 'lock', 'Yêu Cầu Đăng Nhập');
                return;
            }
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=' + type
            }).then(() => location.reload());
        }
        
        function toggleSave() {
            if (!<?= $is_logged_in ? 'true' : 'false' ?>) {
                showAlert('Vui lòng đăng nhập để lưu tài liệu', 'lock', 'Yêu Cầu Đăng Nhập');
                return;
            }
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=save'
            }).then(() => location.reload());
        }
        
        function openShareModal() {
            document.getElementById('shareModal').showModal();
        }
        
        function closeShareModal() {
            document.getElementById('shareModal').close();
        }
        
        function openReportModal() {
            document.getElementById('reportModal').showModal();
        }
        
        function closeReportModal() {
            document.getElementById('reportModal').close();
        }
        
        function copyLink() {
            const link = document.getElementById('docLink');
            link.select();
            document.execCommand('copy');
            showAlert('Đã sao chép liên kết vào clipboard!', 'success', 'Thành Công');
        }
        
        function submitReport(e) {
            e.preventDefault();
            const reason = document.getElementById('reportReason').value;
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=report&reason=' + encodeURIComponent(reason)
            }).then(() => {
                closeReportModal();
                showAlert('Báo cáo đã được gửi thành công. Cảm ơn bạn đã phản hồi!', '✓', 'Thành Công');
            });
        }
        
        function downloadDoc() {
            <?php if(!$is_logged_in): ?>
                showAlert('Vui lòng đăng nhập để tải xuống tài liệu', 'lock', 'Yêu Cầu Đăng Nhập');
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 2000);
                return;
            <?php endif; ?>
            <?php if(!$has_purchased): ?>
                // Mở modal mua tài liệu
                const price = <?= $price ?>;
                openPurchaseModal(<?= $doc_id ?>, price);
                return;
            <?php endif; ?>
            window.location.href = '?id=<?= $doc_id ?>&download=1';
        }
        
        function goToSaved() {
            window.location.href = 'saved.php';
        }
        
        // Toggle Document Info Section
        function toggleDocInfo() {
            const section = document.getElementById('documentInfoSection');
            const arrow = document.getElementById('infoToggleArrow');
            const btn = document.getElementById('infoToggleBtn');
            
            if (section.classList.contains('show')) {
                // Close animation
                section.classList.remove('show');
                arrow.classList.remove('rotated');
                setTimeout(() => {
                    section.style.display = 'none';
                }, 400); // Match CSS transition duration
            } else {
                // Open animation
                section.style.display = 'grid';
                // Force reflow
                section.offsetHeight;
                // Add show class to trigger animation
                section.classList.add('show');
                arrow.classList.add('rotated');
                
                // Smooth scroll to show the section
                setTimeout(() => {
                    section.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'nearest' 
                    });
                }, 100);
            }
        }
        
        let currentPurchaseDocId = null;
        
        function openPurchaseModal(docId, price) {
            currentPurchaseDocId = docId;
            document.getElementById('purchasePrice').textContent = number_format(price) + ' điểm';
            document.getElementById('purchaseModal').showModal();
        }
        
        function closePurchaseModal() {
            document.getElementById('purchaseModal').close();
            currentPurchaseDocId = null;
        }
        
        function confirmPurchase(event) {
            if(!currentPurchaseDocId) {
                return;
            }
            
            // Disable button to prevent double submit
            const confirmBtn = event ? event.target : document.getElementById('confirmPurchaseBtn');
            if(!confirmBtn) return;
            
            const originalText = confirmBtn.textContent;
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Đang xử lý...';
            confirmBtn.style.opacity = '0.6';
            confirmBtn.style.cursor = 'not-allowed';
            
            fetch('handler/purchase_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'document_id=' + currentPurchaseDocId
            })
            .then(response => {
                if(!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if(data.success) {
                        closePurchaseModal();
                        // Show success message
                        const successMsg = document.createElement('div');
                        successMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 10000; font-weight: 600;';
                        successMsg.textContent = '✓ ' + (data.message || 'Mua tài liệu thành công! Đang tải lại...');
                        document.body.appendChild(successMsg);
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        // Re-enable button
                        confirmBtn.disabled = false;
                        confirmBtn.textContent = originalText;
                        confirmBtn.style.opacity = '1';
                        confirmBtn.style.cursor = 'pointer';
                        showAlert(data.message || 'Không thể mua tài liệu', 'error', 'Lỗi');
                    }
                } catch(e) {
                    // Re-enable button
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = originalText;
                    confirmBtn.style.opacity = '1';
                    confirmBtn.style.cursor = 'pointer';
                    console.error('Parse error:', e, 'Response:', text);
                    showAlert('Lỗi xử lý phản hồi từ server', '❌', 'Lỗi');
                }
            })
            .catch(error => {
                // Re-enable button
                confirmBtn.disabled = false;
                confirmBtn.textContent = originalText;
                confirmBtn.style.opacity = '1';
                confirmBtn.style.cursor = 'pointer';
                console.error('Fetch error:', error);
                showAlert('Lỗi kết nối: ' + (error.message || 'Không thể kết nối đến server'), 'error', 'Lỗi Kết Nối');
            });
        }
        
        function number_format(number) {
            return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
        
        // DaisyUI modals handle backdrop clicks automatically via dialog element
    </script>
</body>
</html>
<?php
mysqli_close($conn);
?>