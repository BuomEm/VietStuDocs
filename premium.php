<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: index.php");
}

require_once 'config/db.php';
require_once 'config/auth.php';
require_once 'config/premium.php';

redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$is_premium = isPremium($user_id);
$premium_info = getPremiumInfo($user_id);
$verified_docs = getVerifiedDocumentsCount($user_id);

// Calculate Premium expiration countdown
$days_remaining = null;
$hours_remaining = null;
$minutes_remaining = null;
$show_expiration_warning = false;

if($is_premium && $premium_info) {
    $end_date = new DateTime($premium_info['end_date']);
    $now = new DateTime();
    $interval = $now->diff($end_date);
    
    $days_remaining = $interval->days;
    $hours_remaining = $interval->h;
    $minutes_remaining = $interval->i;
    
    // Show warning if less than 7 days
    if($days_remaining < 7) {
        $show_expiration_warning = true;
    }
}

// Handle payment (Simple mock payment)
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['buy_premium'])) {
    // In real application, integrate with payment gateway (Stripe, PayPal, etc.)
    // For now, we'll just activate premium
    activateMonthlyPremium($user_id);
    
    // Log transaction
    mysqli_query($conn, "
        INSERT INTO transactions (user_id, amount, transaction_type, status) 
        VALUES ($user_id, 29.00, 'monthly', 'success')
    ");
    
    header("Location: premium.php?success=1");
    exit;
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premium - DocShare</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        nav {
            background: #667eea;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        nav a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }
        h1 {
            text-align: center;
            color: #667eea;
            margin-bottom: 10px;
            font-size: 32px;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 40px;
            font-size: 16px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 8px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            text-align: center;
        }
        .countdown-warning {
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 10px;
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
            border-left: 6px solid #ff6f00;
        }
        .countdown-warning h4 {
            margin-bottom: 12px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .countdown-timer {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 15px 0;
        }
        .time-unit {
            background: rgba(255, 255, 255, 0.2);
            padding: 12px;
            border-radius: 8px;
            text-align: center;
        }
        .time-number {
            font-size: 24px;
            font-weight: 700;
            display: block;
        }
        .time-label {
            font-size: 11px;
            text-transform: uppercase;
            opacity: 0.9;
        }
        .renew-btn {
            display: inline-block;
            margin-top: 15px;
            background: white;
            color: #ff9800;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }
        .renew-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }
        .pricing-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
        }
        .pricing-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .pricing-card.popular {
            border: 3px solid #ffd700;
            transform: scale(1.05);
        }
        .popular-badge {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #333;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 12px;
        }
        .pricing-card h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 24px;
        }
        .price {
            font-size: 36px;
            font-weight: bold;
            color: #333;
            margin: 20px 0;
        }
        .currency {
            font-size: 16px;
            color: #666;
        }
        .period {
            color: #999;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .features {
            text-align: left;
            margin: 30px 0;
            list-style: none;
        }
        .features li {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        .features li:before {
            content: "✓ ";
            color: #28a745;
            font-weight: bold;
            margin-right: 8px;
        }
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #764ba2;
        }
        .btn-popular {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #333;
        }
        .btn-popular:hover {
            opacity: 0.9;
        }
        .free-trial-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .free-trial-section h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 20px;
        }
        .progress-bar {
            background: #f0f0f0;
            height: 30px;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            width: 0%;
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .stat-box {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 30px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .badge-active {
            background: #d4edda;
            color: #155724;
        }
        .badge-inactive {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <?php include 'includes/head.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

        <h1>👑 Premium Membership</h1>
        <p class="subtitle">Nâng cấp để xem và tải toàn bộ tài liệu được chia sẻ</p>

        <?php if(isset($_GET['success'])): ?>
            <div class="alert">✅ Nâng cấp thành công! Bạn hiện là thành viên Premium.</div>
        <?php endif; ?>

        <!-- Premium Expiration Warning -->
        <?php if($show_expiration_warning && $is_premium): ?>
            <div class="countdown-warning">
                <h4>⏰ Premium sắp hết hạn!</h4>
                <p style="margin-bottom: 15px; font-size: 14px;">
                    Premium của bạn sẽ hết hạn vào lúc:
                    <strong><?= date('d/m/Y H:i', strtotime($premium_info['end_date'])) ?></strong>
                </p>
                
                <div class="countdown-timer">
                    <div class="time-unit">
                        <span class="time-number"><?= $days_remaining ?></span>
                        <span class="time-label">Days</span>
                    </div>
                    <div class="time-unit">
                        <span class="time-number"><?= $hours_remaining ?></span>
                        <span class="time-label">Hours</span>
                    </div>
                    <div class="time-unit">
                        <span class="time-number"><?= $minutes_remaining ?></span>
                        <span class="time-label">Minutes</span>
                    </div>
                </div>
                
                <p style="font-size: 13px; margin-top: 15px;">
                    Hãy gia hạn ngay để không mất quyền truy cập!
                </p>
                <a href="#pricing" class="renew-btn">🔄 Gia hạn Premium</a>
            </div>
        <?php endif; ?>

        <!-- Current Status -->
        <div style="text-align: center; margin-bottom: 30px;">
            <?php if($is_premium): ?>
                <span class="status-badge badge-active">⭐ Bạn đang là Premium</span>
                <div style="font-size: 13px; color: #666; margin-top: 10px;">
                    Hết hạn: <strong><?= date('d/m/Y H:i', strtotime($premium_info['end_date'])) ?></strong>
                </div>
            <?php else: ?>
                <span class="status-badge badge-inactive">📄 Bạn đang là Free User</span>
            <?php endif; ?>
        </div>

        <!-- Free Trial Section -->
        <div class="free-trial-section">
            <h3>🎁 Cách lấy Premium Miễn Phí</h3>
            <p style="margin-bottom: 20px;">Đăng 3 tài liệu đạt tiêu chuẩn → Nhận <strong>7 ngày Premium miễn phí!</strong></p>
            
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= min(100, ($verified_docs / 3) * 100) ?>%">
                    <?php if($verified_docs < 3): ?>
                        <?= $verified_docs ?>/3
                    <?php else: ?>
                        ✓ Hoàn thành!
                    <?php endif; ?>
                </div>
            </div>

            <div class="stats">
                <div class="stat-box">
                    <div class="stat-number"><?= $verified_docs ?>/3</div>
                    <div class="stat-label">Tài liệu đạt chuẩn</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?= max(0, 3 - $verified_docs) ?></div>
                    <div class="stat-label">Còn cần</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">7</div>
                    <div class="stat-label">Ngày miễn phí</div>
                </div>
            </div>

            <?php if($verified_docs >= 3): ?>
                <p style="margin-top: 20px; padding: 15px; background: #e8f5e9; border-radius: 6px; color: #2e7d32; border-left: 4px solid #28a745;">
                    <strong>✅ Bạn đã đủ điều kiện!</strong><br>
                    Tiếp tục đăng tài liệu để kích hoạt Premium miễn phí 7 ngày.
                </p>
            <?php else: ?>
                <p style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 6px; color: #664d03; border-left: 4px solid #ffc107;">
                    Bạn cần đăng thêm <strong><?= 3 - $verified_docs ?></strong> tài liệu nữa để nhận Premium miễn phí.
                </p>
                <a href="upload.php" style="display: inline-block; margin-top: 15px; color: white; background: #667eea; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600;">📤 Đăng tài liệu ngay →</a>
            <?php endif; ?>
        </div>

        <!-- Pricing Plans -->
        <h2 id="pricing" style="text-align: center; color: #667eea; margin-bottom: 30px; font-size: 24px;">Gói Premium Có Trả Phí</h2>
        
        <div class="pricing-grid">
            <!-- Monthly Plan -->
            <div class="pricing-card popular">
                <div class="popular-badge">⭐ PHỔ BIẾN NHẤT</div>
                <h2>Premium 1 Tháng</h2>
                <div class="price">
                    <span class="currency">₫</span>29.000
                </div>
                <div class="period">1 tháng (30 ngày)</div>
                
                <ul class="features">
                    <li>Xem toàn bộ tài liệu được chia sẻ</li>
                    <li>Tải xuống không giới hạn</li>
                    <li>Chia sẻ tài liệu với bạn bè</li>
                    <li>Ưu tiên hỗ trợ</li>
                    <li>Không quảng cáo</li>
                </ul>

                <form method="POST">
                    <button type="submit" name="buy_premium" class="btn btn-popular">Mua Ngay</button>
                </form>
            </div>

            <!-- Lifetime Plan (Future) -->
            <div class="pricing-card">
                <h2>Premium Vĩnh Viễn</h2>
                <div class="price">
                    <span class="currency">₫</span>99.000
                </div>
                <div class="period">Một lần thanh toán</div>
                
                <ul class="features">
                    <li>Xem toàn bộ tài liệu được chia sẻ</li>
                    <li>Tải xuống không giới hạn</li>
                    <li>Chia sẻ tài liệu với bạn bè</li>
                    <li>Hỗ trợ ưu tiên</li>
                    <li>Không quảng cáo</li>
                    <li>Truy cập vĩnh viễn</li>
                </ul>

                <button class="btn btn-primary" onclick="alert('Tính năng sắp ra mắt!')">Sắp Ra Mắt</button>
            </div>
        </div>

        <!-- FAQ Section -->
        <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h3 style="color: #667eea; margin-bottom: 20px;">❓ Câu Hỏi Thường Gặp</h3>
            
            <div style="margin-bottom: 20px;">
                <h4 style="color: #333; margin-bottom: 8px;">Tôi có thể hủy bỏ Premium bất cứ lúc nào không?</h4>
                <p style="color: #666; font-size: 14px;">Có, bạn có thể hủy bỏ bất cứ lúc nào. Bạn sẽ vẫn sử dụng Premium cho đến khi gói hết hạn.</p>
            </div>

            <div style="margin-bottom: 20px;">
                <h4 style="color: #333; margin-bottom: 8px;">Làm thế nào để tài liệu của tôi được xác nhận?</h4>
                <p style="color: #666; font-size: 14px;">Các tài liệu bạn đăng lên sẽ được tự động xác nhận trong hệ thống. Bạn cần đăng 3 tài liệu khác nhau.</p>
            </div>

            <div style="margin-bottom: 20px;">
                <h4 style="color: #333; margin-bottom: 8px;">Có hoàn tiền nếu không hài lòng không?</h4>
                <p style="color: #666; font-size: 14px;">Bạn sẽ nhận được hoàn tiền 100% nếu không hài lòng trong vòng 7 ngày đầu tiên.</p>
            </div>

            <div style="margin-bottom: 20px;">
                <h4 style="color: #333; margin-bottom: 8px;">Premium miễn phí hết hạn sau bao lâu?</h4>
                <p style="color: #666; font-size: 14px;">Premium miễn phí từ việc đăng 3 tài liệu sẽ hết hạn sau 7 ngày. Sau đó, bạn có thể mua gói Premium hoặc đăng thêm tài liệu để nhận lại miễn phí.</p>
            </div>

            <div>
                <h4 style="color: #333; margin-bottom: 8px;">❓ Tôi đã có Premium nhưng nó hết hạn, làm sao lấy lại?</h4>
                <p style="color: #666; font-size: 14px;">Sau khi Premium hết hạn, bạn có thể bắt đầu lại từ đầu - đăng 3 tài liệu mới để nhận 7 ngày Premium miễn phí, hoặc mua gói Premium trả phí.</p>
            </div>
        </div>
    </div>
</body>
</html>