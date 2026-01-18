<?php
session_start();

require_once '../config/db.php';
require_once '../config/function.php';
require_once '../config/auth.php';
require_once '../config/premium.php';
require_once '../config/points.php';
require_once '../config/categories.php';

// Cho phép xem dashboard khi chưa đăng nhập
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? getCurrentUserId() : 0;
$is_premium = $is_logged_in ? isPremium($user_id) : false;
$premium_info = $is_logged_in ? getPremiumInfo($user_id) : null;
$page_title = "Dashboard - VietStuDocs";
$page_description = "Quản lý tài liệu cá nhân, theo dõi thống kê và khám phá kho tài liệu khổng lồ trên VietStuDocs.";
$page_keywords = "dashboard, quản lý tài liệu, thống kê, thư viện cá nhân";
$current_page = 'dashboard';

// Pagination settings
$items_per_page = 12;

// Fetch user's documents with pagination (chỉ khi đã đăng nhập)
$current_my_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$my_offset = ($current_my_page - 1) * $items_per_page;
$total_my_docs = 0;
$total_my_pages = 0;
$my_docs = [];

if($is_logged_in) {
    $total_my_docs = $VSD->get_row("SELECT COUNT(*) as total FROM documents WHERE user_id=$user_id")['total'];
    $total_my_pages = ceil($total_my_docs / $items_per_page);

    $my_docs = $VSD->get_list("SELECT d.*, u.username, u.avatar,
                                (SELECT COUNT(*) FROM document_interactions WHERE document_id = d.id AND type = 'like') as likes,
                                (SELECT COUNT(*) FROM document_interactions WHERE document_id = d.id AND type = 'dislike') as dislikes,
                                dc.doc_type_code
                              FROM documents d 
                              JOIN users u ON d.user_id = u.id 
                              LEFT JOIN document_categories dc ON d.id = dc.document_id
                              WHERE d.user_id=$user_id 
                              ORDER BY d.created_at DESC 
                              LIMIT $items_per_page OFFSET $my_offset");
}

// Fetch all public documents (only approved) with pagination
$public_docs_where = "d.is_public = TRUE AND d.status = 'approved'";
if($is_logged_in) {
    $public_docs_where .= " AND d.user_id != $user_id";
}

$total_public_docs = $VSD->get_row("SELECT COUNT(*) as total FROM documents d WHERE $public_docs_where")['total'];
$total_pub_pages = ceil($total_public_docs / $items_per_page);
$current_pub_page = isset($_GET['pub_page']) ? max(1, intval($_GET['pub_page'])) : 1;
$pub_offset = ($current_pub_page - 1) * $items_per_page;

$public_docs = $VSD->get_list("
    SELECT d.*, u.username, u.avatar,
           (SELECT COUNT(*) FROM document_interactions WHERE document_id = d.id AND type = 'like') as likes,
           (SELECT COUNT(*) FROM document_interactions WHERE document_id = d.id AND type = 'dislike') as dislikes
    FROM documents d 
    JOIN users u ON d.user_id = u.id 
    WHERE $public_docs_where
    ORDER BY d.created_at DESC
    LIMIT $items_per_page OFFSET $pub_offset
");

// Handle document deletion (yêu cầu đăng nhập)
if(isset($_GET['delete']) && $is_logged_in) {
    $doc_id = intval($_GET['delete']);
    $doc = $VSD->get_row("SELECT * FROM documents WHERE id=$doc_id AND user_id=$user_id");
    
    if($doc) {
        $file_path = "../uploads/" . $doc['file_name'];
        if(file_exists($file_path)) {
            unlink($file_path);
        }
        $VSD->query("DELETE FROM documents WHERE id=$doc_id");
        header("Location: dashboard?msg=deleted");
    }
}

// Handle document download - redirect to secure download handler
if(isset($_GET['download']) && $is_logged_in) {
    $doc_id = intval($_GET['download']);
    header("Location: handler/download?id=" . $doc_id);
    exit;
}
?>
<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

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

    /* Mobile refined grid & cards */
    @media (max-width: 640px) {
        .dashboard-grid {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 12px !important;
        }
        .document-card-vsd {
            border-radius: 1.5rem !important;
        }
        .document-card-vsd .p-6 {
            padding: 12px !important;
        }
        .document-card-vsd h3 {
            font-size: 12px !important;
            min-h-0 !important;
            height: 2.4rem !important;
            margin-bottom: 8px !important;
        }
        .document-card-vsd .quality-indicator {
            display: none !important; /* Hide on mobile to save space */
        }
        .document-card-vsd .divider {
            margin: 4px 0 !important;
        }
        .document-card-vsd .badge {
            font-size: 8px !important;
            padding: 2px 6px !important;
        }
    }
</style>

<div class="drawer-content flex flex-col">
    <?php include '../includes/navbar.php'; ?>
    
    <main class="flex-1 p-4 lg:p-8">
        <!-- Header Section -->
        <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h1 class="text-4xl font-extrabold flex items-center gap-3 text-base-content">
                    <div class="p-3 rounded-2xl bg-primary/10 text-primary shadow-inner">
                        <i class="fa-solid fa-gauge-high"></i>
                    </div>
                    <?= $is_logged_in ? 'Bảng Điều Khiển' : 'Khám Phá Tài Liệu' ?>
                </h1>
                <p class="text-base-content/60 mt-2 font-medium">
                    <?= $is_logged_in ? 'Chào mừng trở lại! Xem tổng quan tài liệu của bạn.' : 'Chào mừng bạn đến với VietStuDocs! Khám phá kho tài liệu phong phú.' ?>
                </p>
            </div>
            
            <?php if($is_logged_in): ?>
            <div class="flex gap-2">
                <div class="badge badge-lg py-5 px-6 bg-base-100 border-base-300 shadow-sm font-bold gap-2">
                    <i class="fa-solid fa-file-invoice text-primary/60"></i>
                    Tài liệu của tôi: <span class="text-primary ml-1"><?= count($my_docs) ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if(isset($_GET['msg']) && $is_logged_in): ?>
            <?php if($_GET['msg'] == 'deleted'): ?>
                <div class="alert bg-error/10 border-error/20 text-error mb-8 rounded-2xl animate-bounce-slow">
                    <i class="fa-solid fa-trash-can"></i>
                    <span class="font-bold">Đã xóa tài liệu thành công!</span>
                </div>
            <?php elseif($_GET['msg'] == 'updated'): ?>
                <div class="alert bg-success/10 border-success/20 text-success mb-8 rounded-2xl animate-bounce-slow">
                    <i class="fa-solid fa-circle-check"></i>
                    <span class="font-bold">Đã cập nhật tài liệu thành công!</span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if($is_logged_in): ?>
        <!-- Push Notification Alert CTA -->
        <div id="push-notification-cta" class="hidden mb-8 group">
            <div class="relative overflow-hidden rounded-[2rem] bg-gradient-to-r from-primary to-secondary p-1 shadow-xl shadow-primary/20 transition-all duration-500 hover:scale-[1.01]">
                <div class="relative bg-base-100 dark:bg-base-300 rounded-[1.8rem] p-6 lg:p-8 flex flex-col lg:flex-row items-center justify-between gap-6 overflow-hidden">
                    <!-- Decor background -->
                    <div class="absolute -right-10 -top-10 w-40 h-40 bg-primary/10 rounded-full blur-3xl group-hover:bg-primary/20 transition-colors"></div>
                    <div class="absolute -left-10 -bottom-10 w-40 h-40 bg-secondary/10 rounded-full blur-3xl group-hover:bg-secondary/20 transition-colors"></div>
                    
                    <div class="flex items-center gap-6 relative z-10">
                        <div class="flex-none w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center text-primary animate-bounce-slow">
                            <i class="fa-solid fa-bell text-3xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-black text-base-content mb-1">Đừng bỏ lỡ thông báo! 🔔</h3>
                            <p class="text-base-content/60 text-sm font-medium max-w-md">Nhận thông báo tức thì khi có người mua tài liệu, tin nhắn từ Gia sư hoặc cập nhật quan trọng từ VietStuDocs.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 relative z-10 w-full lg:w-auto">
                        <button onclick="subscribePush().then(success => success && document.getElementById('push-notification-cta').remove())" 
                                class="btn btn-primary rounded-2xl px-8 h-12 flex-1 lg:flex-none font-black shadow-lg shadow-primary/20 hover:scale-105 transition-all">
                            BẬT THÔNG BÁO NGAY
                        </button>
                        <button onclick="document.getElementById('push-notification-cta').remove(); sessionStorage.setItem('dismissed_push_cta', 'true')" 
                                class="btn btn-ghost btn-square rounded-xl opacity-40 hover:opacity-100 hover:bg-base-200">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(async () => {
                if (typeof checkNotificationStatus === 'function') {
                    const status = checkNotificationStatus();
                    const dismissed = sessionStorage.getItem('dismissed_push_cta');
                    
                    if (status !== 'granted' && !dismissed) {
                        // Double check if not granted, or if granted but no subscription
                        let isSubscribed = false;
                        if ('serviceWorker' in navigator && status === 'granted') {
                            try {
                                const reg = await navigator.serviceWorker.ready;
                                const sub = await reg.pushManager.getSubscription();
                                isSubscribed = !!sub;
                            } catch(e) {}
                        }
                        
                        if (!isSubscribed) {
                            document.getElementById('push-notification-cta').classList.remove('hidden');
                        }
                    }
                }
            }, 1000);
        });
        </script>
        <?php endif; ?>

        <?php if($is_logged_in): ?>
        <!-- My Documents Section -->
        <section class="mb-16">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-2xl font-black flex items-center gap-3">
                    <span class="w-1.5 h-8 bg-primary rounded-full"></span>
                    Tài liệu của tôi
                </h2>
                <a href="upload" class="btn btn-primary btn-sm rounded-xl gap-2 shadow-lg shadow-primary/20">
                    <i class="fa-solid fa-plus"></i>
                    Tải Lên Mới
                </a>
            </div>

            <?php if(count($my_docs) > 0): ?>
                <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8 dashboard-grid">
                <?php
                foreach($my_docs as $doc):
                    $doc_id = $doc['id'];
                    $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                    $thumbnail = $doc['thumbnail'] ?? null;
                    $page_count = isset($doc['total_pages']) && $doc['total_pages'] > 0 ? $doc['total_pages'] : 1;
                    
                    // Stats from pre-fetched columns
                    $likes = intval($doc['likes'] ?? 0);
                    $dislikes = intval($doc['dislikes'] ?? 0);
                    $total_interactions = $likes + $dislikes;
                    $like_percentage = $total_interactions > 0 ? round(($likes / $total_interactions) * 100) : 0;
                    
                    // Category name from pre-fetched code
                    $doc_type_name = isset($doc['doc_type_code']) ? getDocTypeName($doc['doc_type_code']) : 'Khác';
                    
                    // Icons logic from saved.php
                    $icon_class = 'fa-file-lines';
                    $icon_color = 'text-primary';
                    if(in_array($ext, ['pdf'])) { $icon_class = 'fa-file-pdf'; $icon_color = 'text-error'; }
                    elseif(in_array($ext, ['doc', 'docx'])) { $icon_class = 'fa-file-word'; $icon_color = 'text-info'; }
                    elseif(in_array($ext, ['xls', 'xlsx'])) { $icon_class = 'fa-file-excel'; $icon_color = 'text-success'; }
                    elseif(in_array($ext, ['ppt', 'pptx'])) { $icon_class = 'fa-file-powerpoint'; $icon_color = 'text-warning'; }
                    elseif(in_array($ext, ['zip', 'rar'])) { $icon_class = 'fa-file-zipper'; $icon_color = 'text-purple-500'; }

                    // Status
                    $status_badge = '';
                    if($doc['status'] == 'pending') $status_badge = '<span class="badge badge-warning font-black text-[9px] py-3 uppercase border-none shadow-sm shadow-warning/20">Đợi duyệt</span>';
                    elseif($doc['status'] == 'rejected') $status_badge = '<span class="badge badge-error font-black text-[9px] py-3 uppercase border-none shadow-sm shadow-error/20">Từ chối</span>';
                    elseif($doc['status'] == 'approved') $status_badge = '<span class="badge badge-success font-black text-[9px] py-3 uppercase border-none shadow-sm shadow-success/20">Đã duyệt</span>';
                ?>
                    <div class="group relative bg-base-100 rounded-[2.5rem] border border-base-200 overflow-hidden hover:shadow-2xl hover:shadow-primary/10 transition-all duration-500 hover:-translate-y-2 document-card-vsd">
                        <!-- Thumbnail Area -->
                        <div class="aspect-[3/4] bg-base-300/30 relative overflow-hidden flex items-center justify-center">
                            <?php if ($thumbnail && file_exists('../uploads/' . $thumbnail)): ?>
                                <img src="../uploads/<?= htmlspecialchars($thumbnail) ?>" loading="lazy" alt="Thumbnail" class="w-full h-full object-cover transition-transform duration-700">
                            <?php else: ?>
                                <div class="p-10 rounded-3xl bg-base-100 shadow-inner group-hover:scale-110 transition-transform duration-500">
                                    <i class="fa-solid <?= $icon_class ?> text-6xl <?= $icon_color ?>"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Badges -->
                            <div class="absolute top-5 left-5 flex flex-col gap-2">
                                <span class="badge bg-base-100/90 backdrop-blur-md border-none font-black text-[10px] py-3 uppercase shadow-sm"><?= $ext ?></span>
                                <?= $status_badge ?>
                            </div>

                            <div class="absolute bottom-5 right-5">
                                <span class="badge bg-primary/90 backdrop-blur-md border-none text-primary-content font-black text-[10px] py-3 px-4 shadow-xl"><?= $page_count ?> TRANG</span>
                            </div>

                            <!-- Actions Hover Overlay -->
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center gap-3">
                                <a href="view?id=<?= $doc_id ?>" class="btn btn-primary btn-circle shadow-xl translate-y-4 group-hover:translate-y-0 transition-transform duration-500">
                                    <i class="fa-solid fa-eye text-lg text-white"></i>
                                </a>
                                <a href="edit-document?id=<?= $doc_id ?>" class="btn btn-info btn-circle shadow-xl translate-y-4 group-hover:translate-y-0 transition-transform duration-500 delay-75">
                                    <i class="fa-solid fa-pen-to-square text-lg text-white"></i>
                                </a>
                                <button onclick="vsdConfirm({title: 'Xóa tài liệu', message: 'Hành động này không thể hoàn tác. Bạn chắc chứ?', type: 'error', onConfirm: () => window.location.href='dashboard?delete=<?= $doc_id ?>'})" 
                                        class="btn btn-error btn-circle shadow-xl translate-y-4 group-hover:translate-y-0 transition-transform duration-500 delay-100">
                                    <i class="fa-solid fa-trash-can text-lg text-white"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="p-6">
                            <h3 class="font-black text-base line-clamp-2 min-h-[3rem] text-base-content leading-tight group-hover:text-primary transition-colors cursor-pointer" onclick="window.location.href='view?id=<?= $doc_id ?>'" title="<?= htmlspecialchars(preg_replace('/\.[^.]+$/', '', $doc['original_name'])) ?>">
                                <?= htmlspecialchars(preg_replace('/\.[^.]+$/', '', $doc['original_name'])) ?>
                            </h3>
                            
                            <div class="mt-4 flex flex-col gap-3">
                                <!-- Quality Indicator -->
                                <div class="flex items-center gap-3 quality-indicator">
                                    <div class="flex-none flex items-center justify-center w-8 h-8 rounded-full bg-success text-white shadow-lg shadow-success/20">
                                        <i class="fa-solid fa-thumbs-up text-[10px]"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex justify-between items-center mb-1">
                                             <span class="text-[10px] font-black opacity-40 uppercase">Độ tin cậy</span>
                                             <span class="text-[10px] font-bold text-success"><?= $like_percentage ?>%</span>
                                        </div>
                                        <div class="w-full h-1.5 bg-success/10 rounded-full overflow-hidden">
                                            <div class="h-full bg-success shadow-[0_0_8px_rgba(34,197,94,0.4)] transition-all duration-1000" style="width: <?= $like_percentage ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="divider opacity-5 my-1"></div>

                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] font-black uppercase text-base-content/40 tracking-wider"><?= htmlspecialchars($doc_type_name) ?></span>
                                    </div>
                                    <div class="flex items-center gap-1 opacity-30">
                                        <i class="fa-solid fa-calendar-alt text-[10px]"></i>
                                        <span class="text-[10px] font-bold uppercase"><?= date('d M, Y', strtotime($doc['created_at'])) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>

                <!-- Pagination for My Documents -->
                <?php if ($total_my_pages > 1): ?>
                <div class="mt-12 flex justify-center">
                    <div class="join bg-base-200/50 p-1 rounded-2xl border border-base-300">
                        <?php if ($current_my_page > 1): ?>
                            <a href="?page=<?= $current_my_page - 1 ?>&pub_page=<?= $current_pub_page ?>" class="join-item btn btn-ghost hover:bg-base-100 rounded-xl px-4">
                                <i class="fa-solid fa-chevron-left text-xs"></i>
                            </a>
                        <?php endif; ?>

                        <?php 
                        for($i = 1; $i <= $total_my_pages; $i++): 
                            if ($i == 1 || $i == $total_my_pages || ($i >= $current_my_page - 1 && $i <= $current_my_page + 1)):
                        ?>
                            <a href="?page=<?= $i ?>&pub_page=<?= $current_pub_page ?>" class="join-item btn <?= $i == $current_my_page ? 'btn-primary shadow-lg shadow-primary/20' : 'btn-ghost hover:bg-base-100' ?> rounded-xl min-w-[48px]">
                                <?= $i ?>
                            </a>
                        <?php 
                            elseif ($i == $current_my_page - 2 || $i == $current_my_page + 2):
                        ?>
                            <button class="join-item btn btn-ghost btn-disabled rounded-xl cursor-default">...</button>
                        <?php 
                            endif;
                        endfor; 
                        ?>

                        <?php if ($current_my_page < $total_my_pages): ?>
                            <a href="?page=<?= $current_my_page + 1 ?>&pub_page=<?= $current_pub_page ?>" class="join-item btn btn-ghost hover:bg-base-100 rounded-xl px-4">
                                <i class="fa-solid fa-chevron-right text-xs"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="relative overflow-hidden rounded-[3rem] bg-base-100 border-2 border-dashed border-base-200 p-20 text-center">
                    <div class="absolute -right-20 -bottom-20 w-80 h-80 bg-primary/5 rounded-full blur-3xl"></div>
                    <div class="relative z-10">
                        <div class="w-24 h-24 rounded-3xl bg-base-200/50 flex items-center justify-center mb-6 mx-auto">
                            <i class="fa-solid fa-file-circle-plus text-4xl opacity-20"></i>
                        </div>
                        <h3 class="text-2xl font-black mb-2">Bạn chưa có tài liệu nào</h3>
                        <p class="text-base-content/50 mb-8 max-w-xs mx-auto">Bắt đầu chia sẻ kiến thức của bạn với cộng đồng ngay hôm nay.</p>
                        <a href="upload" class="btn btn-primary rounded-2xl px-10 h-14 font-black">
                            TẢI LÊN NGAY
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <!-- Public Documents Section -->
        <section>
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-2xl font-black flex items-center gap-3">
                    <span class="w-1.5 h-8 bg-secondary rounded-full"></span>
                    Khám phá tài liệu công khai
                </h2>
                <div class="badge badge-lg bg-base-100 border-base-200 shadow-sm font-bold text-base-content/60">
                    Kho tài liệu mới nhất
                </div>
            </div>

            <?php if(count($public_docs) > 0): ?>
                <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8 dashboard-grid">
                <?php
                foreach($public_docs as $doc):
                    $doc_id = $doc['id'];
                    $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                    $thumbnail = $doc['thumbnail'] ?? null;
                    $page_count = isset($doc['total_pages']) && $doc['total_pages'] > 0 ? $doc['total_pages'] : 1;
                    
                    // Stats from pre-fetched columns
                    $likes = intval($doc['likes'] ?? 0);
                    $dislikes = intval($doc['dislikes'] ?? 0);
                    $total_interactions = $likes + $dislikes;
                    $like_percentage = $total_interactions > 0 ? round(($likes / $total_interactions) * 100) : 0;
                    
                    $has_purchased = canUserDownloadDocument($user_id, $doc_id);
                    $purchased_badge = $has_purchased ? '<span class="badge badge-success font-black text-[9px] py-3 uppercase border-none shadow-sm shadow-success/20">Đã sở hữu</span>' : '';
                    
                    // Icons logic from saved.php
                    $icon_class = 'fa-file-lines';
                    $icon_color = 'text-primary';
                    if(in_array($ext, ['pdf'])) { $icon_class = 'fa-file-pdf'; $icon_color = 'text-error'; }
                    elseif(in_array($ext, ['doc', 'docx'])) { $icon_class = 'fa-file-word'; $icon_color = 'text-info'; }
                    elseif(in_array($ext, ['xls', 'xlsx'])) { $icon_class = 'fa-file-excel'; $icon_color = 'text-success'; }
                    elseif(in_array($ext, ['ppt', 'pptx'])) { $icon_class = 'fa-file-powerpoint'; $icon_color = 'text-warning'; }
                ?>
                    <div class="group relative bg-base-100 rounded-[2.5rem] border border-base-200 overflow-hidden hover:shadow-2xl hover:shadow-primary/10 transition-all duration-500 hover:-translate-y-2 document-card-vsd">
                        <!-- Thumbnail/Preview -->
                        <div class="aspect-[3/4] bg-base-300/30 relative overflow-hidden flex items-center justify-center">
                            <?php if ($thumbnail && file_exists('../uploads/' . $thumbnail)): ?>
                                <img src="../uploads/<?= htmlspecialchars($thumbnail) ?>" loading="lazy" alt="Thumbnail" class="w-full h-full object-cover transition-transform duration-700">
                            <?php else: ?>
                                <div class="p-10 rounded-3xl bg-base-100 shadow-inner group-hover:scale-110 transition-transform duration-500">
                                    <i class="fa-solid <?= $icon_class ?> text-6xl <?= $icon_color ?>"></i>
                                </div>
                            <?php endif; ?>

                            <!-- Overlay Badges -->
                            <div class="absolute top-5 left-5 flex flex-col gap-2">
                                <span class="badge bg-base-100/90 backdrop-blur-md border-none font-black text-[10px] py-3 uppercase shadow-sm"><?= $ext ?></span>
                                <?= $purchased_badge ?>
                            </div>

                            <div class="absolute bottom-5 right-5">
                                <span class="badge bg-primary/90 backdrop-blur-md border-none text-primary-content font-black text-[10px] py-3 px-4 shadow-xl"><?= $page_count ?> TRANG</span>
                            </div>

                            <!-- Hover Action -->
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                                <a href="view?id=<?= $doc_id ?>" class="btn btn-primary btn-circle shadow-xl translate-y-4 group-hover:translate-y-0 transition-transform duration-500">
                                    <i class="fa-solid fa-eye text-lg text-white"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="p-6">
                            <h3 class="font-black text-base line-clamp-2 min-h-[3rem] text-base-content leading-tight group-hover:text-primary transition-colors cursor-pointer" onclick="window.location.href='view?id=<?= $doc_id ?>'" title="<?= htmlspecialchars(preg_replace('/\.[^.]+$/', '', $doc['original_name'])) ?>">
                                <?= htmlspecialchars(preg_replace('/\.[^.]+$/', '', $doc['original_name'])) ?>
                            </h3>
                            
                            <div class="divider opacity-5 my-4"></div>

                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-xl bg-primary/5 overflow-hidden border border-primary/10">
                                        <?php if(!empty($doc['avatar']) && file_exists('../uploads/avatars/' . $doc['avatar'])): ?>
                                            <img src="../uploads/avatars/<?= $doc['avatar'] ?>" loading="lazy" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-primary/40">
                                                <i class="fa-solid fa-user text-xs"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <a href="user_profile?id=<?= $doc['user_id'] ?>" class="text-xs font-bold text-base-content/60 hover:text-primary transition-colors"><?= htmlspecialchars($doc['username']) ?></a>
                                </div>
                                <div class="flex items-center gap-1.5 px-3 py-1.5 bg-success/10 rounded-full">
                                    <i class="fa-solid fa-thumbs-up text-success text-[10px]"></i>
                                    <span class="text-[10px] font-black text-success"><?= $like_percentage ?>%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>

                <!-- Pagination for Public Documents -->
                <?php if ($total_pub_pages > 1): ?>
                <div class="mt-12 flex justify-center">
                    <div class="join bg-base-200/50 p-1 rounded-2xl border border-base-300">
                        <?php if ($current_pub_page > 1): ?>
                            <a href="?pub_page=<?= $current_pub_page - 1 ?>&page=<?= $current_my_page ?>" class="join-item btn btn-ghost hover:bg-base-100 rounded-xl px-4">
                                <i class="fa-solid fa-chevron-left text-xs"></i>
                            </a>
                        <?php endif; ?>

                        <?php 
                        for($i = 1; $i <= $total_pub_pages; $i++): 
                            if ($i == 1 || $i == $total_pub_pages || ($i >= $current_pub_page - 1 && $i <= $current_pub_page + 1)):
                        ?>
                            <a href="?pub_page=<?= $i ?>&page=<?= $current_my_page ?>" class="join-item btn <?= $i == $current_pub_page ? 'btn-secondary shadow-lg shadow-secondary/20 text-secondary-content' : 'btn-ghost hover:bg-base-100' ?> rounded-xl min-w-[48px]">
                                <?= $i ?>
                            </a>
                        <?php 
                            elseif ($i == $current_pub_page - 2 || $i == $current_pub_page + 2):
                        ?>
                            <button class="join-item btn btn-ghost btn-disabled rounded-xl cursor-default">...</button>
                        <?php 
                            endif;
                        endfor; 
                        ?>

                        <?php if ($current_pub_page < $total_pub_pages): ?>
                            <a href="?pub_page=<?= $current_pub_page + 1 ?>&page=<?= $current_my_page ?>" class="join-item btn btn-ghost hover:bg-base-100 rounded-xl px-4">
                                <i class="fa-solid fa-chevron-right text-xs"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-20 bg-base-100 rounded-[3rem] border border-base-200 shadow-inner">
                    <i class="fa-solid fa-file-circle-question text-6xl text-base-content/10 mb-6"></i>
                    <h3 class="text-xl font-black opacity-20 uppercase tracking-widest">Không có tài liệu nào công khai</h3>
                </div>
            <?php endif; ?>
        </section>
    </main>
    
    <?php include '../includes/footer.php'; ?>
</div>
</div>

<?php 
// Layout clean up
?>

<?php 
// db connection cleaned up by app flow
?>