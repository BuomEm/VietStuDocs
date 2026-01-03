<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: index.php");
}

require_once 'config/db.php';
require_once 'config/function.php';
require_once 'config/auth.php';
require_once 'config/premium.php';

$user_id = getCurrentUserId();
$is_premium = isPremium($user_id);
$current_page = 'saved';

// Handle unsave
if(isset($_GET['unsave'])) {
    $doc_id = intval($_GET['unsave']);
    $VSD->query("DELETE FROM document_interactions WHERE document_id=$doc_id AND user_id=$user_id AND type='save'");
    header("Location: saved.php?msg=unsaved");
    exit;
}

// Fetch saved documents (AFTER unsave logic) - only approved documents
$all_saved_docs = $VSD->get_list("
    SELECT d.*, u.username, u.avatar FROM documents d 
    JOIN users u ON d.user_id = u.id 
    JOIN document_interactions di ON d.id = di.document_id
    WHERE di.user_id = $user_id AND di.type = 'save' AND (d.status = 'approved' OR d.user_id = $user_id)
    ORDER BY di.created_at DESC
");

$total_saved = count($all_saved_docs);
?>
<?php include 'includes/head.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="drawer-content flex flex-col">
    <?php include 'includes/navbar.php'; ?>
    
    <main class="flex-1 p-4 lg:p-8">
        <!-- Header Section -->
        <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h1 class="text-4xl font-extrabold flex items-center gap-3 text-base-content">
                    <div class="p-3 rounded-2xl bg-primary/10 text-primary shadow-inner">
                        <i class="fa-solid fa-bookmark"></i>
                    </div>
                    Tài Liệu Đã Lưu
                </h1>
                <p class="text-base-content/60 mt-2 font-medium">Lưu trữ những kiến thức quan trọng của bạn</p>
            </div>
            
            <div class="flex gap-2">
                <div class="badge badge-lg py-4 px-6 bg-base-100 border-base-300 shadow-sm font-bold gap-2 text-primary">
                    <i class="fa-solid fa-folder-open text-primary/60"></i>
                    Tổng số: <span class="ml-1"><?= $total_saved ?></span>
                </div>
            </div>
        </div>

        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'unsaved'): ?>
            <div class="alert bg-success/10 border-success/20 text-success mb-8 rounded-2xl animate-bounce-slow">
                <i class="fa-solid fa-circle-check"></i>
                <span class="font-bold">Đã bỏ lưu tài liệu thành công!</span>
            </div>
        <?php endif; ?>

        <?php if($total_saved > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php
                foreach($all_saved_docs as $doc):
                    $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                    $thumbnail = $doc['thumbnail'] ?? null;
                    $total_pages = isset($doc['total_pages']) && $doc['total_pages'] > 0 ? $doc['total_pages'] : null;
                    
                    $icon_class = 'fa-file-lines';
                    $icon_color = 'text-primary';
                    if(in_array($ext, ['pdf'])) { $icon_class = 'fa-file-pdf'; $icon_color = 'text-error'; }
                    elseif(in_array($ext, ['doc', 'docx'])) { $icon_class = 'fa-file-word'; $icon_color = 'text-info'; }
                    elseif(in_array($ext, ['xls', 'xlsx'])) { $icon_class = 'fa-file-excel'; $icon_color = 'text-success'; }
                    elseif(in_array($ext, ['ppt', 'pptx'])) { $icon_class = 'fa-file-powerpoint'; $icon_color = 'text-warning'; }
                    elseif(in_array($ext, ['zip', 'rar'])) { $icon_class = 'fa-file-zipper'; $icon_color = 'text-purple-500'; }
                ?>
                    <div class="group relative bg-base-100 rounded-[2rem] border border-base-200 overflow-hidden hover:shadow-2xl hover:shadow-primary/10 transition-all duration-500 hover:-translate-y-2">
                        <!-- Thumbnail/Icon Area -->
                        <div class="aspect-[4/3] bg-base-300/30 relative overflow-hidden flex items-center justify-center">
                            <?php if ($thumbnail && file_exists('uploads/' . $thumbnail)): ?>
                                <img src="uploads/<?= htmlspecialchars($thumbnail) ?>" alt="Thumbnail" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                            <?php else: ?>
                                <div class="p-8 rounded-3xl bg-base-100 shadow-inner group-hover:scale-110 transition-transform duration-500">
                                    <i class="fa-solid <?= $icon_class ?> text-5xl <?= $icon_color ?>"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Badges Overlay -->
                            <div class="absolute top-4 left-4 flex gap-2">
                                <span class="badge bg-base-100/90 backdrop-blur-md border-none font-black text-[10px] py-3 uppercase shadow-sm"><?= $ext ?></span>
                                <?php if ($total_pages): ?>
                                    <span class="badge bg-primary/90 backdrop-blur-md border-none text-primary-content font-bold text-[10px] py-3 shadow-sm"><?= $total_pages ?> TRANG</span>
                                <?php endif; ?>
                            </div>

                            <!-- Actions Overlay (Hover) -->
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center gap-3">
                                <a href="view.php?id=<?= $doc['id'] ?>" class="btn btn-primary btn-circle btn-lg shadow-xl translate-y-4 group-hover:translate-y-0 transition-transform duration-500">
                                    <i class="fa-solid fa-eye text-xl"></i>
                                </a>
                                <button onclick="vsdConfirm({title: 'Xác nhận bỏ lưu', message: 'Bạn có chắc chắn muốn bỏ lưu tài liệu này?', type: 'warning', onConfirm: () => window.location.href='saved.php?unsave=<?= $doc['id'] ?>'})" 
                                        class="btn btn-error btn-circle btn-lg shadow-xl translate-y-4 group-hover:translate-y-0 transition-transform duration-500 delay-75">
                                    <i class="fa-solid fa-bookmark-slash text-xl"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="p-6">
                            <h3 class="font-black text-base line-clamp-2 min-h-[3rem] text-base-content leading-tight group-hover:text-primary transition-colors cursor-pointer" onclick="window.location.href='view.php?id=<?= $doc['id'] ?>'">
                                <?= htmlspecialchars($doc['original_name']) ?>
                            </h3>
                            
                            <div class="divider opacity-10 my-4"></div>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-full bg-primary/10 overflow-hidden border border-primary/20">
                                        <?php if(!empty($doc['avatar']) && file_exists('uploads/avatars/' . $doc['avatar'])): ?>
                                            <img src="uploads/avatars/<?= $doc['avatar'] ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-primary">
                                                <i class="fa-solid fa-user text-xs"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-xs font-bold text-base-content/60"><?= htmlspecialchars($doc['username']) ?></span>
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-tighter opacity-30"><?= date('d M, Y', strtotime($doc['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Premium Empty State -->
            <div class="relative overflow-hidden rounded-[3rem] bg-base-100 border-2 border-dashed border-base-200 p-20 text-center shadow-inner">
                <div class="absolute -right-20 -bottom-20 w-80 h-80 bg-primary/5 rounded-full blur-3xl"></div>
                <div class="absolute -left-20 -top-20 w-80 h-80 bg-primary/5 rounded-full blur-3xl"></div>
                
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-32 h-32 rounded-[2.5rem] bg-base-200/50 flex items-center justify-center mb-8 text-primary shadow-lg border border-white/40 backdrop-blur-sm">
                        <i class="fa-solid fa-bookmark text-5xl opacity-20"></i>
                    </div>
                    <h2 class="text-3xl font-black mb-4">Danh sách trống</h2>
                    <p class="text-base-content/50 max-w-sm mx-auto mb-10 text-lg font-medium leading-relaxed">
                        Bạn chưa lưu tài liệu nào cả. Hãy khám phá kho tài liệu khổng lồ và lưu lại những kiến thức bổ ích nhé!
                    </p>
                    <a href="dashboard.php" class="btn btn-primary btn-lg rounded-2xl px-12 h-16 shadow-xl shadow-primary/20 hover:shadow-primary/40 transition-all duration-300 font-black tracking-wide">
                        <i class="fa-solid fa-compass mr-2"></i>
                        KHÁM PHÁ NGAY
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</div>
</div>
<?php 
// No more stray code here
?>
