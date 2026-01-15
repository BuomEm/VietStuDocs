<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit();
}

require_once '../config/db.php';
require_once '../config/function.php';
require_once '../config/auth.php';
require_once '../config/premium.php';
require_once '../config/points.php';

require_once '../config/settings.php';

$user_id = getCurrentUserId();
$is_premium = isPremium($user_id);
$page_title = "Lịch Sử Hoạt Động";
$current_page = 'history';

// Get active tab
$active_tab = $_GET['tab'] ?? 'purchases';

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get Notifications History
$notifs_query = "
    SELECT *
    FROM notifications
    WHERE user_id = $user_id
    ORDER BY created_at DESC
    LIMIT $per_page OFFSET $offset
";
$notifs_result = $VSD->get_list($notifs_query);
$total_notifs = $VSD->num_rows("SELECT id FROM notifications WHERE user_id = $user_id");

// Get Purchase History
$purchases_query = "
    SELECT 
        ds.id,
        ds.buyer_user_id as user_id,
        ds.document_id,
        ds.points_paid as points_spent,
        ds.purchased_at,
        d.original_name,
        d.file_name,
        d.thumbnail,
        u.username as seller_name
    FROM document_sales ds
    JOIN documents d ON ds.document_id = d.id
    LEFT JOIN users u ON ds.seller_user_id = u.id
    WHERE ds.buyer_user_id = $user_id
    ORDER BY ds.purchased_at DESC
    LIMIT $per_page OFFSET $offset
";
$purchases_result = $VSD->get_list($purchases_query);
$total_purchases = $VSD->num_rows("SELECT id FROM document_sales WHERE buyer_user_id = $user_id");

// Get Point Transactions History
$points_query = "
    SELECT *
    FROM point_transactions
    WHERE user_id = $user_id
    ORDER BY created_at DESC
    LIMIT $per_page OFFSET $offset
";
$points_result = $VSD->get_list($points_query);
$total_points = $VSD->num_rows("SELECT id FROM point_transactions WHERE user_id = $user_id");

// Get Premium Transactions (check if table exists first)
if(db_table_exists('transactions')) {
    $premium_query = "
        SELECT *
        FROM transactions
        WHERE user_id = $user_id
        ORDER BY created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    $premium_result = $VSD->get_list($premium_query);
    $total_premium = $VSD->num_rows("SELECT id FROM transactions WHERE user_id = $user_id");
} else {
    // Create empty array if table doesn't exist
    $premium_result = [];
    $total_premium = 0;
}

// Get Upload History
$uploads_query = "
    SELECT *
    FROM documents
    WHERE user_id = $user_id
    ORDER BY created_at DESC
    LIMIT $per_page OFFSET $offset
";
$uploads_result = $VSD->get_list($uploads_query);
$total_uploads = $VSD->num_rows("SELECT id FROM documents WHERE user_id = $user_id");

// Get Statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM document_sales WHERE buyer_user_id = $user_id) as total_purchased,
        (SELECT SUM(points_paid) FROM document_sales WHERE buyer_user_id = $user_id) as total_spent,
        (SELECT COUNT(*) FROM documents WHERE user_id = $user_id) as total_uploaded,
        (SELECT SUM(views) FROM documents WHERE user_id = $user_id) as total_views,
        (SELECT SUM(downloads) FROM documents WHERE user_id = $user_id) as total_downloads
";
$stats = $VSD->get_row($stats_query);


?>
<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="drawer-content flex flex-col">
    <?php include '../includes/navbar.php'; ?>
    
    <main class="flex-1 p-4 lg:p-8 bg-base-200/50">
        <!-- Header Section -->
        <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <h1 class="text-4xl font-extrabold flex items-center gap-4 text-base-content">
                    <div class="p-3.5 rounded-[1.5rem] bg-primary/10 text-primary shadow-inner border border-primary/10">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    Lịch Sử Hoạt Động
            </h1>
                <p class="text-base-content/60 mt-2 font-medium">Theo dõi mọi vết chân của bạn trên DocShare</p>
            </div>
            
            <!-- <div class="flex items-center gap-3">
                <div class="badge badge-lg h-14 px-8 bg-base-100 border-base-200 shadow-sm font-black gap-3 rounded-2xl">
                    <div class="w-2.5 h-2.5 rounded-full bg-primary animate-pulse"></div>
                    TRỰC TUYẾN
                </div>
            </div> -->
        </div>

        <!-- Statistics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <!-- Stat 1 -->
            <div class="group relative overflow-hidden bg-gradient-to-br from-success to-emerald-700 rounded-[2.5rem] p-8 text-white shadow-xl shadow-success/20 hover:-translate-y-2 transition-all duration-500">
                <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-white/80 font-black uppercase text-[10px] tracking-widest">Đã mua</span>
                        <i class="fa-solid fa-cart-shopping text-xl opacity-40"></i>
                    </div>
                    <div class="text-4xl font-black mb-1"><?= number_format($stats['total_purchased'] ?? 0) ?></div>
                    <div class="text-[10px] font-bold text-white/60 uppercase">Tổng chi: <?= number_format($stats['total_spent'] ?? 0) ?> điểm</div>
                </div>
            </div>

            <!-- Stat 2 -->
            <div class="group relative overflow-hidden bg-gradient-to-br from-primary to-primary-focus rounded-[2.5rem] p-8 text-white shadow-xl shadow-primary/20 hover:-translate-y-2 transition-all duration-500">
                <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-white/80 font-black uppercase text-[10px] tracking-widest">Tải lên</span>
                        <i class="fa-solid fa-cloud-arrow-up text-xl opacity-40"></i>
                    </div>
                    <div class="text-4xl font-black mb-1"><?= number_format($stats['total_uploaded'] ?? 0) ?></div>
                    <div class="text-[10px] font-bold text-white/60 uppercase"><?= number_format($stats['total_views'] ?? 0) ?> lượt xem</div>
                </div>
            </div>

            <!-- Stat 3 -->
            <div class="group relative overflow-hidden bg-gradient-to-br from-warning to-orange-600 rounded-[2.5rem] p-8 text-white shadow-xl shadow-warning/20 hover:-translate-y-2 transition-all duration-500">
                <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-white/80 font-black uppercase text-[10px] tracking-widest">Đã tải về</span>
                        <i class="fa-solid fa-download text-xl opacity-40"></i>
                    </div>
                    <div class="text-4xl font-black mb-1"><?= number_format($stats['total_downloads'] ?? 0) ?></div>
                    <div class="text-[10px] font-bold text-white/60 uppercase">Từ tài liệu của bạn</div>
                </div>
            </div>

            <!-- Stat 4 -->
            <div class="group relative overflow-hidden bg-gradient-to-br from-secondary to-secondary-focus rounded-[2.5rem] p-8 text-white shadow-xl shadow-secondary/20 hover:-translate-y-2 transition-all duration-500">
                <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-white/80 font-black uppercase text-[10px] tracking-widest">Số dư hiện tại</span>
                        <i class="fa-solid fa-wallet text-xl opacity-40"></i>
                    </div>
                    <div class="text-4xl font-black mb-1"><?= number_format(getUserPoints($user_id)['current_points']) ?></div>
                    <div class="text-[10px] font-bold text-white/60 uppercase"><?= $is_premium ? 'TÀI KHOẢN PREMIUM' : 'TÀI KHOẢN MIỄN PHÍ' ?></div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tabs tabs-boxed bg-base-100/50 backdrop-blur-md p-1.5 rounded-[1.8rem] mb-10 border border-base-200 overflow-x-auto flex-nowrap h-16 items-center">
            <a href="?tab=purchases" class="tab tab-lg h-full px-6 rounded-2xl gap-2 font-black transition-all duration-300 <?= $active_tab === 'purchases' ? 'bg-primary text-primary-content shadow-lg shadow-primary/20' : 'hover:bg-base-200' ?>">
                <i class="fa-solid fa-cart-shopping text-sm"></i>
                <span class="whitespace-nowrap">MUA HÀNG</span>
            </a>
            <a href="?tab=points" class="tab tab-lg h-full px-6 rounded-2xl gap-2 font-black transition-all duration-300 <?= $active_tab === 'points' ? 'bg-primary text-primary-content shadow-lg shadow-primary/20' : 'hover:bg-base-200' ?>">
                <i class="fa-solid fa-coins text-sm"></i>
                <span class="whitespace-nowrap">ĐIỂM</span>
            </a>
            <a href="?tab=premium" class="tab tab-lg h-full px-6 rounded-2xl gap-2 font-black transition-all duration-300 <?= $active_tab === 'premium' ? 'bg-primary text-primary-content shadow-lg shadow-primary/20' : 'hover:bg-base-200' ?>">
                <i class="fa-solid fa-crown text-sm"></i>
                <span class="whitespace-nowrap">PREMIUM</span>
            </a>
            <a href="?tab=uploads" class="tab tab-lg h-full px-6 rounded-2xl gap-2 font-black transition-all duration-300 <?= $active_tab === 'uploads' ? 'bg-primary text-primary-content shadow-lg shadow-primary/20' : 'hover:bg-base-200' ?>">
                <i class="fa-solid fa-cloud-arrow-up text-sm"></i>
                <span class="whitespace-nowrap">UPLOAD</span>
            </a>
            <a href="?tab=notifications" class="tab tab-lg h-full px-6 rounded-2xl gap-2 font-black transition-all duration-300 <?= $active_tab === 'notifications' ? 'bg-primary text-primary-content shadow-lg shadow-primary/20' : 'hover:bg-base-200' ?>">
                <i class="fa-solid fa-bell text-sm"></i>
                <span class="whitespace-nowrap">THÔNG BÁO</span>
            </a>
        </div>

        <!-- History Content Container -->
        <div class="min-h-[400px]">
            <!-- Notifications Tab -->
            <?php if($active_tab === 'notifications'): ?>
                <div class="bg-base-100 rounded-[3rem] p-10 border border-base-200 shadow-xl shadow-base-200">
                    <div class="flex justify-between items-center mb-10">
                        <h2 class="text-2xl font-black flex items-center gap-3">
                            <i class="fa-solid fa-bell text-primary"></i>
                            Thông báo mới nhất
                        </h2>
                        <button onclick="markRead()" class="btn btn-ghost hover:bg-primary/5 text-primary btn-sm rounded-xl font-black text-[10px] uppercase tracking-wider">
                            Đánh dấu đã đọc
                        </button>
                    </div>
                    
                    <?php if(count($notifs_result) > 0): ?>
                        <div class="flex flex-col gap-4">
                            <?php foreach($notifs_result as $notif): 
                                $icon = 'fa-info-circle'; $icon_color = 'text-primary'; $icon_bg = 'bg-primary/10';
                                switch($notif['type']) {
                                    case 'document_approved': $icon = 'fa-check-circle'; $icon_color = 'text-success'; $icon_bg = 'bg-success/10'; break;
                                    case 'document_rejected': $icon = 'fa-times-circle'; $icon_color = 'text-error'; $icon_bg = 'bg-error/10'; break;
                                    case 'document_deleted': $icon = 'fa-trash-can'; $icon_color = 'text-error'; $icon_bg = 'bg-error/10'; break;
                                    case 'points_added': $icon = 'fa-coins'; $icon_color = 'text-success'; $icon_bg = 'bg-success/10'; break;
                                    case 'points_deducted': $icon = 'fa-circle-minus'; $icon_color = 'text-error'; $icon_bg = 'bg-error/10'; break;
                                    case 'tutor_answer': $icon = 'fa-graduation-cap'; $icon_color = 'text-info'; $icon_bg = 'bg-info/10'; break;
                                }
                            ?>
                                <div class="group relative flex items-start gap-6 p-6 rounded-[1.8rem] border border-transparent hover:border-base-200 hover:bg-base-200/30 transition-all duration-300 <?= $notif['is_read'] == 0 ? 'bg-primary/5 border-primary/10 shadow-sm shadow-primary/5' : '' ?>">
                                    <div class="w-14 h-14 rounded-2xl <?= $icon_bg ?> <?= $icon_color ?> flex items-center justify-center shrink-0 shadow-inner group-hover:scale-110 transition-transform duration-300">
                                        <i class="fa-solid <?= $icon ?> text-2xl"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex justify-between items-start mb-1">
                                            <h4 class="font-black text-base text-base-content"><?= htmlspecialchars($notif['title'] ?? 'Hệ thống') ?></h4>
                                            <span class="text-[9px] font-black uppercase text-base-content/30 opacity-60"><?= date('H:i • d M, Y', strtotime($notif['created_at'])) ?></span>
                                        </div>
                                        <p class="text-sm font-medium text-base-content/60 leading-relaxed"><?= htmlspecialchars($notif['message']) ?></p>
                                    </div>
                                    <?php if($notif['is_read'] == 1): ?>
                                        <div class="absolute right-6 top-1/2 -translate-y-1/2 opacity-20">
                                            <i class="fa-solid fa-check-double text-xs"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="py-20 text-center opacity-20">
                            <i class="fa-solid fa-bell-slash text-6xl mb-6"></i>
                            <h4 class="text-xl font-black uppercase tracking-widest">Không có thông báo</h4>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Purchase History Tab -->
            <?php if($active_tab === 'purchases'): ?>
                <div class="grid grid-cols-1 gap-6">
                    <?php if(count($purchases_result) > 0): ?>
                        <?php foreach($purchases_result as $purchase): ?>
                            <div class="group relative bg-base-100 rounded-[2.5rem] border border-base-200 p-6 flex flex-col md:flex-row gap-8 hover:shadow-2xl hover:shadow-primary/5 transition-all duration-500 overflow-hidden">
                                <div class="absolute -right-20 -bottom-20 w-80 h-80 bg-primary/5 rounded-full blur-3xl group-hover:bg-primary/10 transition-colors"></div>
                                
                                <!-- Thumb Container -->
                                <div class="w-full md:w-32 aspect-square rounded-[2rem] bg-base-200 overflow-hidden shrink-0 shadow-inner">
                                    <?php if($purchase['thumbnail'] && file_exists('../uploads/' . $purchase['thumbnail'])): ?>
                                        <img src="../uploads/<?= $purchase['thumbnail'] ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-primary/20">
                                            <i class="fa-solid fa-file-invoice text-4xl"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="flex-1">
                                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                                        <!-- Main Content Info -->
                                        <div class="space-y-4 flex-1">
                                            <h3 class="font-black text-2xl text-base-content group-hover:text-primary transition-colors leading-tight">
                                                <?= htmlspecialchars($purchase['original_name']) ?>
                                            </h3>
                                            
                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-8">
                                                <div class="flex flex-col">
                                                    <span class="text-[10px] font-black uppercase tracking-widest text-base-content/30 mb-1.5">Chi Tiết</span>
                                                    <a href="transaction-details.php?id=<?= $purchase['id'] ?>&type=purchase" class="text-sm font-black text-primary border-b border-primary/20 hover:border-primary transition-all w-fit uppercase">Xem giao dịch</a>
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="text-[10px] font-black uppercase tracking-widest text-base-content/30 mb-1.5">Giá thanh toán</span>
                                                    <span class="text-sm font-black text-warning"><?= number_format($purchase['points_spent']) ?> ĐIỂM</span>
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="text-[10px] font-black uppercase tracking-widest text-base-content/30 mb-1.5">Người bán</span>
                                                    <span class="text-sm font-black text-base-content/60"><?= htmlspecialchars($purchase['seller_name'] ?? 'VietStuDocs') ?></span>
                                                </div>
                                            </div>
        </div>

                                        <!-- Right Side Actions -->
                                        <div class="flex flex-col items-end gap-3 shrink-0">
                                            <div class="px-5 py-2.5 rounded-2xl bg-success/10 text-success text-[10px] font-black uppercase tracking-widest border border-success/10">Đã thanh toán</div>
                                            <a href="view.php?id=<?= $purchase['document_id'] ?>" class="btn btn-primary btn-lg rounded-2xl px-12 h-14 min-h-0 shadow-xl shadow-primary/20 font-black hover:scale-[1.05] active:scale-95 transition-all uppercase tracking-wider text-sm">
                                                XEM NGAY
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="bg-base-100 rounded-[3rem] p-24 text-center border border-base-200">
                             <div class="w-24 h-24 rounded-[2rem] bg-base-200/50 flex items-center justify-center mx-auto mb-8">
                                <i class="fa-solid fa-bag-shopping text-4xl opacity-10"></i>
                             </div>
                             <h3 class="text-2xl font-black mb-4">Chưa có giao dịch mua</h3>
                             <p class="text-base-content/40 mb-10 max-w-xs mx-auto">Bạn chưa sở hữu tài liệu trả phí nào. Hãy khám phá kho tài liệu khổng lồ ngay!</p>
                             <a href="search.php" class="btn btn-primary btn-lg rounded-2xl px-12 h-16 shadow-xl font-black">KHÁM PHÁ</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Points Transaction Tab -->
            <?php if($active_tab === 'points'): ?>
                <div class="bg-base-100 rounded-[3rem] p-10 border border-base-200 shadow-xl shadow-base-200">
                    <?php if(count($points_result) > 0): ?>
                        <div class="flex flex-col gap-5">
                            <?php foreach($points_result as $trans): 
                                $is_earn = $trans['transaction_type'] === 'earn';
                            ?>
                                <div class="group flex items-center gap-6 p-6 rounded-[2rem] bg-base-200/20 border border-transparent hover:border-base-200 transition-all duration-300">
                                    <div class="w-16 h-16 rounded-2xl <?= $is_earn ? 'bg-success/10 text-success' : 'bg-error/10 text-error' ?> flex items-center justify-center shadow-inner shrink-0 group-hover:scale-110 transition-transform">
                                        <i class="fa-solid <?= $is_earn ? 'fa-arrow-up-right-dots' : 'fa-arrow-down-short-wide' ?> text-2xl"></i>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="font-black text-lg"><?= htmlspecialchars($trans['reason'] ?? 'Giao dịch không tên') ?></h4>
                                        <p class="text-[10px] font-black uppercase text-base-content/30 mt-1"><?= date('d F Y • H:i:s', strtotime($trans['created_at'])) ?></p>
                                        <a href="transaction-details.php?id=<?= $trans['id'] ?>&type=points" class="mt-2 inline-block text-[9px] font-black uppercase text-primary border-b border-primary/20 hover:border-primary transition-all">XEM GIAO DỊCH</a>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-3xl font-black <?= $is_earn ? 'text-success' : 'text-error' ?>">
                                            <?= $is_earn ? '+' : '-' ?><?= number_format($trans['points']) ?>
                                        </div>
                                        <span class="text-[9px] font-black uppercase tracking-widest opacity-40"><?= $is_earn ? 'Nhận được' : 'Đã sử dụng' ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="py-32 text-center opacity-10">
                            <i class="fa-solid fa-coins text-8xl mb-6"></i>
                            <h4 class="text-2xl font-black uppercase">Chưa có giao dịch điểm</h4>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Premium Transaction Tab -->
            <?php if($active_tab === 'premium'): ?>
                <div class="grid grid-cols-1 gap-6">
                    <?php if(count($premium_result) > 0): ?>
                        <?php foreach($premium_result as $trans): 
                            $status_class = 'badge-ghost'; $status_label = 'Không xác định';
                            if($trans['status'] == 'completed') { $status_class = 'bg-success/10 text-success'; $status_label = 'THÀNH CÔNG'; }
                            elseif($trans['status'] == 'pending') { $status_class = 'bg-warning/10 text-warning'; $status_label = 'ĐANG CHỜ'; }
                            elseif($trans['status'] == 'failed') { $status_class = 'bg-error/10 text-error'; $status_label = 'THẤT BẠI'; }
                        ?>
                            <div class="relative bg-base-100 rounded-[2.5rem] p-8 border border-base-200 flex flex-col md:flex-row items-center justify-between gap-8 shadow-lg shadow-base-200/50">
                                <div class="flex items-center gap-6">
                                    <div class="w-20 h-20 rounded-[2rem] bg-gradient-to-br from-warning/20 to-orange-500/10 flex items-center justify-center text-warning shadow-inner">
                                        <i class="fa-solid fa-crown text-3xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-2xl font-black text-base-content">Gói Premium <?= ucfirst($trans['transaction_type']) ?></h3>
                                        <div class="flex items-center gap-3 mt-2">
                                            <span class="text-[10px] font-black text-base-content/40 uppercase tracking-widest"><?= date('d/m/Y H:i', strtotime($trans['created_at'])) ?></span>
                                            <div class="w-1 h-1 rounded-full bg-base-300"></div>
                                            <span class="text-[10px] font-black <?= $status_class ?> px-3 py-1 rounded-full"><?= $status_label ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end">
                                    <div class="text-4xl font-black text-primary"><?= number_format($trans['amount']) ?>đ</div>
                                    <a href="transaction-details.php?id=<?= $trans['id'] ?>&type=premium" class="mt-4 text-[10px] font-black uppercase text-primary border-b-2 border-primary/20 hover:border-primary transition-all">Xem chi tiết hóa đơn</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="bg-gradient-to-br from-base-100 to-base-200/50 rounded-[3.5rem] p-24 text-center border-2 border-dashed border-base-200">
                             <div class="w-20 h-20 rounded-[2.5rem] bg-warning/10 flex items-center justify-center mx-auto mb-8 border border-warning/20 shadow-xl shadow-warning/5">
                                <i class="fa-solid fa-crown text-3xl text-warning"></i>
                             </div>
                             <h3 class="text-3xl font-black mb-4">Bạn chưa là Premium?</h3>
                             <p class="text-base-content/50 mb-10 max-w-sm mx-auto font-medium">Bản nâng cấp giúp bạn truy cập không giới hạn, tải xuống tốc độ cao và nhận nhiều ưu đãi đặc quyền.</p>
                             <a href="premium.php" class="btn btn-warning btn-lg rounded-2xl px-12 h-16 shadow-xl shadow-warning/20 font-black hover:scale-105 transition-transform">NÂNG CẤP NGAY</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Upload History Tab -->
            <?php if($active_tab === 'uploads'): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php if(count($uploads_result) > 0): ?>
                        <?php foreach($uploads_result as $doc): 
                            $status_badge = '';
                            if($doc['status'] == 'approved') $status_badge = '<span class="badge bg-success/10 text-success border-none font-black text-[9px] py-3 px-4">Đã duyệt</span>';
                            elseif($doc['status'] == 'pending') $status_badge = '<span class="badge bg-warning/10 text-warning border-none font-black text-[9px] py-3 px-4">Chờ duyệt</span>';
                            elseif($doc['status'] == 'rejected') $status_badge = '<span class="badge bg-error/10 text-error border-none font-black text-[9px] py-3 px-4">Từ chối</span>';
                        ?>
                            <div class="group relative bg-base-100 rounded-[2.5rem] border border-base-200 p-6 flex items-center gap-6 hover:shadow-2xl hover:shadow-primary/5 transition-all duration-500">
                                <div class="w-24 h-24 rounded-[1.8rem] bg-base-200/50 flex items-center justify-center shrink-0 shadow-inner group-hover:scale-105 transition-transform duration-500">
                                    <?php if($doc['thumbnail']): ?>
                                        <img src="../uploads/<?= $doc['thumbnail'] ?>" class="w-full h-full object-cover rounded-[1.8rem]">
                                    <?php else: ?>
                                        <i class="fa-solid fa-file-pdf text-4xl text-primary/20"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 overflow-hidden">
                                     <h3 class="font-black text-base text-base-content line-clamp-1 mb-2 group-hover:text-primary transition-colors"><?= htmlspecialchars($doc['original_name']) ?></h3>
                                     <div class="flex flex-wrap gap-2 mb-3">
                                         <?= $status_badge ?>
                                         <span class="badge bg-base-200 border-none text-base-content/40 font-black text-[9px] py-3 px-4"><?= date('d M, Y', strtotime($doc['created_at'])) ?></span>
                                     </div>
                                     <div class="flex items-center gap-6 opacity-40">
                                         <div class="flex items-center gap-1.5">
                                             <i class="fa-solid fa-eye text-[10px]"></i>
                                             <span class="text-[10px] font-black uppercase"><?= number_format($doc['views']) ?></span>
                                         </div>
                                         <div class="flex items-center gap-1.5">
                                             <i class="fa-solid fa-download text-[10px]"></i>
                                             <span class="text-[10px] font-black uppercase"><?= number_format($doc['downloads']) ?></span>
                                         </div>
                                     </div>
                                </div>
                                <div class="shrink-0 flex flex-col gap-2">
                                     <a href="view.php?id=<?= $doc['id'] ?>" class="btn btn-primary btn-circle shadow-lg shadow-primary/20">
                                         <i class="fa-solid fa-eye"></i>
                                     </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full bg-base-100 rounded-[3rem] p-24 text-center border-2 border-dashed border-base-200">
                             <div class="w-20 h-20 rounded-[2rem] bg-primary/10 flex items-center justify-center mx-auto mb-8">
                                <i class="fa-solid fa-cloud-arrow-up text-3xl text-primary"></i>
                             </div>
                             <h3 class="text-2xl font-black mb-4">Kho tài liệu trống</h3>
                             <p class="text-base-content/50 mb-10 max-w-xs mx-auto">Chia sẻ tài liệu của bạn để nhận điểm thưởng và giúp đỡ cộng đồng!</p>
                             <a href="upload.php" class="btn btn-primary btn-lg rounded-2xl px-12 h-16 shadow-xl font-black">TẢI LÊN NGAY</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Premium Pagination -->
            <?php 
                $total_items = 0;
                if($active_tab === 'purchases') $total_items = $total_purchases;
                elseif($active_tab === 'points') $total_items = $total_points;
                elseif($active_tab === 'premium') $total_items = $total_premium;
                elseif($active_tab === 'uploads') $total_items = $total_uploads;
                elseif($active_tab === 'notifications') $total_items = $total_notifs;
                $total_pages = ceil($total_items / $per_page);
            ?>
            
            <?php if($total_pages > 1): ?>
                <div class="flex justify-center mt-20">
                    <div class="join bg-base-100 p-1 rounded-2xl border border-base-200 shadow-xl shadow-base-200/50">
                        <a href="?tab=<?= $active_tab ?>&page=<?= max(1, $page-1) ?>" class="join-item btn btn-md border-none bg-transparent hover:bg-base-200 <?= $page <= 1 ? 'btn-disabled opacity-30' : '' ?>">
                            <i class="fa-solid fa-chevron-left text-xs"></i>
                        </a>
                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        if($start > 1): ?>
                            <a href="?tab=<?= $active_tab ?>&page=1" class="join-item btn btn-md border-none bg-transparent hover:bg-base-200">1</a>
                            <?php if($start > 2): ?><button class="join-item btn btn-md border-none bg-transparent btn-disabled opacity-30">...</button><?php endif; ?>
                        <?php endif; ?>
                        <?php for($i = $start; $i <= $end; $i++): ?>
                            <a href="?tab=<?= $active_tab ?>&page=<?= $i ?>" class="join-item btn btn-md border-none transition-all duration-300 <?= $i === $page ? 'bg-primary text-primary-content font-black shadow-lg shadow-primary/20 scale-110 z-10' : 'bg-transparent hover:bg-base-200' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        <?php if($end < $total_pages): ?>
                            <?php if($end < $total_pages - 1): ?><button class="join-item btn btn-md border-none bg-transparent btn-disabled opacity-30">...</button><?php endif; ?>
                            <a href="?tab=<?= $active_tab ?>&page=<?= $total_pages ?>" class="join-item btn btn-md border-none bg-transparent hover:bg-base-200"><?= $total_pages ?></a>
                        <?php endif; ?>
                        <a href="?tab=<?= $active_tab ?>&page=<?= min($total_pages, $page+1) ?>" class="join-item btn btn-md border-none bg-transparent hover:bg-base-200 <?= $page >= $total_pages ? 'btn-disabled opacity-30' : '' ?>">
                            <i class="fa-solid fa-chevron-right text-xs"></i>
                        </a>
                    </div>
                </div>
                    <?php endif; ?>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</div>
</div>

<?php // db connection cleaned up by app flow ?>
