<?php
require_once __DIR__ . '/../includes/error_handler.php';
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/tutor.php';
require_once __DIR__ . '/../config/points.php';

redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
if (!isTutor($user_id)) {
    header("Location: ../index.php");
    exit;
}

$points_data = getUserPoints($user_id);
$page_title = 'Rút tiền - Gia Sư';
$exchange_rate = intval(getSetting('shop_exchange_rate', 1000));

// Basic validation for existing pending requests
$pdo = getTutorDBConnection();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM withdrawal_requests WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$has_pending = $stmt->fetchColumn() > 0;

include __DIR__ . '/../includes/head.php';
?>

<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --vsd-red: #991b1b;
    }
    
    [data-theme="dark"] {
        --glass-bg: rgba(15, 23, 42, 0.7);
    }

    .withdraw-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 40px 24px;
    }

    .glass-card-vsd {
        background: var(--glass-bg);
        backdrop-filter: blur(30px);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 2.5rem;
        padding: 40px;
    }

    .points-summary {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 40px;
    }

    .summary-item {
        padding: 24px;
        border-radius: 2rem;
        background: oklch(var(--b2) / 0.5);
    }

    .vsd-input {
        background: oklch(var(--b2) / 0.5) !important;
        border: 1px solid oklch(var(--bc) / 0.1) !important;
        border-radius: 1.5rem !important;
        height: 60px !important;
        font-weight: 800 !important;
        font-size: 1.2rem !important;
    }

    .vsd-btn-withdraw {
        width: 100%;
        height: 60px;
        background: #000000;
        color: white;
        border-radius: 1.5rem;
        font-weight: 1000;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-top: 20px;
        border: none;
        cursor: pointer;
    }

    .withdraw-info-box {
        background: rgba(153, 27, 27, 0.05);
        border: 1px solid rgba(153, 27, 27, 0.1);
        padding: 24px;
        border-radius: 2rem;
        margin-top: 30px;
    }
</style>

<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="drawer-content flex flex-col min-h-screen">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>
        
        <main class="flex-1">
            <div class="withdraw-container">
                <div class="mb-10 text-center">
                    <h1 class="text-4xl font-black tracking-tight mb-2">Rút Tiền</h1>
                    <p class="text-sm opacity-50 font-bold">Quy đổi Topup Points thành tiền mặt mang về.</p>
                </div>

                <div class="points-summary">
                    <div class="summary-item">
                        <div class="text-[10px] font-black opacity-30 uppercase tracking-widest mb-1">Topup VSD (Có thể rút)</div>
                        <div class="text-3xl font-black text-success"><?= number_format($points_data['topup_points']) ?> VSD</div>
                    </div>
                    <div class="summary-item">
                        <div class="text-[10px] font-black opacity-30 uppercase tracking-widest mb-1">Bonus VSD (Nội bộ)</div>
                        <div class="text-3xl font-black opacity-30"><?= number_format($points_data['bonus_points']) ?> VSD</div>
                    </div>
                </div>

                <div class="glass-card-vsd">
                    <?php if($has_pending): ?>
                        <div class="text-center py-10">
                            <i class="fa-solid fa-clock-rotate-left text-4xl text-warning mb-4"></i>
                            <h3 class="font-black text-xl">Đang xử lý yêu cầu trước</h3>
                            <p class="text-sm opacity-50 font-bold">Bạn đã có một yêu cầu rút tiền đang chờ duyệt. Vui lòng đợi Admin xử lý.</p>
                        </div>
                    <?php elseif($points_data['topup_points'] < 50): ?>
                        <div class="text-center py-10">
                            <i class="fa-solid fa-circle-exclamation text-4xl text-error mb-4"></i>
                            <h3 class="font-black text-xl">Số dư không đủ</h3>
                            <p class="text-sm opacity-50 font-bold">Bạn cần tối thiểu 50 Topup Points để thực hiện rút tiền.</p>
                        </div>
                    <?php else: ?>
                        <form id="withdrawForm" class="space-y-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black opacity-30 uppercase tracking-widest px-4">Số điểm muốn rút</label>
                                <input type="number" name="points" id="withdraw_points" min="50" max="<?= $points_data['topup_points'] ?>" value="50" class="input vsd-input w-full" oninput="updateReceivedValue(this.value)">
                            </div>
                            
                            <div class="space-y-2">
                                <label class="text-[10px] font-black opacity-30 uppercase tracking-widest px-4">Thông tin ngân hàng (Bank - STK - Tên)</label>
                                <input type="text" name="bank_info" required placeholder="Ví dụ: MB Bank - 999999999 - NGUYEN VAN A" class="input vsd-input w-full h-[80px] text-sm !font-bold">
                            </div>

                            <div class="withdraw-info-box flex justify-between items-center">
                                <div>
                                    <div class="text-[10px] font-black opacity-40 uppercase tracking-widest">Tiền thực nhận (1 VSD = <?= number_format($exchange_rate) ?>đ)</div>
                                    <div class="text-3xl font-black text-primary" id="received_value">0 VNĐ</div>
                                </div>
                                <i class="fa-solid fa-arrow-right-arrow-left text-2xl opacity-10"></i>
                            </div>

                            <button type="submit" class="vsd-btn-withdraw">Gửi yêu cầu rút tiền</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <?php include __DIR__ . '/../includes/footer.php'; ?>
    </div>

    <script>
        const EXCHANGE_RATE = <?= $exchange_rate ?>;
        function updateReceivedValue(pts) {
            const val = pts * EXCHANGE_RATE;
            document.getElementById('received_value').innerText = val.toLocaleString() + ' VNĐ';
        }

        document.getElementById('withdrawForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'request_withdrawal');

            try {
                const res = await fetch('../handler/tutor_handler.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if(data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.message, 'error');
                }
            } catch(err) {
                showAlert('Có lỗi xảy ra', 'error');
            }
        });
    </script>
</body>
</html>
