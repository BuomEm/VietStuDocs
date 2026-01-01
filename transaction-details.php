<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit();
}

require_once 'config/db.php';
require_once 'config/function.php';
require_once 'config/auth.php';
require_once 'config/points.php';

$user_id = getCurrentUserId();
$page_title = "Chi Tiết Giao Dịch - DocShare";
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
<?php include 'includes/head.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<style>
    .detail-row {
        border-bottom: 1px solid hsl(var(--bc) / 0.1);
        padding: 16px 0;
    }
    .detail-row:last-child {
        border-bottom: none;
    }
    
    .receipt-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 20px;
        padding: 32px;
        box-shadow: 0 20px 60px rgba(102, 126, 234, 0.3);
    }
</style>

<div class="drawer-content flex flex-col">
    <?php include 'includes/navbar.php'; ?>
    
    <main class="flex-1 p-6 bg-base-200">
        <!-- Breadcrumb -->
        <div class="text-sm breadcrumbs mb-6">
            <ul>
                <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
                <li><a href="history.php"><i class="fa-solid fa-clock-rotate-left"></i> Lịch Sử</a></li>
                <li>Chi Tiết Giao Dịch</li>
            </ul>
        </div>

        <div class="max-w-4xl mx-auto">
            <!-- Receipt Header -->
            <div class="receipt-card mb-8">
                <div class="text-center mb-6">
                    <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fa-solid fa-receipt text-4xl"></i>
                    </div>
                    <h1 class="text-3xl font-bold mb-2">Hóa Đơn Giao Dịch</h1>
                    <p class="text-white/80">DocShare Platform</p>
                </div>
                
                <div class="bg-white/10 rounded-xl p-6 backdrop-blur-sm">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <div class="text-white/70 text-sm mb-1">Mã Giao Dịch</div>
                            <div class="font-bold text-lg font-mono">
                                #<?= strtoupper($type) ?>-<?= str_pad($transaction_id, 6, '0', STR_PAD_LEFT) ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-white/70 text-sm mb-1">Ngày Giao Dịch</div>
                            <div class="font-bold text-lg">
                                <?= date('d/m/Y H:i', strtotime($transaction['created_at'] ?? $transaction['purchased_at'] ?? 'now')) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction Details -->
            <div class="card bg-base-100 shadow-xl mb-8">
                <div class="card-body">
                    <h2 class="card-title text-2xl mb-6">
                        <i class="fa-solid fa-info-circle text-primary"></i>
                        Chi Tiết Giao Dịch
                    </h2>
                    
                    <?php if($type === 'points'): ?>
                        <!-- Points Transaction Details -->
                        <div class="space-y-4">
                            <div class="detail-row flex justify-between items-center">
                                <span class="text-base-content/70">Loại Giao Dịch</span>
                                <span class="font-bold badge badge-lg <?= $transaction['transaction_type'] === 'earn' ? 'badge-success' : 'badge-error' ?>">
                                    <?= $transaction['transaction_type'] === 'earn' ? 'Nhận Điểm' : 'Tiêu Điểm' ?>
                                </span>
                            </div>
                            
                            <div class="detail-row flex justify-between items-center">
                                <span class="text-base-content/70">Số Điểm</span>
                                <span class="text-3xl font-bold <?= $transaction['transaction_type'] === 'earn' ? 'text-success' : 'text-error' ?>">
                                    <?= $transaction['transaction_type'] === 'earn' ? '+' : '-' ?><?= number_format($transaction['points']) ?>
                                </span>
                            </div>
                            
                            <div class="detail-row flex justify-between items-start">
                                <span class="text-base-content/70">Mô Tả</span>
                                <span class="font-semibold text-right max-w-md">
                                    <?= htmlspecialchars($transaction['reason'] ?? '') ?>
                                </span>
                            </div>
                            
                            <?php if(isset($transaction['related_document'])): ?>
                                <div class="detail-row flex justify-between items-center">
                                    <span class="text-base-content/70">Tài Liệu Liên Quan</span>
                                    <a href="view.php?id=<?= $transaction['document_id'] ?>" class="link link-primary font-semibold">
                                        <?= htmlspecialchars($transaction['related_document']['original_name']) ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <div class="detail-row flex justify-between items-center">
                                <span class="text-base-content/70">Thời Gian</span>
                                <span class="font-semibold">
                                    <?= date('d/m/Y H:i:s', strtotime($transaction['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                        
                    <?php elseif($type === 'purchase'): ?>
                        <!-- Purchase Transaction Details -->
                        <div class="space-y-4">
                            <div class="detail-row">
                                <div class="flex items-center gap-4">
                                    <div class="w-24 h-24 bg-base-200 rounded-lg overflow-hidden flex-shrink-0">
                                        <?php if($transaction['thumbnail']): ?>
                                            <img src="uploads/<?= htmlspecialchars($transaction['thumbnail']) ?>" 
                                                 alt="Thumbnail" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center">
                                                <i class="fa-regular fa-file text-3xl text-base-content/30"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-bold text-xl mb-1">
                                            <?= htmlspecialchars($transaction['original_name']) ?>
                                        </h3>
                                        <p class="text-base-content/70">
                                            Người bán: <?= htmlspecialchars($transaction['seller_name'] ?? 'N/A') ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="detail-row flex justify-between items-center">
                                <span class="text-base-content/70">Giá Mua</span>
                                <span class="text-3xl font-bold text-primary">
                                    <?= number_format($transaction['points_spent']) ?> điểm
                                </span>
                            </div>
                            
                            <div class="detail-row flex justify-between items-center">
                                <span class="text-base-content/70">Thời Gian Mua</span>
                                <span class="font-semibold">
                                    <?= date('d/m/Y H:i:s', strtotime($transaction['purchased_at'])) ?>
                                </span>
                            </div>
                            
                            <div class="detail-row flex justify-between items-center">
                                <span class="text-base-content/70">Trạng Thái</span>
                                <span class="badge badge-success badge-lg">Thành Công</span>
                            </div>
                        </div>
                        
                    <?php elseif($type === 'premium'): ?>
                        <!-- Premium Transaction Details -->
                        <div class="space-y-4">
                            <div class="detail-row flex justify-between items-center">
                                <span class="text-base-content/70">Loại Gói</span>
                                <span class="font-bold badge badge-warning badge-lg">
                                    <i class="fa-solid fa-crown mr-1"></i>
                                    Premium <?= ucfirst($transaction['transaction_type']) ?>
                                </span>
                            </div>
                            
                            <div class="detail-row flex justify-between items-center">
                                <span class="text-base-content/70">Số Tiền</span>
                                <span class="text-3xl font-bold text-primary">
                                    <?= number_format($transaction['amount']) ?>đ
                                </span>
                            </div>
                            
                            <div class="detail-row flex justify-between items-center">
                                <span class="text-base-content/70">Trạng Thái</span>
                                <?php 
                                    $status_classes = [
                                        'completed' => 'badge-success',
                                        'pending' => 'badge-warning',
                                        'failed' => 'badge-error'
                                    ];
                                    $status_labels = [
                                        'completed' => 'Thành Công',
                                        'pending' => 'Đang Xử Lý',
                                        'failed' => 'Thất Bại'
                                    ];
                                ?>
                                <span class="badge <?= $status_classes[$transaction['status']] ?? 'badge-ghost' ?> badge-lg">
                                    <?= $status_labels[$transaction['status']] ?? $transaction['status'] ?>
                                </span>
                            </div>
                            
                            <div class="detail-row flex justify-between items-center">
                                <span class="text-base-content/70">Thời Gian</span>
                                <span class="font-semibold">
                                    <?= date('d/m/Y H:i:s', strtotime($transaction['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actions -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title mb-4">
                        <i class="fa-solid fa-bolt text-warning"></i>
                        Thao Tác
                    </h2>
                    <div class="flex flex-wrap gap-3">
                        <button onclick="window.print()" class="btn btn-primary">
                            <i class="fa-solid fa-print"></i>
                            In Hóa Đơn
                        </button>
                        
                        <?php if($type === 'purchase'): ?>
                            <a href="view.php?id=<?= $transaction['document_id'] ?>" class="btn btn-success">
                                <i class="fa-regular fa-eye"></i>
                                Xem Tài Liệu
                            </a>
                            <a href="view.php?id=<?= $transaction['document_id'] ?>&download=1" class="btn btn-info">
                                <i class="fa-solid fa-download"></i>
                                Tải Xuống
                            </a>
                        <?php endif; ?>
                        
                        <a href="history.php" class="btn btn-ghost">
                            <i class="fa-solid fa-arrow-left"></i>
                            Quay Lại
                        </a>
                    </div>
                </div>
            </div>

            <!-- Support Notice -->
            <div class="alert alert-info mt-6">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <h3 class="font-bold">Cần Hỗ Trợ?</h3>
                    <div class="text-xs">
                        Nếu có bất kỳ vấn đề gì với giao dịch này, vui lòng liên hệ 
                        <a href="mailto:support@docshare.com" class="link link-primary">support@docshare.com</a>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</div>
</div>

<script>
    // Print styling
    window.addEventListener('beforeprint', function() {
        document.body.classList.add('printing');
    });
    
    window.addEventListener('afterprint', function() {
        document.body.classList.remove('printing');
    });
</script>

<style media="print">
    @media print {
        .drawer-side, .navbar, .btn, .alert, footer {
            display: none !important;
        }
        .receipt-card {
            box-shadow: none !important;
        }
        body {
            background: white !important;
        }
    }
</style>

?>
