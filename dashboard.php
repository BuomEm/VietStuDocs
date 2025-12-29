<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: index.php");
}

require_once 'config/db.php';
require_once 'config/auth.php';
require_once 'config/premium.php';
require_once 'config/points.php';
require_once 'config/categories.php';

$user_id = getCurrentUserId();
$is_premium = isPremium($user_id);
$premium_info = getPremiumInfo($user_id);
$page_title = "Dashboard - DocShare";
$current_page = 'dashboard';

// Fetch user's documents
$my_docs = mysqli_query($conn, "SELECT * FROM documents WHERE user_id=$user_id ORDER BY created_at DESC");

// Fetch all public documents from others (only approved)
$public_docs = mysqli_query($conn, "
    SELECT d.*, u.username FROM documents d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.is_public = TRUE AND d.user_id != $user_id AND d.status = 'approved'
    ORDER BY d.created_at DESC
");

// Handle document deletion
if(isset($_GET['delete'])) {
    $doc_id = intval($_GET['delete']);
    $doc = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM documents WHERE id=$doc_id AND user_id=$user_id"));
    
    if($doc) {
        $file_path = "uploads/" . $doc['file_name'];
        if(file_exists($file_path)) {
            unlink($file_path);
        }
        mysqli_query($conn, "DELETE FROM documents WHERE id=$doc_id");
        header("Location: dashboard.php?msg=deleted");
    }
}

// Handle document download
if(isset($_GET['download'])) {
    $doc_id = intval($_GET['download']);
    $doc = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM documents WHERE id=$doc_id AND user_id=$user_id"));
    
    if($doc) {
        $file_path = "uploads/" . $doc['file_name'];
        if(file_exists($file_path)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($doc['original_name']) . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        }
    }
}
?>
<?php include 'includes/head.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- PDF.js Library for document previews -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<!-- DOCX Preview Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/docx-preview@0.1.4/dist/docx-preview.min.js"></script>
<style>
    .document-card {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: all 0.3s;
        cursor: pointer;
        position: relative;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .document-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateY(-2px);
    }
    .document-preview {
        width: 100%;
        height: 240px;
        background: #f5f5f5;
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px 8px 0 0;
    }
    .document-preview img,
    .document-preview canvas {
        width: 100%;
        height: 100%;
        object-fit: contain;
        background: white;
    }
    .document-preview .docx-preview-container {
        width: 100%;
        height: 100%;
        overflow: hidden;
        background: white;
        position: relative;
        display: flex;
        align-items: flex-start;
        justify-content: flex-start;
    }
    .document-preview .docx-preview-container .docx-wrapper {
        width: 100%;
        height: 100%;
        overflow: hidden;
        background: white;
        position: relative;
    }
    .document-preview .docx-preview-container .docx-wrapper > div {
        transform-origin: top left;
        padding: 10px;
        box-sizing: border-box;
    }
    .page-number {
        position: absolute;
        bottom: 8px;
        right: 8px;
        z-index: 5;
    }
    .document-title {
        font-size: 15px;
        font-weight: 600;
        color: #2563eb;
        margin: 12px 12px 6px 12px;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .document-type {
        font-size: 12px;
        color: #6b7280;
        margin: 0 12px 0 12px;
        flex-shrink: 0;
    }
    .like-section {
        margin: 0 12px 12px 12px;
        margin-top: auto;
        padding: 8px 12px;
        background: #f3f4f6;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        cursor: pointer;
        transition: background 0.2s;
        flex-shrink: 0;
    }
    .like-section:hover {
        background: #e5e7eb;
    }
    .like-icon {
        font-size: 18px;
        flex-shrink: 0;
    }
    .like-stats {
        display: flex;
        align-items: center;
        gap: 4px;
        justify-content: center;
    }
    .like-percentage {
        font-size: 14px;
        font-weight: 600;
        color: #1f2937;
    }
    .like-count {
        font-size: 12px;
        color: #6b7280;
    }
</style>

<div class="drawer-content flex flex-col">
    <?php include 'includes/navbar.php'; ?>
    
    <main class="flex-1 p-6">
        <?php if(isset($_GET['msg'])): ?>
            <?php if($_GET['msg'] == 'deleted'): ?>
                <div class="alert alert-success mb-4">
                    <i class="fa-regular fa-circle-check fa-lg"></i>
                    <span>Document deleted successfully</span>
                </div>
            <?php elseif($_GET['msg'] == 'updated'): ?>
                <div class="alert alert-success mb-4">
                    <i class="fa-regular fa-circle-check fa-lg"></i>
                    <span>Document updated successfully</span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- My Documents Section -->
        <div class="mb-12">
            <h2 class="text-2xl font-bold mb-6 pb-3 border-b-2 border-primary flex items-center gap-2">
                <i class="fa-regular fa-book fa-lg"></i>
                My Documents
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4 gap-4">
            <?php
            if(mysqli_num_rows($my_docs) > 0) {
                while($doc = mysqli_fetch_assoc($my_docs)) {
                    $doc_id = $doc['id'];
                    $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                    $file_path = "uploads/" . $doc['file_name'];
                    
                    // Get likes and dislikes
                    $likes = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM document_interactions WHERE document_id=$doc_id AND type='like'"));
                    $dislikes = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM document_interactions WHERE document_id=$doc_id AND type='dislike'"));
                    $total_interactions = $likes + $dislikes;
                    $like_percentage = $total_interactions > 0 ? round(($likes / $total_interactions) * 100) : 0;
                    
                    // Get category info
                    $doc_category = getDocumentCategoryWithNames($doc_id);
                    $doc_type = $doc_category ? htmlspecialchars($doc_category['doc_type_name']) : 'Other';
                    
                    // Determine file types
                    $is_pdf = ($ext === 'pdf');
                    $is_docx = in_array($ext, ['doc', 'docx']);
                    $is_office = in_array($ext, ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx']);
                    $preview_id = 'preview_' . $doc_id;
                    $page_count_id = 'pagecount_' . $doc_id;
                    
                    // Get document name without extension
                    $doc_name_without_ext = preg_replace('/\.[^.]+$/', '', $doc['original_name']);
                    
                    // Status badges
                    $status_badge = '';
                    if($doc['status'] == 'pending') {
                        $status_badge = '<div class="badge badge-warning absolute top-2 right-2 z-10 gap-1"><i class="fa-regular fa-clock"></i> Đang Duyệt</div>';
                    } elseif($doc['status'] == 'rejected') {
                        $status_badge = '<div class="badge badge-error absolute top-2 right-2 z-10 gap-1"><i class="fa-regular fa-circle-xmark"></i> Đã Từ Chối</div>';
                    } elseif($doc['status'] == 'approved') {
                        $status_badge = '<div class="badge badge-success absolute top-2 right-2 z-10 gap-1"><i class="fa-regular fa-circle-check"></i> Đã Duyệt</div>';
                    }
                    $privacy_badge = '';
                    if($doc['is_public'] == 1) {
                        $privacy_badge = '<div class="badge badge-info absolute top-2 left-2 z-10 gap-1"><i class="fa-regular fa-globe"></i></div>';
                    } else {
                        $privacy_badge = '<div class="badge badge-neutral absolute top-2 left-2 z-10 gap-1"><i class="fa-regular fa-lock"></i></div>';
                    }
                    
                    // Get thumbnail and total_pages from database
                    $thumbnail = $doc['thumbnail'] ?? null;
                    $page_count = isset($doc['total_pages']) && $doc['total_pages'] > 0 ? $doc['total_pages'] : 1;
                    $converted_pdf_path = $doc['converted_pdf_path'] ?? null;
                    
                    // Determine preview content
                    $preview_content = '';
                    $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                    $use_thumbnail = false;
                    
                    // If thumbnail exists in database, use it
                    $thumbnail_file_path = 'uploads/' . $thumbnail;
                    $thumbnail_file_exists = $thumbnail && file_exists($thumbnail_file_path);
                    
                    if ($thumbnail_file_exists) {
                        $preview_content = '<img src="uploads/' . htmlspecialchars($thumbnail) . '" alt="Thumbnail" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display=\'none\'; this.parentElement.innerHTML=\'<div class=\\\'flex items-center justify-center h-full\\\'><i class=\\\'fa-regular fa-file fa-4x text-base-content/30\\\'></i></div>\';" />';
                        $use_thumbnail = true;
                    } elseif($is_pdf) {
                        $preview_content = '<div class="flex items-center justify-center h-full"><i class="fa-regular fa-file-pdf fa-4x text-base-content/30"></i></div>';
                    } elseif($is_docx) {
                        $preview_content = '<div class="docx-preview-container" style="width: 100%; height: 100%;"></div>';
                    } elseif($is_image) {
                        $preview_content = '<img src="' . $file_path . '" onerror="this.style.display=\'none\'; this.parentElement.innerHTML=\'<div class=\\\'flex items-center justify-center h-full\\\'><i class=\\\'fa-regular fa-file fa-4x text-base-content/30\\\'></i></div>\';" />';
                    } else {
                        $icon_classes = [
                            'ppt' => 'fa-regular fa-file-powerpoint', 'pptx' => 'fa-regular fa-file-powerpoint',
                            'xls' => 'fa-regular fa-file-excel', 'xlsx' => 'fa-regular fa-file-excel',
                            'txt' => 'fa-regular fa-file-lines', 'zip' => 'fa-regular fa-file-zipper'
                        ];
                        $icon_class = $icon_classes[$ext] ?? 'fa-regular fa-file';
                        $preview_content = '<div class="flex items-center justify-center h-full"><i class="' . $icon_class . ' fa-4x text-base-content/30"></i></div>';
                    }
                    
                    echo '
                    <div class="card bg-base-100 shadow-md hover:shadow-xl transition-shadow cursor-pointer" onclick="window.location.href=\'view.php?id=' . $doc_id . '\'">
                        <figure class="relative h-60 bg-base-200">
                            <div class="document-preview w-full h-full" id="' . $preview_id . '">
                                ' . $preview_content . '
                            </div>
                            <div class="badge badge-primary absolute bottom-2 right-2" id="' . $page_count_id . '">' . $page_count . '</div>
                            ' . $status_badge . '
                            ' . $privacy_badge . '
                        </figure>
                        <div class="card-body p-4">
                            <h3 class="card-title text-sm line-clamp-2">' . htmlspecialchars($doc_name_without_ext) . '</h3>
                            <p class="text-xs text-base-content/70">' . htmlspecialchars($doc_type) . '</p>
                            <div class="card-actions justify-center mt-2">
                                <div class="flex items-center gap-2 w-full">
                                    <div class="flex items-center justify-center w-7 h-7 rounded-full bg-success text-white shadow-md flex-shrink-0">
                                        <i class="fa-regular fa-thumbs-up text-xs"></i>
                                    </div>
                                    <div class="flex-1 h-6 bg-success/80 rounded-full flex items-center justify-center gap-1">
                                        <span class="text-white font-semibold text-sm">' . $like_percentage . '%</span>
                                        <span class="text-white/80 text-xs">(' . $likes . ')</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    ';
                    
                    // Add JavaScript to render document previews
                    if($is_pdf && !$use_thumbnail) {
                        echo '<script>
                        (async function() {
                            try {
                                pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
                                const pdfPath = "' . $file_path . '";
                                const previewId = "' . $preview_id . '";
                                const pageCountId = "' . $page_count_id . '";
                                
                                const pdfDoc = await pdfjsLib.getDocument(pdfPath).promise;
                                const page = await pdfDoc.getPage(1);
                                const scale = 1.2;
                                const viewport = page.getViewport({ scale });
                                
                                const canvas = document.createElement("canvas");
                                const context = canvas.getContext("2d");
                                canvas.width = viewport.width;
                                canvas.height = viewport.height;
                                
                                await page.render({
                                    canvasContext: context,
                                    viewport: viewport
                                }).promise;
                                
                                const previewDiv = document.getElementById(previewId);
                                previewDiv.innerHTML = "";
                                previewDiv.appendChild(canvas);
                                
                                // Update page number
                                const pageCountDiv = document.getElementById(pageCountId);
                                if(pageCountDiv) {
                                    pageCountDiv.textContent = pdfDoc.numPages;
                                    pageCountDiv.style.display = "block";
                                }
                            } catch(error) {
                                console.error("Error loading PDF:", error);
                                const previewDiv = document.getElementById("' . $preview_id . '");
                                if(previewDiv) {
                                    previewDiv.innerHTML = \'<div class="flex items-center justify-center h-full"><i class="fa-regular fa-file fa-4x text-base-content/30"></i></div>\';
                                }
                            }
                        })();
                        </script>';
                    } elseif($is_docx && !$use_thumbnail) {
                        echo '<script>
                        (async function() {
                            try {
                                let retries = 0;
                                while ((typeof JSZip === "undefined" || (typeof docx === "undefined" && typeof docxPreview === "undefined")) && retries < 15) {
                                    await new Promise(resolve => setTimeout(resolve, 200));
                                    retries++;
                                }
                                
                                let docxAPI = null;
                                if (typeof docx !== "undefined" && docx.renderAsync) {
                                    docxAPI = docx;
                                } else if (typeof docxPreview !== "undefined" && docxPreview.renderAsync) {
                                    docxAPI = docxPreview;
                                } else if (window.docxPreview && window.docxPreview.renderAsync) {
                                    docxAPI = window.docxPreview;
                                } else if (window.docx && window.docx.renderAsync) {
                                    docxAPI = window.docx;
                                }
                                
                                if (!docxAPI) {
                                    throw new Error("DOCX library not loaded");
                                }
                                
                                const fileUrl = "' . $file_path . '";
                                const response = await fetch(fileUrl);
                                if (!response.ok) throw new Error("Failed to fetch file");
                                
                                const arrayBuffer = await response.arrayBuffer();
                                const previewId = "' . $preview_id . '";
                                const pageCountId = "' . $page_count_id . '";
                                const container = document.getElementById(previewId).querySelector(".docx-preview-container");
                                
                                await docxAPI.renderAsync(arrayBuffer, container, null, {
                                    className: "docx-wrapper",
                                    inWrapper: true,
                                    ignoreWidth: false,
                                    ignoreHeight: false,
                                    breakPages: true
                                });
                                
                                // Wait for rendering to complete
                                await new Promise(resolve => setTimeout(resolve, 300));
                                
                                // Calculate scale to fit the first page in the container
                                const wrapper = container.querySelector(".docx-wrapper");
                                if (wrapper) {
                                    const firstPage = wrapper.querySelector("div");
                                    if (firstPage) {
                                        const containerWidth = container.offsetWidth;
                                        const containerHeight = container.offsetHeight;
                                        const pageWidth = firstPage.offsetWidth;
                                        const pageHeight = firstPage.offsetHeight;
                                        
                                        // Calculate scale to fit width and height
                                        const scaleX = (containerWidth - 20) / pageWidth;
                                        const scaleY = (containerHeight - 20) / pageHeight;
                                        const scale = Math.min(scaleX, scaleY, 1); 
                                        
                                        // Apply transform to show full first page
                                        firstPage.style.transform = "scale(" + scale + ")";
                                        firstPage.style.transformOrigin = "top left";
                                        
                                        // Hide other pages
                                        const allPages = wrapper.querySelectorAll("div");
                                        for (let i = 1; i < allPages.length; i++) {
                                            allPages[i].style.display = "none";
                                        }
                                        
                                        // Estimate page count from DOCX rendering
                                        const estimatedPageCount = allPages.length > 0 ? allPages.length : 1;
                                        const pageCountDiv = document.getElementById(pageCountId);
                                        if(pageCountDiv) {
                                            pageCountDiv.textContent = estimatedPageCount;
                                            pageCountDiv.style.display = "block";
                                        }
                                    }
                                }
                            } catch(error) {
                                console.error("Error loading DOCX:", error);
                                const previewDiv = document.getElementById("' . $preview_id . '");
                                if(previewDiv) {
                                    previewDiv.innerHTML = \'<div class="flex items-center justify-center h-full"><i class="fa-regular fa-file fa-4x text-base-content/30"></i></div><div class="page-number" id="' . $page_count_id . '">1</div>\';
                                }
                            }
                        })();
                        </script>';
                    } elseif($is_office && !$is_docx) {
                        // For PPT, PPTX, XLS, XLSX - show page count as 1 for now
                        echo '<script>
                        document.getElementById("' . $page_count_id . '").style.display = "block";
                        </script>';
                    }
                }
            } else {
                echo '<div class="col-span-full text-center p-10 bg-base-100 rounded-box shadow">No documents uploaded yet</div>';
            }
            ?>
            </div>
        </div>

        <!-- Public Documents Section -->
        <div>
            <h2 class="text-2xl font-bold mb-6 pb-3 border-b-2 border-primary flex items-center gap-2">
                <i class="fa-regular fa-globe fa-lg"></i>
                Public Documents
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4 gap-4">
            <?php
            if(mysqli_num_rows($public_docs) > 0) {
                while($doc = mysqli_fetch_assoc($public_docs)) {
                    $doc_id = $doc['id'];
                    $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                    $file_path = "uploads/" . $doc['file_name'];
                    
                    // Get likes and dislikes
                    $likes = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM document_interactions WHERE document_id=$doc_id AND type='like'"));
                    $dislikes = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM document_interactions WHERE document_id=$doc_id AND type='dislike'"));
                    $total_interactions = $likes + $dislikes;
                    $like_percentage = $total_interactions > 0 ? round(($likes / $total_interactions) * 100) : 0;
                    
                    // Get category info
                    $doc_category = getDocumentCategoryWithNames($doc_id);
                    $doc_type = $doc_category ? htmlspecialchars($doc_category['doc_type_name']) : 'Other';
                    
                    // Determine file types
                    $is_pdf = ($ext === 'pdf');
                    $is_docx = in_array($ext, ['doc', 'docx']);
                    $is_office = in_array($ext, ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx']);
                    $preview_id = 'preview_public_' . $doc_id;
                    $page_count_id = 'pagecount_public_' . $doc_id;
                    
                    // Get document name without extension
                    $doc_name_without_ext = preg_replace('/\.[^.]+$/', '', $doc['original_name']);
                    
                    $has_purchased = canUserDownloadDocument($user_id, $doc_id);
                    $purchased_badge = $has_purchased ? '<div class="badge badge-success absolute top-2 right-2 z-10 gap-1"><i class="fa-regular fa-cart-shopping"></i> Đã Mua</div>' : '';
                    
                    // Get thumbnail and total_pages from database
                    $thumbnail = $doc['thumbnail'] ?? null;
                    $page_count = isset($doc['total_pages']) && $doc['total_pages'] > 0 ? $doc['total_pages'] : 1;
                    $converted_pdf_path = $doc['converted_pdf_path'] ?? null;
                    
                    // Determine preview content
                    $preview_content = '';
                    $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                    $use_thumbnail = false;
                    
                    // If thumbnail exists in database, use it
                    $thumbnail_file_path = 'uploads/' . $thumbnail;
                    $thumbnail_file_exists = $thumbnail && file_exists($thumbnail_file_path);
                    
                    if ($thumbnail_file_exists) {
                        $preview_content = '<img src="uploads/' . htmlspecialchars($thumbnail) . '" alt="Thumbnail" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display=\'none\'; this.parentElement.innerHTML=\'<div class=\\\'flex items-center justify-center h-full\\\'><i class=\\\'fa-regular fa-file fa-4x text-base-content/30\\\'></i></div>\';" />';
                        $use_thumbnail = true;
                    } elseif($is_pdf) {
                        $preview_content = '<div class="flex items-center justify-center h-full"><i class="fa-regular fa-file-pdf fa-4x text-base-content/30"></i></div>';
                    } elseif($is_docx) {
                        $preview_content = '<div class="docx-preview-container" style="width: 100%; height: 100%;"></div>';
                    } elseif($is_image) {
                        $preview_content = '<img src="' . $file_path . '" onerror="this.style.display=\'none\'; this.parentElement.innerHTML=\'<div class=\\\'flex items-center justify-center h-full\\\'><i class=\\\'fa-regular fa-image fa-4x text-base-content/30\\\'></i></div>\';" />';
                    } else {
                        $icon_classes = [
                            'ppt' => 'fa-regular fa-file-powerpoint', 'pptx' => 'fa-regular fa-file-powerpoint',
                            'xls' => 'fa-regular fa-file-excel', 'xlsx' => 'fa-regular fa-file-excel',
                            'txt' => 'fa-regular fa-file-lines', 'zip' => 'fa-regular fa-file-zipper'
                        ];
                        $icon_class = $icon_classes[$ext] ?? 'fa-regular fa-file';
                        $preview_content = '<div class="flex items-center justify-center h-full"><i class="' . $icon_class . ' fa-4x text-base-content/30"></i></div>';
                    }
                    
                    echo '
                    <div class="card bg-base-100 shadow-md hover:shadow-xl transition-shadow cursor-pointer" onclick="window.location.href=\'view.php?id=' . $doc_id . '\'">
                        <figure class="relative h-60 bg-base-200">
                            <div class="document-preview w-full h-full" id="' . $preview_id . '">
                                ' . $preview_content . '
                            </div>
                            <div class="badge badge-primary absolute bottom-2 right-2" id="' . $page_count_id . '">' . $page_count . '</div>
                            ' . $purchased_badge . '
                        </figure>
                        <div class="card-body p-4">
                            <h3 class="card-title text-sm line-clamp-2">' . htmlspecialchars($doc_name_without_ext) . '</h3>
                            <p class="text-xs text-base-content/70">' . htmlspecialchars($doc_type) . '</p>
                            <div class="card-actions justify-center mt-2">
                                <div class="flex items-center gap-2 w-full">
                                    <div class="flex items-center justify-center w-7 h-7 rounded-full bg-success text-white shadow-md flex-shrink-0">
                                        <i class="fa-regular fa-thumbs-up text-xs"></i>
                                    </div>
                                    <div class="flex-1 h-6 bg-success/80 rounded-full flex items-center justify-center gap-1">
                                        <span class="text-white font-semibold text-sm">' . $like_percentage . '%</span>
                                        <span class="text-white/80 text-xs">(' . $likes . ')</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    ';
                    
                    // Add JavaScript to render document previews
                    if($is_pdf && !$use_thumbnail) {
                        echo '<script>
                        (async function() {
                            try {
                                pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
                                const pdfPath = "' . $file_path . '";
                                const previewId = "' . $preview_id . '";
                                const pageCountId = "' . $page_count_id . '";
                                
                                const pdfDoc = await pdfjsLib.getDocument(pdfPath).promise;
                                const page = await pdfDoc.getPage(1);
                                const scale = 1.2;
                                const viewport = page.getViewport({ scale });
                                
                                const canvas = document.createElement("canvas");
                                const context = canvas.getContext("2d");
                                canvas.width = viewport.width;
                                canvas.height = viewport.height;
                                
                                await page.render({
                                    canvasContext: context,
                                    viewport: viewport
                                }).promise;
                                
                                const previewDiv = document.getElementById(previewId);
                                previewDiv.innerHTML = "";
                                previewDiv.appendChild(canvas);
                                
                                // Update page number
                                const pageCountDiv = document.getElementById(pageCountId);
                                if(pageCountDiv) {
                                    pageCountDiv.textContent = pdfDoc.numPages;
                                    pageCountDiv.style.display = "block";
                                }
                            } catch(error) {
                                console.error("Error loading PDF:", error);
                                const previewDiv = document.getElementById("' . $preview_id . '");
                                if(previewDiv) {
                                    previewDiv.innerHTML = \'<div class="flex items-center justify-center h-full"><i class="fa-regular fa-file fa-4x text-base-content/30"></i></div>\';
                                }
                            }
                        })();
                        </script>';
                    } elseif($is_docx && !$use_thumbnail) {
                        echo '<script>
                        (async function() {
                            try {
                                let retries = 0;
                                while ((typeof JSZip === "undefined" || (typeof docx === "undefined" && typeof docxPreview === "undefined")) && retries < 15) {
                                    await new Promise(resolve => setTimeout(resolve, 200));
                                    retries++;
                                }
                                
                                let docxAPI = null;
                                if (typeof docx !== "undefined" && docx.renderAsync) {
                                    docxAPI = docx;
                                } else if (typeof docxPreview !== "undefined" && docxPreview.renderAsync) {
                                    docxAPI = docxPreview;
                                } else if (window.docxPreview && window.docxPreview.renderAsync) {
                                    docxAPI = window.docxPreview;
                                } else if (window.docx && window.docx.renderAsync) {
                                    docxAPI = window.docx;
                                }
                                
                                if (!docxAPI) {
                                    throw new Error("DOCX library not loaded");
                                }
                                
                                const fileUrl = "' . $file_path . '";
                                const response = await fetch(fileUrl);
                                if (!response.ok) throw new Error("Failed to fetch file");
                                
                                const arrayBuffer = await response.arrayBuffer();
                                const previewId = "' . $preview_id . '";
                                const pageCountId = "' . $page_count_id . '";
                                const container = document.getElementById(previewId).querySelector(".docx-preview-container");
                                
                                await docxAPI.renderAsync(arrayBuffer, container, null, {
                                    className: "docx-wrapper",
                                    inWrapper: true,
                                    ignoreWidth: false,
                                    ignoreHeight: false,
                                    breakPages: true
                                });
                                
                                // Wait for rendering to complete
                                await new Promise(resolve => setTimeout(resolve, 300));
                                
                                // Calculate scale to fit the first page in the container
                                const wrapper = container.querySelector(".docx-wrapper");
                                if (wrapper) {
                                    const firstPage = wrapper.querySelector("div");
                                    if (firstPage) {
                                        const containerWidth = container.offsetWidth;
                                        const containerHeight = container.offsetHeight;
                                        const pageWidth = firstPage.offsetWidth;
                                        const pageHeight = firstPage.offsetHeight;
                                        
                                        // Calculate scale to fit width and height
                                        const scaleX = (containerWidth - 20) / pageWidth;
                                        const scaleY = (containerHeight - 20) / pageHeight;
                                        const scale = Math.min(scaleX, scaleY, 1); 
                                        
                                        // Apply transform to show full first page
                                        firstPage.style.transform = "scale(" + scale + ")";
                                        firstPage.style.transformOrigin = "top left";
                                        
                                        // Hide other pages
                                        const allPages = wrapper.querySelectorAll("div");
                                        for (let i = 1; i < allPages.length; i++) {
                                            allPages[i].style.display = "none";
                                        }
                                        
                                        // Estimate page count from DOCX rendering
                                        const estimatedPageCount = allPages.length > 0 ? allPages.length : 1;
                                        const pageCountDiv = document.getElementById(pageCountId);
                                        if(pageCountDiv) {
                                            pageCountDiv.textContent = estimatedPageCount;
                                            pageCountDiv.style.display = "block";
                                        }
                                    }
                                }
                            } catch(error) {
                                console.error("Error loading DOCX:", error);
                                const previewDiv = document.getElementById("' . $preview_id . '");
                                if(previewDiv) {
                                    previewDiv.innerHTML = \'<div class="flex items-center justify-center h-full"><i class="fa-regular fa-file fa-4x text-base-content/30"></i></div><div class="page-number" id="' . $page_count_id . '">1</div>\';
                                }
                            }
                        })();
                        </script>';
                    } elseif($is_office && !$is_docx) {
                        // For PPT, PPTX, XLS, XLSX - show page count as 1 for now
                        echo '<script>
                        document.getElementById("' . $page_count_id . '").style.display = "block";
                        </script>';
                    }
                }
            } else {
                echo '<div class="col-span-full text-center p-10 bg-base-100 rounded-box shadow">No public documents available</div>';
            }
            ?>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</div>
</div>

<?php 
mysqli_close($conn);
?>