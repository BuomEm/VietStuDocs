<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/file.php';
require_once __DIR__ . '/../config/categories.php';

// Check admin permission
redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Xem tài liệu - Admin Panel";

$doc_id = intval($_GET['id'] ?? 0);

if($doc_id <= 0) {
    header("Location: pending-docs.php");
    exit;
}

// Get document (allow viewing any status for admin)
$query = "SELECT d.*, u.username FROM documents d 
          JOIN users u ON d.user_id = u.id 
          WHERE d.id=$doc_id";
$result = mysqli_query($conn, $query);
$doc = mysqli_fetch_assoc($result);

if(!$doc) {
    header("HTTP/1.0 404 Not Found");
    $page_title = "Tài liệu không tồn tại - Admin Panel";
    include __DIR__ . '/../includes/admin-header.php';
    ?>
    <div class="p-6">
        <div class="container mx-auto max-w-7xl">
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="text-center py-12">
                        <i class="fa-solid fa-magnifying-glass text-6xl text-base-content/30 mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Không tìm thấy tài liệu</h3>
                        <p class="text-base-content/70 mb-4">Tài liệu này không tồn tại trong hệ thống.</p>
                        <a href="pending-docs.php" class="btn btn-primary">
                            <i class="fa-solid fa-arrow-left mr-2"></i>Quay lại
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/../includes/admin-footer.php';
    mysqli_close($conn);
    exit;
}

$file_path = UPLOAD_DIR . $doc['file_name'];
$file_ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));

if(!file_exists($file_path)) {
    header("HTTP/1.0 404 Not Found");
    $page_title = "File không tồn tại - Admin Panel";
    include __DIR__ . '/../includes/admin-header.php';
    ?>
    <div class="p-6">
        <div class="container mx-auto max-w-7xl">
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="text-center py-12">
                        <i class="fa-solid fa-file-circle-xmark text-6xl text-base-content/30 mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">File không tìm thấy</h3>
                        <p class="text-base-content/70 mb-4">File của tài liệu này đã bị xóa hoặc không tồn tại trên server.</p>
                        <a href="pending-docs.php" class="btn btn-primary">
                            <i class="fa-solid fa-arrow-left mr-2"></i>Quay lại
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/../includes/admin-footer.php';
    mysqli_close($conn);
    exit;
}

// Handle download - redirect to secure download handler
if(isset($_GET['download'])) {
    header("Location: ../handler/download.php?id=" . $doc_id);
    exit;
}

// Format file size helper function
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i) * 100) / 100 . ' ' . $sizes[$i];
}

// Load document categories
$doc_category = getDocumentCategoryWithNames($doc_id);

// Get unread notifications count
$unread_notifications = mysqli_num_rows(mysqli_query($conn, 
    "SELECT id FROM admin_notifications WHERE admin_id=$admin_id AND is_read=0"));

// For shared admin sidebar
$admin_active_page = 'pending';

// Include header
include __DIR__ . '/../includes/admin-header.php';
?>

<!-- Page Header with Backdrop Blur -->
<div class="bg-base-100/80 backdrop-blur-md sticky top-0 z-40 border-b border-base-300">
    <div class="container mx-auto max-w-[1600px] px-6 py-4">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex-1 min-w-0">
                <div class="breadcrumbs text-sm mb-1 opacity-60">
                    <ul>
                        <li><a href="pending-docs.php" class="hover:text-primary transition-colors">Tài liệu chờ duyệt</a></li>
                        <li class="overflow-hidden text-ellipsis whitespace-nowrap max-w-[200px]"><?= htmlspecialchars($doc['original_name']) ?></li>
                    </ul>
                </div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl md:text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-primary to-accent truncate">
                        <?= htmlspecialchars($doc['original_name']) ?>
                    </h1>
                    <?php if($doc['status'] === 'pending'): ?>
                        <span class="badge badge-warning badge-lg shadow-sm border-0 font-bold whitespace-nowrap shrink-0">Chờ duyệt</span>
                    <?php elseif($doc['status'] === 'approved'): ?>
                        <span class="badge badge-success badge-lg shadow-sm border-0 font-bold whitespace-nowrap shrink-0">Đã duyệt</span>
                    <?php elseif($doc['status'] === 'rejected'): ?>
                        <span class="badge badge-error badge-lg shadow-sm border-0 font-bold whitespace-nowrap shrink-0">Đã từ chối</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="flex items-center gap-3 shrink-0">
                <a href="../handler/download.php?id=<?= $doc_id ?>" class="btn btn-primary shadow-lg shadow-primary/20 hover:scale-105 transition-transform">
                    <i class="fa-solid fa-download text-lg"></i>
                    <span class="hidden sm:inline">Tải xuống</span>
                </a>
                <a href="pending-docs.php" class="btn btn-ghost hover:bg-base-content/10">
                    <i class="fa-solid fa-arrow-right-from-bracket text-lg"></i>
                    <span class="hidden sm:inline">Quay lại</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="p-6 animate-fade-in">
    <div class="container mx-auto max-w-[1600px]">
        
        <!-- Stats Row -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="stat bg-base-100 shadow-lg rounded-2xl border border-base-200 hover:shadow-xl transition-shadow duration-300">
                <div class="stat-figure text-primary">
                    <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center">
                        <i class="fa-solid fa-file-lines text-2xl"></i>
                    </div>
                </div>
                <div class="stat-title opacity-70">Định dạng</div>
                <div class="stat-value text-primary text-2xl uppercase">.<?= $file_ext ?></div>
                <div class="stat-desc">Loại tệp tin</div>
            </div>
            
            <div class="stat bg-base-100 shadow-lg rounded-2xl border border-base-200 hover:shadow-xl transition-shadow duration-300">
                <div class="stat-figure text-secondary">
                    <div class="w-12 h-12 rounded-xl bg-secondary/10 flex items-center justify-center">
                        <i class="fa-solid fa-hard-drive text-2xl"></i>
                    </div>
                </div>
                <div class="stat-title opacity-70">Kích thước</div>
                <div class="stat-value text-secondary text-2xl"><?= formatFileSize(filesize($file_path)) ?></div>
                <div class="stat-desc">Dung lượng lưu trữ</div>
            </div>

            <div class="stat bg-base-100 shadow-lg rounded-2xl border border-base-200 hover:shadow-xl transition-shadow duration-300">
                <div class="stat-figure text-accent">
                    <div class="w-12 h-12 rounded-xl bg-accent/10 flex items-center justify-center">
                        <i class="fa-solid fa-eye text-2xl"></i>
                    </div>
                </div>
                <div class="stat-title opacity-70">Lượt xem</div>
                <div class="stat-value text-accent text-2xl"><?= number_format($doc['views'] ?? 0) ?></div>
                <div class="stat-desc text-success font-medium">
                    <i class="fa-solid fa-download mr-1"></i> <?= number_format($doc['downloads'] ?? 0) ?> lượt tải
                </div>
            </div>

            <div class="stat bg-base-100 shadow-lg rounded-2xl border border-base-200 hover:shadow-xl transition-shadow duration-300">
                <div class="stat-figure text-info">
                    <div class="w-12 h-12 rounded-xl bg-info/10 flex items-center justify-center">
                        <?php if($doc['is_public']): ?>
                            <i class="fa-solid fa-lock-open text-2xl"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-lock text-2xl"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stat-title opacity-70">Chế độ</div>
                <div class="stat-value text-info text-xl translate-y-1">
                    <?= $doc['is_public'] ? 'Công khai' : 'Riêng tư' ?>
                </div>
                <div class="stat-desc">Trạng thái hiển thị</div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
            <!-- Left Sidebar (Info) -->
            <div class="xl:col-span-4 space-y-6">
                <!-- Info Card -->
                <div class="card bg-base-100 shadow-xl border border-base-200 overflow-hidden group">
                    <div class="card-body p-0">
                        <div class="p-6 bg-gradient-to-br from-base-200 to-base-300 border-b border-base-200">
                            <h3 class="font-bold text-lg flex items-center gap-2">
                                <i class="fa-solid fa-circle-info text-primary"></i>
                                Thông tin chi tiết
                            </h3>
                        </div>
                        <div class="p-6 space-y-6">
                            <!-- Uploader -->
                            <div class="flex items-start gap-4 p-4 rounded-xl bg-base-200/50 hover:bg-base-200 transition-colors">
                                <div class="avatar placeholder">
                                    <div class="bg-neutral text-neutral-content rounded-full w-12 shadow-md ring ring-primary ring-offset-base-100 ring-offset-2">
                                        <span class="text-xl font-bold"><?= strtoupper(substr($doc['username'], 0, 1)) ?></span>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm opacity-60 font-medium">Người đăng</div>
                                    <div class="font-bold text-lg"><?= htmlspecialchars($doc['username']) ?></div>
                                    <div class="text-xs opacity-50 mt-1 flex items-center">
                                        <i class="fa-regular fa-clock mr-1"></i>
                                        <?= date('H:i - d/m/Y', strtotime($doc['created_at'])) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Description -->
                            <?php if($doc['description']): ?>
                            <div class="relative">
                                <div class="text-sm opacity-70 font-bold mb-2 uppercase tracking-wider text-xs">Mô tả tài liệu</div>
                                <div class="p-4 rounded-xl bg-base-200/50 text-sm leading-relaxed max-h-40 overflow-y-auto custom-scrollbar border border-base-200/50">
                                    <?= nl2br(htmlspecialchars($doc['description'])) ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Technical Specs -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="p-3 rounded-xl bg-base-200/30 border border-base-200 hover:border-primary/30 transition-colors">
                                    <div class="text-xs opacity-60 mb-1">Tên tệp gốc</div>
                                    <div class="font-medium text-sm truncate" title="<?= htmlspecialchars($doc['file_name']) ?>">
                                        <?= htmlspecialchars($doc['file_name']) ?>
                                    </div>
                                </div>
                                <?php if($doc['total_pages'] > 0): ?>
                                <div class="p-3 rounded-xl bg-base-200/30 border border-base-200 hover:border-primary/30 transition-colors">
                                    <div class="text-xs opacity-60 mb-1">Số trang</div>
                                    <div class="font-medium text-sm">
                                        <?= number_format($doc['total_pages']) ?> trang
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Categories Card -->
                <div class="card bg-base-100 shadow-xl border border-base-200">
                    <div class="card-body p-0">
                        <div class="p-5 border-b border-base-200 flex justify-between items-center bg-base-200/30">
                            <h3 class="font-bold flex items-center gap-2">
                                <i class="fa-solid fa-tags text-accent"></i>
                                Phân loại
                            </h3>
                        </div>
                        <div class="p-5">
                            <?php if($doc_category): ?>
                            <div class="flex flex-wrap gap-2">
                                <!-- Educational Level -->
                                <div class="badge badge-lg badge-primary gap-2 p-3 h-auto">
                                    <i class="fa-solid fa-school text-xs opacity-70"></i>
                                    <?= htmlspecialchars($doc_category['education_level_name']) ?>
                                </div>

                                <!-- Grade -->
                                <?php if(isset($doc_category['grade_name'])): ?>
                                <div class="badge badge-lg badge-ghost border-base-200 bg-base-200/60 gap-2 p-3 h-auto">
                                    <i class="fa-solid fa-layer-group text-xs opacity-70"></i>
                                    <?= htmlspecialchars($doc_category['grade_name']) ?>
                                </div>
                                <?php endif; ?>

                                <!-- Subject -->
                                <?php if(isset($doc_category['subject_name'])): ?>
                                <div class="badge badge-lg badge-info gap-2 p-3 h-auto font-bold text-info-content">
                                    <i class="fa-solid fa-book text-xs opacity-70"></i>
                                    <?= htmlspecialchars($doc_category['subject_name']) ?>
                                </div>
                                <?php endif; ?>

                                <!-- Major Group -->
                                <?php if(isset($doc_category['major_group_name'])): ?>
                                <div class="badge badge-lg badge-secondary gap-2 p-3 h-auto text-secondary-content">
                                    <i class="fa-solid fa-building text-xs opacity-70"></i>
                                    <?= htmlspecialchars($doc_category['major_group_name']) ?>
                                </div>
                                <?php endif; ?>

                                <!-- Major -->
                                <?php if(isset($doc_category['major_name'])): ?>
                                <div class="badge badge-lg badge-ghost border-base-200 bg-base-200/60 gap-2 p-3 h-auto">
                                    <i class="fa-solid fa-graduation-cap text-xs opacity-70"></i>
                                    <?= htmlspecialchars($doc_category['major_name']) ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Doc Type -->
                                <div class="badge badge-lg badge-outline gap-2 p-3 h-auto border-base-content/20">
                                    <i class="fa-solid fa-file-signature text-xs opacity-70"></i>
                                    <?= htmlspecialchars($doc_category['doc_type_name']) ?>
                                </div>
                            </div>
                            <?php else: ?>
                                <div class="alert bg-base-200/50 border-none text-sm">
                                    <i class="fa-solid fa-circle-exclamation opacity-50"></i>
                                    <span>Chưa có thông tin phân loại cho tài liệu này.</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column (Preview) -->
            <div class="xl:col-span-8">
                <div class="card bg-base-100 shadow-xl border border-base-200 h-full overflow-hidden">
                    <div class="card-body p-0 flex flex-col h-full">
                        <div class="p-4 border-b border-base-200 flex justify-between items-center bg-base-200/30">
                            <h3 class="font-bold flex items-center gap-2">
                                <i class="fa-solid fa-eye text-success"></i>
                                Xem trước tài liệu
                            </h3>
                            <div class="flex gap-2">
                                <button onclick="document.getElementById('viewerContainer').classList.toggle('fullscreen-mode')" class="btn btn-sm btn-ghost btn-square" title="Toàn màn hình">
                                    <i class="fa-solid fa-expand"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="relative bg-base-300/30 flex-1 min-h-[600px] flex justify-center backdrop-blur-sm">
                            <div class="w-full h-full overflow-hidden relative" id="viewerContainer">
                                <div class="h-full overflow-y-auto overflow-x-hidden p-6 custom-scrollbar flex flex-col items-center">
                                    <?php
                                    $pdf_path_for_preview = null;
                                    
                                    switch($file_ext) {
                                        case 'pdf':
                                            echo '<div class="pdf-viewer shadow-2xl mx-auto rounded-lg overflow-hidden bg-white/5" id="pdfViewer"></div>';
                                            $pdf_path_for_preview = '../handler/file.php?doc_id=' . $doc_id;
                                            break;
                                        case 'docx':
                                        case 'doc':
                                            // Check if converted PDF exists
                                            $converted_path = !empty($doc['converted_pdf_path']) ? $doc['converted_pdf_path'] : null;
                                            $converted_file_path = $converted_path ? ltrim($converted_path, '/') : null;
                                            
                                            if ($converted_file_path && file_exists(__DIR__ . '/../' . $converted_file_path)) {
                                                echo '<div class="pdf-viewer shadow-2xl mx-auto rounded-lg overflow-hidden bg-white/5" id="pdfViewer"></div>';
                                                // Nếu converted_pdf_path trong uploads, sử dụng handler
                                                if (strpos($converted_file_path, 'uploads/') === 0) {
                                                    $pdf_path_for_preview = '../handler/file.php?doc_id=' . $doc_id;
                                                } else {
                                                    $pdf_path_for_preview = '../' . $converted_file_path;
                                                }
                                            } else {
                                                echo '<div class="docx-viewer bg-white shadow-2xl mx-auto rounded-lg p-8 min-h-[800px]" id="docxViewer"></div>';
                                                $pdf_path_for_preview = null;
                                            }
                                            break;
                                        case 'jpg':
                                        case 'jpeg':
                                        case 'png':
                                        case 'gif':
                                        case 'webp':
                                            $file_url = '../handler/file.php?doc_id=' . $doc_id;
                                            echo '<div class="flex items-center justify-center h-full"><img src="' . $file_url . '" alt="' . htmlspecialchars($doc['original_name']) . '" class="max-w-full max-h-[800px] shadow-2xl rounded-lg object-contain bg-base-200/50"></div>';
                                            break;
                                        case 'txt':
                                        case 'log':
                                        case 'md':
                                            $content = file_get_contents($file_path);
                                            echo '<div class="w-full max-w-4xl mx-auto"><pre class="whitespace-pre-wrap break-words font-mono text-sm bg-base-100 p-8 rounded-lg shadow-xl border border-base-300 text-base-content leading-relaxed">' . htmlspecialchars($content) . '</pre></div>';
                                            break;
                                        default:
                                            echo '<div class="flex flex-col items-center justify-center h-full p-12 text-base-content/50">
                                                    <div class="w-24 h-24 bg-base-200 rounded-full flex items-center justify-center mb-6 ring-4 ring-base-200/50">
                                                        <i class="fa-solid fa-file-circle-question text-5xl opacity-50"></i>
                                                    </div>
                                                    <h3 class="text-xl font-bold mb-2">Không hỗ trợ xem trước</h3>
                                                    <p>Định dạng .' . htmlspecialchars($file_ext) . ' không hỗ trợ xem trực tiếp.</p>
                                                    <a href="../handler/download.php?id=' . $doc_id . '" class="btn btn-primary mt-6 shadow-lg shadow-primary/20">
                                                        <i class="fa-solid fa-download mr-2"></i> Tải về để xem
                                                    </a>
                                                  </div>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Fullscreen Mode */
    .fullscreen-mode {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 1000;
        background: rgba(30, 30, 30, 0.95);
        display: flex;
        justify-content: center;
        padding-top: 2rem;
        backdrop-filter: blur(10px);
    }
    
    .fullscreen-mode > div {
        max-width: 1200px;
        width: 100%;
    }

    /* Custom Scrollbar */
    .custom-scrollbar::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: rgba(0,0,0,0.05);
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.1);
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(255,255,255,0.2);
    }

    /* PDF Viewer Styles */
    .pdf-viewer {
        width: 100%;
        max-width: 900px; /* A4 width approx */
    }
    
    .pdf-viewer canvas {
        max-width: 100%;
        height: auto !important;
        display: block;
        margin: 0 auto 1.5rem auto;
        box-shadow: 0 4px 20px rgba(0,0,0,0.25);
        border-radius: 4px;
    }
    
    /* DOCX Viewer Styles */
    .docx-viewer {
        width: 100%;
        max-width: 900px;
    }
    
    .docx-viewer .docx-wrapper {
        background: transparent !important;
        padding: 0 !important;
    }
    
    .docx-viewer .docx-wrapper > div {
        margin-bottom: 0 !important;
        box-shadow: none !important;
        background-color: transparent !important;
    }
</style>

<!-- PDF.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js"></script>

<!-- DOCX Preview Library -->
<!-- Note: Using a newer version if possible, or sticking to stable -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/docx-preview@0.1.4/dist/docx-preview.min.js"></script>

<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
    
    // Load PDF
    <?php if($file_ext === 'pdf' || (($file_ext === 'docx' || $file_ext === 'doc') && isset($pdf_path_for_preview) && $pdf_path_for_preview)): ?>
    (async () => {
        try {
            const pdfPath = "<?= htmlspecialchars($pdf_path_for_preview, ENT_QUOTES, 'UTF-8') ?>";
            const loadingTask = pdfjsLib.getDocument(pdfPath);
            const pdfDoc = await loadingTask.promise;
            const viewer = document.getElementById("pdfViewer");
            
            if(viewer) {
                viewer.innerHTML = '';
                
                // Render pages with a bit better quality
                for (let pageNum = 1; pageNum <= pdfDoc.numPages; pageNum++) {
                    const page = await pdfDoc.getPage(pageNum);
                    
                    // Use a generic scale first to get dimensions
                    const viewportRaw = page.getViewport({ scale: 1.5 }); // Good baseline quality
                    
                    const canvas = document.createElement("canvas");
                    const context = canvas.getContext("2d");
                    
                    canvas.width = viewportRaw.width;
                    canvas.height = viewportRaw.height;
                    
                    // Styling to make it responsive
                    canvas.style.maxWidth = '100%';
                    canvas.style.height = 'auto';
                    
                    await page.render({
                        canvasContext: context,
                        viewport: viewportRaw
                    }).promise;
                    
                    viewer.appendChild(canvas);
                }
            }
        } catch(error) {
            const viewer = document.getElementById("pdfViewer");
            if(viewer) {
                viewer.innerHTML = `
                    <div class="alert alert-error shadow-lg max-w-lg mx-auto mt-10">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <div>
                            <h3 class="font-bold">Lỗi tải PDF</h3>
                            <div class="text-xs">${error.message}_${error.name}</div>
                        </div>
                    </div>`;
            }
            console.error('PDF loading error:', error);
        }
    })();
    <?php endif; ?>
    
    // Load DOCX
    <?php if(($file_ext === 'docx' || $file_ext === 'doc') && !isset($pdf_path_for_preview)): ?>
    (async () => {
        try {
            // Wait for libraries
            let retries = 0;
            while ((typeof JSZip === 'undefined' || (typeof docx === 'undefined' && typeof docxPreview === 'undefined')) && retries < 20) {
                await new Promise(resolve => setTimeout(resolve, 100));
                retries++;
            }
            
            let docxAPI = window.docx || window.docxPreview;
            const docxViewer = document.getElementById("docxViewer");
            const fileUrl = "../handler/file.php?doc_id=<?= $doc_id ?>";
            
            if (!docxAPI || !docxViewer) throw new Error('Initialization failed');

            const response = await fetch(fileUrl);
            const arrayBuffer = await response.arrayBuffer();
            
            await docxAPI.renderAsync(arrayBuffer, docxViewer, null, {
                className: "docx-wrapper",
                inWrapper: true,
                ignoreWidth: false,
                ignoreHeight: false,
                breakPages: true,
                experimental: true // Try experimental for better rendering
            });
            
        } catch(error) {
            const viewer = document.getElementById("docxViewer");
            if(viewer) {
                viewer.innerHTML = `
                    <div class="alert alert-error shadow-lg max-w-lg mx-auto mt-10">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <div>
                            <h3 class="font-bold">Lỗi tải văn bản</h3>
                            <div class="text-xs">${error.message}</div>
                        </div>
                    </div>`;
            }
            console.error('DOCX loading error:', error);
        }
    })();
    <?php endif; ?>
</script>

<?php 
include __DIR__ . '/../includes/admin-footer.php';
mysqli_close($conn); 
?>
