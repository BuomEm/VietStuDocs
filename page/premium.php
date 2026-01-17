<?php
// Include Error Handler First
require_once __DIR__ . '/../includes/error_handler.php';

session_start();


require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../config/premium.php';
require_once '../config/settings.php'; 

// redirectIfNotLoggedIn(); // Removed to allow guest access

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? getCurrentUserId() : null;
$user_info = $is_logged_in ? getUserInfo($user_id) : null;
$is_premium = $is_logged_in ? isPremium($user_id) : false;
$premium_info = $is_logged_in ? getPremiumInfo($user_id) : null;

// Overwrite connection check if needed for navbar which uses $conn
if(!isset($conn) && isset($VSD)) {
    $conn = $VSD->conn;
}

// Calculate Premium expiration countdown
$days_remaining = null;
$show_expiration_warning = false;

if($is_premium && $premium_info) {
    if (isset($premium_info['end_date'])) {
        try {
            $end_date = new DateTime($premium_info['end_date']);
            $now = new DateTime();
            
            if ($end_date > $now) {
                $interval = $now->diff($end_date);
                $days_remaining = $interval->days;
                
                if($days_remaining < 7) {
                    $show_expiration_warning = true;
                }
            } else {
                $is_premium = false; 
            }
        } catch (Exception $e) {}
    }
}

$page_title = 'Nâng cấp Premium';
include '../includes/head.php'; 
?>
<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(255, 255, 255, 0.2);
        --vsd-red: #991b1b;
        --red-gradient: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%);
    }
    
    [data-theme="dark"] {
        --glass-bg: rgba(15, 23, 42, 0.7);
        --glass-border: rgba(255, 255, 255, 0.1);
    }

    .premium-page-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px 24px;
    }

    .premium-hero {
        text-align: center;
        margin-bottom: 60px;
        position: relative;
    }

    .crown-glow {
        font-size: 5rem;
        margin-bottom: 24px;
        background: var(--red-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        filter: drop-shadow(0 0 20px rgba(153, 27, 27, 0.3));
        display: inline-block;
        animation: floating 3s ease-in-out infinite;
    }

    @keyframes floating {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }

    .premium-title {
        font-size: clamp(2.5rem, 6vw, 4rem);
        font-weight: 1000;
        letter-spacing: -0.05em;
        margin-bottom: 16px;
        line-height: 1;
        background: linear-gradient(135deg, oklch(var(--bc)) 0%, oklch(var(--bc) / 0.7) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .premium-subtitle {
        font-size: 1.1rem;
        font-weight: 600;
        color: oklch(var(--bc) / 0.5);
        max-width: 600px;
        margin: 0 auto;
        letter-spacing: -0.01em;
    }

    /* Grid Layout */
    .premium-grid {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 32px;
        align-items: start;
    }

    @media (max-width: 1024px) {
        .premium-grid {
            grid-template-columns: 1fr;
        }
    }

    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(30px);
        -webkit-backdrop-filter: blur(30px);
        border: 1px solid var(--glass-border);
        border-radius: 2.5rem;
        padding: 40px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05);
    }

    /* Benefits Section */
    .benefit-item {
        display: flex;
        gap: 20px;
        padding: 24px;
        border-radius: 1.5rem;
        background: oklch(var(--b2) / 0.3);
        border: 1px solid oklch(var(--bc) / 0.05);
        transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
    }

    .benefit-item:hover {
        background: oklch(var(--b2) / 0.5);
        transform: translateX(10px);
        border-color: var(--vsd-red);
    }

    .benefit-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
        background: rgba(153, 27, 27, 0.1);
        color: var(--vsd-red);
    }

    /* Pricing Card */
    .pricing-card {
        border: 2px solid rgba(153, 27, 27, 0.2);
        position: relative;
        overflow: hidden;
    }

    .pricing-card::after {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: conic-gradient(transparent, rgba(153, 27, 27, 0.3), transparent 30%);
        animation: rotate 6s linear infinite;
        z-index: -1;
    }

    @keyframes rotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .pricing-content {
        background: var(--glass-bg);
        border-radius: 2.4rem;
        padding: 40px;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .price-tag {
        font-size: 3.5rem;
        font-weight: 1000;
        color: var(--vsd-red);
        margin: 24px 0;
        letter-spacing: -2px;
    }

    .price-tag span {
        font-size: 1rem;
        color: oklch(var(--bc) / 0.5);
        letter-spacing: normal;
        margin-left: 4px;
    }

    .btn-premium-buy {
        width: 100%;
        height: 64px;
        border-radius: 1.25rem;
        background: var(--vsd-red);
        color: white;
        font-weight: 900;
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        box-shadow: 0 20px 40px -10px rgba(153, 27, 27, 0.4);
        transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        margin-top: 24px;
        border: none;
        cursor: pointer;
    }

    .btn-premium-buy:hover {
        transform: translateY(-4px);
        box-shadow: 0 30px 60px -12px rgba(153, 27, 27, 0.5);
        filter: brightness(1.1);
    }

    /* Status Header */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 18px;
        border-radius: 100px;
        background: oklch(var(--b2) / 0.5);
        border: 1px solid oklch(var(--bc) / 0.1);
        font-size: 10px;
        font-weight: 1000;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        margin-bottom: 24px;
    }

    .status-badge.active {
        background: var(--red-gradient);
        color: white;
        border: none;
        box-shadow: 0 10px 20px -5px rgba(153, 27, 27, 0.4);
    }

    /* Payment Modal Premium - COMPACT REDESIGN */
    .modal-box-vsd {
        background: var(--glass-bg) !important;
        backdrop-filter: blur(50px) !important;
        -webkit-backdrop-filter: blur(50px) !important;
        border: 1px solid var(--glass-border) !important;
        border-radius: 3rem !important;
        padding: 0 !important;
        max-width: 550px !important;
        overflow: visible !important;
        box-shadow: 0 50px 100px -20px rgba(0,0,0,0.3) !important;
    }

    .modal-header-vsd {
        padding: 50px 40px 24px;
        text-align: center;
    }

    .modal-content-vsd {
        padding: 0 40px 40px;
    }

    /* New Grid Layout for Info */
    .payment-details-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
        padding: 24px;
        background: oklch(var(--bc) / 0.03);
        border-radius: 2rem;
        border: 1px solid oklch(var(--bc) / 0.05);
        margin-bottom: 24px;
    }

    @media (max-width: 500px) {
        .payment-details-grid {
            grid-template-columns: 1fr;
        }
    }

    .info-block {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .info-block.full-width {
        grid-column: span 2;
        border-top: 1px solid oklch(var(--bc) / 0.05);
        padding-top: 16px;
        margin-top: 8px;
    }

    @media (max-width: 500px) {
        .info-block.full-width {
            grid-column: span 1;
        }
    }

    .info-label {
        font-size: 9px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: oklch(var(--bc) / 0.4);
    }

    .info-value {
        font-size: 13px;
        font-weight: 800;
        color: oklch(var(--bc));
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .info-value.highlight {
        color: var(--vsd-red);
        font-size: 1.25rem;
    }

    .gradient-text-red {
        background: var(--red-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* Floating Icon */
    .modal-icon-top {
        position: absolute;
        top: -36px;
        left: 50%;
        transform: translateX(-50%);
        width: 72px;
        height: 72px;
        background: var(--red-gradient);
        border-radius: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        color: white;
        box-shadow: 0 15px 35px -5px rgba(153, 27, 27, 0.5);
        z-index: 100;
    }

    /* Alert Styling */
    .vsd-alert-compact {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        background: rgba(153, 27, 27, 0.05);
        border: 1px solid rgba(153, 27, 27, 0.1);
        border-radius: 1rem;
        margin-bottom: 24px;
    }

    .vsd-alert-compact i {
        color: var(--vsd-red);
    }

    .vsd-alert-compact span {
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--vsd-red);
        opacity: 0.8;
    }
</style>

<body class="bg-base-100">
    <?php include '../includes/sidebar.php'; ?>

    <div class="drawer-content flex flex-col min-h-screen">
        <?php include '../includes/navbar.php'; ?>

        <main class="flex-1">
            <div class="premium-page-container">
                
                <div class="premium-hero">
                    <div class="crown-glow">
                        <i class="fa-solid fa-crown"></i>
                    </div>
                    <h1 class="premium-title">Nâng cấp Premium</h1>
                    <p class="premium-subtitle">Mở khóa toàn bộ kho tài liệu khổng lồ và tận hưởng tốc độ tải xuống cực nhanh không giới hạn.</p>
                </div>

                <?php if(isset($_GET['success'])): ?>
                    <div class="glass-card mb-8 max-w-2xl mx-auto border-l-4 border-success animate-in fade-in slide-in-from-top-4 duration-500">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-success/20 text-success flex items-center justify-center text-xl shrink-0">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                            <div>
                                <h3 class="font-black text-lg">Tuyệt vời! Nâng cấp thành công</h3>
                                <p class="text-sm opacity-60 font-bold">Tài khoản của bạn hiện đã là Premium. Chào mừng bạn!</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if($show_expiration_warning && $is_premium): ?>
                    <div class="glass-card mb-8 max-w-2xl mx-auto border-l-4 border-warning">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-warning/20 text-warning flex items-center justify-center text-xl shrink-0">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-black text-lg">Gói Premium sắp hết hạn</h3>
                                <p class="text-sm opacity-60 font-bold">Gói thành viên của bạn sẽ kết thúc sau <?= $days_remaining ?> ngày. Gia hạn ngay để duy trì quyền lợi.</p>
                            </div>
                            <a href="#pricing" class="btn btn-warning btn-sm rounded-xl font-bold">Gia hạn ngay</a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="premium-grid">
                    
                    <!-- Left: Why Premium -->
                    <div class="space-y-8">
                        
                        <div class="glass-card">
                            <div class="flex items-center gap-4 mb-8">
                                <div class="w-16 h-16 rounded-2xl ring-4 ring-base-100 shadow-xl overflow-hidden bg-primary/10 flex items-center justify-center">
                                    <?php if($is_logged_in && !empty($user_info['avatar']) && file_exists('../uploads/avatars/' . $user_info['avatar'])): ?>
                                        <img src="../uploads/avatars/<?= $user_info['avatar'] ?>" class="w-full h-full object-cover" />
                                    <?php else: ?>
                                        <i class="fa-solid fa-user text-2xl text-primary"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h3 class="font-black text-xl"><?= $is_logged_in ? htmlspecialchars($user_info['username']) : 'Người Dùng' ?></h3>
                                    <?php if($is_logged_in): ?>
                                        <?php if($is_premium): ?>
                                            <div class="status-badge active mt-1">
                                                <i class="fa-solid fa-crown"></i> Thành viên Premium
                                            </div>
                                        <?php else: ?>
                                            <div class="status-badge mt-1">Thành viên Thường</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="text-xs font-bold opacity-50 mt-1 max-w-[200px] leading-tight">
                                            Tham gia cộng đồng để mở khóa toàn bộ tính năng
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="benefit-item">
                                    <div class="benefit-icon">
                                        <i class="fa-solid fa-unlock"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-black text-[10px] uppercase tracking-wider mb-1 opacity-70">Truy cập toàn bộ</h4>
                                        <p class="text-[11px] opacity-40 font-bold leading-relaxed">Đọc và tải xuống mọi thứ trong thư viện của chúng tôi.</p>
                                    </div>
                                </div>

                                <div class="benefit-item">
                                    <div class="benefit-icon">
                                        <i class="fa-solid fa-bolt"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-black text-[10px] uppercase tracking-wider mb-1 opacity-70">Tốc độ cực cao</h4>
                                        <p class="text-[11px] opacity-40 font-bold leading-relaxed">Không giới hạn tốc độ. Tải về trong vài giây.</p>
                                    </div>
                                </div>

                                <div class="benefit-item">
                                    <div class="benefit-icon">
                                        <i class="fa-solid fa-eye-slash"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-black text-[10px] uppercase tracking-wider mb-1 opacity-70">Không quảng cáo</h4>
                                        <p class="text-[11px] opacity-40 font-bold leading-relaxed">Trải nghiệm học tập sạch sẽ, không bị phân tâm.</p>
                                    </div>
                                </div>

                                <div class="benefit-item">
                                    <div class="benefit-icon">
                                        <i class="fa-solid fa-headset"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-black text-[10px] uppercase tracking-wider mb-1 opacity-70">Hỗ trợ VIP</h4>
                                        <p class="text-[11px] opacity-40 font-bold leading-relaxed">Được ưu tiên hỗ trợ từ đội ngũ chuyên trách.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- FAQ -->
                        <div class="glass-card p-8">
                            <h3 class="font-black text-xs uppercase tracking-[0.25em] mb-6 border-b border-base-content/5 pb-4 opacity-40">
                                <i class="fa-solid fa-circle-question mr-2"></i> Câu hỏi thường gặp
                            </h3>
                            <div class="space-y-4">
                                <div class="collapse collapse-plus bg-base-200/30 rounded-2xl border border-base-content/5 transition-all">
                                    <input type="checkbox" /> 
                                    <div class="collapse-title text-sm font-black">Tôi có thể hủy bất cứ lúc nào không?</div>
                                    <div class="collapse-content text-xs opacity-50 font-bold">Có, Premium là gói mua một lần hoặc gia hạn tùy chọn. Bạn giữ quyền lợi cho đến hết thời hạn.</div>
                                </div>
                                <div class="collapse collapse-plus bg-base-200/30 rounded-2xl border border-base-content/5 transition-all">
                                    <input type="checkbox" /> 
                                    <div class="collapse-title text-sm font-black">Các hình thức thanh toán được hỗ trợ?</div>
                                    <div class="collapse-content text-xs opacity-50 font-bold">Chúng tôi hỗ trợ chuyển khoản ngân hàng, MoMo và các loại thẻ nội địa. Kích hoạt ngay lập tức qua Admin.</div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Right: Pricing Table -->
                    <div id="pricing">
                        <div class="pricing-card glass-card !p-1">
                            <div class="pricing-content">
                                <div class="badge badge-primary font-black text-[10px] tracking-widest p-4 rounded-xl mb-4">PHỔ BIẾN NHẤT</div>
                                
                                <h2 class="font-black text-2xl uppercase tracking-tighter opacity-80">Gói Vàng Hàng Tháng</h2>
                                
                                <div class="price-tag">
                                    29.000<span>VNĐ</span>
                                </div>
                                
                                <p class="text-xs font-bold opacity-30 mb-8 max-w-[250px]">
                                    Đầy đủ quyền lợi Premium trong 30 ngày. Hoàn hảo cho các dự án ngắn hạn.
                                </p>

                                <div class="w-full space-y-4 mb-8 text-left px-4">
                                    <div class="flex items-center gap-3 text-xs font-black">
                                        <i class="fa-solid fa-check text-success"></i>
                                        <span>Tải xuống tốc độ cao</span>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs font-black">
                                        <i class="fa-solid fa-check text-success"></i>
                                        <span>Xem trước không giới hạn</span>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs font-black">
                                        <i class="fa-solid fa-check text-success"></i>
                                        <span>Truy cập nội dung xác thực</span>
                                    </div>
                                </div>
                                
                                <?php if($is_logged_in): ?>
                                    <button type="button" onclick="showPaymentModal()" class="btn-premium-buy">
                                        Nâng cấp Ngay <i class="fa-solid fa-arrow-right"></i>
                                    </button>
                                <?php else: ?>
                                    <a href="/login?redirect=/premium" class="btn-premium-buy no-underline">
                                        Đăng nhập để nâng cấp <i class="fa-solid fa-right-to-bracket"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <div class="mt-12 pt-8 border-t border-base-content/5 w-full">
                                    <h4 class="font-black text-[10px] uppercase opacity-30 tracking-[0.2em] mb-4">Sắp ra mắt</h4>
                                    <div class="flex justify-between items-center bg-base-200/30 p-4 rounded-2xl opacity-40">
                                        <span class="font-black text-xs">GÓI VĨNH VIỄN</span>
                                        <span class="font-black text-xs">99K</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Payment Modal - REDESIGNED FOR COMPACTNESS AND BRAND RED -->
                <dialog id="payment-modal" class="modal">
                    <div class="modal-box modal-box-vsd">
                        <div class="modal-icon-top">
                            <i class="fa-solid fa-receipt"></i>
                        </div>

                        <div class="modal-header-vsd">
                            <h3 class="font-black text-3xl uppercase tracking-tighter gradient-text-red">Thanh toán</h3>
                            <p class="text-[10px] opacity-40 font-black uppercase tracking-widest mt-1">Vui lòng chuyển khoản chính xác nội dung</p>
                        </div>
                        
                        <div class="modal-content-vsd">
                            <div class="payment-details-grid">
                                <!-- Group 1: Bank & Number -->
                                <div class="info-block">
                                    <span class="info-label">Ngân hàng</span>
                                    <span class="info-value">MB Bank</span>
                                </div>
                                <div class="info-block">
                                    <span class="info-label">Số tài khoản</span>
                                    <span class="info-value font-mono">
                                        999999999
                                        <button class="btn btn-ghost btn-xs btn-circle" onclick="navigator.clipboard.writeText('999999999'); showAlert('Đã sao chép số tài khoản', 'success')">
                                            <i class="fa-regular fa-copy"></i>
                                        </button>
                                    </span>
                                </div>

                                <!-- Group 2: Receiver & Price -->
                                <div class="info-block">
                                    <span class="info-label">Người nhận</span>
                                    <span class="info-value">ADMIN</span>
                                </div>
                                <div class="info-block">
                                    <span class="info-label">Số tiền</span>
                                    <span class="info-value highlight">29.000 VNĐ</span>
                                </div>

                                <!-- Group 3: Reference (Full Width) -->
                                <div class="info-block full-width text-center">
                                    <span class="info-label">Nội dung chuyển khoản</span>
                                    <span class="info-value font-black text-xl text-center justify-center tracking-wider">
                                        PREMIUM <?= $user_id ?>
                                        <button class="btn btn-ghost btn-md btn-circle bg-red-800/10 hover:bg-red-800/20 ml-2" onclick="navigator.clipboard.writeText('PREMIUM <?= $user_id ?>'); showAlert('Đã sao chép nội dung', 'success')">
                                            <i class="fa-regular fa-copy"></i>
                                        </button>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- <div class="vsd-alert-compact">
                                <i class="fa-solid fa-circle-info"></i>
                                <span>Kích hoạt tự động ngay sau khi nhận tiền</span>
                            </div> -->

                            <div class="grid grid-cols-2 gap-4">
                                <button onclick="this.closest('dialog').close()" class="btn btn-ghost rounded-2xl h-14 font-black text-[10px] tracking-[0.2em] opacity-30">ĐÓNG</button>
                                <a href="https://t.me/admin" target="_blank" class="btn bg-red-800 hover:bg-red-900 border-none text-white rounded-2xl h-14 font-black text-[10px] tracking-[0.2em] shadow-xl shadow-red-900/20">
                                    <i class="fa-brands fa-telegram text-lg mr-1"></i> ADMIN
                                </a>
                            </div>
                        </div>
                    </div>
                    <form method="dialog" class="modal-backdrop">
                        <button>close</button>
                    </form>
                </dialog>

                <script>
                    function showPaymentModal() {
                        const modal = document.getElementById('payment-modal');
                        if (modal) {
                            modal.showModal();
                        }
                    }

                    function closePaymentModal() {
                        const modal = document.getElementById('payment-modal');
                        if (modal) {
                            modal.close();
                        }
                    }
                </script>

            </div>
        </main>

        <?php include '../includes/footer.php'; ?>
    </div>
</body>
</html>