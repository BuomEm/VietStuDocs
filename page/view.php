<?php
session_start();
require_once '../config/db.php';
require_once '../config/function.php';
require_once '../config/auth.php';
require_once '../config/premium.php';
require_once '../config/file.php';
require_once '../config/points.php';
require_once '../config/categories.php';
require_once '../config/document_stats.php';
require_once '../config/settings.php';

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
    include '../includes/head.php';
    include '../includes/sidebar.php';
    ?>
    <div class="drawer-content flex flex-col">
        <?php include '../includes/navbar.php'; ?>
        <main class="flex-1 min-h-screen flex items-center justify-center p-6">
            <div class="card bg-base-100 shadow-xl max-w-2xl w-full">
                <div class="card-body text-center">
                    <?php if($error_type === 'not_found'): ?>
                        <?php if(!empty(getSetting('site_logo'))): ?>
                            <img src="<?= htmlspecialchars(getSetting('site_logo')) ?>" alt="Not Found" class="h-24 mx-auto mb-4 opacity-50 grayscale">
                        <?php else: ?>
                            <i class="fa-solid fa-magnifying-glass text-6xl text-base-content/50 mb-4"></i>
                        <?php endif; ?>
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
        <?php include '../includes/footer.php'; ?>
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

// Calc PDF path for preview
$pdf_path_js = null;
if ($file_ext === 'pdf') {
    $pdf_path_js = '../handler/file.php?doc_id=' . $doc_id;
} elseif (in_array($file_ext, ['docx', 'doc'])) {
    $converted_path = $doc['converted_pdf_path'] ?? '';
    if (!empty($converted_path)) {
        // Build absolute path to check existence
        $abs_converted_path = __DIR__ . '/../' . ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $converted_path), DIRECTORY_SEPARATOR);
        if (file_exists($abs_converted_path)) {
            $pdf_path_js = '../handler/file.php?doc_id=' . $doc_id;
        }
    }
}

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
    // Non-logged in users can only view preview (limited pages)
    // They must login and purchase to view full content
    $has_purchased = false;
}

// Load active emojis for comments
$active_emojis = $VSD->get_results("SELECT name, file_path, shortcode FROM emojis WHERE is_active = 1") ?: [];
$emoji_map = [];
foreach($active_emojis as $e) {
    $emoji_map[$e['name']] = $e['file_path'];
}

if(!file_exists($file_path)) {
    header("HTTP/1.0 404 Not Found");
    $page_title = 'File Not Found - VietStuDocs';
    include '../includes/head.php';
    include '../includes/sidebar.php';
    ?>
    <div class="drawer-content flex flex-col">
        <?php include '../includes/navbar.php'; ?>
        <main class="flex-1 min-h-screen flex items-center justify-center p-6">
            <div class="card bg-base-100 shadow-xl max-w-2xl w-full">
                <div class="card-body text-center">
                    <div class="flex justify-center mb-4">
                        <?php if(!empty(getSetting('site_logo'))): ?>
                            <img src="<?= htmlspecialchars(getSetting('site_logo')) ?>" alt="File Not Found" class="h-24 mx-auto opacity-50 grayscale">
                        <?php else: ?>
                            <i class="fa-solid fa-folder-open text-6xl text-base-content/50"></i>
                        <?php endif; ?>
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
        <?php include '../includes/footer.php'; ?>
    </div>
    </div>
    <?php
    // db connection cleaned up by app flow
    exit;
}

// Handle actions (like, dislike, report, save, comments)
handleViewDocumentActions($VSD, $doc_id, $user_id, $doc);

// Get comments with extra info
$all_comments = $VSD->get_list("
    SELECT c.*, u.username, u.avatar,
    (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id) as like_count,
    " . ($is_logged_in ? "(SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id AND user_id = $user_id)" : "0") . " as liked_by_user
    FROM document_comments c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.document_id = $doc_id 
    ORDER BY c.is_pinned DESC, c.created_at DESC
");

// Organize comments into threading (simple Parent -> Children array)
$comments_tree = [];
$comments_map = [];

// Initialize
foreach ($all_comments as $c) {
    if (!$c['parent_id']) {
        $c['replies'] = [];
        $comments_map[$c['id']] = $c;
    }
}

// Map replies specifically to top-level parents for 1-level nesting (or general nesting)
// Note: SQL ORDER BY DESC puts newest first. For replies, usually we want oldest first? Or newest. Let's keep newest first.
foreach ($all_comments as $c) {
    if ($c['parent_id']) {
        if (isset($comments_map[$c['parent_id']])) {
            $comments_map[$c['parent_id']]['replies'][] = $c;
        }
    }
}
// Note: This simple logic only works if parent comes before child or we iterate twice. 
// Since we fetch all, better to do two passes or filter.
// Robust way:
$root_comments = [];
$reply_comments = [];
foreach ($all_comments as $c) {
    if ($c['parent_id']) {
        $reply_comments[] = $c;
    } else {
        $root_comments[$c['id']] = $c;
        $root_comments[$c['id']]['replies'] = [];
    }
}
// Reverse replies to show oldest first? Or keep DESC for newest first. Usually replies are chronological? 
// Let's stick to DESC (newest at top) for now as query does.
foreach ($reply_comments as $c) {
    if (isset($root_comments[$c['parent_id']])) {
        // Prepend or append. Query is DESC, so first item is newest. 
        // If we want replies newest at top: append.
        $root_comments[$c['parent_id']]['replies'][] = $c;
    }
}
$comments = $root_comments; // Replace the flat list with tree


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
function downloadFileWithSpeedLimit($file_path, $speed_limit_kbps = 100, $original_name = null) {
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
    
    // Use original_name if provided, otherwise fallback to basename
    $download_filename = $original_name ? $original_name : basename($file_path);
    
    // Send headers
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . htmlspecialchars($download_filename, ENT_QUOTES, 'UTF-8') . '"');
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

// Handle download - redirect to secure download handler
if(isset($_GET['download'])) {
    header("Location: handler/download.php?id=" . $doc_id);
    exit;
}
?>
<?php
$page_title = htmlspecialchars($doc['original_name']) . ' - VietStuDocs';

// Generate SEO description
$seo_desc = "Xem và tải xuống tài liệu " . htmlspecialchars($doc['original_name']);
if (isset($doc_category['doc_type_name'])) {
    $seo_desc .= " thuộc danh mục " . htmlspecialchars($doc_category['doc_type_name']);
}
$seo_desc .= " tại VietStuDocs. Nền tảng chia sẻ tài liệu học tập hàng đầu.";
$page_description = $seo_desc;

// Generate SEO keywords
$seo_keywords = htmlspecialchars($doc['original_name']) . ", tài liệu, download, tải xuống, VietStuDocs";
if (isset($doc_category['doc_type_name'])) {
    $seo_keywords .= ", " . htmlspecialchars($doc_category['doc_type_name']);
}
if (isset($doc_category['education_level'])) {
    $seo_keywords .= ", " . htmlspecialchars($doc_category['education_level']);
}
$page_keywords = $seo_keywords;

include '../includes/head.php';
include '../includes/sidebar.php';
?>
<!-- PDF.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

<!-- Shared PDF functions for page counting and thumbnail generation -->
<script src="../js/pdf-functions.js"></script>

<!-- DOCX Preview Library - docx-preview with pagination support -->
<!-- JSZip is required dependency for docx-preview -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/docx-preview@0.1.4/dist/docx-preview.min.js"></script>
    
<link rel="stylesheet" href="../css/pages/view.css">

<script>
    const VSD_CONFIG = {
        isLoggedIn: <?= $is_logged_in ? 'true' : 'false' ?>,
        user_id: <?= $user_id ? $user_id : 'null' ?>,
        docId: <?= $doc_id ?>,
        price: <?= $doc['price'] ?? 0 ?>,
        hasPurchased: <?= $has_purchased ? 'true' : 'false' ?>,
        originalName: <?= json_encode($doc['original_name']) ?>,
        totalPages: <?= $doc['total_pages'] ?? 0 ?>,
        hasThumbnail: <?= !empty($doc['thumbnail']) ? 'true' : 'false' ?>,
        limitPreviewPages: <?= (int)getSetting('limit_preview_pages', 5) ?>,
        fileExt: '<?= $file_ext ?>',
        pdfPath: <?= $pdf_path_js ? '"' . htmlspecialchars($pdf_path_js, ENT_QUOTES, 'UTF-8') . '"' : 'null' ?>,
        emojiMap: <?= json_encode($emoji_map) ?>
    };
</script>
<div class="drawer-content flex flex-col">
    <?php include '../includes/navbar.php'; ?>
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
                        <?php if(!empty($doc['avatar']) && file_exists('../uploads/avatars/' . $doc['avatar'])): ?>
                            <img src="../uploads/avatars/<?= $doc['avatar'] ?>" class="w-full h-full object-cover">
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
            // Get pricing: user_price from documents, admin_points from docs_points
            // Logic: use user_price if > 0, otherwise use admin_points
            $pricing_query = "SELECT d.user_price, dp.admin_points 
                             FROM documents d 
                             LEFT JOIN docs_points dp ON d.id = dp.document_id 
                             WHERE d.id = $doc_id";
            $pricing_data = db_get_row($pricing_query);
            $price = 0;
            if($pricing_data) {
                // user_price can be NULL, 0, or > 0
                $user_price = isset($pricing_data['user_price']) && $pricing_data['user_price'] !== null ? intval($pricing_data['user_price']) : null;
                $admin_points = intval($pricing_data['admin_points'] ?? 0);
                // Logic: NULL -> admin_points, 0 -> 0 (free), > 0 -> user_price
                if ($user_price === null) {
                    $price = $admin_points; // Use admin_points if user_price is NULL
                } else {
                    $price = $user_price; // Use user_price (can be 0 for free or > 0)
                }
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
                        <a href="/login" class="btn-vsd btn-vsd-secondary">ĐĂNG NHẬP ĐỂ TƯƠNG TÁC</a>
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
                <div class="text-sm leading-relaxed text-base-content/70 whitespace-pre-wrap"><?php if(!empty($doc['description'])): ?><?= htmlspecialchars(trim($doc['description'])) ?><?php else: ?><span class="italic opacity-50">Tài liệu này chưa có mô tả chi tiết.</span><?php endif; ?></div>
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
        </div>

        <!-- Viewer Section -->
        <div class="viewer-card-vsd <?= !$has_purchased ? 'protected' : '' ?>">
            <?php if(!$has_purchased): ?>
                <div class="preview-banner-vsd">
                    <div class="preview-banner-info">
                        <div class="preview-icon-box">
                            <i class="fa-solid fa-eye"></i>
                        </div>
                        <div class="preview-text">
                            <h3>Bản Xem Trước</h3>
                            <p>Bạn đang xem 5 trang đầu. Sở hữu ngay để xem toàn bộ tài liệu.</p>
                        </div>
                    </div>
                    
                    <div class="preview-price-area">
                        <div class="price-value-vsd">
                            <span class="price-num-vsd"><?= number_format($price) ?></span>
                            <span class="price-txt-vsd">Điểm tích lũy</span>
                        </div>
                        
                        <?php if($is_logged_in): ?>
                            <button class="btn btn-primary h-14 px-8 rounded-2xl font-black text-xs tracking-widest" onclick="openPurchaseModal(<?= $doc_id ?>, <?= $price ?>)">
                                MUA NGAY
                            </button>
                        <?php else: ?>
                            <a href="/login" class="btn btn-primary h-14 px-8 rounded-2xl font-black text-xs tracking-widest">
                                ĐĂNG NHẬP
                            </a>
                        <?php endif; ?>
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
                    $pdf_path_for_preview = '../handler/file.php?doc_id=' . $doc_id;
                    break;
                case 'docx':
                case 'doc':
                    // Check if converted PDF exists - use it for preview instead of DOCX
                    $converted_path = $doc['converted_pdf_path'] ?? '';
                    $abs_converted_path = __DIR__ . '/../' . ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $converted_path), DIRECTORY_SEPARATOR);
                    if (!empty($converted_path) && file_exists($abs_converted_path)) {
                        // Use PDF preview from converted PDF
                        echo '<div class="pdf-viewer-wrapper relative">
                                <div class="pdf-viewer" id="pdfViewer"></div>
                                <div class="pdf-page-counter" id="pdfPageCounter">
                                    <span id="currentPageNum">1</span> / <span id="totalPagesNum">--</span>
                                </div>
                              </div>';
                        // Store converted PDF path for JavaScript (nếu là đường dẫn tương đối, cần kiểm tra)
                        // Nếu converted_pdf_path là đường dẫn trong uploads, sử dụng handler
                        if (strpos($converted_path, '../uploads/') === 0 || strpos($converted_path, '\\uploads\\') !== false) {
                            $pdf_path_for_preview = '../handler/file.php?doc_id=' . $doc_id;
                        } else {
                            $pdf_path_for_preview = $converted_path;
                        }
                    } else {
                        // Fallback to DOCX viewer but use a consistent container
                        echo '<div class="pdf-viewer-wrapper relative">
                                <div class="pdf-viewer docx-mode" id="docxViewer"></div>
                              </div>';
                        $pdf_path_for_preview = null;
                    }
                    break;
                case 'jpg':
                case 'jpeg':
                case 'png':
                case 'gif':
                case 'webp':
                    $file_url = '../handler/file.php?doc_id=' . $doc_id;
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
                    <div class="watermark-text"><?= htmlspecialchars(strtoupper($site_name)) ?> - BẢN QUYỀN</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Comment Section -->
        <div class="comment-section-vsd">
            <h3 class="info-card-title-vsd mb-6">
                <i class="fa-solid fa-comments"></i> BÌNH LUẬN (<span id="commentCount"><?= count($all_comments) ?></span>)
            </h3>
            
            <?php if($is_logged_in): ?>
                <div class="flex gap-4 mb-8">
                    <div class="shrink-0">
                        <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center overflow-hidden">
                            <?php 
                            $curr_user = $VSD->get_row("SELECT avatar FROM users WHERE id = $user_id");
                            if(!empty($curr_user['avatar']) && file_exists('../uploads/avatars/' . $curr_user['avatar'])): 
                            ?>
                                <img src="../uploads/avatars/<?= $curr_user['avatar'] ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="fa-solid fa-user text-primary text-sm"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex-1 relative">
                        <div class="comment-input-wrapper relative flex items-center bg-base-200/50 rounded-[2rem] border border-base-content/10 px-2 py-1 transition-all focus-within:bg-base-100 focus-within:border-primary/50 focus-within:shadow-lg focus-within:shadow-primary/5">
                            <textarea id="commentContent" class="textarea textarea-ghost bg-transparent border-none outline-none focus:bg-transparent shadow-none w-full text-sm resize-none min-h-[44px] py-3 pl-4 leading-tight placeholder:text-base-content/60 !text-base-content/60 focus:!text-base-content " placeholder="Bạn thấy tài liệu này thế nào?" rows="1" oninput="updateCommentUI(this)"></textarea>
                            
                            <div class="flex items-center gap-2 pr-1 shrink-0">
                                <button onclick="toggleEmojiPicker('commentContent')" class="btn btn-circle btn-ghost btn-sm h-9 w-9 text-base-content/60 hover:text-primary hover:bg-base-content/5 transition-colors" title="Chèn Emoji">
                                    <i class="fa-regular fa-face-smile text-lg"></i>
                                </button>
                                
                                <button onclick="handlePostComment()" class="btn btn-circle btn-primary btn-sm h-9 w-9 text-white shadow-md shadow-primary/30 grid place-items-center transition-all" id="postCommentBtn">
                                    <i class="fa-solid fa-paper-plane text-xs pr-0.5 pt-px"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-base-200/50 rounded-2xl p-6 text-center mb-8 border border-base-content/5">
                    <p class="text-base-content/60 text-sm mb-3">Vui lòng đăng nhập để tham gia thảo luận</p>
                    <a href="/login" class="btn btn-primary btn-sm rounded-xl px-6">Đăng nhập ngay</a>
                </div>
            <?php endif; ?>

            <div class="space-y-6" id="commentsList">
                <?php if(empty($comments)): ?>
                    <div id="noCommentsMsg" class="text-center py-10 opacity-50">
                        <i class="fa-regular fa-comments text-4xl mb-3"></i>
                        <p>Chưa có bình luận nào. Hãy là người đầu tiên!</p>
                    </div>
                <?php else: ?>
                    <?php foreach($comments as $comment): ?>
                        <div class="comment-item" id="comment-<?= $comment['id'] ?>">
                            <div class="flex gap-4">
                                <div class="shrink-0">
                                    <div class="w-10 h-10 rounded-full bg-base-300 flex items-center justify-center overflow-hidden">
                                        <?php if(!empty($comment['avatar']) && file_exists('../uploads/avatars/' . $comment['avatar'])): ?>
                                            <img src="../uploads/avatars/<?= $comment['avatar'] ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="bg-primary/10 w-full h-full flex items-center justify-center text-primary font-bold">
                                                <?= strtoupper(substr($comment['username'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex-1 space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm"><?= htmlspecialchars($comment['username']) ?></span>
                                        <?php if($comment['user_id'] == $doc['user_id']): ?>
                                            <span class="badge badge-xs badge-primary font-bold">Tác giả</span>
                                        <?php endif; ?>
                                        <span class="text-xs text-base-content/50"><?= date('H:i d/m/Y', strtotime($comment['created_at'])) ?></span>
                                        <?php if($comment['is_pinned']): ?>
                                            <div class="badge badge-warning badge-xs gap-1 font-bold"><i class="fa-solid fa-thumbtack text-[10px]"></i> Đã ghim</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="group relative">
                                        <p class="text-sm text-base-content/80 leading-relaxed" id="comment-content-<?= $comment['id'] ?>"><?= render_comment_content($comment['content'], $emoji_map) ?></p>
                                        <!-- Edit Form -->
                                        <div id="edit-form-<?= $comment['id'] ?>" class="hidden mt-2">
                                            <textarea id="edit-input-<?= $comment['id'] ?>" class="textarea textarea-bordered w-full text-sm min-h-[60px]"></textarea>
                                            <div class="flex justify-end gap-2 mt-2">
                                                <button onclick="cancelEdit('<?= $comment['id'] ?>')" class="btn btn-xs btn-ghost">Hủy</button>
                                                <button onclick="saveEdit('<?= $comment['id'] ?>')" class="btn btn-xs btn-primary">Lưu</button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Action buttons -->
                                    <div class="flex gap-4 pt-1 items-center">
                                        <!-- Like -->
                                        <button id="like-btn-<?= $comment['id'] ?>" onclick="likeComment('<?= $comment['id'] ?>')" class="flex items-center gap-1 text-xs font-bold transition-colors <?= $comment['liked_by_user'] ? 'text-error' : 'text-base-content/40 hover:text-error' ?>">
                                            <i class="fa-solid fa-heart"></i>
                                            <span id="like-count-<?= $comment['id'] ?>"><?= $comment['like_count'] > 0 ? $comment['like_count'] : '' ?></span>
                                        </button>

                                        <?php if($is_logged_in): ?>
                                            <button class="text-xs font-bold text-base-content/40 hover:text-primary transition-colors" onclick="toggleReply('<?= $comment['id'] ?>')">Trả lời</button>
                                        <?php endif; ?>
                                        
                                        <!-- Menu Dropdown -->
                                        <?php if($is_logged_in): ?>
                                            <div class="dropdown dropdown-end">
                                                <div tabindex="0" role="button" class="btn btn-ghost btn-xs btn-circle text-base-content/30"><i class="fa-solid fa-ellipsis"></i></div>
                                                <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52 text-xs">
                                                    <?php if($doc['user_id'] == $user_id): // Doc Owner Actions ?>
                                                        <?php if($comment['is_pinned']): ?>
                                                            <li><a onclick="actionComment('unpin', <?= $comment['id'] ?>)"><i class="fa-solid fa-thumbtack-slash"></i> Bỏ ghim</a></li>
                                                        <?php else: ?>
                                                            <li><a onclick="actionComment('pin', <?= $comment['id'] ?>)"><i class="fa-solid fa-thumbtack"></i> Ghim bình luận</a></li>
                                                        <?php endif; ?>
                                                    <?php endif; ?>

                                                    <?php if($comment['user_id'] == $user_id): // Comment Owner Actions ?>
                                                        <li><a onclick="startEdit('<?= $comment['id'] ?>')"><i class="fa-solid fa-pen"></i> Chỉnh sửa</a></li>
                                                    <?php endif; ?>

                                                    <?php if($comment['user_id'] == $user_id || $doc['user_id'] == $user_id): // Delete ?>
                                                        <li><a onclick="actionComment('delete', <?= $comment['id'] ?>)" class="text-error"><i class="fa-solid fa-trash"></i> Xóa</a></li>
                                                    <?php endif; ?>

                                                    <li><a onclick="showReportModal(<?= $comment['id'] ?>)"><i class="fa-solid fa-flag"></i> Báo cáo</a></li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Reply Form (Hidden) -->
                                    <div id="reply-form-<?= $comment['id'] ?>" class="hidden mt-3 pl-4 animate-fade-in relative">
                                        <div class="flex gap-3">
                                            <div class="w-8 h-8 rounded-full bg-base-200 flex items-center justify-center shrink-0">
                                                <i class="fa-solid fa-reply text-xs text-base-content/40"></i>
                                            </div>
                                            <div class="flex-1 relative">
                                                <div class="comment-input-area mb-2 relative flex flex-col">
                                                    <textarea id="reply-content-<?= $comment['id'] ?>" class="textarea textarea-ghost w-full focus:bg-transparent focus:outline-none min-h-[44px] max-h-[200px] overflow-y-auto text-sm placeholder:text-base-content/60" placeholder="Viết câu trả lời..." oninput="updateCommentUI(this)"></textarea>
                                                    <div class="flex justify-between items-center px-3 pb-2">
                                                        <button onclick="toggleEmojiPicker('reply-content-<?= $comment['id'] ?>')" class="vsd-emoji-btn vsd-emoji-btn-sm" title="Chèn Emoji">
                                                            <i class="fa-regular fa-face-smile"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="flex justify-end gap-2">
                                                    <button onclick="toggleReply('<?= $comment['id'] ?>')" class="btn btn-ghost btn-xs">Hủy</button>
                                                    <button onclick="handlePostComment('<?= $comment['id'] ?>')" class="btn btn-primary btn-xs">Gửi</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Nested Replies -->
                            <div class="pl-14 space-y-4 mt-4" id="replies-<?= $comment['id'] ?>">
                                <?php 
                                $replyCount = count($comment['replies']);
                                $visibleReplies = array_slice($comment['replies'], 0, 3);
                                $hiddenReplies = array_slice($comment['replies'], 3);
                                ?>

                                <?php foreach($visibleReplies as $reply): ?>
                                    <div class="flex gap-4" id="comment-<?= $reply['id'] ?>">
                                        <div class="shrink-0">
                                            <div class="w-8 h-8 rounded-full bg-base-300 flex items-center justify-center overflow-hidden">
                                                <?php if(!empty($reply['avatar']) && file_exists('../uploads/avatars/' . $reply['avatar'])): ?>
                                                    <img src="../uploads/avatars/<?= $reply['avatar'] ?>" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <div class="bg-primary/10 w-full h-full flex items-center justify-center text-primary font-bold text-xs">
                                                        <?= strtoupper(substr($reply['username'], 0, 1)) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="flex-1 space-y-1">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-sm"><?= htmlspecialchars($reply['username']) ?></span>
                                                <?php if($reply['user_id'] == $doc['user_id']): ?>
                                                    <span class="badge badge-xs badge-primary font-bold">Tác giả</span>
                                                <?php endif; ?>
                                                <span class="text-xs text-base-content/50"><?= date('H:i d/m/Y', strtotime($reply['created_at'])) ?></span>
                                            </div>
                                            
                                            <div class="group relative">
                                                <p class="text-sm text-base-content/80 leading-relaxed" id="comment-content-<?= $reply['id'] ?>"><?= render_comment_content($reply['content'], $emoji_map) ?></p>
                                                <!-- Edit Form -->
                                                <div id="edit-form-<?= $reply['id'] ?>" class="hidden mt-2">
                                                    <textarea id="edit-input-<?= $reply['id'] ?>" class="textarea textarea-bordered w-full text-sm min-h-[60px]"></textarea>
                                                    <div class="flex justify-end gap-2 mt-2">
                                                        <button onclick="cancelEdit('<?= $reply['id'] ?>')" class="btn btn-xs btn-ghost">Hủy</button>
                                                        <button onclick="saveEdit('<?= $reply['id'] ?>')" class="btn btn-xs btn-primary">Lưu</button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Action buttons for replies -->
                                             <div class="flex gap-4 pt-1 items-center">
                                                 <!-- Like -->
                                                 <button id="like-btn-<?= $reply['id'] ?>" onclick="likeComment('<?= $reply['id'] ?>')" class="flex items-center gap-1 text-xs font-bold transition-colors <?= $reply['liked_by_user'] ? 'text-error' : 'text-base-content/40 hover:text-error' ?>">
                                                     <i class="fa-solid fa-heart"></i>
                                                     <span id="like-count-<?= $reply['id'] ?>"><?= $reply['like_count'] > 0 ? $reply['like_count'] : '' ?></span>
                                                 </button>

                                                <!-- Menu Dropdown -->
                                                <?php if($is_logged_in): ?>
                                                    <div class="dropdown dropdown-end">
                                                        <div tabindex="0" role="button" class="btn btn-ghost btn-xs btn-circle text-base-content/30"><i class="fa-solid fa-ellipsis"></i></div>
                                                        <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52 text-xs">
                                                            <?php if($reply['user_id'] == $user_id): // Reply Owner Actions ?>
                                                                <li><a onclick="startEdit('<?= $reply['id'] ?>')"><i class="fa-solid fa-pen"></i> Chỉnh sửa</a></li>
                                                            <?php endif; ?>

                                                            <?php if($reply['user_id'] == $user_id || $doc['user_id'] == $user_id): // Delete ?>
                                                                <li><a onclick="actionComment('delete', <?= $reply['id'] ?>)" class="text-error"><i class="fa-solid fa-trash"></i> Xóa</a></li>
                                                            <?php endif; ?>

                                                            <li><a onclick="showReportModal(<?= $reply['id'] ?>)"><i class="fa-solid fa-flag"></i> Báo cáo</a></li>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <?php if($replyCount > 3): ?>
                                    <div id="hidden-replies-<?= $comment['id'] ?>" class="hidden space-y-4">
                                        <?php foreach($hiddenReplies as $reply): ?>
                                            <div class="flex gap-4" id="comment-<?= $reply['id'] ?>">
                                                <div class="shrink-0">
                                                    <div class="w-8 h-8 rounded-full bg-base-300 flex items-center justify-center overflow-hidden">
                                                        <?php if(!empty($reply['avatar']) && file_exists('../uploads/avatars/' . $reply['avatar'])): ?>
                                                            <img src="../uploads/avatars/<?= $reply['avatar'] ?>" class="w-full h-full object-cover">
                                                        <?php else: ?>
                                                            <div class="bg-primary/10 w-full h-full flex items-center justify-center text-primary font-bold text-xs">
                                                                <?= strtoupper(substr($reply['username'], 0, 1)) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="flex-1 space-y-1">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-sm"><?= htmlspecialchars($reply['username']) ?></span>
                                                <?php if($reply['user_id'] == $doc['user_id']): ?>
                                                    <span class="badge badge-xs badge-primary font-bold">Tác giả</span>
                                                <?php endif; ?>
                                                <span class="text-xs text-base-content/50"><?= date('H:i d/m/Y', strtotime($reply['created_at'])) ?></span>
                                            </div>
                                            
                                            <div class="group relative">
                                                <p class="text-sm text-base-content/80 leading-relaxed" id="comment-content-<?= $reply['id'] ?>"><?= render_comment_content($reply['content'], $emoji_map) ?></p>
                                                <!-- Edit Form -->
                                                <div id="edit-form-<?= $reply['id'] ?>" class="hidden mt-2">
                                                    <textarea id="edit-input-<?= $reply['id'] ?>" class="textarea textarea-bordered w-full text-sm min-h-[60px]"></textarea>
                                                    <div class="flex justify-end gap-2 mt-2">
                                                        <button onclick="cancelEdit('<?= $reply['id'] ?>')" class="btn btn-xs btn-ghost">Hủy</button>
                                                        <button onclick="saveEdit('<?= $reply['id'] ?>')" class="btn btn-xs btn-primary">Lưu</button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Action buttons for hidden replies -->
                                            <div class="flex gap-4 pt-1 items-center">
                                                <!-- Like -->
                                                <button id="like-btn-<?= $reply['id'] ?>" onclick="likeComment('<?= $reply['id'] ?>')" class="flex items-center gap-1 text-xs font-bold transition-colors <?= $reply['liked_by_user'] ? 'text-error' : 'text-base-content/40 hover:text-error' ?>">
                                                    <i class="fa-solid fa-heart"></i>
                                                    <span id="like-count-<?= $reply['id'] ?>"><?= $reply['like_count'] > 0 ? $reply['like_count'] : '' ?></span>
                                                </button>

                                                <!-- Menu Dropdown -->
                                                <?php if($is_logged_in): ?>
                                                    <div class="dropdown dropdown-end">
                                                        <div tabindex="0" role="button" class="btn btn-ghost btn-xs btn-circle text-base-content/30"><i class="fa-solid fa-ellipsis"></i></div>
                                                        <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52 text-xs">
                                                            <?php if($reply['user_id'] == $user_id): // Reply Owner Actions ?>
                                                                <li><a onclick="startEdit('<?= $reply['id'] ?>')"><i class="fa-solid fa-pen"></i> Chỉnh sửa</a></li>
                                                            <?php endif; ?>

                                                            <?php if($reply['user_id'] == $user_id || $doc['user_id'] == $user_id): // Delete ?>
                                                                <li><a onclick="actionComment('delete', <?= $reply['id'] ?>)" class="text-error"><i class="fa-solid fa-trash"></i> Xóa</a></li>
                                                            <?php endif; ?>

                                                            <li><a onclick="showReportModal(<?= $reply['id'] ?>)"><i class="fa-solid fa-flag"></i> Báo cáo</a></li>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button onclick="toggleHiddenReplies('<?= $comment['id'] ?>')" id="btn-show-more-<?= $comment['id'] ?>" class="btn btn-ghost btn-xs text-primary font-bold mt-2">
                                        Xem thêm <?= $replyCount - 3 ?> câu trả lời...
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
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
                <div class="card bg-base-100 shadow-md mb-8">
                <div class="card-body">
                    <h2 class="card-title text-xl mb-4">
                        <i class="fa-solid fa-folder"></i>
                        Cùng chủ đề: <?= $category_name ?>
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
                        Tài liệu khác
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
        <?php include '../includes/footer.php'; ?>
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
        <div class="modal-box max-w-md rounded-[2.5rem] p-8 bg-base-100/90 backdrop-blur-2xl border border-white/10 shadow-2xl">
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-4 top-4">✕</button>
            </form>
            <div class="text-center mb-8">
                <div class="w-24 h-24 bg-primary/10 rounded-3xl flex items-center justify-center mx-auto mb-6">
                    <i class="fa-solid fa-cart-shopping text-4xl text-primary"></i>
                </div>
                <h3 class="font-black text-2xl mb-2 uppercase tracking-tight">Mua Tài Liệu</h3>
                <p class="text-sm text-base-content/60">Bạn đang thực hiện giao dịch mua tài liệu học tập</p>
            </div>
            
            <div class="bg-base-200/50 rounded-[2rem] p-8 text-center mb-8 border border-base-content/5">
                <div class="text-[10px] font-black uppercase tracking-[0.2em] text-base-content/40 mb-4">Giá tài liệu</div>
                <div class="text-6xl font-black text-primary tracking-tighter mb-2" id="purchasePrice">0 điểm</div>
                <div class="text-[11px] font-bold text-base-content/50 uppercase tracking-widest">Tài liệu sẽ được mở khóa vĩnh viễn</div>
            </div>

            <div class="flex flex-col gap-3">
                <button id="confirmPurchaseBtn" onclick="confirmPurchase(event)" class="btn btn-primary btn-lg rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-primary/20 h-16">
                    Xác Nhận Mua
                </button>
                <button type="button" class="btn btn-ghost btn-md rounded-xl font-bold opacity-60 hover:opacity-100" onclick="this.closest('dialog').close()">
                    Hủy Giao Dịch
                </button>
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
                <button class="btn btn-ghost btn-xs btn-circle" onclick="cancelDownload()" title="Hủy tải xuống">
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
                    <button type="button" class="btn btn-ghost" onclick="this.closest('dialog').close()">Hủy</button>
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


<script src="../js/pdf-viewer.js"></script>
<script src="../js/pages/view.js?v=2.02"></script>
</body>
</html>
