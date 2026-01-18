<?php
require_once __DIR__ . '/../includes/error_handler.php';
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../config/points.php';
require_once '../config/settings.php';

redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$points_data = getUserPoints($user_id);
$page_title = 'Nạp Điểm - VietStuDocs';

// Load dynamic packages from settings
$packages = [];
for($i=1; $i<=5; $i++) {
    $price = getSetting("shop_pkg{$i}_price");
    if($price) {
        $packages[] = [
            'id' => $i,
            'price' => intval($price),
            'topup' => intval(getSetting("shop_pkg{$i}_topup")),
            'bonus' => intval(getSetting("shop_pkg{$i}_bonus")),
            'label' => $i == 2 ? 'Gói Phổ Thông' : ($i == 1 ? 'Gói Khởi Đầu' : ($i == 3 ? 'Gói Ưu Đãi' : ($i == 4 ? 'Gói Tiết Kiệm' : 'Gói Chuyên Gia'))),
            'popular' => isSettingEnabled("shop_pkg{$i}_popular")
        ];
    }
}

// Fallback if no packages configured
// if(empty($packages)) {
//     $packages = [
//         ['id' => 1, 'price' => 20000, 'topup' => 20, 'bonus' => 0, 'label' => 'Gói Khởi Đầu'],
//         ['id' => 2, 'price' => 50000, 'topup' => 50, 'bonus' => 5, 'label' => 'Gói Phổ Thông', 'popular' => true],
//         ['id' => 3, 'price' => 100000, 'topup' => 100, 'bonus' => 15, 'label' => 'Gói Ưu Đãi'],
//         ['id' => 4, 'price' => 200000, 'topup' => 200, 'bonus' => 40, 'label' => 'Gói Tiết Kiệm'],
//         ['id' => 5, 'price' => 500000, 'topup' => 500, 'bonus' => 120, 'label' => 'Gói Chuyên Gia']
//     ];
// }

include '../includes/head.php';
?>

<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --vsd-red: #991b1b;
        --red-gradient: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%);
    }
    
    [data-theme="dark"] {
        --glass-bg: rgba(15, 23, 42, 0.7);
    }

    .shop-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px 24px;
    }

    .glass-card-vsd {
        background: var(--glass-bg);
        backdrop-filter: blur(30px);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 2.5rem;
        padding: 40px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05);
    }

    .package-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 24px;
        margin-top: 40px;
    }

    .package-card {
        padding: 32px;
        text-align: center;
        transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
        position: relative;
        overflow: hidden;
        border: 2px solid transparent;
    }

    .package-card:hover {
        transform: translateY(-10px);
        border-color: var(--vsd-red);
        background: var(--glass-bg);
        box-shadow: 0 30px 60px -15px rgba(0,0,0,0.1);
    }

    .package-card.popular {
        border-color: var(--vsd-red);
        background: rgba(153, 27, 27, 0.02);
    }

    .popular-badge {
        position: absolute;
        top: 20px;
        right: -35px;
        background: var(--vsd-red);
        color: white;
        padding: 8px 40px;
        transform: rotate(45deg);
        font-size: 10px;
        font-weight: 900;
        letter-spacing: 0.1em;
    }

    .package-price {
        font-size: 2.5rem;
        font-weight: 1000;
        letter-spacing: -2px;
        margin: 20px 0;
        color: var(--vsd-red);
    }

    .points-info {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 30px;
    }

    .point-line {
        font-size: 1.1rem;
        font-weight: 800;
    }

    .bonus-tag {
        background: #22c55e;
        color: white;
        padding: 4px 12px;
        border-radius: 100px;
        font-size: 10px;
        font-weight: 900;
        margin-left: 8px;
        text-transform: uppercase;
    }

    .vsd-btn-buy {
        width: 100%;
        height: 56px;
        background: var(--vsd-red);
        color: white;
        border-radius: 1.25rem;
        font-weight: 1000;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
    }

    .vsd-btn-buy:hover {
        filter: brightness(1.2);
        transform: scale(1.02);
    }

    /* Modal Styling */
    .modal-box-vsd {
        background: var(--glass-bg) !important;
        backdrop-filter: blur(50px) !important;
        border-radius: 3rem !important;
        padding: 50px !important;
        max-width: 500px !important;
        border: 1px solid rgba(255,255,255,0.1) !important;
    }
</style>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="drawer-content flex flex-col min-h-screen">
        <?php include '../includes/navbar.php'; ?>
        
        <main class="flex-1">
            <div class="shop-container">
                <div class="flex flex-col md:flex-row justify-between items-center gap-8 mb-12">
                    <div>
                        <h1 class="text-4xl font-black tracking-tight mb-2">Cửa hàng Điểm</h1>
                        <p class="text-sm opacity-50 font-bold">Nạp điểm để dùng các dịch vụ Gia sư và mua tài liệu.</p>
                    </div>
                    <div class="glass-card-vsd !p-6 flex items-center gap-6">
                        <div class="text-right">
                            <div class="text-[10px] font-black opacity-30 uppercase tracking-widest">Số dư hiện tại</div>
                            <div class="text-2xl font-black text-primary"><?= number_format($points_data['current_points']) ?> VSD</div>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-primary/10 flex items-center justify-center text-primary text-xl">
                            <i class="fa-solid fa-coins"></i>
                        </div>
                    </div>
                </div>

                <div class="package-grid">
                    <?php foreach($packages as $pkg): ?>
                        <div class="glass-card-vsd package-card <?= $pkg['popular'] ? 'popular' : '' ?>">
                            <?php if($pkg['popular']): ?>
                                <div class="popular-badge">PHỔ BIẾN</div>
                            <?php endif; ?>
                            
                            <h3 class="text-xs font-black opacity-40 uppercase tracking-widest"><?= $pkg['label'] ?></h3>
                            <div class="package-price"><?= number_format($pkg['price'] / 1000) ?>k <span class="text-xs opacity-30">VNĐ</span></div>
                            
                            <div class="points-info">
                                <div class="point-line">
                                    <?= $pkg['topup'] ?> Topup VSD
                                </div>
                                <?php if($pkg['bonus'] > 0): ?>
                                    <div class="point-line text-success">
                                        +<?= $pkg['bonus'] ?> Bonus VSD <span class="bonus-tag">TẶNG KÈM</span>
                                    </div>
                                <?php else: ?>
                                    <div class="point-line opacity-0 pointer-events-none">
                                        &nbsp;
                                    </div>
                                <?php endif; ?>
                            </div>

                            <button onclick="openPaymentModal(<?= $pkg['id'] ?>, <?= $pkg['price'] ?>, <?= $pkg['topup'] + $pkg['bonus'] ?>)" class="vsd-btn-buy">Nạp ngay</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>

        <?php include '../includes/footer.php'; ?>
    </div>

    <!-- Payment Modal -->
    <dialog id="payment_modal" class="modal">
        <div class="modal-box modal-box-vsd relative">
            <div class="text-center">
                <div class="w-20 h-20 bg-primary/10 rounded-3xl flex items-center justify-center text-3xl text-primary mx-auto mb-6">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <h3 class="font-black text-2xl uppercase tracking-tighter">Thanh toán</h3>
                <p class="text-xs font-bold opacity-40 mt-2 mb-8">Vui lòng chuyển khoản đúng thông tin bên dưới</p>
                
                <div class="bg-base-200/50 rounded-3xl p-6 text-left space-y-4 mb-8">
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-black opacity-30 uppercase">Ngân hàng</span>
                        <span class="font-black text-sm"><?= htmlspecialchars(getSetting('shop_bank_name', 'MB BANK')) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-black opacity-30 uppercase">Số tài khoản</span>
                        <div class="flex items-center gap-2">
                            <span class="font-black text-sm"><?= htmlspecialchars(getSetting('shop_bank_number', '999999999')) ?></span>
                            <button onclick="copyToClipboard('<?= htmlspecialchars(getSetting('shop_bank_number', '999999999')) ?>')" class="btn btn-ghost btn-xs btn-circle"><i class="fa-regular fa-copy"></i></button>
                        </div>
                    </div>
                    <?php if($bank_owner = getSetting('shop_bank_owner')): ?>
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-black opacity-30 uppercase">Chủ tài khoản</span>
                        <span class="font-black text-sm uppercase"><?= htmlspecialchars($bank_owner) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-black opacity-30 uppercase">Số tiền</span>
                        <span class="font-black text-lg text-primary" id="modal_price">0 VNĐ</span>
                    </div>
                    <div class="pt-4 border-t border-base-content/5">
                        <span class="text-[10px] font-black opacity-30 uppercase block mb-2">Nội dung chuyển khoản</span>
                        <div class="flex items-center justify-between p-4 bg-primary/5 rounded-2xl border border-primary/10">
                            <span class="font-black text-lg tracking-widest" id="modal_ref">NAP <?= $user_id ?> PKG1</span>
                            <button onclick="copyToClipboard(document.getElementById('modal_ref').innerText)" class="btn btn-primary btn-sm rounded-xl">Sao chép</button>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <button onclick="document.getElementById('payment_modal').close()" class="btn btn-ghost rounded-2xl h-14 font-black text-xs opacity-30">Đóng</button>
                    <a href="https://t.me/admin" target="_blank" class="btn btn-primary rounded-2xl h-14 font-black text-xs">Hỗ trợ ngay</a>
                </div>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <script>
        function openPaymentModal(pkgId, price, points) {
            document.getElementById('modal_price').innerText = price.toLocaleString() + ' VNĐ';
            document.getElementById('modal_ref').innerText = 'NAP <?= $user_id ?> PKG' + pkgId;
            document.getElementById('payment_modal').showModal();
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text);
            showAlert('Đã sao chép vào bộ nhớ tạm', 'success');
        }
    </script>
</body>
</html>
