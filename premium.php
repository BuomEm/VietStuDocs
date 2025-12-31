<?php
// Include Error Handler First
require_once __DIR__ . '/includes/error_handler.php';

session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: index");
    exit;
}

require_once 'config/db.php';
require_once 'config/auth.php';
require_once 'config/premium.php';
require_once 'config/settings.php'; // Needed for site currency/info if any

redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$is_premium = isPremium($user_id);
$premium_info = getPremiumInfo($user_id);
$verified_docs = getVerifiedDocumentsCount($user_id);

// Calculate Premium expiration countdown
$days_remaining = null;
$show_expiration_warning = false;

if($is_premium && $premium_info) {
    // Check if end_date is valid
    if (isset($premium_info['end_date'])) {
        try {
            $end_date = new DateTime($premium_info['end_date']);
            $now = new DateTime();
            
            if ($end_date > $now) {
                $interval = $now->diff($end_date);
                $days_remaining = $interval->days;
                
                // Show warning if less than 7 days
                if($days_remaining < 7) {
                    $show_expiration_warning = true;
                }
            } else {
                // Expired but maybe status not updated yet
                $is_premium = false; 
            }
        } catch (Exception $e) {
            // Invalid date format, ignore
        }
    }
}

// Handle payment
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['buy_premium'])) {
    // Instead of auto-activating, redirect to payment modal
    // activateMonthlyPremium($user_id);
    
    // Log intent (optional)
    /*
    mysqli_query($conn, "
        INSERT INTO transactions (user_id, amount, transaction_type, status) 
        VALUES ($user_id, 29000, 'monthly', 'pending')
    ");
    */
    
    header("Location: premium?payment=bank_transfer");
    exit;
}

// Do NOT close connection, needed for navbar
// mysqli_close($conn); 
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <?php include 'includes/head.php'; ?>
    <title>Nâng cấp Premium - DocShare</title>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="drawer-content flex flex-col bg-base-200 min-h-screen">
        <?php include 'includes/navbar.php'; ?>

        <main class="flex-1 p-6">
            <!-- Breadcrumbs -->
            

            <!-- Header -->
            <div class="text-center mb-12">
                <h1 class="text-4xl font-bold text-primary mb-4 flex items-center justify-center gap-3">
                    <i class="fa-solid fa-crown text-yellow-500"></i>
                    Nâng cấp Premium
                </h1>
                <p class="text-base-content/70 text-lg">Mở khóa toàn bộ kho tài liệu và tải xuống không giới hạn</p>
            </div>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success shadow-lg mb-8 max-w-2xl mx-auto">
                    <i class="fa-solid fa-circle-check"></i>
                    <div>
                        <h3 class="font-bold">Thành công!</h3>
                        <div class="text-xs">Tài khoản của bạn đã được nâng cấp lên Premium.</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Expiration Warning -->
            <?php if($show_expiration_warning && $is_premium): ?>
                <div class="alert alert-warning shadow-lg mb-8 max-w-2xl mx-auto">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div class="flex-1">
                        <h3 class="font-bold">Premium sắp hết hạn!</h3>
                        <div class="text-xs">Gói Premium của bạn sẽ hết hạn trong <?= $days_remaining ?> ngày tới. Gia hạn ngay để không bị gián đoạn.</div>
                    </div>
                    <a href="#pricing" class="btn btn-sm">Gia hạn ngay</a>
                </div>
            <?php endif; ?>

            <!-- Current Status Logic -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-5xl mx-auto">
                
                <!-- Left Column: Current Status & Benefits -->
                <div class="space-y-8">
                    
                    <!-- Status Card -->
                    <div class="card bg-base-100 shadow-xl border border-base-200">
                        <div class="card-body">
                            <h2 class="card-title">
                                <i class="fa-solid fa-user-tag"></i> Trạng thái hiện tại
                            </h2>
                            
                            <div class="flex items-center gap-4 mt-4">
                                <div class="avatar placeholder">
                                    <div class="bg-neutral text-neutral-content rounded-full w-16">
                                        <span class="text-xl">
                                            <?= strtoupper(substr($user_info['username'] ?? 'U', 0, 1)) ?>
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <h3 class="font-bold text-lg"><?= htmlspecialchars($user_info['username'] ?? 'User') ?></h3>
                                    <?php if($is_premium): ?>
                                        <div class="badge badge-warning gap-1">
                                            <i class="fa-solid fa-crown text-xs"></i> Premium Member
                                        </div>
                                        <p class="text-sm mt-1 opacity-70">
                                            Hết hạn: <?= date('d/m/Y', strtotime($premium_info['end_date'])) ?>
                                        </p>
                                    <?php else: ?>
                                        <div class="badge badge-ghost">Free Member</div>
                                        <p class="text-sm mt-1 opacity-70">Chưa kích hoạt Premium</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Benefits List (Expanded) -->
                    <div class="card bg-base-100 shadow-xl">
                        <div class="card-body">
                            <h2 class="card-title text-primary"><i class="fa-solid fa-star"></i> Quyền lợi Premium</h2>
                            
                            <div class="grid grid-cols-1 gap-4 mt-4">
                                <div class="flex items-start gap-4 p-3 bg-base-200 rounded-lg">
                                    <div class="p-2 bg-success/20 rounded-full text-success">
                                        <i class="fa-solid fa-lock-open"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold">Truy cập không giới hạn</h3>
                                        <p class="text-sm opacity-70">Xem và tải xuống hàng ngàn tài liệu chất lượng cao.</p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-4 p-3 bg-base-200 rounded-lg">
                                    <div class="p-2 bg-info/20 rounded-full text-info">
                                        <i class="fa-solid fa-bolt"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold">Tốc độ cao</h3>
                                        <p class="text-sm opacity-70">Tải tài liệu với tốc độ tối đa, không cần chờ đợi.</p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-4 p-3 bg-base-200 rounded-lg">
                                    <div class="p-2 bg-warning/20 rounded-full text-warning">
                                        <i class="fa-solid fa-ban"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold">Không quảng cáo</h3>
                                        <p class="text-sm opacity-70">Trải nghiệm học tập mượt mà không bị làm phiền.</p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-4 p-3 bg-base-200 rounded-lg">
                                    <div class="p-2 bg-secondary/20 rounded-full text-secondary">
                                        <i class="fa-solid fa-headset"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold">Hỗ trợ ưu tiên</h3>
                                        <p class="text-sm opacity-70">Được ưu tiên hỗ trợ khi gặp vấn đề về tài liệu.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                 <!-- Right Column: Paid Plans -->
                 <div class="space-y-8" id="pricing">
                    
                    <!-- Monthly Plan -->
                    <div class="card bg-base-100 shadow-xl border-2 border-primary transform hover:-translate-y-1 transition-transform duration-300">
                        <div class="absolute top-0 right-0">
                            <div class="badge badge-primary rounded-bl-lg rounded-tr-lg p-3 font-bold shadow-md">PHỔ BIẾN NHẤT</div>
                        </div>
                        
                        <div class="card-body text-center">
                            <h2 class="text-2xl font-bold text-base-content">Gói 1 Tháng</h2>
                            <div class="my-6">
                                <span class="text-5xl font-extrabold text-primary">29.000đ</span>
                                <span class="text-base-content/60">/ tháng</span>
                            </div>
                            
                            <p class="mb-6 text-base-content/80">Truy cập đầy đủ tính năng trong 30 ngày. Hủy bất kỳ lúc nào.</p>
                            
                            <form method="POST" class="w-full">
                                <button type="submit" name="buy_premium" class="btn btn-primary btn-block btn-lg shadow-lg hover:shadow-primary/50 transition-all">
                                    <i class="fa-solid fa-cart-shopping mr-2"></i>
                                    Mua Gói Ngay
                                </button>
                            </form>
                            
                            <div class="divider">hoặc</div>

                            <!-- Lifetime Mockup -->
                            <div class="opacity-60 grayscale filter hover:grayscale-0 hover:opacity-100 transition-all duration-300">
                                 <h3 class="font-bold text-lg mb-2">Gói Vĩnh Viễn</h3>
                                 <p class="text-2xl font-bold">99.000đ</p>
                                 <button class="btn btn-outline btn-sm mt-3" disabled>Sắp ra mắt</button>
                            </div>
                        </div>
                    </div>

                    <!-- FAQ -->
                    <div class="collapse collapse-plus bg-base-100 shadow-xl">
                        <input type="checkbox" /> 
                        <div class="collapse-title text-lg font-medium">
                            <i class="fa-regular fa-circle-question mr-2"></i> Câu hỏi thường gặp
                        </div>
                        <div class="collapse-content"> 
                            <div class="space-y-4 pt-2">
                                <div>
                                    <h4 class="font-bold text-sm">Tôi có thể hủy gói bất cứ lúc nào?</h4>
                                    <p class="text-xs opacity-70">Đúng vậy, bạn có thể hủy gia hạn bất kỳ lúc nào và vẫn giữ quyền lợi đến hết chu kỳ.</p>
                                </div>
                                <div>
                                    <h4 class="font-bold text-sm">Thanh toán như thế nào?</h4>
                                    <p class="text-xs opacity-70">Chúng tôi hỗ trợ chuyển khoản ngân hàng, MoMo và thẻ tín dụng (sắp ra mắt).</p>
                                </div>
                            </div>
                        </div>
                    </div>

                 </div>
            </div>

            <!-- Payment Modal (Hidden by default, shown via query param) -->
            <?php if(isset($_GET['payment']) && $_GET['payment'] == 'bank_transfer'): ?>
            <input type="checkbox" id="payment-modal" class="modal-toggle" checked />
            <div class="modal modal-bottom sm:modal-middle">
                <div class="modal-box">
                    <h3 class="font-bold text-lg text-primary flex items-center gap-2">
                        <i class="fa-solid fa-money-bill-transfer"></i> Thông tin chuyển khoản
                    </h3>
                    <p class="py-4">Vui lòng chuyển khoản theo thông tin dưới đây để kích hoạt Premium:</p>
                    
                    <div class="bg-base-200 p-4 rounded-lg space-y-3 mb-4">
                        <div class="flex justify-between border-b border-base-content/10 pb-2">
                            <span class="opacity-70">Ngân hàng:</span>
                            <span class="font-bold">MB Bank (Quân Đội)</span>
                        </div>
                        <div class="flex justify-between border-b border-base-content/10 pb-2">
                            <span class="opacity-70">Số tài khoản:</span>
                            <span class="font-bold flex items-center gap-2">
                                999999999
                                <button class="btn btn-ghost btn-xs" onclick="navigator.clipboard.writeText('999999999')"><i class="fa-regular fa-copy"></i></button>
                            </span>
                        </div>
                         <div class="flex justify-between border-b border-base-content/10 pb-2">
                            <span class="opacity-70">Chủ tài khoản:</span>
                            <span class="font-bold">ADMIN DOCSHARE</span>
                        </div>
                        <div class="flex justify-between border-b border-base-content/10 pb-2">
                            <span class="opacity-70">Số tiền:</span>
                            <span class="font-bold text-primary">29.000 VNĐ</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="opacity-70">Nội dung:</span>
                            <span class="font-bold text-secondary">PREMIUM <?= $user_id ?></span>
                        </div>
                    </div>
                    
                    <div class="alert alert-info shadow-sm text-xs">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>Sau khi chuyển khoản, vui lòng chụp màn hình và gửi cho Admin qua Telegram/Zalo để được kích hoạt nhanh nhất.</span>
                    </div>

                    <div class="modal-action">
                        <a href="premium" class="btn">Đóng</a>
                        <a href="https://t.me/admin" target="_blank" class="btn btn-primary">
                            <i class="fa-brands fa-telegram"></i> Liên hệ Admin
                        </a>
                    </div>
                </div>
                <label class="modal-backdrop" for="payment-modal">Close</label>
            </div>
            <?php endif; ?>
        </main>

        <?php include 'includes/footer.php'; ?>
    </div>
    </div> <!-- Close drawer -->
</body>
</html>