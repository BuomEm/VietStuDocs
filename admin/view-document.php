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

// Handle download
if(isset($_GET['download'])) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($doc['original_name']) . '"');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
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

<!-- Page Header -->
<div class="p-6 bg-base-100 border-b border-base-300">
    <div class="container mx-auto max-w-7xl">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <div class="breadcrumbs text-sm mb-2">
                    <ul>
                        <li><a href="pending-docs.php">Tài liệu chờ duyệt</a></li>
                        <li><?= htmlspecialchars($doc['original_name']) ?></li>
                    </ul>
                </div>
                <h2 class="text-2xl font-bold"><?= htmlspecialchars($doc['original_name']) ?></h2>
                <p class="text-base-content/70 mt-1">
                    Tác giả: <?= htmlspecialchars($doc['username']) ?> | 
                    Ngày tạo: <?= date('d/m/Y H:i', strtotime($doc['created_at'])) ?>
                </p>
            </div>
            <div class="flex gap-2">
                <a href="view-document.php?id=<?= $doc_id ?>&download=1" class="btn btn-primary">
                    <i class="fa-solid fa-download mr-2"></i>
                    Tải xuống
                </a>
                <a href="pending-docs.php" class="btn btn-ghost">
                    <i class="fa-solid fa-arrow-left mr-2"></i>
                    Quay lại
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Page Body -->
<div class="p-6">
    <div class="container mx-auto max-w-7xl">
        <!-- Status Badges -->
        <div class="flex gap-2 mb-6">
            <?php if($doc['status'] === 'pending'): ?>
                <span class="badge badge-warning badge-lg">Chờ duyệt</span>
            <?php elseif($doc['status'] === 'approved'): ?>
                <span class="badge badge-success badge-lg">Đã duyệt</span>
            <?php elseif($doc['status'] === 'rejected'): ?>
                <span class="badge badge-error badge-lg">Đã từ chối</span>
            <?php endif; ?>
            
            <?php if(!$doc['is_public']): ?>
                <span class="badge badge-outline badge-lg">Riêng tư</span>
            <?php else: ?>
                <span class="badge badge-info badge-lg">Công khai</span>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Document Info -->
            <div class="lg:col-span-1 space-y-6">
                <div class="card bg-base-100 shadow">
                    <div class="card-header bg-base-200">
                        <h3 class="card-title">
                            <i class="fa-solid fa-circle-info mr-2"></i>
                            Thông tin tài liệu
                        </h3>
                    </div>
                    <div class="card-body">
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-base-content/70 text-sm font-semibold mb-1">Tên file</dt>
                                <dd class="text-base-content"><?= htmlspecialchars($doc['file_name']) ?></dd>
                            </div>
                            <div>
                                <dt class="text-base-content/70 text-sm font-semibold mb-1">Loại file</dt>
                                <dd><span class="badge badge-primary">.<?= strtoupper($file_ext) ?></span></dd>
                            </div>
                            <?php if($doc['total_pages'] > 0): ?>
                            <div>
                                <dt class="text-base-content/70 text-sm font-semibold mb-1">Số trang</dt>
                                <dd class="text-base-content"><?= number_format($doc['total_pages']) ?></dd>
                            </div>
                            <?php endif; ?>
                            <div>
                                <dt class="text-base-content/70 text-sm font-semibold mb-1">Kích thước</dt>
                                <dd class="text-base-content"><?= formatFileSize(filesize($file_path)) ?></dd>
                            </div>
                            <div>
                                <dt class="text-base-content/70 text-sm font-semibold mb-1">Lượt xem</dt>
                                <dd class="text-base-content"><?= number_format($doc['views'] ?? 0) ?></dd>
                            </div>
                            <div>
                                <dt class="text-base-content/70 text-sm font-semibold mb-1">Lượt tải</dt>
                                <dd class="text-base-content"><?= number_format($doc['downloads'] ?? 0) ?></dd>
                            </div>
                        </dl>
                        
                        <?php if($doc['description']): ?>
                        <div class="mt-4 pt-4 border-t border-base-300">
                            <h4 class="text-sm font-semibold mb-2">Mô tả</h4>
                            <p class="text-base-content/70 text-sm"><?= nl2br(htmlspecialchars($doc['description'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Categories Card -->
                <?php if($doc_category): ?>
                <div class="card bg-base-100 shadow">
                    <div class="card-header bg-base-200">
                        <h3 class="card-title">
                            <i class="fa-solid fa-tags mr-2"></i>
                            Phân Loại
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="space-y-3">
                            <!-- Cấp học -->
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fa-solid fa-school text-sm text-base-content/70"></i>
                                    <span class="text-base-content/70 text-sm font-semibold">Cấp học</span>
                                </div>
                                <span class="badge badge-primary"><?= htmlspecialchars($doc_category['education_level_name']) ?></span>
                            </div>
                            
                            <?php if(isset($doc_category['grade_name'])): ?>
                            <!-- Lớp -->
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fa-solid fa-users text-sm text-base-content/70"></i>
                                    <span class="text-base-content/70 text-sm font-semibold">Lớp</span>
                                </div>
                                <span class="badge badge-primary"><?= htmlspecialchars($doc_category['grade_name']) ?></span>
                            </div>
                            
                            <!-- Môn học -->
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fa-solid fa-book text-sm text-base-content/70"></i>
                                    <span class="text-base-content/70 text-sm font-semibold">Môn học</span>
                                </div>
                                <span class="badge badge-info"><?= htmlspecialchars($doc_category['subject_name']) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if(isset($doc_category['major_group_name'])): ?>
                            <!-- Nhóm ngành -->
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fa-solid fa-building text-sm text-base-content/70"></i>
                                    <span class="text-base-content/70 text-sm font-semibold">Nhóm ngành</span>
                                </div>
                                <span class="badge badge-primary"><?= htmlspecialchars($doc_category['major_group_name']) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if(isset($doc_category['major_name'])): ?>
                            <!-- Ngành học -->
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fa-solid fa-graduation-cap text-sm text-base-content/70"></i>
                                    <span class="text-base-content/70 text-sm font-semibold">Ngành học</span>
                                </div>
                                <span class="badge badge-info"><?= htmlspecialchars($doc_category['major_name']) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Loại tài liệu -->
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fa-solid fa-file-lines text-sm text-base-content/70"></i>
                                    <span class="text-base-content/70 text-sm font-semibold">Loại tài liệu</span>
                                </div>
                                <span class="badge badge-success"><?= htmlspecialchars($doc_category['doc_type_name']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card bg-base-100 shadow">
                    <div class="card-header bg-base-200">
                        <h3 class="card-title">
                            <i class="fa-solid fa-tags mr-2"></i>
                            Phân Loại
                        </h3>
                    </div>
                    <div class="card-body">
                        <p class="text-base-content/70 text-sm">Chưa có phân loại cho tài liệu này.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Document Viewer -->
            <div class="lg:col-span-2">
                <div class="card bg-base-100 shadow">
                    <div class="card-header bg-base-200">
                        <h3 class="card-title">Xem trước</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="max-h-[800px] overflow-y-auto bg-base-200">
                            <?php
                            $pdf_path_for_preview = null;
                            
                            switch($file_ext) {
                                case 'pdf':
                                    echo '<div class="pdf-viewer p-6" id="pdfViewer"></div>';
                                    $pdf_path_for_preview = '/uploads/' . $doc['file_name'];
                                    break;
                                case 'docx':
                                case 'doc':
                                    // Check if converted PDF exists
                                    $converted_path = !empty($doc['converted_pdf_path']) ? $doc['converted_pdf_path'] : null;
                                    // Ensure converted_pdf_path is absolute (starts with /)
                                    if ($converted_path && substr($converted_path, 0, 1) !== '/') {
                                        $converted_path = '/' . $converted_path;
                                    }
                                    // Check if file exists (using relative path for file_exists check)
                                    $converted_file_path = $converted_path ? ltrim($converted_path, '/') : null;
                                    if ($converted_file_path && file_exists(__DIR__ . '/../' . $converted_file_path)) {
                                        echo '<div class="pdf-viewer p-6" id="pdfViewer"></div>';
                                        $pdf_path_for_preview = $converted_path;
                                    } else {
                                        $file_url = '/uploads/' . $doc['file_name'];
                                        echo '<div class="docx-viewer p-6 bg-white" id="docxViewer"></div>';
                                        $pdf_path_for_preview = null;
                                    }
                                    break;
                                case 'jpg':
                                case 'jpeg':
                                case 'png':
                                case 'gif':
                                case 'webp':
                                    $file_url = '/uploads/' . $doc['file_name'];
                                    echo '<div class="text-center p-6"><img src="' . $file_url . '" alt="' . htmlspecialchars($doc['original_name']) . '" class="max-w-full h-auto mx-auto"></div>';
                                    break;
                                case 'txt':
                                case 'log':
                                case 'md':
                                    $content = file_get_contents($file_path);
                                    echo '<div class="p-6"><pre class="whitespace-pre-wrap break-words font-mono text-sm bg-white p-4 rounded">' . htmlspecialchars($content) . '</pre></div>';
                                    break;
                                default:
                                    echo '<div class="p-12 text-center text-base-content/70">
                                            <i class="fa-solid fa-file text-6xl mb-4"></i>
                                            <p>File type: .' . htmlspecialchars($file_ext) . ' cannot be previewed</p>
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

<!-- PDF.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js"></script>

<!-- DOCX Preview Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/docx-preview@0.1.4/dist/docx-preview.min.js"></script>

<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
    
    // Load PDF if applicable
    <?php if($file_ext === 'pdf' || (($file_ext === 'docx' || $file_ext === 'doc') && isset($pdf_path_for_preview) && $pdf_path_for_preview)): ?>
    (async () => {
        try {
            const pdfPath = "<?= htmlspecialchars($pdf_path_for_preview, ENT_QUOTES, 'UTF-8') ?>";
            const loadingTask = pdfjsLib.getDocument(pdfPath);
            const pdfDoc = await loadingTask.promise;
            const viewer = document.getElementById("pdfViewer");
            viewer.innerHTML = '';
            
            // Render all pages
            for (let pageNum = 1; pageNum <= pdfDoc.numPages; pageNum++) {
                const page = await pdfDoc.getPage(pageNum);
                const scale = 2;
                const viewport = page.getViewport({ scale });
                
                const canvas = document.createElement("canvas");
                const context = canvas.getContext("2d");
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                canvas.className = "mx-auto mb-4 shadow-lg";
                
                await page.render({
                    canvasContext: context,
                    viewport: viewport
                }).promise;
                
                viewer.appendChild(canvas);
            }
        } catch(error) {
            document.getElementById("pdfViewer").innerHTML = '<div class="p-12 text-center text-base-content/70">Error loading PDF: ' + error.message + '</div>';
            console.error('PDF loading error:', error);
        }
    })();
    <?php endif; ?>
    
    // Load DOCX if applicable
    <?php if(($file_ext === 'docx' || $file_ext === 'doc') && !isset($pdf_path_for_preview)): ?>
    (async () => {
        try {
            // Wait for libraries to load
            let retries = 0;
            while ((typeof JSZip === 'undefined' || (typeof docx === 'undefined' && typeof docxPreview === 'undefined')) && retries < 15) {
                await new Promise(resolve => setTimeout(resolve, 200));
                retries++;
            }
            
            if (typeof JSZip === 'undefined') {
                throw new Error('JSZip library not loaded');
            }
            
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
                throw new Error('DOCX preview library not loaded correctly.');
            }
            
            const docxViewer = document.getElementById("docxViewer");
            if (!docxViewer) {
                throw new Error('DOCX viewer element not found');
            }
            
            const fileUrl = "/uploads/<?= $doc['file_name'] ?>";
            const response = await fetch(fileUrl);
            
            if (!response.ok) {
                throw new Error('Failed to fetch file: ' + response.statusText);
            }
            
            const arrayBuffer = await response.arrayBuffer();
            
            if (!arrayBuffer || arrayBuffer.byteLength === 0) {
                throw new Error('File is empty or invalid');
            }
            
            if (typeof docxAPI.renderAsync !== 'function') {
                throw new Error('docx.renderAsync is not available. Library version may be incompatible.');
            }
            
            await docxAPI.renderAsync(arrayBuffer, docxViewer, null, {
                className: "docx-wrapper",
                inWrapper: true,
                ignoreWidth: false,
                ignoreHeight: false,
                ignoreFonts: false,
                breakPages: true,
                ignoreLastRenderedPageBreak: false,
                experimental: false,
                trimXmlDeclaration: true,
                useBase64URL: false,
                showChanges: false,
                showInsertions: false,
                showDeletions: false
            });
            
        } catch(error) {
            document.getElementById("docxViewer").innerHTML = '<div class="p-12 text-center text-base-content/70">Error loading DOCX: ' + error.message + '</div>';
            console.error('DOCX loading error:', error);
        }
    })();
    <?php endif; ?>
</script>

<?php 
include __DIR__ . '/../includes/admin-footer.php';
mysqli_close($conn); 
?>
