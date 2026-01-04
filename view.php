<?php
session_start();
require_once 'config/db.php';
require_once 'config/function.php';
require_once 'config/auth.php';
require_once 'config/premium.php';
require_once 'config/file.php';
require_once 'config/points.php';
require_once 'config/categories.php';
require_once 'config/document_stats.php';
require_once 'config/settings.php';

// Cho phép xem mà không cần đăng nhập
$user_id = isset($_SESSION['user_id']) ? getCurrentUserId() : null;
$is_premium = $user_id ? isPremium($user_id) : false;
$is_logged_in = isset($_SESSION['user_id']);
$current_page = 'view';

$doc_id = intval($_GET['id'] ?? 0);

// First check if document exists at all (any status)
$check_query = "SELECT d.*, u.username, u.avatar FROM documents d 
                JOIN users u ON d.user_id = u.id 
                WHERE d.id=$doc_id";
$doc_check = $VSD->get_row($check_query);

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
    // db connection cleaned up by app flow
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
    // db connection cleaned up by app flow
    exit;
}

// Handle actions (like, dislike, report, save)
if($_SERVER["REQUEST_METHOD"] == "POST" && $is_logged_in) {
    $action = $_POST['action'] ?? '';
    
    if($action === 'like' || $action === 'dislike') {
        // Toggle like/dislike
        $existing = $VSD->get_row("SELECT * FROM document_interactions WHERE document_id=$doc_id AND user_id=$user_id AND type='$action'");
        
        if($existing) {
            $VSD->query("DELETE FROM document_interactions WHERE id={$existing['id']}");
        } else {
            // Remove opposite reaction if exists
            $VSD->query("DELETE FROM document_interactions WHERE document_id=$doc_id AND user_id=$user_id AND type IN ('like', 'dislike')");
            // Add new reaction
            $VSD->query("INSERT INTO document_interactions (document_id, user_id, type) VALUES ($doc_id, $user_id, '$action')");
        }
        
        // Return updated stats
        $new_likes = intval($VSD->num_rows("SELECT * FROM document_interactions WHERE document_id=$doc_id AND type='like'") ?: 0);
        $new_dislikes = intval($VSD->num_rows("SELECT * FROM document_interactions WHERE document_id=$doc_id AND type='dislike'") ?: 0);
        $curr_reaction = $VSD->get_row("SELECT type FROM document_interactions WHERE document_id=$doc_id AND user_id=$user_id AND type IN ('like', 'dislike')");
        
        echo json_encode([
            'success' => true, 
            'likes' => $new_likes, 
            'dislikes' => $new_dislikes,
            'user_reaction' => $curr_reaction['type'] ?? null
        ]);
        exit;
    }
    
    if($action === 'save') {
        // Toggle save
        $existing = $VSD->get_row("SELECT * FROM document_interactions WHERE document_id=$doc_id AND user_id=$user_id AND type='save'");
        
        if($existing) {
            $VSD->query("DELETE FROM document_interactions WHERE id={$existing['id']}");
            $saved = false;
        } else {
            $VSD->query("INSERT INTO document_interactions (document_id, user_id, type) VALUES ($doc_id, $user_id, 'save')");
            $saved = true;
        }
        echo json_encode(['success' => true, 'saved' => $saved]);
        exit;
    }
    
    if($action === 'report') {
        $reason = $VSD->escape($_POST['reason'] ?? 'Inappropriate content');
        $VSD->query("INSERT INTO document_reports (document_id, user_id, reason) VALUES ($doc_id, $user_id, '$reason')");
        echo json_encode(['success' => true, 'message' => 'Report submitted']);
        exit;
    }
}

// Get interaction stats
$likes = intval($VSD->num_rows("SELECT * FROM document_interactions WHERE document_id=$doc_id AND type='like'") ?: 0);
$dislikes = intval($VSD->num_rows("SELECT * FROM document_interactions WHERE document_id=$doc_id AND type='dislike'") ?: 0);
$saves = intval($VSD->num_rows("SELECT * FROM document_interactions WHERE document_id=$doc_id AND type='save'") ?: 0);

// Get user's current reactions
$user_reaction = null;
$user_saved = false;
if($is_logged_in) {
    $reaction = $VSD->get_row("SELECT type FROM document_interactions WHERE document_id=$doc_id AND user_id=$user_id AND type IN ('like', 'dislike')");
    $user_reaction = $reaction['type'] ?? null;
    
    $saved = $VSD->get_row("SELECT * FROM document_interactions WHERE document_id=$doc_id AND user_id=$user_id AND type='save'");
    $user_saved = $saved ? true : false;
}

// Get documents from same category (More from Category)
$category_docs = [];
if($doc_category) {
    // Build WHERE clause based on category type
    $cat_where = "dc.education_level = '" . $VSD->escape($doc_category['education_level']) . "'";
    
    // For phổ thông: match by subject
    if(isset($doc_category['subject_code']) && $doc_category['subject_code']) {
        $cat_where .= " AND dc.subject_code = '" . $VSD->escape($doc_category['subject_code']) . "'";
    }
    // For đại học: match by major
    elseif(isset($doc_category['major_code']) && $doc_category['major_code']) {
        $cat_where .= " AND dc.major_code = '" . $VSD->escape($doc_category['major_code']) . "'";
    }
    $category_query = "SELECT d.*, u.username 
                       FROM documents d 
                       JOIN users u ON d.user_id = u.id 
                       JOIN document_categories dc ON d.id = dc.document_id
                       WHERE $cat_where AND d.id != $doc_id AND d.status = 'approved' AND d.is_public = TRUE
                       LIMIT 4";
    $category_docs = $VSD->get_list($category_query);
}

// Get similar documents for "Recommended for you" (only approved)
$similar_docs = $VSD->get_list("
    SELECT d.*, u.username FROM documents d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.is_public = TRUE AND d.status = 'approved' AND d.id != $doc_id
    ORDER BY (d.views + d.downloads * 2) DESC, d.created_at DESC
    LIMIT 8
");

// Function to download file with speed limit
function downloadFileWithSpeedLimit($file_path, $speed_limit_kbps = 100) {
    // Open file
    $file = fopen($file_path, 'rb');
    if (!$file) {
        return false;
    }
    
    // Get file size
    $file_size = filesize($file_path);
    
    // Calculate chunk size (e.g., 100KB per chunk)
    $chunk_size = 100 * 1024; // 100KB
    
    // Calculate delay between chunks to achieve desired speed
    // speed_limit_kbps is in KB/s, so we need to send chunk_size bytes
    // Time per chunk = (chunk_size / 1024) / speed_limit_kbps seconds
    $delay_microseconds = (($chunk_size / 1024) / $speed_limit_kbps) * 1000000;
    
    // Send headers
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
    header('Content-Length: ' . $file_size);
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Disable output buffering
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Send file in chunks with speed limit
    $bytes_sent = 0;
    while (!feof($file) && $bytes_sent < $file_size) {
        // Check if client disconnected
        if (connection_aborted()) {
            break;
        }
        
        // Read chunk
        $chunk = fread($file, $chunk_size);
        if ($chunk === false) {
            break;
        }
        
        // Send chunk
        echo $chunk;
        flush();
        
        // Update bytes sent
        $bytes_sent += strlen($chunk);
        
        // Apply speed limit delay (except for last chunk)
        if ($bytes_sent < $file_size && $delay_microseconds > 0) {
            usleep($delay_microseconds);
        }
    }
    
    fclose($file);
    return true;
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
    
    // Download with speed limit (from settings)
    $download_speed_kbps = (int)getSetting('limit_download_speed_free', 100);
    
    // For premium users or document owners, allow faster download
    if($is_premium || ($is_logged_in && $doc['user_id'] == $user_id)) {
        $download_speed_kbps = (int)getSetting('limit_download_speed_premium', 500);
    }
    
    downloadFileWithSpeedLimit($file_path, $download_speed_kbps);
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
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(255, 255, 255, 0.2);
        --primary-gradient: linear-gradient(135deg, oklch(var(--p)) 0%, oklch(var(--p) / 0.8) 100%);
    }
    
    [data-theme="dark"] {
        --glass-bg: rgba(15, 23, 42, 0.7);
        --glass-border: rgba(255, 255, 255, 0.1);
    }

    .view-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 40px 24px;
    }

    /* Document Header Premium */
    .view-header-card {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 2.5rem;
        padding: 40px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05);
        margin-bottom: 32px;
        position: relative;
        overflow: hidden;
    }

    .view-header-card::before {
        content: '';
        position: absolute;
        top: -100px;
        right: -100px;
        width: 300px;
        height: 300px;
        background: oklch(var(--p) / 0.03);
        border-radius: 50%;
        filter: blur(60px);
    }

    .view-title {
        font-size: 2.25rem;
        font-weight: 900;
        color: oklch(var(--bc));
        letter-spacing: -0.04em;
        line-height: 1.1;
        margin-bottom: 24px;
    }

    .view-meta-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 24px;
        margin-bottom: 32px;
    }

    .user-badge-premium {
        display: flex;
        align-items: center;
        gap: 12px;
        background: oklch(var(--b2) / 0.5);
        padding: 8px 20px 8px 8px;
        border-radius: 1.25rem;
        border: 1px solid oklch(var(--bc) / 0.05);
        transition: all 0.3s ease;
    }

    .user-badge-premium:hover {
        background: oklch(var(--b2));
        transform: translateY(-2px);
    }

    .user-avatar-vsd {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        background: oklch(var(--p) / 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .meta-item-vsd {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 11px;
        font-weight: 900;
        color: oklch(var(--bc) / 0.4);
        text-transform: uppercase;
        letter-spacing: 0.1em;
    }

    /* Premium Status Badge */
    .status-badge-premium {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 16px;
        border-radius: 100px;
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.15em;
        background: linear-gradient(135deg, oklch(var(--s)) 0%, oklch(var(--s) / 0.8) 100%);
        color: white;
        box-shadow: 0 10px 20px -5px oklch(var(--s) / 0.3);
        border: 1px solid oklch(var(--s) / 0.2);
        animation: pulseFade 2s infinite;
    }

    .status-badge-owner {
        background: linear-gradient(135deg, oklch(var(--p)) 0%, oklch(var(--p) / 0.8) 100%);
        box-shadow: 0 10px 20px -5px oklch(var(--p) / 0.3);
        border: 1px solid oklch(var(--p) / 0.2);
    }

    @keyframes pulseFade {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.9; transform: scale(0.98); }
    }

    .actions-bar-vsd {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        padding-top: 32px;
        border-top: 1px solid oklch(var(--bc) / 0.05);
    }

    .btn-vsd {
        height: 48px;
        padding: 0 24px;
        border-radius: 1rem;
        font-weight: 900;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1);
        cursor: pointer;
        border: 1px solid transparent;
    }

    .btn-vsd-primary {
        background: oklch(var(--p));
        color: oklch(var(--pc));
        box-shadow: 0 10px 15px -3px oklch(var(--p) / 0.2);
    }

    .btn-vsd-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 20px 25px -5px oklch(var(--p) / 0.3);
    }

    .btn-vsd-secondary {
        background: oklch(var(--b2) / 0.5);
        color: oklch(var(--bc));
        border-color: oklch(var(--bc) / 0.05);
    }

    .btn-vsd-secondary:hover {
        background: oklch(var(--b2));
        border-color: oklch(var(--bc) / 0.1);
        transform: translateY(-1px);
    }

    .btn-vsd-ghost {
        background: transparent;
        color: oklch(var(--bc) / 0.5);
    }

    .btn-vsd-ghost:hover {
        background: oklch(var(--bc) / 0.05);
        color: oklch(var(--bc));
    }

    .reaction-group {
        display: flex;
        gap: 8px;
        background: oklch(var(--b2) / 0.3);
        padding: 6px;
        border-radius: 1.25rem;
        border: 1px solid oklch(var(--bc) / 0.05);
    }

    /* PDF Viewer Premium */
    .viewer-card-vsd {
        background: oklch(var(--b1));
        border-radius: 3rem;
        border: 1px solid oklch(var(--bc) / 0.05);
        padding: 40px;
        margin-bottom: 40px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        position: relative;
        overflow: hidden;
    }

    .pdf-viewer {
        width: 100%;
        height: 190vh;
        background: oklch(var(--b2) / 0.5);
        border-radius: 2rem;
        overflow-y: auto;
        padding: 40px;
        position: relative;
        scrollbar-gutter: stable;
    }

    .pdf-page-container {
        margin: 0 auto 40px auto;
        background: white;
        box-shadow: 0 20px 40px rgba(0,0,0,0.08);
        border-radius: 1rem;
        overflow: hidden;
        transition: transform 0.3s ease;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .pdf-page-container:hover {
        transform: scale(1.01);
    }

    .pdf-page-counter {
        position: absolute;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%) translateY(20px);
        background: rgba(15, 23, 42, 0.9);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        color: white;
        padding: 10px 24px;
        border-radius: 2rem;
        font-size: 0.75rem;
        font-weight: 900;
        letter-spacing: 0.15em;
        z-index: 100;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.4);
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
        border: 1px solid rgba(255,255,255,0.1);
        pointer-events: none;
    }

    .pdf-page-counter.active {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }

    #currentPageNum {
        color: oklch(var(--p));
        font-size: 0.85rem;
    }

    /* Info Section Grid */
    .info-grid-vsd {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 32px;
        margin-bottom: 40px;
    }

    .info-card-premium {
        background: var(--glass-bg);
        border-radius: 2.5rem;
        border: 1px solid var(--glass-border);
        padding: 32px;
    }

    .info-card-title-vsd {
        font-size: 1.25rem;
        font-weight: 900;
        color: oklch(var(--bc));
        letter-spacing: -0.02em;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .info-card-title-vsd i {
        color: oklch(var(--p));
    }

    /* Categories Styling */
    .cat-group-vsd {
        margin-bottom: 24px;
    }

    .cat-label-vsd {
        font-size: 0.7rem;
        font-weight: 900;
        color: oklch(var(--bc) / 0.3);
        text-transform: uppercase;
        letter-spacing: 0.15em;
        margin-bottom: 12px;
        display: block;
    }

    .cat-tag-vsd {
        display: inline-flex;
        padding: 8px 20px;
        background: oklch(var(--p) / 0.05);
        color: oklch(var(--p));
        border-radius: 2rem;
        font-size: 11px;
        font-weight: 900;
        border: 1px solid oklch(var(--p) / 0.1);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    /* Watermark Protection */
    .protected {
        user-select: none;
        -webkit-user-select: none;
    }

    .watermark-overlay {
        position: absolute;
        inset: 0;
        pointer-events: none;
        z-index: 50;
        background-image: repeating-linear-gradient(45deg, transparent, transparent 150px, rgba(0,0,0,0.02) 150px, rgba(0,0,0,0.02) 300px);
    }
    
    .watermark-text {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-30deg);
        font-size: 8rem;
        font-weight: 900;
        color: rgba(0,0,0,0.03);
        white-space: nowrap;
        pointer-events: none;
    }

    /* Additional Viewer Styles */
    .image-viewer {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: oklch(var(--b2) / 0.3);
        min-height: 500px;
        border-radius: 2rem;
    }
    
    .image-viewer img {
        max-width: 100%;
        max-height: 100%;
        border-radius: 1rem;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
    }
    
    .text-viewer {
        padding: 40px;
        background: white;
        border-radius: 2rem;
        overflow-y: auto;
        max-height: 800px;
    }
    
    .text-viewer pre {
        font-family: 'Courier New', monospace;
        font-size: 13px;
        line-height: 1.8;
        white-space: pre-wrap;
        word-wrap: break-word;
        color: #334155;
    }

    .docx-viewer {
        width: 100%;
        padding: 40px;
        background: oklch(var(--b2) / 0.3);
        border-radius: 2rem;
        min-height: 500px;
    }

    .page-loader {
        transition: opacity 0.3s ease;
    }

    /* Download Queue Widget Premium */
    .download-queue-widget {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 9999;
        min-width: 320px;
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 1.5rem;
        padding: 24px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.2);
        transform: translateY(120%);
        transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1);
        pointer-events: none;
        opacity: 0;
    }

    .download-queue-widget.show {
        transform: translateY(0);
        opacity: 1;
        pointer-events: auto;
    }

    .download-queue-widget .progress {
        height: 8px;
        border-radius: 4px;
        background: oklch(var(--b3));
    }

    /* Loading Overlay */
    .vsd-loading-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        color: white;
        flex-direction: column;
        gap: 16px;
    }

    .vsd-loading-overlay.active {
        display: flex;
    }

    @media (max-width: 1024px) {
        .info-grid-vsd {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 640px) {
        .view-header-card {
            padding: 24px;
            border-radius: 1.5rem;
        }
        .view-title {
            font-size: 1.5rem;
        }
        .pdf-viewer {
            height: 100vh;
            padding: 15px;
        }
    }
</style>
<div class="drawer-content flex flex-col">
    <?php include 'includes/navbar.php'; ?>
    <main class="flex-1 p-6">
        <div class="max-w-7xl mx-auto">
            <div class="space-y-6">
    <div class="view-container">
        <!-- Header Section Premium -->
        <div class="view-header-card">
            <h1 class="view-title"><?= htmlspecialchars($doc['original_name']) ?></h1>
            
            <div class="view-meta-row">
                <a href="user_profile.php?id=<?= $doc['user_id'] ?>" class="user-badge-premium">
                    <div class="user-avatar-vsd">
                        <?php if(!empty($doc['avatar']) && file_exists('uploads/avatars/' . $doc['avatar'])): ?>
                            <img src="uploads/avatars/<?= $doc['avatar'] ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fa-solid fa-user text-primary text-sm"></i>
                        <?php endif; ?>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-base-content/70"><?= htmlspecialchars($doc['username']) ?></span>
                </a>

                <div class="meta-item-vsd">
                    <i class="fa-solid fa-calendar-day"></i>
                    <?= date('M d, Y', strtotime($doc['created_at'])) ?>
                </div>

                <div class="meta-item-vsd">
                    <i class="fa-solid fa-eye"></i>
                    <?= number_format($doc['views'] ?? 0) ?> XEM
                </div>

                <div class="meta-item-vsd">
                    <i class="fa-solid fa-download"></i>
                    <?= number_format($doc['downloads'] ?? 0) ?> TẢI
                </div>

                <?php if($is_logged_in): ?>
                    <?php if($doc['user_id'] == $user_id): ?>
                        <div class="status-badge-premium status-badge-owner">
                            <i class="fa-solid fa-crown"></i> CỦA BẠN
                        </div>
                    <?php elseif($has_purchased): ?>
                        <div class="status-badge-premium">
                            <i class="fa-solid fa-check-double"></i> ĐÃ SỞ HỮU
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php 
            $doc_points = getDocumentPoints($doc_id);
            $price = 0;
            if($doc_points) {
                $price = $doc_points['user_price'] > 0 ? $doc_points['user_price'] : ($doc_points['admin_points'] ?? 0);
            }
            ?>

            <div class="actions-bar-vsd">
                <div class="flex gap-3 flex-wrap">
                    <button class="btn-vsd btn-vsd-primary" onclick="downloadDoc()">
                        <i class="fa-solid fa-download"></i> 
                        <?= !$has_purchased ? 'MUA & TẢI XUỐNG' : 'TẢI XUỐNG NGAY' ?>
                    </button>
                    
                    <?php if($is_logged_in && $doc['user_id'] == $user_id): ?>
                        <a href="edit-document.php?id=<?= $doc_id ?>" class="btn-vsd btn-vsd-secondary">
                            <i class="fa-solid fa-pen-to-square"></i> CHỈNH SỬA
                        </a>
                    <?php endif; ?>

                    <button class="btn-vsd btn-vsd-secondary" onclick="toggleDocInfo()">
                        <i class="fa-solid fa-circle-info"></i> THÔNG TIN
                    </button>
                </div>

                <div class="flex gap-4 items-center">
                    <?php if($is_logged_in): ?>
                        <div class="reaction-group">
                            <button class="btn-vsd btn-vsd-ghost <?= $user_reaction === 'like' ? 'text-primary' : '' ?>" onclick="toggleReaction('like')" style="height: 36px; padding: 0 16px;">
                                <i class="fa-<?= $user_reaction === 'like' ? 'solid' : 'regular' ?> fa-thumbs-up"></i>
                                <?= number_format($likes) ?>
                            </button>
                            <button class="btn-vsd btn-vsd-ghost <?= $user_reaction === 'dislike' ? 'text-error' : '' ?>" onclick="toggleReaction('dislike')" style="height: 36px; padding: 0 16px;">
                                <i class="fa-<?= $user_reaction === 'dislike' ? 'solid' : 'regular' ?> fa-thumbs-down"></i>
                                <?= number_format($dislikes) ?>
                            </button>
                        </div>

                        <button class="btn-vsd btn-vsd-ghost <?= $user_saved ? 'text-primary' : '' ?>" onclick="toggleSave()" title="Lưu tài liệu">
                            <i class="fa-<?= $user_saved ? 'solid' : 'regular' ?> fa-bookmark"></i>
                        </button>

                        <button class="btn-vsd btn-vsd-ghost" onclick="openShareModal()" title="Chia sẻ">
                            <i class="fa-solid fa-share-nodes"></i>
                        </button>

                        <button class="btn-vsd btn-vsd-ghost" onclick="openReportModal()" title="Báo cáo">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </button>
                    <?php else: ?>
                        <a href="index.php" class="btn-vsd btn-vsd-secondary">ĐĂNG NHẬP ĐỂ TƯƠNG TÁC</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Info Grid VSD -->
        <div class="info-grid-vsd" id="documentInfoSection" style="display: none;">
            <div class="info-card-premium">
                <h3 class="info-card-title-vsd">
                    <i class="fa-solid fa-file-signature"></i> MÔ TẢ TÀI LIỆU
                </h3>
                <div class="text-sm leading-relaxed text-base-content/70 whitespace-pre-wrap">
                    <?php if(!empty($doc['description'])): ?>
                        <?= htmlspecialchars($doc['description']) ?>
                    <?php else: ?>
                        <span class="italic opacity-50">Tài liệu này chưa có mô tả chi tiết.</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-card-premium">
                <h3 class="info-card-title-vsd">
                    <i class="fa-solid fa-layer-group"></i> PHÂN LOẠI
                </h3>
                <?php if($doc_category): ?>
                    <div class="cat-group-vsd">
                        <span class="cat-label-vsd">Cấp học</span>
                        <span class="cat-tag-vsd"><?= htmlspecialchars($doc_category['education_level_name']) ?></span>
                    </div>
                    <?php if(isset($doc_category['grade_name'])): ?>
                        <div class="cat-group-vsd">
                            <span class="cat-label-vsd">Lớp & Môn học</span>
                            <div class="flex gap-2">
                                <span class="cat-tag-vsd"><?= htmlspecialchars($doc_category['grade_name']) ?></span>
                                <span class="cat-tag-vsd"><?= htmlspecialchars($doc_category['subject_name']) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if(isset($doc_category['major_group_name'])): ?>
                        <div class="cat-group-vsd">
                            <span class="cat-label-vsd">Chuyên ngành</span>
                            <div class="flex flex-wrap gap-2">
                                <span class="cat-tag-vsd"><?= htmlspecialchars($doc_category['major_group_name']) ?></span>
                                <?php if(!empty($doc_category['major_name'])): ?>
                                    <span class="cat-tag-vsd"><?= htmlspecialchars($doc_category['major_name']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="cat-group-vsd">
                        <span class="cat-label-vsd">Loại file</span>
                        <span class="cat-tag-vsd" style="background: oklch(var(--s) / 0.05); color: oklch(var(--s)); border-color: oklch(var(--s) / 0.1);">
                            <?= htmlspecialchars($doc_category['doc_type_name']) ?>
                        </span>
                    </div>
                <?php else: ?>
                    <span class="text-sm italic opacity-50">Chưa có thông tin phân loại.</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Viewer Section -->
        <div class="viewer-card-vsd <?= !$has_purchased ? 'protected' : '' ?>">
            <?php if(!$has_purchased): ?>
                <div class="alert alert-warning">
                    <i class="fa-solid fa-triangle-exclamation shrink-0 text-xl"></i>
                    <div>
                        <h3 class="font-bold">Bản Xem Trước</h3>
                        <div class="text-sm">Bạn đang xem bản xem trước (5 trang đầu) của tài liệu này.</div>
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
                    echo '<div class="pdf-viewer-wrapper relative">
                            <div class="pdf-viewer" id="pdfViewer"></div>
                            <div class="pdf-page-counter" id="pdfPageCounter">
                                <span id="currentPageNum">1</span> / <span id="totalPagesNum">--</span>
                            </div>
                          </div>';
                    $pdf_path_for_preview = 'uploads/' . $doc['file_name'];
                    break;
                case 'docx':
                case 'doc':
                    // Check if converted PDF exists - use it for preview instead of DOCX
                    $converted_path = $doc['converted_pdf_path'] ?? '';
                    if (!empty($converted_path) && file_exists($converted_path)) {
                        // Use PDF preview from converted PDF
                        echo '<div class="pdf-viewer-wrapper relative">
                                <div class="pdf-viewer" id="pdfViewer"></div>
                                <div class="pdf-page-counter" id="pdfPageCounter">
                                    <span id="currentPageNum">1</span> / <span id="totalPagesNum">--</span>
                                </div>
                              </div>';
                        // Store converted PDF path for JavaScript
                        $pdf_path_for_preview = $converted_path;
                    } else {
                        // Fallback to DOCX viewer if PDF conversion failed or pending
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

    <!-- Global Loading Overlay -->
    <div id="vsdGlobalLoader" class="vsd-loading-overlay">
        <span class="loading loading-spinner loading-lg text-primary"></span>
        <span class="font-black uppercase tracking-[0.2em] text-[10px] text-white">Đang xử lý...</span>
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

    <!-- Download Queue Widget -->
    <div id="downloadQueueWidget" class="download-queue-widget hidden">
        <div class="download-queue-content">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-download text-primary text-lg"></i>
                    <span class="font-semibold">Đang tải xuống</span>
                </div>
                <button class="btn btn-ghost btn-xs btn-circle" onclick="hideDownloadQueue()" title="Ẩn">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div>
                <div class="flex items-center justify-between text-xs mb-2">
                    <span id="downloadProgressPercent" class="font-bold text-primary">0%</span>
                    <div id="downloadSpeedBadge" class="badge badge-error badge-sm gap-1 py-3 px-3">
                        <i id="downloadSpeedIcon" class="fa-solid fa-hourglass-half"></i>
                        <span id="downloadSpeed">0 KB/s</span>
                    </div>
                </div>
                <progress id="downloadProgressBar" class="progress progress-primary w-full h-2" value="0" max="100"></progress>
            </div>
        </div>
    </div>

    <!-- Report Modal -->
    <dialog id="reportModal" class="modal">
        <div class="modal-box">
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <h3 class="font-bold text-lg mb-4 flex items-center gap-2 text-error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Báo Cáo Tài Liệu
            </h3>
            <form onsubmit="submitReport(event)" id="reportForm" class="space-y-4">
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Lý do báo cáo <span class="text-error">*</span></span>
                    </label>
                    <select id="reportReason" class="select select-bordered w-full" required>
                        <option value="">-- Chọn lý do --</option>
                        <option value="inappropriate">Nội dung không phù hợp</option>
                        <option value="copyright">Vi phạm bản quyền</option>
                        <option value="spam">Spam / Quảng cáo</option>
                        <option value="misleading">Tiêu đề gây hiểu lầm</option>
                        <option value="low_quality">Chất lượng kém</option>
                        <option value="duplicate">Trùng lặp nội dung</option>
                        <option value="other">Lý do khác</option>
                    </select>
                </div>
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Mô tả chi tiết</span>
                    </label>
                    <textarea id="reportDescription" class="textarea textarea-bordered h-24" placeholder="Vui lòng cung cấp thêm thông tin về báo cáo của bạn..."></textarea>
                    <label class="label">
                        <span class="label-text-alt">Mô tả chi tiết sẽ giúp quản trị viên xem xét nhanh hơn</span>
                    </label>
                </div>
                <div class="alert alert-warning">
                    <i class="fa-solid fa-info-circle"></i>
                    <span class="text-sm">Báo cáo sai sự thật có thể dẫn đến việc tài khoản của bạn bị hạn chế.</span>
                </div>
                <div class="modal-action">
                    <form method="dialog">
                        <button type="button" class="btn btn-ghost">Hủy</button>
                    </form>
                    <button type="submit" class="btn btn-error">
                        <i class="fa-solid fa-paper-plane mr-2"></i>
                        Gửi Báo Cáo
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>


    <script>
        // Global PDF.js configurations
        if (typeof pdfjsLib !== 'undefined') {
            pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
        }

        /**
         * VSD Advanced PDF Viewer (Drive Style)
         * High performance lazy loading with IntersectionObserver
         */
        class VsdPdfViewer {
            constructor(viewerId, pdfPath, options = {}) {
                this.viewer = document.getElementById(viewerId);
                this.pdfPath = pdfPath;
                this.options = {
                    maxPreviewPages: options.maxPreviewPages || 5, // Default to 5 pages
                    hasPurchased: options.hasPurchased || false,
                    dprLimit: 3, // Increased for sharper rendering at larger sizes
                    rootMargin: '2000px 0px', // Pre-load ~5 pages ahead

                    ...options
                };
                
                this.pdfDoc = null;
                this.numPages = 0;
                this.trackObserver = null;
                this.activePages = new Map(); // pageNum -> { renderTask, canvas }
                this.renderingPages = new Set(); // Fix: Track pages currently rendering to prevent race conditions
                this.pageCounter = document.getElementById('pdfPageCounter');
                this.currentPageNumDisplay = document.getElementById('currentPageNum');
                this.totalPagesNumDisplay = document.getElementById('totalPagesNum');
                
                this.init();
            }

            async init() {
                try {
                    // Set worker properly
                    const loadingTask = pdfjsLib.getDocument({
                        url: this.pdfPath,
                        enableWebGL: false, // Fix: Disable WebGL to prevent texture inversion issues
                        disableAutoFetch: true, // Lazy loading
                        disableStream: false
                    });
                    
                    this.pdfDoc = await loadingTask.promise;
                    this.numPages = this.pdfDoc.numPages;
                    
                    if (this.totalPagesNumDisplay) {
                        this.totalPagesNumDisplay.textContent = this.numPages;
                    }

                    await this.setupPlaceholders();
                    this.setupObservers();
                    
                    // Show counter
                    if (this.pageCounter) this.pageCounter.classList.add('active');
                } catch (err) {
                    console.error('PDF Init Error:', err);
                    if (this.viewer) {
                        this.viewer.innerHTML = `<div class="p-10 text-center text-error">
                            <i class="fa-solid fa-triangle-exclamation text-4xl mb-2"></i>
                            <p>Không thể hiển thị tài liệu: ${err.message}</p>
                        </div>`;
                    }
                }
            }

            async setupPlaceholders() {
                this.viewer.innerHTML = '';
                // Get page 1 info for aspect ratio
                const firstPage = await this.pdfDoc.getPage(1);
                const viewport = firstPage.getViewport({ scale: 1 });
                const aspectRatio = viewport.height / viewport.width;

                for (let i = 1; i <= this.numPages; i++) {
                    const container = document.createElement('div');
                    container.className = 'pdf-page-container';
                    container.id = `vsd-page-${i}`;
                    container.dataset.page = i;
                    
                    // Maintain aspect ratio exactly
                    container.style.aspectRatio = `${viewport.width} / ${viewport.height}`;
                    container.style.width = '100%';
                    container.style.maxWidth = (viewport.width * 1.5) + 'px'; // Increased size

                    // Loader element
                    const loader = document.createElement('div');
                    loader.className = 'page-loader absolute inset-0 flex flex-col items-center justify-center bg-base-100 z-10 transition-opacity duration-300';
                    loader.innerHTML = `
                        <span class="loading loading-spinner loading-md text-primary opacity-50"></span>
                        <div class="mt-2 text-[10px] font-bold opacity-20 uppercase tracking-widest text-center">Trang ${i} / ${this.numPages}</div>
                    `;
                    container.appendChild(loader);

                    // Blur logic for non-purchased
                    if (!this.options.hasPurchased && i > this.options.maxPreviewPages) {
                        container.classList.add('page-limit-blur');
                        const blurLabel = document.createElement('div');
                        blurLabel.className = 'absolute inset-0 flex items-center justify-center z-20 pointer-events-none';
                        blurLabel.innerHTML = `<div class="bg-base-100/90 px-5 py-3 rounded-xl shadow-2xl font-bold text-sm border border-primary/20 backdrop-blur-sm">Mua tài liệu để xem đầy đủ</div>`;
                        container.appendChild(blurLabel);
                    }

                    this.viewer.appendChild(container);
                }
            }

            setupObservers() {
                // 1. Rendering Observer (Load on approach, destroy on leave)
                this.renderObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        const pageNum = parseInt(entry.target.dataset.page);
                        if (entry.isIntersecting) {
                            this.renderPage(pageNum, entry.target);
                        } else {
                            this.destroyPage(pageNum);
                        }
                    });
                }, {
                    root: this.viewer,
                    rootMargin: this.options.rootMargin, // Load ahead
                    threshold: 0.01
                });

                // 2. Tracking Observer (Update page number indicator)
                this.trackObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            if (this.currentPageNumDisplay) {
                                this.currentPageNumDisplay.textContent = entry.target.dataset.page;
                            }
                        }
                    });
                }, {
                    root: this.viewer,
                    threshold: 0.51 // Trigger when more than half is visible
                });

                const pages = this.viewer.querySelectorAll('.pdf-page-container');
                pages.forEach(el => {
                    this.renderObserver.observe(el);
                    this.trackObserver.observe(el);
                });
            }

            async renderPage(pageNum, container) {
                // Fix: Check both active AND rendering states
                if (this.activePages.has(pageNum) || this.renderingPages.has(pageNum)) return; 
                if (!this.options.hasPurchased && pageNum > this.options.maxPreviewPages) return;

                // Fix: Lock the page immediately
                this.renderingPages.add(pageNum);

                try {
                    const page = await this.pdfDoc.getPage(pageNum);
                    
                    // Fix: Update container dimensions to match the ACTUAL page dimensions
                    const naturalViewport = page.getViewport({ scale: 1 });
                    container.style.aspectRatio = `${naturalViewport.width} / ${naturalViewport.height}`;
                    container.style.maxWidth = (naturalViewport.width * 1.5) + 'px';

                    const dpr = Math.min(window.devicePixelRatio || 1, this.options.dprLimit);
                    const viewport = page.getViewport({ scale: dpr });
                    
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d', { alpha: false });
                    
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;
                    canvas.style.width = '100%';
                    canvas.style.height = 'auto';
                    canvas.style.opacity = '0';
                    canvas.style.transition = 'opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1)';

                    const renderContext = {
                        canvasContext: context,
                        viewport: viewport
                    };

                    const renderTask = page.render(renderContext);
                    
                    // Store task immediately
                    this.activePages.set(pageNum, { renderTask, canvas });

                    await renderTask.promise;
                    
                    // Fix: Ensure no duplicate canvases exist before appending
                    const existingCanvas = container.querySelector('canvas');
                    if (existingCanvas) existingCanvas.remove();

                    container.appendChild(canvas);
                    requestAnimationFrame(() => {
                        canvas.style.opacity = '1';
                        const loader = container.querySelector('.page-loader');
                        if (loader) {
                            loader.style.opacity = '0';
                            setTimeout(() => loader.remove(), 600);
                        }
                    });

                } catch (err) {
                    if (err.name === 'RenderingCancelledException') return;
                    console.warn(`Render error page ${pageNum}:`, err);
                    this.activePages.delete(pageNum); // Clean up if failed
                } finally {
                    // Fix: Release the lock
                    this.renderingPages.delete(pageNum);
                }
            }

            destroyPage(pageNum) {
                const item = this.activePages.get(pageNum);
                if (!item) return;

                if (item.renderTask) item.renderTask.cancel();
                if (item.canvas) item.canvas.remove();
                
                // Reset placeholder state (add loader back if needed)
                const container = document.getElementById(`vsd-page-${pageNum}`);
                if (container && !container.querySelector('.page-loader')) {
                    const loader = document.createElement('div');
                    loader.className = 'page-loader absolute inset-0 flex flex-col items-center justify-center bg-base-100 z-10';
                    loader.innerHTML = `<span class="loading loading-spinner loading-md text-primary opacity-30"></span>`;
                    container.appendChild(loader);
                }

                this.activePages.delete(pageNum);
            }
        }


        <?php 
        // Set pdfPath based on file type
        if ($file_ext === 'pdf') {
            $pdf_path_js = "uploads/" . $doc['file_name'];
        } elseif (($file_ext === 'docx' || $file_ext === 'doc')) {
            // For DOCX, prioritize converted PDF if available
            if (!empty($doc['converted_pdf_path']) && file_exists($doc['converted_pdf_path'])) {
                $pdf_path_js = $doc['converted_pdf_path'];
            } else {
                $pdf_path_js = null;
            }
        } else {
            $pdf_path_js = null;
        }
        ?>
        const pdfPath = <?= $pdf_path_js ? '"' . htmlspecialchars($pdf_path_js, ENT_QUOTES, 'UTF-8') . '"' : 'null' ?>;
        const hasPurchased = <?= $has_purchased ? 'true' : 'false' ?>;
        const maxPreviewPages = <?= (int)getSetting('limit_preview_pages', 5) ?>;
        
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
        if (!window.pdfViewerInitialized) {
            window.pdfViewerInitialized = true;
            (async () => {
                try {
                    if (pdfPath) {
                        new VsdPdfViewer('pdfViewer', pdfPath, {
                            maxPreviewPages: maxPreviewPages,
                            hasPurchased: hasPurchased
                        });
                        
                        // Lazy generation logic...
                        const docId = <?= $doc_id ?>;
                        const totalPages = <?= $doc['total_pages'] ?? 0 ?>;
                        const hasThumbnail = <?= !empty($doc['thumbnail']) ? 'true' : 'false' ?>;
                        
                        if (totalPages === 0 || !hasThumbnail) {
                            try {
                                await processPdfDocument(pdfPath, docId, {
                                    countPages: totalPages === 0,
                                    generateThumbnail: !hasThumbnail,
                                    thumbnailWidth: 400
                                });
                            } catch(error) {
                                console.warn('Lazy generation failed:', error);
                            }
                        }
                    } else {
                        const viewer = document.getElementById("pdfViewer");
                        if(viewer) viewer.innerHTML = '<div style="padding: 40px; text-align: center; color: #999;">PDF path not available</div>';
                    }
                } catch(error) {
                    const viewer = document.getElementById("pdfViewer");
                    if(viewer) viewer.innerHTML = '<div style="padding: 40px; text-align: center; color: #999;">Error loading PDF: ' + error.message + '</div>';
                }
            })();
        }
        <?php endif; ?>
        
        // Load DOCX or converted PDF if applicable
        <?php if($file_ext === 'docx' || $file_ext === 'doc'): ?>
        (async () => {
            // Check if already initialized (e.g. by PDF viewer above)
            if (window.pdfViewerInitialized) return;
            window.pdfViewerInitialized = true;

            try {
                <?php if(isset($pdf_path_for_preview) && $pdf_path_for_preview): ?>
                // Use converted PDF for preview
                const pdfPath = "<?= htmlspecialchars($pdf_path_for_preview, ENT_QUOTES, 'UTF-8') ?>";
                new VsdPdfViewer('pdfViewer', pdfPath, {
                    maxPreviewPages: maxPreviewPages,
                    hasPurchased: hasPurchased
                });
                // ... (Lazy generation logic omitted for brevity as it's duplicate of above but inside DOCX block) ...
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
        function showGlobalLoader() {
            document.getElementById('vsdGlobalLoader').classList.add('active');
        }

        function hideGlobalLoader() {
            document.getElementById('vsdGlobalLoader').classList.remove('active');
        }

        function toggleReaction(type) {
            if (!<?= $is_logged_in ? 'true' : 'false' ?>) {
                showAlert('Vui lòng đăng nhập để tương tác', 'lock', 'Yêu Cầu Đăng Nhập');
                return;
            }
            
            const params = new URLSearchParams();
            params.append('action', type);

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Update counts
                    const likeBtn = document.querySelector('[onclick="toggleReaction(\'like\')"]');
                    const dislikeBtn = document.querySelector('[onclick="toggleReaction(\'dislike\')"]');
                    
                    likeBtn.innerHTML = `<i class="fa-${data.user_reaction === 'like' ? 'solid' : 'regular'} fa-thumbs-up"></i> ${data.likes}`;
                    dislikeBtn.innerHTML = `<i class="fa-${data.user_reaction === 'dislike' ? 'solid' : 'regular'} fa-thumbs-down"></i> ${data.dislikes}`;
                    
                    // Update classes
                    likeBtn.classList.toggle('text-primary', data.user_reaction === 'like');
                    dislikeBtn.classList.toggle('text-error', data.user_reaction === 'dislike');
                }
            })
            .catch(err => showAlert('Lỗi kết nối', 'error'));
        }
        
        function toggleSave() {
            if (!<?= $is_logged_in ? 'true' : 'false' ?>) {
                showAlert('Vui lòng đăng nhập để lưu tài liệu', 'lock', 'Yêu Cầu Đăng Nhập');
                return;
            }
            
            const params = new URLSearchParams();
            params.append('action', 'save');

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const saveBtn = document.querySelector('[onclick="toggleSave()"]');
                    saveBtn.innerHTML = `<i class="fa-${data.saved ? 'solid' : 'regular'} fa-bookmark"></i>`;
                    saveBtn.classList.toggle('text-primary', data.saved);
                    
                    if(data.saved) {
                        // Optional: subtle toast or animation
                    }
                }
            })
            .catch(err => showAlert('Lỗi kết nối', 'error'));
        }
        
        function openShareModal() {
            document.getElementById('shareModal').showModal();
        }
        
        function closeShareModal() {
            document.getElementById('shareModal').close();
        }
        
        function openReportModal() {
            if (!<?= $is_logged_in ? 'true' : 'false' ?>) {
                showAlert('Vui lòng đăng nhập để báo cáo tài liệu', 'lock', 'Yêu Cầu Đăng Nhập');
                return;
            }
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
            const description = document.getElementById('reportDescription').value;
            
            if (!reason) {
                showAlert('Vui lòng chọn lý do báo cáo', 'triangle-exclamation', 'Thiếu Thông Tin');
                return;
            }
            
            // Disable submit button to prevent double submission
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Đang gửi...';
            
            fetch('/handler/report_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    document_id: <?= $doc_id ?>,
                    reason: reason,
                    description: description
                })
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane mr-2"></i>Gửi Báo Cáo';
                
                if (data.success) {
                    closeReportModal();
                    document.getElementById('reportForm').reset();
                    showAlert(data.message, 'circle-check', 'Thành Công');
                } else {
                    showAlert(data.message, 'triangle-exclamation', 'Lỗi');
                }
            })
            .catch(error => {
                console.error('Report error:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane mr-2"></i>Gửi Báo Cáo';
                showAlert('Có lỗi xảy ra khi gửi báo cáo. Vui lòng thử lại sau.', 'triangle-exclamation', 'Lỗi');
            });
        }
        
        function downloadDoc() {
            <?php if(!$is_logged_in): ?>
                showAlert('Vui lòng đăng nhập để tải xuống tài liệu', 'lock', 'Yêu Cầu Đăng Nhập');
                setTimeout(() => {
                    window.location.href = '/index.php';
                }, 2000);
                return;
            <?php endif; ?>
            <?php if(!$has_purchased): ?>
                // Mở modal mua tài liệu
                const price = <?= $price ?>;
                openPurchaseModal(<?= $doc_id ?>, price);
                return;
            <?php endif; ?>
            
            // Show download queue widget
            showDownloadQueue();
            
            // Start download with progress tracking
            const downloadUrl = '?id=<?= $doc_id ?>&download=1';
            const fileName = '<?= htmlspecialchars($doc['original_name'], ENT_QUOTES) ?>';
            
            downloadWithProgress(downloadUrl, fileName);
        }
        
        // Download with progress tracking
        function downloadWithProgress(url, fileName) {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.responseType = 'blob';
            
            let startTime = Date.now();
            let lastLoaded = 0;
            let lastTime = startTime;
            
            xhr.onprogress = function(e) {
                if (e.lengthComputable) {
                    const currentTime = Date.now();
                    const loaded = e.loaded;
                    const total = e.total;
                    const percent = Math.round((loaded / total) * 100);
                    
                    // Calculate speed (bytes per second)
                    const timeDiff = (currentTime - lastTime) / 1000; // seconds
                    const bytesDiff = loaded - lastLoaded;
                    const speedBps = timeDiff > 0 ? bytesDiff / timeDiff : 0;
                    
                    // Update UI
                    updateDownloadProgress(percent, speedBps, loaded, total);
                    
                    lastLoaded = loaded;
                    lastTime = currentTime;
                }
            };
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    // Create blob and download
                    const blob = xhr.response;
                    const downloadUrl = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = downloadUrl;
                    a.download = fileName;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(downloadUrl);
                    
                    // Hide download queue after a delay
                    setTimeout(() => {
                        hideDownloadQueue();
                    }, 1000);
                } else {
                    hideDownloadQueue();
                    showAlert('Lỗi khi tải xuống tài liệu', 'error', 'Lỗi');
                }
            };
            
            xhr.onerror = function() {
                hideDownloadQueue();
                showAlert('Lỗi kết nối khi tải xuống', 'error', 'Lỗi');
            };
            
            xhr.send();
        }
        
        // Show download queue widget
        function showDownloadQueue() {
            const widget = document.getElementById('downloadQueueWidget');
            if (widget) {
                widget.classList.remove('hidden');
                widget.classList.add('show');
            }
        }
        
        // Hide download queue widget
        function hideDownloadQueue() {
            const widget = document.getElementById('downloadQueueWidget');
            if (widget) {
                widget.classList.remove('show');
                setTimeout(() => {
                    widget.classList.add('hidden');
                }, 300);
            }
        }
        
        // Update download progress
        function updateDownloadProgress(percent, speedBps, loaded, total) {
            const progressBar = document.getElementById('downloadProgressBar');
            const progressPercent = document.getElementById('downloadProgressPercent');
            const downloadSpeed = document.getElementById('downloadSpeed');
            const speedIcon = document.getElementById('downloadSpeedIcon');
            
            if (progressBar) {
                progressBar.value = percent;
            }
            
            if (progressPercent) {
                progressPercent.textContent = percent + '%';
            }
            
            if (downloadSpeed) {
                const speedKBps = (speedBps / 1024).toFixed(1);
                const speedMBps = (speedBps / (1024 * 1024)).toFixed(2);
                
                if (speedBps >= 1024 * 1024) {
                    downloadSpeed.textContent = speedMBps + ' MB/s';
                } else {
                    downloadSpeed.textContent = speedKBps + ' KB/s';
                }
                
                // Update icon and badge based on speed
                const speedBadge = document.getElementById('downloadSpeedBadge');
                if (speedIcon && speedBadge) {
                    if (speedBps >= 200 * 1024) {
                        // Fast: >= 200 KB/s
                        speedIcon.className = 'fa-solid fa-bolt';
                        speedBadge.className = 'badge badge-success badge-sm gap-1 py-3 px-3 text-white';
                        speedIcon.title = 'Tải nhanh';
                    } else if (speedBps >= 50 * 1024) {
                        // Medium: >= 50 KB/s
                        speedIcon.className = 'fa-solid fa-gauge';
                        speedBadge.className = 'badge badge-warning badge-sm gap-1 py-3 px-3 text-warning-content';
                        speedIcon.title = 'Tải trung bình';
                    } else {
                        // Slow: < 50 KB/s
                        speedIcon.className = 'fa-solid fa-hourglass-half';
                        speedBadge.className = 'badge badge-error badge-sm gap-1 py-3 px-3 text-white';
                        speedIcon.title = 'Tải chậm';
                    }
                }
            }
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
            
            fetch('/handler/purchase_handler.php', {
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
                    // Check if response is empty
                    if(!text || text.trim() === '') {
                        throw new Error('Server trả về phản hồi trống');
                    }
                    
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
                        const errorMsg = data.message || 'Không thể mua tài liệu. Vui lòng thử lại sau.';
                        console.error('Purchase failed:', errorMsg, data);
                        showAlert(errorMsg, 'error', 'Lỗi Mua Tài Liệu');
                    }
                } catch(e) {
                    // Re-enable button
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = originalText;
                    confirmBtn.style.opacity = '1';
                    confirmBtn.style.cursor = 'pointer';
                    console.error('Parse error:', e, 'Response:', text);
                    showAlert('Lỗi xử lý phản hồi từ server. Vui lòng thử lại sau.', 'error', 'Lỗi');
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
?>