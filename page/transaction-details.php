<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit();
}

require_once '../config/db.php';
require_once '../config/function.php';
require_once '../config/auth.php';
require_once '../config/points.php';

$user_id = getCurrentUserId();
$page_title = "Chi Tiết Giao Dịch - VietStuDocs";
$current_page = 'history';

// Get transaction ID
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$type = $_GET['type'] ?? 'points'; // points, purchase, premium

$transaction = null;

// Get transaction details based on type
if($type === 'points') {
    $query = "SELECT * FROM point_transactions WHERE id = $transaction_id AND user_id = $user_id";
    $transaction = db_get_row($query);
    
    if($transaction) {
        // Get related document if exists
        if($transaction['document_id']) {
            $doc_query = "SELECT * FROM documents WHERE id = " . $transaction['document_id'];
            $transaction['related_document'] = db_get_row($doc_query);
        }
    }
} 
elseif($type === 'purchase') {
    $query = "
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
        WHERE ds.id = $transaction_id AND ds.buyer_user_id = $user_id
    ";
    $transaction = db_get_row($query);
}
elseif($type === 'premium') {
    // Check if table exists
    if(db_table_exists('transactions')) {
        $query = "SELECT * FROM transactions WHERE id = $transaction_id AND user_id = $user_id";
        $transaction = db_get_row($query);
    } else {
        $transaction = null;
    }
}

if(!$transaction) {
    header("Location: history.php");
    exit();
}

?>
<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="drawer-content flex flex-col min-h-screen bg-base-200/50">
    <?php include '../includes/navbar.php'; ?>
    
    <main class="flex-1 p-4 lg:p-8">
        <!-- Breadcrumb & Header -->
        <div class="max-w-4xl mx-auto mb-10">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <div class="text-[10px] font-black uppercase tracking-[0.2em] text-primary mb-2">Transaction Receipt</div>
                    <h1 class="text-4xl font-black text-base-content flex items-center gap-4">
                        Chi Tiết Hóa Đơn
                        <div class="badge badge-lg bg-primary/10 text-primary border-none font-black text-xs h-8">
                            #<?= strtoupper($type) ?>-<?= str_pad($transaction_id, 6, '0', STR_PAD_LEFT) ?>
                        </div>
                    </h1>
                </div>
                <a href="history.php" class="btn btn-ghost bg-base-100 hover:bg-base-200 rounded-2xl px-6 gap-2 font-black text-xs shadow-sm border border-base-200">
                    <i class="fa-solid fa-arrow-left text-sm opacity-40"></i>
                    QUAY LẠI LỊCH SỬ
                </a>
            </div>
        </div>

        <div class="max-w-4xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Main Receipt Card -->
                <div class="lg:col-span-8 space-y-8">
                    <div class="bg-base-100 rounded-[3rem] p-10 md:p-12 border border-base-200 shadow-xl shadow-base-200/50 relative overflow-hidden">
                        <div class="absolute -right-20 -top-20 w-80 h-80 bg-primary/5 rounded-full blur-3xl"></div>
                        
                        <!-- Receipt Top -->
                        <div class="relative z-10 flex flex-col items-center text-center mb-12">
                            <div class="w-24 h-24 rounded-[2rem] bg-primary text-white flex items-center justify-center shadow-2xl shadow-primary/30 mb-6 rotate-3 hover:rotate-0 transition-transform duration-500">
                                <i class="fa-solid fa-receipt text-4xl"></i>
                            </div>
                            <h2 class="text-2xl font-black text-base-content tracking-tight mb-2">VietStuDocs Receipt</h2>
                            <p class="text-sm font-bold opacity-30 uppercase tracking-[0.3em]">Hóa đơn giao dịch chính thức</p>
                            <div class="mt-6 w-full max-w-[200px] h-1 bg-gradient-to-r from-transparent via-base-200 to-transparent"></div>
                        </div>

                        <!-- Data Grid -->
                        <div class="relative z-10 space-y-8">
                            <div class="grid grid-cols-2 gap-8 pb-8 border-b border-base-200">
                                <div>
                                    <span class="text-[10px] font-black uppercase tracking-widest text-base-content/30 block mb-2">Ngày thực hiện</span>
                                    <h4 class="text-base font-black"><?= date('d, M Y', strtotime($transaction['created_at'] ?? $transaction['purchased_at'] ?? 'now')) ?></h4>
                                    <p class="text-xs font-bold opacity-40 mt-0.5"><?= date('H:i:s A', strtotime($transaction['created_at'] ?? $transaction['purchased_at'] ?? 'now')) ?></p>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-base-content/30 block mb-2">Trạng thái</span>
                                    <div class="badge badge-lg bg-success/10 text-success border-none font-black text-[10px] uppercase tracking-wider h-7 px-4">HOÀN TẤT</div>
                                </div>
                            </div>

                            <!-- Specific Details based on Type -->
                            <?php if($type === 'purchase'): ?>
                                <div class="py-2">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-base-content/30 block mb-4">Thông tin sản phẩm</span>
                                    <div class="group flex items-center gap-6 p-6 rounded-[2.5rem] bg-base-200/30 border border-base-200 hover:bg-base-100 hover:shadow-xl transition-all duration-500">
                                        <div class="w-16 h-20 rounded-xl bg-base-300 overflow-hidden shrink-0 shadow-inner">
                                            <?php if($transaction['thumbnail']): ?>
                                                <img src="../uploads/<?= htmlspecialchars($transaction['thumbnail']) ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center text-primary/20">
                                                    <i class="fa-solid fa-file-invoice text-3xl"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="font-black text-base text-base-content leading-tight mb-1 group-hover:text-primary transition-colors"><?= htmlspecialchars($transaction['original_name']) ?></h3>
                                            <p class="text-xs font-bold opacity-40 uppercase tracking-wider">Người bán: <?= htmlspecialchars($transaction['seller_name'] ?? 'VietStuDocs') ?></p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <div class="text-lg font-black text-warning"><?= number_format($transaction['points_spent']) ?></div>
                                            <div class="text-[10px] font-black opacity-30 uppercase">ĐIỂM</div>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif($type === 'points'): ?>
                                <div class="py-2">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-base-content/30 block mb-4">Chi tiết biến động số dư</span>
                                    <div class="flex flex-col gap-4">
                                        <div class="flex justify-between items-center p-6 rounded-[2rem] bg-base-200/30 border border-base-200">
                                            <div class="flex items-center gap-4">
                                                <div class="w-12 h-12 rounded-full <?= $transaction['transaction_type'] === 'earn' ? 'bg-success/10 text-success' : 'bg-error/10 text-error' ?> flex items-center justify-center">
                                                    <i class="fa-solid <?= $transaction['transaction_type'] === 'earn' ? 'fa-plus' : 'fa-minus' ?> text-lg"></i>
                                                </div>
                                                <div>
                                                    <h4 class="font-black text-base capitalize"><?= $transaction['transaction_type'] === 'earn' ? 'Cộng điểm' : 'Trừ điểm' ?></h4>
                                                    <p class="text-xs font-medium opacity-50"><?= htmlspecialchars($transaction['reason'] ?? 'Giao dịch hệ thống') ?></p>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-2xl font-black tracking-tighter <?= $transaction['transaction_type'] === 'earn' ? 'text-success' : 'text-error' ?>">
                                                    <?= $transaction['transaction_type'] === 'earn' ? '+' : '-' ?><?= number_format($transaction['points']) ?>
                                                </div>
                                                <div class="text-[10px] font-black opacity-30 uppercase tracking-widest">ĐIỂM VSD</div>
                                            </div>
                                        </div>
                                        
                                        <?php if(isset($transaction['related_document'])): ?>
                                            <div class="p-6 rounded-[2rem] bg-primary/5 border border-primary/10 flex items-center justify-between">
                                                <div class="flex items-center gap-4">
                                                    <i class="fa-solid fa-link text-primary/40"></i>
                                                    <span class="text-sm font-bold opacity-60">Liên quan đến tài liệu:</span>
                                                </div>
                                                <a href="view.php?id=<?= $transaction['document_id'] ?>" class="text-sm font-black text-primary hover:underline"><?= htmlspecialchars($transaction['related_document']['original_name']) ?></a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php elseif($type === 'premium'): ?>
                                <div class="py-2">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-base-content/30 block mb-4">Gói dịch vụ đăng ký</span>
                                    <div class="p-8 rounded-[2.5rem] bg-gradient-to-br from-warning/10 to-orange-500/10 border border-warning/20 relative overflow-hidden group">
                                         <i class="fa-solid fa-crown absolute -right-6 -bottom-6 text-9xl text-warning/10 -rotate-12 group-hover:rotate-0 transition-transform duration-700"></i>
                                         <div class="relative z-10 flex justify-between items-center">
                                            <div>
                                                <h3 class="text-2xl font-black text-warning flex items-center gap-3 mb-1">
                                                    PREMIUM <?= strtoupper($transaction['transaction_type']) ?>
                                                    <i class="fa-solid fa-circle-check text-xs"></i>
                                                </h3>
                                                <p class="text-xs font-bold opacity-60">Nâng cấp trải nghiệm không giới hạn</p>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-3xl font-black text-base-content tracking-tighter"><?= number_format($transaction['amount']) ?>đ</div>
                                                <div class="text-[10px] font-black opacity-30 uppercase tracking-[0.2em]">THANH TOÁN TIỀN MẶT</div>
                                            </div>
                                         </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Total Summary Section -->
                            <div class="mt-12 pt-8 border-t-2 border-dashed border-base-200">
                                <div class="flex justify-between items-center mb-4">
                                    <span class="text-sm font-bold opacity-40 uppercase tracking-widest">Tổng cộng</span>
                                    <div class="h-px bg-base-200 flex-1 mx-6 opacity-50"></div>
                                    <div class="text-3xl font-black tracking-tighter text-base-content">
                                        <?php if($type === 'purchase'): ?>
                                            <?= number_format($transaction['points_spent']) ?> <span class="text-lg opacity-30">P</span>
                                        <?php elseif($type === 'points'): ?>
                                            <?= number_format($transaction['points']) ?> <span class="text-lg opacity-30">P</span>
                                        <?php elseif($type === 'premium'): ?>
                                            <?= number_format($transaction['amount']) ?> <span class="text-lg opacity-30">VNĐ</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[10px] font-black opacity-30 uppercase tracking-[0.3em]">Mã xác thực nội bộ</span>
                                    <span class="text-[10px] font-mono opacity-20"><?= bin2hex(random_bytes(16)) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Decorative Zigzag -->
                        <div class="absolute bottom-0 left-0 w-full h-2 bg-repeat-x" style="background-image: radial-gradient(circle at 10px 0px, transparent 12px, white 13px); background-size: 20px 20px;"></div>
                    </div>

                    <!-- Actions Bar -->
                    <div class="flex flex-wrap gap-4 print:hidden">
                        <button onclick="window.print()" class="btn btn-primary btn-lg rounded-2xl px-10 h-16 shadow-xl shadow-primary/20 font-black gap-3 flex-1">
                            <i class="fa-solid fa-print"></i>
                            IN HÓA ĐƠN
                        </button>
                        <?php if($type === 'purchase'): ?>
                            <a href="view.php?id=<?= $transaction['document_id'] ?>" class="btn btn-base-100 btn-lg rounded-2xl px-10 h-16 shadow-xl border border-base-200 font-black gap-3 flex-1">
                                <i class="fa-solid fa-file-arrow-down"></i>
                                TÀI LIỆU CỦA TÔI
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Side Info Cards -->
                <div class="lg:col-span-4 space-y-6">
                    <div class="bg-base-100 rounded-[2.5rem] p-8 border border-base-200 shadow-xl shadow-base-200/30">
                        <h3 class="text-[10px] font-black uppercase tracking-widest text-primary mb-6">Thông tin hỗ trợ</h3>
                        <div class="space-y-6">
                            <div class="flex gap-4">
                                <div class="w-10 h-10 rounded-xl bg-primary/5 text-primary flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-headset"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-black mb-1">Cần giúp đỡ?</h4>
                                    <p class="text-xs font-medium opacity-50 leading-relaxed">Nếu gặp vấn đề với giao dịch này, hãy liên hệ ngay với chúng tôi.</p>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <div class="w-10 h-10 rounded-xl bg-primary/5 text-primary flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-shield-check"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-black mb-1">Giao dịch an toàn</h4>
                                    <p class="text-xs font-medium opacity-50 leading-relaxed">Mọi giao dịch trên VietStuDocs được mã hóa và bảo vệ 24/7.</p>
                                </div>
                            </div>
                        </div>
                        <div class="divider my-6 opacity-30"></div>
                        <a href="mailto:support@vietstudocs.com" class="btn btn-ghost w-full rounded-xl font-black text-xs text-primary hover:bg-primary/5 transition-colors">SUPPORT@VIETSTUDOCS.COM</a>
                    </div>

                    <div id="print-hide-notes" class="bg-primary/5 rounded-[2.5rem] p-8 border border-primary/10 relative overflow-hidden print:hidden">
                        <div class="relative z-10">
                            <h3 class="text-xl font-black text-primary mb-4">Ghi chú quan trọng</h3>
                            <ul class="space-y-3">
                                <li class="flex items-start gap-3">
                                    <i class="fa-solid fa-circle-dot text-[8px] mt-1.5 text-primary opacity-40"></i>
                                    <span class="text-xs font-medium opacity-60 leading-relaxed">Giữ lại mã giao dịch để được hỗ trợ nhanh nhất.</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <i class="fa-solid fa-circle-dot text-[8px] mt-1.5 text-primary opacity-40"></i>
                                    <span class="text-xs font-medium opacity-60 leading-relaxed">Hóa đơn điện tử có giá trị tương đương hóa đơn giấy.</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <i class="fa-solid fa-circle-dot text-[8px] mt-1.5 text-primary opacity-40"></i>
                                    <span class="text-xs font-medium opacity-60 leading-relaxed">Thông tin bảo mật nghiêm ngặt.</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

<style media="print">
    @media print {
        /* Hide UI clutter */
        .drawer-side, .navbar, .btn, .print\:hidden, .breadcrumbs, .mb-10, .divider, .absolute, #print-hide-notes {
            display: none !important;
        }
        
        /* Reset containers - Ensure they don't hide content */
        body, html, .drawer, .drawer-content, main {
            background: #fff !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            height: auto !important;
            overflow: visible !important;
            display: block !important;
            visibility: visible !important;
        }
        
        .max-w-4xl {
            max-w: 100% !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 2rem !important;
            visibility: visible !important;
        }

        /* Restore Grid Layout for Print */
        .grid {
            display: grid !important;
            grid-template-cols: repeat(12, minmax(0, 1fr)) !important;
            gap: 1.5rem !important;
            visibility: visible !important;
        }

        .lg\:col-span-8 { 
            grid-column: span 8 / span 8 !important; 
            display: block !important;
        }
        .lg\:col-span-4 { 
            grid-column: span 4 / span 4 !important;
            display: block !important;
        }

        /* Re-apply Card Aesthetics - Use specific selectors to hide shadows without hiding content */
        .bg-base-100, .bg-primary\/5 {
            background-color: #fff !important;
            border: 1px solid #eee !important;
            border-radius: 2.5rem !important;
            box-shadow: none !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        .lg\:col-span-8 .bg-base-100 {
            padding: 2.5rem !important;
        }

        .lg\:col-span-4 > div:not(.print\:hidden) {
            padding: 1.5rem !important;
            background-color: #fbfbfb !important;
            border: 1px solid #eeeeee !important;
            border-radius: 2rem !important;
        }

        /* Typography Scaling */
        .text-4xl { font-size: 1.75rem !important; }
        .text-3xl { font-size: 1.5rem !important; }
        .text-2xl { font-size: 1.25rem !important; }
        
        /* Strip heavy effects by resetting properties instead of hiding classes */
        .blur-3xl, .shadow-xl, .shadow-2xl, .shadow-base-200\/50, .shadow-inner {
            box-shadow: none !important;
            filter: none !important;
            /* DO NOT USE display: none here */
        }
        
        /* Ensure colors print accurately */
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            box-shadow: none !important;
        }
        
        .rotate-3 { transform: none !important; }
        
        /* Fix Badge Borders */
        .badge {
            border: 1px solid #eee !important;
        }
    }
</style>

</div>
</div>
